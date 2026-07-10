<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:10px;color:#1A1A2E;padding:20px 24px; }
        .header { padding-bottom:12px;margin-bottom:14px;border-bottom:2px solid #1F2D3D; }
        .empresa { font-size:14px;font-weight:bold;color:#1A1A2E; }
        .titulo  { font-size:13px;font-weight:bold;color:#1A1A2E;margin-top:4px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }

        .cuenta-box  { background:#F5F7FA;border:1px solid #D8DCE6;
                       border-left:4px solid #2C5F8A;
                       padding:10px 14px;margin-bottom:14px; }
        .cuenta-cod  { font-family:monospace;font-size:13px;font-weight:bold;color:#2C5F8A; }
        .cuenta-nom  { font-size:11px;font-weight:600;color:#1A1A2E;margin-top:2px; }
        .cuenta-tipo { font-size:9px;color:#555770;margin-top:2px; }

        .resumen   { display:table;width:100%;margin-bottom:14px;
                     border:1px solid #D8DCE6; }
        .res-card  { display:table-cell;text-align:center;padding:8px;
                     border-right:1px solid #D8DCE6; }
        .res-card:last-child { border-right:none; }
        .res-label { font-size:8px;font-weight:bold;text-transform:uppercase;
                     color:#555770; }
        .res-value { font-size:14px;font-weight:bold;font-family:monospace;
                     margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse;margin-bottom:10px; }
        thead tr { background:#1F2D3D; }
        thead th { padding:7px 8px;text-align:left;font-size:8.5px;font-weight:bold;
                   text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:6px 8px;border-bottom:1px solid #D8DCE6;
                   font-size:9px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }
        .num   { font-family:monospace;font-weight:bold;color:#2C5F8A; }
        .muted { color:#AAAAAA; }

        .total-row td { background:#1F2D3D!important;color:white!important;
                        font-weight:bold!important;font-size:10px!important;
                        padding:8px!important; }

        .saldo-final { text-align:right;font-size:10px;font-weight:bold;
                       padding:6px 8px;background:#F5F7FA;
                       border:1px solid #D8DCE6;color:#1A1A2E; }

        .footer { margin-top:16px;padding-top:8px;border-top:1px solid #D8DCE6;
                  font-size:8px;color:#555770;text-align:right; }
    </style>
</head>
<body>

<div class="header">
    <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}</div>
    <div class="titulo">MAYOR CONTABLE</div>
    <div class="sub">
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
        <div class="res-label">Total DEBE</div>
        <div class="res-value">${{ number_format($totalDebe, 2) }}</div>
    </div>
    <div class="res-card">
        <div class="res-label">Total HABER</div>
        <div class="res-value">${{ number_format($totalHaber, 2) }}</div>
    </div>
    <div class="res-card">
        <div class="res-label">Saldo</div>
        <div class="res-value">
            ${{ number_format(abs($saldo), 2) }} {{ $saldo >= 0 ? 'D' : 'H' }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:12%">Fecha</th>
            <th style="width:14%">Asiento</th>
            <th style="width:42%">Descripción</th>
            <th class="right" style="width:16%">DEBE</th>
            <th class="right" style="width:16%">HABER</th>
        </tr>
    </thead>
    <tbody>
        @forelse($detalles as $d)
        <tr>
            <td>{{ $d->asiento?->fecha?->format('d/m/Y') ?? '—' }}</td>
            <td class="num">{{ $d->asiento?->numero ?? '—' }}</td>
            <td style="color:#555770">
                {{ Str::limit($d->descripcion ?? $d->asiento?->concepto ?? '—', 50) }}
            </td>
            <td class="right">
                @if($d->debe > 0)
                    <span style="font-weight:bold">${{ number_format($d->debe, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="right">
                @if($d->haber > 0)
                    <span style="font-weight:bold">${{ number_format($d->haber, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;padding:16px;color:#555770">Sin movimientos</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3">TOTALES</td>
            <td class="right">${{ number_format($totalDebe, 2) }}</td>
            <td class="right">${{ number_format($totalHaber, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="saldo-final">
    Saldo final:
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
