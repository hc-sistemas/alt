<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 10px; color: #1F2D3D; margin: 0; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    .sub { color: #555770; font-size: 9px; }
    table { width: 100%; border-collapse: collapse; }
    td, th { border: 1px solid #D8DCE6; padding: 4px 6px; vertical-align: top; }
    th { background: #F5F7FA; text-align: left; font-size: 9px; text-transform: uppercase; }
    .caja { border: 1px solid #D8DCE6; padding: 8px; margin-bottom: 8px; }
    .etq { color: #555770; font-size: 8px; text-transform: uppercase; }
    .mono { font-family: DejaVu Sans Mono, monospace; }
    .right { text-align: right; }
</style>
</head>
<body>
<table style="border:0"><tr>
    <td style="border:0;width:60%">
        <h1>GUÍA DE REMISIÓN</h1>
        <div class="sub">{{ $guia->empresa->razon_social ?? '' }} · RUC {{ $guia->empresa->ruc ?? '' }}</div>
        <div class="sub">Emitida el {{ \Carbon\Carbon::parse($guia->fecha_emision)->format('d/m/Y') }}</div>
    </td>
    <td style="border:0" class="right">
        <div class="etq">Comprobante N°</div>
        <div class="mono" style="font-size:13px;font-weight:bold">{{ $guia->numero_completo }}</div>
        <div class="etq" style="margin-top:4px">Estado SRI</div>
        <div>{{ strtoupper($guia->estado_sri ?? 'pendiente') }}</div>
    </td>
</tr></table>

<div class="caja" style="margin-top:8px">
    <div class="etq">Clave de acceso (49 dígitos)</div>
    <div class="mono" style="font-size:11px;letter-spacing:.5px">{{ $guia->clave_acceso ?: 'Pendiente de generación: se asigna al enviar la guía al SRI' }}</div>
    <div class="etq" style="margin-top:4px">Número de autorización</div>
    <div class="mono">{{ $guia->autorizacion ?: 'Pendiente de autorización del SRI' }}</div>
    {{-- Código QR autorizado por el SRI: la definición (DEC-039) está pendiente, por eso no se dibuja todavía --}}
    <div class="sub" style="margin-top:4px">Código QR del SRI: disponible cuando la guía esté autorizada (decisión DEC-039 pendiente).</div>
</div>

<table>
    <tr><th colspan="2">Transportista</th><th colspan="2">Traslado</th></tr>
    <tr>
        <td><span class="etq">RUC / cédula</span><br>{{ $guia->transportista->identificacion ?? '—' }}</td>
        <td><span class="etq">Razón social</span><br>{{ $guia->transportista->razon_social ?? '—' }}</td>
        <td><span class="etq">Fecha de inicio</span><br>{{ $guia->fecha_inicio_transporte ? \Carbon\Carbon::parse($guia->fecha_inicio_transporte)->format('d/m/Y') : '—' }}</td>
        <td><span class="etq">Fecha de fin</span><br>{{ $guia->fecha_fin_transporte ? \Carbon\Carbon::parse($guia->fecha_fin_transporte)->format('d/m/Y') : '—' }}</td>
    </tr>
    <tr>
        <td><span class="etq">Placa del vehículo</span><br>{{ $guia->transportista->placa ?? '—' }}</td>
        <td><span class="etq">Motivo</span><br>{{ $guia->motivo }}</td>
        <td><span class="etq">Punto de partida</span><br>{{ $guia->origen ?? '—' }}</td>
        <td><span class="etq">Destino</span><br>{{ $guia->destino ?? '—' }}</td>
    </tr>
    <tr>
        <td colspan="2"><span class="etq">Documento sustento (factura)</span><br><span class="mono">{{ $guia->factura->numero_completo ?? '—' }}</span></td>
        <td colspan="2"><span class="etq">Ruta</span><br>{{ $guia->ruta ?? '—' }}</td>
    </tr>
</table>

<p class="etq" style="margin:10px 0 3px">Detalle físico de los productos transportados</p>
<table>
    <thead><tr><th style="width:18%">Código</th><th>Descripción</th><th style="width:14%">N° de serie</th><th class="right" style="width:10%">Cantidad</th></tr></thead>
    <tbody>
    @foreach($guia->detalles as $d)
        <tr>
            <td class="mono">{{ $codigos[$d->producto_id] ?? '—' }}</td>
            <td>{{ $d->descripcion }}</td>
            <td class="mono">{{ $d->numero_serie ?? '' }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float) $d->cantidad, 2, '.', ''), '0'), '.') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p class="sub" style="margin-top:14px">Impreso por {{ auth()->user()?->nombre ?? '—' }} · {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
