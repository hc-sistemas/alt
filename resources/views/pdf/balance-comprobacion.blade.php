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
        <div class="empresa">{{ $empresa?->nombre_comercial ?? $empresa?->razon_social ?? 'Altamira' }}</div>
        <div class="titulo">BALANCE DE COMPROBACIÓN DE SUMAS Y SALDOS</div>
        <div style="font-size:10px; font-weight:bold; color:#1F2D3D; margin-top:2px;">
            {{ $periodo ?? '' }}
        </div>
        <div class="sub">Generado: {{ now()->format('d/m/Y H:i') }} &middot; Expresado en USD</div>
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
            <th class="right">Saldo Anterior</th>
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
            <td class="right">
                @if($c['saldo_anterior'] != 0)
                    {{ number_format(abs($c['saldo_anterior']), 2) }} {{ $c['saldo_anterior'] > 0 ? 'D' : 'H' }}
                @endif
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
            <td colspan="4">TOTALES</td>
            <td class="right">${{ number_format($totales['debe'], 2) }}</td>
            <td class="right">${{ number_format($totales['haber'], 2) }}</td>
            <td class="right">${{ number_format($totales['deudor'], 2) }}</td>
            <td class="right">${{ number_format($totales['acreedor'], 2) }}</td>
        </tr>
    </tfoot>
</table>

{{-- Verificación de la partida doble: en un libro sano las sumas del debe y
     del haber son iguales entre sí, y los saldos deudores y acreedores
     también. El reporte no lo comprobaba nunca. --}}
@php
    $dS = $totales['descuadre_sumas']  ?? 0;
    $dL = $totales['descuadre_saldos'] ?? 0;
@endphp
<div style="margin-top:12px; border:2px solid {{ (abs($dS) < 0.02 && abs($dL) < 0.02) ? '#059669' : '#dc2626' }};
            border-radius:6px; padding:7px 10px; text-align:center;">
    @if(abs($dS) < 0.02 && abs($dL) < 0.02)
        <span style="color:#059669; font-weight:bold; font-size:9px;">
            ✓ Balance cuadrado — Sumas Debe = Sumas Haber y Saldos Deudores = Saldos Acreedores
        </span>
    @else
        <span style="color:#dc2626; font-weight:bold; font-size:9px;">
            ⚠ DESCUADRE
            @if(abs($dS) >= 0.02) &nbsp;·&nbsp; Sumas: ${{ number_format($dS, 2) }} @endif
            @if(abs($dL) >= 0.02) &nbsp;·&nbsp; Saldos: ${{ number_format($dL, 2) }} @endif
        </span>
        <div style="font-size:7px; color:#6b7280; margin-top:3px;">
            Revise si hay cuentas con movimientos marcadas como "no permite asientos" o desactivadas:
            quedan fuera de este reporte pero sus saldos siguen en el libro.
        </div>
    @endif
</div>

<div style="margin-top:8px; font-size:7px; color:#9ca3af; text-align:right;">
    {{ $cuentas->count() }} cuenta(s) con movimientos
</div>

<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
