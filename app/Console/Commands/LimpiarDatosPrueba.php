<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Borrado de la información de PRUEBA antes del arranque (02-01 §2.5, CAM-052).
 *
 * Por defecto solo MUESTRA qué se borraría (simulación). Para ejecutar hay que
 * pasar --ejecutar y escribir la frase de confirmación. No toca: usuarios,
 * perfiles, permisos, empresas, centros de costo, plan de cuentas, parámetros,
 * períodos, productos, saldos de inventario, clientes, proveedores, colaboradores,
 * bancos/cajas ni bodegas (el saldo de inventario se reemplaza con la carga del
 * Excel de inventario, que se hace aparte).
 */
class LimpiarDatosPrueba extends Command
{
    protected $signature = 'altamira:limpiar-datos-prueba
                            {--ejecutar : Borra de verdad (sin esto solo simula)}';

    protected $description = 'Borra los datos de prueba transaccionales (simulación por defecto)';

    private const FRASE = 'BORRAR DATOS DE PRUEBA';

    /** Tablas transaccionales, de las hojas hacia las raíces. */
    private const TABLAS = [
        // Ventas y cobranza
        'factura_pagos', 'factura_detalles', 'facturas',
        'cuentas_cobrar_cobros', 'cuentas_cobrar',
        'nota_credito_detalles', 'notas_credito',
        'retencion_detalles', 'retenciones',
        'guia_remision_detalles', 'guias_remision',
        'prefactura_abonos', 'prefactura_detalles', 'prefacturas',
        'proforma_detalles', 'proformas',
        'aprobaciones_especiales',
        // Compras e importaciones
        'devoluciones_compra_detalles', 'devoluciones_compra',
        'anticipos_proveedores', 'cuentas_pagar',
        'recepcion_escaneos', 'recepcion_detalles', 'recepciones_bodega',
        'etiquetas_productos', 'compra_detalles', 'compras',
        'partidas_transito', 'importaciones',
        // Inventario (movimientos; los saldos NO se tocan)
        'traslado_detalles', 'traslados_bodega',
        'producto_series', 'inventario_movimientos',
        // Bancos y caja
        'conciliaciones_bancarias', 'cheques', 'datafast_liquidaciones', 'datafast_lotes',
        'movimientos_bancarios', 'cierres_caja',
        // Contabilidad
        'asiento_detalles', 'asientos_contables',
        // RRHH
        'nomina_detalles', 'nominas', 'liquidaciones', 'prestamos_empleados',
        'asistencias', 'horas_extras_aprobacion',
        // Taller
        'taller_ot_repuestos', 'taller_rev_componentes', 'taller_diagnosticos',
        'taller_ordenes_trabajo', 'taller_ingresos', 'taller_equipos',
        // Avisos y bitácoras de las pruebas
        'notificaciones', 'log_documentos', 'log_cambios_criticos',
    ];

    public function handle(): int
    {
        $existentes = array_values(array_filter(self::TABLAS, fn ($t) => Schema::hasTable($t)));

        $filas = [];
        $total = 0;
        foreach ($existentes as $t) {
            $n = DB::table($t)->count();
            $total += $n;
            $filas[] = [$t, $n];
        }
        $this->table(['Tabla', 'Filas'], array_filter($filas, fn ($f) => $f[1] > 0) ?: [['(todas vacías)', 0]]);
        $this->info("Total de filas a borrar: {$total}");

        // Seguridad: TRUNCATE ... CASCADE vacía también las tablas que referencian
        // a las truncadas. Si alguna no está en la lista, no se ejecuta nada.
        $fuera = $this->tablasAfectadasFueraDeLista($existentes);
        if ($fuera) {
            $this->error('Abortado: borrar estas tablas arrastraría otras que no están en la lista: ' . implode(', ', $fuera));
            return self::FAILURE;
        }

        if (!$this->option('ejecutar')) {
            $this->warn('Simulación: no se borró nada. Use --ejecutar para borrar.');
            return self::SUCCESS;
        }

        $this->warn('BD: ' . DB::getDatabaseName() . ' — esta acción no se puede deshacer.');
        if ($this->ask('Escriba exactamente "' . self::FRASE . '" para continuar') !== self::FRASE) {
            $this->error('Frase incorrecta. No se borró nada.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($existentes) {
            DB::statement('TRUNCATE TABLE ' . implode(', ', array_map(fn ($t) => '"' . $t . '"', $existentes)) . ' RESTART IDENTITY CASCADE');
        });

        $this->info('Datos de prueba borrados.');
        return self::SUCCESS;
    }

    /** Tablas que referencian (directa o indirectamente) a las de la lista y no están en ella. */
    private function tablasAfectadasFueraDeLista(array $lista): array
    {
        $pendientes = $lista;
        $vistas     = array_flip($lista);
        $fuera      = [];

        while ($pendientes) {
            $t = array_pop($pendientes);
            $hijas = DB::select(
                "select distinct cl.relname as tabla
                 from pg_constraint c
                 join pg_class cl on cl.oid = c.conrelid
                 join pg_class rf on rf.oid = c.confrelid
                 where c.contype = 'f' and rf.relname = ?",
                [$t]
            );
            foreach ($hijas as $h) {
                if (!isset($vistas[$h->tabla])) {
                    $vistas[$h->tabla] = true;
                    $fuera[]           = $h->tabla;
                    $pendientes[]      = $h->tabla;
                }
            }
        }

        return $fuera;
    }
}
