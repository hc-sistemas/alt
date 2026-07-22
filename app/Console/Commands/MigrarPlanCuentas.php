<?php

namespace App\Console\Commands;

use App\Models\PlanCuenta;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrarPlanCuentas extends Command
{
    protected $signature = 'altamira:migrar-plan-cuentas
                            {--preview  : Muestra registros de origen sin migrar}
                            {--limpiar  : Limpia plan_cuentas antes de migrar}';

    protected $description = 'Migra erp_plan_cuentas → plan_cuentas (backup 30052026)';

    public function handle(): void
    {
        if (!Schema::hasTable('erp_plan_cuentas')) {
            $this->error('❌ Tabla erp_plan_cuentas no encontrada en la BD altamira.');
            $this->newLine();
            $this->line('  pg_restore -U postgres -d altamira -t erp_plan_cuentas 30052026.backup');
            return;
        }

        $columnas = Schema::getColumnListing('erp_plan_cuentas');
        $total    = DB::table('erp_plan_cuentas')->count();

        $this->info("📋 Columnas: " . implode(', ', $columnas));
        $this->info("📊 Registros: {$total}");

        if ($this->option('preview')) {
            $muestra = DB::table('erp_plan_cuentas')
                ->orderBy($this->detectar($columnas, ['pln_codigo', 'codigo', 'code', 'cod']) ?? 'id')
                ->limit(20)
                ->get();
            $this->table($columnas, $muestra->map(fn($r) => (array) $r));
            $this->info('✅ Preview completado. No se realizaron cambios.');
            return;
        }

        if ($this->option('limpiar')) {
            if (!$this->confirm('⚠️  ¿Eliminar TODAS las cuentas actuales antes de migrar?', false)) {
                $this->warn('Operación cancelada.');
                return;
            }
            DB::statement('TRUNCATE TABLE plan_cuentas RESTART IDENTITY CASCADE');
            $this->warn('🗑️  Plan de cuentas actual eliminado.');
        }

        // Detección de columnas — incluye convención legacy pln_*
        $colCodigo   = $this->detectar($columnas, ['pln_codigo', 'codigo', 'code', 'cuenta', 'cod', 'numero']);
        $colNombre   = $this->detectar($columnas, ['pln_descripcion', 'nombre', 'name', 'descripcion', 'description', 'detalle']);
        $colDesc     = $this->detectar($columnas, ['pln_obs', 'descripcion', 'description', 'observacion', 'detalle']);
        $colEstado   = $this->detectar($columnas, ['pln_estado', 'estado', 'activo', 'active', 'status', 'habilitado']);
        $colAsientos = $this->detectar($columnas, ['permite_asientos', 'acepta_movimientos', 'movimientos', 'hoja']);
        $colGrupo    = $this->detectar($columnas, ['pln_grupo', 'grupo', 'tipo', 'clase', 'category']);

        if (!$colCodigo || !$colNombre) {
            $this->error('No se pudieron detectar columnas codigo/nombre. Columnas disponibles: ' . implode(', ', $columnas));
            return;
        }

        $this->newLine();
        $this->info("🔍 Columnas detectadas:");
        $this->line("   código  → {$colCodigo}");
        $this->line("   nombre  → {$colNombre}");
        $this->line("   obs     → " . ($colDesc     ?? '(ninguna)'));
        $this->line("   estado  → " . ($colEstado   ?? '(ninguna — default: activa)'));
        $this->line("   asient. → " . ($colAsientos ?? '(ninguna — inferido del código)'));
        $this->line("   grupo   → " . ($colGrupo    ?? '(ninguna)'));
        $this->newLine();

        // Cargar todos los registros ordenados por código (más cortos primero para respetar jerarquía)
        $registros = DB::table('erp_plan_cuentas')
            ->orderByRaw("LENGTH({$colCodigo}), {$colCodigo}")
            ->get();

        $migrados = 0;
        $omitidos = 0;
        $errores  = 0;
        $mapaIds  = []; // codigo_normalizado → id en plan_cuentas

        $bar = $this->output->createProgressBar(count($registros));
        $bar->start();

        // ── Paso 1: insertar todas las cuentas sin padre_id ─────────────────
        foreach ($registros as $reg) {
            try {
                $arr     = (array) $reg;
                $codigoRaw = trim((string) ($arr[$colCodigo] ?? ''));
                $nombre    = trim((string) ($arr[$colNombre] ?? 'Sin nombre'));

                if ($codigoRaw === '') {
                    $omitidos++;
                    $bar->advance();
                    continue;
                }

                // Los códigos con punto final (ej: "1.1.01.02.") son cuentas grupo
                $tieneTrailingDot = str_ends_with($codigoRaw, '.');
                $codigo           = rtrim($codigoRaw, '.'); // normalizar: quitar punto final

                if ($codigo === '') {
                    $omitidos++;
                    $bar->advance();
                    continue;
                }

                $nivel = $this->calcularNivel($codigo);

                // pln_estado = 0 en el legacy significa "activa" (convención invertida)
                $estadoRaw = $colEstado ? ($arr[$colEstado] ?? null) : null;
                $estado    = $estadoRaw === null ? true : ($estadoRaw == 0);

                // permite_asientos:
                // - Si el código original tenía punto final → es cuenta grupo → false
                // - Si hay columna explícita → usarla
                // - Fallback: nivel >= 4 suele ser cuenta hoja
                if ($tieneTrailingDot) {
                    $permiteAsientos = false;
                } elseif ($colAsientos) {
                    $permiteAsientos = (bool) ($arr[$colAsientos] ?? true);
                } else {
                    $permiteAsientos = true; // se ajustará en paso 3
                }

                $cuenta = PlanCuenta::firstOrCreate(
                    ['codigo' => $codigo],
                    [
                        'nombre'           => $nombre ?: $codigo,
                        'descripcion'      => $colDesc ? (trim((string) ($arr[$colDesc] ?? '')) ?: null) : null,
                        'tipo'             => $this->inferirTipo($codigo),
                        'nivel'            => $nivel,
                        'padre_id'         => null,
                        'permite_asientos' => $permiteAsientos,
                        'estado'           => $estado,
                        'total_asientos'   => 0,
                    ]
                );

                $mapaIds[$codigo] = $cuenta->id;
                $migrados++;
            } catch (\Exception $e) {
                $errores++;
                $this->newLine();
                $this->error('Error en "' . ($codigoRaw ?? '?') . '": ' . $e->getMessage());
            }

            $bar->advance();
        }

        // ── Paso 2: asignar padre_id usando códigos normalizados ─────────────
        // Enriquecer mapaIds con TODOS los registros de plan_cuentas (incluye los del seeder)
        // para que cuentas como "1.1" del seeder sirvan como padres de legacy accounts.
        PlanCuenta::select('id', 'codigo')->each(function ($c) use (&$mapaIds) {
            $mapaIds[$c->codigo] = $c->id;
        });

        foreach ($mapaIds as $codigo => $id) {
            $codigoPadre = $this->obtenerCodigoPadre($codigo);
            if ($codigoPadre && isset($mapaIds[$codigoPadre])) {
                PlanCuenta::where('id', $id)
                    ->whereNull('padre_id')
                    ->update(['padre_id' => $mapaIds[$codigoPadre]]);
            }
        }

        // ── Paso 3: desactivar permite_asientos en cuentas con hijos ─────────
        // (las que tienen hijos no deberían aceptar asientos directos)
        $conHijos = PlanCuenta::has('hijos')->pluck('id');
        if ($conHijos->isNotEmpty()) {
            PlanCuenta::whereIn('id', $conHijos)
                ->where('permite_asientos', true)
                ->update(['permite_asientos' => false]);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('✅ Migración completada:');
        $this->line("   • Migrados:  {$migrados}");
        $this->line("   • Omitidos:  {$omitidos}");
        $this->line("   • Errores:   {$errores}");
        $this->line("   • Con padre: " . count(array_filter(
            array_keys($mapaIds),
            fn($c) => $this->obtenerCodigoPadre($c) !== null && isset($mapaIds[$this->obtenerCodigoPadre($c)])
        )));
    }

    private function detectar(array $columnas, array $candidatos): ?string
    {
        foreach ($candidatos as $c) {
            if (in_array($c, $columnas, true)) {
                return $c;
            }
        }
        return null;
    }

    private function inferirTipo(string $codigo): string
    {
        return match (substr(trim($codigo), 0, 1)) {
            '1' => 'activo',
            '2' => 'pasivo',
            '3' => 'patrimonio',
            '4' => 'ingreso',
            '5' => 'gasto',
            '6' => 'gasto', // Costos → gasto en este sistema
            default => 'activo',
        };
    }

    private function calcularNivel(string $codigo): int
    {
        // Código ya normalizado (sin trailing dot)
        return count(explode('.', $codigo));
    }

    private function obtenerCodigoPadre(string $codigo): ?string
    {
        $partes = explode('.', $codigo);
        if (count($partes) <= 1) {
            return null;
        }
        array_pop($partes);
        return implode('.', $partes);
    }
}
