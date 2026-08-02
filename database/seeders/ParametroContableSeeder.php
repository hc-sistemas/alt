<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\ParametroContable;
use App\Models\PlanCuenta;
use Illuminate\Database\Seeder;

class ParametroContableSeeder extends Seeder
{
    // Códigos verificados contra plan_cuentas de la BD real de Altamira
    // (formato corto real: 1.1.1.01, NO el formato largo 1.1.01.01.08 que
    // tenía esta lista antes — ese formato no existe en plan_cuentas, así
    // que los 20 parámetros de abajo nunca se llegaban a configurar en un
    // ambiente nuevo corrido desde cero: PlanCuenta::where('codigo', ...)
    // siempre devolvía null y el bucle en configurarEmpresa() los saltaba
    // en silencio. Reconfirmado 2026-08-01 contra la BD viva, junto con
    // cta_vouchers (la cuenta puente de Datafast — ver auditoría del mismo
    // día) y otros 3 parámetros que este archivo daba por "requieren
    // configuración manual" pero que en la BD real YA tienen una cuenta
    // asignada desde hace tiempo.
    private array $mapeo = [
        'cta_caja_general'         => ['codigo_cuenta' => '1.1.1.01', 'descripcion' => 'Caja General (cobros en efectivo)'],
        'cta_bancos_locales'       => ['codigo_cuenta' => '1.1.1.03', 'descripcion' => 'Bancos Locales (cobros por transferencia)'],
        'cta_clientes_locales'     => ['codigo_cuenta' => '1.1.3.01', 'descripcion' => 'Clientes Locales (ventas a crédito)'],
        'cta_ventas_locales'       => ['codigo_cuenta' => '4.1.1.01', 'descripcion' => 'Venta de Mercaderías — Mercado Local'],
        'cta_iva_ventas'           => ['codigo_cuenta' => '2.1.3.04', 'descripcion' => 'IVA en Ventas por Liquidar al SRI'],
        'cta_anticipos_clientes'   => ['codigo_cuenta' => '2.1.6.01', 'descripcion' => 'Anticipos de Clientes — Corto Plazo'],
        'cta_proveedores_locales'  => ['codigo_cuenta' => '2.1.1.01', 'descripcion' => 'Proveedores Locales (CxP)'],
        'cta_iva_compras'          => ['codigo_cuenta' => '1.1.5.01', 'descripcion' => 'Crédito Tributario IVA Compras'],
        'cta_retencion_ir'         => ['codigo_cuenta' => '2.1.3.01', 'descripcion' => 'Retenciones en la Fuente de IR por Pagar'],
        'cta_retencion_iva'        => ['codigo_cuenta' => '2.1.3.02', 'descripcion' => 'Retenciones de IVA por Pagar'],
        'cta_inventario_mercaderia'=> ['codigo_cuenta' => '1.1.4.01', 'descripcion' => 'Inventario de Mercaderías'],
        'cta_comisiones_bancarias' => ['codigo_cuenta' => '5.3.1.02', 'descripcion' => 'Comisiones Bancarias y Pasarelas de Pago'],
        'cta_retencion_iva_cobrada'=> ['codigo_cuenta' => '1.1.5.02', 'descripcion' => 'Crédito Tributario por Retenciones de IVA'],
        'cta_retencion_ir_cobrada' => ['codigo_cuenta' => '1.1.5.03', 'descripcion' => 'Crédito Tributario por Retenciones de IR'],
        'cta_sueldos_salarios'     => ['codigo_cuenta' => '5.2.1.01', 'descripcion' => 'Sueldos, Salarios y Horas Extras'],
        'cta_aporte_patronal'      => ['codigo_cuenta' => '5.2.1.03', 'descripcion' => 'Aporte Patronal IESS (11.15%)'],
        'cta_iess_por_pagar'       => ['codigo_cuenta' => '2.1.4.02', 'descripcion' => 'Obligaciones con el IESS — Aporte Patronal (11.15%)'],
        'cta_nomina_por_pagar'     => ['codigo_cuenta' => '2.1.4.01', 'descripcion' => 'Nómina por Pagar'],
        'cta_anticipos_empleados'  => ['codigo_cuenta' => '1.1.3.04', 'descripcion' => 'Préstamos y Anticipos a Empleados'],
        'cta_gastos_no_deducibles' => ['codigo_cuenta' => '5.4.1.01', 'descripcion' => 'Gastos No Deducibles'],
        // Antes marcados como "requieren configuración manual" — la BD real
        // ya tiene una cuenta asignada para los 4, así que dejan de ser
        // manuales para un ambiente nuevo:
        'cta_vouchers'             => ['codigo_cuenta' => '1.1.1.05', 'descripcion' => 'Cuentas Virtuales y Pasarelas de Pago (cuenta puente Datafast)'],
        'cta_costo_ventas'         => ['codigo_cuenta' => '5.1.1.01', 'descripcion' => 'Costo de Ventas de Mercaderías Locales'],
        'cta_gasto_compras'        => ['codigo_cuenta' => '5.2.2.01', 'descripcion' => 'Honorarios Profesionales y Asesorías'],
        'cta_ajuste_inventario'    => ['codigo_cuenta' => '5.1.1.04', 'descripcion' => 'Ajustes por Faltantes o Mermas de Inventario'],
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
