<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:9px; color:#1F2D3D; padding:16px 20px; }
        .header { display:table; width:100%; margin-bottom:12px; padding-bottom:10px; border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell; vertical-align:middle; width:60%; }
        .h-right { display:table-cell; vertical-align:middle; text-align:right; width:40%; }
        .empresa { font-size:14px; font-weight:bold; }
        .titulo  { font-size:12px; font-weight:bold; margin-top:3px; }
        .sub     { font-size:7.5px; color:#6b7280; margin-top:2px; }
        .seccion-header { background:#1F2D3D; color:white; padding:5px 8px; font-size:8px; font-weight:bold; text-transform:uppercase; margin-top:10px; }
        table { width:100%; border-collapse:collapse; }
        tbody td { padding:3px 6px; border-bottom:1px solid #e5e7eb; font-size:8.5px; }
        tbody td.right { text-align:right; font-family:monospace; font-weight:bold; }
        tbody tr:nth-child(even) { background:#f5f7fa; }
        .subtotal td { border-top:1px solid #1F2D3D; font-weight:bold; padding:4px 6px; }
        .subtotal td.right { text-align:right; font-family:monospace; }
        .cod { font-family:monospace; font-size:7.5px; color:#6b7280; margin-right:4px; }
        .resultado { margin-top:14px; border:2px solid #1F2D3D; border-radius:6px; padding:10px 14px; display:table; width:100%; }
        .res-left  { display:table-cell; vertical-align:middle; font-size:10px; font-weight:bold; }
        .res-right { display:table-cell; text-align:right; font-size:13px; font-weight:bold; font-family:monospace; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa?->nombre ?? 'Altamira' }}</div>
        <div class="titulo">ESTADO DE RESULTADOS</div>
        <div class="sub">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:8px; color:#6b7280;">RUC: {{ $empresa?->ruc ?? '—' }}</div>
    </div>
</div>

{{-- INGRESOS --}}
<div class="seccion-header">INGRESOS</div>
<table>
    <tbody>
        @foreach($ingresos as $c)
        <tr>
            <td><span class="cod">{{ $c['codigo'] }}</span>{{ $c['nombre'] }}</td>
            <td class="right">${{ number_format($c['saldo'], 2) }}</td>
        </tr>
        @endforeach
        @if($ingresos->isEmpty())
        <tr><td colspan="2" style="text-align:center; color:#9ca3af; padding:8px;">Sin ingresos registrados</td></tr>
        @endif
    </tbody>
    <tfoot>
        <tr class="subtotal">
            <td>TOTAL INGRESOS</td>
            <td class="right">${{ number_format($totales['ingresos'], 2) }}</td>
        </tr>
    </tfoot>
</table>

{{-- GASTOS --}}
<div class="seccion-header" style="margin-top:14px;">GASTOS</div>
<table>
    <tbody>
        @foreach($gastos as $c)
        <tr>
            <td><span class="cod">{{ $c['codigo'] }}</span>{{ $c['nombre'] }}</td>
            <td class="right">${{ number_format($c['saldo'], 2) }}</td>
        </tr>
        @endforeach
        @if($gastos->isEmpty())
        <tr><td colspan="2" style="text-align:center; color:#9ca3af; padding:8px;">Sin gastos registrados</td></tr>
        @endif
    </tbody>
    <tfoot>
        <tr class="subtotal">
            <td>TOTAL GASTOS</td>
            <td class="right">${{ number_format($totales['gastos'], 2) }}</td>
        </tr>
    </tfoot>
</table>

{{-- RESULTADO --}}
<div class="resultado">
    <div class="res-left" style="color:{{ $utilidad >= 0 ? '#059669' : '#dc2626' }}">
        {{ $utilidad >= 0 ? 'UTILIDAD DEL PERÍODO' : 'PÉRDIDA DEL PERÍODO' }}
    </div>
    <div class="res-right" style="color:{{ $utilidad >= 0 ? '#059669' : '#dc2626' }}">
        ${{ number_format(abs($utilidad), 2) }}
    </div>
</div>

</body>
</html>
