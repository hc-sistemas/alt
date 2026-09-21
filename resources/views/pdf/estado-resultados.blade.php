<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:9px; color:#1F2D3D; padding:18px 22px; }
        .header { display:table; width:100%; margin-bottom:12px; padding-bottom:10px; border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell; vertical-align:middle; width:62%; }
        .h-right { display:table-cell; vertical-align:middle; text-align:right; width:38%; }
        .empresa { font-size:14px; font-weight:bold; }
        .titulo  { font-size:12px; font-weight:bold; margin-top:3px; }
        .periodo { font-size:10px; font-weight:bold; color:#1F2D3D; margin-top:2px; }
        .sub     { font-size:7.5px; color:#6b7280; margin-top:2px; }

        table { width:100%; border-collapse:collapse; }
        td { padding:3px 6px; font-size:8.5px; }
        td.right { text-align:right; font-family:monospace; }
        .cod { font-family:monospace; font-size:7px; color:#6b7280; }

        .seccion td { background:#1F2D3D; color:white; font-weight:bold; font-size:8px;
                      text-transform:uppercase; padding:5px 6px; }
        .detalle td { border-bottom:1px solid #eef1f5; }
        .detalle:nth-child(even) td { background:#fafbfc; }

        .subtotal td { border-top:1px solid #1F2D3D; border-bottom:1px solid #1F2D3D;
                       font-weight:bold; padding:5px 6px; background:#f0f3f7; }
        .resultado td { background:#2C5F8A; color:white; font-weight:bold;
                        font-size:10px; padding:7px 6px; }
        .negativo { color:#dc2626; }
        .nota { font-size:7px; color:#6b7280; font-style:italic; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa?->nombre_comercial ?? $empresa?->razon_social ?? 'Altamira' }}</div>
        <div class="titulo">ESTADO DE RESULTADOS INTEGRAL</div>
        <div class="periodo">{{ $periodo ?? '' }}</div>
        <div class="sub">Generado: {{ now()->format('d/m/Y H:i') }} &middot; Expresado en USD</div>
    </div>
    <div class="h-right">
        <div style="font-size:8px; color:#6b7280;">RUC: {{ $empresa?->ruc ?? '—' }}</div>
    </div>
</div>

@php
    $linea = function ($c) {
        return '<tr class="detalle"><td><span class="cod">' . e($c['codigo']) . '</span> ' . e($c['nombre']) . '</td>'
             . '<td class="right' . ($c['saldo'] < 0 ? ' negativo' : '') . '">$'
             . number_format($c['saldo'], 2) . '</td></tr>';
    };
@endphp

<table>
    {{-- ── INGRESOS OPERACIONALES ─────────────────────────────────────── --}}
    <tr class="seccion"><td>Ingresos de actividades ordinarias</td><td class="right">USD</td></tr>
    @forelse($ingresos as $c)
        {!! $linea($c) !!}
    @empty
        <tr class="detalle"><td colspan="2" style="color:#9ca3af">Sin movimientos</td></tr>
    @endforelse
    <tr class="subtotal">
        <td>TOTAL INGRESOS OPERACIONALES</td>
        <td class="right">${{ number_format($totales['ingresos'], 2) }}</td>
    </tr>

    {{-- ── COSTO DE VENTAS ────────────────────────────────────────────── --}}
    <tr class="seccion"><td>(−) Costo de ventas</td><td class="right">USD</td></tr>
    @forelse($costos as $c)
        {!! $linea($c) !!}
    @empty
        <tr class="detalle">
            <td colspan="2" style="color:#9ca3af">
                Sin movimientos
                <span class="nota">— si hubo ventas de mercadería, revise que las facturas estén generando su asiento de costo de ventas.</span>
            </td>
        </tr>
    @endforelse
    <tr class="subtotal">
        <td>TOTAL COSTO DE VENTAS</td>
        <td class="right">${{ number_format($totales['costos'], 2) }}</td>
    </tr>

    <tr class="subtotal">
        <td>UTILIDAD BRUTA EN VENTAS</td>
        <td class="right {{ $totales['utilidad_bruta'] < 0 ? 'negativo' : '' }}">
            ${{ number_format($totales['utilidad_bruta'], 2) }}
        </td>
    </tr>

    {{-- ── GASTOS OPERATIVOS ──────────────────────────────────────────── --}}
    <tr class="seccion"><td>(−) Gastos operativos</td><td class="right">USD</td></tr>
    @forelse($gastosOp as $c)
        {!! $linea($c) !!}
    @empty
        <tr class="detalle"><td colspan="2" style="color:#9ca3af">Sin movimientos</td></tr>
    @endforelse
    <tr class="subtotal">
        <td>TOTAL GASTOS OPERATIVOS</td>
        <td class="right">${{ number_format($totales['gastos_op'], 2) }}</td>
    </tr>

    <tr class="subtotal">
        <td>UTILIDAD OPERACIONAL</td>
        <td class="right {{ $totales['utilidad_oper'] < 0 ? 'negativo' : '' }}">
            ${{ number_format($totales['utilidad_oper'], 2) }}
        </td>
    </tr>

    {{-- ── NO OPERACIONALES ───────────────────────────────────────────── --}}
    @if($otrosIng->isNotEmpty() || $gastosFin->isNotEmpty() || $otrosGas->isNotEmpty())
        <tr class="seccion"><td>Resultados no operacionales</td><td class="right">USD</td></tr>

        @foreach($otrosIng as $c) {!! $linea($c) !!} @endforeach
        @if($otrosIng->isNotEmpty())
            <tr class="subtotal"><td>(+) Otros ingresos</td>
                <td class="right">${{ number_format($totales['otros_ingresos'], 2) }}</td></tr>
        @endif

        @foreach($gastosFin as $c) {!! $linea($c) !!} @endforeach
        @if($gastosFin->isNotEmpty())
            <tr class="subtotal"><td>(−) Gastos financieros</td>
                <td class="right">${{ number_format($totales['gastos_fin'], 2) }}</td></tr>
        @endif

        @foreach($otrosGas as $c) {!! $linea($c) !!} @endforeach
        @if($otrosGas->isNotEmpty())
            <tr class="subtotal"><td>(−) Otros gastos y no deducibles</td>
                <td class="right">${{ number_format($totales['otros_gastos'], 2) }}</td></tr>
        @endif
    @endif

    {{-- ── CONCILIACIÓN TRIBUTARIA ────────────────────────────────────── --}}
    <tr class="subtotal">
        <td>UTILIDAD ANTES DE PARTICIPACIÓN E IMPUESTOS</td>
        <td class="right {{ $totales['utilidad_antes'] < 0 ? 'negativo' : '' }}">
            ${{ number_format($totales['utilidad_antes'], 2) }}
        </td>
    </tr>
    <tr class="detalle">
        <td>(−) Participación trabajadores {{ rtrim(rtrim(number_format($totales['pct_participacion'], 2), '0'), '.') }}%</td>
        <td class="right">${{ number_format($totales['participacion'], 2) }}</td>
    </tr>
    <tr class="subtotal">
        <td>BASE IMPONIBLE</td>
        <td class="right {{ $totales['base_imponible'] < 0 ? 'negativo' : '' }}">
            ${{ number_format($totales['base_imponible'], 2) }}
        </td>
    </tr>
    <tr class="detalle">
        <td>(−) Impuesto a la renta {{ rtrim(rtrim(number_format($totales['pct_impuesto'], 2), '0'), '.') }}%</td>
        <td class="right">${{ number_format($totales['impuesto_renta'], 2) }}</td>
    </tr>

    <tr class="resultado">
        <td>{{ $totales['utilidad_neta'] >= 0 ? 'UTILIDAD NETA DEL PERÍODO' : 'PÉRDIDA NETA DEL PERÍODO' }}</td>
        <td class="right">${{ number_format(abs($totales['utilidad_neta']), 2) }}</td>
    </tr>
</table>

<div style="margin-top:8px;" class="nota">
    La participación a trabajadores ({{ rtrim(rtrim(number_format($totales['pct_participacion'], 2), '0'), '.') }}%)
    y el impuesto a la renta ({{ rtrim(rtrim(number_format($totales['pct_impuesto'], 2), '0'), '.') }}%)
    son un cálculo referencial sobre la utilidad contable del período. No constituyen la
    conciliación tributaria definitiva, que requiere el ajuste por gastos no deducibles,
    amortización de pérdidas y demás partidas conciliatorias del formulario del SRI.
</div>

<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
