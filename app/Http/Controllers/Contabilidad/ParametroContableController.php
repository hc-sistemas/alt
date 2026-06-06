<?php

namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\ParametroContable;
use App\Models\PlanCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ParametroContableController extends Controller
{
    private array $parametrosDefinidos = [
        // Ventas
        ['codigo' => 'cta_caja_general',         'descripcion' => 'Caja General (cobros en efectivo)',            'grupo' => 'Ventas'],
        ['codigo' => 'cta_bancos_locales',        'descripcion' => 'Bancos Locales (cobros por transferencia)',    'grupo' => 'Ventas'],
        ['codigo' => 'cta_vouchers',              'descripcion' => 'Dinero Electrónico / Vouchers Datafast',       'grupo' => 'Ventas'],
        ['codigo' => 'cta_clientes_locales',      'descripcion' => 'Clientes Locales (ventas a crédito)',          'grupo' => 'Ventas'],
        ['codigo' => 'cta_ventas_locales',        'descripcion' => 'Venta de Mercaderías Locales',                 'grupo' => 'Ventas'],
        ['codigo' => 'cta_iva_ventas',            'descripcion' => 'IVA en Ventas por Pagar',                      'grupo' => 'Ventas'],
        ['codigo' => 'cta_anticipos_clientes',    'descripcion' => 'Anticipos de Clientes (reservas)',             'grupo' => 'Ventas'],
        ['codigo' => 'cta_costo_ventas',          'descripcion' => 'Costo de Ventas de Mercaderías',               'grupo' => 'Ventas'],
        // Compras
        ['codigo' => 'cta_proveedores_locales',   'descripcion' => 'Proveedores Locales (CxP)',                    'grupo' => 'Compras'],
        ['codigo' => 'cta_iva_compras',           'descripcion' => 'Crédito Tributario por IVA en Compras',        'grupo' => 'Compras'],
        ['codigo' => 'cta_retencion_ir',          'descripcion' => 'Retenciones en la Fuente de IR por Pagar',     'grupo' => 'Compras'],
        ['codigo' => 'cta_retencion_iva',         'descripcion' => 'Retenciones de IVA por Pagar',                 'grupo' => 'Compras'],
        ['codigo' => 'cta_gasto_compras',         'descripcion' => 'Gastos Generales (compras no inventariables)', 'grupo' => 'Compras'],
        // Inventario
        ['codigo' => 'cta_inventario_mercaderia', 'descripcion' => 'Inventario de Mercadería',                     'grupo' => 'Inventario'],
        ['codigo' => 'cta_ajuste_inventario',     'descripcion' => 'Ajustes por Faltantes o Mermas de Inventario', 'grupo' => 'Inventario'],
        // Bancos
        ['codigo' => 'cta_comisiones_bancarias',  'descripcion' => 'Comisiones Bancarias y Pasarelas (Datafast)',  'grupo' => 'Bancos'],
        ['codigo' => 'cta_retencion_iva_cobrada', 'descripcion' => 'Crédito Tributario por Retenciones de IVA',   'grupo' => 'Bancos'],
        ['codigo' => 'cta_retencion_ir_cobrada',  'descripcion' => 'Crédito Tributario por Retenciones de IR',    'grupo' => 'Bancos'],
        // Nómina
        ['codigo' => 'cta_sueldos_salarios',      'descripcion' => 'Sueldos y Salarios',                           'grupo' => 'Nómina'],
        ['codigo' => 'cta_aporte_patronal',       'descripcion' => 'Aporte Patronal IESS 11.15%',                  'grupo' => 'Nómina'],
        ['codigo' => 'cta_iess_por_pagar',        'descripcion' => 'Obligaciones con el IESS',                     'grupo' => 'Nómina'],
        ['codigo' => 'cta_nomina_por_pagar',      'descripcion' => 'Nómina por Pagar',                             'grupo' => 'Nómina'],
        ['codigo' => 'cta_anticipos_empleados',   'descripcion' => 'Préstamos y Anticipos a Empleados',            'grupo' => 'Nómina'],
        // SRI
        ['codigo' => 'cta_gastos_no_deducibles',  'descripcion' => 'Gastos No Deducibles Locales',                 'grupo' => 'SRI'],
    ];

    public function index(): Response
    {
        $empresaId  = session('empresa_activa_id');
        $parametros = ParametroContable::where('empresa_id', $empresaId)
            ->with('cuenta')
            ->get()
            ->keyBy('codigo');

        $listaCompleta = collect($this->parametrosDefinidos)->map(function ($def) use ($parametros) {
            $param = $parametros->get($def['codigo']);
            return [
                'codigo'      => $def['codigo'],
                'descripcion' => $def['descripcion'],
                'grupo'       => $def['grupo'],
                'cuenta_id'   => $param?->cuenta_id,
                'cuenta'      => $param?->cuenta
                    ? "{$param->cuenta->codigo} — {$param->cuenta->nombre}"
                    : null,
                'configurado' => $param !== null,
            ];
        });

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'tipo']);

        return Inertia::render('Contabilidad/Parametros/Index', [
            'grupos'  => $listaCompleta->groupBy('grupo'),
            'cuentas' => $cuentas,
            'stats'   => [
                'total'        => count($this->parametrosDefinidos),
                'configurados' => $listaCompleta->where('configurado', true)->count(),
                'pendientes'   => $listaCompleta->where('configurado', false)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'parametros'             => 'required|array',
            'parametros.*.codigo'    => 'required|string',
            'parametros.*.cuenta_id' => 'nullable|exists:plan_cuentas,id',
        ]);

        foreach ($request->parametros as $param) {
            if (empty($param['cuenta_id'])) {
                ParametroContable::where('empresa_id', $empresaId)
                    ->where('codigo', $param['codigo'])
                    ->delete();
                continue;
            }

            $def = collect($this->parametrosDefinidos)->firstWhere('codigo', $param['codigo']);

            ParametroContable::updateOrCreate(
                ['empresa_id' => $empresaId, 'codigo' => $param['codigo']],
                [
                    'cuenta_id'   => $param['cuenta_id'],
                    'descripcion' => $def['descripcion'] ?? $param['codigo'],
                ]
            );
        }

        return back()->with('success', 'Parámetros contables guardados correctamente.');
    }

    public function autoconfigurar(): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        // Códigos verificados contra la BD real de Altamira (formato 1.1.01.01)
        $mapeo = [
            'cta_caja_general'         => '1.1.01.01.08',
            'cta_bancos_locales'       => '1.1.01.02.01',
            'cta_clientes_locales'     => '1.1.02.01.01',
            'cta_ventas_locales'       => '4.1.01.01',
            'cta_iva_ventas'           => '2.1.04.01.03',
            'cta_anticipos_clientes'   => '2.2.03.01',
            'cta_proveedores_locales'  => '2.1.01.01.01',
            'cta_iva_compras'          => '1.1.05.01.01',
            'cta_retencion_ir'         => '2.1.04.01.05',
            'cta_retencion_iva'        => '2.1.04.01.02',
            'cta_inventario_mercaderia'=> '1.1.03.01.01',
            'cta_comisiones_bancarias' => '6.01.21.02.03',
            'cta_retencion_iva_cobrada'=> '1.1.05.01.02',
            'cta_retencion_ir_cobrada' => '1.1.05.02.01',
            'cta_sueldos_salarios'     => '6.01.01.01',
            'cta_aporte_patronal'      => '6.01.02.01',
            'cta_iess_por_pagar'       => '2.1.04.03.01',
            'cta_nomina_por_pagar'     => '2.1.04.04.01',
            'cta_anticipos_empleados'  => '1.1.04.04.03',
            'cta_gastos_no_deducibles' => '6.01.19.04',
            // cta_vouchers, cta_costo_ventas, cta_gasto_compras, cta_ajuste_inventario
            // requieren configuración manual — no hay cuenta específica en el PGC legacy
        ];

        $configurados  = 0;
        $noEncontrados = [];

        foreach ($mapeo as $codigo => $codigoCuenta) {
            $cuenta = PlanCuenta::where('codigo', $codigoCuenta)->first();
            if (!$cuenta) {
                $noEncontrados[] = $codigoCuenta;
                continue;
            }

            $def = collect($this->parametrosDefinidos)->firstWhere('codigo', $codigo);

            ParametroContable::updateOrCreate(
                ['empresa_id' => $empresaId, 'codigo' => $codigo],
                [
                    'cuenta_id'   => $cuenta->id,
                    'descripcion' => $def['descripcion'] ?? $codigo,
                ]
            );
            $configurados++;
        }

        $msg = "{$configurados} parámetros configurados automáticamente.";
        if (!empty($noEncontrados)) {
            $msg .= ' No encontradas: ' . implode(', ', $noEncontrados);
        }

        return back()->with('success', $msg);
    }
}
