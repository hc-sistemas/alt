<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8.5px; color:#1A1A2E; padding:20px 24px; }

.header { display:table; width:100%; margin-bottom:14px; padding-bottom:10px; border-bottom:2px solid #1A1A2E; }
.h-left  { display:table-cell; vertical-align:middle; width:65%; }
.h-right { display:table-cell; vertical-align:middle; text-align:right; width:35%; }
.empresa  { font-size:14px; font-weight:bold; }
.titulo   { font-size:12px; font-weight:bold; margin-top:3px; color:#1F2D3D; }
.sub      { font-size:7.5px; color:#555770; margin-top:2px; }

.badge-formulario { display:inline-block; background:#1F2D3D; color:white; font-size:8px;
                    font-weight:bold; padding:3px 10px; border-radius:3px; letter-spacing:.5px; }
.badge-sri { display:inline-block; background:#F59E0B; color:white; font-size:7px; font-weight:bold;
             padding:2px 7px; border-radius:3px; letter-spacing:.5px; margin-top:4px; }

.aviso { background:#FEF9EC; border:1px solid #F59E0B; border-radius:4px; padding:6px 10px;
         font-size:7px; color:#92400E; margin-bottom:12px; }

table { width:100%; border-collapse:collapse; margin-bottom:0; }
thead th { background:#1F2D3D; color:white; padding:5px 8px; text-align:left; font-size:7.5px;
           font-weight:bold; text-transform:uppercase; }
thead th.r { text-align:right; }
thead th.c { text-align:center; }
tbody tr:nth-child(even) { background:#F7F8FA; }
tbody td { padding:4px 8px; border-bottom:1px solid #E8EAF0; font-size:8px; }
tbody td.r { text-align:right; font-family:monospace; }
tbody td.c { text-align:center; }
tbody td.cod { font-weight:bold; color:#2C5F8A; font-family:monospace; }
tfoot td { background:#1F2D3D; color:white; font-weight:bold; padding:5px 8px; font-size:8px; }
tfoot td.r { text-align:right; font-family:monospace; }

.firma { display:table; width:100%; margin-top:40px; }
.firma-cell { display:table-cell; width:50%; text-align:center; padding:0 20px; }
.firma-linea { border-top:1px solid #1A1A2E; padding-top:4px; font-size:7.5px; color:#555770; }
.firma-cargo { font-size:6.5px; color:#888; margin-top:2px; }

.footer { margin-top:20px; padding-top:6px; border-top:1px solid #D8DCE6; display:table; width:100%; }
.f-left  { display:table-cell; font-size:6px; color:#888; }
.f-right { display:table-cell; text-align:right; font-size:6px; color:#888; }
</style>
</head>
<body>

<!-- Cabecera -->
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->razon_social ?? 'Empresa' }}</div>
        <div class="titulo">Formulario 103 — Retención en la Fuente del IR</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} &nbsp;|&nbsp; Período: {{ $nombreMes }} {{ $anio }}</div>
    </div>
    <div class="h-right">
        <div><span class="badge-formulario">FORMULARIO 103</span></div>
        <div style="margin-top:4px;"><span class="badge-sri">SRI ECUADOR</span></div>
        <div class="sub" style="margin-top:4px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

<!-- Aviso -->
<div class="aviso">
    <strong>Nota:</strong> Este documento es un resumen informativo generado por ERP Altamira.
    Para presentación al SRI utilice el formulario oficial en el portal <em>sri.gob.ec</em>.
</div>

<!-- Tabla de retenciones -->
@if($detalles->isEmpty())
    <p style="text-align:center;padding:20px;color:#AAAAAA;font-style:italic;font-size:8px;">
        No se registraron retenciones en la fuente (IR) en el período {{ $nombreMes }} {{ $anio }}.
    </p>
@else
<table>
    <thead>
        <tr>
            <th>Código SRI</th>
            <th>Concepto / Tipo de Pago</th>
            <th class="c">% Retención</th>
            <th class="r">Base Imponible</th>
            <th class="r">Valor Retenido</th>
            <th class="c">N° Comp.</th>
        </tr>
    </thead>
    <tbody>
        @php
            $codigos = [
                '303' => 'Honorarios profesionales y dietas',
                '304' => 'Servicios predomina la mano de obra',
                '307' => 'Servicios entre sociedades',
                '308' => 'Servicios de publicidad y comunicación',
                '309' => 'Servicio de transporte privado de pasajeros',
                '310' => 'Transferencia de bienes muebles de naturaleza corporal',
                '312' => 'Transferencia de bienes inmuebles',
                '319' => 'Arrendamiento bienes inmuebles',
                '320' => 'Seguros y reaseguros',
                '322' => 'Rendimientos financieros / intereses',
                '323' => 'Pagos de bienes o servicios no sujetos a retención',
                '332' => 'Otras compras de bienes y servicios no sujetas a retención',
                '341' => 'Otras retenciones aplicables el 1%',
                '343' => 'Otras retenciones aplicables el 2%',
                '344' => 'Otras retenciones aplicables el 8%',
            ];
        @endphp
        @foreach($detalles as $d)
        <tr>
            <td class="cod">{{ $d->codigo }}</td>
            <td>{{ $codigos[$d->codigo] ?? 'Retención código ' . $d->codigo }}</td>
            <td class="c">{{ number_format((float)$d->porcentaje, 0) }}%</td>
            <td class="r">${{ number_format($d->base_imponible, 2) }}</td>
            <td class="r">${{ number_format($d->valor_retenido, 2) }}</td>
            <td class="c">{{ $d->num_comprobantes }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3"><strong>TOTALES</strong></td>
            <td class="r">${{ number_format($totalBase, 2) }}</td>
            <td class="r">${{ number_format($totalRetenido, 2) }}</td>
            <td class="c">{{ $detalles->sum('num_comprobantes') }}</td>
        </tr>
    </tfoot>
</table>

<!-- Liquidación rápida -->
<div style="display:table;width:60%;margin-left:40%;margin-top:14px;border:1px solid #D8DCE6;border-radius:4px;">
    <div style="background:#F7F8FA;padding:6px 12px;border-bottom:1px solid #D8DCE6;">
        <span style="font-size:7px;font-weight:bold;text-transform:uppercase;color:#555770;letter-spacing:.5px;">
            Resumen de Declaración
        </span>
    </div>
    <div style="padding:8px 12px;">
        <div style="display:table;width:100%;margin-bottom:4px;">
            <span style="display:table-cell;font-size:7.5px;color:#555770;">Total base imponible</span>
            <span style="display:table-cell;text-align:right;font-family:monospace;font-size:8px;font-weight:bold;">
                ${{ number_format($totalBase, 2) }}
            </span>
        </div>
        <div style="display:table;width:100%;padding-top:4px;border-top:1px solid #E8EAF0;">
            <span style="display:table-cell;font-size:7.5px;color:#1A1A2E;font-weight:bold;">
                Total impuesto a retener y pagar
            </span>
            <span style="display:table-cell;text-align:right;font-family:monospace;font-size:10px;font-weight:bold;color:#1F2D3D;">
                ${{ number_format($totalRetenido, 2) }}
            </span>
        </div>
    </div>
</div>
@endif

<!-- Firmas -->
<div class="firma">
    <div class="firma-cell">
        <div class="firma-linea">Nombre del Agente de Retención</div>
        <div class="firma-cargo">Representante Legal</div>
    </div>
    <div class="firma-cell">
        <div class="firma-linea">{{ $empresa->razon_social ?? '' }}</div>
        <div class="firma-cargo">RUC: {{ $empresa->ruc ?? '—' }}</div>
    </div>
</div>

<!-- Footer -->
<div class="footer">
    <div class="f-left">ERP Altamira · Documento informativo · Período {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $anio }}</div>
    <div class="f-right">Generado: {{ now()->format('d/m/Y H:i:s') }}</div>
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
