<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9px;color:#1a1a1a;padding:16px 20px; }
        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:2px solid #2C3E50; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#1A3A5C; }
        .titulo  { font-size:11px;font-weight:bold;color:#1a1a1a;margin-top:3px; }
        .sub     { font-size:8px;color:#555;margin-top:2px; }
        .resumen { display:table;width:100%;margin-bottom:12px; }
        .res-item { display:table-cell;text-align:center;padding:7px;
                    border:1px solid #CCC; }
        .res-item:not(:first-child) { border-left:none; }
        .res-label { font-size:7px;font-weight:bold;
                     text-transform:uppercase;color:#666; }
        .res-valor { font-size:13px;font-weight:bold;
                     font-family:monospace;margin-top:2px; }
        table { width:100%;border-collapse:collapse; }
        thead tr { background:#2C3E50; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F8F9FA; }
        tbody td { padding:5px 7px;border-bottom:1px solid #E8E8E8;
                   font-size:8.5px; }
        tbody td.right { text-align:right;font-family:monospace; }
        .dif-ok  { color:#2D6A4F;font-weight:bold; }
        .dif-err { color:#7B2D2D;font-weight:bold; }
        .b-ab { background:#D1FAE5;color:#065F46;padding:1px 5px;
                border-radius:4px;font-size:7px;font-weight:bold; }
        .b-ce { background:#E5E7EB;color:#374151;padding:1px 5px;
                border-radius:4px;font-size:7px;font-weight:bold; }
        .fila-total { background:#2C3E50!important; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         padding:7px!important; }
        .footer { margin-top:10px;padding-top:8px;border-top:1px solid #CCC;
                  display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#888; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#888; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Reporte de Caja — {{ $cajaNombre }}</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} · Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold">{{ $cierres->count() }} cierres</div>
        <div class="sub">{{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Total Facturado</div>
        <div class="res-valor" style="color:#1A3A5C">${{ number_format($cierres->sum('total_facturado'), 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Cobrado</div>
        <div class="res-valor" style="color:#2D6A4F">${{ number_format($cierres->sum('total_cobrado'), 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Efectivo</div>
        <div class="res-valor" style="color:#374151">${{ number_format($cierres->sum('total_efectivo'), 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Tarjeta</div>
        <div class="res-valor" style="color:#374151">${{ number_format($cierres->sum('total_tarjeta'), 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Diferencias</div>
        @php $totalDif = $cierres->sum('diferencia'); @endphp
        <div class="res-valor {{ $totalDif == 0 ? 'dif-ok' : 'dif-err' }}">${{ number_format(abs($totalDif), 2) }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:8%">Fecha</th>
            <th style="width:15%">Caja</th>
            <th style="width:8%">Apertura</th>
            <th style="width:8%">Cierre</th>
            <th class="right" style="width:11%">Monto Ini.</th>
            <th class="right" style="width:11%">Facturado</th>
            <th class="right" style="width:11%">Cobrado</th>
            <th class="right" style="width:10%">Efectivo</th>
            <th class="right" style="width:10%">Diferencia</th>
            <th style="width:8%">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($cierres as $c)
        <tr>
            <td>{{ \Carbon\Carbon::parse($c->fecha)->format('d/m/Y') }}</td>
            <td style="font-weight:600;font-size:8px">{{ $c->bancoCaja?->nombre ?? '—' }}</td>
            <td style="font-size:8px">
                {{ $c->hora_apertura ? \Carbon\Carbon::parse($c->hora_apertura)->format('H:i') : '—' }}
            </td>
            <td style="font-size:8px">
                {{ $c->hora_cierre ? \Carbon\Carbon::parse($c->hora_cierre)->format('H:i') : '—' }}
            </td>
            <td class="right">${{ number_format($c->monto_inicial, 2) }}</td>
            <td class="right">${{ number_format($c->total_facturado, 2) }}</td>
            <td class="right">${{ number_format($c->total_cobrado, 2) }}</td>
            <td class="right">${{ number_format($c->total_efectivo, 2) }}</td>
            <td class="right {{ abs($c->diferencia) > 0.01 ? 'dif-err' : 'dif-ok' }}">
                {{ abs($c->diferencia) > 0.01 ? '$'.number_format(abs($c->diferencia), 2) : '✓' }}
            </td>
            <td style="text-align:center">
                <span class="{{ $c->estado === 'abierto' ? 'b-ab' : 'b-ce' }}">{{ ucfirst($c->estado) }}</span>
            </td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="4">TOTALES</td>
            <td class="right">${{ number_format($cierres->sum('monto_inicial'), 2) }}</td>
            <td class="right">${{ number_format($cierres->sum('total_facturado'), 2) }}</td>
            <td class="right">${{ number_format($cierres->sum('total_cobrado'), 2) }}</td>
            <td class="right">${{ number_format($cierres->sum('total_efectivo'), 2) }}</td>
            <td class="right">${{ number_format($cierres->sum('diferencia'), 2) }}</td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira · Reporte de Caja · {{ $cajaNombre }}</div>
    <div class="f-r">{{ now()->format('d/m/Y H:i:s') }}</div>
</div>

</body>
</html>
