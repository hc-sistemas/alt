<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1f2937;
            padding: 24px 28px;
            background: #fff;
        }

        /* HEADER */
        .header {
            display: table;
            width: 100%;
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 3px solid #F59E0B;
        }
        .header-left  { display: table-cell; vertical-align: middle; width: 60%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 40%; }
        .empresa-nombre { font-size: 17px; font-weight: bold; color: #F59E0B; }
        .empresa-sub    { font-size: 9px; color: #6b7280; margin-top: 2px; }
        .asiento-numero { font-size: 22px; font-weight: bold; color: #1f2937; font-family: monospace; }

        /* BADGES */
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            margin-left: 4px;
        }
        .b-activo  { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .b-anulado { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .b-auto    { background: #dbeafe; color: #1e40af; border: 1px solid #93c5fd; }
        .b-manual  { background: #ede9fe; color: #5b21b6; border: 1px solid #c4b5fd; }

        /* CONCEPTO */
        .concepto-box {
            background: #fffbeb;
            border-left: 4px solid #F59E0B;
            padding: 10px 14px;
            margin-bottom: 16px;
        }
        .concepto-label { font-size: 8px; font-weight: bold; text-transform: uppercase;
                          color: #9ca3af; margin-bottom: 3px; }
        .concepto-texto { font-size: 12px; font-weight: bold; color: #1f2937; }

        /* INFO GRID */
        .info-grid { display: table; width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 4px; }
        .info-cell { display: table-cell; width: 25%; padding: 8px 12px;
                     background: #f9fafb; border: 1px solid #e5e7eb; }
        .info-label { font-size: 8px; font-weight: bold; text-transform: uppercase;
                      color: #9ca3af; margin-bottom: 3px; }
        .info-value { font-size: 11px; font-weight: 600; color: #1f2937; }

        /* MONTOS RESUMEN */
        .montos-grid { display: table; width: 100%; margin-bottom: 16px; border-collapse: separate; border-spacing: 4px; }
        .monto-card { display: table-cell; width: 33%; text-align: center; padding: 10px;
                      border: 1px solid #e5e7eb; }
        .monto-label { font-size: 8px; font-weight: bold; text-transform: uppercase;
                       color: #9ca3af; margin-bottom: 4px; }
        .monto-valor { font-size: 15px; font-weight: bold; font-family: monospace; }
        .verde { color: #059669; }
        .rojo  { color: #dc2626; }
        .muted { color: #d1d5db; }

        /* TABLA */
        .tabla-titulo {
            background: #1f2937;
            color: white;
            padding: 8px 12px;
            font-size: 11px;
            font-weight: bold;
        }
        table { width: 100%; border-collapse: collapse; }
        table thead tr { background: #F59E0B; }
        table thead th {
            padding: 8px 10px;
            text-align: left;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: white;
            letter-spacing: 0.5px;
        }
        table thead th.right { text-align: right; }
        table tbody tr:nth-child(even) { background: #f9fafb; }
        table tbody tr:nth-child(odd)  { background: #ffffff; }
        table tbody td {
            padding: 8px 10px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
            vertical-align: middle;
        }
        table tbody td.right { text-align: right; font-family: monospace; }
        .cod { font-weight: bold; color: #D97706; font-family: monospace; font-size: 10px; }
        .nom { color: #6b7280; font-size: 9px; margin-top: 1px; }

        /* FILA TOTALES */
        .fila-total { background: #1f2937 !important; }
        .fila-total td { color: white !important; font-weight: bold !important;
                         font-size: 11px !important; padding: 10px !important; }
        .fila-total .verde { color: #6ee7b7 !important; }
        .fila-total .rojo  { color: #fca5a5 !important; }

        /* CUADRE */
        .cuadre-box {
            text-align: right;
            padding: 8px 12px;
            background: #f0fdf4;
            border-top: 2px solid #6ee7b7;
            margin-bottom: 20px;
        }
        .cuadre-ok  { color: #059669; font-weight: bold; font-size: 11px; }
        .cuadre-err { color: #dc2626; font-weight: bold; font-size: 11px; }

        /* FOOTER */
        .footer {
            margin-top: 28px;
            padding-top: 14px;
            border-top: 2px solid #e5e7eb;
            display: table;
            width: 100%;
        }
        .firma-cell { display: table-cell; width: 35%; text-align: center; }
        .info-cell-footer { display: table-cell; width: 30%; text-align: center; vertical-align: middle; }
        .linea-firma { border-top: 1px solid #374151; margin-bottom: 4px;
                       width: 80%; margin-left: auto; margin-right: auto; }
        .firma-nombre  { font-size: 9px; color: #6b7280; }
        .footer-centro { font-size: 8px; color: #9ca3af; line-height: 1.6; }
    </style>
</head>
<body>

    {{-- HEADER --}}
    <div class="header">
        <div class="header-left">
            <div class="empresa-nombre">
                {{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}
            </div>
            <div class="empresa-sub">
                RUC: {{ $empresa->ruc ?? '—' }} &nbsp;·&nbsp;
                {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
            </div>
            <div class="empresa-sub" style="margin-top:6px;font-size:10px;">
                ASIENTO CONTABLE
            </div>
        </div>
        <div class="header-right">
            <div class="asiento-numero">{{ $asiento->numero }}</div>
            <div style="margin-top:5px">
                <span class="badge {{ $asiento->estado === 1 ? 'b-activo' : 'b-anulado' }}">
                    {{ $asiento->estado === 1 ? 'ACTIVO' : 'ANULADO' }}
                </span>
                <span class="badge {{ $asiento->es_automatico ? 'b-auto' : 'b-manual' }}">
                    {{ $asiento->es_automatico ? 'AUTOMATICO' : 'MANUAL' }}
                </span>
            </div>
        </div>
    </div>

    {{-- CONCEPTO --}}
    <div class="concepto-box">
        <div class="concepto-label">Concepto del asiento</div>
        <div class="concepto-texto">{{ $asiento->concepto }}</div>
    </div>

    {{-- INFO GRID --}}
    <div class="info-grid">
        <div class="info-cell">
            <div class="info-label">Fecha</div>
            <div class="info-value">{{ $asiento->fecha?->format('d/m/Y') ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Periodo</div>
            <div class="info-value">{{ $asiento->ejercicio?->periodo_label ?? '—' }}</div>
        </div>
        <div class="info-cell">
            <div class="info-label">Documento</div>
            <div class="info-value">
                {{ $asiento->documento_tipo ? $asiento->documento_tipo . ' ' . ($asiento->documento_ref ?? '') : '—' }}
            </div>
        </div>
        <div class="info-cell">
            <div class="info-label">Creado por</div>
            <div class="info-value">{{ $asiento->creadoPor?->email ?? '—' }}</div>
        </div>
    </div>

    {{-- MONTOS --}}
    <div class="montos-grid">
        <div class="monto-card">
            <div class="monto-label">Total DEBE</div>
            <div class="monto-valor verde">${{ number_format($asiento->total_debe, 2) }}</div>
        </div>
        <div class="monto-card">
            <div class="monto-label">Total HABER</div>
            <div class="monto-valor rojo">${{ number_format($asiento->total_haber, 2) }}</div>
        </div>
        <div class="monto-card">
            @php $diff = abs($asiento->total_debe - $asiento->total_haber) @endphp
            <div class="monto-label">Diferencia</div>
            <div class="monto-valor {{ $diff < 0.001 ? 'verde' : 'rojo' }}">
                ${{ number_format($diff, 2) }}
            </div>
        </div>
    </div>

    {{-- PARTIDAS --}}
    <div class="tabla-titulo">Partidas del Asiento &mdash; {{ count($asiento->detalles) }} lineas</div>
    <table>
        <thead>
            <tr>
                <th style="width:28%">Cuenta Contable</th>
                <th>Descripcion</th>
                <th class="right" style="width:15%">DEBE ($)</th>
                <th class="right" style="width:15%">HABER ($)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($asiento->detalles as $detalle)
            <tr>
                <td>
                    <div class="cod">{{ $detalle->cuenta?->codigo }}</div>
                    <div class="nom">{{ $detalle->cuenta?->nombre }}</div>
                </td>
                <td style="color:#374151">{{ $detalle->descripcion ?? '—' }}</td>
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
            <tr class="fila-total">
                <td colspan="2">TOTALES</td>
                <td class="right verde">${{ number_format($asiento->total_debe, 2) }}</td>
                <td class="right rojo">${{ number_format($asiento->total_haber, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @php $diff = abs($asiento->total_debe - $asiento->total_haber) @endphp
    <div class="cuadre-box">
        @if($diff < 0.001)
            <span class="cuadre-ok">Asiento cuadrado — DEBE = HABER = ${{ number_format($asiento->total_debe, 2) }}</span>
        @else
            <span class="cuadre-err">Asiento no cuadra — Diferencia: ${{ number_format($diff, 2) }}</span>
        @endif
    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <div class="firma-cell">
            <div class="linea-firma"></div>
            <div class="firma-nombre">Contador / Responsable</div>
        </div>
        <div class="info-cell-footer">
            <div class="footer-centro">
                <strong>{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</strong><br>
                Impreso: {{ now()->format('d/m/Y H:i') }}<br>
                Usuario: {{ auth()->user()?->email }}<br>
                <em>Documento de registro inmutable</em>
            </div>
        </div>
        <div class="firma-cell">
            <div class="linea-firma"></div>
            <div class="firma-nombre">Gerencia / Aprobado por</div>
        </div>
    </div>

</body>
</html>
