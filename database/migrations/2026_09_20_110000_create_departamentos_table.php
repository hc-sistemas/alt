<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('departamentos')) {
            Schema::create('departamentos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('nombre', 100);
                $table->string('descripcion', 300)->nullable();
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('colaboradores') && !Schema::hasColumn('colaboradores', 'departamento_id')) {
            Schema::table('colaboradores', function (Blueprint $table) {
                $table->foreignId('departamento_id')->nullable()
                    ->constrained('departamentos')->nullOnDelete();
            });
        }

        // Backfill: convierte el texto libre existente en catálogo (agrupando sin
        // distinguir mayúsculas/espacios). Se mantiene colaboradores.departamento
        // como nombre desnormalizado para no romper filtros ni reportes.
        $crear = function (int $empresaId, string $nombre) {
            $nombre = trim(preg_replace('/\s+/', ' ', $nombre));
            if ($nombre === '') {
                return null;
            }
            $existente = DB::table('departamentos')
                ->where('empresa_id', $empresaId)
                ->whereRaw('lower(nombre) = ?', [mb_strtolower($nombre)])
                ->first();
            if ($existente) {
                return $existente;
            }
            $id = DB::table('departamentos')->insertGetId([
                'empresa_id' => $empresaId, 'nombre' => $nombre, 'estado' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('departamentos')->find($id);
        };

        if (Schema::hasTable('colaboradores')) {
            DB::table('colaboradores')
                ->whereNotNull('departamento')->whereNull('departamento_id')
                ->get(['id', 'empresa_id', 'departamento'])
                ->each(function ($c) use ($crear) {
                    $dep = $crear((int) $c->empresa_id, $c->departamento);
                    if ($dep) {
                        DB::table('colaboradores')->where('id', $c->id)
                            ->update(['departamento_id' => $dep->id, 'departamento' => $dep->nombre]);
                    }
                });
        }

        if (Schema::hasTable('puestos_trabajo')) {
            DB::table('puestos_trabajo')->whereNotNull('departamento')
                ->get(['empresa_id', 'departamento'])
                ->each(fn($p) => $crear((int) $p->empresa_id, $p->departamento));
        }
    }

    public function down(): void {}
};
