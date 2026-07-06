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
         font-size:7px; color:#92400E; margin-bottom:14px; }

.seccion { margin-bottom:14px; border:1px solid #D8DCE6; border-radius:4px; overflow:hidden; }
.seccion-titulo { background:#1F2D3D; color:white; font-size:8px; font-weight:bold;
                  text-transform:uppercase; letter-spacing:.5px; padding:5px 10px; }
.seccion-body { padding:0; }

.fila { display:table; width:100%; border-bottom:1px solid #E8EAF0; }
.fila:last-child { border-bottom:none; }
.fila:nth-child(even) { background:#F7F8FA; }
.fila-num   { display:table-cell; width:50px; padding:5px 8px; font-size:6.5px; font-weight:bold;
              color:#2C5F8A; font-family:monospace; vertical-align:middle; }
.fila-label { display:table-cell; padding:5px 8px; font-size:8px; vertical-align:middle; }
.fila-valor { display:table-cell; width:130px; text-align:right; padding:5px 10px;
              font-family:monospace; font-size:8.5px; font-weight:bold; vertical-align:middle; }

.fila-total { background:#E8EDF2 !important; }
.fila-total .fila-label { font-weight:bold; }
.fila-total .fila-valor { font-size:9px; color:#1F2D3D; }

.liquidacion { border:2px solid #1F2D3D; border-radius:4px; overflow:hidden; margin-bottom:14px; }
.liq-titulo  { background:#1A1A2E; color:#F59E0B; font-size:9px; font-weight:bold;
               text-transform:uppercase; letter-spacing:.5px; padding:6px 12px; }
.liq-fila { display:table; width:100%; padding:6px 12px; border-bottom:1px solid #D8DCE6; }
.liq-fila:last-child { border-bottom:none; }
.liq-label { display:table-cell; font-size:8px; color:#555770; }
.liq-valor { display:table-cell; text-align:right; font-family:monospace; font-size:9px; font-weight:bold; }
.liq-fila-pagar { background:#F0FDF4; }
.liq-fila-pagar .liq-label { color:#166534; font-weight:bold; font-size:9px; }
.liq-fila-pagar .liq-valor { color:#166534; font-size:12px; }
.liq-fila-pagar-cero { background:#FEF9EC; }
.liq-fila-pagar-cero .liq-label { color:#92400E; font-weight:bold; font-size:9px; }
.liq-fila-pagar-cero .liq-valor { color:#92400E; font-size:12px; }

.firma { display:table; width:100%; margin-top:36px; }
.firma-cell { display:table-cell; width:50%; text-align:center; padding:0 20px; }
.firma-linea { border-top:1px solid #1A1A2E; padding-top:4px; font-size:7.5px; color:#555770; }
.firma-cargo { font-size:6.5px; color:#888; margin-top:2px; }

.footer { margin-top:18px; padding-top:6px; border-top:1px solid #D8DCE6; display:table; width:100%; }
.f-left  { display:table-cell; font-size:6px; color:#888; }
.f-right { display:table-cell; text-align:right; font-size:6px; color:#888; }
</style>
</head>
<body>

<!-- Cabecera -->
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->razon_social ?? 'Empresa' }}</div>
        <div class="titulo">Formulario 104 — Declaración del IVA</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} &nbsp;|&nbsp; Período: {{ $nombreMes }} {{ $anio }}</div>
    </div>
    <div class="h-right">
        <div><span class="badge-formulario">FORMULARIO 104</span></div>
        <div style="margin-top:4px;"><span class="badge-sri">SRI ECUADOR</span></div>
        <div class="sub" style="margin-top:4px;">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

<!-- Aviso -->
<div class="aviso">
    <strong>Nota:</strong> Documento informativo generado por ERP Altamira. IVA aplicado: 15%.
    Para declaración oficial use el portal <em>sri.gob.ec</em> — Formulario 104A para contribuyentes personas naturales.
</div>

@php
    $ventasGravadas = (float)($ventas->total_ventas_gravadas ?? 0);
    $ventas0        = (float)($ventas->total_ventas_0 ?? 0);
    $totalVentas    = (float)($ventas->total_ventas ?? 0);
    $numFacturas    = (int)($ventas->num_facturas ?? 0);
    $comprasGrav    = (float)($compras->total_compras_gravadas ?? 0);
    $compras0       = (float)($compras->total_compras_0 ?? 0);
    $totalCompras   = (float)($compras->total_compras ?? 0);
    $numCompras     = (int)($compras->num_compras ?? 0);
@endphp

<!-- VENTAS -->
<div class="seccion">
    <div class="seccion-titulo">Ventas — IVA Causado</div>
    <div class="seccion-body">
        <div class="fila">
            <div class="fila-num">401</div>
            <div class="fila-label">Ventas netas tarifa 0% (exentas / no objeto)</div>
            <div class="fila-valor">${{ number_format($ventas0, 2) }}</div>
        </div>
        <div class="fila">
            <div class="fila-num">402</div>
            <div class="fila-label">Ventas netas tarifa 15% (base imponible)</div>
            <div class="fila-valor">${{ number_format($ventasGravadas, 2) }}</div>
        </div>
        <div class="fila fila-total">
            <div class="fila-num">403</div>
            <div class="fila-label">Total ventas y otras operaciones</div>
            <div class="fila-valor">${{ number_format($ventasGravadas + $ventas0, 2) }}</div>
        </div>
        <div class="fila" style="background:#EFF6FF;">
            <div class="fila-num">431</div>
            <div class="fila-label" style="color:#1D4ED8;font-weight:bold;">
                IVA generado en ventas (15% × base gravada)
            </div>
            <div class="fila-valor" style="color:#1D4ED8;">${{ number_format($ivaVentas, 2) }}</div>
        </div>
        @if($numFacturas > 0)
        <div class="fila">
            <div class="fila-num"></div>
            <div class="fila-label" style="color:#888;font-size:7px;">{{ $numFacturas }} factura(s) emitida(s) en el período</div>
            <div class="fila-valor"></div>
        </div>
        @endif
    </div>
</div>

<!-- COMPRAS -->
<div class="seccion">
    <div class="seccion-titulo">Compras — Crédito Tributario</div>
    <div class="seccion-body">
        <div class="fila">
            <div class="fila-num">501</div>
            <div class="fila-label">Compras netas tarifa 0% (exentas / no objeto)</div>
            <div class="fila-valor">${{ number_format($compras0, 2) }}</div>
        </div>
        <div class="fila">
            <div class="fila-num">502</div>
            <div class="fila-label">Compras netas tarifa 15% (base imponible)</div>
            <div class="fila-valor">${{ number_format($comprasGrav, 2) }}</div>
        </div>
        <div class="fila fila-total">
            <div class="fila-num">503</div>
            <div class="fila-label">Total compras y pagos</div>
            <div class="fila-valor">${{ number_format($comprasGrav + $compras0, 2) }}</div>
        </div>
        <div class="fila" style="background:#F0FDF4;">
            <div class="fila-num">552</div>
            <div class="fila-label" style="color:#166534;font-weight:bold;">
                Crédito tributario de compras (IVA pagado)
            </div>
            <div class="fila-valor" style="color:#166534;">${{ number_format($credito, 2) }}</div>
        </div>
        @if($numCompras > 0)
        <div class="fila">
            <div class="fila-num"></div>
            <div class="fila-label" style="color:#888;font-size:7px;">{{ $numCompras }} factura(s) de compra en el período</div>
            <div class="fila-valor"></div>
        </div>
        @endif
    </div>
</div>

<!-- RETENCIONES IVA -->
<div class="seccion">
    <div class="seccion-titulo">Retenciones de IVA Recibidas</div>
    <div class="seccion-body">
        <div class="fila">
            <div class="fila-num">609</div>
            <div class="fila-label">IVA retenido por agentes de retención (clientes)</div>
            <div class="fila-valor">${{ number_format($ivaRetenido, 2) }}</div>
        </div>
    </div>
</div>

<!-- LIQUIDACIÓN -->
<div class="liquidacion">
    <div class="liq-titulo">Liquidación del Impuesto</div>
    <div class="liq-fila">
        <div class="liq-label">IVA causado (ventas)</div>
        <div class="liq-valor">${{ number_format($ivaCausado, 2) }}</div>
    </div>
    <div class="liq-fila">
        <div class="liq-label">(-) Crédito tributario (compras)</div>
        <div class="liq-valor" style="color:#DC2626;">- ${{ number_format($credito, 2) }}</div>
    </div>
    <div class="liq-fila">
        <div class="liq-label">(-) Retenciones de IVA recibidas</div>
        <div class="liq-valor" style="color:#DC2626;">- ${{ number_format($ivaRetenido, 2) }}</div>
    </div>
    @if($ivaPagar > 0)
    <div class="liq-fila liq-fila-pagar">
        <div class="liq-label">IMPUESTO A PAGAR (casilla 601)</div>
        <div class="liq-valor">${{ number_format($ivaPagar, 2) }}</div>
    </div>
    @else
    <div class="liq-fila liq-fila-pagar-cero">
        <div class="liq-label">CRÉDITO TRIBUTARIO SIGUIENTE PERÍODO (casilla 615)</div>
        <div class="liq-valor">${{ number_format(abs($ivaCausado - $credito - $ivaRetenido), 2) }}</div>
    </div>
    @endif
</div>

<!-- Firmas -->
<div class="firma">
    <div class="firma-cell">
        <div class="firma-linea">Representante Legal</div>
        <div class="firma-cargo">{{ $empresa->razon_social ?? '' }}</div>
    </div>
    <div class="firma-cell">
        <div class="firma-linea">Contador / Responsable</div>
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
