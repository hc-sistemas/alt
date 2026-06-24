<?php

namespace App\Console\Commands;

use App\Models\Colaborador;
use App\Models\Nomina;
use App\Models\NominaDetalle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Crea 2 nóminas de prueba con los colaboradores activos:
 *   - Mayo 2026 → estado pagado
 *   - Junio 2026 → estado borrador
 *
 * Uso exclusivo en desarrollo.
 */
class SeedearNomina extends Command
{
    protected $signature   = 'altamira:seedear-nomina {--force : Omitir confirmación}';
    protected $description = 'Crea nóminas de prueba para desarrollo (mayo y junio 2026)';

    private const SBU = 460.0; // Salario Básico Unificado Ecuador 2026

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('Esto creará nóminas de prueba. ¿Continuar?')) {
                return self::SUCCESS;
            }
        }

        $empresaId    = 1;
        $usuarioId    = 1; // admin

        $colaboradores = Colaborador::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('apellidos')
            ->get();

        if ($colaboradores->isEmpty()) {
            $this->error('No hay colaboradores activos en empresa_id=1.');
            return self::FAILURE;
        }

        $this->info("Colaboradores: {$colaboradores->count()}");

        $periodos = [
            ['anio' => 2026, 'mes' => 5, 'estado' => 'pagado',   'label' => 'Mayo 2026'],
            ['anio' => 2026, 'mes' => 6, 'estado' => 'borrador', 'label' => 'Junio 2026'],
        ];

        DB::transaction(function () use ($periodos, $colaboradores, $empresaId, $usuarioId) {
            foreach ($periodos as $p) {
                // Verificar que no exista
                $existe = Nomina::where('empresa_id', $empresaId)
                    ->where('anio', $p['anio'])
                    ->where('mes', $p['mes'])
                    ->where('periodo_tipo', 'mensual')
                    ->exists();

                if ($existe) {
                    $this->warn("  Ya existe la nómina {$p['label']} — omitiendo.");
                    continue;
                }

                $nomina = Nomina::create([
                    'empresa_id'   => $empresaId,
                    'periodo_tipo' => 'mensual',
                    'anio'         => $p['anio'],
                    'mes'          => $p['mes'],
                    'quincena'     => null,
                    'fecha_emision'=> "{$p['anio']}-{$p['mes']}-30",
                    'estado'       => $p['estado'],
                    'generado_por' => $usuarioId,
                    'procesado_por'=> $p['estado'] !== 'borrador' ? $usuarioId : null,
                    'pagado_por'   => $p['estado'] === 'pagado' ? $usuarioId : null,
                    'created_at'   => now(),
                ]);

                $totalIng = 0.0;
                $totalEgr = 0.0;

                foreach ($colaboradores as $col) {
                    $sueldo   = (float)$col->sueldo_base;
                    $otros    = 0.0;

                    // Décimos mensualizados
                    if ($col->decimo_tercero === 'mensualiza') {
                        $otros += round($sueldo / 12, 2);
                    }
                    if ($col->decimo_cuarto === 'mensualiza') {
                        $otros += round(self::SBU / 12, 2);
                    }

                    $totalIngresos    = round($sueldo + $otros, 2);
                    $aportePersonal   = round($totalIngresos * 0.0945, 2);
                    $totalEgresos     = $aportePersonal;
                    $netoPagar        = round($totalIngresos - $totalEgresos, 2);

                    NominaDetalle::create([
                        'nomina_id'            => $nomina->id,
                        'colaborador_id'       => $col->id,
                        'sueldo_base'          => $sueldo,
                        'horas_extras_50'      => 0,
                        'horas_extras_100'     => 0,
                        'comisiones'           => 0,
                        'otros_ingresos'       => $otros,
                        'total_ingresos'       => $totalIngresos,
                        'aporte_personal_iess' => $aportePersonal,
                        'descuento_atrasos'    => 0,
                        'descuento_prestamos'  => 0,
                        'descuento_anticipos'  => 0,
                        'otros_egresos'        => 0,
                        'total_egresos'        => $totalEgresos,
                        'neto_pagar'           => $netoPagar,
                        'tipo_pago'            => $col->numero_cuenta ? 'transferencia' : null,
                        'num_cuenta'           => $col->numero_cuenta,
                        'banco'                => $col->banco,
                        'estado'               => $p['estado'],
                        'modificado_manualmente' => false,
                        'created_at'           => now(),
                    ]);

                    $totalIng += $totalIngresos;
                    $totalEgr += $totalEgresos;
                }

                $nomina->update([
                    'total_ingresos' => round($totalIng, 2),
                    'total_egresos'  => round($totalEgr, 2),
                    'total_neto'     => round($totalIng - $totalEgr, 2),
                ]);

                $this->info("  ✓ {$p['label']} — {$colaboradores->count()} empleados — neto: $" . number_format($totalIng - $totalEgr, 2));
            }
        });

        $this->info('Seeder de nómina completado.');
        return self::SUCCESS;
    }
}
