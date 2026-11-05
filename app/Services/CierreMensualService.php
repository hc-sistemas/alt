<?php

namespace App\Services;

use App\Models\EjercicioContable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Controles del cierre mensual (02-08 §2.9). Cada control tiene un nivel:
 *  - bloqueo:     el cierre no puede continuar mientras no se cumpla (asientos descuadrados, caja abierta, banco sin conciliar);
 *  - justificable: se puede cerrar solo si el contador lo justifica expresamente (tarjetas, cheques, subsidiarios vs mayor, etc.);
 *  - manual:      lo que el sistema no puede comprobar (IVA, cuentas sin clasificar, anticipos): el contador lo confirma.
 */
class CierreMensualService
{
    /** Diferencia tolerada entre un auxiliar y su cuenta del mayor (redondeos). */
    private const TOLERANCIA = 1.00;

    /** @return array<int, array{clave:string,titulo:string,nivel:string,ok:bool,detalle:string}> */
    public function controles(EjercicioContable $e): array
    {
        $empresaId = (int) $e->empresa_id;
        $desde = Carbon::create($e->anio, $e->mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth();
        $d = $desde->toDateString();
        $h = $hasta->toDateString();
        $c = [];
        $add = function (string $clave, string $titulo, string $nivel, bool $ok, string $detalle) use (&$c) {
            $c[] = compact('clave', 'titulo', 'nivel', 'ok', 'detalle');
        };

        // ── Bloqueos ──────────────────────────────────────────────────────────
        $sinCuadrar = $e->asientos()->whereRaw('ABS(total_debe - total_haber) > 0.0001')->where('estado', 1)->count();
        $add('asientos', 'Asientos cuadrados (débito = crédito)', 'bloqueo', $sinCuadrar === 0,
            $sinCuadrar ? "{$sinCuadrar} asiento(s) sin cuadrar" : 'Todos cuadran');

        $cajas = DB::table('cierres_caja')->where('empresa_id', $empresaId)->where('estado', 'abierto')->whereBetween('fecha', [$d, $h])->count();
        $add('cajas', 'Cajas de todos los días cerradas', 'bloqueo', $cajas === 0,
            $cajas ? "{$cajas} caja(s) abierta(s): ciérrelas en Bancos → Cajas" : 'Sin cajas abiertas');

        $sinConciliar = [];
        foreach (DB::table('bancos_cajas')->where('empresa_id', $empresaId)->where('tipo', 'banco')->where('estado', true)->get(['id', 'nombre']) as $b) {
            $tiene = DB::table('movimientos_bancarios')->where('banco_caja_id', $b->id)->where('anulado', false)->whereBetween('fecha', [$d, $h])->exists();
            $conc  = DB::table('conciliaciones_bancarias')->where('banco_caja_id', $b->id)->where('estado', 'conciliada')->where('fecha_corte', '>=', $h)->exists();
            if ($tiene && !$conc) {
                $sinConciliar[] = $b->nombre;
            }
        }
        $add('bancos', 'Bancos conciliados al fin del mes', 'bloqueo', $sinConciliar === [],
            $sinConciliar ? 'Falta conciliar: ' . implode(', ', $sinConciliar) . ' (corte al ' . $hasta->format('d/m/Y') . ')' : 'Todos conciliados');

        // ── Justificables ─────────────────────────────────────────────────────
        $lotes = DB::table('datafast_lotes')->where('empresa_id', $empresaId)->where('estado', 'pendiente')->where('fecha', '<=', $h)->count();
        $add('tarjetas', 'Tarjetas liquidadas o justificadas', 'justificable', $lotes === 0,
            $lotes ? "{$lotes} lote(s) de tarjetas sin liquidar" : 'Sin lotes pendientes');

        $chq = DB::table('cheques')->where('empresa_id', $empresaId)->where('estado', 'emitido')->where('fecha_emision', '<=', $h)->count();
        $cartera = Schema::hasTable('cheques_recibidos')
            ? DB::table('cheques_recibidos')->where('empresa_id', $empresaId)->where('estado', 'en_cartera')->where('fecha_cheque', '<=', $h)->count() : 0;
        $add('cheques', 'Cheques emitidos cobrados y recibidos depositados (o justificados)', 'justificable', $chq + $cartera === 0,
            ($chq + $cartera) ? "{$chq} emitido(s) sin cobrar y {$cartera} recibido(s) en cartera ya depositables" : 'Sin pendientes');

        $mayorCxC = $this->saldoMayor($empresaId, $h, 'cta_clientes_locales', 1);
        $cxc = (float) DB::table('cuentas_cobrar')->where('empresa_id', $empresaId)->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->sum('saldo');
        $this->cuadre($add, 'cxc', 'Cuentas por cobrar cuadradas con el mayor', $cxc, $mayorCxC);

        $mayorCxP = $this->saldoMayor($empresaId, $h, 'cta_proveedores_locales', -1);
        $cxp = (float) DB::table('cuentas_pagar')->where('empresa_id', $empresaId)->whereIn('estado', ['pendiente', 'parcial'])->sum('saldo');
        $this->cuadre($add, 'cxp', 'Cuentas por pagar cuadradas con el mayor', $cxp, $mayorCxP);

        $kardex = (float) DB::table('inventario_saldos as s')->join('bodegas as b', 'b.id', '=', 's.bodega_id')
            ->where('b.empresa_id', $empresaId)->selectRaw('COALESCE(SUM(s.stock_actual * s.costo_promedio), 0) as v')->value('v');
        $mayorInv = (float) DB::table('asiento_detalles as d')->join('asientos_contables as a', 'a.id', '=', 'd.asiento_id')
            ->join('plan_cuentas as c', 'c.id', '=', 'd.cuenta_id')
            ->where('a.empresa_id', $empresaId)->where('a.estado', 1)->where('a.fecha', '<=', $h)
            ->where('c.codigo', 'like', '1.01.04.%')->where('c.codigo', '!=', '1.01.04.07')
            ->sum(DB::raw('d.debe - d.haber'));
        $this->cuadre($add, 'inventario', 'Inventario cuadrado con el kárdex', $kardex, $mayorInv);

        $imps = DB::table('importaciones')->where('empresa_id', $empresaId)->where('estado', '!=', 'liquidada')->count();
        $add('importaciones', 'Importaciones liquidadas', 'justificable', $imps === 0,
            $imps ? "{$imps} importación(es) sin liquidar" : 'Todas liquidadas');

        $activos = Schema::hasTable('activos_fijos') ? DB::table('activos_fijos')->where('empresa_id', $empresaId)->where('estado', true)->count() : 0;
        $deprec  = Schema::hasTable('activos_depreciaciones')
            ? DB::table('activos_depreciaciones')->where('periodo_año', $e->anio)->where('periodo_mes', $e->mes)->exists() : false;
        $add('depreciacion', 'Depreciación del mes registrada', 'justificable', $activos === 0 || $deprec,
            $activos === 0 ? 'No hay activos fijos' : ($deprec ? 'Depreciación registrada' : "Falta la depreciación de {$activos} activo(s) fijo(s)"));

        $colab = Schema::hasTable('colaboradores') ? DB::table('colaboradores')->where('empresa_id', $empresaId)->where('estado', true)->count() : 0;
        $nomina = DB::table('nominas')->where('empresa_id', $empresaId)->where('anio', $e->anio)->where('mes', $e->mes)
            ->whereIn('estado', ['procesado', 'procesada', 'pagado', 'pagada'])->exists();
        $add('nomina', 'Nómina del mes procesada', 'justificable', $colab === 0 || $nomina,
            $colab === 0 ? 'No hay colaboradores activos' : ($nomina ? 'Nómina procesada' : 'No hay nómina procesada para el mes'));

        // ── Manuales (el sistema no puede comprobarlos) ───────────────────────
        $sinClasif = (float) DB::table('asiento_detalles as d')->join('asientos_contables as a', 'a.id', '=', 'd.asiento_id')
            ->join('plan_cuentas as c', 'c.id', '=', 'd.cuenta_id')
            ->where('a.empresa_id', $empresaId)->where('a.estado', 1)->where('a.fecha', '<=', $h)
            ->whereIn('c.codigo', ['1.01.04.09', '4.01.09', '5.01.08'])->sum(DB::raw('ABS(d.debe - d.haber)'));
        $add('iva', 'IVA del mes revisado (PC-TRI-01)', 'manual', false, 'La liquidación del IVA la confirma el contador');
        $add('sin_clasificar', 'Cuentas sin clasificar revisadas (no se exige saldo cero, DEC-031)', 'manual', false,
            'Movimiento en 1.01.04.09, 4.01.09 y 5.01.08 acumulado: $' . number_format($sinClasif, 2));
        $anticipos = Schema::hasTable('anticipos_proveedores')
            ? (float) DB::table('anticipos_proveedores')->where('empresa_id', $empresaId)->sum('saldo') : 0.0;
        $add('anticipos', 'Anticipos conciliados', 'manual', false, 'Saldo de anticipos a proveedores: $' . number_format($anticipos, 2));

        return $c;
    }

    /** Fecha de fin de mes que aún no llegó: cerrar antes exige confirmación (02-08 §2.9). */
    public function esAnticipado(EjercicioContable $e): bool
    {
        return now()->toDateString() < Carbon::create($e->anio, $e->mes, 1)->endOfMonth()->toDateString();
    }

    private function cuadre(callable $add, string $clave, string $titulo, float $auxiliar, float $mayor): void
    {
        $dif = round($auxiliar - $mayor, 2);
        $add($clave, $titulo, 'justificable', abs($dif) <= self::TOLERANCIA,
            abs($dif) <= self::TOLERANCIA ? 'Cuadra con el mayor'
                : 'Auxiliar $' . number_format($auxiliar, 2) . ' frente al mayor $' . number_format($mayor, 2) . ' (diferencia $' . number_format($dif, 2) . ')');
    }

    /** Saldo del mayor de la cuenta de un parámetro; $signo = 1 para saldo deudor, −1 para acreedor. */
    private function saldoMayor(int $empresaId, string $hasta, string $parametro, int $signo): float
    {
        $cuentaId = app(AsientoService::class)->cuentaId($parametro, $empresaId);
        $neto = (float) DB::table('asiento_detalles as d')->join('asientos_contables as a', 'a.id', '=', 'd.asiento_id')
            ->where('a.empresa_id', $empresaId)->where('a.estado', 1)->where('a.fecha', '<=', $hasta)->where('d.cuenta_id', $cuentaId)
            ->sum(DB::raw('d.debe - d.haber'));

        return round($neto * $signo, 2);
    }
}
