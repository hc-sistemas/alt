<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Puestos de trabajo
        if (!Schema::hasTable('puestos_trabajo')) {
            Schema::create('puestos_trabajo', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('nombre', 100);
                $table->string('cargo', 100)->nullable();
                $table->string('departamento', 100)->nullable();
                $table->boolean('estado')->default(true);
            });
        }

        // 2. Horarios de trabajo
        if (!Schema::hasTable('horarios')) {
            Schema::create('horarios', function (Blueprint $table) {
                $table->id();
                $table->string('descripcion', 100);
                $table->time('hora_entrada');
                $table->time('hora_salida');
                $table->smallInteger('tolerancia_minutos')->default(5);
                $table->boolean('lunes')->default(true);
                $table->boolean('martes')->default(true);
                $table->boolean('miercoles')->default(true);
                $table->boolean('jueves')->default(true);
                $table->boolean('viernes')->default(true);
                $table->boolean('sabado')->default(false);
                $table->boolean('domingo')->default(false);
            });
        }

        // 3. Colaboradores / Empleados
        if (!Schema::hasTable('colaboradores')) {
            Schema::create('colaboradores', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->foreignId('puesto_id')->nullable()->constrained('puestos_trabajo')->nullOnDelete();
                $table->foreignId('horario_id')->nullable()->constrained('horarios')->nullOnDelete();
                // Identificación
                $table->string('cedula_ruc', 13)->unique();
                $table->string('apellidos', 100);
                $table->string('nombres', 100);
                $table->string('email', 200)->nullable()->unique();
                $table->string('telefono', 20)->nullable();
                $table->string('celular', 20)->nullable();
                $table->string('direccion', 300)->nullable();
                $table->date('fecha_nacimiento')->nullable();
                $table->char('sexo', 1)->nullable();
                $table->string('estado_civil', 20)->nullable();
                // Datos laborales
                $table->date('fecha_ingreso');
                $table->date('fecha_salida')->nullable();
                $table->string('tipo_contrato', 50)->nullable(); // indefinido|plazo_fijo|honorarios
                $table->string('cargo', 100)->nullable();
                $table->string('departamento', 100)->nullable();
                $table->decimal('comision_porcentaje', 5, 2)->default(0);
                // Remuneración
                $table->decimal('sueldo_base', 10, 2)->default(0);
                $table->string('decimo_tercero', 10)->default('acumula'); // acumula|mensualiza
                $table->string('decimo_cuarto', 10)->default('acumula');
                $table->string('fondos_reserva', 10)->default('acumula');
                // Datos bancarios
                $table->string('banco', 100)->nullable();
                $table->string('tipo_cuenta', 20)->nullable(); // ahorros|corriente
                $table->string('numero_cuenta', 30)->nullable();
                // Sistema
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }

        // 4. Asistencias / Timbre digital
        if (!Schema::hasTable('asistencias')) {
            Schema::create('asistencias', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
                $table->date('fecha')->default(now()->toDateString());
                $table->timestamp('hora_entrada')->nullable();
                $table->timestamp('hora_salida')->nullable();
                $table->integer('minutos_atraso')->default(0);
                $table->decimal('horas_extra', 5, 2)->default(0);
                $table->string('tipo_extra', 20)->nullable(); // suplementaria|extraordinaria
                $table->string('ip_entrada', 45)->nullable();
                $table->string('ip_salida', 45)->nullable();
                $table->string('observacion', 300)->nullable();
            });
        }

        // 5. Horas extras pendientes de aprobación
        if (!Schema::hasTable('horas_extras_aprobacion')) {
            Schema::create('horas_extras_aprobacion', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
                $table->foreignId('asistencia_id')->nullable()->constrained('asistencias')->nullOnDelete();
                $table->date('fecha');
                $table->decimal('horas_solicitadas', 5, 2);
                $table->decimal('horas_aprobadas', 5, 2)->default(0);
                $table->string('tipo', 20); // suplementaria|extraordinaria
                $table->decimal('valor_calculado', 10, 2)->default(0);
                $table->string('estado', 20)->default('pendiente'); // pendiente|aprobado|rechazado
                $table->foreignId('aprobado_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamp('fecha_aprobacion')->nullable();
                $table->string('observacion', 300)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('horas_extras_aprobacion');
        Schema::dropIfExists('asistencias');
        Schema::dropIfExists('colaboradores');
        Schema::dropIfExists('horarios');
        Schema::dropIfExists('puestos_trabajo');
    }
};
