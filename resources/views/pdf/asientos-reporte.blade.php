<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px; color: #1A1A2E;
            padding: 20px 24px; background: #fff;
        }

        .header {
            display: table; width: 100%;
            margin-bottom: 16px; padding-bottom: 12px;
            border-bottom: 2px solid #1F2D3D;
        }
        .h-left  { display:table-cell; vertical-align:middle; width:65%; }
        .h-right { display:table-cell; vertical-align:middle; text-align:right; width:35%; }
        .empresa-nombre { font-size:15px; font-weight:bold; color:#1A1A2E; }
        .empresa-sub    { font-size:8px; color:#555770; margin-top:2px; }
        .doc-titulo     { font-size:11px; font-weight:bold; color:#1A1A2E;
                          text-transform:uppercase; letter-spacing:0.5px; margin-top:6px; }
        .doc-fecha      { font-size:9px; color:#555770; }
        .doc-total      { font-size:9px; color:#555770; margin-top:2px; }

        .resumen {
            display: table; width: 100%; margin-bottom: 14px;
            border: 1px solid #D8DCE6;
        }
        .res-item {
            display: table-cell; text-align: center;
            padding: 8px 12px; border-right: 1px solid #D8DCE6;
        }
        .res-item:last-child { border-right:none; }
        .res-label {
            font-size: 7px; font-weight: bold; text-transform: uppercase;
            letter-spacing: 0.5px; color: #555770; margin-bottom: 3px;
        }
        .res-valor { font-size:13px; font-weight:bold; color:#1A1A2E; font-family:monospace; }

        table { width:100%; border-collapse:collapse; margin-bottom:8px; }
        thead tr { background:#1F2D3D; }
        thead th {
            padding: 7px 6px; text-align:left; font-size:8px;
            font-weight:bold; text-transform:uppercase;
            letter-spacing:0.4px; color:#FFFFFF;
        }
        thead th.right  { text-align:right; }
        thead th.center { text-align:center; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody tr:nth-child(odd)  { background:#FFFFFF; }
        tbody tr.anulado         { opacity:0.55; }
        tbody td {
            padding: 6px 6px; border-bottom:1px solid #D8DCE6;
            font-size:8.5px; vertical-align:middle; color:#1A1A2E;
        }
        tbody td.right  { text-align:right; font-family:monospace; }
        tbody td.center { text-align:center; }

        .num-asiento  { font-family:monospace; font-weight:bold; color:#2C5F8A; font-size:8.5px; }
        .concepto-principal { font-weight:600; color:#1A1A2E; }
        .concepto-ref { font-size:7.5px; color:#555770; margin-top:1px; }

        .tipo-auto   { font-size:7px; font-weight:bold; color:#2C5F8A;
                       background:#EEF2F8; padding:1px 5px; border-radius:3px; }
        .tipo-manual { font-size:7px; font-weight:bold; color:#555770;
                       background:#F5F7FA; padding:1px 5px; border-radius:3px; }
        .estado-activo  { font-size:7px; font-weight:bold; color:#1A1A2E; }
        .estado-anulado { font-size:7px; font-weight:bold; color:#555770;
                          text-decoration:line-through; }

        .fila-total { background:#1F2D3D !important; }
        .fila-total td {
            color:#FFFFFF !important; font-weight:bold !important;
            font-size:9px !important; padding:8px 6px !important;
        }

        .footer {
            margin-top:14px; padding-top:8px;
            border-top:1px solid #D8DCE6;
            display:table; width:100%;
        }
        .f-left  { display:table-cell; font-size:7px; color:#555770; }
        .f-right { display:table-cell; text-align:right; font-size:7px; color:#555770; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa-nombre">
            {{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}
        </div>
        <div class="empresa-sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot;
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
        <div class="doc-titulo">Reporte General de Asientos Contables</div>
    </div>
    <div class="h-right">
        <div class="doc-fecha">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="doc-total">Total: {{ $asientos->count() }} asientos</div>
        <div class="doc-total">Usuario: {{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Total Asientos</div>
        <div class="res-valor">{{ $asientos->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Debe</div>
        <div class="res-valor">${{ number_format($totalDebe, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Haber</div>
        <div class="res-valor">${{ number_format($totalHaber, 2) }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Anulados</div>
        <div class="res-valor" style="color:#555770">
            {{ $asientos->where('estado', 0)->count() }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Balance</div>
        @php $diff = abs($totalDebe - $totalHaber) @endphp
        <div class="res-valor">{{ $diff < 0.01 ? 'Cuadrado' : 'Descuadrado' }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:11%">N° Asiento</th>
            <th style="width:8%">Fecha</th>
            <th style="width:32%">Concepto</th>
            <th style="width:9%">Referencia</th>
            <th class="center" style="width:7%">Tipo</th>
            <th class="right" style="width:10%">Debe ($)</th>
            <th class="right" style="width:10%">Haber ($)</th>
            <th class="center" style="width:6%">Estado</th>
            <th style="width:7%">Período</th>
        </tr>
    </thead>
    <tbody>
        @foreach($asientos as $asiento)
        <tr class="{{ $asiento->estado === 0 ? 'anulado' : '' }}">
            <td><span class="num-asiento">{{ $asiento->numero }}</span></td>
            <td>{{ $asiento->fecha?->format('d/m/Y') }}</td>
            <td>
                <div class="concepto-principal">
                    {{ Str::limit($asiento->concepto, 55) }}
                </div>
                @if($asiento->documento_ref)
                <div class="concepto-ref">
                    {{ $asiento->documento_tipo }} &middot;
                    {{ $asiento->documento_ref }}
                </div>
                @endif
            </td>
            <td style="font-size:7.5px;color:#555770">
                {{ $asiento->documento_ref ?? '—' }}
            </td>
            <td class="center">
                @if($asiento->es_automatico)
                    <span class="tipo-auto">Auto</span>
                @else
                    <span class="tipo-manual">Manual</span>
                @endif
            </td>
            <td class="right" style="font-weight:600">
                ${{ number_format($asiento->total_debe, 2) }}
            </td>
            <td class="right" style="font-weight:600">
                ${{ number_format($asiento->total_haber, 2) }}
            </td>
            <td class="center">
                @if($asiento->estado === 1)
                    <span class="estado-activo">Activo</span>
                @else
                    <span class="estado-anulado">Anulado</span>
                @endif
            </td>
            <td style="font-size:7.5px;color:#555770">
                {{ $asiento->ejercicio?->periodo_label ?? '—' }}
            </td>
        </tr>
        @endforeach

        <tr class="fila-total">
            <td colspan="5">TOTALES</td>
            <td class="right">${{ number_format($totalDebe, 2) }}</td>
            <td class="right">${{ number_format($totalHaber, 2) }}</td>
            <td colspan="2" class="center">
                @php $diff = abs($totalDebe - $totalHaber) @endphp
                @if($diff < 0.01)
                    <span style="font-size:8px">&#10003; Cuadrado</span>
                @else
                    <span style="font-size:8px">${{ number_format($diff, 2) }}</span>
                @endif
            </td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-left">
        ERP Altamira &middot; Reporte de Asientos Contables &middot;
        Documento generado automáticamente
    </div>
    <div class="f-right">{{ now()->format('d/m/Y H:i:s') }}</div>
</div>

</body>
</html>
