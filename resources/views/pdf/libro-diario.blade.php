<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:8.5px;color:#1f2937;padding:16px 20px; }

        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:3px solid #F59E0B; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#F59E0B; }
        .titulo  { font-size:12px;font-weight:bold;color:#1f2937;margin-top:4px; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }

        .cards { display:table;width:100%;margin-bottom:12px; }
        .card  { display:table-cell;text-align:center;padding:8px;border-radius:6px; }
        .c-blue  { background:#dbeafe;border-left:3px solid #1e40af;margin-right:6px; }
        .c-green { background:#d1fae5;border-left:3px solid #059669;margin:0 3px; }
        .c-red   { background:#fee2e2;border-left:3px solid #dc2626;margin-left:6px; }
        .card-label { font-size:7px;font-weight:bold;text-transform:uppercase;color:#6b7280; }
        .card-value { font-size:13px;font-weight:bold;font-family:monospace;margin-top:2px; }

        .asiento-header { background:#1f2937;color:white;padding:5px 8px;
                          margin-top:8px;border-radius:4px 4px 0 0;
                          display:table;width:100%; }
        .ah-num   { display:table-cell;font-family:monospace;font-weight:bold;
                    color:#F59E0B;width:15%; }
        .ah-fecha { display:table-cell;width:12%; }
        .ah-conc  { display:table-cell;width:55%; }
        .ah-tipo  { display:table-cell;text-align:right;width:18%; }

        table { width:100%;border-collapse:collapse;margin-bottom:0; }
        thead tr { background:#F59E0B; }
        thead th { padding:5px 6px;text-align:left;font-size:7.5px;font-weight:bold;
                   text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        tbody td { padding:4px 6px;border-bottom:1px solid #e5e7eb;font-size:8px; }
        tbody td.right { text-align:right;font-family:monospace; }
        .cod   { font-weight:bold;color:#F59E0B;font-family:monospace; }
        .nom   { color:#6b7280;font-size:7.5px; }
        .verde { color:#059669;font-weight:bold; }
        .rojo  { color:#dc2626;font-weight:bold; }
        .muted { color:#d1d5db; }

        .subtotal-row td { background:#f3f4f6;font-weight:bold;font-size:8.5px;padding:5px 6px; }

        .total-final td { background:#1f2937!important;color:white!important;
                          font-weight:bold!important;font-size:9px!important;padding:6px!important; }

        .badge { display:inline-block;padding:1px 5px;border-radius:8px;
                 font-size:7px;font-weight:bold; }
        .b-auto { background:#dbeafe;color:#1e40af; }
        .b-man  { background:#ede9fe;color:#5b21b6; }

        .footer { margin-top:12px;padding-top:8px;border-top:1px solid #e5e7eb;
                  display:table;width:100%; }
        .f-left  { display:table-cell;font-size:7px;color:#9ca3af; }
        .f-right { display:table-cell;text-align:right;font-size:7px;color:#9ca3af; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}</div>
        <div class="titulo">LIBRO DIARIO</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} · {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:11px;font-weight:bold;color:#1f2937">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="sub">Total asientos: {{ $asientos->count() }}</div>
        <div class="sub">Usuario: {{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="cards">
    <div class="card c-blue">
        <div class="card-label">Total Asientos</div>
        <div class="card-value" style="color:#1e40af">{{ $asientos->count() }}</div>
    </div>
    <div class="card c-green">
        <div class="card-label">Total DEBE</div>
        <div class="card-value" style="color:#059669">${{ number_format($totalDebe, 2) }}</div>
    </div>
    <div class="card c-red">
        <div class="card-label">Total HABER</div>
        <div class="card-value" style="color:#dc2626">${{ number_format($totalHaber, 2) }}</div>
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
            <td style="color:#374151">{{ Str::limit($detalle->descripcion ?? '—', 40) }}</td>
            <td class="right">
                @if($detalle->debe > 0)
                    <span class="verde">${{ number_format($detalle->debe, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td class="right">
                @if($detalle->haber > 0)
                    <span class="rojo">${{ number_format($detalle->haber, 2) }}</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="subtotal-row">
            <td colspan="3" style="text-align:right;color:#6b7280">
                Subtotal {{ $asiento->numero }}
            </td>
            <td class="right verde">${{ number_format($asiento->total_debe, 2) }}</td>
            <td class="right rojo">${{ number_format($asiento->total_haber, 2) }}</td>
        </tr>
    </tbody>
</table>
@endforeach

<table style="margin-top:8px">
    <tbody>
        <tr class="total-final">
            <td style="width:73%">TOTALES GENERALES</td>
            <td class="right" style="width:13.5%;color:#6ee7b7!important">${{ number_format($totalDebe, 2) }}</td>
            <td class="right" style="width:13.5%;color:#fca5a5!important">${{ number_format($totalHaber, 2) }}</td>
        </tr>
    </tbody>
</table>

@php $diff = abs($totalDebe - $totalHaber); @endphp
@if($diff < 0.01)
<div style="text-align:right;padding:6px 8px;font-size:9px;font-weight:bold;color:#059669">
    Libro cuadrado &mdash; DEBE = HABER
</div>
@else
<div style="text-align:right;padding:6px 8px;font-size:9px;font-weight:bold;color:#dc2626">
    Diferencia: ${{ number_format($diff, 2) }}
</div>
@endif

<div class="footer">
    <div class="f-left">ERP Altamira &middot; Libro Diario &middot; Registro inmutable</div>
    <div class="f-right">Impreso: {{ now()->format('d/m/Y H:i:s') }}</div>
</div>

</body>
</html>
