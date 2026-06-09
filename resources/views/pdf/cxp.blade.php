<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9px;color:#1f2937;padding:16px 20px; }

        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:3px solid #F59E0B; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#F59E0B; }
        .titulo  { font-size:11px;font-weight:bold;color:#1f2937;margin-top:3px; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }

        .cards { display:table;width:100%;margin-bottom:12px; }
        .card  { display:table-cell;text-align:center;padding:8px;border-radius:6px; }
        .c1 { background:#fee2e2;border-left:3px solid #dc2626; }
        .c2 { background:#ffedd5;border-left:3px solid #ea580c;margin:0 4px; }
        .c3 { background:#fef9c3;border-left:3px solid #ca8a04;margin:0 4px; }
        .c4 { background:#fef3c7;border-left:3px solid #F59E0B; }
        .card-label { font-size:7px;font-weight:bold;
                      text-transform:uppercase;color:#6b7280; }
        .card-value { font-size:13px;font-weight:bold;margin-top:2px; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1f2937; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }

        tbody td { padding:5px 7px;border-bottom:1px solid #e5e7eb;font-size:8.5px; }
        tbody td.right { text-align:right;font-family:monospace; }

        .u-vencida { background:#fef2f2; }
        .u-critica  { background:#fff7ed; }
        .u-proxima  { background:#fefce8; }
        .u-normal   { background:#f0fdf4; }

        .badge { display:inline-block;padding:1px 5px;border-radius:8px;
                 font-size:7px;font-weight:bold; }
        .b-vencida { background:#fee2e2;color:#dc2626; }
        .b-critica  { background:#ffedd5;color:#ea580c; }
        .b-proxima  { background:#fef9c3;color:#ca8a04; }
        .b-normal   { background:#dcfce7;color:#16a34a; }

        .dorado { color:#F59E0B;font-weight:bold; }

        .fila-total { background:#1f2937; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         padding:7px!important;font-size:9px!important; }

        .footer { margin-top:10px;padding-top:8px;
                  border-top:1px solid #e5e7eb;display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#9ca3af; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#9ca3af; }
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
            RUC: {{ $empresa->ruc ?? '—' }} ·
            Corte: {{ now()->format('d/m/Y') }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1f2937">
            {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">{{ $cxp->count() }} obligaciones pendientes</div>
        <div class="sub">Usuario: {{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="cards">
    <div class="card c1">
        <div class="card-label">Vencidas</div>
        <div class="card-value" style="color:#dc2626">
            {{ $cxp->filter(fn($c) => $c->urgencia === 'vencida')->count() }}
        </div>
    </div>
    <div class="card c2">
        <div class="card-label">Críticas (0-5d)</div>
        <div class="card-value" style="color:#ea580c">
            {{ $cxp->filter(fn($c) => $c->urgencia === 'critica')->count() }}
        </div>
    </div>
    <div class="card c3">
        <div class="card-label">Próximas (6-15d)</div>
        <div class="card-value" style="color:#ca8a04">
            {{ $cxp->filter(fn($c) => $c->urgencia === 'proxima')->count() }}
        </div>
    </div>
    <div class="card c4">
        <div class="card-label">Saldo Total</div>
        <div class="card-value" style="color:#F59E0B;font-size:11px">
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
        <tr class="u-{{ $urgencia }}">
            <td style="font-weight:600;color:#1f2937">
                {{ Str::limit($c->proveedor?->razon_social ?? '—', 35) }}
            </td>
            <td style="font-family:monospace;color:#F59E0B;font-weight:bold">
                {{ $c->compra?->num_documento ?? '—' }}
            </td>
            <td class="right">
                ${{ number_format((float)$c->monto, 2) }}
            </td>
            <td class="right dorado">
                ${{ number_format((float)$c->saldo, 2) }}
            </td>
            <td>{{ $c->fecha_emision?->format('d/m/Y') }}</td>
            <td>{{ $c->fecha_vencimiento?->format('d/m/Y') }}</td>
            <td>
                <span class="badge b-{{ $urgencia }}">{{ $diasLabel }}</span>
            </td>
            <td>
                <span class="badge b-{{ $urgencia }}">
                    {{ ucfirst($c->estado) }}
                </span>
            </td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="3">SALDO TOTAL PENDIENTE</td>
            <td class="right" style="color:#F59E0B!important">
                ${{ number_format($cxp->sum('saldo'), 2) }}
            </td>
            <td colspan="4"></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira · Cuentas por Pagar · Reporte al {{ now()->format('d/m/Y') }}</div>
    <div class="f-r">Impreso: {{ now()->format('d/m/Y H:i') }}</div>
</div>

</body>
</html>
