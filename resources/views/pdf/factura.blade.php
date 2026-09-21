<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8.5px; color:#000; padding:24px 28px; }
        table { border-collapse:collapse; }
        .w100 { width:100%; }
        .box  { border:1px solid #000; padding:6px 8px; }
        .r    { text-align:right; }
        .c    { text-align:center; }
        .b    { font-weight:bold; }

        .titulo   { font-size:15px; font-weight:bold; margin-bottom:5px; }
        .auth-tit { font-size:10px; font-weight:bold; margin-top:5px; }
        .grande   { font-size:10px; font-weight:bold; margin-top:3px; }
        .mono     { font-family:'DejaVu Sans Mono',monospace; font-size:8px; }
        .emisor-n { font-size:12px; font-weight:bold; margin-bottom:5px; }
        .emisor-l { font-size:7.5px; font-weight:bold; margin-top:4px; line-height:1.35; }

        .det th { border:1px solid #000; padding:4px 4px; font-size:8.5px; text-align:center; }
        .det td { border:1px solid #000; padding:2px 4px; font-size:7.5px; }

        .tot td { border:1px solid #000; padding:3px 6px; font-size:8.5px; }
        .tot td.l { width:65%; }
        .info td { border:none; padding:2px 0; font-size:8.5px; }
        .pago th, .pago td { border:1px solid #000; padding:3px 6px; font-size:8px; text-align:left; }

        .aviso { border:1px solid #F59E0B; background:#FFF7E0; color:#8A5A00; padding:4px 8px;
                 font-size:7.5px; margin-top:6px; }
        .anulada { text-align:center; font-size:34px; font-weight:bold; color:#DC2626;
                   border:3px solid #DC2626; padding:4px; margin-bottom:8px; }
    </style>
</head>
<body>
@php
    $emp = $factura->empresa;
    $formaTexto = [
        'efectivo'      => 'Sin utilización del sistema financiero',
        'transferencia' => 'Otros con utilización del sistema financiero',
        'cheque'        => 'Otros con utilización del sistema financiero',
        'tarjeta'       => 'Tarjeta de crédito',
        'credito'       => 'Crédito',
    ];
    $sinImpuestos = (float) $factura->subtotal_0 + (float) $factura->subtotal_15 + (float) $factura->subtotal_exento;
    $fmt = fn ($n) => number_format((float) $n, 2, '.', '');
    $especial = $emp->contribuyente_especial;
@endphp

    @if($factura->estado === 'anulada')
        <div class="anulada">ANULADA</div>
    @endif

    {{-- ── Encabezado: logo + emisor | recuadro de autorización ── --}}
    <table class="w100">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:12px;">
                <div style="height:74px; margin-bottom:8px;">
                    @if($logo)
                        <img src="{{ $logo }}" style="max-height:70px; max-width:100%;">
                    @else
                        <div class="emisor-n">{{ $emp->nombre_comercial ?: $emp->razon_social }}</div>
                    @endif
                </div>
                <div class="box">
                    <div class="emisor-n">{{ $emp->razon_social }}</div>
                    @if($emp->nombre_comercial && $emp->nombre_comercial !== $emp->razon_social)
                        <div class="emisor-l">Nombre comercial: {{ $emp->nombre_comercial }}</div>
                    @endif
                    <div class="emisor-l">Dirección Matriz: {{ $emp->direccion_matriz }}</div>
                    @if($emp->direccion_establecimiento)
                        <div class="emisor-l">Dirección Sucursal: {{ $emp->direccion_establecimiento }}</div>
                    @endif
                    <div class="emisor-l" style="margin-top:8px;">RUC: {{ $emp->ruc }}</div>
                    @if($especial)
                        <div class="emisor-l">Contribuyente Especial Nro: {{ is_bool($especial) ? '' : $especial }}</div>
                    @endif
                    <div class="emisor-l">OBLIGADO A LLEVAR CONTABILIDAD: {{ $emp->obligado_contabilidad ? 'SI' : 'NO' }}</div>
                </div>
            </td>
            <td style="width:50%; vertical-align:top;">
                <div class="box" style="min-height:190px;">
                    <div class="titulo">FACTURA</div>
                    <div class="grande">No. &nbsp;{{ $factura->numero_completo }}</div>
                    <div class="auth-tit">NÚMERO DE AUTORIZACIÓN</div>
                    <div class="mono">{{ $factura->autorizacion ?: 'PENDIENTE DE AUTORIZACIÓN' }}</div>
                    <div class="auth-tit">FECHA Y HORA DE AUTORIZACIÓN:</div>
                    <div class="grande" style="font-weight:normal;">{{ $factura->fecha_hora_aut ?: '—' }}</div>
                    <div class="grande">AMBIENTE : {{ (int) $emp->ambiente_sri === 2 ? 'PRODUCCIÓN' : 'PRUEBAS' }}</div>
                    <div class="grande">EMISIÓN : &nbsp;NORMAL</div>
                    <div class="auth-tit">CLAVE DE ACCESO</div>
                    @if($barcode)
                        <div class="c" style="margin-top:4px;"><img src="{{ $barcode }}" style="height:34px; width:100%;"></div>
                    @endif
                    <div class="mono c" style="margin-top:2px;">{{ $clave }}</div>
                </div>
            </td>
        </tr>
    </table>

    @if($factura->estado_sri !== 'autorizada')
        <div class="aviso">Documento pendiente de autorización del SRI — sin validez tributaria hasta su autorización.</div>
    @endif

    {{-- ── Cliente ── --}}
    <table class="w100" style="margin-top:8px; border:1px solid #000;">
        <tr>
            <td style="padding:4px 8px; width:68%;" class="b">Razón Social / Nombres y Apellidos : {{ $factura->razon_social }}</td>
            <td style="padding:4px 8px;" class="b">Identificación : {{ $factura->identificacion }}</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;" class="b">Fecha de Emisión : {{ $factura->fecha_emision->format('Y-m-d') }} {{ $factura->hora_emision }}</td>
            <td style="padding:4px 8px;" class="b">Guía Remisión : {{ $factura->guia_remision }}</td>
        </tr>
    </table>

    {{-- ── Detalle ── --}}
    <table class="w100 det" style="margin-top:8px;">
        <thead>
            <tr>
                <th style="width:14%;">COD.<br>PRINCIPAL</th>
                <th style="width:7%;">CANT.</th>
                <th>DESCRIPCION</th>
                <th style="width:11%;">PRECIO<br>UNITARIO</th>
                <th style="width:9%;">DESC$</th>
                <th style="width:11%;">PRECIO<br>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($factura->detalles as $d)
                <tr>
                    <td>{{ $d->codigo_producto }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($d->cantidad, 4, '.', ''), '0'), '.') }}</td>
                    <td>{{ $d->descripcion }}</td>
                    <td class="r">{{ $fmt($d->precio_unitario) }}</td>
                    <td class="r">{{ $fmt($d->descuento_valor) }}</td>
                    <td class="r">{{ $fmt($d->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- ── Información adicional / forma de pago | totales ── --}}
    <table class="w100" style="margin-top:8px;">
        <tr>
            <td style="width:58%; vertical-align:top; padding-right:12px;">
                <div class="box">
                    <div class="b" style="font-size:10px; margin-bottom:4px;">Información Adicional</div>
                    <table class="info">
                        <tr><td>VENDEDOR: {{ $factura->usuario?->nombre }}</td></tr>
                        @if($factura->direccion_cliente)<tr><td>Dirección : {{ $factura->direccion_cliente }}</td></tr>@endif
                        @if($factura->telefono_cliente)<tr><td>Teléfono : {{ $factura->telefono_cliente }}</td></tr>@endif
                        @if($factura->email_cliente)<tr><td>Email : {{ $factura->email_cliente }}</td></tr>@endif
                        @if($factura->observaciones)
                            <tr><td class="b" style="padding-top:4px;">Observaciones :</td></tr>
                            <tr><td class="b">{!! nl2br(e($factura->observaciones)) !!}</td></tr>
                        @endif
                    </table>
                </div>

                <table class="pago" style="margin-top:8px; width:85%;">
                    <tr><th>Forma de pago</th><th style="width:22%;">Valor</th></tr>
                    @foreach($factura->pagos as $p)
                        <tr class="b">
                            <td>{{ $formaTexto[$p->forma_pago] ?? ucfirst($p->forma_pago) }}@if($p->forma_pago === 'credito' && (int) $p->plazo > 0) ({{ (int) $p->plazo }} días)@endif</td>
                            <td>${{ $fmt($p->valor) }}</td>
                        </tr>
                    @endforeach
                </table>
            </td>
            <td style="width:42%; vertical-align:top;">
                <table class="w100 tot">
                    <tr><td class="l">SUBTOTAL 15%</td><td class="r">{{ $fmt($factura->subtotal_15) }}</td></tr>
                    <tr><td class="l">SUBTOTAL 0%</td><td class="r">{{ $fmt($factura->subtotal_0) }}</td></tr>
                    <tr><td class="l">SUBTOTAL No objeto de IVA</td><td class="r">0.00</td></tr>
                    <tr><td class="l">SUBTOTAL Exento de IVA</td><td class="r">{{ $fmt($factura->subtotal_exento) }}</td></tr>
                    <tr><td class="l">SUBTOTAL SIN IMPUESTOS</td><td class="r">{{ $fmt($sinImpuestos) }}</td></tr>
                    <tr><td class="l">DESCUENTO</td><td class="r">{{ $fmt($factura->descuento_total) }}</td></tr>
                    <tr><td class="l">ICE</td><td class="r">{{ $fmt($factura->total_ice) }}</td></tr>
                    <tr><td class="l">IVA 15%</td><td class="r">{{ $fmt($factura->total_iva) }}</td></tr>
                    <tr><td class="l">IRBPNR</td><td class="r">0.00</td></tr>
                    <tr><td class="l">PROPINA</td><td class="r">0.00</td></tr>
                    <tr class="b"><td class="l">VALOR TOTAL</td><td class="r">{{ $fmt($factura->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
