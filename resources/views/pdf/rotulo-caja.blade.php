<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 12mm; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 13px; color: #111; margin: 0; }
    .rotulo { border: 2px solid #111; padding: 14px 16px; margin-bottom: 12px; page-break-inside: avoid; }
    .titulo { font-size: 11px; letter-spacing: 1px; text-transform: uppercase; color: #555; }
    .nombre { font-size: 22px; font-weight: bold; margin: 2px 0 8px; }
    .linea { margin: 3px 0; }
    .etq { display: inline-block; width: 90px; color: #555; font-size: 11px; text-transform: uppercase; }
    .grande { font-size: 18px; font-weight: bold; }
    .pie { margin-top: 10px; border-top: 1px dashed #999; padding-top: 6px; font-size: 11px; color: #555; }
</style>
</head>
<body>
@for ($i = 1; $i <= $bultos; $i++)
<div class="rotulo">
    <div class="titulo">{{ $remitente }} · Rótulo de envío</div>
    <div class="nombre">{{ $destinatario }}</div>
    <div class="linea"><span class="etq">Dirección</span> {{ $direccion }}</div>
    @if($ciudad)<div class="linea"><span class="etq">Ciudad</span> {{ $ciudad }}</div>@endif
    @if($telefono)<div class="linea"><span class="etq">Teléfono</span> {{ $telefono }}</div>@endif
    @if($identificacion)<div class="linea"><span class="etq">CI / RUC</span> {{ $identificacion }}</div>@endif
    <div class="linea"><span class="etq">Factura</span> {{ $factura }}</div>
    <div class="linea"><span class="etq">Fecha</span> {{ $fecha }}</div>
    <div class="linea grande"><span class="etq">Bulto</span> {{ $i }} de {{ $bultos }}</div>
    @if($observaciones)<div class="pie">{{ $observaciones }}</div>@endif
</div>
@endfor
</body>
</html>
