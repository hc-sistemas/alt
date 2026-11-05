<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\Cheque;
use App\Models\ConciliacionBancaria;
use App\Models\DatafastLote;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\PartidaTransito;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Ocho reportes de Bancos que faltaban (02-06 §2.8, CAM-105). Todos comparten el
 * mismo formato: secciones con columnas y filas, en PDF o Excel (?formato=excel).
 */
class BancoReportesAdicionalesController extends Controller
{
    private const REPORTES = [
        'conciliacion'            => 'Conciliación bancaria',
        'cheques-pendientes'      => 'Cheques pendientes de cobro',
        'cheques-postfechados'    => 'Cheques post fechados recibidos',
        'tarjetas-por-liquidar'   => 'Tarjetas por liquidar',
        'liquidaciones-tarjetas'  => 'Liquidaciones de tarjetas',
        'comisiones-tarjetas'     => 'Comisiones de tarjetas por período',
        'retenciones-tarjetas'    => 'Retenciones de tarjetas por período',
        'saldos-consolidados'     => 'Saldos consolidados',
        'libro-vs-mayor'          => 'Libro frente a mayor',
        'diferencias-conciliacion' => 'Diferencias de conciliación',
    ];

    public static function catalogo(): array
    {
        return self::REPORTES;
    }

    public function generar(Request $request, string $reporte)
    {
        abort_unless(isset(self::REPORTES[$reporte]), 404);
        ini_set('memory_limit', '1024M');

        $empresaId = (int) session('empresa_activa_id');
        $request->validate([
            'fecha_desde'      => 'nullable|date',
            'fecha_hasta'      => 'nullable|date|after_or_equal:fecha_desde',
            'banco_caja_id'    => 'nullable|integer|exists_empresa:bancos_cajas',
            'conciliacion_id'  => 'nullable|integer|exists:conciliaciones_bancarias,id',
            'formato'          => 'nullable|in:pdf,excel',
        ]);

        $datos = match ($reporte) {
            'conciliacion'             => $this->conciliacion($request, $empresaId),
            'cheques-pendientes'       => $this->chequesPendientes($request, $empresaId),
            'cheques-postfechados'     => $this->chequesPostfechados($request, $empresaId),
            'tarjetas-por-liquidar'    => $this->tarjetasPorLiquidar($request, $empresaId),
            'liquidaciones-tarjetas'   => $this->liquidacionesTarjetas($request, $empresaId),
            'comisiones-tarjetas'      => $this->comisionesTarjetas($request, $empresaId),
            'retenciones-tarjetas'     => $this->retencionesTarjetas($request, $empresaId),
            'saldos-consolidados'      => $this->saldosConsolidados(),
            'libro-vs-mayor'           => $this->libroVsMayor($request, $empresaId),
            'diferencias-conciliacion' => $this->diferenciasConciliacion($request, $empresaId),
        };
        $datos['titulo'] = self::REPORTES[$reporte];

        $nombre = $reporte . '-' . now()->format('Y-m-d');

        if ($request->input('formato') === 'excel') {
            return $this->excel($datos, $nombre);
        }

        $empresa = Empresa::find($empresaId);
        return Pdf::loadView('pdf.banco-reporte-generico', compact('datos', 'empresa'))
            ->setPaper('a4', 'landscape')
            ->stream($nombre . '.pdf');
    }

    // ── Utilidades ────────────────────────────────────────────────────────────

    private function m(float|int|string|null $v): string
    {
        return number_format((float) $v, 2, '.', ',');
    }

    private function f($fecha): string
    {
        return $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '—';
    }

    private function rango(Request $r): string
    {
        $d = $r->filled('fecha_desde') ? $this->f($r->fecha_desde) : 'inicio';
        $h = $r->filled('fecha_hasta') ? $this->f($r->fecha_hasta) : $this->f(now());
        return "Del {$d} al {$h}";
    }

    private function excel(array $datos, string $nombre)
    {
        $filas = [[$datos['titulo']], [$datos['subtitulo'] ?? ''], []];
        foreach ($datos['secciones'] as $sec) {
            if (!empty($sec['titulo'])) $filas[] = [$sec['titulo']];
            $filas[] = array_map(fn ($c) => $c['t'], $sec['columnas']);
            foreach ($sec['filas'] as $fila) $filas[] = $fila;
            if (!empty($sec['totales'])) $filas[] = $sec['totales'];
            $filas[] = [];
        }

        $export = new class($filas) implements FromArray {
            public function __construct(private array $filas) {}
            public function array(): array { return $this->filas; }
        };

        return Excel::download($export, $nombre . '.xlsx');
    }

    /** Columna de texto (izquierda) o numérica (derecha). */
    private function col(string $t, bool $num = false): array
    {
        return ['t' => $t, 'n' => $num];
    }

    // ── 1. Conciliación bancaria ──────────────────────────────────────────────
    private function conciliacion(Request $r, int $empresaId): array
    {
        $q = ConciliacionBancaria::with('bancoCaja')->where('empresa_id', $empresaId);
        if ($r->filled('conciliacion_id')) $q->where('id', $r->conciliacion_id);
        if ($r->filled('banco_caja_id'))   $q->where('banco_caja_id', $r->banco_caja_id);
        $conc = $q->orderByDesc('fecha_corte')->orderByDesc('id')->first();

        if (!$conc) {
            return ['subtitulo' => 'Sin conciliaciones registradas.', 'secciones' => []];
        }

        $pend = fn (string $tipo, string $sentido) => round((float) PartidaTransito::where('conciliacion_id', $conc->id)
            ->where('tipo', $tipo)->where('conciliada', false)->where('sentido', $sentido)->sum('monto'), 2);

        $libro = (float) $conc->saldo_sistema;
        $dep   = $pend('sistema', 'ingreso');
        $chq   = $pend('sistema', 'egreso');
        $nc    = $pend('banco', 'ingreso');
        $nd    = $pend('banco', 'egreso');
        $esperado = round($libro - $dep + $chq + $nc - $nd, 2);

        $partidas = function (string $tipo) use ($conc) {
            return PartidaTransito::where('conciliacion_id', $conc->id)->where('tipo', $tipo)->where('conciliada', false)
                ->orderBy('fecha')->get()
                ->map(fn ($p) => [$this->f($p->fecha), $p->descripcion, ucfirst((string) $p->sentido), $this->m($p->monto)])->all();
        };

        return [
            'subtitulo' => "{$conc->bancoCaja?->nombre} · Corte al {$this->f($conc->fecha_corte)} · " . ($conc->estaConciliada() ? 'Conciliada' : 'Pendiente'),
            'secciones' => [
                [
                    'titulo'   => 'Cálculo',
                    'columnas' => [$this->col('Concepto'), $this->col('Valor', true)],
                    'filas'    => [
                        ['Saldo según libro al corte', $this->m($libro)],
                        ['(−) Depósitos en tránsito', $this->m($dep)],
                        ['(+) Cheques y egresos no cobrados', $this->m($chq)],
                        ['(+) Notas de crédito del banco no registradas', $this->m($nc)],
                        ['(−) Notas de débito del banco no registradas', $this->m($nd)],
                        ['= Saldo esperado según banco', $this->m($esperado)],
                        ['Saldo del estado de cuenta', $this->m($conc->saldo_banco)],
                        ['Diferencia', $this->m($conc->diferencia)],
                    ],
                ],
                [
                    'titulo'   => 'Del libro y no en el banco (cheques no cobrados, depósitos en tránsito)',
                    'columnas' => [$this->col('Fecha'), $this->col('Descripción'), $this->col('Sentido'), $this->col('Monto', true)],
                    'filas'    => $partidas('sistema'),
                ],
                [
                    'titulo'   => 'Del banco y no en el libro (notas de débito y crédito)',
                    'columnas' => [$this->col('Fecha'), $this->col('Descripción'), $this->col('Sentido'), $this->col('Monto', true)],
                    'filas'    => $partidas('banco'),
                ],
                [
                    'titulo'   => 'Observación',
                    'columnas' => [$this->col('Detalle')],
                    'filas'    => [[$conc->observacion ?: '—']],
                ],
            ],
        ];
    }

    // ── 2. Cheques pendientes de cobro ────────────────────────────────────────
    private function chequesPendientes(Request $r, int $empresaId): array
    {
        $q = Cheque::with('bancoCaja')->where('empresa_id', $empresaId)->where('estado', 'emitido');
        if ($r->filled('banco_caja_id')) $q->where('banco_caja_id', $r->banco_caja_id);
        if ($r->filled('fecha_hasta'))   $q->where('fecha_emision', '<=', $r->fecha_hasta);
        $cheques = $q->orderBy('fecha_emision')->get();

        $hoy = now();
        return [
            'subtitulo' => 'Cheques emitidos que aún no se cobran. ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('Banco'), $this->col('N° cheque'), $this->col('Beneficiario'), $this->col('Emisión'),
                               $this->col('Fecha de cobro'), $this->col('Días'), $this->col('Monto', true)],
                'filas'    => $cheques->map(fn ($c) => [
                    $c->bancoCaja?->nombre, $c->numero, $c->beneficiario, $this->f($c->fecha_emision),
                    $this->f($c->fecha_cobro), (string) (int) floor($hoy->diffInDays($c->fecha_emision, true)), $this->m($c->monto),
                ])->all(),
                'totales'  => ['TOTAL', '', '', '', '', '', $this->m($cheques->sum('monto'))],
            ]],
        ];
    }

    // ── 3. Cheques post fechados recibidos ────────────────────────────────────
    private function chequesPostfechados(Request $r, int $empresaId): array
    {
        // Cheques de clientes que siguen en cartera (aún no depositados), con fecha futura o ya vencida para depositar
        $q = \App\Models\ChequeRecibido::with(['cliente:id,razon_social', 'factura:id,numero_completo'])
            ->where('empresa_id', $empresaId)->where('estado', 'en_cartera');
        if ($r->filled('fecha_desde'))   $q->where('fecha_cheque', '>=', $r->fecha_desde);
        if ($r->filled('fecha_hasta'))   $q->where('fecha_cheque', '<=', $r->fecha_hasta);
        $movs = $q->orderBy('fecha_cheque')->get();

        return [
            'subtitulo' => 'Cheques recibidos de clientes con fecha futura, hasta su depósito. ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('Banco emisor'), $this->col('N° cheque'), $this->col('Cliente'), $this->col('Recibido'),
                               $this->col('Fecha del cheque'), $this->col('Factura'), $this->col('Monto', true)],
                'filas'    => $movs->map(fn ($m) => [
                    $m->banco_emisor, $m->numero, $m->cliente?->razon_social, $this->f($m->fecha_recepcion),
                    $this->f($m->fecha_cheque) . ($m->esPostfechado() ? ' (post fechado)' : ''), $m->factura?->numero_completo, $this->m($m->monto),
                ])->all(),
                'totales'  => ['TOTAL', '', '', '', '', '', $this->m($movs->sum('monto'))],
            ]],
        ];
    }

    // ── 4. Tarjetas por liquidar ──────────────────────────────────────────────
    private function tarjetasPorLiquidar(Request $r, int $empresaId): array
    {
        $q = DatafastLote::with('bancoCaja')->where('empresa_id', $empresaId)->where('estado', '!=', 'liquidado');
        $procesadores = DB::table('tarjeta_procesadores')->where('empresa_id', $empresaId)->pluck('nombre', 'id');
        if ($r->filled('banco_caja_id')) $q->where('banco_caja_id', $r->banco_caja_id);
        if ($r->filled('fecha_hasta'))   $q->where('fecha', '<=', $r->fecha_hasta);
        $lotes = $q->orderBy('procesador_id')->orderBy('fecha')->get();

        return [
            'subtitulo' => 'Lotes de tarjeta pendientes de liquidación por procesador (cuenta Vouchers por liquidar). ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('Procesador'), $this->col('N° lote'), $this->col('Fecha'),
                               $this->col('Días sin liquidar'), $this->col('Estado'), $this->col('Valor bruto', true)],
                'filas'    => $lotes->map(fn ($l) => [
                    ($l->procesador_id ? ($procesadores[$l->procesador_id] ?? null) : null) ?? $l->bancoCaja?->nombre,
                    $l->numero_lote, $this->f($l->fecha),
                    (string) (int) floor(now()->diffInDays($l->fecha, true)), ucfirst($l->estado), $this->m($l->total_vouchers),
                ])->all(),
                'totales'  => ['TOTAL POR LIQUIDAR', '', '', '', '', $this->m($lotes->sum('total_vouchers'))],
            ]],
        ];
    }

    // ── 5. Liquidaciones de tarjetas ──────────────────────────────────────────
    private function liquidacionesTarjetas(Request $r, int $empresaId): array
    {
        $q = DB::table('datafast_liquidaciones as l')
            ->join('datafast_lotes as t', 't.id', '=', 'l.lote_id')
            ->leftJoin('bancos_cajas as b', 'b.id', '=', 'l.banco_destino_id')
            ->where('t.empresa_id', $empresaId);
        if ($r->filled('banco_caja_id')) $q->where('l.banco_destino_id', $r->banco_caja_id);
        if ($r->filled('fecha_desde'))   $q->where('l.fecha_deposito', '>=', $r->fecha_desde);
        if ($r->filled('fecha_hasta'))   $q->where('l.fecha_deposito', '<=', $r->fecha_hasta);
        $liqs = $q->orderBy('l.fecha_deposito')->get(['t.numero_lote', 'l.fecha_deposito', 'b.nombre as banco',
            'l.valor_bruto', 'l.comision_datafast', 'l.retencion_iva', 'l.retencion_ir', 'l.valor_neto']);

        $suma = fn (string $c) => $this->m($liqs->sum($c));
        return [
            'subtitulo' => 'Liquidaciones de lotes de tarjeta: bruto, comisión, retenciones y neto depositado. ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('N° lote'), $this->col('Depósito'), $this->col('Banco destino'),
                               $this->col('Bruto', true), $this->col('Comisión', true), $this->col('Ret. IVA', true),
                               $this->col('Ret. IR', true), $this->col('Neto', true)],
                'filas'    => $liqs->map(fn ($l) => [
                    $l->numero_lote, $this->f($l->fecha_deposito), $l->banco, $this->m($l->valor_bruto),
                    $this->m($l->comision_datafast), $this->m($l->retencion_iva), $this->m($l->retencion_ir), $this->m($l->valor_neto),
                ])->all(),
                'totales'  => ['TOTAL', '', '', $suma('valor_bruto'), $suma('comision_datafast'), $suma('retencion_iva'), $suma('retencion_ir'), $suma('valor_neto')],
            ]],
        ];
    }

    /** Liquidaciones del período con su lote y procesador (base de comisiones y retenciones). */
    private function liquidacionesPeriodo(Request $r, int $empresaId)
    {
        $q = DB::table('datafast_liquidaciones as l')
            ->join('datafast_lotes as t', 't.id', '=', 'l.lote_id')
            ->leftJoin('tarjeta_procesadores as p', 'p.id', '=', 't.procesador_id')
            ->where('t.empresa_id', $empresaId);
        if ($r->filled('banco_caja_id')) $q->where('l.banco_destino_id', $r->banco_caja_id);
        if ($r->filled('fecha_desde'))   $q->where('l.fecha_deposito', '>=', $r->fecha_desde);
        if ($r->filled('fecha_hasta'))   $q->where('l.fecha_deposito', '<=', $r->fecha_hasta);

        return $q->orderBy('l.fecha_deposito')->get(['t.numero_lote', 'p.nombre as procesador', 'l.fecha_deposito',
            'l.valor_bruto', 'l.comision_datafast', 'l.iva_comision', 'l.retencion_iva', 'l.retencion_ir']);
    }

    // ── Comisiones de tarjetas por período ────────────────────────────────────
    private function comisionesTarjetas(Request $r, int $empresaId): array
    {
        $liqs = $this->liquidacionesPeriodo($r, $empresaId);
        $suma = fn (string $c) => $this->m($liqs->sum($c));

        return [
            'subtitulo' => 'Comisión cobrada por el procesador (gasto 6.01.10) e IVA de la comisión (crédito tributario). ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('Depósito'), $this->col('N° lote'), $this->col('Procesador'), $this->col('Bruto', true),
                               $this->col('Comisión', true), $this->col('IVA comisión', true), $this->col('% efectivo', true)],
                'filas'    => $liqs->map(fn ($l) => [
                    $this->f($l->fecha_deposito), $l->numero_lote, $l->procesador ?? '—', $this->m($l->valor_bruto),
                    $this->m($l->comision_datafast), $this->m($l->iva_comision),
                    (float) $l->valor_bruto > 0 ? number_format((float) $l->comision_datafast / (float) $l->valor_bruto * 100, 2) . '%' : '—',
                ])->all(),
                'totales'  => ['TOTAL', '', '', $suma('valor_bruto'), $suma('comision_datafast'), $suma('iva_comision'), ''],
            ]],
        ];
    }

    // ── Retenciones de tarjetas por período (F104 / ATS) ──────────────────────
    private function retencionesTarjetas(Request $r, int $empresaId): array
    {
        $liqs = $this->liquidacionesPeriodo($r, $empresaId);

        return [
            'subtitulo' => 'Retenciones que el emisor aplica al comercio: crédito tributario del F104 y el ATS. ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('Depósito'), $this->col('N° lote'), $this->col('Procesador'), $this->col('Bruto', true),
                               $this->col('Retención IVA', true), $this->col('Retención IR', true), $this->col('Total retenido', true)],
                'filas'    => $liqs->map(fn ($l) => [
                    $this->f($l->fecha_deposito), $l->numero_lote, $l->procesador ?? '—', $this->m($l->valor_bruto),
                    $this->m($l->retencion_iva), $this->m($l->retencion_ir), $this->m((float) $l->retencion_iva + (float) $l->retencion_ir),
                ])->all(),
                'totales'  => ['TOTAL', '', '', $this->m($liqs->sum('valor_bruto')), $this->m($liqs->sum('retencion_iva')),
                               $this->m($liqs->sum('retencion_ir')), $this->m($liqs->sum('retencion_iva') + $liqs->sum('retencion_ir'))],
            ]],
        ];
    }

    // ── 6. Saldos consolidados (todas las empresas) ───────────────────────────
    private function saldosConsolidados(): array
    {
        $cuentas = BancoCaja::with(['empresa:id,razon_social,ruc', 'cuenta:id,codigo'])
            ->where('estado', true)->orderBy('empresa_id')->orderBy('tipo')->orderBy('nombre')->get();

        $filas = $cuentas->map(fn ($b) => [
            $b->empresa?->razon_social, $b->empresa?->ruc, $b->tipo_label, $b->nombre,
            $b->cuenta?->codigo ?? '—', $this->m($b->saldo_actual),
        ])->all();

        $porTipo = $cuentas->groupBy('tipo_label')->map(fn ($g, $t) => [$t, (string) $g->count(), $this->m($g->sum('saldo_actual'))])->values()->all();

        return [
            'subtitulo' => 'Saldo de cada banco y caja de todas las empresas, a ' . $this->f(now()),
            'secciones' => [
                [
                    'titulo'   => 'Detalle por cuenta',
                    'columnas' => [$this->col('Empresa'), $this->col('RUC'), $this->col('Tipo'), $this->col('Cuenta'),
                                   $this->col('Cuenta contable'), $this->col('Saldo', true)],
                    'filas'    => $filas,
                    'totales'  => ['TOTAL', '', '', '', '', $this->m($cuentas->sum('saldo_actual'))],
                ],
                [
                    'titulo'   => 'Resumen por tipo',
                    'columnas' => [$this->col('Tipo'), $this->col('Cuentas', true), $this->col('Saldo', true)],
                    'filas'    => $porTipo,
                ],
            ],
        ];
    }

    // ── 7. Libro frente a mayor ───────────────────────────────────────────────
    private function libroVsMayor(Request $r, int $empresaId): array
    {
        $hasta = $r->input('fecha_hasta');
        $bancos = BancoCaja::with('cuenta:id,codigo,nombre')->where('empresa_id', $empresaId)->where('estado', true)
            ->whereNotNull('cuenta_id')->get()->groupBy('cuenta_id');

        $filas = [];
        $totL = $totM = 0.0;
        foreach ($bancos as $cuentaId => $grupo) {
            $mayor = (float) DB::table('asiento_detalles as d')
                ->join('asientos_contables as a', 'a.id', '=', 'd.asiento_id')
                ->where('a.empresa_id', $empresaId)->where('a.estado', 1)->where('d.cuenta_id', $cuentaId)
                ->when($hasta, fn ($q) => $q->where('a.fecha', '<=', $hasta))
                ->selectRaw('COALESCE(SUM(d.debe - d.haber), 0) as saldo')->value('saldo');
            $libro = (float) $grupo->sum('saldo_actual');
            $totL += $libro; $totM += $mayor;
            $filas[] = [
                $grupo->first()->cuenta?->codigo . ' ' . $grupo->first()->cuenta?->nombre,
                $grupo->pluck('nombre')->implode(', '),
                $this->m($libro), $this->m($mayor), $this->m($libro - $mayor),
            ];
        }

        return [
            'subtitulo' => 'Saldo de los libros de Bancos frente al mayor contable de su cuenta. Hasta ' . ($hasta ? $this->f($hasta) : $this->f(now())),
            'secciones' => [[
                'columnas' => [$this->col('Cuenta contable'), $this->col('Bancos / cajas'), $this->col('Saldo libro (Bancos)', true),
                               $this->col('Saldo mayor', true), $this->col('Diferencia', true)],
                'filas'    => $filas,
                'totales'  => ['TOTAL', '', $this->m($totL), $this->m($totM), $this->m($totL - $totM)],
            ], [
                'titulo'   => 'Nota',
                'columnas' => [$this->col('Detalle')],
                'filas'    => [['El saldo inicial de bancos y cajas aún no se registra con asiento de apertura (pendiente DEC-011), por lo que una diferencia igual al saldo inicial es esperada.']],
            ]],
        ];
    }

    // ── 8. Diferencias de conciliación ────────────────────────────────────────
    private function diferenciasConciliacion(Request $r, int $empresaId): array
    {
        $q = ConciliacionBancaria::with('bancoCaja')->where('empresa_id', $empresaId);
        if ($r->filled('banco_caja_id')) $q->where('banco_caja_id', $r->banco_caja_id);
        if ($r->filled('fecha_desde'))   $q->where('fecha_corte', '>=', $r->fecha_desde);
        if ($r->filled('fecha_hasta'))   $q->where('fecha_corte', '<=', $r->fecha_hasta);
        $concs = $q->orderBy('fecha_corte')->get();

        $filas = [];
        $total = 0.0;
        foreach ($concs as $c) {
            $aj = PartidaTransito::where('conciliacion_id', $c->id)->whereNotNull('asiento_generado_id')
                ->where('descripcion', 'like', 'Ajuste conciliación%')->get();
            $monto = $aj->sum(fn ($p) => $p->sentido === 'egreso' ? -(float) $p->monto : (float) $p->monto);
            if ($aj->isEmpty() && !$c->tieneDiferencia()) continue;
            $total += $monto;
            $filas[] = [
                $this->f($c->fecha_corte), $c->bancoCaja?->nombre, $c->estaConciliada() ? 'Conciliada' : 'Pendiente',
                $aj->isEmpty() ? $this->m($c->diferencia) : $this->m($monto),
                $aj->isEmpty() ? 'Sin ajustar' : ($monto > 0 ? 'Sobrante' : 'Faltante'),
                $c->observacion ?: '—',
            ];
        }

        return [
            'subtitulo' => 'Conciliaciones cerradas con diferencia y ajustadas a 6.01.14. ' . $this->rango($r),
            'secciones' => [[
                'columnas' => [$this->col('Corte'), $this->col('Banco'), $this->col('Estado'), $this->col('Diferencia', true),
                               $this->col('Tipo'), $this->col('Observación')],
                'filas'    => $filas,
                'totales'  => ['TOTAL AJUSTADO', '', '', $this->m($total), '', ''],
            ]],
        ];
    }
}
