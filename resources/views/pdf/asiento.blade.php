<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1A1A2E;
            padding: 24px 28px;
            background: #fff;
        }

        .header {
            display: table; width: 100%;
            margin-bottom: 18px; padding-bottom: 14px;
            border-bottom: 2px solid #1F2D3D;
        }
        .header-left  { display: table-cell; vertical-align: middle; width: 60%; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; width: 40%; }
        .empresa-nombre { font-size: 17px; font-weight: bold; color: #1A1A2E; }
        .empresa-sub    { font-size: 9px; color: #555770; margin-top: 2px; }
        .asiento-numero { font-size: 22px; font-weight: bold; color: #2C5F8A; font-family: monospace; }

        .badge {
            display: inline-block; padding: 2px 8px; border-radius: 3px;
            font-size: 9px; font-weight: bold; margin-left: 4px;
            border: 1px solid #D8DCE6; background: #F5F7FA; color: #1A1A2E;
        }

        .concepto-box {
            background: #F5F7FA; border-left: 4px solid #1F2D3D;
            padding: 10px 14px; margin-bottom: 16px;
        }
        .concepto-label { font-size: 8px; font-weight: bold; text-transform: uppercase;
                          color: #555770; margin-bottom: 3px; }
        .concepto-texto { font-size: 12px; font-weight: bold; color: #1A1A2E; }

        .info-grid { display: table; width: 100%; margin-bottom: 16px;
                     border-collapse: separate; border-spacing: 4px; }
        .info-cell { display: table-cell; width: 25%; padding: 8px 12px;
                     background: #F5F7FA; border: 1px solid #D8DCE6; }
        .info-label { font-size: 8px; font-weight: bold; text-transform: uppercase;
                      color: #555770; margin-bottom: 3px; }
        .info-value { font-size: 11px; font-weight: 600; color: #1A1A2E; }

        .montos-grid { display: table; width: 100%; margin-bottom: 16px;
                       border-collapse: separate; border-spacing: 4px; }
        .monto-card { display: table-cell; width: 33%; text-align: center; padding: 10px;
                      border: 1px solid #D8DCE6; background: #F5F7FA; }
        .monto-label { font-size: 8px; font-weight: bold; text-transform: uppercase;
                       color: #555770; margin-bottom: 4px; }
        .monto-valor { font-size: 15px; font-weight: bold; font-family: monospace;
                       color: #1A1A2E; }

        .tabla-titulo {
            background: #1F2D3D; color: white;
            padding: 8px 12px; font-size: 11px; font-weight: bold;
        }
        table { width: 100%; border-collapse: collapse; }
        table thead tr { background: #1F2D3D; }
        table thead th {
            padding: 8px 10px; text-align: left; font-size: 9px;
            font-weight: bold; text-transform: uppercase; color: white; letter-spacing: 0.5px;
        }
        table thead th.right { text-align: right; }
        table tbody tr:nth-child(even) { background: #F5F7FA; }
        table tbody tr:nth-child(odd)  { background: #FFFFFF; }
        table tbody td {
            padding: 8px 10px; border-bottom: 1px solid #D8DCE6;
            font-size: 10px; vertical-align: middle; color: #1A1A2E;
        }
        table tbody td.right { text-align: right; font-family: monospace; }
        .cod { font-weight: bold; color: #2C5F8A; font-family: monospace; font-size: 10px; }
        .nom { color: #555770; font-size: 9px; margin-top: 1px; }
        .muted { color: #AAAAAA; }

        .fila-total { background: #1F2D3D !important; }
        .fila-total td { color: white !important; font-weight: bold !important;
                         font-size: 11px !important; padding: 10px !important; }

        .cuadre-box {
            text-align: right; padding: 8px 12px;
            background: #F5F7FA; border-top: 2px solid #D8DCE6;
            margin-bottom: 20px;
        }
        .cuadre-ok  { color: #1A1A2E; font-weight: bold; font-size: 11px; }
        .cuadre-err { color: #555770; font-weight: bold; font-size: 11px; }

        .footer {
            margin-top: 28px; padding-top: 14px;
            border-top: 2px solid #D8DCE6;
            display: table; width: 100%;
        }
        .firma-cell { display: table-cell; width: 35%; text-align: center; }
        .info-cell-footer { display: table-cell; width: 30%; text-align: center; vertical-align: middle; }
        .linea-firma { border-top: 1px solid #1A1A2E; margin-bottom: 4px;
                       width: 80%; margin-left: auto; margin-right: auto; }
        .firma-nombre  { font-size: 9px; color: #555770; }
        .footer-centro { font-size: 8px; color: #555770; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-left">
            <div class="empresa-nombre">
                {{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}
            </div>
            <div class="empresa-sub">
                RUC: {{ $empresa->ruc ?? '—' }} &nbsp;&middot;&nbsp;
                {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
            </div>
            <div class="empresa-sub" style="margin-top:6px;font-size:10px;font-weight:bold">
                ASIENTO CONTABLE
            </div>
        </div>
        <div class="header-right">
            <div class="asiento-numero">{{ $asiento->numero }}</div>
            <div style="margin-top:5px">
                <span class="badge">
                    {{ $asiento->estado === 1 ? 'ACTIVO' : 'ANULADO' }}
                </span>
                <span class="badge">
                    {{ $asiento->es_automatico ? 'AUTOMÁTICO' : 'MANUAL' }}
                </span>
            </div>
        </div>
    </div>

    <div class="concepto-box">
        <div class="concepto-label">Concepto del asiento</div>
        <div class="concepto-texto">{{ $asiento->concepto }}</div>
    </div>

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

    <div class="montos-grid">
        <div class="monto-card">
            <div class="monto-label">Total DEBE</div>
            <div class="monto-valor">${{ number_format($asiento->total_debe, 2) }}</div>
        </div>
        <div class="monto-card">
            <div class="monto-label">Total HABER</div>
            <div class="monto-valor">${{ number_format($asiento->total_haber, 2) }}</div>
        </div>
        <div class="monto-card">
            @php $diff = abs($asiento->total_debe - $asiento->total_haber) @endphp
            <div class="monto-label">Diferencia</div>
            <div class="monto-valor" style="color:{{ $diff < 0.001 ? '#1A1A2E' : '#555770' }}">
                ${{ number_format($diff, 2) }}
            </div>
        </div>
    </div>

    <div class="tabla-titulo">Partidas del Asiento &mdash; {{ count($asiento->detalles) }} líneas</div>
    <table>
        <thead>
            <tr>
                <th style="width:28%">Cuenta Contable</th>
                <th>Descripción</th>
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
                <td style="color:#555770">{{ $detalle->descripcion ?? '—' }}</td>
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
            <tr class="fila-total">
                <td colspan="2">TOTALES</td>
                <td class="right">${{ number_format($asiento->total_debe, 2) }}</td>
                <td class="right">${{ number_format($asiento->total_haber, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @php $diff = abs($asiento->total_debe - $asiento->total_haber) @endphp
    <div class="cuadre-box">
        @if($diff < 0.001)
            <span class="cuadre-ok">&#10003; Asiento cuadrado — DEBE = HABER = ${{ number_format($asiento->total_debe, 2) }}</span>
        @else
            <span class="cuadre-err">Asiento no cuadra — Diferencia: ${{ number_format($diff, 2) }}</span>
        @endif
    </div>

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


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
