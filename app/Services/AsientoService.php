<?php
namespace App\Services;

use App\Models\AnticipoProveedor;
use App\Models\AsientoContable;
use App\Models\AsientoDetalle;
use App\Models\EjercicioContable;
use App\Models\ParametroContable;
use App\Models\PlanCuenta;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class AsientoService
{
    // ══════════════════════════════════════════════════════════
    // MÉTODO BASE — todos los demás lo llaman internamente
    // ══════════════════════════════════════════════════════════
    public function crear(
        int     $empresaId,
        string  $concepto,
        array   $partidas,
        string  $documentoTipo  = 'MANUAL',
        ?int    $documentoId    = null,
        ?string $documentoRef   = null,
        bool    $esAutomatico   = false,
        ?string $fecha          = null,
    ): AsientoContable {

        // 1. Verificar período contable abierto
        //
        // El candado debe ser sensible a la FECHA del asiento, no solo a si existe
        // *algún* período abierto para la empresa: sin esto, un asiento (manual o
        // automático) con fecha dentro de un mes ya cerrado se colaba igual,
        // quedando vinculado al período abierto actual pero con una fecha que
        // pertenece a un mes que ya no debería aceptar movimientos nuevos.
        // El asiento SIEMPRE pertenece al período de SU PROPIA fecha.
        //
        // Antes esto se resolvía en dos pasos inconsistentes: se validaba el
        // período de la fecha, pero después se guardaba `ejercicio_id` = el
        // último período abierto de la empresa. Consecuencias reales:
        //   - un asiento con fecha de un mes SIN fila en ejercicios_contables
        //     pasaba el candado sin control y quedaba archivado en el período
        //     abierto actual (agujero para registrar en meses pasados o
        //     futuros arbitrarios);
        //   - el Cierre Fiscal Anual selecciona los asientos del año vía
        //     ejercicio->anio, así que los asientos mal clasificados quedaban
        //     fuera del cierre (o dentro del año equivocado);
        //   - el filtro "Ejercicio" de Asientos y de los reportes devolvía
        //     movimientos que no son de ese mes.
        $fechaAsiento = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        $ejercicio    = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $fechaAsiento->year)
            ->where('mes', $fechaAsiento->month)
            ->first();

        if (!$ejercicio) {
            throw new \Exception(
                'No existe el período contable ' . $fechaAsiento->format('m/Y') . '. ' .
                'Ábralo en Contabilidad → Ejercicios antes de registrar movimientos con esa fecha.'
            );
        }

        if ($ejercicio->estaCerrado()) {
            throw new \Exception(
                "El período {$ejercicio->periodo_label} está cerrado. " .
                "No se pueden crear ni modificar asientos con fecha en un período cerrado."
            );
        }

        // 2. Validar DEBE = HABER (partida doble)
        $totalDebe  = collect($partidas)->sum(fn($p) => (float)($p['debe']  ?? 0));
        $totalHaber = collect($partidas)->sum(fn($p) => (float)($p['haber'] ?? 0));

        if (abs($totalDebe - $totalHaber) > 0.0001) {
            $diff = number_format(abs($totalDebe - $totalHaber), 2);
            throw new \Exception(
                "El asiento no cuadra. DEBE: \${$totalDebe} ≠ HABER: \${$totalHaber}. " .
                "Diferencia: \${$diff}"
            );
        }

        // 3. Validar mínimo 2 partidas
        if (count($partidas) < 2) {
            throw new \Exception('Un asiento requiere mínimo 2 partidas.');
        }

        // 4. Validar que cada cuenta permite asientos
        foreach ($partidas as $index => $partida) {
            $cuenta = PlanCuenta::find($partida['cuenta_id'] ?? null);
            if (!$cuenta) {
                throw new \Exception("Partida #" . ($index + 1) . ": cuenta no encontrada.");
            }
            if (!$cuenta->permite_asientos) {
                throw new \Exception(
                    "La cuenta {$cuenta->codigo} — {$cuenta->nombre} " .
                    "no permite asientos. Solo cuentas hoja (nivel 4) aceptan movimientos."
                );
            }
            if (!$cuenta->estado) {
                throw new \Exception(
                    "La cuenta {$cuenta->codigo} — {$cuenta->nombre} está inactiva."
                );
            }
        }

        // 5. Crear en transacción.
        //
        // El número se genera con MAX()+1, así que dos guardados simultáneos
        // pueden pedir el mismo. Con el índice único (empresa_id, numero) eso
        // ahora revienta en vez de duplicar en silencio: se reintenta unas
        // pocas veces tomando el siguiente número libre.
        $intentos = 0;
        while (true) {
            try {
                return $this->persistir(
                    $empresaId, $ejercicio, $concepto, $partidas,
                    $documentoTipo, $documentoId, $documentoRef,
                    $esAutomatico, $fecha, $totalDebe, $totalHaber
                );
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if (++$intentos >= 5) {
                    throw new \Exception(
                        'No se pudo asignar un número de asiento libre. Intente nuevamente.'
                    );
                }
                usleep(50_000 * $intentos);
            }
        }
    }

    private function persistir(
        int     $empresaId,
        EjercicioContable $ejercicio,
        string  $concepto,
        array   $partidas,
        ?string $documentoTipo,
        ?int    $documentoId,
        ?string $documentoRef,
        bool    $esAutomatico,
        ?string $fecha,
        float   $totalDebe,
        float   $totalHaber,
    ): AsientoContable {
        return DB::transaction(function () use (
            $empresaId, $ejercicio, $concepto, $partidas,
            $documentoTipo, $documentoId, $documentoRef,
            $esAutomatico, $fecha, $totalDebe, $totalHaber
        ) {
            $anio   = $fecha ? (int)date('Y', strtotime($fecha)) : now()->year;
            $numero = AsientoContable::generarNumero($empresaId, $anio);

            $asiento = AsientoContable::create([
                'empresa_id'     => $empresaId,
                'ejercicio_id'   => $ejercicio->id,
                'numero'         => $numero,
                'fecha'          => $fecha ?? now()->toDateString(),
                'concepto'       => $concepto,
                'documento_tipo' => $documentoTipo,
                'documento_id'   => $documentoId,
                'documento_ref'  => $documentoRef,
                'total_debe'     => $totalDebe,
                'total_haber'    => $totalHaber,
                'es_automatico'  => $esAutomatico,
                'estado'         => 1,
                'creado_por'     => Auth::id(),
                'created_at'     => now(),
            ]);

            foreach ($partidas as $partida) {
                AsientoDetalle::create([
                    'asiento_id'      => $asiento->id,
                    'cuenta_id'       => $partida['cuenta_id'],
                    'centro_costo_id' => $partida['centro_costo_id'] ?? null,
                    'descripcion'     => $partida['descripcion']     ?? null,
                    'debe'            => (float)($partida['debe']    ?? 0),
                    'haber'           => (float)($partida['haber']   ?? 0),
                ]);
            }

            // Actualizar contador en plan_cuentas
            $cuentaIds = collect($partidas)->pluck('cuenta_id')->unique();
            PlanCuenta::whereIn('id', $cuentaIds)->increment('total_asientos');

            $this->registrarAuditoria('crear', $asiento);

            return $asiento;
        });
    }

    // ══════════════════════════════════════════════════════════
    // ANULAR — genera reversión, nunca borra
    // ══════════════════════════════════════════════════════════
    public function anular(AsientoContable $asiento, string $motivo): AsientoContable
    {
        if ($asiento->estaAnulado()) {
            throw new \Exception("El asiento {$asiento->numero} ya está anulado.");
        }

        $ejercicio = $asiento->ejercicio;
        if ($ejercicio && $ejercicio->estaCerrado()) {
            throw new \Exception(
                "El período {$ejercicio->periodo_label} está cerrado. " .
                "No se puede anular asientos en períodos cerrados."
            );
        }

        return DB::transaction(function () use ($asiento, $motivo) {
            $asiento->update(['estado' => 0]);

            $partidas = $asiento->detalles->map(fn($d) => [
                'cuenta_id'       => $d->cuenta_id,
                'centro_costo_id' => $d->centro_costo_id,
                'descripcion'     => "REVERSA: " . ($d->descripcion ?? $asiento->concepto),
                'debe'            => (float)$d->haber,
                'haber'           => (float)$d->debe,
            ])->toArray();

            $asientoReversa = $this->crear(
                empresaId:    $asiento->empresa_id,
                concepto:     "ANULACIÓN {$asiento->numero}: {$motivo}",
                partidas:     $partidas,
                documentoTipo:'MANUAL',
                documentoId:  $asiento->id,
                documentoRef: $asiento->numero,
                esAutomatico: false,
                // La reversión va con la MISMA fecha del asiento original, no
                // con la de hoy: si no, anular en octubre un asiento de
                // septiembre dejaba el ingreso en septiembre y la reversa en
                // octubre, descuadrando los dos meses a la vez.
                fecha:        $asiento->fecha?->toDateString(),
            );

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'empresa_id'     => $asiento->empresa_id,
                'tabla'          => 'asientos_contables',
                'registro_id'    => $asiento->id,
                'campo'          => 'estado',
                'valor_anterior' => '1',
                'valor_nuevo'    => "0 — {$motivo}",
                'ip_address'     => Request::ip(),
            ]);

            return $asientoReversa;
        });
    }

    // ══════════════════════════════════════════════════════════
    // HELPERS PRIVADOS
    // ══════════════════════════════════════════════════════════

    // Mapa de códigos de parámetro → códigos del plan de cuentas.
    //
    // CORREGIDO (2026-09-20) — auditoría del módulo de Contabilidad. El mapa
    // anterior estaba roto de dos formas distintas y por eso NINGÚN asiento
    // automático llegaba a generarse (26 de 42 códigos no existían en la BD):
    //
    //   1. FORMATO. El plan de cuentas real del cliente NO usa el segmento
    //      final con cero a la izquierda en las clases 1, 2, 3, 4 y 5.1: la
    //      cuenta es '1.1.1.1' (Caja General), no '1.1.1.01'. Solo las clases
    //      5.2, 5.3 y 5.4 usan dos dígitos ('5.2.2.06'). Es inconsistente en
    //      los datos reales, así que además de corregir los valores de este
    //      mapa, cuentaId() normaliza los códigos antes de comparar (ver
    //      normalizarCodigo()): así '1.1.1.01' y '1.1.1.1' resuelven igual y
    //      el sistema no se vuelve a romper si el cliente renumera.
    //
    //   2. SEMÁNTICA. Varios códigos apuntaban a una cuenta que existe pero
    //      NO es la que dice el nombre del parámetro. Los peores:
    //        - cta_ganancias_acumuladas → 3.1.3.01 = "Superavit por
    //          Revaluacion PPE" (la real es 3.1.4.1). El cierre fiscal anual
    //          arrastraba la utilidad del ejercicio al superávit por
    //          revaluación.
    //        - cta_utilidad_periodo → 3.1.4.01 = "Ganancias Acumuladas"
    //          (la real es 3.1.5.1 "Utilidad del Periodo").
    //        - cta_aporte_patronal → 5.2.1.03 = "Comisiones y Bonos"
    //          (la real es 5.2.1.04). Toda la nómina registraba el aporte
    //          patronal IESS como comisiones — y de ahí en adelante todo el
    //          bloque de nómina estaba corrido un número.
    //        - cta_anticipos_clientes → 2.1.6.01 = "Porcion Corriente de
    //          Obligaciones LP" (la real es 2.1.1.3).
    //
    // Verificado cuenta por cuenta contra plan_cuentas de la BD `altamira`.
    private const FALLBACK_PLAN = [
        // ── Activo ────────────────────────────────────────────────────────
        'cta_caja_general'              => '1.1.1.1',  // Caja General
        'cta_cajas_chicas'              => '1.1.1.2',  // Cajas Chicas y Fondos
        'cta_bancos_locales'            => '1.1.1.3',  // Bancos Locales
        'cta_bancos_exterior'           => '1.1.1.4',  // Bancos del Exterior
        'cta_vouchers'                  => '1.1.1.5',  // Dinero Electrónico / Pasarelas
        'cta_clientes_locales'          => '1.1.3.1',  // Clientes Locales
        'cta_clientes_exterior'         => '1.1.3.2',  // Clientes del Exterior
        'cta_anticipos_proveedores'     => '1.1.3.3',  // Anticipos a Proveedores
        'cta_anticipos_empleados'       => '1.1.3.4',  // Préstamos y Anticipos a Empleados
        'cta_provision_incobrables'     => '1.1.3.5',  // (-) Provisión Cuentas Incobrables
        'cta_inventario_mercaderia'     => '1.1.4.1',  // Inventario de Mercadería
        'cta_inventario_transito'       => '1.1.4.3',  // Inventario en Tránsito
        'cta_iva_compras'               => '1.1.5.1',  // Crédito Tributario por IVA
        'cta_retencion_iva_cobrada'     => '1.1.5.2',  // Cred. Trib. Retenciones de IVA
        'cta_retencion_ir_cobrada'      => '1.1.5.3',  // Cred. Trib. Retenciones de IR
        // ── Pasivo ────────────────────────────────────────────────────────
        'cta_proveedores_locales'       => '2.1.1.1',  // Proveedores Locales
        'cta_proveedores_exterior'      => '2.1.1.2',  // Proveedores del Exterior
        'cta_anticipos_clientes'        => '2.1.1.3',  // Anticipos de Clientes
        'cta_retencion_ir'              => '2.1.3.1',  // Retenciones Fuente IR por Pagar
        'cta_retencion_iva'             => '2.1.3.2',  // Retenciones de IVA por Pagar
        'cta_impuesto_renta_pagar'      => '2.1.3.3',  // Impuesto a la Renta por Pagar
        'cta_iva_ventas'                => '2.1.3.4',  // IVA Ventas por Pagar
        'cta_nomina_por_pagar'          => '2.1.4.1',  // Nómina por Pagar
        'cta_iess_por_pagar'            => '2.1.4.2',  // Oblig. IESS Aporte Patronal 11.15%
        'cta_iess_personal_por_pagar'   => '2.1.4.3',  // Oblig. IESS Aporte Personal 9.45%
        'cta_decimo_tercero_pagar'      => '2.1.4.5',  // Décimo Tercer Sueldo por Pagar
        'cta_decimo_cuarto_pagar'       => '2.1.4.6',  // Décimo Cuarto Sueldo por Pagar
        'cta_vacaciones_pagar'          => '2.1.4.7',  // Vacaciones por Pagar
        'cta_fondos_reserva_pagar'      => '2.1.4.8',  // Fondos de Reserva por Pagar
        'cta_participacion_trabajadores'=> '2.1.4.9',  // Utilidades a Trabajadores 15%
        // ── Patrimonio ────────────────────────────────────────────────────
        'cta_ganancias_acumuladas'      => '3.1.4.1',  // Ganancias Acumuladas
        'cta_perdidas_acumuladas'       => '3.1.4.2',  // (-) Pérdidas Acumuladas
        'cta_utilidad_periodo'          => '3.1.5.1',  // Utilidad del Periodo
        'cta_perdida_periodo'           => '3.1.5.2',  // (-) Pérdida del Periodo
        // ── Ingresos ──────────────────────────────────────────────────────
        'cta_ventas_locales'            => '4.1.1.1',  // Venta de Mercancías Locales
        'cta_ventas_exterior'           => '4.1.1.2',  // Venta de Mercancías al Exterior
        'cta_ingresos_servicios'        => '4.1.2.1',  // Ingresos por Servicios Técnicos
        'cta_devoluciones_ventas'       => '4.1.3.1',  // (-) Devoluciones en Ventas
        'cta_descuentos_ventas'         => '4.1.3.2',  // (-) Descuentos y Rebajas en Ventas
        // ── Costo de ventas ───────────────────────────────────────────────
        'cta_costo_ventas'              => '5.1.1.1',  // Costo de Ventas Mercancías Locales
        'cta_costo_ventas_importadas'   => '5.1.1.2',  // Costo de Ventas Mercancías Importadas
        'cta_costo_servicios'           => '5.1.1.3',  // Costo de Prestación de Servicios
        'cta_ajuste_inventario'         => '5.1.1.4',  // Ajustes por Faltantes o Mermas
        // ── Gastos de personal ────────────────────────────────────────────
        'cta_sueldos_salarios'          => '5.2.1.01', // Sueldos y Salarios
        'cta_horas_extras'              => '5.2.1.02', // Horas Extras y Suplementarias
        'cta_aporte_patronal'           => '5.2.1.04', // Aporte Patronal IESS 11.15%
        'cta_decimo_tercero'            => '5.2.1.05', // Décimo Tercer Sueldo
        'cta_decimo_cuarto'             => '5.2.1.06', // Décimo Cuarto Sueldo
        'cta_vacaciones'                => '5.2.1.07', // Vacaciones
        'cta_fondos_reserva'            => '5.2.1.08', // Fondos de Reserva
        // ── Gastos generales / financieros / otros ────────────────────────
        'cta_gasto_compras_default'     => '5.2.2.06', // Suministros de Oficina
        'cta_gasto_servicios'           => '5.2.2.01', // Honorarios Profesionales
        'cta_gasto_arrendamiento'       => '5.2.2.02', // Arrendamientos de Locales
        'cta_gasto_servicios_basicos'   => '5.2.2.03', // Servicios Básicos
        'cta_gasto_publicidad'          => '5.2.2.11', // Publicidad y Marketing
        'cta_comisiones_bancarias'      => '5.3.1.02', // Comisiones Bancarias y Pasarelas
        'cta_gastos_no_deducibles'      => '5.4.1.01', // Gastos No Deducibles Locales
        'cta_ajuste_conciliacion'       => '5.4.1.03', // Otros Gastos Extraordinarios
    ];

    /** Mapa parámetro → código de cuenta, para el autoconfigurador de la UI. */
    public static function planPorDefecto(): array
    {
        return self::FALLBACK_PLAN;
    }

    /**
     * Normaliza un código de cuenta para poder compararlo sin depender de los
     * ceros a la izquierda de cada segmento: '1.1.1.01' y '1.1.1.1' son el
     * mismo código. El plan de cuentas real del cliente mezcla ambos formatos
     * (clases 1–4 y 5.1 con un dígito, 5.2–5.4 con dos), así que comparar el
     * string tal cual dejaba sin resolver más de la mitad de los parámetros.
     */
    private static function normalizarCodigo(string $codigo): string
    {
        return implode('.', array_map(
            fn($seg) => ltrim($seg, '0') === '' ? '0' : ltrim($seg, '0'),
            explode('.', trim($codigo))
        ));
    }

    /**
     * Busca una cuenta del plan por código tolerando el formato de los ceros
     * a la izquierda. Devuelve null si no existe o no acepta movimientos.
     */
    public static function buscarCuentaPorCodigo(string $codigo): ?PlanCuenta
    {
        $exacta = PlanCuenta::where('codigo', $codigo)
            ->where('permite_asientos', true)->where('estado', true)->first();
        if ($exacta) {
            return $exacta;
        }

        $objetivo = self::normalizarCodigo($codigo);

        return PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->get(['id', 'codigo', 'nombre', 'tipo', 'permite_asientos', 'estado'])
            ->first(fn($c) => self::normalizarCodigo($c->codigo) === $objetivo);
    }

    public function cuentaId(string $codigo, int $empresaId): int
    {
        // 1. Buscar en parametros_contables
        $id = ParametroContable::getCuentaId($codigo, $empresaId);
        if ($id) {
            return $id;
        }

        // 2. Fallback: buscar en plan_cuentas por código conocido (sin filtro empresa_id)
        $planCodigo = self::FALLBACK_PLAN[$codigo] ?? null;
        if ($planCodigo) {
            $cuenta = self::buscarCuentaPorCodigo($planCodigo);

            if ($cuenta) {
                // Auto-guardar para que futuras llamadas sean directas
                ParametroContable::updateOrCreate(
                    ['empresa_id' => $empresaId, 'codigo' => $codigo],
                    ['cuenta_id' => $cuenta->id, 'descripcion' => $cuenta->nombre]
                );
                return $cuenta->id;
            }
        }

        throw new \Exception(
            "Parámetro contable '{$codigo}' no configurado. " .
            "Configure los parámetros en Contabilidad → Configuración."
        );
    }

    /**
     * Pago de nómina: cancela el pasivo generado al procesar (HABER cta_nomina_por_pagar en nomina())
     * contra la cuenta del banco/caja desde la que se paga.
     */
    public function pagoNominaDesdeCuenta(
        int     $empresaId,
        int     $nominaId,
        string  $referencia,
        float   $monto,
        ?int    $ctaBancoId = null,
        ?string $fecha      = null,
    ): AsientoContable {
        return $this->crear(
            empresaId: $empresaId,
            concepto:  "Pago nómina {$referencia}",
            partidas: [
                ['cuenta_id' => $this->cuentaId('cta_nomina_por_pagar', $empresaId),
                 'debe' => $monto, 'haber' => 0, 'descripcion' => "Pago nómina {$referencia}"],
                ['cuenta_id' => $ctaBancoId ?? $this->cuentaId('cta_bancos_locales', $empresaId),
                 'debe' => 0, 'haber' => $monto, 'descripcion' => "Pago nómina {$referencia}"],
            ],
            documentoTipo: 'NOMPAG',
            documentoId:   $nominaId,
            documentoRef:  $referencia,
            esAutomatico:  true,
            fecha:         $fecha,
        );
    }

    /**
     * Parámetros contables que necesita compraRegistrada() según el tipo de asiento.
     * Debe mantenerse en sincronía con esa función.
     */
    public static function codigosCompra(string $tipoAsiento, bool $retIR = false, bool $retIVA = false): array
    {
        if ($tipoAsiento === 'no_deducible') {
            return ['cta_gastos_no_deducibles', 'cta_proveedores_locales'];
        }

        $codigos = [
            $tipoAsiento === 'inventario' ? 'cta_inventario_mercaderia' : 'cta_gasto_compras_default',
            'cta_iva_compras',
            'cta_proveedores_locales',
        ];
        if ($retIR)  $codigos[] = 'cta_retencion_ir';
        if ($retIVA) $codigos[] = 'cta_retencion_iva';

        return $codigos;
    }

    /**
     * Parámetros contables que necesita facturaAutorizada() según las formas
     * de pago usadas y si la factura mueve inventario. Se usa con
     * validarConfiguracion() ANTES de emitir la factura.
     */
    public static function codigosFactura(array $formasPago = [], bool $conInventario = false): array
    {
        $codigos = ['cta_ventas_locales', 'cta_iva_ventas'];

        foreach ($formasPago ?: ['efectivo'] as $fp) {
            $forma = is_array($fp) ? ($fp['forma'] ?? 'efectivo') : $fp;
            $codigos[] = match (strtolower(trim((string) $forma))) {
                'credito', 'crédito'                              => 'cta_clientes_locales',
                'transferencia', 'deposito', 'depósito', 'cheque' => 'cta_bancos_locales',
                'tarjeta', 'tarjeta_credito',
                'tarjeta_debito', 'datafast'                      => 'cta_vouchers',
                default                                           => 'cta_caja_general',
            };
        }

        if ($conInventario) {
            $codigos[] = 'cta_costo_ventas';
            $codigos[] = 'cta_inventario_mercaderia';
        }

        return array_values(array_unique($codigos));
    }

    /**
     * Costo (a costo promedio) de la mercadería que salió por un documento.
     * Se lee del kárdex, que es la fuente de verdad del costo: así el asiento
     * de costo de ventas usa exactamente el mismo valor que descargó el stock
     * y contabilidad e inventario no pueden divergir.
     */
    public static function costoSalidaDocumento(string $docTipo, int $docId): float
    {
        return (float) DB::table('inventario_movimientos')
            ->where('doc_tipo', strtoupper($docTipo))
            ->where('doc_id', $docId)
            ->where('tipo', 'salida')
            ->sum('costo_total');
    }

    /**
     * Verifica que la parte contable esté lista ANTES de ejecutar una operación que genera
     * asientos: período abierto (y mes de la fecha no cerrado) y parámetros contables
     * resolubles. Lanza \DomainException con un mensaje que dice qué hacer.
     */
    public function validarConfiguracion(int $empresaId, array $codigos = [], ?string $fecha = null): void
    {
        $problemas = [];
        $fechaAsiento = $fecha ? \Carbon\Carbon::parse($fecha) : now();

        // Mismo criterio que crear(): el período que importa es el del mes de
        // la FECHA del documento, no "cualquier período abierto".
        $ejercicioDelMes = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $fechaAsiento->year)
            ->where('mes', $fechaAsiento->month)
            ->first();

        if (!$ejercicioDelMes) {
            $problemas[] = 'No existe el período contable ' . $fechaAsiento->format('m/Y')
                . '. Ábralo en Contabilidad → Ejercicios.';
        } elseif ($ejercicioDelMes->estaCerrado()) {
            $problemas[] = "El período {$ejercicioDelMes->periodo_label} está cerrado. "
                . 'Reábralo en Contabilidad → Ejercicios o use una fecha de un período abierto.';
        }

        $faltantes = [];
        foreach (array_unique($codigos) as $codigo) {
            try {
                $this->cuentaId($codigo, $empresaId);
            } catch (\Throwable) {
                $faltantes[] = $codigo;
            }
        }
        if ($faltantes) {
            $problemas[] = 'Faltan parámetros contables: ' . implode(', ', $faltantes)
                . '. Configúrelos en Contabilidad → Parámetros Contables.';
        }

        if ($problemas) {
            throw new \DomainException(
                'No se puede continuar: la contabilidad no está lista. ' . implode(' ', $problemas)
            );
        }
    }

    private function registrarAuditoria(string $accion, AsientoContable $asiento): void
    {
        DB::table('log_documentos')->insert([
            'usuario_id'  => Auth::id(),
            'username'    => Auth::user()?->email ?? 'sistema',
            'accion'      => $accion,
            'modulo'      => 'contabilidad',
            'tabla'       => 'asientos_contables',
            'registro_id' => $asiento->id,
            'descripcion' => "Asiento {$asiento->numero}: {$asiento->concepto}",
            'ip_address'  => Request::ip(),
            'empresa_id'  => $asiento->empresa_id,
            'fecha'       => now(),
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // VISIBILIDAD DE DOCUMENTOS SIN ASIENTO (ej. período cerrado)
    //
    // Decisión de diseño intencional (ver CLAUDE.md): cuando un documento
    // como una Compra genera su asiento automático DESPUÉS de guardarse
    // (dentro de un try/catch que "no bloquea si falla"), un fallo no debe
    // quedar solo en storage/logs — el Contador/Super Admin de la empresa
    // deben enterarse sin tener que buscar documentos huérfanos a mano.
    // ══════════════════════════════════════════════════════════
    public function notificarAsientoFallido(
        int    $empresaId,
        string $tabla,
        int    $registroId,
        string $referencia,
        string $mensaje,
    ): void {
        DB::table('log_documentos')->insert([
            'usuario_id'  => Auth::id(),
            'username'    => Auth::user()?->email ?? 'sistema',
            'accion'      => 'asiento_fallido',
            'modulo'      => 'contabilidad',
            'tabla'       => $tabla,
            'registro_id' => $registroId,
            'descripcion' => "{$referencia}: no se generó asiento contable — {$mensaje}",
            'ip_address'  => Request::ip(),
            'empresa_id'  => $empresaId,
            'fecha'       => now(),
        ]);

        $destinatarios = \App\Models\Usuario::whereHas(
                'empresas', fn($q) => $q->where('empresas.id', $empresaId)
            )
            ->whereHas('perfil', fn($q) => $q->whereIn('nombre', ['super_admin', 'contador']))
            ->where('estado', true)
            ->get(['id']);

        foreach ($destinatarios as $usuario) {
            \App\Models\Notificacion::create([
                'usuario_id' => $usuario->id,
                'tipo'       => 'asiento_fallido',
                'titulo'     => 'Documento sin asiento contable',
                'mensaje'    => "{$referencia} no generó asiento contable: {$mensaje}",
                'icono'      => 'alert-triangle',
                'url'        => null,
                'leida'      => false,
            ]);
        }
    }

    // ══════════════════════════════════════════════════════════
    // MÉTODOS PARA DEV 1 — Ventas
    // ══════════════════════════════════════════════════════════

    /**
     * Cuenta de contrapartida (por dónde entra el dinero) según la forma de pago.
     * 'credito' no es un cobro: es una Cuenta por Cobrar al cliente.
     */
    public function cuentaCobroPorForma(string $formaPago, int $empresaId): int
    {
        return match (strtolower(trim($formaPago))) {
            'credito', 'crédito' => $this->cuentaId('cta_clientes_locales', $empresaId),
            'transferencia',
            'deposito', 'depósito',
            'cheque'             => $this->cuentaId('cta_bancos_locales',   $empresaId),
            'tarjeta',
            'tarjeta_credito',
            'tarjeta_debito',
            'datafast'           => $this->cuentaId('cta_vouchers',         $empresaId),
            default              => $this->cuentaId('cta_caja_general',     $empresaId),
        };
    }

    /**
     * Asiento de emisión de factura de venta.
     *
     * Registra las DOS mitades que exige el sistema de inventario permanente:
     *
     *   (1) Reconocimiento del ingreso
     *         DEBE  Caja / Bancos / Vouchers / Clientes  (una partida POR CADA
     *               forma de pago — ver abajo)
     *         HABER Ventas                               subtotal
     *         HABER IVA en Ventas por Pagar              iva
     *
     *   (2) Reconocimiento del costo y baja del inventario  ← FALTABA POR COMPLETO
     *         DEBE  Costo de Ventas                      costo promedio vendido
     *         HABER Inventario de Mercadería             costo promedio vendido
     *
     * Sin (2) el inventario contable solo crecía con las compras y nunca se
     * descargaba: el activo quedaba inflado, el kárdex y la contabilidad
     * divergían para siempre, y el Estado de Resultados mostraba la venta
     * completa con costo cero (utilidad y base imponible irreales).
     *
     * $formasPago permite repartir el débito entre varias cuentas. Antes se
     * tomaba solo la forma de mayor monto y se cargaba el 100% del total ahí:
     * una factura de $1.000 con $300 en efectivo y $700 a crédito debitaba
     * $1.000 a Caja y no registraba nada en Clientes, dejando la CxC sin
     * respaldo contable.
     */
    public function facturaAutorizada(
        int     $empresaId,
        int     $facturaId,
        string  $numeroFactura,
        float   $subtotal,
        float   $iva,
        float   $total,
        string  $formaPago  = 'efectivo',
        ?array  $formasPago = null,
        float   $costoVenta = 0,
        ?string $fecha      = null,
    ): AsientoContable {

        $partidas = [];

        // ── (1) Débito: una partida por forma de pago ──────────────────────
        $desglose = [];
        foreach ($formasPago ?? [] as $fp) {
            $monto = round((float)($fp['monto'] ?? 0), 2);
            if ($monto <= 0) continue;
            $desglose[] = ['forma' => (string)($fp['forma'] ?? 'efectivo'), 'monto' => $monto];
        }

        // Si no vino el desglose (o no suma el total) se usa la forma principal
        // por el total, que es el comportamiento anterior.
        $sumaDesglose = round(array_sum(array_column($desglose, 'monto')), 2);
        if (!$desglose || abs($sumaDesglose - round($total, 2)) > 0.01) {
            $desglose = [['forma' => $formaPago, 'monto' => round($total, 2)]];
        }

        // Agrupa por cuenta: varias formas pueden caer en la misma (ej. dos
        // transferencias) y no tiene sentido duplicar la línea.
        $porCuenta = [];
        foreach ($desglose as $d) {
            $cta = $this->cuentaCobroPorForma($d['forma'], $empresaId);
            $porCuenta[$cta] = round(($porCuenta[$cta] ?? 0) + $d['monto'], 2);
        }
        foreach ($porCuenta as $cuentaId => $monto) {
            $partidas[] = [
                'cuenta_id'   => $cuentaId,
                'debe'        => $monto,
                'haber'       => 0,
                'descripcion' => "Factura {$numeroFactura}",
            ];
        }

        // ── (1) Crédito: ingreso e IVA ─────────────────────────────────────
        $partidas[] = [
            'cuenta_id'   => $this->cuentaId('cta_ventas_locales', $empresaId),
            'debe'        => 0,
            'haber'       => round($subtotal, 2),
            'descripcion' => "Venta — {$numeroFactura}",
        ];
        if ($iva > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_iva_ventas', $empresaId),
                'debe'        => 0,
                'haber'       => round($iva, 2),
                'descripcion' => "IVA en ventas — {$numeroFactura}",
            ];
        }

        // ── (2) Costo de ventas / baja de inventario ───────────────────────
        $costoVenta = round($costoVenta, 2);
        if ($costoVenta > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_costo_ventas', $empresaId),
                'debe'        => $costoVenta,
                'haber'       => 0,
                'descripcion' => "Costo de ventas — {$numeroFactura}",
            ];
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_inventario_mercaderia', $empresaId),
                'debe'        => 0,
                'haber'       => $costoVenta,
                'descripcion' => "Baja de inventario por venta — {$numeroFactura}",
            ];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Venta factura {$numeroFactura}",
            partidas: $partidas, documentoTipo: 'FAC',
            documentoId: $facturaId, documentoRef: $numeroFactura, esAutomatico: true,
            fecha: $fecha,
        );
    }

    public function anticipoCliente(
        int     $empresaId,
        int     $documentoId,
        string  $referencia,
        float   $monto,
        string  $formaPago = 'efectivo',
        ?string $fecha     = null,
    ): AsientoContable {
        $cta = $this->cuentaCobroPorForma($formaPago, $empresaId);

        return $this->crear(
            empresaId: $empresaId, concepto: "Anticipo cliente — {$referencia}",
            partidas: [
                ['cuenta_id' => $cta, 'debe' => $monto, 'haber' => 0,
                 'descripcion' => "Anticipo {$referencia}"],
                ['cuenta_id' => $this->cuentaId('cta_anticipos_clientes', $empresaId),
                 'debe' => 0, 'haber' => $monto,
                 'descripcion' => "Anticipo cliente {$referencia}"],
            ],
            documentoTipo: 'FAC', documentoId: $documentoId,
            documentoRef: $referencia, esAutomatico: true,
            fecha: $fecha,
        );
    }

    public function cobro(
        int     $empresaId,
        int     $documentoId,
        string  $referencia,
        float   $monto,
        string  $formaPago = 'transferencia',
        ?string $fecha     = null,
    ): AsientoContable {
        $cta = $formaPago === 'efectivo'
            ? $this->cuentaId('cta_caja_general',   $empresaId)
            : $this->cuentaId('cta_bancos_locales', $empresaId);

        return $this->crear(
            empresaId: $empresaId, concepto: "Cobro CxC — {$referencia}",
            partidas: [
                ['cuenta_id' => $cta, 'debe' => $monto, 'haber' => 0,
                 'descripcion' => "Cobro {$referencia}"],
                ['cuenta_id' => $this->cuentaId('cta_clientes_locales', $empresaId),
                 'debe' => 0, 'haber' => $monto,
                 'descripcion' => "Cancelación CxC {$referencia}"],
            ],
            documentoTipo: 'CXC', documentoId: $documentoId,
            documentoRef: $referencia, esAutomatico: true,
            fecha: $fecha,
        );
    }

    /**
     * Nota de crédito emitida (devolución o anulación parcial de una venta).
     *
     * Correcciones sobre la versión anterior:
     *   - El débito va a "(-) Devoluciones en Ventas" (cuenta regularizadora
     *     de ingresos, 4.1.3.1) en vez de debitar directamente la cuenta de
     *     Ventas. Así la devolución queda trazable en el Estado de Resultados
     *     en lugar de desaparecer restando del ingreso bruto — que es
     *     justamente para lo que existe cta_devoluciones_ventas, parametrizada
     *     desde el primer día y hasta ahora nunca usada.
     *   - La contrapartida ya no es siempre Clientes: si la venta original fue
     *     de contado, lo que sale es efectivo/banco, no una CxC. Se elige con
     *     $formaPago igual que en la factura.
     *   - Reversa el costo de ventas y reingresa el inventario ($costoVenta)
     *     cuando la NC devuelve mercadería.
     */
    public function notaCreditoEmitida(
        int     $empresaId,
        int     $notaCreditoId,
        string  $referencia,
        float   $subtotal,
        float   $iva,
        string  $formaPago  = 'credito',
        float   $costoVenta = 0,
        ?string $fecha      = null,
    ): AsientoContable {
        $partidas = [
            ['cuenta_id' => $this->cuentaId('cta_devoluciones_ventas', $empresaId),
             'debe' => round($subtotal, 2), 'haber' => 0,
             'descripcion' => "Devolución en ventas NC {$referencia}"],
            ['cuenta_id' => $this->cuentaCobroPorForma($formaPago, $empresaId),
             'debe' => 0, 'haber' => round($subtotal + $iva, 2),
             'descripcion' => "NC {$referencia}"],
        ];
        if ($iva > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_iva_ventas', $empresaId),
                'debe' => round($iva, 2), 'haber' => 0,
                'descripcion' => "IVA NC {$referencia}",
            ];
        }

        $costoVenta = round($costoVenta, 2);
        if ($costoVenta > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_inventario_mercaderia', $empresaId),
                'debe' => $costoVenta, 'haber' => 0,
                'descripcion' => "Reingreso de inventario por NC {$referencia}",
            ];
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_costo_ventas', $empresaId),
                'debe' => 0, 'haber' => $costoVenta,
                'descripcion' => "Reversa costo de ventas NC {$referencia}",
            ];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Nota de crédito {$referencia}",
            partidas: $partidas, documentoTipo: 'NC',
            documentoId: $notaCreditoId, documentoRef: $referencia, esAutomatico: true,
            fecha: $fecha,
        );
    }

    // ══════════════════════════════════════════════════════════
    // NÓMINA — Dev 2
    // ══════════════════════════════════════════════════════════

    /**
     * Genera el asiento contable de nómina al pasar a estado "procesado".
     *
     * Siempre cuadra porque:
     *   DEBE  = gastos_sueldos_neto + gasto_aporte_patronal
     *   HABER = iess_personal + iess_patronal + recuperacion_prestamos + neto_por_pagar
     *
     * gastos_sueldos_neto = total_ingresos - descuento_atrasos - otros_egresos
     */
    public function nomina(\App\Models\Nomina $nomina, ?int $centroCostoId = null): AsientoContable
    {
        $empresaId = $nomina->empresa_id;
        $periodo   = $nomina->periodo_label;
        $cc        = $centroCostoId;

        $detalles = $nomina->detalles()->with('colaborador')->get();

        $sumSueldosNeto    = 0.0;
        $sumAportePatronal = 0.0;
        $sumIessPersonal   = 0.0;
        $sumPrestamos      = 0.0;
        $sumNeto           = 0.0;

        foreach ($detalles as $d) {
            $sumSueldosNeto    += (float)$d->total_ingresos - (float)$d->descuento_atrasos - (float)$d->otros_egresos;
            $sumAportePatronal += round((float)$d->total_ingresos * 0.1115, 2);
            $sumIessPersonal   += (float)$d->aporte_personal_iess;
            $sumPrestamos      += (float)$d->descuento_prestamos + (float)$d->descuento_anticipos;
            $sumNeto           += (float)$d->neto_pagar;
        }

        $sumSueldosNeto    = round($sumSueldosNeto, 2);
        $sumAportePatronal = round($sumAportePatronal, 2);

        // Cuenta IESS personal — pasa por cuentaId() como cualquier otra para
        // que sea configurable desde Parámetros Contables y tolere el formato
        // del código. Antes buscaba literalmente '2.1.4.03', que NO existe en
        // el plan real (la cuenta es '2.1.4.3'), así que el asiento de nómina
        // fallaba siempre con "Cuenta no encontrada en el plan de cuentas".
        $cuentaIessPersonalId = $this->cuentaId('cta_iess_personal_por_pagar', $empresaId);

        $partidas = [];

        if ($sumSueldosNeto > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_sueldos_salarios', $empresaId),
                'debe' => $sumSueldosNeto, 'haber' => 0, 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — sueldos y horas extras"];
        }
        if ($sumAportePatronal > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_aporte_patronal', $empresaId),
                'debe' => $sumAportePatronal, 'haber' => 0, 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — aporte patronal IESS 11.15%"];
        }
        if ($sumIessPersonal > 0) {
            $partidas[] = ['cuenta_id' => $cuentaIessPersonalId,
                'debe' => 0, 'haber' => round($sumIessPersonal, 2), 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — IESS personal 9.45%"];
        }
        if ($sumAportePatronal > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_iess_por_pagar', $empresaId),
                'debe' => 0, 'haber' => $sumAportePatronal, 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — IESS patronal por pagar"];
        }
        if ($sumPrestamos > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_anticipos_empleados', $empresaId),
                'debe' => 0, 'haber' => round($sumPrestamos, 2), 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — recuperación préstamos y anticipos"];
        }
        if ($sumNeto > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_nomina_por_pagar', $empresaId),
                'debe' => 0, 'haber' => round($sumNeto, 2), 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — neto a pagar"];
        }

        return $this->crear(
            empresaId:     $empresaId,
            concepto:      "Nómina {$periodo}",
            partidas:      $partidas,
            documentoTipo: 'NOM',
            documentoId:   $nomina->id,
            documentoRef:  "NOM-{$nomina->anio}-{$nomina->mes}",
            esAutomatico:  true,
            fecha:         $nomina->fecha_emision?->toDateString(),
        );
    }

    // Pago de nómina: DEBE Nómina por pagar / HABER Bancos.
    public function pagoNomina(\App\Models\Nomina $nomina, string $fechaPago, string $comprobante): AsientoContable
    {
        $empresaId = $nomina->empresa_id;
        $periodo   = $nomina->periodo_label;
        $monto     = round((float) $nomina->total_neto, 2);

        return $this->crear(
            empresaId:     $empresaId,
            concepto:      "Pago de nómina {$periodo} — comprobante {$comprobante}",
            partidas: [
                ['cuenta_id' => $this->cuentaId('cta_nomina_por_pagar', $empresaId),
                 'debe' => $monto, 'haber' => 0,
                 'descripcion' => "Pago nómina {$periodo}"],
                ['cuenta_id' => $this->cuentaId('cta_bancos_locales', $empresaId),
                 'debe' => 0, 'haber' => $monto,
                 'descripcion' => "Pago nómina {$periodo} — {$comprobante}"],
            ],
            documentoTipo: 'NOMPAG',
            documentoId:   $nomina->id,
            documentoRef:  "NOMPAG-{$nomina->anio}-{$nomina->mes}",
            esAutomatico:  true,
            fecha:         $fechaPago,
        );
    }

    // ══════════════════════════════════════════════════════════
    // PRÉSTAMOS Y ANTICIPOS A EMPLEADOS — Dev 2
    // DEBE: 1.1.3.04 Préstamos y Anticipos a Empleados
    // HABER: 1.1.1.03 Bancos Locales
    // ══════════════════════════════════════════════════════════
    public function prestamoEmpleado(\App\Models\PrestamoEmpleado $prestamo, int $empresaId): AsientoContable
    {
        $col   = $prestamo->colaborador;
        $label = $col ? "{$col->apellidos} {$col->nombres}" : "Colaborador #{$prestamo->colaborador_id}";
        $tipo  = $prestamo->tipo === 'anticipo' ? 'Anticipo' : 'Préstamo';
        $ref   = "PREST-{$prestamo->id}";

        return $this->crear(
            empresaId:    $empresaId,
            concepto:     "{$tipo} a empleado — {$label}",
            partidas: [
                ['cuenta_id'   => $this->cuentaId('cta_anticipos_empleados', $empresaId),
                 'debe'        => (float)$prestamo->monto_total,
                 'haber'       => 0,
                 'descripcion' => "{$tipo} entregado: {$label}"],
                ['cuenta_id'   => $this->cuentaId('cta_bancos_locales', $empresaId),
                 'debe'        => 0,
                 'haber'       => (float)$prestamo->monto_total,
                 'descripcion' => "Desembolso {$tipo} {$label}"],
            ],
            documentoTipo: 'PREST',
            documentoId:   $prestamo->id,
            documentoRef:  $ref,
            esAutomatico:  true,
            fecha:         $prestamo->fecha?->toDateString(),
        );
    }

    // ══════════════════════════════════════════════════════════
    // LIQUIDACIÓN / FINIQUITO — Dev 2
    // Genera asiento al aprobar una liquidación de empleado
    // DEBE: 2.1.4.x (sueldos/décimos/vacaciones por pagar)
    // HABER: 1.1.1.03 Bancos Locales
    // ══════════════════════════════════════════════════════════
    public function liquidacionEmpleado(\App\Models\Liquidacion $liq, int $empresaId): AsientoContable
    {
        $col      = $liq->colaborador;
        $label    = $col ? "{$col->apellidos} {$col->nombres}" : "Colaborador #{$liq->colaborador_id}";
        $motivos  = ['renuncia' => 'Renuncia', 'despido' => 'Despido', 'fin_contrato' => 'Fin de contrato'];
        $motivoLabel = $motivos[$liq->motivo] ?? 'Liquidación';
        $ref      = "LIQ-{$liq->id}";
        $total    = (float)$liq->total_liquidacion;

        // El asiento paga la liquidación total desde Bancos
        return $this->crear(
            empresaId:    $empresaId,
            concepto:     "Liquidación empleado ({$motivoLabel}) — {$label}",
            partidas: [
                ['cuenta_id'   => $this->cuentaId('cta_sueldos_salarios', $empresaId),
                 'debe'        => $total,
                 'haber'       => 0,
                 'descripcion' => "Finiquito {$motivoLabel} — {$label}"],
                ['cuenta_id'   => $this->cuentaId('cta_bancos_locales', $empresaId),
                 'debe'        => 0,
                 'haber'       => $total,
                 'descripcion' => "Pago finiquito {$label}"],
            ],
            documentoTipo: 'LIQ',
            documentoId:   $liq->id,
            documentoRef:  $ref,
            esAutomatico:  true,
            fecha:         $liq->fecha_salida?->toDateString(),
        );
    }

    public function ajusteInventario(
        int     $empresaId,
        int     $documentoId,
        string  $referencia,
        float   $monto,
        string  $tipo  = 'faltante',
        ?string $fecha = null,
    ): AsientoContable {
        $partidas = $tipo === 'faltante' ? [
            ['cuenta_id' => $this->cuentaId('cta_ajuste_inventario',    $empresaId),
             'debe' => $monto, 'haber' => 0,
             'descripcion' => "Ajuste faltante {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_inventario_mercaderia', $empresaId),
             'debe' => 0, 'haber' => $monto,
             'descripcion' => "Rebaja inventario {$referencia}"],
        ] : [
            ['cuenta_id' => $this->cuentaId('cta_inventario_mercaderia', $empresaId),
             'debe' => $monto, 'haber' => 0,
             'descripcion' => "Sobrante inventario {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_ajuste_inventario',    $empresaId),
             'debe' => 0, 'haber' => $monto,
             'descripcion' => "Ajuste sobrante {$referencia}"],
        ];

        return $this->crear(
            empresaId: $empresaId,
            concepto: "Ajuste inventario {$tipo} — {$referencia}",
            partidas: $partidas, documentoTipo: 'INV',
            documentoId: $documentoId, documentoRef: $referencia, esAutomatico: true,
            fecha: $fecha,
        );
    }

    // ══════════════════════════════════════════════════════════
    // MÉTODOS PROPIOS DEV 2 — Compras y Finanzas
    // ══════════════════════════════════════════════════════════

    public function compraRegistrada(
        int    $empresaId,
        int    $compraId,
        string $referencia,
        float  $subtotal,
        float  $iva,
        float  $retencionIR   = 0,
        float  $retencionIVA  = 0,
        string $tipo          = 'inventario',
        ?int   $centroCostoId = null,
        ?string $fecha        = null,
    ): AsientoContable {
        $cc = $centroCostoId;

        if ($tipo === 'no_deducible') {
            return $this->crear(
                empresaId:    $empresaId,
                concepto:     "Gasto no deducible {$referencia}",
                partidas: [
                    ['cuenta_id' => $this->cuentaId('cta_gastos_no_deducibles', $empresaId),
                     'debe' => $subtotal, 'haber' => 0, 'centro_costo_id' => $cc,
                     'descripcion' => "Gasto no deducible {$referencia}"],
                    ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
                     'debe' => 0, 'haber' => $subtotal, 'centro_costo_id' => $cc,
                     'descripcion' => "CxP (no ded.) {$referencia}"],
                ],
                documentoTipo: 'COMPRA', documentoId: $compraId,
                documentoRef: $referencia, esAutomatico: true,
                fecha: $fecha,
            );
        }

        $ctaCompra = $tipo === 'inventario'
            ? $this->cuentaId('cta_inventario_mercaderia', $empresaId)
            : $this->cuentaId('cta_gasto_compras_default', $empresaId);
        $neto = $subtotal + $iva - $retencionIR - $retencionIVA;

        $partidas = [
            ['cuenta_id' => $ctaCompra, 'debe' => $subtotal, 'haber' => 0,
             'centro_costo_id' => $cc, 'descripcion' => "Compra {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_iva_compras', $empresaId),
             'debe' => $iva, 'haber' => 0, 'centro_costo_id' => $cc,
             'descripcion' => "IVA compra {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
             'debe' => 0, 'haber' => $neto, 'centro_costo_id' => $cc,
             'descripcion' => "CxP {$referencia}"],
        ];
        if ($retencionIR > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_retencion_ir', $empresaId),
                'debe' => 0, 'haber' => $retencionIR, 'centro_costo_id' => $cc,
                'descripcion' => "Ret. IR {$referencia}"];
        }
        if ($retencionIVA > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_retencion_iva', $empresaId),
                'debe' => 0, 'haber' => $retencionIVA, 'centro_costo_id' => $cc,
                'descripcion' => "Ret. IVA {$referencia}"];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Compra {$referencia}",
            partidas: $partidas, documentoTipo: 'COMPRA',
            documentoId: $compraId, documentoRef: $referencia, esAutomatico: true,
            fecha: $fecha,
        );
    }

    public function pagoProveedor(
        int  $empresaId,
        int  $documentoId,
        string $referencia,
        float  $monto,
        ?int   $centroCostoId = null,
        ?string $fecha        = null,
    ): AsientoContable {
        $cc = $centroCostoId;
        return $this->crear(
            empresaId: $empresaId, concepto: "Pago proveedor {$referencia}",
            partidas: [
                ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
                 'debe' => $monto, 'haber' => 0, 'centro_costo_id' => $cc,
                 'descripcion' => "Pago {$referencia}"],
                ['cuenta_id' => $this->cuentaId('cta_bancos_locales', $empresaId),
                 'debe' => 0, 'haber' => $monto, 'centro_costo_id' => $cc,
                 'descripcion' => "Transferencia {$referencia}"],
            ],
            documentoTipo: 'BANCO', documentoId: $documentoId,
            documentoRef: $referencia, esAutomatico: true,
            fecha: $fecha,
        );
    }

    // ══════════════════════════════════════════════════════════
    // ANTICIPO PROVEEDOR — Dev 2
    // ══════════════════════════════════════════════════════════
    public function anticipoProveedor(
        int    $empresaId,
        int    $anticiPoId,
        string $referencia,
        float  $monto,
        int    $bancoCajaId,
        string $fecha,
    ): AsientoContable {
        $banco = \App\Models\BancoCaja::findOrFail($bancoCajaId);
        $cuentaBancoId = $banco->cuenta_id;

        if (!$cuentaBancoId) {
            throw new \Exception(
                "El banco/caja '{$banco->nombre}' no tiene cuenta contable configurada. " .
                "Configure la cuenta en Bancos → Catálogo."
            );
        }

        // El try/catch con una lista de códigos de respaldo que había aquí
        // antes era código muerto y estaba mal de dos formas a la vez: los
        // códigos ('1.1.3.3', '1.1.04.04', '1.1.4.4', '1.1.4.03') no
        // corresponden a "Anticipos a Proveedores" en el plan de cuentas
        // real (que usa '1.1.3.03'), y además filtraba
        // PlanCuenta::where('empresa_id', $empresaId) — pero
        // plan_cuentas.empresa_id es NULL en todas las filas reales (plan
        // de cuentas compartido entre empresas, mismo hallazgo ya
        // documentado en ReporteContableController), así que ese filtro
        // nunca habría encontrado nada de todas formas. cuentaId() ya
        // tiene su propio fallback correcto (FALLBACK_PLAN → '1.1.3.03'),
        // no hace falta duplicarlo aquí.
        $cuentaAnticipoId = $this->cuentaId('cta_anticipos_proveedores', $empresaId);

        return $this->crear(
            empresaId:    $empresaId,
            concepto:     "Anticipo proveedor {$referencia}",
            partidas: [
                ['cuenta_id' => $cuentaAnticipoId, 'debe' => $monto, 'haber' => 0,
                 'descripcion' => "Anticipo {$referencia}"],
                ['cuenta_id' => $cuentaBancoId,    'debe' => 0, 'haber' => $monto,
                 'descripcion' => "Salida banco {$referencia}"],
            ],
            documentoTipo: 'ANTICIPO_PROV',
            documentoId:   $anticiPoId,
            documentoRef:  $referencia,
            esAutomatico:  true,
            fecha:         $fecha,
        );
    }

    // ══════════════════════════════════════════════════════════
    // CIERRE DE CAJA — Dev 2
    // ══════════════════════════════════════════════════════════
    public function cierreCaja(
        int    $empresaId,
        int    $cierreId,
        string $codigo,
        string $fecha,
        float  $montoDeclarado,
        float  $montoEsperado,
        int    $cuentaCajaId,
    ): ?AsientoContable {
        $diferencia = round($montoDeclarado - $montoEsperado, 2);

        if (abs($diferencia) < 0.01) {
            return null;
        }

        try {
            $cuentaAjusteId = $this->cuentaId('cta_ajuste_inventario', $empresaId);
        } catch (\Exception) {
            $cuentaAjusteId = $cuentaCajaId;
        }

        $partidas = $diferencia > 0
            ? [
                ['cuenta_id' => $cuentaCajaId,   'debe' => $diferencia, 'haber' => 0,
                 'descripcion' => "Sobrante cierre caja {$codigo}"],
                ['cuenta_id' => $cuentaAjusteId, 'debe' => 0, 'haber' => $diferencia,
                 'descripcion' => "Sobrante cierre caja {$codigo}"],
              ]
            : [
                ['cuenta_id' => $cuentaAjusteId, 'debe' => abs($diferencia), 'haber' => 0,
                 'descripcion' => "Faltante cierre caja {$codigo}"],
                ['cuenta_id' => $cuentaCajaId,   'debe' => 0, 'haber' => abs($diferencia),
                 'descripcion' => "Faltante cierre caja {$codigo}"],
              ];

        return $this->crear(
            empresaId:    $empresaId,
            concepto:     "Cierre de caja {$codigo} — " . ($diferencia > 0 ? 'sobrante' : 'faltante'),
            partidas:     $partidas,
            documentoTipo: 'CIERRE_CAJA',
            documentoId:  $cierreId,
            documentoRef: $codigo,
            esAutomatico: true,
            fecha:        $fecha,
        );
    }

    // ── Cruce anticipo proveedor contra CxP — Dev 2 C-08 ────────────────────────
    public function cruciarAnticipo(
        int     $empresaId,
        int     $anticipoId,
        string  $referencia,
        float   $monto,
        ?string $fecha        = null,
        ?int    $centroCostoId = null,
    ): AsientoContable {
        $anticipo  = AnticipoProveedor::with('proveedor')->findOrFail($anticipoId);
        $proveedor = $anticipo->proveedor;
        $cc        = $centroCostoId;

        // BUG REAL encontrado en auditoría (2026-07-29): comparaba contra
        // 'exterior', pero proveedores.tipo solo usa 'nacional'/
        // 'internacional' (confirmado en la tabla real y en
        // Proveedor::scopeInternacionales()) — la condición nunca era
        // verdadera, así que el cruce de anticipo SIEMPRE enviaba la CxP
        // resultante a "Proveedores Locales" (2.1.1.01), incluso para
        // proveedores genuinamente internacionales, en vez de
        // "Proveedores del Exterior" (2.1.1.02) como pide el cliente.
        $ctaProvId = ($proveedor && $proveedor->tipo === 'internacional')
            ? $this->cuentaId('cta_proveedores_exterior', $empresaId)
            : $this->cuentaId('cta_proveedores_locales',  $empresaId);

        $ctaAnticipoId = $this->cuentaId('cta_anticipos_proveedores', $empresaId);

        return $this->crear(
            empresaId:    $empresaId,
            concepto:     "Cruce anticipo proveedor {$referencia}",
            partidas: [
                ['cuenta_id' => $ctaProvId,     'debe' => $monto, 'haber' => 0,
                 'centro_costo_id' => $cc,
                 'descripcion' => "Cruce anticipo {$referencia} — CxP cancelada"],
                ['cuenta_id' => $ctaAnticipoId, 'debe' => 0, 'haber' => $monto,
                 'centro_costo_id' => $cc,
                 'descripcion' => "Cruce anticipo {$referencia} — anticipo aplicado"],
            ],
            documentoTipo: 'ANTICIPO_PROV',
            documentoId:   $anticipoId,
            documentoRef:  $referencia,
            esAutomatico:  true,
            fecha:         $fecha,
        );
    }

    // ── Asiento de ajuste por diferencia en conciliación bancaria ──────────────
    public function ajusteConciliacion(
        int     $empresaId,
        int     $conciliacionId,
        float   $diferencia,
        string  $descripcion = 'Ajuste conciliación bancaria',
        ?string $fecha       = null,
    ): AsientoContable {
        // Si diferencia > 0 → falta dinero en sistema (ingreso no registrado)
        // Si diferencia < 0 → sobra en sistema (egreso no registrado)
        $ctaBancos = $this->cuentaId('cta_bancos_locales', $empresaId);
        $ctaAjuste = $this->cuentaId('cta_ajuste_conciliacion', $empresaId);

        $partidas = $diferencia > 0
            ? [
                ['cuenta_id' => $ctaBancos, 'debe' => abs($diferencia), 'haber' => 0,            'descripcion' => $descripcion],
                ['cuenta_id' => $ctaAjuste, 'debe' => 0,                'haber' => abs($diferencia), 'descripcion' => $descripcion],
              ]
            : [
                ['cuenta_id' => $ctaAjuste, 'debe' => abs($diferencia), 'haber' => 0,            'descripcion' => $descripcion],
                ['cuenta_id' => $ctaBancos, 'debe' => 0,                'haber' => abs($diferencia), 'descripcion' => $descripcion],
              ];

        return $this->crear(
            empresaId: $empresaId, concepto: $descripcion,
            partidas: $partidas,
            documentoTipo: 'CONCILIACION', documentoId: $conciliacionId,
            documentoRef: "CONC-{$conciliacionId}", esAutomatico: true,
            fecha: $fecha,
        );
    }
}
