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
        .res-item { display:table-cell;text-align:center;padding:8px;
                    border-right:1px solid #D8DCE6; }
        .res-item:last-child { border-right:none; }
        .res-label { font-size:7px;font-weight:bold;
                     text-transform:uppercase;color:#555770; }
        .res-valor { font-size:13px;font-weight:bold;margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1F2D3D; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:5px 7px;border-bottom:1px solid #D8DCE6;
                   font-size:8.5px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }

        .badge { display:inline-block;padding:1px 5px;border-radius:3px;
                 font-size:7px;font-weight:bold;border:1px solid #D8DCE6; }
        .b-urg { background:#F5F7FA;color:#555770; }
        .b-est { background:#EEF2F8;color:#1A1A2E; }

        .fila-total { background:#1F2D3D; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         padding:7px!important;font-size:9px!important; }

        .footer { margin-top:10px;padding-top:8px;
                  border-top:1px solid #D8DCE6;display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#555770; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#555770; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">
            {{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}
        </div>
        <div class="titulo">REPORTE DE CUENTAS POR PAGAR</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot;
            Corte: {{ now()->format('d/m/Y') }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1A1A2E">
            {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">{{ $cxp->count() }} obligaciones pendientes</div>
        <div class="sub">Usuario: {{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Vencidas</div>
        <div class="res-valor" style="color:#555770">
            {{ $cxp->filter(fn($c) => $c->urgencia === 'vencida')->count() }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Críticas (0-5d)</div>
        <div class="res-valor" style="color:#555770">
            {{ $cxp->filter(fn($c) => $c->urgencia === 'critica')->count() }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Próximas (6-15d)</div>
        <div class="res-valor">
            {{ $cxp->filter(fn($c) => $c->urgencia === 'proxima')->count() }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Saldo Total</div>
        <div class="res-valor" style="font-size:11px">
            ${{ number_format($cxp->sum('saldo'), 2) }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:30%">Proveedor</th>
            <th style="width:14%">N° Documento</th>
            <th class="right" style="width:10%">Monto</th>
            <th class="right" style="width:10%">Saldo</th>
            <th style="width:10%">Emisión</th>
            <th style="width:10%">Vencimiento</th>
            <th style="width:10%">Días</th>
            <th style="width:6%">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($cxp as $c)
        @php
            $urgencia  = $c->urgencia;
            $dias      = $c->dias_vencimiento;
            $diasLabel = $dias < 0
                ? "Vencida {$dias}d"
                : ($dias === 0 ? 'Hoy' : "+{$dias}d");
        @endphp
        <tr>
            <td style="font-weight:600;color:#1A1A2E">
                {{ Str::limit($c->proveedor?->razon_social ?? '—', 35) }}
            </td>
            <td style="font-family:monospace;color:#2C5F8A;font-weight:bold">
                {{ $c->compra?->num_documento ?? '—' }}
            </td>
            <td class="right">
                ${{ number_format((float)$c->monto, 2) }}
            </td>
            <td class="right" style="font-weight:bold">
                ${{ number_format((float)$c->saldo, 2) }}
            </td>
            <td>{{ $c->fecha_emision?->format('d/m/Y') }}</td>
            <td>{{ $c->fecha_vencimiento?->format('d/m/Y') }}</td>
            <td>
                <span class="badge b-urg">{{ $diasLabel }}</span>
            </td>
            <td>
                <span class="badge b-est">{{ ucfirst($c->estado) }}</span>
            </td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="3">SALDO TOTAL PENDIENTE</td>
            <td class="right">
                ${{ number_format($cxp->sum('saldo'), 2) }}
            </td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira &middot; Cuentas por Pagar &middot; Reporte al {{ now()->format('d/m/Y') }}</div>
    <div class="f-r">Impreso: {{ now()->format('d/m/Y H:i') }}</div>
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
