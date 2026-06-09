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
        .ing   { color:#2D6A4F;font-weight:bold; }
        .egr   { color:#7B2D2D;font-weight:bold; }
        .badge { display:inline-block;padding:1px 5px;border-radius:4px;
                 font-size:7px;font-weight:bold; }
        .b-ing { background:#D1FAE5;color:#065F46; }
        .b-egr { background:#FEE2E2;color:#7B2D2D; }
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
        <div class="titulo">Reporte de Movimientos Bancarios</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} · Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1a1a1a">{{ now()->format('d/m/Y') }}</div>
        <div class="sub">Total: {{ $movimientos->count() }} movimientos</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Movimientos</div>
        <div class="res-valor" style="color:#1A3A5C">{{ $movimientos->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Ingresos</div>
        <div class="res-valor" style="color:#2D6A4F">${{ number_format($totalIngresos, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Egresos</div>
        <div class="res-valor" style="color:#7B2D2D">${{ number_format($totalEgresos, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Diferencia Neta</div>
        @php $neto = $totalIngresos - $totalEgresos; @endphp
        <div class="res-valor" style="color:{{ $neto >= 0 ? '#2D6A4F' : '#7B2D2D' }}">
            ${{ number_format(abs($neto), 2) }} {{ $neto >= 0 ? '(+)' : '(-)' }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:9%">Fecha</th>
            <th style="width:18%">Banco/Caja</th>
            <th style="width:7%">Tipo</th>
            <th style="width:8%">Sub-tipo</th>
            <th style="width:28%">Descripción</th>
            <th style="width:14%">Beneficiario</th>
            <th class="right" style="width:10%">Monto</th>
            <th style="width:6%">Conc.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($movimientos as $m)
        <tr>
            <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
            <td style="font-weight:600;font-size:8px">{{ $m->bancoCaja?->nombre ?? '—' }}</td>
            <td>
                <span class="badge {{ $m->tipo === 'ingreso' ? 'b-ing' : 'b-egr' }}">
                    {{ ucfirst($m->tipo) }}
                </span>
            </td>
            <td style="font-size:8px;text-transform:capitalize">{{ $m->sub_tipo ?? '—' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($m->descripcion ?? '—', 40) }}</td>
            <td style="font-size:8px">{{ \Illuminate\Support\Str::limit($m->beneficiario ?? '—', 20) }}</td>
            <td class="right {{ $m->tipo === 'ingreso' ? 'ing' : 'egr' }}">${{ number_format($m->monto, 2) }}</td>
            <td style="text-align:center;font-size:9px">{{ $m->conciliado ? '✓' : '—' }}</td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="6">TOTALES</td>
            <td class="right" style="color:#A8D5C2!important">
                ${{ number_format($totalIngresos, 2) }} /
                <span style="color:#FFAAAA">${{ number_format($totalEgresos, 2) }}</span>
            </td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira · Movimientos Bancarios</div>
    <div class="f-r">Impreso: {{ now()->format('d/m/Y H:i:s') }} · Usuario: {{ auth()->user()?->email }}</div>
</div>

</body>
</html>
