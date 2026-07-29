<?php

use App\Jobs\AlertaAtrasosRecurrentes;
use App\Jobs\AlertaVencimientoCxP;
use App\Jobs\AlertaVouchersNoLiquidados;
use App\Jobs\LimpiarExportacionesAsientosJob;
use App\Jobs\LimpiarExportacionesComprasJob;
use App\Jobs\LimpiarExportacionesCxPJob;
use App\Jobs\LimpiarExportacionesProveedoresJob;
use App\Jobs\LimpiarExportacionesReportesContablesJob;
use App\Jobs\RecordatorioCierreNomina;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Revisar CxP por vencer todos los días a las 08:00
Schedule::job(new AlertaVencimientoCxP)->dailyAt('08:00');

// Lotes Datafast pendientes > 72h sin liquidar — diario a las 09:00
Schedule::job(new AlertaVouchersNoLiquidados)->dailyAt('09:00');

// Colaboradores con 3+ atrasos en la semana en curso — diario a las 09:00
Schedule::job(new AlertaAtrasosRecurrentes)->dailyAt('09:00');

// Recordatorio cierre de nómina — día 28 de cada mes a las 09:00
Schedule::job(new RecordatorioCierreNomina)->monthlyOn(28, '09:00');

// Borra exportaciones de Asientos Contables (Excel/PDF generados en segundo
// plano) con más de 48h — diario a las 03:00
Schedule::job(new LimpiarExportacionesAsientosJob)->dailyAt('03:00');

// Borra reportes PDF de Facturas de Compra generados en segundo plano con
// más de 48h — diario a las 03:05
Schedule::job(new LimpiarExportacionesComprasJob)->dailyAt('03:05');

// Borra exportaciones de Proveedores (Excel/PDF generados en segundo
// plano) con más de 48h — diario a las 03:10
Schedule::job(new LimpiarExportacionesProveedoresJob)->dailyAt('03:10');

// Borra exportaciones de Cuentas por Pagar (Excel/PDF generados en segundo
// plano) con más de 48h — diario a las 03:15
Schedule::job(new LimpiarExportacionesCxPJob)->dailyAt('03:15');

// Borra exportaciones de Reportes Contables (Libro Diario/Mayor generados
// en segundo plano) con más de 48h — diario a las 03:20
Schedule::job(new LimpiarExportacionesReportesContablesJob)->dailyAt('03:20');
