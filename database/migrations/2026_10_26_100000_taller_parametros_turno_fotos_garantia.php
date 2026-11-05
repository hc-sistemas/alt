<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo K, Taller (02-10): parámetros PC-TAL-02, turno por orden de llegada, hasta 4 fotos por
 * ingreso, cierre de garantías y condiciones del servicio configurables (DEC-054). Idempotente.
 */
return new class extends Migration
{
    /** Parámetros nuevos: la cuenta sale del plan vigente, no se escribe en el código. */
    private const PARAMETROS = [
        'cta_ventas_repuestos_taller'     => ['4.01.06', 'Venta de Repuestos e Insumos de Taller (PC-TAL-02)'],
        'cta_costo_repuestos_taller'      => ['5.01.06', 'Costo de Ventas - Repuestos e Insumos de Taller (PC-TAL-02)'],
        'cta_inventario_repuestos_taller' => ['1.01.04.06', 'Inventario de Repuestos e Insumos de Taller (PC-TAL-02)'],
    ];

    public const CONDICIONES_DEFECTO = "El equipo debe retirarse con este documento.\n"
        . "Si en el lapso de 3 meses luego de la notificación de arreglo su equipo no es retirado podrá ser rematado.";

    public function up(): void
    {
        if (Schema::hasTable('parametros_contables') && Schema::hasTable('plan_cuentas')) {
            foreach (self::PARAMETROS as $codigo => [$codigoCuenta, $descripcion]) {
                $cuentaId = DB::table('plan_cuentas')->where('codigo', $codigoCuenta)->value('id');
                if ($cuentaId && !DB::table('parametros_contables')->whereNull('empresa_id')->where('codigo', $codigo)->exists()) {
                    DB::table('parametros_contables')->insert([
                        'empresa_id' => null, 'codigo' => $codigo, 'cuenta_id' => $cuentaId, 'descripcion' => $descripcion,
                    ]);
                }
            }
        }

        if (Schema::hasTable('taller_ordenes_trabajo')) {
            Schema::table('taller_ordenes_trabajo', function (Blueprint $table) {
                if (!Schema::hasColumn('taller_ordenes_trabajo', 'turno')) {
                    $table->unsignedInteger('turno')->nullable();
                }
                if (!Schema::hasColumn('taller_ordenes_trabajo', 'costo_garantia')) {
                    $table->decimal('costo_garantia', 14, 2)->default(0);
                }
                if (!Schema::hasColumn('taller_ordenes_trabajo', 'garantia_cerrada_at')) {
                    $table->timestamp('garantia_cerrada_at')->nullable();
                }
            });
        }

        if (!Schema::hasTable('taller_ingreso_fotos') && Schema::hasTable('taller_ingresos')) {
            Schema::create('taller_ingreso_fotos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ingreso_id')->index();
                $table->string('ruta', 255);
                $table->unsignedSmallInteger('orden')->default(1);
                $table->timestamps();
            });
        }

        // Condiciones del servicio del documento de ingreso (texto editable, DEC-054 pendiente)
        if (Schema::hasTable('configuraciones') && Schema::hasTable('empresas')) {
            foreach (DB::table('empresas')->pluck('id') as $empresaId) {
                if (DB::table('configuraciones')->where('empresa_id', $empresaId)->where('clave', 'taller_condiciones_servicio')->exists()) {
                    continue;
                }
                $this->insertarCondiciones((int) $empresaId);
            }
        }
    }

    private function insertarCondiciones(int $empresaId): void
    {
        {
            $cols = Schema::getColumnListing('configuraciones');
            $fila = ['clave' => 'taller_condiciones_servicio', 'valor' => self::CONDICIONES_DEFECTO, 'empresa_id' => $empresaId];
            foreach (['created_at', 'updated_at'] as $ts) {
                if (in_array($ts, $cols, true)) {
                    $fila[$ts] = now();
                }
            }
            if (in_array('tipo', $cols, true)) {
                $fila['tipo'] = 'texto';
            }
            if (in_array('descripcion', $cols, true)) {
                $fila['descripcion'] = 'Condiciones del servicio impresas en el documento de ingreso a taller';
            }
            DB::table('configuraciones')->insert($fila);
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
