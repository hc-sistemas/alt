<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8px; color:#1F2D3D; padding:14px 18px; }
        .header { display:table; width:100%; margin-bottom:10px; padding-bottom:8px; border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell; vertical-align:middle; width:60%; }
        .h-right { display:table-cell; vertical-align:middle; text-align:right; width:40%; }
        .empresa { font-size:13px; font-weight:bold; color:#1F2D3D; }
        .titulo  { font-size:11px; font-weight:bold; color:#1F2D3D; margin-top:3px; }
        .sub     { font-size:7px; color:#6b7280; margin-top:2px; }
        table { width:100%; border-collapse:collapse; margin-top:8px; }
        thead tr { background:#1F2D3D; }
        thead th { padding:5px 6px; text-align:left; font-size:7px; font-weight:bold; text-transform:uppercase; color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#f5f7fa; }
        tbody td { padding:3px 6px; border-bottom:1px solid #e5e7eb; font-size:7.5px; }
        tbody td.right { text-align:right; font-family:monospace; }
        .tipo-tag { display:inline-block; padding:1px 4px; border-radius:3px; font-size:6.5px; font-weight:bold; text-transform:uppercase; }
        .tipo-activo    { background:#dcfce7; color:#166534; }
        .tipo-pasivo    { background:#fee2e2; color:#991b1b; }
        .tipo-patrimonio{ background:#e0f2fe; color:#0c4a6e; }
        .tipo-ingreso   { background:#d1fae5; color:#065f46; }
        .tipo-gasto     { background:#fef3c7; color:#92400e; }
        .total-row td { background:#1F2D3D !important; color:white; font-weight:bold; padding:4px 6px; }
        .total-row td.right { text-align:right; font-family:monospace; }
    </style>
</head>
<body>
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa?->nombre ?? 'Altamira' }}</div>
        <div class="titulo">BALANCE DE COMPROBACIÓN</div>
        <div class="sub">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:8px; color:#6b7280;">RUC: {{ $empresa?->ruc ?? '—' }}</div>
        <div style="font-size:8px; color:#6b7280;">{{ $empresa?->direccion ?? '' }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Código</th>
            <th>Cuenta</th>
            <th>Tipo</th>
            <th class="right">Suma Debe</th>
            <th class="right">Suma Haber</th>
            <th class="right">Saldo Deudor</th>
            <th class="right">Saldo Acreedor</th>
        </tr>
    </thead>
    <tbody>
        @foreach($cuentas as $c)
        <tr>
            <td style="font-family:monospace; font-weight:bold; color:#1F2D3D;">{{ $c['codigo'] }}</td>
            <td>{{ $c['nombre'] }}</td>
            <td>
                <span class="tipo-tag tipo-{{ $c['tipo'] }}">{{ ucfirst($c['tipo']) }}</span>
            </td>
            <td class="right">${{ number_format($c['suma_debe'], 2) }}</td>
            <td class="right">${{ number_format($c['suma_haber'], 2) }}</td>
            <td class="right">{{ $c['saldo_deudor'] > 0 ? '$' . number_format($c['saldo_deudor'], 2) : '' }}</td>
            <td class="right">{{ $c['saldo_acreedor'] > 0 ? '$' . number_format($c['saldo_acreedor'], 2) : '' }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr class="total-row">
            <td colspan="3">TOTALES</td>
            <td class="right">${{ number_format($totales['debe'], 2) }}</td>
            <td class="right">${{ number_format($totales['haber'], 2) }}</td>
            <td class="right">${{ number_format($totales['deudor'], 2) }}</td>
            <td class="right">${{ number_format($totales['acreedor'], 2) }}</td>
        </tr>
    </tfoot>
</table>

<div style="margin-top:12px; font-size:7px; color:#9ca3af; text-align:right;">
    {{ $cuentas->count() }} cuenta(s) con movimientos
</div>

<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
