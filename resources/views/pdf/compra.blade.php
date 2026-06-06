<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:10px;color:#1f2937;padding:20px 24px; }

        /* Header */
        .header { display:table;width:100%;margin-bottom:16px;
                  padding-bottom:12px;border-bottom:3px solid #F59E0B; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:16px;font-weight:bold;color:#F59E0B; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }
        .doc-num { font-size:18px;font-weight:bold;color:#1f2937;font-family:monospace; }

        /* Badges */
        .badge { display:inline-block;padding:2px 8px;border-radius:10px;
                 font-size:8px;font-weight:bold; }
        .b-act { background:#d1fae5;color:#065f46; }
        .b-anu { background:#fee2e2;color:#991b1b; }

        /* Grid info */
        .info-grid { display:table;width:100%;margin-bottom:14px; }
        .info-half { display:table-cell;width:50%;vertical-align:top;padding-right:10px; }
        .info-box  { background:#f9fafb;border-radius:8px;padding:10px 12px; }
        .info-title{ font-size:8px;font-weight:bold;text-transform:uppercase;
                     color:#9ca3af;margin-bottom:6px;letter-spacing:0.5px; }
        .info-row  { display:table;width:100%;margin-bottom:3px; }
        .info-label{ display:table-cell;width:45%;font-size:9px;color:#6b7280; }
        .info-value{ display:table-cell;font-size:9px;font-weight:600;
                     color:#1f2937;text-align:right; }

        /* Tabla detalles */
        .tabla-titulo { background:#1f2937;color:white;padding:7px 10px;
                        font-size:10px;font-weight:bold;border-radius:6px 6px 0 0; }
        table { width:100%;border-collapse:collapse; }
        thead tr { background:#F59E0B; }
        thead th { padding:7px 8px;text-align:left;font-size:8px;font-weight:bold;
                   text-transform:uppercase;color:white;letter-spacing:0.3px; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        tbody td { padding:6px 8px;border-bottom:1px solid #e5e7eb;
                   font-size:9px;vertical-align:middle; }
        tbody td.right { text-align:right;font-family:monospace; }
        .desc   { font-weight:600;color:#1f2937; }
        .cuenta { font-size:8px;color:#9ca3af;margin-top:1px; }
        .verde  { color:#059669;font-weight:bold; }
        .rojo   { color:#dc2626; }
        .dorado { color:#F59E0B;font-weight:bold; }

        /* Totales */
        .totales       { display:table;width:100%;margin-top:0; }
        .totales-space { display:table-cell;width:55%; }
        .totales-box   { display:table-cell;width:45%; }
        .total-row     { display:table;width:100%;padding:4px 10px; }
        .total-label   { display:table-cell;font-size:9px;color:#6b7280; }
        .total-value   { display:table-cell;text-align:right;
                         font-family:monospace;font-size:9px; }
        .total-final   { background:#1f2937;border-radius:0 0 6px 0; }
        .total-final .total-label { color:white;font-weight:bold;font-size:10px; }
        .total-final .total-value { color:#F59E0B;font-weight:bold;font-size:11px; }

        /* Footer */
        .footer  { margin-top:20px;padding-top:10px;border-top:1px solid #e5e7eb;
                   display:table;width:100%; }
        .f-left  { display:table-cell;font-size:8px;color:#9ca3af; }
        .f-right { display:table-cell;text-align:right;font-size:8px;color:#9ca3af; }
    </style>
</head>
<body>

{{-- HEADER --}}
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} · {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
        <div class="sub" style="margin-top:6px;font-size:10px;font-weight:bold;color:#374151">
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

{{-- INFO GRID --}}
<div class="info-grid">
    {{-- Proveedor --}}
    <div class="info-half">
        <div class="info-box">
            <div class="info-title">Proveedor</div>
            <div style="font-weight:700;font-size:10px;color:#1f2937;margin-bottom:4px">
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

    {{-- Documento --}}
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

{{-- TABLA DETALLES --}}
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
            <td class="right rojo">
                {{ (float)$detalle->descuento > 0 ? '-$'.number_format((float)$detalle->descuento, 2) : '—' }}
            </td>
            <td class="right">${{ number_format((float)$detalle->subtotal, 2) }}</td>
            <td class="right">
                <span style="background:#dbeafe;color:#1e40af;padding:1px 4px;
                             border-radius:4px;font-size:7px;font-weight:bold">
                    {{ number_format((float)$detalle->porcentaje_iva, 0) }}%
                </span>
            </td>
            <td class="right dorado">${{ number_format((float)$detalle->total, 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="7" style="text-align:center;padding:16px;color:#9ca3af">Sin ítems</td></tr>
        @endforelse
    </tbody>
</table>

{{-- TOTALES --}}
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
            <span class="total-value verde">${{ number_format((float)$compra->total_iva, 2) }}</span>
        </div>
        @if($compra->gasto_no_deducible)
        <div class="total-row">
            <span class="total-label rojo" style="font-size:8px">Gasto no deducible</span>
            <span class="total-value"></span>
        </div>
        @endif
        <div class="total-row total-final">
            <span class="total-label">TOTAL</span>
            <span class="total-value">${{ number_format((float)$compra->total, 2) }}</span>
        </div>
    </div>
</div>

{{-- CxP si existe --}}
@if($compra->cuentaPagar)
<div style="margin-top:14px;background:#fef3c7;border-radius:8px;
            padding:10px 12px;border-left:4px solid #F59E0B">
    <span style="font-size:8px;font-weight:bold;text-transform:uppercase;color:#92400e">
        Cuenta por Pagar
    </span>
    <span style="margin-left:12px;font-size:9px;color:#78350f">
        Saldo: ${{ number_format((float)$compra->cuentaPagar->saldo, 2) }} &middot;
        Vence: {{ $compra->cuentaPagar->fecha_vencimiento?->format('d/m/Y') ?? '—' }} &middot;
        Estado: {{ ucfirst($compra->cuentaPagar->estado) }}
    </span>
</div>
@endif

{{-- FOOTER --}}
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
