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
        .r { text-align:right; } .c { text-align:center; } .b { font-weight:bold; }
        .titulo   { font-size:16px; font-weight:bold; margin-bottom:6px; }
        .grande   { font-size:10px; font-weight:bold; margin-top:4px; }
        .emisor-n { font-size:12px; font-weight:bold; margin-bottom:5px; }
        .emisor-l { font-size:7.5px; font-weight:bold; margin-top:4px; line-height:1.35; }
        .det th { border:1px solid #000; padding:4px 4px; font-size:8.5px; text-align:center; }
        .det td { border:1px solid #000; padding:2px 4px; font-size:7.5px; }
        .tot td { border:1px solid #000; padding:3px 6px; font-size:8.5px; }
        .tot td.l { width:65%; }
        .info td { border:none; padding:2px 0; font-size:8.5px; }
        .ab th, .ab td { border:1px solid #000; padding:3px 6px; font-size:8px; text-align:left; }
        .aviso { border:1px solid #F59E0B; background:#FFF7E0; color:#8A5A00; padding:4px 8px; font-size:7.5px; margin-top:6px; }
        .anulada { text-align:center; font-size:34px; font-weight:bold; color:#DC2626; border:3px solid #DC2626; padding:4px; margin-bottom:8px; }
    </style>
</head>
<body>
@php
    $emp = $doc['empresa'];
    $cli = $doc['cliente'];
    $fmt = fn ($n) => number_format((float) $n, 2, '.', '');
    $sinImpuestos = $doc['subtotal15'] + $doc['subtotal0'];
    $formaTexto = [
        'efectivo' => 'Efectivo', 'transferencia' => 'Transferencia bancaria', 'tarjeta' => 'Tarjeta de crédito',
        'cheque' => 'Cheque', 'credito' => 'Crédito',
    ];
@endphp

    @if($doc['estado'] === 'anulada')
        <div class="anulada">ANULADA</div>
    @endif

    <table class="w100">
        <tr>
            <td style="width:50%; vertical-align:top; padding-right:12px;">
                <div style="height:74px; margin-bottom:8px;">
                    @if($doc['logo'])
                        <img src="{{ $doc['logo'] }}" style="max-height:70px; max-width:100%;">
                    @else
                        <div class="emisor-n">{{ $emp->nombre_comercial ?: $emp->razon_social }}</div>
                    @endif
                </div>
                <div class="box">
                    <div class="emisor-n">{{ $emp->razon_social }}</div>
                    <div class="emisor-l">Dirección Matriz: {{ $emp->direccion_matriz }}</div>
                    @if($emp->telefono)<div class="emisor-l">Teléfono: {{ $emp->telefono }}</div>@endif
                    <div class="emisor-l" style="margin-top:8px;">RUC: {{ $emp->ruc }}</div>
                </div>
            </td>
            <td style="width:50%; vertical-align:top;">
                <div class="box" style="min-height:120px;">
                    <div class="titulo">{{ $doc['titulo'] }}</div>
                    <div class="grande">No. &nbsp;{{ $doc['numero'] }}</div>
                    <div class="grande" style="font-weight:normal;">Fecha de emisión: {{ $doc['fecha'] }}</div>
                    @if($doc['vencimiento'])
                        <div class="grande" style="font-weight:normal;">Válida hasta: {{ $doc['vencimiento'] }}</div>
                    @endif
                    <div class="grande">ESTADO: {{ strtoupper($doc['estado']) }}</div>
                    <div class="grande" style="font-weight:normal;">Vendedor: {{ $doc['vendedor'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="aviso">Documento sin validez tributaria: no es una factura ni un comprobante autorizado por el SRI.</div>

    <table class="w100" style="margin-top:8px; border:1px solid #000;">
        <tr>
            <td style="padding:4px 8px; width:68%;" class="b">Razón Social / Nombres y Apellidos : {{ $cli?->razon_social }}</td>
            <td style="padding:4px 8px;" class="b">Identificación : {{ $cli?->identificacion }}</td>
        </tr>
        <tr>
            <td style="padding:4px 8px;" class="b">Dirección : {{ $cli?->direccion }}</td>
            <td style="padding:4px 8px;" class="b">Teléfono : {{ $cli?->telefono }}</td>
        </tr>
    </table>

    <table class="w100 det" style="margin-top:8px;">
        <thead>
            <tr>
                <th style="width:14%;">COD.<br>PRINCIPAL</th>
                <th style="width:7%;">CANT.</th>
                <th>DESCRIPCION</th>
                <th style="width:11%;">PRECIO<br>UNITARIO</th>
                <th style="width:9%;">DESC%</th>
                <th style="width:11%;">PRECIO<br>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($doc['detalles'] as $d)
                <tr>
                    <td>{{ $d['codigo'] }}</td>
                    <td class="c">{{ rtrim(rtrim(number_format($d['cant'], 4, '.', ''), '0'), '.') }}</td>
                    <td>{{ $d['descripcion'] }}</td>
                    <td class="r">{{ $fmt($d['precio']) }}</td>
                    <td class="r">{{ $fmt($d['pct']) }}</td>
                    <td class="r">{{ $fmt($d['neto']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="w100" style="margin-top:8px;">
        <tr>
            <td style="width:58%; vertical-align:top; padding-right:12px;">
                <div class="box">
                    <div class="b" style="font-size:10px; margin-bottom:4px;">Información Adicional</div>
                    <table class="info">
                        @if($cli?->email)<tr><td>Email : {{ $cli->email }}</td></tr>@endif
                        @if($doc['observaciones'])
                            <tr><td class="b" style="padding-top:4px;">Observaciones :</td></tr>
                            <tr><td class="b">{!! nl2br(e($doc['observaciones'])) !!}</td></tr>
                        @endif
                    </table>
                </div>

                @if(!empty($doc['abonos']))
                    <table class="ab" style="margin-top:8px; width:90%;">
                        <tr><th>Abono</th><th>Forma</th><th style="width:22%;">Valor</th></tr>
                        @foreach($doc['abonos'] as $a)
                            <tr><td>{{ $a['fecha'] }}</td><td>{{ $formaTexto[$a['forma']] ?? $a['forma'] }}</td><td>${{ $fmt($a['valor']) }}</td></tr>
                        @endforeach
                        <tr class="b"><td colspan="2">Total abonado</td><td>${{ $fmt($doc['abonado'] ?? 0) }}</td></tr>
                        <tr class="b"><td colspan="2">Saldo pendiente</td><td>${{ $fmt($doc['saldo'] ?? 0) }}</td></tr>
                    </table>
                @endif
            </td>
            <td style="width:42%; vertical-align:top;">
                <table class="w100 tot">
                    <tr><td class="l">SUBTOTAL 15%</td><td class="r">{{ $fmt($doc['subtotal15']) }}</td></tr>
                    <tr><td class="l">SUBTOTAL 0%</td><td class="r">{{ $fmt($doc['subtotal0']) }}</td></tr>
                    <tr><td class="l">SUBTOTAL SIN IMPUESTOS</td><td class="r">{{ $fmt($sinImpuestos) }}</td></tr>
                    <tr><td class="l">DESCUENTO</td><td class="r">{{ $fmt($doc['descuento']) }}</td></tr>
                    <tr><td class="l">IVA 15%</td><td class="r">{{ $fmt($doc['iva']) }}</td></tr>
                    <tr class="b"><td class="l">VALOR TOTAL</td><td class="r">{{ $fmt($doc['total']) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
