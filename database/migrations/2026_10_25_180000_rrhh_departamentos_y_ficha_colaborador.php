<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo J, RRHH (02-09 §2.2 y §2.3):
 *  - ficha del colaborador: tipo de relación (dependencia / servicios) y número de afiliación al IESS;
 *  - departamentos: Contabilidad y Financiero se unen en «Administración y Finanzas» y se crean
 *    Recursos Humanos, Logística, Servicio Técnico, Eventos y Limpieza en cada empresa, todos con
 *    descripción (el rol muestra el nombre del departamento).
 * No borra colaboradores ni departamentos con personas: reasigna. Idempotente.
 */
return new class extends Migration
{
    private const NUEVOS = [
        'Recursos Humanos'  => 'Selección, contratación, nómina y bienestar del personal',
        'Logística'         => 'Bodega, despachos, importaciones y transporte',
        'Servicio Técnico'  => 'Reparación y mantenimiento de equipos (Altamira FIX)',
        'Eventos'           => 'Gestión y montaje de eventos',
        'Limpieza'          => 'Limpieza y mantenimiento de las instalaciones',
    ];

    public function up(): void
    {
        if (Schema::hasTable('colaboradores')) {
            Schema::table('colaboradores', function (Blueprint $table) {
                if (!Schema::hasColumn('colaboradores', 'tipo_relacion')) {
                    $table->string('tipo_relacion', 15)->default('dependencia'); // dependencia | servicios
                }
                if (!Schema::hasColumn('colaboradores', 'numero_iess')) {
                    $table->string('numero_iess', 20)->nullable();
                }
            });
        }

        if (!Schema::hasTable('departamentos')) {
            return;
        }

        DB::transaction(function () {
            foreach (DB::table('empresas')->pluck('id') as $empresaId) {
                $this->departamentosDeEmpresa((int) $empresaId);
            }
        });
    }

    private function departamentosDeEmpresa(int $empresaId): void
    {
        $buscar = fn (string $n) => DB::table('departamentos')->where('empresa_id', $empresaId)
            ->whereRaw('lower(nombre) = ?', [mb_strtolower($n)])->first();

        // Contabilidad + Financiero => Administración y Finanzas
        $destino = $buscar('Administración y Finanzas');
        $conta   = $buscar('Contabilidad');
        $finan   = $buscar('Financiero');

        if (!$destino) {
            if ($conta) {
                DB::table('departamentos')->where('id', $conta->id)->update(['nombre' => 'Administración y Finanzas', 'updated_at' => now()]);
                $destino = $buscar('Administración y Finanzas');
                $conta   = null;
            } elseif ($finan) {
                DB::table('departamentos')->where('id', $finan->id)->update(['nombre' => 'Administración y Finanzas', 'updated_at' => now()]);
                $destino = $buscar('Administración y Finanzas');
                $finan   = null;
            } else {
                $id = DB::table('departamentos')->insertGetId([
                    'empresa_id' => $empresaId, 'nombre' => 'Administración y Finanzas', 'descripcion' => 'Contabilidad, finanzas y administración general',
                    'estado' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $destino = DB::table('departamentos')->find($id);
            }
        }
        foreach ([$conta, $finan] as $viejo) {
            if ($viejo && $viejo->id !== $destino->id) {
                DB::table('colaboradores')->where('departamento_id', $viejo->id)
                    ->update(['departamento_id' => $destino->id, 'departamento' => $destino->nombre]);
                DB::table('departamentos')->where('id', $viejo->id)->delete();
            }
        }
        DB::table('colaboradores')->where('departamento_id', $destino->id)->update(['departamento' => $destino->nombre]);

        // Los nuevos departamentos
        foreach (self::NUEVOS as $nombre => $descripcion) {
            if (!$buscar($nombre)) {
                DB::table('departamentos')->insert([
                    'empresa_id' => $empresaId, 'nombre' => $nombre, 'descripcion' => $descripcion,
                    'estado' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        // Descripción obligatoria: se completa la que falte
        DB::table('departamentos')->where('empresa_id', $empresaId)
            ->where(fn ($q) => $q->whereNull('descripcion')->orWhere('descripcion', ''))
            ->update(['descripcion' => DB::raw("'Departamento de ' || nombre")]);
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
