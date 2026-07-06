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

        .badge { display:inline-block;padding:1px 5px;border-radius:3px;
                 font-size:7px;font-weight:bold;border:1px solid #D8DCE6; }
        .b-ing { background:#EEF2F8;color:#2C5F8A; }
        .b-egr { background:#F5F7FA;color:#555770; }

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

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Reporte de Movimientos Bancarios</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot; Generado: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1A1A2E">{{ now()->format('d/m/Y') }}</div>
        <div class="sub">Total: {{ $movimientos->count() }} movimientos</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Movimientos</div>
        <div class="res-valor">{{ $movimientos->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Ingresos</div>
        <div class="res-valor">${{ number_format($totalIngresos, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Egresos</div>
        <div class="res-valor">${{ number_format($totalEgresos, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Diferencia Neta</div>
        @php $neto = $totalIngresos - $totalEgresos; @endphp
        <div class="res-valor">
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
            <td style="font-size:8px">
                {{ \Illuminate\Support\Str::limit($m->beneficiario ?? '—', 20) }}
            </td>
            <td class="right" style="font-weight:bold">
                ${{ number_format($m->monto, 2) }}
            </td>
            <td style="text-align:center;font-size:9px">{{ $m->conciliado ? '&#10003;' : '—' }}</td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="6">TOTALES</td>
            <td class="right">
                ${{ number_format($totalIngresos, 2) }} /
                ${{ number_format($totalEgresos, 2) }}
            </td>
            <td></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira &middot; Movimientos Bancarios</div>
    <div class="f-r">
        Impreso: {{ now()->format('d/m/Y H:i:s') }} &middot;
        Usuario: {{ auth()->user()?->email }}
    </div>
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
