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

        // 3b. Cada línea lleva debe o haber, nunca los dos; mínimo dos líneas con valor (02-08 §2.1)
        $conValor = 0;
        foreach ($partidas as $i => $p) {
            $d = (float) ($p['debe']  ?? 0);
            $h = (float) ($p['haber'] ?? 0);
            if ($d > 0 && $h > 0) {
                throw new \Exception('Partida #' . ($i + 1) . ': una línea no puede tener debe y haber a la vez.');
            }
            if ($d > 0 || $h > 0) {
                $conValor++;
            }
        }
        if ($conValor < 2) {
            throw new \Exception('Un asiento requiere mínimo 2 líneas con valor.');
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

            // Centro de costo obligatorio en ingresos, costos y gastos (PC-GEN-02: cuentas 4, 5 y 6).
            // En un asiento manual se exige; en los automáticos de cada módulo, si no lo traen, se
            // asigna el centro de costo de la empresa.
            if (in_array(substr((string) $cuenta->codigo, 0, 1), ['4', '5', '6'], true) && empty($partida['centro_costo_id'])) {
                if (!$esAutomatico) {
                    throw new \Exception(
                        'Partida #' . ($index + 1) . ": la cuenta {$cuenta->codigo} exige centro de costo (ingresos, costos y gastos)."
                    );
                }
                $partidas[$index]['centro_costo_id'] = $this->centroCostoDeEmpresa($empresaId);
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

    /** Centro de costo propio de la empresa (UIO / Import), por defecto de los asientos automáticos. */
    private function centroCostoDeEmpresa(int $empresaId): ?int
    {
        static $cache = [];

        return $cache[$empresaId] ??= \App\Models\CentroCosto::where('empresa_id', $empresaId)
            ->where('tipo', 'empresa')->value('id');
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

    // Mapa de parámetro → código del PLAN DE CUENTAS FINAL (database/data/plan_cuentas_final.php,
    // migración cargar_plan_cuentas_final). Mantener en sincronía con REAPUNTES de esa migración.
    // El plan final no trae cuentas generales de venta/costo/inventario (solo por categoría):
    // las genéricas (cta_ventas_*, cta_costo_ventas*, cta_inventario_mercaderia) apuntan a las
    // cuentas "sin clasificar" 4.01.09, 5.01.08 y 1.01.04.09 como respaldo provisional hasta que
    // cada producto use la cuenta de su categoría (DEC-030).
    private const FALLBACK_PLAN = [
        // ── Activo ────────────────────────────────────────
        'cta_caja_general'              => '1.01.01.01',
        'cta_cajas_chicas'              => '1.01.01.02',
        'cta_bancos_exterior'           => '1.01.02.03',
        'cta_banco_pichincha'           => '1.01.02.01.01',
        'cta_banco_produbanco'          => '1.01.02.02.01',
        'cta_banco_guayaquil'           => '1.01.02.02.02',
        'cta_vouchers'                  => '1.01.03.04',
        'cta_clientes_locales'          => '1.01.03.01',
        'cta_clientes_exterior'         => '1.01.03.03',
        'cta_anticipos_proveedores'     => '1.01.06.01',
        'cta_anticipos_empleados'       => '1.01.06.02',
        'cta_provision_incobrables'     => '1.01.03.02',
        'cta_cheques_cartera'           => '1.01.03.05',
        'cta_iva_comision_tarjeta'      => '1.01.05.01',
        'cta_inventario_mercaderia'     => '1.01.04.09',
        'cta_inventario_transito'       => '1.01.04.07',
        'cta_iva_compras'               => '1.01.05.01',
        'cta_retencion_iva_cobrada'     => '1.01.05.03',
        'cta_retencion_ir_cobrada'      => '1.01.05.02',
        // ── Pasivo ────────────────────────────────────────
        'cta_proveedores_locales'       => '2.01.01.01',
        'cta_proveedores_exterior'      => '2.01.01.02',
        'cta_proveedores_logisticos'    => '2.01.01.03',
        'cta_iva_por_pagar_mes'         => '2.01.02.04',
        'cta_cuenta_socio'              => '2.01.06',
        'cta_anticipos_clientes'        => '2.01.04',
        'cta_retencion_ir'              => '2.01.02.02',
        'cta_retencion_iva'             => '2.01.02.03',
        'cta_impuesto_renta_pagar'      => '2.01.07',
        'cta_iva_ventas'                => '2.01.02.01',
        'cta_nomina_por_pagar'          => '2.01.03.01',
        'cta_iess_por_pagar'            => '2.01.03.02',
        'cta_iess_personal_por_pagar'   => '2.01.03.02',
        'cta_decimo_tercero_pagar'      => '2.01.03.03.01',
        'cta_decimo_cuarto_pagar'       => '2.01.03.03.02',
        'cta_vacaciones_pagar'          => '2.01.03.03.03',
        'cta_fondos_reserva_pagar'      => '2.01.03.04',
        'cta_participacion_trabajadores' => '2.01.03.05',
        // ── Patrimonio ────────────────────────────────────────
        'cta_capital'                   => '3.01.01',
        'cta_retiros_propietario'       => '3.01.04.01',
        'cta_ganancias_acumuladas'      => '3.01.02.01',
        'cta_perdidas_acumuladas'       => '3.01.02.02',
        'cta_utilidad_periodo'          => '3.01.03.01',
        'cta_perdida_periodo'           => '3.01.03.01',
        // ── Ingresos ────────────────────────────────────────
        'cta_ventas_exterior'           => '4.01.09',
        'cta_ingresos_servicios'        => '4.01.08',
        'cta_otros_ingresos'            => '4.04',
        'cta_devoluciones_ventas'       => '4.02.01',
        'cta_descuentos_ventas'         => '4.02.02',
        // ── Costo de ventas ────────────────────────────────────────
        'cta_costo_servicios'           => '5.01.07.01',
        'cta_ventas_repuestos_taller'   => '4.01.06',
        'cta_costo_repuestos_taller'    => '5.01.06',
        'cta_inventario_repuestos_taller' => '1.01.04.06',
        'cta_ajuste_inventario'         => '5.01.09',
        // ── Gastos de personal ────────────────────────────────────────
        'cta_sueldos_salarios'          => '6.01.01',
        'cta_horas_extras'              => '6.01.01',
        'cta_aporte_patronal'           => '6.01.02',
        'cta_decimo_tercero'            => '6.01.01',
        'cta_decimo_cuarto'             => '6.01.01',
        'cta_vacaciones'                => '6.01.01',
        'cta_fondos_reserva'            => '6.01.02',
        // ── Gastos generales / financieros / otros ────────────────────────────────────────
        'cta_gasto_servicios'           => '6.01.11',
        'cta_gasto_arrendamiento'       => '6.01.03',
        'cta_gasto_servicios_basicos'   => '6.01.04',
        'cta_gasto_publicidad'          => '6.01.05',
        'cta_comisiones_bancarias'      => '6.01.10',
        'cta_gastos_no_deducibles'      => '6.02.01',
        'cta_ajuste_conciliacion'       => '6.01.14',
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
                // Auto-guardar para que futuras llamadas sean directas. Se guarda
                // como configuración general (compartida por las dos empresas).
                ParametroContable::updateOrCreate(
                    ['empresa_id' => null, 'codigo' => $codigo],
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
                ['cuenta_id' => $this->cuentaBancoElegida($ctaBancoId, 'pagar la nómina'),
                 'debe' => 0, 'haber' => $monto, 'descripcion' => "Pago nómina {$referencia}"],
            ],
            documentoTipo: 'NOMPAG',
            documentoId:   $nominaId,
            documentoRef:  $referencia,
            esAutomatico:  true,
            fecha:         $fecha,
        );
    }

    // ══════════════════════════════════════════════════════════
    // Cuentas por producto (PC-CAT-01 a 20): venta, costo e inventario salen de la SUBCATEGORÍA
    // ══════════════════════════════════════════════════════════

    /**
     * Cuentas de inventario, venta y costo de cada producto según su subcategoría. Es ESTRICTO (DEC-030): un
     * producto sin subcategoría con cuentas no se contabiliza; solo Computadoras y PISOS LED usan la cuenta «sin
     * clasificar» porque así está en su fila de PC-CAT-19/20. Los productos tipo «servicio» venden en ingresos por
     * servicios y no mueven inventario.
     *
     * @param  array<int,int|null> $productoIds
     * @return array<int,array{inventario:?int,venta:?int,costo:?int,servicio:bool}>
     * @throws \DomainException con la lista de productos sin clasificar
     */
    public function cuentasProductos(array $productoIds, int $empresaId = 0): array
    {
        $svc = app(\App\Services\CuentasProductoService::class);
        $resultado = [];
        $faltan = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $productoIds))));
        $productos = DB::table('productos')->whereIn('id', $ids)->get(['id', 'codigo', 'nombre', 'tipo'])->keyBy('id');

        foreach ($ids as $id) {
            $p = $productos[$id] ?? null;
            if ($p && $p->tipo === 'servicio') {
                // El servicio vende en la cuenta de su subcategoría (Servicio técnico 4.01.08, Eventos 4.04, siempre por
                // producto); sin subcategoría usa el parámetro de ingresos por servicios.
                $cuentaServicio = $svc->paraProducto($id)['venta'] ?? null;
                $resultado[$id] = ['inventario' => null, 'costo' => null, 'servicio' => true,
                    'venta' => $cuentaServicio ?: ($empresaId ? $this->cuentaId('cta_ingresos_servicios', $empresaId) : null)];
                continue;
            }
            $c = $svc->paraProducto($id);
            if ($c) {
                $resultado[$id] = $c + ['servicio' => false];
                continue;
            }
            $faltan[] = $p ? trim("{$p->codigo} {$p->nombre}") : "#{$id}";
        }

        if ($faltan) {
            throw new \DomainException(
                'No se puede contabilizar: ' . count($faltan) . ' producto(s) no tienen subcategoría con cuentas contables '
                . '(Inventario → Productos → asignar subcategoría): ' . implode('; ', array_slice($faltan, 0, 5))
                . (count($faltan) > 5 ? ' y ' . (count($faltan) - 5) . ' más' : '') . '.'
            );
        }

        return $resultado;
    }

    /** Costo (del kárdex) por producto de lo que salió con un documento. @return array<int,float> */
    public static function costoSalidaPorProducto(string $docTipo, int $docId): array
    {
        return DB::table('inventario_movimientos')
            ->where('doc_tipo', strtoupper($docTipo))->where('doc_id', $docId)->where('tipo', 'salida')
            ->select('producto_id', DB::raw('SUM(costo_total) as costo'))->groupBy('producto_id')
            ->pluck('costo', 'producto_id')->map(fn ($v) => (float) $v)->all();
    }

    /** Suma $monto a la cuenta $cuentaId dentro de $acumulado. */
    private function acumular(array &$acumulado, int $cuentaId, float $monto): void
    {
        $acumulado[$cuentaId] = round(($acumulado[$cuentaId] ?? 0) + $monto, 2);
    }

    /** Reparte la diferencia de redondeo ($objetivo - suma) en la cuenta de mayor monto. */
    private function ajustarRedondeo(array &$acumulado, float $objetivo): void
    {
        if (!$acumulado) {
            return;
        }
        $dif = round($objetivo - array_sum($acumulado), 2);
        if ($dif != 0.0 && abs($dif) <= 0.05) {
            arsort($acumulado);
            $acumulado[array_key_first($acumulado)] = round($acumulado[array_key_first($acumulado)] + $dif, 2);
        }
    }

    /**
     * Parámetros contables que necesita compraRegistrada() según el tipo de asiento.
     * (La cuenta de cada línea sale de la subcategoría del producto o de la cuenta que elige quien registra un gasto
     * sin producto: DEC-012; ya no hay cuenta de gasto por defecto.)
     * Debe mantenerse en sincronía con esa función.
     */
    public static function codigosCompra(string $tipoAsiento, bool $retIR = false, bool $retIVA = false): array
    {
        if ($tipoAsiento === 'no_deducible') {
            return ['cta_gastos_no_deducibles', 'cta_proveedores_locales'];
        }

        $codigos = [
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
        // Venta, costo e inventario salen de la subcategoría de cada producto (cuentasProductos()), no de parámetros
        $codigos = ['cta_iva_ventas'];

        foreach ($formasPago ?: ['efectivo'] as $fp) {
            $forma = is_array($fp) ? ($fp['forma'] ?? 'efectivo') : $fp;
            // Transferencia / depósito: la cuenta es la del banco elegido en la factura (no es un parámetro). Cheque: cartera.
            $codigo = match (strtolower(trim((string) $forma))) {
                'credito', 'crédito'                              => 'cta_clientes_locales',
                'cheque'                                          => 'cta_cheques_cartera',
                'transferencia', 'deposito', 'depósito'           => null,
                'tarjeta', 'tarjeta_credito',
                'tarjeta_debito', 'datafast'                      => 'cta_vouchers',
                default                                           => 'cta_caja_general',
            };
            if ($codigo) {
                $codigos[] = $codigo;
            }
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
    public function cuentaCobroPorForma(string $formaPago, int $empresaId, ?int $bancoCajaId = null): int
    {
        // Transferencia o cheque con la cuenta bancaria elegida al cobrar (PC-BAN-01): su propia cuenta contable
        $forma = strtolower(trim($formaPago));

        // El cheque recibido queda en cartera hasta que se deposite (PC-VEN-13, DEC-001): no es banco ni movimiento bancario.
        if ($forma === 'cheque') {
            return $this->cuentaId('cta_cheques_cartera', $empresaId);
        }

        if ($bancoCajaId && in_array($forma, ['transferencia', 'deposito', 'depósito'], true)) {
            $banco = \App\Models\BancoCaja::where('empresa_id', $empresaId)->where('id', $bancoCajaId)->first();
            if (!$banco) {
                throw new \DomainException('El banco elegido no pertenece a esta empresa.');
            }
            if (!$banco->cuenta_id) {
                throw new \DomainException("El banco «{$banco->nombre}» no tiene cuenta contable asignada (Bancos → Bancos y Cajas).");
            }

            return (int) $banco->cuenta_id;
        }

        // Sin banco elegido: no hay banco por defecto. La transferencia y el depósito exigen elegirlo.
        if (in_array($forma, ['transferencia', 'deposito', 'depósito'], true)) {
            throw new \DomainException('Seleccione el banco de la ' . ($forma === 'transferencia' ? 'transferencia' : 'forma de pago') . ': el sistema no usa un banco por defecto.');
        }

        return match ($forma) {
            'credito', 'crédito' => $this->cuentaId('cta_clientes_locales', $empresaId),
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
        ?array  $lineas     = null,
        bool    $esEvento   = false,
    ): AsientoContable {

        $partidas = [];

        // ── (1) Débito: una partida por forma de pago ──────────────────────
        $desglose = [];
        foreach ($formasPago ?? [] as $fp) {
            $monto = round((float)($fp['monto'] ?? 0), 2);
            if ($monto <= 0) continue;
            $desglose[] = ['forma' => (string)($fp['forma'] ?? 'efectivo'), 'monto' => $monto, 'banco_caja_id' => $fp['banco_caja_id'] ?? null];
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
            $cta = $this->cuentaCobroPorForma($d['forma'], $empresaId, $d['banco_caja_id'] ?? null);
            $porCuenta[$cta] = round(($porCuenta[$cta] ?? 0) + $d['monto'], 2);
        }

        // Toda venta nace en cuentas por cobrar (02-04 §2.3): DEBE Clientes por el total. Cada cobro que no
        // es crédito la cancela: DEBE caja / banco / vouchers y HABER Clientes. La venta a crédito queda
        // abierta en Clientes y no mueve Caja ni Bancos.
        $ctaClientes = $this->cuentaId('cta_clientes_locales', $empresaId);
        $partidas[] = [
            'cuenta_id'   => $ctaClientes,
            'debe'        => round($total, 2),
            'haber'       => 0,
            'descripcion' => "Venta a cliente — Factura {$numeroFactura}",
        ];
        foreach ($porCuenta as $cuentaId => $monto) {
            if ($cuentaId === $ctaClientes) {
                continue; // crédito: la cuenta por cobrar queda abierta
            }
            $partidas[] = [
                'cuenta_id'   => $cuentaId,
                'debe'        => $monto,
                'haber'       => 0,
                'descripcion' => "Cobro Factura {$numeroFactura}",
            ];
            $partidas[] = [
                'cuenta_id'   => $ctaClientes,
                'debe'        => 0,
                'haber'       => $monto,
                'descripcion' => "Cancelación CxC Factura {$numeroFactura}",
            ];
        }

        // ── (1) Crédito: ingreso e IVA ─────────────────────────────────────
        // Con líneas, la venta y el costo salen de la subcategoría de cada producto (PC-CAT).
        $ventas = $costos = $inventarios = [];
        if ($lineas !== null) {
            $cuentas = $this->cuentasProductos(array_column($lineas, 'producto_id'), $empresaId);
            foreach ($lineas as $l) {
                $c = $cuentas[(int) $l['producto_id']];
                $sub = round((float) ($l['subtotal'] ?? 0), 2);
                $cos = round((float) ($l['costo'] ?? 0), 2);
                if ($sub > 0) {
                    if (!$c['venta']) {
                        throw new \DomainException("El producto #{$l['producto_id']} (regalo) no tiene cuenta de venta: no puede facturarse con valor.");
                    }
                    // Factura de evento (PC-VEN-16, DEC-050): el ingreso va a «otros ingresos», no a la cuenta del producto
                    $this->acumular($ventas, $esEvento ? $this->cuentaId('cta_otros_ingresos', $empresaId) : (int) $c['venta'], $sub);
                }
                if ($cos > 0 && !$c['servicio']) {
                    $this->acumular($costos, (int) $c['costo'], $cos);
                    $this->acumular($inventarios, (int) $c['inventario'], $cos);
                }
            }
            $this->ajustarRedondeo($ventas, round($subtotal, 2));
            foreach ($ventas as $cuentaVenta => $monto) {
                $partidas[] = ['cuenta_id' => $cuentaVenta, 'debe' => 0, 'haber' => $monto, 'descripcion' => "Venta — {$numeroFactura}"];
            }
        } else {
            // Sin líneas de producto no hay de dónde sacar la cuenta de venta: ya no existe una cuenta genérica (DEC-012/030)
            throw new \DomainException('La factura necesita sus líneas de producto para contabilizar la venta por subcategoría.');
        }
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
        if ($lineas !== null) {
            foreach ($costos as $cuentaCosto => $monto) {
                $partidas[] = ['cuenta_id' => $cuentaCosto, 'debe' => $monto, 'haber' => 0, 'descripcion' => "Costo de ventas — {$numeroFactura}"];
            }
            foreach ($inventarios as $cuentaInv => $monto) {
                $partidas[] = ['cuenta_id' => $cuentaInv, 'debe' => 0, 'haber' => $monto, 'descripcion' => "Baja de inventario por venta — {$numeroFactura}"];
            }
        } elseif ($costoVenta > 0) {
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

    /**
     * Parámetros que necesita facturaTaller() según las formas de pago usadas y si la orden lleva repuestos.
     * Se usa con validarConfiguracion() ANTES de generar la factura.
     */
    public static function codigosFacturaTaller(array $formasPago = [], bool $conServicio = true, bool $conRepuestos = false, bool $conIva = true): array
    {
        $codigos = ['cta_clientes_locales'];
        if ($conServicio) {
            $codigos[] = 'cta_ingresos_servicios';
        }
        if ($conRepuestos) {
            array_push($codigos, 'cta_ventas_repuestos_taller', 'cta_costo_repuestos_taller', 'cta_inventario_repuestos_taller');
        }
        if ($conIva) {
            $codigos[] = 'cta_iva_ventas';
        }
        foreach ($formasPago as $forma) {
            $codigo = match (strtolower(trim((string) $forma))) {
                'credito', 'crédito'                              => 'cta_clientes_locales',
                'cheque'                                          => 'cta_cheques_cartera',
                'transferencia', 'deposito', 'depósito'           => null, // la cuenta es la del banco elegido
                'tarjeta', 'tarjeta_credito',
                'tarjeta_debito', 'datafast'                      => 'cta_vouchers',
                default                                           => 'cta_caja_general',
            };
            if ($codigo) {
                $codigos[] = $codigo;
            }
        }

        return array_values(array_unique($codigos));
    }

    /**
     * Factura de una orden de Taller (02-10 §2.9, PC-TAL-01 y PC-TAL-02), siempre en el centro de costo
     * Altamira FIX:
     *   DEBE  Clientes por el total (cada cobro que no es crédito la cancela contra caja / banco / vouchers)
     *   HABER Ingresos por Servicio Técnico (servicio)   — PC-TAL-01
     *   HABER Venta de Repuestos (repuestos)             — PC-TAL-02
     *   HABER IVA en ventas
     *   DEBE  Costo de Ventas de Repuestos / HABER Inventario de Repuestos (costo del kárdex) — PC-TAL-02
     */
    public function facturaTaller(
        int     $empresaId,
        int     $facturaId,
        string  $numeroFactura,
        float   $subtotalServicio,
        float   $subtotalRepuestos,
        float   $iva,
        float   $total,
        array   $formasPago,
        float   $costoRepuestos,
        int     $centroCostoId,
        ?string $fecha = null,
    ): AsientoContable {
        $cc = $centroCostoId;
        $partidas = [];

        $ctaClientes = $this->cuentaId('cta_clientes_locales', $empresaId);
        $partidas[] = ['cuenta_id' => $ctaClientes, 'debe' => round($total, 2), 'haber' => 0,
            'centro_costo_id' => $cc, 'descripcion' => "Servicio técnico — Factura {$numeroFactura}"];

        // Cada cobro que no es a crédito cancela la cuenta por cobrar contra caja / banco / vouchers
        $porCuenta = [];
        foreach ($formasPago as $fp) {
            $monto = round((float) ($fp['monto'] ?? 0), 2);
            if ($monto <= 0) {
                continue;
            }
            $cta = $this->cuentaCobroPorForma((string) ($fp['forma'] ?? 'efectivo'), $empresaId, $fp['banco_caja_id'] ?? null);
            $porCuenta[$cta] = round(($porCuenta[$cta] ?? 0) + $monto, 2);
        }
        foreach ($porCuenta as $cuentaId => $monto) {
            if ($cuentaId === $ctaClientes) {
                continue; // crédito: la cuenta por cobrar queda abierta
            }
            $partidas[] = ['cuenta_id' => $cuentaId, 'debe' => $monto, 'haber' => 0,
                'centro_costo_id' => $cc, 'descripcion' => "Cobro Factura {$numeroFactura}"];
            $partidas[] = ['cuenta_id' => $ctaClientes, 'debe' => 0, 'haber' => $monto,
                'centro_costo_id' => $cc, 'descripcion' => "Cancelación CxC Factura {$numeroFactura}"];
        }

        if (round($subtotalServicio, 2) > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_ingresos_servicios', $empresaId),
                'debe' => 0, 'haber' => round($subtotalServicio, 2), 'centro_costo_id' => $cc,
                'descripcion' => "Servicio técnico — {$numeroFactura}"];
        }
        if (round($subtotalRepuestos, 2) > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_ventas_repuestos_taller', $empresaId),
                'debe' => 0, 'haber' => round($subtotalRepuestos, 2), 'centro_costo_id' => $cc,
                'descripcion' => "Repuestos de taller — {$numeroFactura}"];
        }
        if (round($iva, 2) > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_iva_ventas', $empresaId),
                'debe' => 0, 'haber' => round($iva, 2), 'centro_costo_id' => $cc,
                'descripcion' => "IVA en ventas — {$numeroFactura}"];
        }

        $costoRepuestos = round($costoRepuestos, 2);
        if ($costoRepuestos > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_costo_repuestos_taller', $empresaId),
                'debe' => $costoRepuestos, 'haber' => 0, 'centro_costo_id' => $cc,
                'descripcion' => "Costo de repuestos — {$numeroFactura}"];
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_inventario_repuestos_taller', $empresaId),
                'debe' => 0, 'haber' => $costoRepuestos, 'centro_costo_id' => $cc,
                'descripcion' => "Baja de inventario de repuestos — {$numeroFactura}"];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Factura de taller {$numeroFactura}",
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
        ?int    $bancoCajaId = null,
    ): AsientoContable {
        $cta = $this->cuentaCobroPorForma($formaPago, $empresaId, $bancoCajaId);

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

    /** Depósito de un cheque en cartera (PC-VEN-14): DEBE banco / HABER cheques en cartera. */
    public function depositoChequeCartera(int $empresaId, int $chequeId, string $referencia, float $monto, int $ctaBancoId, ?string $fecha = null): AsientoContable
    {
        return $this->crear(
            empresaId: $empresaId, concepto: "Depósito de cheque en cartera — {$referencia}",
            partidas: [
                ['cuenta_id' => $this->cuentaBancoElegida($ctaBancoId, 'depositar el cheque'), 'debe' => $monto, 'haber' => 0,
                 'descripcion' => "Depósito cheque {$referencia}"],
                ['cuenta_id' => $this->cuentaId('cta_cheques_cartera', $empresaId), 'debe' => 0, 'haber' => $monto,
                 'descripcion' => "Salida de cartera cheque {$referencia}"],
            ],
            documentoTipo: 'CHQREC', documentoId: $chequeId,
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
        ?int    $ctaBancoId = null,
        float   $retencionIva = 0,
        float   $retencionIr  = 0,
    ): AsientoContable {
        // Efectivo entra a la caja; transferencia al banco que el usuario eligió al cobrar (sin banco por defecto);
        // el cheque queda en cartera hasta depositarlo (PC-VEN-13).
        $cta = match (true) {
            $formaPago === 'efectivo' => $ctaBancoId ?: $this->cuentaId('cta_caja_general', $empresaId),
            $formaPago === 'cheque'   => $this->cuentaId('cta_cheques_cartera', $empresaId),
            default                   => $this->cuentaBancoElegida($ctaBancoId, 'registrar el cobro'),
        };

        // La retención que hace el cliente cancela cuenta por cobrar y crea crédito tributario (PC-VEN-12)
        $retencionIva = round($retencionIva, 2);
        $retencionIr  = round($retencionIr, 2);
        $partidas = [['cuenta_id' => $cta, 'debe' => $monto, 'haber' => 0, 'descripcion' => "Cobro {$referencia}"]];
        if ($retencionIva > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_retencion_iva_cobrada', $empresaId), 'debe' => $retencionIva, 'haber' => 0,
                           'descripcion' => "Retención IVA recibida {$referencia}"];
        }
        if ($retencionIr > 0) {
            $partidas[] = ['cuenta_id' => $this->cuentaId('cta_retencion_ir_cobrada', $empresaId), 'debe' => $retencionIr, 'haber' => 0,
                           'descripcion' => "Retención IR recibida {$referencia}"];
        }
        $partidas[] = ['cuenta_id' => $this->cuentaId('cta_clientes_locales', $empresaId),
                       'debe' => 0, 'haber' => round($monto + $retencionIva + $retencionIr, 2),
                       'descripcion' => "Cancelación CxC {$referencia}"];

        return $this->crear(
            empresaId: $empresaId, concepto: "Cobro CxC — {$referencia}",
            partidas: $partidas,
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
        ?array  $costosPorProducto = null,
        ?int    $bancoCajaId = null,
    ): AsientoContable {
        $partidas = [
            ['cuenta_id' => $this->cuentaId('cta_devoluciones_ventas', $empresaId),
             'debe' => round($subtotal, 2), 'haber' => 0,
             'descripcion' => "Devolución en ventas NC {$referencia}"],
            ['cuenta_id' => $this->cuentaCobroPorForma($formaPago, $empresaId, $bancoCajaId),
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
        if ($costosPorProducto) {
            // Reversa en las cuentas de inventario y costo de la subcategoría de cada producto devuelto
            $cuentas = $this->cuentasProductos(array_keys($costosPorProducto), $empresaId);
            $inventarios = $costos = [];
            foreach ($costosPorProducto as $productoId => $monto) {
                $c = $cuentas[(int) $productoId];
                if ($monto > 0 && !$c['servicio']) {
                    $this->acumular($inventarios, (int) $c['inventario'], (float) $monto);
                    $this->acumular($costos, (int) $c['costo'], (float) $monto);
                }
            }
            foreach ($inventarios as $cuenta => $monto) {
                $partidas[] = ['cuenta_id' => $cuenta, 'debe' => $monto, 'haber' => 0, 'descripcion' => "Reingreso de inventario por NC {$referencia}"];
            }
            foreach ($costos as $cuenta => $monto) {
                $partidas[] = ['cuenta_id' => $cuenta, 'debe' => 0, 'haber' => $monto, 'descripcion' => "Reversa costo de ventas NC {$referencia}"];
            }
        } elseif ($costoVenta > 0) {
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

        // Provisiones mensuales (no se descuentan al colaborador): décimo tercero 1/12 del sueldo, décimo
        // cuarto 1/12 del salario básico, vacaciones 15 días por año y fondos de reserva 1/12 con más de 12
        // meses. Solo se provisiona lo que se acumula; lo que se mensualiza ya se paga en el rol (PC-NOM-04 a 07).
        $provDecimoTercero = $provDecimoCuarto = $provVacaciones = $provFondos = 0.0;
        $fechaNomina = $nomina->fecha_emision ?? now();
        foreach ($detalles as $d) {
            $col = $d->colaborador;
            if (!$col) {
                continue;
            }
            $sueldo = (float) $d->sueldo_base;
            if (($col->decimo_tercero ?? 'acumula') === 'acumula') {
                $provDecimoTercero += round($sueldo / 12, 2);
            }
            if (($col->decimo_cuarto ?? 'acumula') === 'acumula') {
                $provDecimoCuarto += round(\App\Services\NominaCalculoService::SBU / 12, 2);
            }
            $provVacaciones += round($sueldo * 15 / 360, 2);
            if (($col->fondos_reserva ?? 'acumula') === 'acumula' && $col->fecha_ingreso
                && $col->fecha_ingreso->copy()->addMonths(12)->lte($fechaNomina)) {
                $provFondos += round($sueldo / 12, 2);
            }
        }

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

        // Provisiones: gasto al debe y pasivo propio al haber (parámetros de «Nómina» en Parámetros Contables)
        $provisiones = [
            ['monto' => $provDecimoTercero, 'gasto' => 'cta_decimo_tercero', 'pasivo' => 'cta_decimo_tercero_pagar', 'nombre' => 'décimo tercero'],
            ['monto' => $provDecimoCuarto,  'gasto' => 'cta_decimo_cuarto',  'pasivo' => 'cta_decimo_cuarto_pagar',  'nombre' => 'décimo cuarto'],
            ['monto' => $provVacaciones,    'gasto' => 'cta_vacaciones',     'pasivo' => 'cta_vacaciones_pagar',     'nombre' => 'vacaciones'],
            ['monto' => $provFondos,        'gasto' => 'cta_fondos_reserva', 'pasivo' => 'cta_fondos_reserva_pagar', 'nombre' => 'fondos de reserva'],
        ];
        foreach ($provisiones as $pv) {
            $monto = round($pv['monto'], 2);
            if ($monto <= 0) {
                continue;
            }
            $partidas[] = ['cuenta_id' => $this->cuentaId($pv['gasto'], $empresaId),
                'debe' => $monto, 'haber' => 0, 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — provisión {$pv['nombre']}"];
            $partidas[] = ['cuenta_id' => $this->cuentaId($pv['pasivo'], $empresaId),
                'debe' => 0, 'haber' => $monto, 'centro_costo_id' => $cc,
                'descripcion' => "Nómina {$periodo} — provisión {$pv['nombre']} por pagar"];
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

    /**
     * Cuenta contable del banco elegido por el usuario. No existe banco por defecto (01 §5, DEC-038): si no se indicó
     * el banco, o el banco no tiene cuenta contable asignada, se bloquea la operación con un mensaje claro.
     */
    public function cuentaBancoElegida(?int $ctaBancoId, string $operacion = 'esta operación'): int
    {
        if (!$ctaBancoId) {
            throw new \DomainException("Seleccione el banco para {$operacion}: el sistema no usa un banco por defecto.");
        }

        return $ctaBancoId;
    }

    // Pago de nómina: DEBE Nómina por pagar / HABER Bancos.
    public function pagoNomina(\App\Models\Nomina $nomina, string $fechaPago, string $comprobante, ?int $ctaBancoId = null): AsientoContable
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
                // La cuenta del banco o caja desde el que realmente se paga (siempre se elige; no hay banco por defecto)
                ['cuenta_id' => $this->cuentaBancoElegida($ctaBancoId, 'pagar la nómina'),
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
    public function prestamoEmpleado(\App\Models\PrestamoEmpleado $prestamo, int $empresaId, ?int $ctaBancoId = null): AsientoContable
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
                ['cuenta_id'   => $this->cuentaBancoElegida($ctaBancoId, 'entregar el ' . strtolower($tipo)),
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
    public function liquidacionEmpleado(\App\Models\Liquidacion $liq, int $empresaId, ?int $ctaBancoId = null): AsientoContable
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
                ['cuenta_id'   => $this->cuentaBancoElegida($ctaBancoId, 'pagar el finiquito'),
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

    /**
     * Asiento de un faltante o sobrante de inventario (PC-INV-01), una línea por producto con la cuenta de inventario de su
     * subcategoría (modo estricto) y la contrapartida en 5.01.09 «faltantes y mermas» (`cta_ajuste_inventario`).
     *   faltante: Debe 5.01.09 / Haber inventario   —   sobrante: Debe inventario / Haber 5.01.09
     * Lo usan el ajuste de inventario y la merma al recibir un traslado entre bodegas.
     *
     * @param array<int,array{producto_id:int,valor:float}> $lineas  valor = cantidad × costo promedio
     */
    public function ajusteInventario(
        int     $empresaId,
        string  $tipo,
        array   $lineas,
        string  $concepto,
        string  $documentoTipo,
        ?int    $documentoId,
        string  $referencia,
        ?string $fecha = null,
    ): ?AsientoContable {
        $lineas = array_values(array_filter($lineas, fn ($l) => round((float) $l['valor'], 2) > 0));
        if (!$lineas) {
            return null;
        }

        $cuentas = $this->cuentasProductos(array_column($lineas, 'producto_id'), $empresaId);
        $codigos = DB::table('productos')->whereIn('id', array_column($lineas, 'producto_id'))->pluck('codigo', 'id');
        $ctaAjuste = $this->cuentaId('cta_ajuste_inventario', $empresaId);
        $cc = \App\Models\CentroCosto::where('empresa_id', $empresaId)->where('tipo', 'empresa')->value('id');
        $esFaltante = $tipo === 'faltante';

        $partidas = [];
        $total = 0.0;
        foreach ($lineas as $l) {
            $pid = (int) $l['producto_id'];
            $ctaInv = $cuentas[$pid]['inventario']
                ?? throw new \DomainException('El producto ' . ($codigos[$pid] ?? "#{$pid}") . ' es un servicio: no mueve inventario.');
            $valor = round((float) $l['valor'], 2);
            $total += $valor;
            $partidas[] = ['cuenta_id' => $ctaInv, 'debe' => $esFaltante ? 0 : $valor, 'haber' => $esFaltante ? $valor : 0,
                'descripcion' => ($esFaltante ? 'Baja de ' : 'Sobrante de ') . ($codigos[$pid] ?? "#{$pid}")];
        }
        $total = round($total, 2);
        array_unshift($partidas, ['cuenta_id' => $ctaAjuste, 'debe' => $esFaltante ? $total : 0, 'haber' => $esFaltante ? 0 : $total,
            'centro_costo_id' => $cc, 'descripcion' => $concepto]);

        return $this->crear(
            empresaId:     $empresaId,
            concepto:      $concepto,
            partidas:      $partidas,
            documentoTipo: $documentoTipo,
            documentoId:   $documentoId,
            documentoRef:  $referencia,
            esAutomatico:  true,
            fecha:         $fecha,
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
        ?array $lineas        = null,
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

        // Cada línea va a su cuenta: el producto, a la de inventario de su subcategoría (PC-CAT); un gasto sin producto, a
        // la cuenta que eligió quien lo registró (DEC-012: ya no hay cuenta de gasto por defecto).
        if (!$lineas) {
            throw new \DomainException('La compra no tiene líneas para contabilizar.');
        }
        $cuentas = $this->cuentasProductos(array_column($lineas, 'producto_id'), $empresaId);
        $debitos = [];
        foreach ($lineas as $l) {
            $sub = round((float) ($l['subtotal'] ?? 0), 2);
            if (!empty($l['producto_id'])) {
                $c = $cuentas[(int) $l['producto_id']];
                $cuenta = $c['servicio'] ? ($l['cuenta_id'] ?? null) : $c['inventario'];
            } else {
                $cuenta = $l['cuenta_id'] ?? null;
            }
            if (!$cuenta) {
                throw new \DomainException('Hay una línea de gasto sin cuenta contable: elija la cuenta de esa línea (no existe cuenta por defecto).');
            }
            $this->acumular($debitos, (int) $cuenta, $sub);
        }
        $this->ajustarRedondeo($debitos, round($subtotal, 2));
        if (abs(array_sum($debitos) - round($subtotal, 2)) > 0.01) {
            throw new \DomainException('Las líneas de la compra (' . number_format(array_sum($debitos), 2) . ') no suman su subtotal ('
                . number_format($subtotal, 2) . '): revise los valores de cada línea.');
        }
        $neto = $subtotal + $iva - $retencionIR - $retencionIVA;

        $partidas = [];
        foreach ($debitos as $cuentaDebito => $monto) {
            $partidas[] = ['cuenta_id' => $cuentaDebito, 'debe' => $monto, 'haber' => 0,
                'centro_costo_id' => $cc, 'descripcion' => "Compra {$referencia}"];
        }
        $partidas = array_merge($partidas, [
            ['cuenta_id' => $this->cuentaId('cta_iva_compras', $empresaId),
             'debe' => $iva, 'haber' => 0, 'centro_costo_id' => $cc,
             'descripcion' => "IVA compra {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
             'debe' => 0, 'haber' => $neto, 'centro_costo_id' => $cc,
             'descripcion' => "CxP {$referencia}"],
        ]);
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
        ?int   $ctaBancoId    = null,
    ): AsientoContable {
        $cc = $centroCostoId;
        return $this->crear(
            empresaId: $empresaId, concepto: "Pago proveedor {$referencia}",
            partidas: [
                ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
                 'debe' => $monto, 'haber' => 0, 'centro_costo_id' => $cc,
                 'descripcion' => "Pago {$referencia}"],
                ['cuenta_id' => $this->cuentaBancoElegida($ctaBancoId, 'pagar al proveedor'),
                 'debe' => 0, 'haber' => $monto, 'centro_costo_id' => $cc,
                 'descripcion' => "Pago {$referencia}"],
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
        ?int   $centroCostoId = null,
    ): ?AsientoContable {
        $diferencia = round($montoDeclarado - $montoEsperado, 2);

        if (abs($diferencia) < 0.01) {
            return null;
        }

        // Sobrante y faltante de arqueo van a 6.01.14 (PC-CAJ-12, PC-CAJ-13; DEC-005).
        // Antes usaba la cuenta de faltantes de inventario y, si faltaba, la propia caja.
        $cuentaAjusteId = $this->cuentaId('cta_ajuste_conciliacion', $empresaId);
        $partidas = $diferencia > 0
            ? [
                ['cuenta_id' => $cuentaCajaId,   'debe' => $diferencia, 'haber' => 0,
                 'descripcion' => "Sobrante cierre caja {$codigo}"],
                ['cuenta_id' => $cuentaAjusteId, 'debe' => 0, 'haber' => $diferencia, 'centro_costo_id' => $centroCostoId,
                 'descripcion' => "Sobrante cierre caja {$codigo}"],
              ]
            : [
                ['cuenta_id' => $cuentaAjusteId, 'debe' => abs($diferencia), 'haber' => 0, 'centro_costo_id' => $centroCostoId,
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
        ?int    $ctaBancoId  = null,
    ): AsientoContable {
        // Si diferencia > 0 → falta dinero en sistema (ingreso no registrado)
        // Si diferencia < 0 → sobra en sistema (egreso no registrado)
        // La cuenta del propio banco que se concilia (no hay banco por defecto)
        $ctaBancos = $this->cuentaBancoElegida($ctaBancoId, 'ajustar la conciliación');
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
