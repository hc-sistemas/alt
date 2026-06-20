<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:8.5px;color:#1A1A2E;padding:16px 20px; }

        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#1A1A2E; }
        .titulo  { font-size:12px;font-weight:bold;color:#1A1A2E;margin-top:4px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }

        .resumen { display:table;width:100%;margin-bottom:12px;
                   border:1px solid #D8DCE6; }
        .res-item { display:table-cell;text-align:center;padding:8px;
                    border-right:1px solid #D8DCE6; }
        .res-item:last-child { border-right:none; }
        .res-label { font-size:7px;font-weight:bold;text-transform:uppercase;
                     color:#555770; }
        .res-valor { font-size:13px;font-weight:bold;font-family:monospace;
                     margin-top:2px;color:#1A1A2E; }

        .asiento-header { background:#1F2D3D;color:white;padding:5px 8px;
                          margin-top:8px;display:table;width:100%; }
        .ah-num   { display:table-cell;font-family:monospace;font-weight:bold;
                    color:#A8C4DE;width:15%; }
        .ah-fecha { display:table-cell;width:12%; }
        .ah-conc  { display:table-cell;width:55%; }
        .ah-tipo  { display:table-cell;text-align:right;width:18%; }

        table { width:100%;border-collapse:collapse;margin-bottom:0; }
        thead tr { background:#1F2D3D; }
        thead th { padding:5px 6px;text-align:left;font-size:7.5px;font-weight:bold;
                   text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:4px 6px;border-bottom:1px solid #D8DCE6;
                   font-size:8px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }
        .cod   { font-weight:bold;color:#2C5F8A;font-family:monospace; }
        .nom   { color:#555770;font-size:7.5px; }
        .muted { color:#AAAAAA; }

        .badge { display:inline-block;padding:1px 5px;border-radius:3px;
                 font-size:7px;font-weight:bold;border:1px solid #D8DCE6; }
        .b-auto { background:#EEF2F8;color:#2C5F8A; }
        .b-man  { background:#F5F7FA;color:#555770; }

        .subtotal-row td { background:#EEF1F5;font-weight:bold;
                           font-size:8.5px;padding:5px 6px;color:#1A1A2E; }

        .total-final td { background:#1F2D3D!important;color:white!important;
                          font-weight:bold!important;font-size:9px!important;
                          padding:6px!important; }

        .footer { margin-top:12px;padding-top:8px;border-top:1px solid #D8DCE6;
                  display:table;width:100%; }
        .f-left  { display:table-cell;font-size:7px;color:#555770; }
        .f-right { display:table-cell;text-align:right;font-size:7px;color:#555770; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}</div>
        <div class="titulo">LIBRO DIARIO</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot; {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:11px;font-weight:bold;color:#1A1A2E">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="sub">Total asientos: {{ $asientos->count() }}</div>
        <div class="sub">Usuario: {{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Total Asientos</div>
        <div class="res-valor">{{ $asientos->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total DEBE</div>
        <div class="res-valor">${{ number_format($totalDebe, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total HABER</div>
        <div class="res-valor">${{ number_format($totalHaber, 2) }}</div>
    </div>
</div>

@foreach($asientos as $asiento)
<div class="asiento-header">
    <span class="ah-num">{{ $asiento->numero }}</span>
    <span class="ah-fecha">{{ $asiento->fecha?->format('d/m/Y') }}</span>
    <span class="ah-conc">{{ Str::limit($asiento->concepto, 60) }}</span>
    <span class="ah-tipo">
        <span class="badge {{ $asiento->es_automatico ? 'b-auto' : 'b-man' }}">
            {{ $asiento->es_automatico ? 'Auto' : 'Manual' }}
        </span>
    </span>
</div>
<table>
    <thead>
        <tr>
            <th style="width:18%">Código</th>
            <th style="width:40%">Cuenta</th>
            <th style="width:27%">Descripción</th>
            <th class="right" style="width:7.5%">DEBE</th>
            <th class="right" style="width:7.5%">HABER</th>
        </tr>
    </thead>
    <tbody>
        @foreach($asiento->detalles as $detalle)
        <tr>
            <td><span class="cod">{{ $detalle->cuenta?->codigo ?? '—' }}</span></td>
            <td><span class="nom">{{ $detalle->cuenta?->nombre ?? '—' }}</span></td>
            <td style="color:#555770">{{ Str::limit($detalle->descripcion ?? '—', 40) }}</td>
            <td class="right">
                @if($detalle->debe > 0)
                    <span style="font-weight:bold">${{ number_format($detalle->debe, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="right">
                @if($detalle->haber > 0)
                    <span style="font-weight:bold">${{ number_format($detalle->haber, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="subtotal-row">
            <td colspan="3" style="text-align:right;color:#555770">
                Subtotal {{ $asiento->numero }}
            </td>
            <td class="right" style="font-weight:bold">${{ number_format($asiento->total_debe, 2) }}</td>
            <td class="right" style="font-weight:bold">${{ number_format($asiento->total_haber, 2) }}</td>
        </tr>
    </tbody>
</table>
@endforeach

<table style="margin-top:8px">
    <tbody>
        <tr class="total-final">
            <td style="width:73%">TOTALES GENERALES</td>
            <td class="right" style="width:13.5%;font-weight:bold">${{ number_format($totalDebe, 2) }}</td>
            <td class="right" style="width:13.5%;font-weight:bold">${{ number_format($totalHaber, 2) }}</td>
        </tr>
    </tbody>
</table>

@php $diff = abs($totalDebe - $totalHaber); @endphp
<div style="text-align:right;padding:6px 8px;font-size:9px;font-weight:bold;
            color:#1A1A2E;background:#F5F7FA;border:1px solid #D8DCE6;margin-top:2px">
    @if($diff < 0.01)
        &#10003; Libro cuadrado &mdash; DEBE = HABER
    @else
        Diferencia: ${{ number_format($diff, 2) }}
    @endif
</div>

<div class="footer">
    <div class="f-left">ERP Altamira &middot; Libro Diario &middot; Registro inmutable</div>
    <div class="f-right">Impreso: {{ now()->format('d/m/Y H:i:s') }}</div>
</div>

</body>
</html>
