<?php
namespace App\Services;

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
    private function cuentaId(string $codigo, int $empresaId): int
    {
        $id = ParametroContable::getCuentaId($codigo, $empresaId);
        if (!$id) {
            throw new \Exception(
                "Parámetro contable '{$codigo}' no configurado. " .
                "Configure los parámetros en Contabilidad → Configuración."
            );
        }
        return $id;
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
        float  $retencionIR  = 0,
        float  $retencionIVA = 0,
        string $tipo = 'inventario',
    ): AsientoContable {
        $ctaCompra  = $tipo === 'inventario'
            ? $this->cuentaId('cta_inventario_mercaderia', $empresaId)
            : $this->cuentaId('cta_gasto_compras',         $empresaId);
        $neto = $subtotal + $iva - $retencionIR - $retencionIVA;

        $partidas = [
            ['cuenta_id' => $ctaCompra,
             'debe' => $subtotal, 'haber' => 0,
             'descripcion' => "Compra {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_iva_compras', $empresaId),
             'debe' => $iva, 'haber' => 0,
             'descripcion' => "IVA compra {$referencia}"],
            ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
             'debe' => 0, 'haber' => $neto,
             'descripcion' => "CxP {$referencia}"],
        ];
        if ($retencionIR > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_retencion_ir', $empresaId),
                'debe' => 0, 'haber' => $retencionIR,
                'descripcion' => "Ret. IR {$referencia}",
            ];
        }
        if ($retencionIVA > 0) {
            $partidas[] = [
                'cuenta_id'   => $this->cuentaId('cta_retencion_iva', $empresaId),
                'debe' => 0, 'haber' => $retencionIVA,
                'descripcion' => "Ret. IVA {$referencia}",
            ];
        }

        return $this->crear(
            empresaId: $empresaId, concepto: "Compra {$referencia}",
            partidas: $partidas, documentoTipo: 'COMPRA',
            documentoId: $compraId, documentoRef: $referencia, esAutomatico: true,
        );
    }

    public function pagoProveedor(
        int    $empresaId,
        int    $documentoId,
        string $referencia,
        float  $monto,
    ): AsientoContable {
        return $this->crear(
            empresaId: $empresaId, concepto: "Pago proveedor {$referencia}",
            partidas: [
                ['cuenta_id' => $this->cuentaId('cta_proveedores_locales', $empresaId),
                 'debe' => $monto, 'haber' => 0,
                 'descripcion' => "Pago {$referencia}"],
                ['cuenta_id' => $this->cuentaId('cta_bancos_locales', $empresaId),
                 'debe' => 0, 'haber' => $monto,
                 'descripcion' => "Transferencia {$referencia}"],
            ],
            documentoTipo: 'BANCO', documentoId: $documentoId,
            documentoRef: $referencia, esAutomatico: true,
        );
    }
}
