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
        $ejercicio = EjercicioContable::where('empresa_id', $empresaId)
            ->where('estado', 'abierto')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->first();

        if (!$ejercicio) {
            throw new \Exception(
                'No hay un período contable abierto. ' .
                'Abra un período en Contabilidad → Ejercicios antes de registrar asientos.'
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

        // 5. Crear en transacción
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

    // Mapa de códigos de parámetro → códigos del plan de cuentas verificados contra BD
    private const FALLBACK_PLAN = [
        'cta_caja_general'            => '1.1.1.01',
        'cta_cajas_chicas'            => '1.1.1.02',
        'cta_bancos_locales'          => '1.1.1.03',
        'cta_bancos_exterior'         => '1.1.1.04',
        'cta_vouchers'                => '1.1.1.05',
        'cta_clientes_locales'        => '1.1.3.01',
        'cta_clientes_exterior'       => '1.1.3.02',
        'cta_anticipos_proveedores'   => '1.1.3.03',
        'cta_anticipos_empleados'     => '1.1.3.04',
        'cta_inventario_mercaderia'   => '1.1.4.01',
        'cta_inventario_transito'     => '1.1.4.03',
        'cta_iva_compras'             => '1.1.5.01',
        'cta_retencion_iva_cobrada'   => '1.1.5.02',
        'cta_retencion_ir_cobrada'    => '1.1.5.03',
        'cta_proveedores_locales'     => '2.1.1.01',
        'cta_proveedores_exterior'    => '2.1.1.02',
        'cta_retencion_ir'            => '2.1.3.01',
        'cta_retencion_iva'           => '2.1.3.02',
        'cta_iva_ventas'              => '2.1.3.04',
        'cta_nomina_por_pagar'        => '2.1.4.01',
        'cta_iess_por_pagar'          => '2.1.4.02',
        'cta_anticipos_clientes'      => '2.1.6.01',
        'cta_ganancias_acumuladas'    => '3.1.3.01',
        'cta_perdidas_acumuladas'     => '3.1.3.02',
        'cta_utilidad_periodo'        => '3.1.4.01',
        'cta_perdida_periodo'         => '3.1.4.02',
        'cta_ventas_locales'          => '4.1.1.01',
        'cta_devoluciones_ventas'     => '4.1.3.01',
        'cta_costo_ventas'            => '5.1.1.01',
        'cta_costo_ventas_importadas' => '5.1.1.02',
        'cta_ajuste_inventario'       => '5.1.1.04',
        'cta_sueldos_salarios'        => '5.2.1.01',
        'cta_aporte_patronal'         => '5.2.1.03',
        'cta_comisiones_bancarias'    => '5.3.1.02',
        'cta_gastos_no_deducibles'    => '5.4.1.01',
        // Gastos operativos — compras sin producto asignado
        'cta_gasto_compras_default'   => '5.2.2.06',
        'cta_gasto_servicios'         => '5.2.2.01',
        'cta_gasto_arrendamiento'     => '5.2.2.02',
        'cta_gasto_servicios_basicos' => '5.2.2.03',
        'cta_gasto_publicidad'        => '5.2.2.11',
    ];

    private function cuentaId(string $codigo, int $empresaId): int
    {
        // 1. Buscar en parametros_contables
        $id = ParametroContable::getCuentaId($codigo, $empresaId);
        if ($id) {
            return $id;
        }

        // 2. Fallback: buscar en plan_cuentas por código conocido (sin filtro empresa_id)
        $planCodigo = self::FALLBACK_PLAN[$codigo] ?? null;
        if ($planCodigo) {
            $cuenta = PlanCuenta::where('codigo', $planCodigo)
                ->where('permite_asientos', true)
                ->where('estado', true)
                ->first();

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
    // MÉTODOS PARA DEV 1 — Ventas
    // ══════════════════════════════════════════════════════════

    public function facturaAutorizada(
        int    $empresaId,
        int    $facturaId,
        string $numeroFactura,
        float  $subtotal,
        float  $iva,
        float  $total,
        string $formaPago = 'efectivo',
    ): AsientoContable {

        $cuentaCobro = match($formaPago) {
            'efectivo'      => $this->cuentaId('cta_caja_general',     $empresaId),
            'transferencia' => $this->cuentaId('cta_bancos_locales',   $empresaId),
            'tarjeta'       => $this->cuentaId('cta_vouchers',         $empresaId),
            'credito'       => $this->cuentaId('cta_clientes_locales', $empresaId),
            default         => $this->cuentaId('cta_caja_general',     $empresaId),
        };

        $partidas = [
            ['cuenta_id' => $cuentaCobro,
             'debe'  => $total, 'haber' => 0,
             'descripcion' => "Factura {$numeroFactura}"],
            ['cuenta_id' => $this->cuentaId('cta_ventas_locales', $empresaId),
             'debe'  => 0, 'haber' => $subtotal,
             'descripcion' => "Venta — {$numeroFactura}"],
        ];
        if ($iva > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_iva_ventas', $empresaId),
                'debe'  => 0, 'haber' => $iva,
                'descripcion' => "IVA 15% — {$numeroFactura}",
            ];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Venta factura {$numeroFactura}",
            partidas: $partidas, documentoTipo: 'FAC',
            documentoId: $facturaId, documentoRef: $numeroFactura, esAutomatico: true,
        );
    }

    public function anticipoCliente(
        int    $empresaId,
        int    $documentoId,
        string $referencia,
        float  $monto,
        string $formaPago = 'efectivo',
    ): AsientoContable {
        $cta = $formaPago === 'transferencia'
            ? $this->cuentaId('cta_bancos_locales', $empresaId)
            : $this->cuentaId('cta_caja_general',   $empresaId);

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
        );
    }

    public function cobro(
        int    $empresaId,
        int    $documentoId,
        string $referencia,
        float  $monto,
        string $formaPago = 'transferencia',
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
        );
    }

    public function notaCreditoEmitida(
        int    $empresaId,
        int    $notaCreditoId,
        string $referencia,
        float  $subtotal,
        float  $iva,
    ): AsientoContable {
        $partidas = [
            ['cuenta_id' => $this->cuentaId('cta_ventas_locales', $empresaId),
             'debe' => $subtotal, 'haber' => 0,
             'descripcion' => "Devolución NC {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_clientes_locales', $empresaId),
             'debe' => 0, 'haber' => $subtotal + $iva,
             'descripcion' => "NC {$referencia}"],
        ];
        if ($iva > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_iva_ventas', $empresaId),
                'debe' => $iva, 'haber' => 0,
                'descripcion' => "IVA NC {$referencia}",
            ];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Nota de crédito {$referencia}",
            partidas: $partidas, documentoTipo: 'NC',
            documentoId: $notaCreditoId, documentoRef: $referencia, esAutomatico: true,
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

        // Cuenta IESS personal (2.1.4.03) — búsqueda directa en plan_cuentas
        $cuentaIessPersonalId = PlanCuenta::where('codigo', '2.1.4.03')->value('id');
        if (!$cuentaIessPersonalId) {
            throw new \Exception('Cuenta 2.1.4.03 (IESS Aporte Personal) no encontrada en el plan de cuentas.');
        }

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
        );
    }

    public function ajusteInventario(
        int    $empresaId,
        int    $documentoId,
        string $referencia,
        float  $monto,
        string $tipo = 'faltante',
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
        );
    }

    public function pagoProveedor(
        int  $empresaId,
        int  $documentoId,
        string $referencia,
        float  $monto,
        ?int   $centroCostoId = null,
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
        $cuentaBancoId = $banco->cuenta_contable_id;

        if (!$cuentaBancoId) {
            throw new \Exception(
                "El banco/caja '{$banco->nombre}' no tiene cuenta contable configurada. " .
                "Configure la cuenta en Bancos → Catálogo."
            );
        }

        try {
            $cuentaAnticipoId = $this->cuentaId('cta_anticipos_proveedores', $empresaId);
        } catch (\Exception) {
            $cuenta = PlanCuenta::where('empresa_id', $empresaId)
                ->where('permite_asientos', true)
                ->where('estado', true)
                ->whereIn('codigo', ['1.1.3.3', '1.1.04.04', '1.1.4.4', '1.1.4.03'])
                ->first();
            if (!$cuenta) {
                throw new \Exception(
                    "Configure el parámetro 'cta_anticipos_proveedores' en Contabilidad → Configuración."
                );
            }
            $cuentaAnticipoId = $cuenta->id;
        }

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

        $ctaProvId = ($proveedor && $proveedor->tipo === 'exterior')
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
        int    $empresaId,
        int    $conciliacionId,
        float  $diferencia,
        string $descripcion = 'Ajuste conciliación bancaria',
    ): AsientoContable {
        // Si diferencia > 0 → falta dinero en sistema (ingreso no registrado)
        // Si diferencia < 0 → sobra en sistema (egreso no registrado)
        $ctaBancos = $this->cuentaId('cta_bancos_locales', $empresaId);
        $ctaAjuste = $this->cuentaId('cta_ajuste_inventario', $empresaId);

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
        );
    }
}
