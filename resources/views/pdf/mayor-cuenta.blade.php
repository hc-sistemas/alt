<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:10px;color:#1f2937;padding:20px 24px; }
        .header { padding-bottom:12px;margin-bottom:14px;border-bottom:3px solid #F59E0B; }
        .empresa { font-size:14px;font-weight:bold;color:#F59E0B; }
        .titulo  { font-size:13px;font-weight:bold;color:#1f2937;margin-top:4px; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }

        .cuenta-box  { background:#f9fafb;border-left:4px solid #F59E0B;
                       border-radius:0 6px 6px 0;padding:10px 14px;margin-bottom:14px; }
        .cuenta-cod  { font-family:monospace;font-size:13px;font-weight:bold;color:#F59E0B; }
        .cuenta-nom  { font-size:11px;font-weight:600;color:#1f2937;margin-top:2px; }
        .cuenta-tipo { font-size:9px;color:#6b7280;margin-top:2px; }

        .resumen   { display:table;width:100%;margin-bottom:14px; }
        .res-card  { display:table-cell;text-align:center;padding:8px;border-radius:6px; }
        .res-label { font-size:8px;font-weight:bold;text-transform:uppercase;color:#6b7280; }
        .res-value { font-size:14px;font-weight:bold;font-family:monospace;margin-top:2px; }

        table { width:100%;border-collapse:collapse;margin-bottom:10px; }
        thead tr { background:#1f2937; }
        thead th { padding:7px 8px;text-align:left;font-size:8.5px;font-weight:bold;
                   text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        tbody td { padding:6px 8px;border-bottom:1px solid #e5e7eb;font-size:9px; }
        tbody td.right { text-align:right;font-family:monospace; }
        .num   { font-family:monospace;font-weight:bold;color:#F59E0B; }
        .verde { color:#059669;font-weight:bold; }
        .rojo  { color:#dc2626;font-weight:bold; }
        .muted { color:#d1d5db; }
        .saldo-pos { color:#059669;font-weight:bold; }
        .saldo-neg { color:#dc2626;font-weight:bold; }

        .total-row td { background:#1f2937!important;color:white!important;
                        font-weight:bold!important;font-size:10px!important;padding:8px!important; }

        .footer { margin-top:16px;padding-top:8px;border-top:1px solid #e5e7eb;
                  font-size:8px;color:#9ca3af;text-align:right; }
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
    <div class="res-card" style="background:#d1fae5;border-left:3px solid #059669">
        <div class="res-label">Total DEBE</div>
        <div class="res-value" style="color:#059669">${{ number_format($totalDebe, 2) }}</div>
    </div>
    <div class="res-card" style="background:#fee2e2;border-left:3px solid #dc2626;margin:0 6px">
        <div class="res-label">Total HABER</div>
        <div class="res-value" style="color:#dc2626">${{ number_format($totalHaber, 2) }}</div>
    </div>
    <div class="res-card" style="background:#fef3c7;border-left:3px solid #F59E0B">
        <div class="res-label">Saldo</div>
        <div class="res-value {{ $saldo >= 0 ? 'saldo-pos' : 'saldo-neg' }}">
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
            <td style="color:#374151">
                {{ Str::limit($d->descripcion ?? $d->asiento?->concepto ?? '—', 50) }}
            </td>
            <td class="right">
                @if($d->debe > 0)
                    <span class="verde">${{ number_format($d->debe, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="right">
                @if($d->haber > 0)
                    <span class="rojo">${{ number_format($d->haber, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="5" style="text-align:center;padding:16px;color:#9ca3af">Sin movimientos</td></tr>
        @endforelse
        <tr class="total-row">
            <td colspan="3">TOTALES</td>
            <td class="right" style="color:#6ee7b7!important">${{ number_format($totalDebe, 2) }}</td>
            <td class="right" style="color:#fca5a5!important">${{ number_format($totalHaber, 2) }}</td>
        </tr>
    </tbody>
</table>

<div style="text-align:right;font-size:10px;font-weight:bold;padding:6px 8px;
            background:#f9fafb;border-radius:6px">
    Saldo final:
    <span class="{{ $saldo >= 0 ? 'saldo-pos' : 'saldo-neg' }}">
        ${{ number_format(abs($saldo), 2) }} {{ $saldo >= 0 ? 'DEUDOR' : 'ACREEDOR' }}
    </span>
</div>

<div class="footer">
    ERP Altamira &middot; Mayor Contable &middot; {{ $cuenta->codigo }} &mdash; {{ $cuenta->nombre }}
</div>

</body>
</html>
