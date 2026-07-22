<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:9px; color:#1F2D3D; padding:16px 20px; }
        .header { display:table; width:100%; margin-bottom:12px; padding-bottom:10px; border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell; vertical-align:middle; width:65%; }
        .h-right { display:table-cell; vertical-align:middle; text-align:right; width:35%; }
        .empresa { font-size:14px; font-weight:bold; }
        .titulo  { font-size:12px; font-weight:bold; margin-top:3px; }
        .sub     { font-size:7.5px; color:#6b7280; margin-top:2px; }
        .seccion-header {
            background:#1F2D3D; color:white;
            padding:5px 8px; font-size:8px; font-weight:bold;
            text-transform:uppercase; margin-top:10px;
        }
        table { width:100%; border-collapse:collapse; }
        tbody td { padding:3px 6px; border-bottom:1px solid #e5e7eb; font-size:8px; }
        tbody td.right { text-align:right; font-family:monospace; font-weight:bold; }
        tbody td.neg   { text-align:right; font-family:monospace; font-weight:bold; color:#dc2626; }
        tbody td.pos   { text-align:right; font-family:monospace; font-weight:bold; color:#059669; }
        tbody tr:nth-child(even) { background:#f5f7fa; }
        .cod { font-family:monospace; font-size:7px; color:#6b7280; }
        .sub-total-row td {
            background:#e5e7eb; font-weight:bold;
            padding:4px 6px; font-size:8px; border-top:1px solid #9ca3af;
        }
        .sub-total-row td.right { text-align:right; font-family:monospace; }
        .total-row td {
            background:#1F2D3D; color:white; font-weight:bold;
            padding:5px 6px; font-size:9px;
        }
        .total-row td.right { text-align:right; font-family:monospace; }
        .total-row td.pos   { text-align:right; font-family:monospace; color:#86efac; }
        .total-row td.neg   { text-align:right; font-family:monospace; color:#fca5a5; }
        .vacio { padding:6px 8px; font-size:7.5px; color:#9ca3af; font-style:italic; }
        .cuadre { margin-top:14px; border:2px solid #1F2D3D; border-radius:6px; padding:8px 12px; }
        .cuadre-grid { display:table; width:100%; }
        .cuadre-col  { display:table-cell; width:33%; padding:3px 6px; font-size:8px; }
        .cuadre-col.center { text-align:center; }
        .cuadre-col.right  { text-align:right; }
        .lbl  { color:#6b7280; font-size:7px; }
        .val  { font-weight:bold; font-size:9px; font-family:monospace; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa?->nombre_comercial ?? $empresa?->razon_social ?? 'Altamira' }}</div>
        <div class="titulo">ESTADO DE FLUJO DE EFECTIVO — MÉTODO INDIRECTO</div>
        <div class="sub">Período: {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:8px; color:#6b7280;">RUC: {{ $empresa?->ruc ?? '—' }}</div>
        <div style="font-size:7px; color:#9ca3af; margin-top:2px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

{{-- ── I. ACTIVIDADES OPERATIVAS ── --}}
<div class="seccion-header">I. Actividades Operativas</div>

<table>
    <tbody>
        <tr>
            <td style="padding-left:10px;">
                Utilidad/(Pérdida) neta del período
                <span style="font-size:7px; color:#6b7280;">
                    (Ingresos ${{ number_format($resumen['total_ingresos'],2) }} − Gastos ${{ number_format($resumen['total_gastos'],2) }})
                </span>
            </td>
            <td class="{{ $resumen['utilidad'] >= 0 ? 'pos' : 'neg' }}">
                {{ $resumen['utilidad'] < 0 ? '-' : '' }}${{ number_format(abs($resumen['utilidad']),2) }}
            </td>
        </tr>
    </tbody>
</table>

@if($operativoActivo->count() > 0 || $operativoPasivo->count() > 0)
    <table style="margin-top:4px;">
        <thead>
            <tr>
                <td style="padding:3px 6px; font-size:7px; font-weight:bold; color:#6b7280; border-bottom:1px solid #d1d5db;">
                    Variaciones en capital de trabajo
                </td>
                <td style="width:90px; padding:3px 6px; font-size:7px; font-weight:bold; color:#6b7280; text-align:right; border-bottom:1px solid #d1d5db;">
                    Efecto en Caja
                </td>
            </tr>
        </thead>
        <tbody>
            @foreach($operativoActivo as $item)
            <tr>
                <td style="padding-left:16px;">
                    <span class="cod">{{ $item['codigo'] }}</span>
                    {{ $item['nombre'] }}
                    <span style="font-size:7px; color:#9ca3af;">
                        (${{ number_format($item['saldo_inicio'],2) }} → ${{ number_format($item['saldo_fin'],2) }})
                    </span>
                </td>
                <td class="{{ $item['efecto_caja'] >= 0 ? 'pos' : 'neg' }}">
                    {{ $item['efecto_caja'] < 0 ? '-' : '+' }}${{ number_format(abs($item['efecto_caja']),2) }}
                </td>
            </tr>
            @endforeach
            @foreach($operativoPasivo as $item)
            <tr>
                <td style="padding-left:16px;">
                    <span class="cod">{{ $item['codigo'] }}</span>
                    {{ $item['nombre'] }}
                    <span style="font-size:7px; color:#9ca3af;">
                        (${{ number_format($item['saldo_inicio'],2) }} → ${{ number_format($item['saldo_fin'],2) }})
                    </span>
                </td>
                <td class="{{ $item['efecto_caja'] >= 0 ? 'pos' : 'neg' }}">
                    {{ $item['efecto_caja'] < 0 ? '-' : '+' }}${{ number_format(abs($item['efecto_caja']),2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="vacio">Sin variaciones de capital de trabajo en el período.</p>
@endif

<table>
    <tbody>
        <tr class="total-row">
            <td>FLUJO NETO OPERATIVO</td>
            <td class="{{ $resumen['flujo_operativo'] >= 0 ? 'pos' : 'neg' }}">
                {{ $resumen['flujo_operativo'] < 0 ? '-' : '' }}${{ number_format(abs($resumen['flujo_operativo']),2) }}
            </td>
        </tr>
    </tbody>
</table>

{{-- ── II. ACTIVIDADES DE INVERSIÓN ── --}}
<div class="seccion-header">II. Actividades de Inversión (Activos No Corrientes)</div>

@if($inversion->count() > 0)
    <table>
        <tbody>
            @foreach($inversion as $item)
            <tr>
                <td style="padding-left:10px;">
                    <span class="cod">{{ $item['codigo'] }}</span>
                    {{ $item['nombre'] }}
                    <span style="font-size:7px; color:#9ca3af;">
                        (${{ number_format($item['saldo_inicio'],2) }} → ${{ number_format($item['saldo_fin'],2) }})
                    </span>
                </td>
                <td class="{{ $item['efecto_caja'] >= 0 ? 'pos' : 'neg' }}">
                    {{ $item['efecto_caja'] < 0 ? '-' : '+' }}${{ number_format(abs($item['efecto_caja']),2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="vacio">Sin movimientos de activos no corrientes en el período.</p>
@endif

<table>
    <tbody>
        <tr class="total-row">
            <td>FLUJO NETO DE INVERSIÓN</td>
            <td class="{{ $resumen['flujo_inversion'] >= 0 ? 'pos' : 'neg' }}">
                {{ $resumen['flujo_inversion'] < 0 ? '-' : '' }}${{ number_format(abs($resumen['flujo_inversion']),2) }}
            </td>
        </tr>
    </tbody>
</table>

{{-- ── III. ACTIVIDADES DE FINANCIAMIENTO ── --}}
<div class="seccion-header">III. Actividades de Financiamiento (Pasivos Largo Plazo)</div>

@if($financiamiento->count() > 0)
    <table>
        <tbody>
            @foreach($financiamiento as $item)
            <tr>
                <td style="padding-left:10px;">
                    <span class="cod">{{ $item['codigo'] }}</span>
                    {{ $item['nombre'] }}
                    <span style="font-size:7px; color:#9ca3af;">
                        (${{ number_format($item['saldo_inicio'],2) }} → ${{ number_format($item['saldo_fin'],2) }})
                    </span>
                </td>
                <td class="{{ $item['efecto_caja'] >= 0 ? 'pos' : 'neg' }}">
                    {{ $item['efecto_caja'] < 0 ? '-' : '+' }}${{ number_format(abs($item['efecto_caja']),2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="vacio">Sin variaciones de pasivos a largo plazo en el período.</p>
@endif

<table>
    <tbody>
        <tr class="total-row">
            <td>FLUJO NETO DE FINANCIAMIENTO</td>
            <td class="{{ $resumen['flujo_financiamiento'] >= 0 ? 'pos' : 'neg' }}">
                {{ $resumen['flujo_financiamiento'] < 0 ? '-' : '' }}${{ number_format(abs($resumen['flujo_financiamiento']),2) }}
            </td>
        </tr>
    </tbody>
</table>

{{-- ── IV. VARIACIÓN NETA ── --}}
<div class="cuadre" style="margin-top:12px;">
    <div class="cuadre-grid">
        <div class="cuadre-col">
            <div class="lbl">Saldo inicial de efectivo/bancos</div>
            <div class="val">${{ number_format($resumen['efectivo_inicio'],2) }}</div>
        </div>
        <div class="cuadre-col center">
            <div class="lbl">Variación neta del período</div>
            <div class="val" style="color:{{ $resumen['flujo_neto'] >= 0 ? '#059669' : '#dc2626' }};">
                {{ $resumen['flujo_neto'] >= 0 ? '+' : '-' }}${{ number_format(abs($resumen['flujo_neto']),2) }}
            </div>
            <div style="font-size:7px; color:#6b7280; margin-top:1px;">
                Operativo {{ $resumen['flujo_operativo'] >= 0 ? '+' : '' }}${{ number_format($resumen['flujo_operativo'],2) }}
                | Inversión {{ $resumen['flujo_inversion'] >= 0 ? '+' : '' }}${{ number_format($resumen['flujo_inversion'],2) }}
                | Financ. {{ $resumen['flujo_financiamiento'] >= 0 ? '+' : '' }}${{ number_format($resumen['flujo_financiamiento'],2) }}
            </div>
        </div>
        <div class="cuadre-col right">
            <div class="lbl">Saldo final de efectivo/bancos</div>
            <div class="val">${{ number_format($resumen['efectivo_fin'],2) }}</div>
            @php $diff = round($resumen['efectivo_fin'] - $resumen['efectivo_inicio'] - $resumen['flujo_neto'], 2); @endphp
            @if(abs($diff) < 0.05)
                <div style="font-size:7px; color:#059669; margin-top:2px;">✓ Cuadre verificado</div>
            @else
                <div style="font-size:7px; color:#dc2626; margin-top:2px;">⚠ Diferencia: ${{ number_format(abs($diff),2) }}</div>
            @endif
        </div>
    </div>
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
