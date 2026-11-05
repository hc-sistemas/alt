<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Revisión previa al despliegue: SOLO LEE, no escribe nada. Se corre ANTES de `php artisan migrate` (y de nuevo
 * después) para saber en qué estado está la base y si alguna migración se va a detener. Ver
 * docs/DESPLIEGUE_PRODUCCION.md.
 *
 * Termina con código de salida 1 si hay algo que BLOQUEA (no migrar hasta resolverlo) y 0 si se puede seguir.
 */
class PreflightDespliegue extends Command
{
    protected $signature = 'altamira:preflight-despliegue';
    protected $description = 'Revisa (solo lectura) si la base está lista para migrar; indica qué bloquea y qué se resolverá solo';

    private int $bloqueos = 0;
    private int $avisos = 0;

    public function handle(Migrator $migrator): int
    {
        $this->line('');
        $this->info('=== 1. A qué base estás conectado ===');
        $cx = DB::connection();
        $bd = $cx->getDatabaseName();
        $this->line('  Base: ' . $bd . ' | servidor: ' . config('database.connections.' . config('database.default') . '.host')
            . ' | entorno de la app: ' . config('app.env'));
        if ($bd !== 'altamira') {
            $this->aviso("La base no se llama «altamira» (es «{$bd}»). Si esto es producción, DETENTE: solo se toca la base altamira.");
        } else {
            $this->ok('La base es «altamira».');
        }

        $this->line('');
        $this->info('=== 2. Migraciones pendientes ===');
        $ejecutadas = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
        $archivos = array_keys($migrator->getMigrationFiles($this->laravel->databasePath('migrations')));
        $pendientes = array_values(array_diff($archivos, $ejecutadas));
        sort($pendientes);
        $this->line('  Ya ejecutadas: ' . count($ejecutadas) . ' | pendientes: ' . count($pendientes));
        foreach ($pendientes as $p) {
            $this->line('    - ' . $p);
        }
        if (!$pendientes) {
            $this->ok('No hay migraciones pendientes.');
        }
        $desconocidas = array_diff($ejecutadas, $archivos);
        if ($desconocidas) {
            $this->aviso('La base tiene migraciones que NO están en el código: ' . implode(', ', array_slice($desconocidas, 0, 5))
                . '. Significa que el código desplegado es más viejo que la base. Revisa que hiciste git pull de la rama correcta.');
        }

        $this->line('');
        $this->info('=== 3. Qué datos reales hay (para saber el riesgo) ===');
        foreach (['asientos_contables', 'asiento_detalles', 'facturas', 'compras', 'nominas', 'movimientos_bancarios', 'productos', 'clientes'] as $t) {
            if (Schema::hasTable($t)) {
                $this->line(sprintf('  %-24s %s', $t, number_format(DB::table($t)->count())));
            }
        }

        $this->line('');
        $this->info('=== 4. Plan de cuentas: ¿la migración del plan V4 se detendría? ===');
        if (in_array('2026_10_24_130000_cargar_plan_cuentas_final', $pendientes, true)) {
            $this->revisarPlan();
        } else {
            $this->ok('La migración del plan de cuentas V4 ya está ejecutada (o no aplica).');
        }

        $this->line('');
        $this->info('=== 5. Producción: productos y IVA ===');
        if (Schema::hasTable('productos')) {
            $iva = DB::table('productos')->select('porcentaje_iva', DB::raw('count(*) n'))->groupBy('porcentaje_iva')->orderBy('porcentaje_iva')->pluck('n', 'porcentaje_iva')->all();
            $this->line('  Productos por IVA: ' . json_encode($iva));
            $this->line('  (los que estén en 12 se pasan a 15 con altamira:actualizar-iva-productos, después de migrar)');
        }

        $this->line('');
        if ($this->bloqueos) {
            $this->error("RESULTADO: {$this->bloqueos} BLOQUEO(S). NO ejecutes migrate hasta resolverlos.");

            return self::FAILURE;
        }
        $this->info('RESULTADO: sin bloqueos' . ($this->avisos ? " ({$this->avisos} aviso(s): léelos arriba antes de seguir)" : '') . '. Esta revisión no modificó nada.');

        return self::SUCCESS;
    }

    /** Misma regla que la migración del plan: cuentas que cambian de significado y ya tienen movimientos. */
    private function revisarPlan(): void
    {
        $plan = require database_path('data/plan_cuentas_final.php');
        $finales = array_column($plan, null, 0);
        $existentes = DB::table('plan_cuentas')->get()->keyBy('codigo');
        $norm = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
        $resueltas = [];
        $bloqueadas = [];

        foreach ($finales as $codigo => [, $nombre, , $permite]) {
            $fila = $existentes[$codigo] ?? null;
            if (!$fila) {
                continue;
            }
            $cambiaNombre = $norm($fila->nombre) !== $norm($nombre);
            $dejaDePermitir = (bool) $fila->permite_asientos && !$permite;
            if (!$cambiaNombre && !$dejaDePermitir) {
                continue;
            }
            $detalle = $this->movimientos((int) $fila->id);
            if (!array_sum($detalle)) {
                continue;
            }
            $hijas = [];
            foreach ($finales as $c => [, , , $p]) {
                $partes = explode('.', $c);
                if ($p && count($partes) > 1 && implode('.', array_slice($partes, 0, -1)) === $codigo) {
                    $hijas[] = $c;
                }
            }
            $soloAsientos = $detalle['compra_detalles'] + $detalle['bancos_cajas'] + $detalle['movimientos_bancarios'] === 0;
            if (!$cambiaNombre && $dejaDePermitir && count($hijas) === 1 && $soloAsientos) {
                $resueltas[] = "{$codigo} «{$fila->nombre}»: {$detalle['asiento_detalles']} línea(s) de asiento pasarán a {$hijas[0]} (automático)";
            } else {
                $bloqueadas[] = "{$codigo} «{$fila->nombre}»: " . json_encode($detalle) . ($cambiaNombre ? ' — cambia de nombre' : ' — deja de aceptar asientos');
            }
        }

        foreach ($resueltas as $r) {
            $this->aviso('Se resolverá solo: ' . $r);
        }
        foreach ($bloqueadas as $b) {
            $this->bloqueo('La migración del plan se DETENDRÁ por: ' . $b);
        }
        if (!$resueltas && !$bloqueadas) {
            $this->ok('Ninguna cuenta con movimientos cambia de significado: la migración del plan debe pasar.');
        }
    }

    /** @return array<string,int> */
    private function movimientos(int $cuentaId): array
    {
        $refs = ['asiento_detalles' => 'cuenta_id', 'compra_detalles' => 'cuenta_id', 'bancos_cajas' => 'cuenta_id', 'movimientos_bancarios' => 'cuenta_contrapartida_id'];
        $r = [];
        foreach ($refs as $t => $c) {
            $r[$t] = Schema::hasTable($t) && Schema::hasColumn($t, $c) ? DB::table($t)->where($c, $cuentaId)->count() : 0;
        }

        return $r;
    }

    private function ok(string $m): void
    {
        $this->line('  <fg=green>OK</>     ' . $m);
    }

    private function aviso(string $m): void
    {
        $this->avisos++;
        $this->line('  <fg=yellow>AVISO</>  ' . $m);
    }

    private function bloqueo(string $m): void
    {
        $this->bloqueos++;
        $this->line('  <fg=red>BLOQUEO</> ' . $m);
    }
}
