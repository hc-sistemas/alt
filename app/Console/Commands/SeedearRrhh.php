<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Colaborador;

class SeedearRrhh extends Command
{
    protected $signature   = 'altamira:seedear-rrhh';
    protected $description = 'Crea datos de prueba para el módulo RRHH (colaboradores)';

    public function handle(): void
    {
        $this->info('🌱 Seeding módulo RRHH...');

        $colaboradores = [
            [
                'cedula_ruc'          => '1712345678',
                'nombres'             => 'Carlos Andrés',
                'apellidos'           => 'Maldonado Rivera',
                'email'               => 'carlos.maldonado@altamira.com',
                'telefono'            => '0991234567',
                'direccion'           => 'Av. Amazonas N23-45, Quito',
                'cargo'               => 'Administrador',
                'departamento'        => 'Administración',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2022-01-10',
                'sueldo_base'         => 1200.00,
                'comision_porcentaje' => 0,
                'banco'               => 'Banco Pichincha',
                'tipo_cuenta'         => 'ahorros',
                'numero_cuenta'       => '2201234567',
                'decimo_tercero'      => 'acumula',
                'decimo_cuarto'       => 'acumula',
                'fondos_reserva'      => 'acumula',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1798765432',
                'nombres'             => 'María José',
                'apellidos'           => 'Vásquez Torres',
                'email'               => 'maria.vasquez@altamira.com',
                'telefono'            => '0987654321',
                'direccion'           => 'Calle Sucre 12-34, Quito',
                'cargo'               => 'Contadora',
                'departamento'        => 'Contabilidad',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2021-03-15',
                'sueldo_base'         => 1500.00,
                'comision_porcentaje' => 0,
                'banco'               => 'Banco Guayaquil',
                'tipo_cuenta'         => 'corriente',
                'numero_cuenta'       => '0981234567',
                'decimo_tercero'      => 'acumula',
                'decimo_cuarto'       => 'mensualiza',
                'fondos_reserva'      => 'acumula',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1723456789',
                'nombres'             => 'Luis Fernando',
                'apellidos'           => 'Paredes Godoy',
                'email'               => 'luis.paredes@altamira.com',
                'telefono'            => '0976543210',
                'direccion'           => 'Av. 6 de Diciembre N45-12, Quito',
                'cargo'               => 'Vendedor',
                'departamento'        => 'Ventas',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2023-06-01',
                'sueldo_base'         => 650.00,
                'comision_porcentaje' => 1.5,
                'banco'               => 'Banco Pichincha',
                'tipo_cuenta'         => 'ahorros',
                'numero_cuenta'       => '2209876543',
                'decimo_tercero'      => 'mensualiza',
                'decimo_cuarto'       => 'mensualiza',
                'fondos_reserva'      => 'mensualiza',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1734567890',
                'nombres'             => 'Ana Gabriela',
                'apellidos'           => 'Moreno Salazar',
                'email'               => 'ana.moreno@altamira.com',
                'telefono'            => '0965432109',
                'direccion'           => 'Calle Colón 78-90, Quito',
                'cargo'               => 'Vendedor',
                'departamento'        => 'Ventas',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2023-09-15',
                'sueldo_base'         => 650.00,
                'comision_porcentaje' => 1.5,
                'banco'               => 'Produbanco',
                'tipo_cuenta'         => 'ahorros',
                'numero_cuenta'       => '1234567890',
                'decimo_tercero'      => 'mensualiza',
                'decimo_cuarto'       => 'mensualiza',
                'fondos_reserva'      => 'mensualiza',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1745678901',
                'nombres'             => 'Jorge Sebastián',
                'apellidos'           => 'Villa Espinoza',
                'email'               => 'jorge.villa@altamira.com',
                'telefono'            => '0954321098',
                'direccion'           => 'Av. República 34-56, Quito',
                'cargo'               => 'Técnico',
                'departamento'        => 'Taller',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2022-08-20',
                'sueldo_base'         => 750.00,
                'comision_porcentaje' => 0,
                'banco'               => 'Banco del Pacífico',
                'tipo_cuenta'         => 'ahorros',
                'numero_cuenta'       => '5678901234',
                'decimo_tercero'      => 'acumula',
                'decimo_cuarto'       => 'acumula',
                'fondos_reserva'      => 'acumula',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1756789012',
                'nombres'             => 'Roberto Esteban',
                'apellidos'           => 'Cárdenas Vega',
                'email'               => 'roberto.cardenas@altamira.com',
                'telefono'            => '0943210987',
                'direccion'           => 'Calle Veintimilla 56-78, Quito',
                'cargo'               => 'Técnico',
                'departamento'        => 'Taller',
                'tipo_contrato'       => 'plazo_fijo',
                'fecha_ingreso'       => '2024-01-05',
                'sueldo_base'         => 700.00,
                'comision_porcentaje' => 0,
                'banco'               => 'Banco Pichincha',
                'tipo_cuenta'         => 'ahorros',
                'numero_cuenta'       => '2203456789',
                'decimo_tercero'      => 'mensualiza',
                'decimo_cuarto'       => 'mensualiza',
                'fondos_reserva'      => 'mensualiza',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1767890123',
                'nombres'             => 'Patricia Elizabet',
                'apellidos'           => 'Ruiz Andrade',
                'email'               => 'patricia.ruiz@altamira.com',
                'telefono'            => '0932109876',
                'direccion'           => 'Av. Naciones Unidas N12-34, Quito',
                'cargo'               => 'Bodeguero',
                'departamento'        => 'Bodega',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2021-11-10',
                'sueldo_base'         => 600.00,
                'comision_porcentaje' => 0,
                'banco'               => 'Banco Guayaquil',
                'tipo_cuenta'         => 'ahorros',
                'numero_cuenta'       => '0986543210',
                'decimo_tercero'      => 'acumula',
                'decimo_cuarto'       => 'acumula',
                'fondos_reserva'      => 'acumula',
                'estado'              => true,
                'empresa_id'          => 1,
            ],
            [
                'cedula_ruc'          => '1778901234',
                'nombres'             => 'Diego Mauricio',
                'apellidos'           => 'Herrera Castillo',
                'email'               => 'diego.herrera@altamira.com',
                'telefono'            => '0921098765',
                'direccion'           => 'Calle Ladrón de Guevara 23-45, Quito',
                'cargo'               => 'Vendedor',
                'departamento'        => 'Ventas',
                'tipo_contrato'       => 'indefinido',
                'fecha_ingreso'       => '2024-04-01',
                'sueldo_base'         => 650.00,
                'comision_porcentaje' => 2.0,
                'banco'               => 'Produbanco',
                'tipo_cuenta'         => 'corriente',
                'numero_cuenta'       => '9876543210',
                'decimo_tercero'      => 'mensualiza',
                'decimo_cuarto'       => 'mensualiza',
                'fondos_reserva'      => 'mensualiza',
                'estado'              => false,
                'empresa_id'          => 1,
            ],
        ];

        $creados   = 0;
        $existentes = 0;

        foreach ($colaboradores as $datos) {
            $clave = ['cedula_ruc' => $datos['cedula_ruc']];

            [$colaborador, $nuevo] = [
                Colaborador::firstOrCreate($clave, $datos),
                false,
            ];

            // firstOrCreate devuelve el modelo; wasRecentlyCreated indica si se creó ahora
            if ($colaborador->wasRecentlyCreated) {
                $creados++;
                $this->line("  <info>+</info> {$datos['apellidos']} {$datos['nombres']} ({$datos['cedula_ruc']})");
            } else {
                $existentes++;
                $this->line("  <comment>~</comment> {$datos['apellidos']} {$datos['nombres']} ya existe");
            }
        }

        $this->newLine();
        $this->info("✓ RRHH seeder completado: {$creados} colaboradores creados, {$existentes} ya existían.");
    }
}
