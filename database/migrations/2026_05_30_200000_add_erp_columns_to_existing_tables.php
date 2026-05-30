<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agregar columnas faltantes a empresas
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'slogan')) {
                $table->string('slogan')->nullable()->after('logo');
            }
            if (!Schema::hasColumn('empresas', 'telefono')) {
                $table->string('telefono', 20)->nullable()->after('email_notificaciones');
            }
            if (!Schema::hasColumn('empresas', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Agregar columnas faltantes a perfiles
        Schema::table('perfiles', function (Blueprint $table) {
            if (!Schema::hasColumn('perfiles', 'es_sistema')) {
                $table->boolean('es_sistema')->default(false)->after('descripcion');
            }
        });

        // Agregar columnas faltantes a modulos
        Schema::table('modulos', function (Blueprint $table) {
            if (!Schema::hasColumn('modulos', 'clave')) {
                $table->string('clave', 30)->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('modulos', 'padre_id')) {
                $table->foreignId('padre_id')->nullable()->constrained('modulos')->nullOnDelete()->after('orden');
            }
            if (!Schema::hasColumn('modulos', 'estado')) {
                $table->boolean('estado')->default(true)->after('padre_id');
            }
            if (!Schema::hasColumn('modulos', 'created_at')) {
                $table->timestamps();
            }
        });

        // Agregar columnas faltantes a centros_costo
        Schema::table('centros_costo', function (Blueprint $table) {
            if (!Schema::hasColumn('centros_costo', 'es_taller')) {
                $table->boolean('es_taller')->default(false)->after('tipo');
            }
        });

        // Agregar columnas faltantes a usuarios
        Schema::table('usuarios', function (Blueprint $table) {
            if (!Schema::hasColumn('usuarios', 'avatar')) {
                $table->string('avatar')->nullable()->after('nombre');
            }
            if (!Schema::hasColumn('usuarios', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (!Schema::hasColumn('usuarios', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // limites_descuento - crear si no tiene estructura correcta
        if (Schema::hasTable('limites_descuento')) {
            $cols = Schema::getColumnListing('limites_descuento');
            if (!in_array('puede_aprobar', $cols)) {
                Schema::table('limites_descuento', function (Blueprint $table) {
                    $table->decimal('porcentaje_maximo', 5, 2)->default(0)->after('perfil_id');
                    $table->boolean('puede_aprobar')->default(false)->after('porcentaje_maximo');
                    $table->decimal('porcentaje_aprobacion_max', 5, 2)->default(0)->after('puede_aprobar');
                });
            }
        }
    }

    public function down(): void
    {
        // No revertir en producción
    }
};
