<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1a1a1a;
            padding: 20px 24px;
            background: #fff;
        }

        /* ── HEADER ── */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #2C3E50;
        }
        .h-left  { display:table-cell; vertical-align:middle; width:65%; }
        .h-right { display:table-cell; vertical-align:middle;
                   text-align:right; width:35%; }
        .empresa-nombre {
            font-size: 15px;
            font-weight: bold;
            color: #1A3A5C;
        }
        .empresa-sub {
            font-size: 8px;
            color: #555;
            margin-top: 2px;
        }
        .doc-titulo {
            font-size: 11px;
            font-weight: bold;
            color: #1a1a1a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 6px;
        }
        .doc-fecha {
            font-size: 9px;
            color: #555;
        }
        .doc-total {
            font-size: 9px;
            color: #555;
            margin-top: 2px;
        }

        /* ── RESUMEN ── */
        .resumen {
            display: table;
            width: 100%;
            margin-bottom: 14px;
            border: 1px solid #CCCCCC;
            border-radius: 4px;
        }
        .res-item {
            display: table-cell;
            text-align: center;
            padding: 8px 12px;
            border-right: 1px solid #CCCCCC;
        }
        .res-item:last-child { border-right: none; }
        .res-label {
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #555;
            margin-bottom: 3px;
        }
        .res-valor {
            font-size: 13px;
            font-weight: bold;
            color: #1A3A5C;
            font-family: monospace;
        }
        .res-valor.verde { color: #2D6A4F; }
        .res-valor.rojo  { color: #7B2D2D; }

        /* ── TABLA ── */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        thead tr {
            background: #2C3E50;
        }
        thead th {
            padding: 7px 6px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #FFFFFF;
        }
        thead th.right { text-align: right; }
        thead th.center { text-align: center; }

        tbody tr:nth-child(even) { background: #F8F9FA; }
        tbody tr:nth-child(odd)  { background: #FFFFFF; }
        tbody tr.anulado         { opacity: 0.55; }

        tbody td {
            padding: 6px 6px;
            border-bottom: 1px solid #E8E8E8;
            font-size: 8.5px;
            vertical-align: middle;
            color: #1a1a1a;
        }
        tbody td.right  { text-align: right; font-family: monospace; }
        tbody td.center { text-align: center; }

        .num-asiento {
            font-family: monospace;
            font-weight: bold;
            color: #1A3A5C;
            font-size: 8.5px;
        }
        .concepto-principal { font-weight: 600; color: #1a1a1a; }
        .concepto-ref {
            font-size: 7.5px;
            color: #777;
            margin-top: 1px;
        }
        .tipo-auto {
            font-size: 7px;
            font-weight: bold;
            color: #1A3A5C;
            background: #E8EEF5;
            padding: 1px 5px;
            border-radius: 3px;
        }
        .tipo-manual {
            font-size: 7px;
            font-weight: bold;
            color: #4A235A;
            background: #F0EBF5;
            padding: 1px 5px;
            border-radius: 3px;
        }
        .estado-activo {
            font-size: 7px;
            font-weight: bold;
            color: #2D6A4F;
        }
        .estado-anulado {
            font-size: 7px;
            font-weight: bold;
            color: #7B2D2D;
            text-decoration: line-through;
        }

        /* ── FILA TOTALES ── */
        .fila-total {
            background: #2C3E50 !important;
        }
        .fila-total td {
            color: #FFFFFF !important;
            font-weight: bold !important;
            font-size: 9px !important;
            padding: 8px 6px !important;
        }
        .cuadrado {
            font-size: 8px;
            font-weight: bold;
            color: #A8D5C2;
        }

        /* ── FOOTER ── */
        .footer {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #CCCCCC;
            display: table;
            width: 100%;
        }
        .f-left {
            display: table-cell;
            font-size: 7px;
            color: #888;
        }
        .f-right {
            display: table-cell;
            text-align: right;
            font-size: 7px;
            color: #888;
        }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <div class="h-left">
        <div class="empresa-nombre">
            {{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}
        </div>
        <div class="empresa-sub">
            RUC: {{ $empresa->ruc ?? '—' }} ·
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
        <div class="doc-titulo">Reporte General de Asientos Contables</div>
    </div>
    <div class="h-right">
        <div class="doc-fecha">{{ now()->format('d/m/Y H:i') }}</div>
        <div class="doc-total">Total: {{ $asientos->count() }} asientos</div>
        <div class="doc-total">
            Usuario: {{ auth()->user()?->email }}
        </div>
    </div>
</div>

{{-- RESUMEN --}}
<div class="resumen">
    <div class="res-item">
        <div class="res-label">Total Asientos</div>
        <div class="res-valor">{{ $asientos->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Debe</div>
        <div class="res-valor verde">
            ${{ number_format($totalDebe, 2) }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Haber</div>
        <div class="res-valor verde">
            ${{ number_format($totalHaber, 2) }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Anulados</div>
        <div class="res-valor rojo">
            {{ $asientos->where('estado', 0)->count() }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Balance</div>
        @php $diff = abs($totalDebe - $totalHaber) @endphp
        <div class="res-valor {{ $diff < 0.01 ? 'verde' : 'rojo' }}">
            {{ $diff < 0.01 ? 'Cuadrado' : 'Descuadrado' }}
        </div>
    </div>
</div>

{{-- TABLA --}}
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
            <td>
                <span class="num-asiento">{{ $asiento->numero }}</span>
            </td>
            <td>{{ $asiento->fecha?->format('d/m/Y') }}</td>
            <td>
                <div class="concepto-principal">
                    {{ Str::limit($asiento->concepto, 55) }}
                </div>
                @if($asiento->documento_ref)
                <div class="concepto-ref">
                    {{ $asiento->documento_tipo }} ·
                    {{ $asiento->documento_ref }}
                </div>
                @endif
            </td>
            <td style="font-size:7.5px;color:#666">
                {{ $asiento->documento_ref ?? '—' }}
            </td>
            <td class="center">
                @if($asiento->es_automatico)
                    <span class="tipo-auto">Auto</span>
                @else
                    <span class="tipo-manual">Manual</span>
                @endif
            </td>
            <td class="right" style="color:#2D6A4F;font-weight:600">
                ${{ number_format($asiento->total_debe, 2) }}
            </td>
            <td class="right" style="color:#2D6A4F;font-weight:600">
                ${{ number_format($asiento->total_haber, 2) }}
            </td>
            <td class="center">
                @if($asiento->estado === 1)
                    <span class="estado-activo">Activo</span>
                @else
                    <span class="estado-anulado">Anulado</span>
                @endif
            </td>
            <td style="font-size:7.5px;color:#555">
                {{ $asiento->ejercicio?->periodo_label ?? '—' }}
            </td>
        </tr>
        @endforeach

        {{-- TOTALES --}}
        <tr class="fila-total">
            <td colspan="5">TOTALES</td>
            <td class="right">
                ${{ number_format($totalDebe, 2) }}
            </td>
            <td class="right">
                ${{ number_format($totalHaber, 2) }}
            </td>
            <td colspan="2" class="center">
                @php $diff = abs($totalDebe - $totalHaber) @endphp
                @if($diff < 0.01)
                    <span class="cuadrado">✓ Cuadrado</span>
                @else
                    <span style="color:#FFB3B3;font-size:8px">
                        ✗ ${{ number_format($diff, 2) }}
                    </span>
                @endif
            </td>
        </tr>
    </tbody>
</table>

{{-- FOOTER --}}
<div class="footer">
    <div class="f-left">
        ERP Altamira · Reporte de Asientos Contables ·
        Documento generado automáticamente
    </div>
    <div class="f-right">
        Página 1 · {{ now()->format('d/m/Y H:i:s') }}
    </div>
</div>

</body>
</html>
