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
        table { width:100%; border-collapse:collapse; margin-top:4px; }
        thead th { background:#e5e7eb; padding:3px 6px; font-size:7.5px; font-weight:bold; text-align:left; border-bottom:1px solid #9ca3af; }
        thead th.right { text-align:right; }
        tbody td { padding:3px 6px; border-bottom:1px solid #e5e7eb; font-size:8px; }
        tbody td.right { text-align:right; font-family:monospace; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        .total-row td { background:#1F2D3D; color:white; font-weight:bold; padding:4px 6px; }
        .total-row td.right { text-align:right; font-family:monospace; }
        .sin-registros {
            border:2px dashed #d1d5db; border-radius:8px;
            padding:24px; text-align:center; margin:16px 0; color:#9ca3af;
        }
        .resumen { display:table; width:100%; margin-top:14px; border:2px solid #1F2D3D; border-radius:6px; padding:8px 12px; }
        .res-col { display:table-cell; width:33%; padding:3px 6px; }
        .res-col.center { text-align:center; }
        .res-col.right  { text-align:right; }
        .lbl { font-size:7px; color:#6b7280; }
        .val { font-weight:bold; font-size:10px; font-family:monospace; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre }}</div>
        <div class="titulo">ANEXO ICE — Impuesto a los Consumos Especiales</div>
        <div class="sub">Período: {{ $nombreMes }} {{ $anio }} · RUC: {{ $empresa->ruc }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:7px; color:#9ca3af;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

@if($sinRegistros)
    <div class="sin-registros">
        <div style="font-size:22px; margin-bottom:6px;">📦</div>
        <div style="font-size:11px; font-weight:bold; color:#4b5563; margin-bottom:4px;">Sin registros de ICE</div>
        <div style="font-size:8px;">
            No se encontraron compras ni ventas con ICE en el período {{ $nombreMes }} {{ $anio }}.
        </div>
        <div style="font-size:7.5px; margin-top:4px; color:#6b7280;">
            Si la empresa no comercializa productos gravados con ICE, este anexo no es exigible.
        </div>
    </div>
@else

    {{-- COMPRAS CON ICE --}}
    @if($comprasIce->count() > 0)
        <div class="seccion-header">Compras con ICE — {{ $comprasIce->count() }} comprobante(s)</div>
        <table>
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Fecha</th>
                    <th>Proveedor</th>
                    <th class="right">Base Imponible</th>
                    <th class="right">ICE Pagado</th>
                    <th class="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($comprasIce as $c)
                <tr>
                    <td>{{ $c->num_documento }}</td>
                    <td>{{ \Carbon\Carbon::parse($c->fecha_emision)->format('d/m/Y') }}</td>
                    <td>{{ $c->proveedor }}</td>
                    <td class="right">${{ number_format((float)$c->base_imponible, 2) }}</td>
                    <td class="right" style="font-weight:bold;">${{ number_format((float)$c->total_ice, 2) }}</td>
                    <td class="right">${{ number_format((float)$c->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="4">TOTAL ICE COMPRAS</td>
                    <td class="right">${{ number_format($totalIceCompras, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- VENTAS CON ICE --}}
    @if($ventasIce->count() > 0)
        <div class="seccion-header">Ventas con ICE — {{ $ventasIce->count() }} comprobante(s)</div>
        <table>
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th class="right">ICE Cobrado</th>
                    <th class="right">Total Factura</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ventasIce as $v)
                <tr>
                    <td>{{ $v->numero_completo }}</td>
                    <td>{{ \Carbon\Carbon::parse($v->fecha_emision)->format('d/m/Y') }}</td>
                    <td>{{ $v->cliente }}</td>
                    <td class="right" style="font-weight:bold;">${{ number_format((float)$v->total_ice, 2) }}</td>
                    <td class="right">${{ number_format((float)$v->total, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">TOTAL ICE VENTAS</td>
                    <td class="right">${{ number_format($totalIceVentas, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    {{-- RESUMEN --}}
    <div class="resumen">
        <div class="res-col">
            <div class="lbl">ICE en Compras (crédito tributario)</div>
            <div class="val">${{ number_format($totalIceCompras, 2) }}</div>
        </div>
        <div class="res-col center">
            <div class="lbl">ICE en Ventas (ICE causado)</div>
            <div class="val">${{ number_format($totalIceVentas, 2) }}</div>
        </div>
        <div class="res-col right">
            @php $saldo = round($totalIceVentas - $totalIceCompras, 2); @endphp
            <div class="lbl">Saldo ICE a pagar / crédito</div>
            <div class="val" style="color:{{ $saldo > 0 ? '#dc2626' : '#059669' }};">
                {{ $saldo >= 0 ? '' : '-' }}${{ number_format(abs($saldo), 2) }}
                {{ $saldo > 0 ? '(a pagar)' : '(crédito)' }}
            </div>
        </div>
    </div>

@endif


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
