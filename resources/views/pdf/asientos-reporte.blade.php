<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9px;color:#1f2937;padding:16px 20px; }

        .header { display:table;width:100%;margin-bottom:14px;
                  padding-bottom:10px;border-bottom:3px solid #F59E0B; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:15px;font-weight:bold;color:#F59E0B; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }
        .titulo  { font-size:13px;font-weight:bold;color:#1f2937; }

        .cards { display:table;width:100%;margin-bottom:14px;border-spacing:6px; }
        .card  { display:table-cell;text-align:center;padding:8px 10px;
                 border-radius:6px;width:25%; }
        .card-label { font-size:7px;font-weight:bold;text-transform:uppercase;
                      color:#9ca3af;margin-bottom:3px; }
        .card-value { font-size:14px;font-weight:bold;font-family:monospace; }
        .c-blue  { background:#dbeafe;border-left:3px solid #1e40af; }
        .c-green { background:#d1fae5;border-left:3px solid #059669; }
        .c-red   { background:#fee2e2;border-left:3px solid #dc2626; }
        .c-gold  { background:#fef3c7;border-left:3px solid #F59E0B; }

        table { width:100%;border-collapse:collapse;margin-bottom:10px; }
        thead tr { background:#1f2937; }
        thead th { padding:6px 8px;text-align:left;font-size:8px;
                   font-weight:bold;text-transform:uppercase;
                   color:white;letter-spacing:0.3px; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        tbody tr:nth-child(odd)  { background:#ffffff; }
        tbody tr.anulado { opacity:0.5; }
        tbody td { padding:5px 8px;border-bottom:1px solid #e5e7eb;
                   font-size:8.5px;vertical-align:middle; }
        tbody td.right { text-align:right;font-family:monospace; }
        .num   { font-weight:bold;color:#F59E0B;font-family:monospace; }
        .verde { color:#059669;font-weight:bold; }
        .rojo  { color:#dc2626;font-weight:bold; }
        .badge { display:inline-block;padding:1px 6px;border-radius:8px;
                 font-size:7px;font-weight:bold; }
        .b-act { background:#d1fae5;color:#065f46; }
        .b-anu { background:#fee2e2;color:#991b1b; }
        .b-man { background:#ede9fe;color:#5b21b6; }
        .b-aut { background:#dbeafe;color:#1e40af; }

        .fila-total td { background:#1f2937!important;color:white!important;
                         font-weight:bold!important;font-size:9px!important;
                         padding:7px 8px!important; }

        .footer { margin-top:12px;padding-top:8px;border-top:1px solid #e5e7eb;
                  display:table;width:100%; }
        .f-left  { display:table-cell;font-size:7px;color:#9ca3af; }
        .f-right { display:table-cell;text-align:right;font-size:7px;color:#9ca3af; }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <div class="h-left">
        <div class="empresa">
            {{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}
        </div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} ·
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
        <div class="sub" style="margin-top:6px;font-size:10px;font-weight:bold;color:#374151">
            REPORTE GENERAL DE ASIENTOS CONTABLES
        </div>
    </div>
    <div class="h-right">
        <div class="titulo">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="sub">Total: {{ $asientos->count() }} asientos</div>
    </div>
</div>

{{-- CARDS RESUMEN --}}
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
    <div class="card c-gold">
        <div class="card-label">Anulados</div>
        <div class="card-value" style="color:#F59E0B">{{ $asientos->where('estado', 0)->count() }}</div>
    </div>
</div>

{{-- TABLA ASIENTOS --}}
<table>
    <thead>
        <tr>
            <th style="width:12%">N° Asiento</th>
            <th style="width:9%">Fecha</th>
            <th>Concepto</th>
            <th style="width:10%">Referencia</th>
            <th style="width:8%">Tipo</th>
            <th class="right" style="width:10%">DEBE ($)</th>
            <th class="right" style="width:10%">HABER ($)</th>
            <th style="width:7%">Estado</th>
            <th style="width:10%">Período</th>
        </tr>
    </thead>
    <tbody>
        @foreach($asientos as $asiento)
        <tr class="{{ $asiento->estado === 0 ? 'anulado' : '' }}">
            <td class="num">{{ $asiento->numero }}</td>
            <td>{{ $asiento->fecha?->format('d/m/Y') }}</td>
            <td>
                {{ Str::limit($asiento->concepto, 50) }}
                @if($asiento->estado === 0)
                    <br><span style="color:#dc2626;font-size:7px">ANULADO</span>
                @endif
            </td>
            <td style="font-size:7.5px">{{ $asiento->documento_ref ?? '—' }}</td>
            <td>
                <span class="badge {{ $asiento->es_automatico ? 'b-aut' : 'b-man' }}">
                    {{ $asiento->es_automatico ? '⚡ Auto' : '✎ Manual' }}
                </span>
            </td>
            <td class="right verde">${{ number_format($asiento->total_debe, 2) }}</td>
            <td class="right rojo">${{ number_format($asiento->total_haber, 2) }}</td>
            <td>
                <span class="badge {{ $asiento->estado === 1 ? 'b-act' : 'b-anu' }}">
                    {{ $asiento->estado === 1 ? 'Activo' : 'Anulado' }}
                </span>
            </td>
            <td style="font-size:7.5px">{{ $asiento->ejercicio?->periodo_label ?? '—' }}</td>
        </tr>
        @endforeach

        <tr class="fila-total">
            <td colspan="5">TOTALES</td>
            <td class="right" style="color:#6ee7b7!important">${{ number_format($totalDebe, 2) }}</td>
            <td class="right" style="color:#fca5a5!important">${{ number_format($totalHaber, 2) }}</td>
            <td colspan="2">
                @if(abs($totalDebe - $totalHaber) < 0.01)
                    <span style="color:#6ee7b7">✅ Cuadrado</span>
                @else
                    <span style="color:#fca5a5">❌ Diferencia: ${{ number_format(abs($totalDebe - $totalHaber), 2) }}</span>
                @endif
            </td>
        </tr>
    </tbody>
</table>

{{-- FOOTER --}}
<div class="footer">
    <div class="f-left">
        ERP Altamira · Reporte inmutable · Usuario: {{ auth()->user()?->email }}
    </div>
    <div class="f-right">
        Página 1 · {{ now()->format('d/m/Y H:i:s') }}
    </div>
</div>

</body>
</html>
