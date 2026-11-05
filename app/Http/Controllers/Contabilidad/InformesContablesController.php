<?php

namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Informes de Contabilidad que faltaban (02-08 §2.8): retenciones emitidas y recibidas por período y análisis vertical
 * y horizontal. Se filtran por la empresa activa (RUC) y por centro de costo. El talón resumen del anexo (DEC-008) sigue
 * pendiente de definición.
 */
class InformesContablesController extends Controller
{
    private const INFORMES = [
        'retenciones'         => 'Retenciones emitidas y recibidas por período',
        'analisis-vertical'   => 'Análisis vertical',
        'analisis-horizontal' => 'Análisis horizontal',
    ];

    /** Clases del plan de cuentas por primer dígito: nombre y lado natural (1 = deudor, −1 = acreedor). */
    private const CLASES = [
        '1' => ['Activo', 1], '2' => ['Pasivo', -1], '3' => ['Patrimonio', -1],
        '4' => ['Ingresos', -1], '5' => ['Costos', 1], '6' => ['Gastos', 1],
    ];

    public function generar(Request $request, string $informe)
    {
        abort_unless(isset(self::INFORMES[$informe]), 404);
        ini_set('memory_limit', '1024M');

        $request->validate([
            'fecha_desde'     => 'required|date',
            'fecha_hasta'     => 'required|date|after_or_equal:fecha_desde',
            'centro_costo_id' => 'nullable|integer|exists_empresa:centros_costo',
            'formato'         => 'nullable|in:pdf,excel',
        ]);

        $empresaId = (int) session('empresa_activa_id');
        $datos = match ($informe) {
            'retenciones'         => $this->retenciones($request, $empresaId),
            'analisis-vertical'   => $this->vertical($request, $empresaId),
            'analisis-horizontal' => $this->horizontal($request, $empresaId),
        };
        $datos['titulo'] = self::INFORMES[$informe];
        $nombre = $informe . '-' . now()->format('Y-m-d');

        if ($request->input('formato') === 'excel') {
            return $this->excel($datos, $nombre);
        }

        return Pdf::loadView('pdf.banco-reporte-generico', ['datos' => $datos, 'empresa' => Empresa::find($empresaId)])
            ->setPaper('a4', 'landscape')->stream($nombre . '.pdf');
    }

    private function m($v): string { return number_format((float) $v, 2, '.', ','); }
    private function f($d): string { return $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : '—'; }
    private function col(string $t, bool $num = false): array { return ['t' => $t, 'n' => $num]; }
    private function rango(Request $r): string { return 'Del ' . $this->f($r->fecha_desde) . ' al ' . $this->f($r->fecha_hasta); }

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

    // ── Retenciones ───────────────────────────────────────────────────────────
    private function retenciones(Request $r, int $empresaId): array
    {
        // Emitidas: retenciones que hace la empresa al comprar (con su comprobante)
        $emitidas = DB::table('retenciones as t')->leftJoin('compras as c', 'c.id', '=', 't.compra_id')->leftJoin('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->where('t.empresa_id', $empresaId)->whereNotNull('t.compra_id')->where('t.estado', '!=', 'anulada')
            ->whereBetween('t.fecha_emision', [$r->fecha_desde, $r->fecha_hasta])
            ->orderBy('t.fecha_emision')->orderBy('t.id')
            ->get(['t.numero_completo', 't.fecha_emision', 'p.razon_social', 'c.num_documento', 't.total']);

        // Recibidas: las que hacen los clientes al pagar (PC-VEN-12), registradas en el cobro de la cuenta por cobrar
        $recibidas = DB::table('cuentas_cobrar_cobros as b')->join('cuentas_cobrar as x', 'x.id', '=', 'b.cuenta_cobrar_id')
            ->leftJoin('clientes as cl', 'cl.id', '=', 'x.cliente_id')->leftJoin('facturas as f', 'f.id', '=', 'x.factura_id')
            ->where('x.empresa_id', $empresaId)->whereRaw('(b.retencion_iva + b.retencion_ir) > 0')
            ->whereBetween('b.fecha', [$r->fecha_desde, $r->fecha_hasta])
            ->when($r->filled('centro_costo_id'), fn ($q) => $q->where('f.centro_costo_id', $r->centro_costo_id))
            ->orderBy('b.fecha')->orderBy('b.id')
            ->get(['b.num_retencion', 'b.fecha', 'cl.razon_social', 'f.numero_completo as factura', 'b.retencion_iva', 'b.retencion_ir']);

        return [
            'subtitulo' => $this->rango($r),
            'secciones' => [
                [
                    'titulo'   => 'Retenciones emitidas (compras)',
                    'columnas' => [$this->col('Comprobante'), $this->col('Fecha'), $this->col('Proveedor'), $this->col('Factura de compra'), $this->col('Total retenido', true)],
                    'filas'    => $emitidas->map(fn ($x) => [$x->numero_completo, $this->f($x->fecha_emision), $x->razon_social, $x->num_documento, $this->m($x->total)])->all(),
                    'totales'  => ['TOTAL', '', '', '', $this->m($emitidas->sum('total'))],
                ],
                [
                    'titulo'   => 'Retenciones recibidas (ventas) — crédito tributario',
                    'columnas' => [$this->col('Comprobante'), $this->col('Fecha'), $this->col('Cliente'), $this->col('Factura'), $this->col('Retención IVA', true), $this->col('Retención IR', true)],
                    'filas'    => $recibidas->map(fn ($x) => [$x->num_retencion, $this->f($x->fecha), $x->razon_social, $x->factura, $this->m($x->retencion_iva), $this->m($x->retencion_ir)])->all(),
                    'totales'  => ['TOTAL', '', '', '', $this->m($recibidas->sum('retencion_iva')), $this->m($recibidas->sum('retencion_ir'))],
                ],
            ],
        ];
    }

    // ── Saldos por cuenta ─────────────────────────────────────────────────────

    /**
     * Saldo (en su lado natural) de cada cuenta con movimiento. Las cuentas de balance (clases 1-3) se acumulan hasta la fecha
     * de corte; las de resultados (4-6) se toman solo del rango.
     *
     * @return array<string, array{nombre:string, saldo:float}> codigo => datos
     */
    private function saldos(int $empresaId, string $desde, string $hasta, ?int $centro): array
    {
        $filas = DB::table('asiento_detalles as d')->join('asientos_contables as a', 'a.id', '=', 'd.asiento_id')
            ->join('plan_cuentas as c', 'c.id', '=', 'd.cuenta_id')
            ->where('a.empresa_id', $empresaId)->where('a.estado', 1)->where('a.fecha', '<=', $hasta)
            ->when($centro, fn ($q) => $q->where('d.centro_costo_id', $centro))
            ->groupBy('c.codigo', 'c.nombre')
            ->selectRaw("c.codigo, c.nombre,
                SUM(d.debe - d.haber) FILTER (WHERE a.fecha <= ?) as acumulado,
                SUM(d.debe - d.haber) FILTER (WHERE a.fecha >= ? AND a.fecha <= ?) as periodo", [$hasta, $desde, $hasta])
            ->orderBy('c.codigo')->get();

        $out = [];
        foreach ($filas as $f) {
            $clase = substr((string) $f->codigo, 0, 1);
            if (!isset(self::CLASES[$clase])) continue;
            // Cierres anuales: el resultado ya cerrado no debe duplicarse en las cuentas de resultados del rango
            $neto = in_array($clase, ['1', '2', '3'], true) ? (float) $f->acumulado : (float) $f->periodo;
            $saldo = round($neto * self::CLASES[$clase][1], 2);
            if (abs($saldo) < 0.005) continue;
            $out[$f->codigo] = ['nombre' => $f->nombre, 'saldo' => $saldo];
        }

        return $out;
    }

    private function pct(float $parte, float $total): string
    {
        return $total != 0.0 ? number_format($parte / $total * 100, 2, '.', ',') . '%' : '—';
    }

    // ── Análisis vertical ─────────────────────────────────────────────────────
    private function vertical(Request $r, int $empresaId): array
    {
        $centro = $r->filled('centro_costo_id') ? (int) $r->centro_costo_id : null;
        $s = $this->saldos($empresaId, $r->fecha_desde, $r->fecha_hasta, $centro);

        // Total por clase: solo las cuentas hoja tienen movimiento, así que sumarlas da el total de la clase
        $suma = [];
        foreach ($s as $codigo => $d) {
            $suma[substr((string) $codigo, 0, 1)] = ($suma[substr((string) $codigo, 0, 1)] ?? 0) + $d['saldo'];
        }
        $baseBalance = (float) ($suma['1'] ?? 0);   // % sobre el total del activo
        $baseResult  = (float) ($suma['4'] ?? 0);   // % sobre los ingresos

        $balance = $resultados = [];
        foreach ($s as $codigo => $d) {
            $clase = substr((string) $codigo, 0, 1);
            if (in_array($clase, ['1', '2', '3'], true)) {
                $balance[] = [$codigo, $d['nombre'], self::CLASES[$clase][0], $this->m($d['saldo']), $this->pct($d['saldo'], $baseBalance)];
            } else {
                $resultados[] = [$codigo, $d['nombre'], self::CLASES[$clase][0], $this->m($d['saldo']), $this->pct($d['saldo'], $baseResult)];
            }
        }

        $cols = [$this->col('Cuenta'), $this->col('Nombre'), $this->col('Clase'), $this->col('Saldo', true), $this->col('% vertical', true)];

        return [
            'subtitulo' => $this->rango($r) . ($centro ? ' · centro de costo filtrado' : '') . ' · Situación sobre el total del activo; resultados sobre los ingresos',
            'secciones' => [
                ['titulo' => 'Estado de situación (acumulado al ' . $this->f($r->fecha_hasta) . ')', 'columnas' => $cols, 'filas' => $balance,
                 'totales' => ['', 'Total activo', '', $this->m($baseBalance), '100.00%']],
                ['titulo' => 'Estado de resultados del período', 'columnas' => $cols, 'filas' => $resultados,
                 'totales' => ['', 'Total ingresos', '', $this->m($baseResult), '100.00%']],
            ],
        ];
    }

    // ── Análisis horizontal ───────────────────────────────────────────────────
    private function horizontal(Request $r, int $empresaId): array
    {
        $centro = $r->filled('centro_costo_id') ? (int) $r->centro_costo_id : null;
        $actual = $this->saldos($empresaId, $r->fecha_desde, $r->fecha_hasta, $centro);
        $desdeAnt = \Carbon\Carbon::parse($r->fecha_desde)->subYear()->toDateString();
        $hastaAnt = \Carbon\Carbon::parse($r->fecha_hasta)->subYear()->toDateString();
        $anterior = $this->saldos($empresaId, $desdeAnt, $hastaAnt, $centro);

        $filas = [];
        foreach (array_unique(array_merge(array_keys($actual), array_keys($anterior))) as $codigo) {
            $a = (float) ($actual[$codigo]['saldo'] ?? 0);
            $b = (float) ($anterior[$codigo]['saldo'] ?? 0);
            $clase = substr((string) $codigo, 0, 1);
            $filas[] = [$codigo, $actual[$codigo]['nombre'] ?? $anterior[$codigo]['nombre'], self::CLASES[$clase][0],
                $this->m($a), $this->m($b), $this->m($a - $b), $b != 0.0 ? number_format(($a - $b) / abs($b) * 100, 2, '.', ',') . '%' : '—'];
        }
        usort($filas, fn ($x, $y) => strcmp($x[0], $y[0]));

        return [
            'subtitulo' => $this->rango($r) . ' frente a ' . $this->f($desdeAnt) . ' – ' . $this->f($hastaAnt) . ($centro ? ' · centro de costo filtrado' : ''),
            'secciones' => [[
                'titulo'   => 'Variación frente al mismo período del año anterior',
                'columnas' => [$this->col('Cuenta'), $this->col('Nombre'), $this->col('Clase'), $this->col('Período actual', true),
                               $this->col('Año anterior', true), $this->col('Variación $', true), $this->col('Variación %', true)],
                'filas'    => $filas,
            ]],
        ];
    }
}
