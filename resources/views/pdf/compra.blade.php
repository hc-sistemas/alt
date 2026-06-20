<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:10px;color:#1A1A2E;padding:20px 24px; }

        .header { display:table;width:100%;margin-bottom:16px;
                  padding-bottom:12px;border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:16px;font-weight:bold;color:#1A1A2E; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }
        .doc-num { font-size:18px;font-weight:bold;color:#2C5F8A;font-family:monospace; }

        .badge { display:inline-block;padding:2px 8px;border-radius:3px;
                 font-size:8px;font-weight:bold;border:1px solid #D8DCE6; }
        .b-act { background:#EEF2F8;color:#1A1A2E; }
        .b-anu { background:#F5F7FA;color:#555770; }

        .info-grid  { display:table;width:100%;margin-bottom:14px; }
        .info-half  { display:table-cell;width:50%;vertical-align:top;padding-right:10px; }
        .info-box   { background:#F5F7FA;border:1px solid #D8DCE6;padding:10px 12px; }
        .info-title { font-size:8px;font-weight:bold;text-transform:uppercase;
                      color:#555770;margin-bottom:6px;letter-spacing:0.5px; }
        .info-row   { display:table;width:100%;margin-bottom:3px; }
        .info-label { display:table-cell;width:45%;font-size:9px;color:#555770; }
        .info-value { display:table-cell;font-size:9px;font-weight:600;
                      color:#1A1A2E;text-align:right; }

        .tabla-titulo { background:#1F2D3D;color:white;padding:7px 10px;
                        font-size:10px;font-weight:bold; }
        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1F2D3D; }
        thead th { padding:7px 8px;text-align:left;font-size:8px;font-weight:bold;
                   text-transform:uppercase;color:white;letter-spacing:0.3px; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:6px 8px;border-bottom:1px solid #D8DCE6;
                   font-size:9px;vertical-align:middle;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }
        .desc  { font-weight:600;color:#1A1A2E; }
        .cuenta{ font-size:8px;color:#555770;margin-top:1px; }
        .num   { font-family:monospace;font-weight:bold;color:#2C5F8A; }

        .totales       { display:table;width:100%;margin-top:0; }
        .totales-space { display:table-cell;width:55%; }
        .totales-box   { display:table-cell;width:45%; }
        .total-row     { display:table;width:100%;padding:4px 10px; }
        .total-label   { display:table-cell;font-size:9px;color:#555770; }
        .total-value   { display:table-cell;text-align:right;
                         font-family:monospace;font-size:9px;color:#1A1A2E; }
        .total-final   { background:#1F2D3D; }
        .total-final .total-label { color:white;font-weight:bold;font-size:10px; }
        .total-final .total-value { color:white;font-weight:bold;font-size:11px; }

        .cxp-box { margin-top:14px;background:#F5F7FA;border:1px solid #D8DCE6;
                   border-left:4px solid #2C5F8A;padding:10px 12px; }
        .cxp-label { font-size:8px;font-weight:bold;text-transform:uppercase;color:#555770; }
        .cxp-texto { margin-left:12px;font-size:9px;color:#1A1A2E; }

        .footer { margin-top:20px;padding-top:10px;border-top:1px solid #D8DCE6;
                  display:table;width:100%; }
        .f-left  { display:table-cell;font-size:8px;color:#555770; }
        .f-right { display:table-cell;text-align:right;font-size:8px;color:#555770; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot; {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
        <div class="sub" style="margin-top:6px;font-size:10px;font-weight:bold;color:#1A1A2E">
            COMPROBANTE DE COMPRA
        </div>
    </div>
    <div class="h-right">
        <div class="doc-num">{{ $compra->num_documento }}</div>
        <div style="margin-top:4px">
            <span class="badge {{ $compra->estado === 'activa' ? 'b-act' : 'b-anu' }}">
                {{ strtoupper($compra->estado) }}
            </span>
        </div>
        <div class="sub" style="margin-top:4px">Tipo: {{ $compra->tipo_documento }}</div>
    </div>
</div>

<div class="info-grid">
    <div class="info-half">
        <div class="info-box">
            <div class="info-title">Proveedor</div>
            <div style="font-weight:700;font-size:10px;color:#1A1A2E;margin-bottom:4px">
                {{ $compra->proveedor?->razon_social ?? '—' }}
            </div>
            <div class="info-row">
                <span class="info-label">{{ strtoupper($compra->proveedor?->tipo_identificacion ?? 'RUC') }}</span>
                <span class="info-value">{{ $compra->proveedor?->identificacion ?? '—' }}</span>
            </div>
            @if($compra->proveedor?->email)
            <div class="info-row">
                <span class="info-label">Email</span>
                <span class="info-value">{{ $compra->proveedor->email }}</span>
            </div>
            @endif
            @if($compra->proveedor?->telefono)
            <div class="info-row">
                <span class="info-label">Teléfono</span>
                <span class="info-value">{{ $compra->proveedor->telefono }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="info-half" style="padding-right:0;padding-left:10px">
        <div class="info-box">
            <div class="info-title">Datos del Documento</div>
            <div class="info-row">
                <span class="info-label">Fecha emisión</span>
                <span class="info-value">{{ $compra->fecha_emision?->format('d/m/Y') ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Fecha registro</span>
                <span class="info-value">{{ $compra->fecha_registro?->format('d/m/Y') ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Vencimiento</span>
                <span class="info-value">{{ $compra->fecha_vencimiento?->format('d/m/Y') ?? '—' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Días crédito</span>
                <span class="info-value">{{ $compra->dias_credito }}</span>
            </div>
            @if($compra->num_autorizacion)
            <div class="info-row">
                <span class="info-label">Autorización SRI</span>
                <span class="info-value" style="font-size:7px">
                    {{ Str::limit($compra->num_autorizacion, 30) }}
                </span>
            </div>
            @endif
        </div>
    </div>
</div>

<div class="tabla-titulo">
    Detalle de ítems &mdash; {{ $compra->detalles->count() }} línea(s)
</div>
<table>
    <thead>
        <tr>
            <th style="width:40%">Descripción</th>
            <th class="right" style="width:8%">Cant.</th>
            <th class="right" style="width:11%">P. Unit.</th>
            <th class="right" style="width:10%">Desc.</th>
            <th class="right" style="width:11%">Subtotal</th>
            <th class="right" style="width:8%">IVA%</th>
            <th class="right" style="width:12%">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($compra->detalles as $detalle)
        <tr>
            <td>
                <div class="desc">{{ $detalle->descripcion }}</div>
                @if($detalle->cuenta)
                <div class="cuenta">{{ $detalle->cuenta->codigo }} — {{ $detalle->cuenta->nombre }}</div>
                @endif
            </td>
            <td class="right">{{ number_format((float)$detalle->cantidad, 2) }}</td>
            <td class="right">${{ number_format((float)$detalle->precio_unitario, 2) }}</td>
            <td class="right">
                {{ (float)$detalle->descuento > 0 ? '-$'.number_format((float)$detalle->descuento, 2) : '—' }}
            </td>
            <td class="right">${{ number_format((float)$detalle->subtotal, 2) }}</td>
            <td class="right">
                <span style="background:#EEF2F8;color:#2C5F8A;padding:1px 4px;
                             border-radius:2px;font-size:7px;font-weight:bold">
                    {{ number_format((float)$detalle->porcentaje_iva, 0) }}%
                </span>
            </td>
            <td class="right" style="font-weight:bold">${{ number_format((float)$detalle->total, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:16px;color:#555770">Sin ítems</td></tr>
        @endforelse
    </tbody>
</table>

<div class="totales">
    <div class="totales-space"></div>
    <div class="totales-box">
        <div class="total-row" style="padding-top:6px">
            <span class="total-label">Subtotal 0%</span>
            <span class="total-value">${{ number_format((float)$compra->subtotal_0, 2) }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">Subtotal gravado</span>
            <span class="total-value">${{ number_format((float)$compra->subtotal_iva, 2) }}</span>
        </div>
        <div class="total-row">
            <span class="total-label">IVA</span>
            <span class="total-value">${{ number_format((float)$compra->total_iva, 2) }}</span>
        </div>
        <div class="total-row total-final">
            <span class="total-label">TOTAL</span>
            <span class="total-value">${{ number_format((float)$compra->total, 2) }}</span>
        </div>
    </div>
</div>

@if($compra->cuentaPagar)
<div class="cxp-box">
    <span class="cxp-label">Cuenta por Pagar</span>
    <span class="cxp-texto">
        Saldo: ${{ number_format((float)$compra->cuentaPagar->saldo, 2) }} &middot;
        Vence: {{ $compra->cuentaPagar->fecha_vencimiento?->format('d/m/Y') ?? '—' }} &middot;
        Estado: {{ ucfirst($compra->cuentaPagar->estado) }}
    </span>
</div>
@endif

<div class="footer">
    <div class="f-left">
        ERP Altamira &middot; Impreso: {{ now()->format('d/m/Y H:i') }} &middot;
        Usuario: {{ auth()->user()?->email }}
    </div>
    <div class="f-right">
        {{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }} &middot; Documento de registro
    </div>
</div>

</body>
</html>
