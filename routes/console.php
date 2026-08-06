<?php

use App\Jobs\AlertaAtrasosRecurrentes;
use App\Jobs\AlertaCajasAbiertas;
use App\Jobs\AlertaVencimientoCxP;
use App\Jobs\AlertaVouchersNoLiquidados;
use App\Jobs\LimpiarExportacionesNominaJob;
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

// Cajas que siguen 'abierto' desde el día anterior o antes (se supone que
// abren y cierran el mismo día) — diario a las 09:15
Schedule::job(new AlertaCajasAbiertas)->dailyAt('09:15');

// Recordatorio cierre de nómina — día 28 de cada mes a las 09:00
Schedule::job(new RecordatorioCierreNomina)->monthlyOn(28, '09:00');

// Borra ZIP de roles de pago de Nómina generados en segundo plano con más
// de 48h — diario a las 03:25
Schedule::job(new LimpiarExportacionesNominaJob)->dailyAt('03:25');
