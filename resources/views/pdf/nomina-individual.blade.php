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
        }

        /* ─── Header ─── */
        .header {
            display: table; width: 100%;
            border-bottom: 3px solid #1F2D3D;
            padding-bottom: 12px; margin-bottom: 16px;
        }
        .h-left  { display: table-cell; vertical-align: middle; width: 65%; }
        .h-right { display: table-cell; vertical-align: middle; text-align: right; width: 35%; }
        .empresa-nombre { font-size: 17px; font-weight: bold; color: #1F2D3D; letter-spacing: 0.5px; }
        .empresa-sub    { font-size: 8px; color: #555770; margin-top: 3px; }
        .doc-titulo     { font-size: 13px; font-weight: bold; color: #1F2D3D; text-transform: uppercase; }
        .doc-periodo    { font-size: 9px; color: #555770; margin-top: 3px; }

        /* ─── Datos del colaborador ─── */
        .colaborador-box {
            background: #F0F4F8;
            border: 1px solid #C9D3DF;
            border-left: 4px solid #1F2D3D;
            padding: 10px 14px;
            margin-bottom: 14px;
        }
        .colab-nombre { font-size: 13px; font-weight: bold; color: #1F2D3D; }
        .colab-sub    { font-size: 9px; color: #555770; margin-top: 3px; }
        .colab-grid   { display: table; width: 100%; margin-top: 8px; }
        .colab-cell   { display: table-cell; width: 33%; font-size: 9px; }
        .colab-label  { color: #555770; }
        .colab-value  { color: #1A1A2E; font-weight: bold; }

        /* ─── Tablas de rubros ─── */
        .seccion-titulo {
            font-size: 9px; font-weight: bold; text-transform: uppercase;
            color: #fff; background: #1F2D3D;
            padding: 5px 10px; letter-spacing: 0.5px;
            margin-bottom: 0;
        }
        table.rubros {
            width: 100%; border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.rubros th {
            background: #EEF2F8; font-size: 8px; font-weight: bold;
            text-align: left; padding: 5px 8px;
            color: #1F2D3D; border: 1px solid #D0D7E2;
        }
        table.rubros td {
            font-size: 9px; padding: 5px 8px;
            border: 1px solid #D0D7E2; color: #1A1A2E;
            background: #fff;
        }
        table.rubros tr:nth-child(even) td { background: #F8FAFC; }
        table.rubros td.monto { text-align: right; font-family: monospace; }
        table.rubros tr.subtotal td {
            font-weight: bold; background: #EEF2F8;
            border-top: 2px solid #1F2D3D;
        }

        /* ─── Resumen ─── */
        .resumen-box {
            display: table; width: 100%;
            border: 2px solid #1F2D3D;
            margin-bottom: 18px;
        }
        .res-cell { display: table-cell; width: 33%; text-align: center; padding: 10px 6px; }
        .res-label { font-size: 8px; color: #555770; text-transform: uppercase; margin-bottom: 4px; }
        .res-valor { font-size: 16px; font-weight: bold; color: #1F2D3D; }
        .res-ingresos { border-right: 1px solid #C9D3DF; }
        .res-egresos  { border-right: 1px solid #C9D3DF; }
        .res-neto .res-valor { color: #1F7A3C; font-size: 20px; }

        /* ─── Firmas ─── */
        .firmas {
            display: table; width: 100%; margin-top: 28px;
        }
        .firma-cel {
            display: table-cell; width: 50%; text-align: center;
            padding: 0 20px;
        }
        .firma-linea {
            border-top: 1px solid #1A1A2E;
            padding-top: 6px;
            margin-top: 28px;
            font-size: 9px; color: #1A1A2E;
        }
        .firma-sub { font-size: 8px; color: #555770; margin-top: 3px; }

        /* ─── Footer ─── */
        .footer {
            margin-top: 20px;
            border-top: 1px solid #C9D3DF;
            padding-top: 8px;
            font-size: 7.5px; color: #888;
            text-align: center;
        }
    </style>
</head>
<body>

{{-- ── Header ─────────────────────────────────────────────────────────── --}}
<div class="header">
    <div class="h-left">
        <div class="empresa-nombre">{{ strtoupper($empresa->nombre_comercial ?? $empresa->razon_social ?? 'ALTAMIRA LIGHT & SOUND') }}</div>
        <div class="empresa-sub">RUC: {{ $empresa->ruc ?? '' }} &nbsp;|&nbsp; {{ $empresa->direccion ?? 'Ecuador' }}</div>
    </div>
    <div class="h-right">
        <div class="doc-titulo">Rol de Pagos</div>
        <div class="doc-periodo">{{ $nomina->periodo_label }}</div>
        <div class="doc-periodo" style="margin-top:2px;">
            {{ $nomina->periodo_tipo === 'quincenal' ? 'Quincenal' : 'Mensual' }}
        </div>
    </div>
</div>

{{-- ── Datos del colaborador ───────────────────────────────────────────── --}}
@php
    $col = $detalle->colaborador;
@endphp
<div class="colaborador-box">
    <div class="colab-nombre">{{ $col->apellidos }} {{ $col->nombres }}</div>
    <div class="colab-sub">{{ $col->cargo ?? 'Sin cargo asignado' }}</div>
    <div class="colab-grid">
        <div class="colab-cell">
            <span class="colab-label">Cédula/RUC:&nbsp;</span>
            <span class="colab-value">{{ $col->cedula_ruc }}</span>
        </div>
        <div class="colab-cell">
            <span class="colab-label">Banco:&nbsp;</span>
            <span class="colab-value">{{ $col->banco ?? '—' }}</span>
        </div>
        <div class="colab-cell">
            <span class="colab-label">Cta:&nbsp;</span>
            <span class="colab-value">{{ $col->numero_cuenta ?? '—' }}</span>
        </div>
    </div>
</div>

{{-- ── INGRESOS ─────────────────────────────────────────────────────────── --}}
<div class="seccion-titulo">Ingresos</div>
<table class="rubros">
    <tr>
        <th style="width:65%">Concepto</th>
        <th style="width:35%;text-align:right">Valor (USD)</th>
    </tr>
    <tr>
        <td>Sueldo Base</td>
        <td class="monto">{{ number_format($detalle->sueldo_base, 2) }}</td>
    </tr>
    @if((float)$detalle->horas_extras_50 > 0)
    <tr>
        <td>Horas Extras Suplementarias (50%)</td>
        <td class="monto">{{ number_format($detalle->horas_extras_50, 2) }}</td>
    </tr>
    @endif
    @if((float)$detalle->horas_extras_100 > 0)
    <tr>
        <td>Horas Extras Extraordinarias (100%)</td>
        <td class="monto">{{ number_format($detalle->horas_extras_100, 2) }}</td>
    </tr>
    @endif
    @if((float)$detalle->comisiones > 0)
    <tr>
        <td>Comisiones</td>
        <td class="monto">{{ number_format($detalle->comisiones, 2) }}</td>
    </tr>
    @endif
    @if((float)$detalle->otros_ingresos > 0)
    <tr>
        <td>Otros Ingresos (Décimos / Fondos de Reserva)</td>
        <td class="monto">{{ number_format($detalle->otros_ingresos, 2) }}</td>
    </tr>
    @endif
    <tr class="subtotal">
        <td>TOTAL INGRESOS</td>
        <td class="monto">{{ number_format($detalle->total_ingresos, 2) }}</td>
    </tr>
</table>

{{-- ── EGRESOS ──────────────────────────────────────────────────────────── --}}
<div class="seccion-titulo">Egresos</div>
<table class="rubros">
    <tr>
        <th style="width:65%">Concepto</th>
        <th style="width:35%;text-align:right">Valor (USD)</th>
    </tr>
    <tr>
        <td>Aporte Personal IESS (9.45%)</td>
        <td class="monto">{{ number_format($detalle->aporte_personal_iess, 2) }}</td>
    </tr>
    @if((float)$detalle->descuento_atrasos > 0)
    <tr>
        <td>Descuento por Atrasos</td>
        <td class="monto">{{ number_format($detalle->descuento_atrasos, 2) }}</td>
    </tr>
    @endif
    @if((float)$detalle->descuento_prestamos > 0)
    <tr>
        <td>Cuota Préstamo</td>
        <td class="monto">{{ number_format($detalle->descuento_prestamos, 2) }}</td>
    </tr>
    @endif
    @if((float)$detalle->descuento_anticipos > 0)
    <tr>
        <td>Recuperación de Anticipos</td>
        <td class="monto">{{ number_format($detalle->descuento_anticipos, 2) }}</td>
    </tr>
    @endif
    @if((float)$detalle->otros_egresos > 0)
    <tr>
        <td>Otros Descuentos</td>
        <td class="monto">{{ number_format($detalle->otros_egresos, 2) }}</td>
    </tr>
    @endif
    <tr class="subtotal">
        <td>TOTAL EGRESOS</td>
        <td class="monto">{{ number_format($detalle->total_egresos, 2) }}</td>
    </tr>
</table>

{{-- ── Resumen ──────────────────────────────────────────────────────────── --}}
<div class="resumen-box">
    <div class="res-cell res-ingresos">
        <div class="res-label">Total Ingresos</div>
        <div class="res-valor">$ {{ number_format($detalle->total_ingresos, 2) }}</div>
    </div>
    <div class="res-cell res-egresos">
        <div class="res-label">Total Egresos</div>
        <div class="res-valor">$ {{ number_format($detalle->total_egresos, 2) }}</div>
    </div>
    <div class="res-cell res-neto">
        <div class="res-label">Neto a Pagar</div>
        <div class="res-valor">$ {{ number_format($detalle->neto_pagar, 2) }}</div>
    </div>
</div>

{{-- ── Firmas ───────────────────────────────────────────────────────────── --}}
<div class="firmas">
    <div class="firma-cel">
        <div class="firma-linea">
            Firma del Empleador
            <div class="firma-sub">{{ $empresa->nombre_comercial ?? $empresa->razon_social ?? 'Altamira Light & Sound' }}</div>
        </div>
    </div>
    <div class="firma-cel">
        <div class="firma-linea">
            Firma de Conformidad
            <div class="firma-sub">{{ $col->apellidos }} {{ $col->nombres }}</div>
        </div>
    </div>
</div>

{{-- ── Footer ──────────────────────────────────────────────────────────── --}}
<div class="footer">
    Documento generado el {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp;
    {{ $empresa->nombre_comercial ?? $empresa->razon_social ?? 'Altamira' }} &nbsp;|&nbsp;
    Este documento es de carácter confidencial.
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
