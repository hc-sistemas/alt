<?php

namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\ParametroContable;
use App\Models\PlanCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ParametroContableController extends Controller
{
    private array $parametrosDefinidos = [
        // ── Ventas ────────────────────────────────────────────────────────────
        ['codigo' => 'cta_caja_general',             'descripcion' => 'Caja General (cobros en efectivo)',               'grupo' => 'Ventas'],
        ['codigo' => 'cta_bancos_locales',            'descripcion' => 'Bancos Locales (cobros por transferencia)',       'grupo' => 'Ventas'],
        ['codigo' => 'cta_vouchers',                  'descripcion' => 'Dinero Electrónico / Vouchers Datafast',          'grupo' => 'Ventas'],
        ['codigo' => 'cta_clientes_locales',          'descripcion' => 'Clientes Locales (ventas a crédito)',             'grupo' => 'Ventas'],
        ['codigo' => 'cta_clientes_exterior',         'descripcion' => 'Clientes del Exterior',                          'grupo' => 'Ventas'],
        ['codigo' => 'cta_ventas_locales',            'descripcion' => 'Venta de Mercaderías Locales',                   'grupo' => 'Ventas'],
        ['codigo' => 'cta_devoluciones_ventas',       'descripcion' => '(-) Devoluciones en Ventas',                     'grupo' => 'Ventas'],
        ['codigo' => 'cta_iva_ventas',                'descripcion' => 'IVA en Ventas por Pagar',                        'grupo' => 'Ventas'],
        ['codigo' => 'cta_anticipos_clientes',        'descripcion' => 'Anticipos de Clientes (reservas)',                'grupo' => 'Ventas'],
        ['codigo' => 'cta_costo_ventas',              'descripcion' => 'Costo de Ventas de Mercaderías Locales',          'grupo' => 'Ventas'],
        // ── Compras ───────────────────────────────────────────────────────────
        ['codigo' => 'cta_proveedores_locales',       'descripcion' => 'Proveedores Locales (CxP)',                      'grupo' => 'Compras'],
        ['codigo' => 'cta_proveedores_exterior',      'descripcion' => 'Proveedores del Exterior (importaciones)',        'grupo' => 'Compras'],
        ['codigo' => 'cta_iva_compras',               'descripcion' => 'Crédito Tributario por IVA en Compras',          'grupo' => 'Compras'],
        ['codigo' => 'cta_retencion_ir',              'descripcion' => 'Retenciones en la Fuente de IR por Pagar',       'grupo' => 'Compras'],
        ['codigo' => 'cta_retencion_iva',             'descripcion' => 'Retenciones de IVA por Pagar',                   'grupo' => 'Compras'],
        // 'cta_gasto_compras' se quitó de esta lista: salía en la UI y el
        // contador lo configuraba creyendo que servía, pero ningún punto del
        // sistema lo lee jamás — el código que realmente usa AsientoService es
        // 'cta_gasto_compras_default' (grupo "Gastos Operativos").
        ['codigo' => 'cta_anticipos_proveedores',     'descripcion' => 'Anticipos a Proveedores (locales/internacionales)', 'grupo' => 'Compras'],
        // ── Inventario ────────────────────────────────────────────────────────
        ['codigo' => 'cta_inventario_mercaderia',     'descripcion' => 'Inventario de Mercaderías',                      'grupo' => 'Inventario'],
        ['codigo' => 'cta_inventario_transito',       'descripcion' => 'Inventario en Tránsito — Importaciones en Curso','grupo' => 'Inventario'],
        ['codigo' => 'cta_costo_ventas_importadas',   'descripcion' => 'Costo de Ventas de Mercaderías Importadas',      'grupo' => 'Inventario'],
        ['codigo' => 'cta_ajuste_inventario',         'descripcion' => 'Ajustes por Faltantes o Mermas de Inventario',   'grupo' => 'Inventario'],
        // ── Bancos ────────────────────────────────────────────────────────────
        ['codigo' => 'cta_bancos_exterior',           'descripcion' => 'Bancos del Exterior',                            'grupo' => 'Bancos'],
        // Faltaban en la UI aunque el sistema sí los usa: 'cta_cajas_chicas' lo
        // escribía autoconfigurar() sin que nadie pudiera verlo ni corregirlo, y
        // 'cta_ajuste_conciliacion' lo necesita AsientoService::ajusteConciliacion()
        // pero no estaba ni en esta lista ni en el autoconfigurador, así que
        // dependía de un código fijo en el código fuente y era imposible de
        // configurar desde la aplicación.
        ['codigo' => 'cta_cajas_chicas',              'descripcion' => 'Cajas Chicas y Fondos Fijos',                    'grupo' => 'Bancos'],
        ['codigo' => 'cta_ajuste_conciliacion',       'descripcion' => 'Ajustes por Diferencias en Conciliación Bancaria','grupo' => 'Bancos'],
        ['codigo' => 'cta_comisiones_bancarias',      'descripcion' => 'Comisiones Bancarias y Pasarelas (Datafast)',    'grupo' => 'Bancos'],
        ['codigo' => 'cta_retencion_iva_cobrada',     'descripcion' => 'Crédito Tributario por Retenciones de IVA',     'grupo' => 'Bancos'],
        ['codigo' => 'cta_retencion_ir_cobrada',      'descripcion' => 'Crédito Tributario por Retenciones de IR',      'grupo' => 'Bancos'],
        // ── Nómina ────────────────────────────────────────────────────────────
        ['codigo' => 'cta_sueldos_salarios',          'descripcion' => 'Sueldos, Salarios y Horas Extras',               'grupo' => 'Nómina'],
        ['codigo' => 'cta_aporte_patronal',           'descripcion' => 'Aporte Patronal IESS 11.15%',                   'grupo' => 'Nómina'],
        ['codigo' => 'cta_iess_por_pagar',            'descripcion' => 'Obligaciones con el IESS — Aporte Patronal',    'grupo' => 'Nómina'],
        // Lo usa AsientoService::nomina() en cada rol de pago. Antes se buscaba
        // con un código fijo en el código fuente ('2.1.4.03', que ni siquiera
        // existe en el plan real) y no se podía configurar.
        ['codigo' => 'cta_iess_personal_por_pagar',   'descripcion' => 'Obligaciones con el IESS — Aporte Personal 9.45%','grupo' => 'Nómina'],
        ['codigo' => 'cta_nomina_por_pagar',          'descripcion' => 'Nómina por Pagar',                              'grupo' => 'Nómina'],
        ['codigo' => 'cta_anticipos_empleados',       'descripcion' => 'Préstamos y Anticipos a Empleados',             'grupo' => 'Nómina'],
        ['codigo' => 'cta_decimo_tercero',            'descripcion' => 'Décimo Tercer Sueldo',                          'grupo' => 'Nómina'],
        ['codigo' => 'cta_decimo_cuarto',             'descripcion' => 'Décimo Cuarto Sueldo',                          'grupo' => 'Nómina'],
        ['codigo' => 'cta_vacaciones',                'descripcion' => 'Vacaciones',                                    'grupo' => 'Nómina'],
        ['codigo' => 'cta_fondos_reserva',            'descripcion' => 'Fondos de Reserva',                             'grupo' => 'Nómina'],
        // ── SRI ───────────────────────────────────────────────────────────────
        ['codigo' => 'cta_gastos_no_deducibles',      'descripcion' => 'Gastos No Deducibles Locales',                  'grupo' => 'SRI'],
        // ── Contabilidad ──────────────────────────────────────────────────────
        ['codigo' => 'cta_ganancias_acumuladas',      'descripcion' => 'Ganancias Acumuladas (ejercicios anteriores)',   'grupo' => 'Contabilidad'],
        ['codigo' => 'cta_perdidas_acumuladas',       'descripcion' => '(-) Pérdidas Acumuladas (ejercicios anteriores)','grupo' => 'Contabilidad'],
        ['codigo' => 'cta_utilidad_periodo',          'descripcion' => 'Utilidad del Periodo',                          'grupo' => 'Contabilidad'],
        ['codigo' => 'cta_perdida_periodo',           'descripcion' => '(-) Pérdida del Periodo',                       'grupo' => 'Contabilidad'],
        // ── Gastos Operativos ─────────────────────────────────────────────────
        ['codigo' => 'cta_gasto_compras_default',     'descripcion' => 'Gasto Genérico (Suministros — fallback compras sin cuenta)','grupo' => 'Gastos Operativos'],
        ['codigo' => 'cta_gasto_servicios',           'descripcion' => 'Honorarios y Servicios Profesionales',           'grupo' => 'Gastos Operativos'],
        ['codigo' => 'cta_gasto_arrendamiento',       'descripcion' => 'Arrendamientos de Locales',                     'grupo' => 'Gastos Operativos'],
        ['codigo' => 'cta_gasto_servicios_basicos',   'descripcion' => 'Servicios Básicos (Agua, Luz, Internet)',        'grupo' => 'Gastos Operativos'],
        ['codigo' => 'cta_gasto_publicidad',          'descripcion' => 'Publicidad y Marketing',                        'grupo' => 'Gastos Operativos'],
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

        // Tipo de cuenta que corresponde a cada parámetro. No se validaba nada:
        // se podía asignar una cuenta de ingreso a "Bancos Locales" y el error
        // recién aparecía —como un asiento absurdo— meses después.
        $tipoEsperado = [
            'activo'     => [
                'cta_caja_general', 'cta_cajas_chicas', 'cta_bancos_locales', 'cta_bancos_exterior',
                'cta_vouchers', 'cta_clientes_locales', 'cta_clientes_exterior',
                'cta_anticipos_proveedores', 'cta_anticipos_empleados',
                'cta_inventario_mercaderia', 'cta_inventario_transito',
                'cta_iva_compras', 'cta_retencion_iva_cobrada', 'cta_retencion_ir_cobrada',
            ],
            'pasivo'     => [
                'cta_proveedores_locales', 'cta_proveedores_exterior', 'cta_anticipos_clientes',
                'cta_retencion_ir', 'cta_retencion_iva', 'cta_iva_ventas',
                'cta_nomina_por_pagar', 'cta_iess_por_pagar', 'cta_iess_personal_por_pagar',
            ],
            'patrimonio' => [
                'cta_ganancias_acumuladas', 'cta_perdidas_acumuladas',
                'cta_utilidad_periodo', 'cta_perdida_periodo',
            ],
            'ingreso'    => ['cta_ventas_locales', 'cta_devoluciones_ventas'],
            'gasto'      => [
                'cta_costo_ventas', 'cta_costo_ventas_importadas', 'cta_ajuste_inventario',
                'cta_sueldos_salarios', 'cta_aporte_patronal', 'cta_decimo_tercero',
                'cta_decimo_cuarto', 'cta_vacaciones', 'cta_fondos_reserva',
                'cta_comisiones_bancarias', 'cta_gastos_no_deducibles', 'cta_ajuste_conciliacion',
                'cta_gasto_compras_default', 'cta_gasto_servicios', 'cta_gasto_arrendamiento',
                'cta_gasto_servicios_basicos', 'cta_gasto_publicidad',
            ],
        ];
        $esperadoPorCodigo = [];
        foreach ($tipoEsperado as $tipo => $codigos) {
            foreach ($codigos as $cod) $esperadoPorCodigo[$cod] = $tipo;
        }

        $cuentas   = PlanCuenta::whereIn('id', collect($request->parametros)->pluck('cuenta_id')->filter())
                        ->get()->keyBy('id');
        $errores   = [];
        $guardados = 0;
        $borrados  = 0;

        foreach ($request->parametros as $param) {
            if (empty($param['cuenta_id'])) {
                // Dejar el campo vacío BORRA el parámetro. Se mantiene el
                // comportamiento (es la única forma de desasignar), pero ahora
                // se informa en el mensaje en vez de perder configuración en
                // silencio.
                $borrados += ParametroContable::where('empresa_id', $empresaId)
                    ->where('codigo', $param['codigo'])
                    ->delete();
                continue;
            }

            $cuenta = $cuentas->get($param['cuenta_id']);

            if (!$cuenta || !$cuenta->permite_asientos || !$cuenta->estado) {
                $errores[] = "{$param['codigo']}: la cuenta seleccionada no acepta asientos o está inactiva.";
                continue;
            }

            $esperado = $esperadoPorCodigo[$param['codigo']] ?? null;
            if ($esperado && $cuenta->tipo !== $esperado) {
                $errores[] = "{$param['codigo']}: se esperaba una cuenta de tipo " . ucfirst($esperado)
                    . " y {$cuenta->codigo} es de tipo " . ucfirst($cuenta->tipo) . '.';
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
            $guardados++;
        }

        if ($errores) {
            return back()->with('error',
                "Se guardaron {$guardados} parámetro(s). No se guardaron: " . implode(' ', $errores));
        }

        $msg = "{$guardados} parámetro(s) guardados correctamente.";
        if ($borrados > 0) {
            $msg .= " {$borrados} parámetro(s) quedaron sin cuenta asignada.";
        }

        return back()->with('success', $msg);
    }


    /**
     * Configura de golpe todos los parámetros que tienen una cuenta por
     * defecto conocida.
     *
     * El mapa de códigos ya no vive aquí: se lee de AsientoService, que es
     * quien de verdad resuelve las cuentas al generar los asientos. Antes eran
     * DOS copias del mismo mapa que se habían desincronizado — y las dos
     * estaban mal, porque apuntaban a códigos con cero a la izquierda
     * ('1.1.1.01') que no existen en el plan real del cliente ('1.1.1.1'): 26
     * de 42 cuentas no se encontraban y el autoconfigurador las reportaba
     * como "No encontradas" sin que nadie entendiera por qué.
     */
    public function autoconfigurar(): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        // Solo se autoconfiguran los parámetros que la UI muestra: escribir
        // parámetros invisibles (como pasaba con 'cta_cajas_chicas') deja
        // configuración que el contador no puede ver ni corregir.
        $visibles = collect($this->parametrosDefinidos)->pluck('codigo')->all();
        $mapa     = array_intersect_key(
            \App\Services\AsientoService::planPorDefecto(),
            array_flip($visibles)
        );

        $configurados  = 0;
        $noEncontrados = [];

        DB::transaction(function () use ($empresaId, $mapa, &$configurados, &$noEncontrados) {
            foreach ($mapa as $codigo => $codigoCuenta) {
                // buscarCuentaPorCodigo() tolera el formato de los ceros a la
                // izquierda y además exige permite_asientos + estado activo:
                // antes se aceptaba cualquier cuenta, incluida una de
                // agrupación, y el error solo aparecía después, al intentar
                // generar el asiento.
                $cuenta = \App\Services\AsientoService::buscarCuentaPorCodigo($codigoCuenta);

                if (!$cuenta) {
                    $noEncontrados[] = $codigoCuenta;
                    continue;
                }

                $def = collect($this->parametrosDefinidos)->firstWhere('codigo', $codigo);

                ParametroContable::updateOrCreate(
                    ['empresa_id' => $empresaId, 'codigo' => $codigo],
                    [
                        'cuenta_id'   => $cuenta->id,
                        'descripcion' => $def['descripcion'] ?? $cuenta->nombre,
                    ]
                );
                $configurados++;
            }
        });

        $msg = "{$configurados} parámetros configurados automáticamente.";
        if (!empty($noEncontrados)) {
            $msg .= ' No encontradas en el plan de cuentas: ' . implode(', ', $noEncontrados)
                 . '. Asígnelas a mano.';
        }

        return back()->with('success', $msg);
    }
}
