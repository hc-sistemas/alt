<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    @page {
        size: 255.12pt 99.21pt;
        margin: 0;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: Arial, Helvetica, sans-serif;
        color: #000;
        background: #fff;
    }

    .etiqueta {
        width: 255.12pt;
        text-align: center;
        padding: 5pt 7pt 0 7pt;
        overflow: hidden;
    }

    .marca {
        font-size: 8pt;
        font-weight: bold;
        letter-spacing: 0.5pt;
        margin-top: 8pt;
        margin-bottom: 3pt;
    }

    .barcode-img {
        width: 218pt;
        height: 44pt;
        display: block;
        margin: 0 auto 3pt auto;
    }

    .cod-label {
        font-size: 7.5pt;
        font-weight: bold;
        font-family: 'Courier New', Courier, monospace;
        letter-spacing: 0.3pt;
        margin-bottom: 2pt;
    }

    .nombre-label {
        font-size: 7pt;
        color: #222;
        white-space: nowrap;
        overflow: hidden;
    }
</style>
</head>
<body>
@foreach ($etiquetas as $etiqueta)
<div class="etiqueta"{{ !$loop->first ? ' style="page-break-before: always;"' : '' }}>
    <p class="marca">ALTAMIRA LIGHT &amp; SOUND</p>
    <img class="barcode-img"
         src="data:image/png;base64,{{ $etiqueta['barcode_png'] }}"
         alt="{{ $etiqueta['codigo_barras'] }}">
    <p class="cod-label">{{ $etiqueta['codigo_barras'] }}</p>
    <p class="nombre-label">{{ $etiqueta['descripcion'] }}</p>
</div>
@endforeach
</body>
</html>
