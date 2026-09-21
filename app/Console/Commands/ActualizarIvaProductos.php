<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ActualizarIvaProductos extends Command
{
    protected $signature = 'altamira:actualizar-iva-productos
                            {--iva=15 : Porcentaje de IVA vigente}
                            {--aplicar : Ejecuta el cambio (sin esta opción solo simula)}';

    protected $description = 'Actualiza productos.porcentaje_iva al IVA vigente (solo los que gravan IVA; los de 0% no se tocan)';

    public function handle(): int
    {
        $iva = (float) $this->option('iva');

        // Solo productos que gravan IVA y aún no están al porcentaje vigente.
        // Los de 0% (exentos / tarifa 0) se dejan intactos.
        $query = DB::table('productos')
            ->where('porcentaje_iva', '>', 0)
            ->where('porcentaje_iva', '<>', $iva);

        $resumen = (clone $query)
            ->select('porcentaje_iva', DB::raw('count(*) as total'))
            ->groupBy('porcentaje_iva')
            ->get();

        $total = $resumen->sum('total');
        $this->table(['IVA actual', 'Productos'], $resumen->map(fn ($r) => [$r->porcentaje_iva, $r->total])->all());
        $this->info("Se pasarían a {$iva}%: {$total} productos.");

        if (!$this->option('aplicar')) {
            $this->warn('Simulación: no se modificó nada. Use --aplicar para ejecutar.');
            return self::SUCCESS;
        }

        if ($total === 0) {
            return self::SUCCESS;
        }

        // Respaldo previo (id, codigo, iva anterior) para poder revertir.
        $ruta = 'backups/productos_iva_' . now()->format('Ymd_His') . '.csv';
        $csv  = "id,codigo,porcentaje_iva_anterior\n";
        foreach ((clone $query)->select('id', 'codigo', 'porcentaje_iva')->orderBy('id')->get() as $p) {
            $csv .= "{$p->id},\"{$p->codigo}\",{$p->porcentaje_iva}\n";
        }
        Storage::disk('local')->put($ruta, $csv);
        $this->info('Respaldo: storage/app/private/' . $ruta);

        $actualizados = DB::transaction(fn () => $query->update(['porcentaje_iva' => $iva]));

        $this->info("Actualizados: {$actualizados} productos a {$iva}%.");
        return self::SUCCESS;
    }
}
