<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Rubros de nómina (catálogo de conceptos)
        if (!Schema::hasTable('rubros_nomina')) {
            Schema::create('rubros_nomina', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 20)->unique();
                $table->string('descripcion', 200);
                $table->string('grupo', 50)->nullable();       // ingreso|descuento|provision
                $table->string('tipo_valor', 20)->nullable();  // fijo|porcentaje|formula
                $table->decimal('valor', 10, 4)->default(0);
                $table->string('operacion', 10)->default('+'); // +|-
                $table->string('cuenta_contable', 20)->nullable();
                $table->boolean('afecta_iess')->default(false);
                $table->boolean('afecta_renta')->default(false);
                $table->boolean('estado')->default(true);
            });
        }

        // 2. Préstamos y anticipos a empleados
        if (!Schema::hasTable('prestamos_empleados')) {
            Schema::create('prestamos_empleados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colaborador_id')->constrained('colaboradores')->cascadeOnDelete();
                $table->string('tipo', 20)->default('anticipo'); // anticipo|prestamo
                $table->decimal('monto_total', 10, 2);
                $table->decimal('saldo', 10, 2);
                $table->decimal('cuota', 10, 2)->default(0);
                $table->date('fecha')->default(now()->toDateString());
                $table->string('descripcion', 300)->nullable();
                $table->string('estado', 20)->default('activo'); // activo|pagado
                $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 3. Nóminas — cabecera por período
        if (!Schema::hasTable('nominas')) {
            Schema::create('nominas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('periodo_tipo', 20);           // mensual|quincenal
                $table->smallInteger('anio');
                $table->tinyInteger('mes');                   // 1-12
                $table->tinyInteger('quincena')->nullable();  // 1|2, null si mensual
                $table->date('fecha_emision');
                $table->string('estado', 20)->default('borrador'); // borrador|procesado|pagado
                $table->decimal('total_ingresos', 10, 2)->default(0);
                $table->decimal('total_egresos', 10, 2)->default(0);
                $table->decimal('total_neto', 10, 2)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->foreignId('generado_por')->constrained('usuarios');
                $table->foreignId('procesado_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->foreignId('pagado_por')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // 4. Nómina detalles — una fila por colaborador
        if (!Schema::hasTable('nomina_detalles')) {
            Schema::create('nomina_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('nomina_id')->constrained('nominas')->cascadeOnDelete();
                $table->foreignId('colaborador_id')->constrained('colaboradores');
                // Ingresos
                $table->decimal('sueldo_base', 10, 2)->default(0);
                $table->decimal('horas_extras_50', 10, 2)->default(0);
                $table->decimal('horas_extras_100', 10, 2)->default(0);
                $table->decimal('comisiones', 10, 2)->default(0);
                $table->decimal('otros_ingresos', 10, 2)->default(0);
                $table->decimal('total_ingresos', 10, 2)->default(0);
                // Egresos
                $table->decimal('aporte_personal_iess', 10, 2)->default(0);
                $table->decimal('descuento_atrasos', 10, 2)->default(0);
                $table->decimal('descuento_prestamos', 10, 2)->default(0);
                $table->decimal('descuento_anticipos', 10, 2)->default(0);
                $table->decimal('otros_egresos', 10, 2)->default(0);
                $table->decimal('total_egresos', 10, 2)->default(0);
                $table->decimal('neto_pagar', 10, 2)->default(0);
                // Pago
                $table->string('tipo_pago', 20)->nullable();  // transferencia|cheque|efectivo
                $table->string('num_cuenta', 50)->nullable();
                $table->string('banco', 100)->nullable();
                // Control
                $table->string('estado', 20)->default('borrador');
                $table->boolean('modificado_manualmente')->default(false);
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_detalles');
        Schema::dropIfExists('nominas');
        Schema::dropIfExists('prestamos_empleados');
        Schema::dropIfExists('rubros_nomina');
    }
};
