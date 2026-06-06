<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\EjercicioContable;
use App\Models\AsientoContable;

class MigrarAsientosContables extends Command
{
    protected $signature   = 'altamira:migrar-asientos
                              {--preview  : Ver estructura sin migrar}
                              {--limpiar  : Limpiar tablas antes de migrar}
                              {--seedear  : Crear datos de prueba si no hay datos legacy}';
    protected $description = 'Migra erp_periodos y erp_asientos_contables al nuevo schema';

    public function handle(): void
    {
        $tienePeriodos = Schema::hasTable('erp_periodos');
        $tieneAsientos = Schema::hasTable('erp_asientos_contables');

        $this->info("📋 erp_periodos:           " . ($tienePeriodos ? "✅ existe" : "❌ no existe"));
        $this->info("📋 erp_asientos_contables: " . ($tieneAsientos ? "✅ existe" : "❌ no existe"));

        if ($this->option('preview')) {
            if ($tienePeriodos) {
                $cols  = Schema::getColumnListing('erp_periodos');
                $total = DB::table('erp_periodos')->count();
                $this->info("Columnas erp_periodos: " . implode(', ', $cols));
                $this->info("Total períodos: {$total}");
                $muestra = DB::table('erp_periodos')->limit(3)->get();
                $this->table($cols, $muestra->map(fn($r) => (array)$r)->toArray());
            }
            if ($tieneAsientos) {
                $cols  = Schema::getColumnListing('erp_asientos_contables');
                $total = DB::table('erp_asientos_contables')->count();
                $this->info("Columnas erp_asientos_contables: " . implode(', ', $cols));
                $this->info("Total asientos: {$total}");
            }
            $this->info('✅ Preview listo. Sin cambios.');
            return;
        }

        if ($this->option('limpiar')) {
            if ($this->confirm('⚠️ ¿Eliminar ejercicios y asientos actuales?', false)) {
                DB::statement('ALTER TABLE asiento_detalles DISABLE TRIGGER ALL');
                DB::statement('ALTER TABLE asientos_contables DISABLE TRIGGER ALL');
                DB::statement('ALTER TABLE ejercicios_contables DISABLE TRIGGER ALL');
                DB::table('asiento_detalles')->delete();
                DB::table('asientos_contables')->delete();
                DB::table('ejercicios_contables')->delete();
                DB::statement('ALTER TABLE asiento_detalles ENABLE TRIGGER ALL');
                DB::statement('ALTER TABLE asientos_contables ENABLE TRIGGER ALL');
                DB::statement('ALTER TABLE ejercicios_contables ENABLE TRIGGER ALL');
                $this->warn('🗑️ Tablas limpiadas.');
            }
        }

        if ($tienePeriodos)  $this->migrarPeriodos();
        if ($tieneAsientos)  $this->migrarAsientos();

        if ($this->option('seedear') || (!$tienePeriodos && !$tieneAsientos)) {
            $this->seedearDatosPrueba();
        }

        $this->info('✅ Proceso completado.');
    }

    private function migrarPeriodos(): void
    {
        $this->info('📅 Migrando períodos...');
        $cols      = Schema::getColumnListing('erp_periodos');
        $registros = DB::table('erp_periodos')->get();
        $migrados  = 0;

        $colAnio   = $this->detectar($cols, ['anio','year','periodo_anio','per_anio','p_year']);
        $colMes    = $this->detectar($cols, ['mes','month','periodo_mes','per_mes','p_month']);
        $colEstado = $this->detectar($cols, ['estado','activo','status','cerrado']);
        $colFecha  = $this->detectar($cols, ['fecha_apertura','fecha_inicio','fecha_creacion','created_at']);
        $colCierre = $this->detectar($cols, ['fecha_cierre','fecha_fin','fecha_cerrado']);
        $empId     = 1;

        foreach ($registros as $reg) {
            $arr  = (array)$reg;
            $anio = $colAnio ? (int)($arr[$colAnio] ?? date('Y')) : date('Y');
            $mes  = $colMes  ? (int)($arr[$colMes]  ?? 1)         : 1;

            if ($mes < 1 || $mes > 12) continue;

            if (EjercicioContable::where('empresa_id', $empId)
                ->where('anio', $anio)->where('mes', $mes)->exists()) continue;

            $estado = 'abierto';
            if ($colEstado) {
                $val    = strtolower((string)($arr[$colEstado] ?? ''));
                $estado = in_array($val, ['1','true','abierto','activo','open']) ? 'abierto' : 'cerrado';
            }

            EjercicioContable::create([
                'empresa_id'    => $empId,
                'anio'          => $anio,
                'mes'           => $mes,
                'descripcion'   => $this->nombreMes($mes) . " {$anio}",
                'fecha_apertura'=> $colFecha  ? ($arr[$colFecha]  ?? now()->toDateString()) : now()->toDateString(),
                'fecha_cierre'  => $colCierre ? ($arr[$colCierre] ?? null) : null,
                'estado'        => $estado,
                'created_at'    => now(),
            ]);
            $migrados++;
        }
        $this->info("   ✅ {$migrados} períodos migrados.");
    }

    private function migrarAsientos(): void
    {
        $this->info('📒 Migrando asientos...');
        $cols      = Schema::getColumnListing('erp_asientos_contables');
        $registros = DB::table('erp_asientos_contables')->orderBy('id')->limit(500)->get();
        $migrados  = 0;
        $empId     = 1;

        $colNumero   = $this->detectar($cols, ['con_asiento','numero','number','asiento','cod_asiento']);
        $colConcepto = $this->detectar($cols, ['con_concepto','concepto','descripcion','description','detalle']);
        $colFecha    = $this->detectar($cols, ['con_fecha_emision','fecha','fecha_emision','date','fecha_asiento']);
        $colDebe     = $this->detectar($cols, ['debe','total_debe','con_debe','monto_debe']);
        $colHaber    = $this->detectar($cols, ['haber','total_haber','con_haber','monto_haber']);

        $this->info("   Mapeando: numero={$colNumero} | concepto={$colConcepto} | fecha={$colFecha}");

        $ejercicio = EjercicioContable::where('empresa_id', $empId)
            ->where('estado', 'abierto')
            ->orderByDesc('anio')->orderByDesc('mes')->first();

        if (!$ejercicio) {
            $ejercicio = EjercicioContable::create([
                'empresa_id'    => $empId,
                'anio'          => date('Y'),
                'mes'           => date('n'),
                'descripcion'   => $this->nombreMes((int)date('n')) . ' ' . date('Y'),
                'fecha_apertura'=> now()->toDateString(),
                'estado'        => 'abierto',
                'created_at'    => now(),
            ]);
        }

        foreach ($registros as $reg) {
            try {
                $arr      = (array)$reg;
                $numero   = $colNumero   ? trim($arr[$colNumero]   ?? '') : '';
                $concepto = $colConcepto ? trim($arr[$colConcepto] ?? '') : 'Asiento migrado';
                $fecha    = $colFecha    ? ($arr[$colFecha] ?? now()->toDateString()) : now()->toDateString();
                $debe     = $colDebe     ? (float)($arr[$colDebe]  ?? 0) : 0;
                $haber    = $colHaber    ? (float)($arr[$colHaber] ?? 0) : 0;

                if (empty($numero)) {
                    $numero = 'AS-MIG-' . str_pad($migrados + 1, 4, '0', STR_PAD_LEFT);
                }

                if (AsientoContable::where('empresa_id', $empId)
                    ->where('numero', $numero)->exists()) continue;

                AsientoContable::create([
                    'empresa_id'     => $empId,
                    'ejercicio_id'   => $ejercicio->id,
                    'numero'         => $numero,
                    'fecha'          => $fecha,
                    'concepto'       => $concepto ?: 'Asiento migrado del sistema legacy',
                    'documento_tipo' => 'MANUAL',
                    'total_debe'     => $debe,
                    'total_haber'    => $haber,
                    'es_automatico'  => false,
                    'estado'         => 1,
                    'creado_por'     => 1,
                    'created_at'     => now(),
                ]);
                $migrados++;
            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
            }
        }
        $this->info("   ✅ {$migrados} asientos migrados.");
    }

    private function seedearDatosPrueba(): void
    {
        $this->info('🌱 Creando datos de prueba...');
        $empId = 1;

        // Crear períodos de los últimos 6 meses
        $ejercicioIds = [];
        for ($i = 5; $i >= 0; $i--) {
            $fecha = now()->subMonths($i);
            $anio  = (int)$fecha->format('Y');
            $mes   = (int)$fecha->format('n');

            $ej = EjercicioContable::where('empresa_id', $empId)
                ->where('anio', $anio)->where('mes', $mes)->first();

            if (!$ej) {
                $ej = EjercicioContable::create([
                    'empresa_id'    => $empId,
                    'anio'          => $anio,
                    'mes'           => $mes,
                    'descripcion'   => $this->nombreMes($mes) . " {$anio}",
                    'fecha_apertura'=> now()->subMonths(5 - $i)->startOfMonth()->toDateString(),
                    'fecha_cierre'  => $i < 5 ? now()->subMonths(5 - $i)->endOfMonth()->toDateString() : null,
                    'estado'        => $i < 5 ? 'cerrado' : 'abierto',
                    'created_at'    => now(),
                ]);
            }
            $ejercicioIds[] = $ej->id;
        }

        $cuentas = DB::table('plan_cuentas')
            ->where('permite_asientos', true)
            ->where('estado', true)
            ->limit(10)->pluck('id')->toArray();

        if (empty($cuentas)) {
            $this->warn('⚠️ No hay cuentas en plan_cuentas. Ejecuta primero el PlanCuentaSeeder.');
            return;
        }

        $ejercicioActivo = end($ejercicioIds);
        $demos = [
            ['Venta de mercadería — Factura 001-001-000001', 'FAC'],
            ['Pago servicios básicos',                       'MANUAL'],
            ['Compra de suministros de oficina',             'COMPRA'],
            ['Nómina mensual',                               'NOM'],
            ['Cobro factura cliente — Transferencia',        'CXC'],
            ['Pago proveedor — Factura 001-002-000045',      'COMPRA'],
            ['Venta de servicio técnico — OT-2026-0012',     'FAC'],
            ['Depreciación mensual activos fijos',           'MANUAL'],
            ['Anticipo cliente — Reserva equipo audio',      'FAC'],
            ['Ajuste inventario — conteo físico',            'INV'],
        ];

        $created = 0;
        foreach ($demos as $idx => [$concepto, $tipo]) {
            $monto = rand(500, 15000) + rand(0, 99) / 100;
            $num   = 'AS-' . now()->year . '-' . str_pad($idx + 1, 4, '0', STR_PAD_LEFT);

            if (AsientoContable::where('numero', $num)->where('empresa_id', $empId)->exists()) continue;

            $asiento = AsientoContable::create([
                'empresa_id'     => $empId,
                'ejercicio_id'   => $ejercicioActivo,
                'numero'         => $num,
                'fecha'          => now()->subDays(rand(0, 30))->toDateString(),
                'concepto'       => $concepto,
                'documento_tipo' => $tipo,
                'documento_ref'  => $tipo === 'FAC'
                    ? '001-001-' . str_pad(rand(1, 999), 6, '0', STR_PAD_LEFT)
                    : null,
                'total_debe'     => $monto,
                'total_haber'    => $monto,
                'es_automatico'  => $tipo !== 'MANUAL',
                'estado'         => 1,
                'creado_por'     => 1,
                'created_at'     => now(),
            ]);

            $cta1 = $cuentas[array_rand($cuentas)];
            $cta2 = $cuentas[array_rand($cuentas)];

            DB::table('asiento_detalles')->insert([
                ['asiento_id' => $asiento->id, 'cuenta_id' => $cta1,
                 'debe' => $monto, 'haber' => 0,   'descripcion' => $concepto],
                ['asiento_id' => $asiento->id, 'cuenta_id' => $cta2,
                 'debe' => 0,      'haber' => $monto, 'descripcion' => $concepto],
            ]);
            $created++;
        }
        $this->info("   ✅ {$created} asientos de prueba creados.");
    }

    private function detectar(array $cols, array $opciones): ?string
    {
        foreach ($opciones as $op) {
            if (in_array($op, $cols)) return $op;
        }
        return null;
    }

    private function nombreMes(int $mes): string
    {
        return [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
                5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
                9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'][$mes] ?? '';
    }
}
