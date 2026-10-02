<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9px;color:#1A1A2E;padding:16px 20px; }
        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#1A1A2E; }
        .titulo  { font-size:11px;font-weight:bold;color:#1A1A2E;margin-top:3px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }

        .resumen { display:table;width:100%;margin-bottom:12px;
                   border:1px solid #D8DCE6; }
        .res-item { display:table-cell;text-align:center;padding:7px;
                    border-right:1px solid #D8DCE6; }
        .res-item:last-child { border-right:none; }
        .res-label { font-size:7px;font-weight:bold;
                     text-transform:uppercase;color:#555770; }
        .res-valor { font-size:13px;font-weight:bold;
                     font-family:monospace;margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1F2D3D; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:5px 7px;border-bottom:1px solid #D8DCE6;
                   font-size:8.5px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }

        .fila-total { background:#1F2D3D!important; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         padding:7px!important; }

        .footer { margin-top:10px;padding-top:8px;border-top:1px solid #D8DCE6;
                  display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#555770; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#555770; }
    </style>
</head>
<body>

@php
    $periodoLabel = ['mensual' => 'Mensual', 'trimestral' => 'Trimestral', 'anual' => 'Anual'][$periodo] ?? ucfirst($periodo);
@endphp

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Resumen de Ventas — {{ $periodoLabel }}</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot;
            Período: {{ $desde->format('d/m/Y') }} — {{ $hasta->format('d/m/Y') }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1A1A2E">{{ now()->format('d/m/Y') }}</div>
        <div class="sub">Total: {{ $facturas->count() }} facturas</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Facturas</div>
        <div class="res-valor">{{ $facturas->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Subtotal</div>
        <div class="res-valor">${{ number_format($facturas->sum(fn($f) => (float) $f->subtotal_0 + (float) $f->subtotal_15), 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">IVA</div>
        <div class="res-valor">${{ number_format($facturas->sum('total_iva'), 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total vendido</div>
        <div class="res-valor">${{ number_format($total, 2) }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:16%">N° Factura</th>
            <th style="width:12%">Fecha</th>
            <th style="width:40%">Cliente</th>
            <th style="width:16%">Identificación</th>
            <th class="right" style="width:16%">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($facturas as $f)
        <tr>
            <td style="font-family:monospace">{{ $f->numero_completo }}</td>
            <td>{{ $f->fecha_emision?->format('d/m/Y') }}</td>
            <td>{{ \Illuminate\Support\Str::limit($f->cliente?->razon_social ?? $f->razon_social ?? '—', 45) }}</td>
            <td>{{ $f->identificacion }}</td>
            <td class="right">${{ number_format($f->total, 2) }}</td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="4">TOTAL DEL PERÍODO</td>
            <td class="right">${{ number_format($total, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira &middot; Resumen de Ventas — Dashboard</div>
    <div class="f-r">
        Impreso: {{ now()->format('d/m/Y H:i:s') }} &middot;
        Usuario: {{ auth()->user()?->email }}
    </div>
</div>

</body>
</html>
