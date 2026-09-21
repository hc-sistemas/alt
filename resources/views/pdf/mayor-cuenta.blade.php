<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:10px;color:#1A1A2E;padding:20px 24px; }
        .header { border-left:4px solid #2C5F8A;
                  padding:10px 14px;margin-bottom:14px; }
        .empresa { font-size:14px;font-weight:bold; }
        .titulo  { font-size:12px;font-weight:bold;margin-top:2px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }
        .periodo { font-size:9px;font-weight:bold;color:#2C5F8A;margin-top:3px; }

        .cuenta-box { border:1px solid #D8DCE6;padding:8px 12px;margin-bottom:10px; }
        .cuenta-cod { font-family:monospace;font-size:11px;font-weight:bold;color:#2C5F8A; }
        .cuenta-nom { font-size:12px;font-weight:bold;margin-top:2px; }
        .cuenta-tipo{ font-size:8px;color:#555770;margin-top:2px; }

        .resumen  { display:table;width:100%;border-spacing:6px 0;margin-bottom:10px; }
        .res-card { display:table-cell;width:25%;padding:6px 8px;
                    border:1px solid #D8DCE6;background:#F5F7FA; }
        .res-label{ font-size:7.5px;text-transform:uppercase;color:#555770; }
        .res-value{ font-size:11px;font-weight:bold;font-family:monospace;
                    margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse;margin-bottom:10px; }
        thead tr { background:#1F2D3D; }
        thead th { padding:7px 8px;text-align:left;font-size:8.5px;font-weight:bold;
                   text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:5px 8px;border-bottom:1px solid #D8DCE6;
                   font-size:9px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }
        tbody td.num   { font-family:monospace;font-size:8.5px; }
        .muted { color:#9ca3af; }

        .fila-anterior td { background:#EEF2F7!important;font-weight:bold;font-style:italic; }
        .total-row td { background:#1F2D3D!important;color:white!important;
                        font-weight:bold!important;font-size:10px!important;
                        padding:8px!important; }
        .total-row td.right { text-align:right;font-family:monospace; }

        .saldo-final { margin-top:8px;padding:8px 10px;background:#F5F7FA;
                       border:1px solid #D8DCE6;color:#1A1A2E;font-size:10px; }
        .footer { margin-top:10px;font-size:8px;color:#555770;text-align:right; }
    </style>
</head>
<body>

<div class="header">
    <div class="empresa">{{ $empresa?->nombre_comercial ?? $empresa?->razon_social ?? 'Altamira Light &amp; Sound' }}</div>
    <div class="titulo">MAYOR CONTABLE</div>
    <div class="periodo">{{ $periodo ?? '' }}</div>
    <div class="sub">
        RUC: {{ $empresa?->ruc ?? '—' }} &middot;
        Generado: {{ now()->format('d/m/Y H:i') }} &middot; Usuario: {{ auth()->user()?->email }}
    </div>
</div>

<div class="cuenta-box">
    <div class="cuenta-cod">{{ $cuenta->codigo }}</div>
    <div class="cuenta-nom">{{ $cuenta->nombre }}</div>
    <div class="cuenta-tipo">Tipo: {{ ucfirst($cuenta->tipo) }}</div>
</div>

<div class="resumen">
    <div class="res-card">
        <div class="res-label">Saldo anterior</div>
        <div class="res-value">
            ${{ number_format(abs($saldoAnterior), 2) }} {{ $saldoAnterior >= 0 ? 'D' : 'H' }}
        </div>
    </div>
    <div class="res-card">
        <div class="res-label">Total DEBE</div>
        <div class="res-value">${{ number_format($totalDebe, 2) }}</div>
    </div>
    <div class="res-card">
        <div class="res-label">Total HABER</div>
        <div class="res-value">${{ number_format($totalHaber, 2) }}</div>
    </div>
    <div class="res-card">
        <div class="res-label">Saldo final</div>
        <div class="res-value">
            ${{ number_format(abs($saldo), 2) }} {{ $saldo >= 0 ? 'D' : 'H' }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:11%">Fecha</th>
            <th style="width:13%">Asiento</th>
            <th style="width:36%">Descripción</th>
            <th class="right" style="width:13%">DEBE</th>
            <th class="right" style="width:13%">HABER</th>
            <th class="right" style="width:14%">SALDO</th>
        </tr>
    </thead>
    <tbody>
        <tr class="fila-anterior">
            <td colspan="5">SALDO ANTERIOR</td>
            <td class="right">
                ${{ number_format(abs($saldoAnterior), 2) }} {{ $saldoAnterior >= 0 ? 'D' : 'H' }}
            </td>
        </tr>
        @forelse($lineas as $l)
        <tr>
            <td>{{ $l['fecha'] }}</td>
            <td class="num">{{ $l['numero'] }}</td>
            <td style="color:#555770">{{ Str::limit($l['descripcion'], 48) }}</td>
            <td class="right">
                @if($l['debe'] > 0)
                    <span style="font-weight:bold">${{ number_format($l['debe'], 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="right">
                @if($l['haber'] > 0)
                    <span style="font-weight:bold">${{ number_format($l['haber'], 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="right">
                {{ number_format(abs($l['saldo']), 2) }} {{ $l['saldo'] >= 0 ? 'D' : 'H' }}
            </td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center;padding:16px;color:#555770">Sin movimientos en el período</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3">TOTALES DEL PERÍODO</td>
            <td class="right">${{ number_format($totalDebe, 2) }}</td>
            <td class="right">${{ number_format($totalHaber, 2) }}</td>
            <td class="right">
                {{ number_format(abs($saldo), 2) }} {{ $saldo >= 0 ? 'D' : 'H' }}
            </td>
        </tr>
    </tbody>
</table>

<div class="saldo-final">
    Saldo anterior <strong>${{ number_format(abs($saldoAnterior), 2) }}</strong>
    {{ $saldoAnterior >= 0 ? 'deudor' : 'acreedor' }}
    &nbsp;+&nbsp; movimientos del período
    (<strong>${{ number_format($totalDebe, 2) }}</strong> debe /
     <strong>${{ number_format($totalHaber, 2) }}</strong> haber)
    &nbsp;=&nbsp; saldo final
    <strong>${{ number_format(abs($saldo), 2) }} {{ $saldo >= 0 ? 'DEUDOR' : 'ACREEDOR' }}</strong>
</div>

<div class="footer">
    ERP Altamira &middot; Mayor Contable &middot; {{ $cuenta->codigo }} &mdash; {{ $cuenta->nombre }}
</div>

<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
