<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9.5px;color:#1A1A2E;padding:20px 24px; }
        .header { display:table;width:100%;margin-bottom:14px;
                  padding-bottom:10px;border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#1A1A2E; }
        .titulo  { font-size:11px;font-weight:bold;color:#1A1A2E;margin-top:4px;
                   text-transform:uppercase;letter-spacing:0.5px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }

        .banco-box { background:#F5F7FA;border:1px solid #D8DCE6;
                     border-left:4px solid #2C5F8A;
                     padding:10px 14px;margin-bottom:14px;
                     display:table;width:100%; }
        .bc-left  { display:table-cell;width:60%; }
        .bc-right { display:table-cell;width:40%;text-align:right; }
        .bc-nombre{ font-size:12px;font-weight:bold;color:#1A1A2E; }
        .bc-cuenta{ font-size:9px;color:#555770;margin-top:2px; }
        .saldo-label { font-size:7px;text-transform:uppercase;
                       letter-spacing:0.5px;color:#555770; }
        .saldo-valor { font-size:15px;font-weight:bold;
                       font-family:monospace;color:#1A1A2E; }

        .resumen { display:table;width:100%;margin-bottom:14px;
                   border:1px solid #D8DCE6; }
        .res-item { display:table-cell;text-align:center;
                    padding:8px;border-right:1px solid #D8DCE6; }
        .res-item:last-child { border-right:none; }
        .res-label { font-size:7px;font-weight:bold;
                     text-transform:uppercase;color:#555770; }
        .res-valor { font-size:13px;font-weight:bold;
                     font-family:monospace;margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1F2D3D; }
        thead th { padding:6px 7px;text-align:left;font-size:8px;
                   font-weight:bold;text-transform:uppercase;
                   color:white;letter-spacing:0.3px; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:5.5px 7px;border-bottom:1px solid #D8DCE6;
                   font-size:8.5px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }

        .fila-total { background:#1F2D3D!important; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         padding:7px!important;font-size:9px!important; }

        .footer { margin-top:12px;padding-top:8px;
                  border-top:1px solid #D8DCE6;display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#555770; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#555770; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Estado de Cuenta Bancaria</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot;
            Período: {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1A1A2E">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="sub">{{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="banco-box">
    <div class="bc-left">
        <div class="bc-nombre">{{ $banco->nombre }}</div>
        <div class="bc-cuenta">
            @if($banco->num_cuenta) N° Cuenta: {{ $banco->num_cuenta }} &middot; @endif
            Tipo: {{ ucfirst($banco->tipo_cuenta ?? $banco->tipo) }}
        </div>
    </div>
    <div class="bc-right">
        <div class="saldo-label">Saldo final período</div>
        <div class="saldo-valor">${{ number_format($saldoFinal, 2) }}</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Saldo Inicial</div>
        <div class="res-valor">${{ number_format($saldoInicial, 2) }}</div>
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
        <div class="res-label">Saldo Final</div>
        <div class="res-valor" style="font-size:14px">${{ number_format($saldoFinal, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Movimientos</div>
        <div class="res-valor">{{ $movimientos->count() }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:9%">Fecha</th>
            <th style="width:9%">Sub-tipo</th>
            <th style="width:32%">Descripción</th>
            <th style="width:14%">Referencia</th>
            <th class="right" style="width:11%">Ingresos</th>
            <th class="right" style="width:11%">Egresos</th>
            <th class="right" style="width:14%">Saldo</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td colspan="6" style="color:#555770;font-style:italic">Saldo anterior al período</td>
            <td class="right" style="font-weight:bold">${{ number_format($saldoInicial, 2) }}</td>
        </tr>
        @foreach($movimientos as $m)
        <tr>
            <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
            <td style="text-transform:capitalize">{{ $m->sub_tipo ?? $m->tipo }}</td>
            <td>
                <div style="font-weight:600;color:#1A1A2E">
                    {{ \Illuminate\Support\Str::limit($m->descripcion ?? '—', 45) }}
                </div>
                @if($m->beneficiario)
                    <div style="font-size:7.5px;color:#555770">{{ $m->beneficiario }}</div>
                @endif
            </td>
            <td style="font-size:8px;color:#555770">{{ $m->num_documento ?? '—' }}</td>
            <td class="right">
                @if($m->tipo === 'ingreso')
                    <span style="font-weight:bold">${{ number_format($m->monto, 2) }}</span>
                @else
                    <span style="color:#AAAAAA">—</span>
                @endif
            </td>
            <td class="right">
                @if($m->tipo === 'egreso')
                    <span style="font-weight:bold">${{ number_format($m->monto, 2) }}</span>
                @else
                    <span style="color:#AAAAAA">—</span>
                @endif
            </td>
            <td class="right" style="font-weight:bold">${{ number_format($m->saldo_acumulado, 2) }}</td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="4">TOTALES DEL PERÍODO</td>
            <td class="right">${{ number_format($totalIngresos, 2) }}</td>
            <td class="right">${{ number_format($totalEgresos, 2) }}</td>
            <td class="right">${{ number_format($saldoFinal, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira &middot; Estado de Cuenta &middot; {{ $banco->nombre }}</div>
    <div class="f-r">Generado: {{ now()->format('d/m/Y H:i:s') }}</div>
</div>

</body>
</html>
