<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\ParametroContable;
use App\Models\PlanCuenta;
use Illuminate\Database\Seeder;

class ParametroContableSeeder extends Seeder
{
    // Códigos verificados contra la BD real de Altamira (formato 1.1.01.01)
    private array $mapeo = [
        'cta_caja_general'         => ['codigo_cuenta' => '1.1.01.01.08', 'descripcion' => 'Caja General (cobros en efectivo)'],
        'cta_bancos_locales'       => ['codigo_cuenta' => '1.1.01.02.01', 'descripcion' => 'Bancos Locales (cobros por transferencia)'],
        'cta_clientes_locales'     => ['codigo_cuenta' => '1.1.02.01.01', 'descripcion' => 'Clientes Locales (ventas a crédito)'],
        'cta_ventas_locales'       => ['codigo_cuenta' => '4.1.01.01',    'descripcion' => 'Venta de Mercaderías Locales'],
        'cta_iva_ventas'           => ['codigo_cuenta' => '2.1.04.01.03', 'descripcion' => 'IVA en Ventas por Pagar'],
        'cta_anticipos_clientes'   => ['codigo_cuenta' => '2.2.03.01',    'descripcion' => 'Anticipos de Clientes (reservas)'],
        'cta_proveedores_locales'  => ['codigo_cuenta' => '2.1.01.01.01', 'descripcion' => 'Proveedores Locales (CxP)'],
        'cta_iva_compras'          => ['codigo_cuenta' => '1.1.05.01.01', 'descripcion' => 'Crédito Tributario por IVA en Compras'],
        'cta_retencion_ir'         => ['codigo_cuenta' => '2.1.04.01.05', 'descripcion' => 'Retenciones en la Fuente de IR por Pagar'],
        'cta_retencion_iva'        => ['codigo_cuenta' => '2.1.04.01.02', 'descripcion' => 'Retenciones de IVA por Pagar'],
        'cta_inventario_mercaderia'=> ['codigo_cuenta' => '1.1.03.01.01', 'descripcion' => 'Inventario de Mercadería'],
        'cta_comisiones_bancarias' => ['codigo_cuenta' => '6.01.21.02.03','descripcion' => 'Comisiones Bancarias y Pasarelas (Datafast)'],
        'cta_retencion_iva_cobrada'=> ['codigo_cuenta' => '1.1.05.01.02', 'descripcion' => 'Crédito Tributario por Retenciones de IVA'],
        'cta_retencion_ir_cobrada' => ['codigo_cuenta' => '1.1.05.02.01', 'descripcion' => 'Crédito Tributario por Retenciones de IR'],
        'cta_sueldos_salarios'     => ['codigo_cuenta' => '6.01.01.01',   'descripcion' => 'Sueldos y Salarios'],
        'cta_aporte_patronal'      => ['codigo_cuenta' => '6.01.02.01',   'descripcion' => 'Aporte Patronal IESS 11.15%'],
        'cta_iess_por_pagar'       => ['codigo_cuenta' => '2.1.04.03.01', 'descripcion' => 'Obligaciones con el IESS'],
        'cta_nomina_por_pagar'     => ['codigo_cuenta' => '2.1.04.04.01', 'descripcion' => 'Nómina por Pagar'],
        'cta_anticipos_empleados'  => ['codigo_cuenta' => '1.1.04.04.03', 'descripcion' => 'Préstamos y Anticipos a Empleados'],
        'cta_gastos_no_deducibles' => ['codigo_cuenta' => '6.01.19.04',   'descripcion' => 'Gastos No Deducibles Locales'],
        // Los siguientes requieren configuración manual (no hay cuenta específica en el PGC legacy):
        // cta_vouchers, cta_costo_ventas, cta_gasto_compras, cta_ajuste_inventario
    ];

    public function run(): void
    {
        $empresas = Empresa::all();

        foreach ($empresas as $empresa) {
            $this->configurarEmpresa($empresa->id);
        }
    }

    private function configurarEmpresa(int $empresaId): void
    {
        foreach ($this->mapeo as $codigo => $data) {
            $cuenta = PlanCuenta::where('codigo', $data['codigo_cuenta'])->first();
            if (!$cuenta) {
                continue;
            }

            ParametroContable::updateOrCreate(
                ['empresa_id' => $empresaId, 'codigo' => $codigo],
                ['cuenta_id' => $cuenta->id, 'descripcion' => $data['descripcion']]
            );
        }
    }
}
