<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9.5px;color:#1a1a1a;padding:20px 24px; }
        .header { display:table;width:100%;margin-bottom:14px;
                  padding-bottom:10px;border-bottom:2px solid #2C3E50; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#1A3A5C; }
        .titulo  { font-size:11px;font-weight:bold;color:#1a1a1a;margin-top:4px;
                   text-transform:uppercase;letter-spacing:0.5px; }
        .sub     { font-size:8px;color:#555;margin-top:2px; }
        .banco-box { background:#F8F9FA;border:1px solid #CCCCCC;
                     border-left:4px solid #1A3A5C;
                     border-radius:0 6px 6px 0;
                     padding:10px 14px;margin-bottom:14px;
                     display:table;width:100%; }
        .bc-left  { display:table-cell;width:60%; }
        .bc-right { display:table-cell;width:40%;text-align:right; }
        .bc-nombre{ font-size:12px;font-weight:bold;color:#1A3A5C; }
        .bc-cuenta{ font-size:9px;color:#555;margin-top:2px; }
        .saldo-label { font-size:7px;text-transform:uppercase;
                       letter-spacing:0.5px;color:#777; }
        .saldo-valor { font-size:15px;font-weight:bold;
                       font-family:monospace;color:#2D6A4F; }
        .resumen { display:table;width:100%;margin-bottom:14px; }
        .res-item { display:table-cell;text-align:center;
                    padding:8px;border:1px solid #CCCCCC; }
        .res-item:not(:first-child) { border-left:none; }
        .res-label { font-size:7px;font-weight:bold;
                     text-transform:uppercase;color:#666; }
        .res-valor { font-size:13px;font-weight:bold;
                     font-family:monospace;margin-top:2px; }
        table { width:100%;border-collapse:collapse; }
        thead tr { background:#2C3E50; }
        thead th { padding:6px 7px;text-align:left;font-size:8px;
                   font-weight:bold;text-transform:uppercase;
                   color:white;letter-spacing:0.3px; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F8F9FA; }
        tbody td { padding:5.5px 7px;border-bottom:1px solid #E8E8E8;
                   font-size:8.5px; }
        tbody td.right { text-align:right;font-family:monospace; }
        .ing  { color:#2D6A4F;font-weight:600; }
        .egr  { color:#7B2D2D;font-weight:600; }
        .sald { color:#1A3A5C;font-weight:bold; }
        .fila-total { background:#2C3E50!important; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         padding:7px!important;font-size:9px!important; }
        .footer { margin-top:12px;padding-top:8px;
                  border-top:1px solid #CCC;display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#888; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#888; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Estado de Cuenta Bancaria</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} ·
            Período: {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }}
            al {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1a1a1a">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="sub">{{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="banco-box">
    <div class="bc-left">
        <div class="bc-nombre">{{ $banco->nombre }}</div>
        <div class="bc-cuenta">
            @if($banco->num_cuenta) N° Cuenta: {{ $banco->num_cuenta }} · @endif
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
        <div class="res-valor" style="color:#1A3A5C">${{ number_format($saldoInicial, 2) }}</div>
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
        <div class="res-label">Saldo Final</div>
        <div class="res-valor" style="color:#2D6A4F;font-size:14px">${{ number_format($saldoFinal, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Movimientos</div>
        <div class="res-valor" style="color:#374151">{{ $movimientos->count() }}</div>
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
            <td colspan="6" style="color:#555;font-style:italic">Saldo anterior al período</td>
            <td class="right sald">${{ number_format($saldoInicial, 2) }}</td>
        </tr>
        @foreach($movimientos as $m)
        <tr>
            <td>{{ \Carbon\Carbon::parse($m->fecha)->format('d/m/Y') }}</td>
            <td style="text-transform:capitalize">{{ $m->sub_tipo ?? $m->tipo }}</td>
            <td>
                <div style="font-weight:600;color:#1a1a1a">{{ \Illuminate\Support\Str::limit($m->descripcion ?? '—', 45) }}</div>
                @if($m->beneficiario)
                    <div style="font-size:7.5px;color:#777">{{ $m->beneficiario }}</div>
                @endif
            </td>
            <td style="font-size:8px;color:#555">{{ $m->num_documento ?? '—' }}</td>
            <td class="right">
                @if($m->tipo === 'ingreso')
                    <span class="ing">${{ number_format($m->monto, 2) }}</span>
                @else
                    <span style="color:#aaa">—</span>
                @endif
            </td>
            <td class="right">
                @if($m->tipo === 'egreso')
                    <span class="egr">${{ number_format($m->monto, 2) }}</span>
                @else
                    <span style="color:#aaa">—</span>
                @endif
            </td>
            <td class="right sald">${{ number_format($m->saldo_acumulado, 2) }}</td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="4">TOTALES DEL PERÍODO</td>
            <td class="right" style="color:#A8D5C2!important">${{ number_format($totalIngresos, 2) }}</td>
            <td class="right" style="color:#FFAAAA!important">${{ number_format($totalEgresos, 2) }}</td>
            <td class="right" style="color:#A8D5C2!important">${{ number_format($saldoFinal, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira · Estado de Cuenta · {{ $banco->nombre }}</div>
    <div class="f-r">Generado: {{ now()->format('d/m/Y H:i:s') }}</div>
</div>

</body>
</html>
