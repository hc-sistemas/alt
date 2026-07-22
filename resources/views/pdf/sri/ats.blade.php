<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:7.5px; color:#1A1A2E; padding:14px 18px; }

.header { display:table; width:100%; margin-bottom:10px; padding-bottom:8px; border-bottom:2px solid #1A1A2E; }
.h-left  { display:table-cell; vertical-align:middle; width:65%; }
.h-right { display:table-cell; vertical-align:middle; text-align:right; width:35%; }
.empresa { font-size:13px; font-weight:bold; color:#1A1A2E; }
.titulo  { font-size:11px; font-weight:bold; margin-top:3px; color:#1F2D3D; }
.sub     { font-size:7px; color:#555770; margin-top:1px; }

.badge-sri { display:inline-block; background:#F59E0B; color:white; font-size:7px; font-weight:bold;
             padding:2px 7px; border-radius:3px; letter-spacing:.5px; }

.resumen { display:table; width:100%; margin-bottom:10px; border:1px solid #D8DCE6; border-radius:4px; }
.res-item { display:table-cell; text-align:center; padding:6px 8px; border-right:1px solid #D8DCE6; }
.res-item:last-child { border-right:none; }
.res-label { font-size:6.5px; font-weight:bold; text-transform:uppercase; color:#555770; }
.res-valor { font-size:12px; font-weight:bold; font-family:monospace; margin-top:1px; color:#1A1A2E; }
.res-sub   { font-size:6px; color:#888; margin-top:1px; }

.seccion-titulo { font-size:8px; font-weight:bold; text-transform:uppercase; letter-spacing:.5px;
                  color:white; background:#1F2D3D; padding:4px 8px; margin-top:8px; margin-bottom:0; }

table { width:100%; border-collapse:collapse; margin-bottom:0; }
thead th { background:#2C5F8A; color:white; padding:4px 5px; text-align:left; font-size:6.5px; font-weight:bold; text-transform:uppercase; }
thead th.r { text-align:right; }
tbody tr:nth-child(even) { background:#F7F8FA; }
tbody td { padding:3px 5px; border-bottom:1px solid #E8EAF0; font-size:7px; color:#1A1A2E; }
tbody td.r { text-align:right; font-family:monospace; }
tbody td.muted { color:#888; }
tfoot td { background:#E8EAF0; font-weight:bold; padding:4px 5px; font-size:7px; }
tfoot td.r { text-align:right; font-family:monospace; }

.empty { text-align:center; padding:12px; color:#AAAAAA; font-style:italic; font-size:7px; }
.footer { margin-top:12px; padding-top:6px; border-top:1px solid #D8DCE6; display:table; width:100%; }
.f-left  { display:table-cell; font-size:6px; color:#888; }
.f-right { display:table-cell; text-align:right; font-size:6px; color:#888; }
</style>
</head>
<body>

<!-- Cabecera -->
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->razon_social ?? 'Empresa' }}</div>
        <div class="titulo">ATS — Anexo Transaccional Simplificado</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} &nbsp;|&nbsp; Período: {{ $nombreMes }} {{ $anio }}</div>
    </div>
    <div class="h-right">
        <span class="badge-sri">SRI ECUADOR</span>
        <div class="sub" style="margin-top:4px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

<!-- Resumen numérico -->
<div class="resumen">
    <div class="res-item">
        <div class="res-label">Compras</div>
        <div class="res-valor">{{ $compras->count() }}</div>
        <div class="res-sub">documentos</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Compras</div>
        <div class="res-valor">${{ number_format($compras->sum('total'), 2) }}</div>
        <div class="res-sub">IVA incl.</div>
    </div>
    <div class="res-item">
        <div class="res-label">Ventas</div>
        <div class="res-valor">{{ $ventas->count() }}</div>
        <div class="res-sub">documentos</div>
    </div>
    <div class="res-item">
        <div class="res-label">Total Ventas</div>
        <div class="res-valor">${{ number_format($ventas->sum('total'), 2) }}</div>
        <div class="res-sub">IVA incl.</div>
    </div>
    <div class="res-item">
        <div class="res-label">Retenciones</div>
        <div class="res-valor">{{ $retenciones->count() }}</div>
        <div class="res-sub">emitidas</div>
    </div>
    <div class="res-item">
        <div class="res-label">IVA Compras</div>
        <div class="res-valor">${{ number_format($compras->sum('total_iva'), 2) }}</div>
        <div class="res-sub">crédito tributario</div>
    </div>
    <div class="res-item">
        <div class="res-label">IVA Ventas</div>
        <div class="res-valor">${{ number_format($ventas->sum('total_iva'), 2) }}</div>
        <div class="res-sub">IVA causado</div>
    </div>
</div>

<!-- COMPRAS -->
<div class="seccion-titulo">Compras del Período</div>
@if($compras->isEmpty())
    <p class="empty">Sin compras registradas en el período.</p>
@else
<table>
    <thead>
        <tr>
            <th>RUC / Identificación</th>
            <th>Proveedor</th>
            <th>N° Documento</th>
            <th>Fecha</th>
            <th class="r">Base 0%</th>
            <th class="r">Base 15%</th>
            <th class="r">IVA</th>
            <th class="r">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($compras as $c)
        <tr>
            <td class="muted">{{ $c->identificacion }}</td>
            <td>{{ \Illuminate\Support\Str::limit($c->razon_social, 35) }}</td>
            <td>{{ $c->num_documento }}</td>
            <td>{{ \Carbon\Carbon::parse($c->fecha_emision)->format('d/m/Y') }}</td>
            <td class="r">${{ number_format($c->subtotal_0 ?? 0, 2) }}</td>
            <td class="r">${{ number_format($c->subtotal_iva ?? 0, 2) }}</td>
            <td class="r">${{ number_format($c->total_iva ?? 0, 2) }}</td>
            <td class="r">${{ number_format($c->total ?? 0, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTAL COMPRAS</strong></td>
            <td class="r">${{ number_format($compras->sum('subtotal_0'), 2) }}</td>
            <td class="r">${{ number_format($compras->sum('subtotal_iva'), 2) }}</td>
            <td class="r">${{ number_format($compras->sum('total_iva'), 2) }}</td>
            <td class="r">${{ number_format($compras->sum('total'), 2) }}</td>
        </tr>
    </tfoot>
</table>
@endif

<!-- VENTAS -->
<div class="seccion-titulo" style="margin-top:12px;">Ventas del Período</div>
@if($ventas->isEmpty())
    <p class="empty">Sin ventas registradas en el período o módulo de ventas no activado.</p>
@else
<table>
    <thead>
        <tr>
            <th>RUC / Identificación</th>
            <th>Cliente</th>
            <th>N° Documento</th>
            <th>Fecha</th>
            <th class="r">Base 0%</th>
            <th class="r">Base 15%</th>
            <th class="r">IVA</th>
            <th class="r">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($ventas as $v)
        <tr>
            <td class="muted">{{ $v->identificacion }}</td>
            <td>{{ \Illuminate\Support\Str::limit($v->razon_social, 35) }}</td>
            <td>{{ $v->numero_completo }}</td>
            <td>{{ \Carbon\Carbon::parse($v->fecha_emision)->format('d/m/Y') }}</td>
            <td class="r">${{ number_format($v->subtotal_0 ?? 0, 2) }}</td>
            <td class="r">${{ number_format($v->subtotal_15 ?? 0, 2) }}</td>
            <td class="r">${{ number_format($v->total_iva ?? 0, 2) }}</td>
            <td class="r">${{ number_format($v->total ?? 0, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTAL VENTAS</strong></td>
            <td class="r">${{ number_format($ventas->sum('subtotal_0'), 2) }}</td>
            <td class="r">${{ number_format($ventas->sum('subtotal_15'), 2) }}</td>
            <td class="r">${{ number_format($ventas->sum('total_iva'), 2) }}</td>
            <td class="r">${{ number_format($ventas->sum('total'), 2) }}</td>
        </tr>
    </tfoot>
</table>
@endif

<!-- RETENCIONES -->
<div class="seccion-titulo" style="margin-top:12px;">Retenciones Emitidas</div>
@if($retenciones->isEmpty())
    <p class="empty">Sin retenciones emitidas en el período.</p>
@else
<table>
    <thead>
        <tr>
            <th>RUC Proveedor</th>
            <th>Proveedor</th>
            <th>N° Retención</th>
            <th>Fecha</th>
            <th class="r">Total Retenido</th>
        </tr>
    </thead>
    <tbody>
        @foreach($retenciones as $r)
        <tr>
            <td class="muted">{{ $r->identificacion }}</td>
            <td>{{ \Illuminate\Support\Str::limit($r->razon_social, 45) }}</td>
            <td>{{ $r->numero_completo }}</td>
            <td>{{ \Carbon\Carbon::parse($r->fecha_emision)->format('d/m/Y') }}</td>
            <td class="r">${{ number_format($r->total ?? 0, 2) }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="4"><strong>TOTAL RETENIDO</strong></td>
            <td class="r">${{ number_format($retenciones->sum('total'), 2) }}</td>
        </tr>
    </tfoot>
</table>
@endif

<!-- Footer -->
<div class="footer">
    <div class="f-left">Documento generado automáticamente por ERP Altamira · Uso interno</div>
    <div class="f-right">ATS {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $anio }} · RUC {{ $empresa->ruc ?? '—' }}</div>
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
