<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de ingreso a taller {{ $ot?->numero }}</title>
    <style>
        @page { margin: 22px 28px; }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans', Arial, sans-serif; font-size:9px; color:#1F2937; background:#FFFFFF; }

        /* Un solo color de acento de la marca para títulos y líneas */
        .acento { color:#B45309; }

        .encabezado { display:table; width:100%; padding-bottom:8px; border-bottom:2px solid #B45309; margin-bottom:10px; }
        .enc-logo  { display:table-cell; vertical-align:middle; width:30%; }
        .enc-logo img { max-width:150px; max-height:52px; }
        .enc-datos { display:table-cell; vertical-align:middle; width:70%; text-align:right; line-height:1.45; }
        .enc-datos .marca { font-size:12px; font-weight:bold; color:#B45309; }

        .titulo { display:table; width:100%; margin-bottom:10px; }
        .titulo-txt { display:table-cell; vertical-align:middle; }
        .titulo-txt h1 { font-size:14px; color:#B45309; }
        .titulo-txt .meta { margin-top:3px; color:#4B5563; }
        .titulo-ot { display:table-cell; vertical-align:middle; text-align:right; width:38%; }
        .ot-numero { display:inline-block; padding:6px 12px; border:2px solid #B45309; border-radius:4px;
                     font-size:14px; font-weight:bold; color:#B45309; }

        .bloque { margin-bottom:9px; }
        .bloque h2 { font-size:9.5px; color:#B45309; text-transform:uppercase; letter-spacing:.4px;
                     padding-bottom:2px; border-bottom:1px solid #B45309; margin-bottom:4px; }
        table.datos { width:100%; border-collapse:collapse; }
        table.datos td { padding:2.5px 4px; border-bottom:1px solid #E5E7EB; vertical-align:top; }
        table.datos td.l { width:17%; font-weight:bold; color:#374151; }
        table.datos td.v { width:33%; }
        .texto { padding:3px 4px; min-height:14px; }

        table.lista { width:100%; border-collapse:collapse; }
        table.lista th { text-align:left; padding:3px 4px; border-bottom:1px solid #B45309; font-size:8.5px; color:#374151; }
        table.lista td { padding:2.5px 4px; border-bottom:1px solid #E5E7EB; font-size:8.5px; }

        .fotos { display:table; width:100%; }
        .foto { display:table-cell; width:25%; text-align:center; padding:0 3px; }
        .foto .caja { height:78px; border:1px solid #D1D5DB; border-radius:3px; display:table; width:100%; }
        .foto .caja span { display:table-cell; vertical-align:middle; }
        .foto img { max-width:100%; max-height:74px; }
        .foto.vacia .caja { border-style:dashed; color:#9CA3AF; font-size:8px; }

        .condiciones { font-size:8px; color:#374151; line-height:1.5; white-space:pre-line; }

        .firmas { display:table; width:100%; margin-top:46px; }
        .firma { display:table-cell; width:50%; text-align:center; padding:0 22px; }
        .firma .linea { border-top:1px solid #1F2937; padding-top:3px; font-weight:bold; }
        .firma .sub { color:#6B7280; font-size:8px; margin-top:1px; }

        .pie { margin-top:14px; padding-top:5px; border-top:2px solid #B45309; text-align:center; color:#374151; font-size:8.5px; }
        .pie .imp { color:#9CA3AF; font-size:7.5px; margin-top:2px; }
    </style>
</head>
<body>
@php
    $equipo  = $ingreso->equipo;
    $cliente = $ingreso->cliente;
    $fechaIng = $ingreso->fecha ? \Carbon\Carbon::parse($ingreso->fecha)->format('d/m/Y') : '—';
    $horaIng  = $ingreso->hora ? substr($ingreso->hora, 0, 5) : '';
@endphp

{{-- 1. Encabezado --}}
<div class="encabezado">
    <div class="enc-logo"><img src="{{ $logoPath }}" alt="Altamira Light &amp; Sound"></div>
    <div class="enc-datos">
        <div class="marca">Altamira FIX — Servicio técnico</div>
        <div>{{ $empresa?->razon_social ?? 'Altamira Light & Sound' }} · RUC {{ $empresa?->ruc ?? '—' }}</div>
        <div>{{ $empresa?->direccion_establecimiento ?: ($empresa?->direccion_matriz ?? '') }}</div>
        <div>{{ $empresa?->telefono ? 'Tel. ' . $empresa->telefono : '' }}</div>
    </div>
</div>

{{-- 2. Título con el número de OT --}}
<div class="titulo">
    <div class="titulo-txt">
        <h1>Orden de ingreso a taller</h1>
        <div class="meta">Fecha y hora de ingreso: {{ $fechaIng }} {{ $horaIng }} · Recibido por: {{ $recibidoPor ?? '—' }}</div>
    </div>
    <div class="titulo-ot"><span class="ot-numero">{{ $ot?->numero ?? '—' }}</span></div>
</div>

{{-- 3. Cliente --}}
<div class="bloque">
    <h2>Datos del cliente</h2>
    <table class="datos">
        <tr>
            <td class="l">Nombre</td><td class="v">{{ $cliente?->razon_social ?? '—' }}</td>
            <td class="l">RUC / Cédula</td><td class="v">{{ $cliente?->identificacion ?? '—' }}</td>
        </tr>
        <tr>
            <td class="l">Teléfono</td><td class="v">{{ $cliente?->telefono ?? '—' }}</td>
            <td class="l">Correo</td><td class="v">{{ $cliente?->email ?? '—' }}</td>
        </tr>
    </table>
</div>

{{-- 4. Equipo --}}
<div class="bloque">
    <h2>Datos del equipo</h2>
    <table class="datos">
        <tr>
            <td class="l">Tipo</td><td class="v">{{ $equipo?->tipo?->descripcion ?? '—' }}</td>
            <td class="l">Marca</td><td class="v">{{ $equipo?->marca ?? '—' }}</td>
        </tr>
        <tr>
            <td class="l">Modelo</td><td class="v">{{ $equipo?->modelo ?? '—' }}</td>
            <td class="l">N° de serie</td><td class="v">{{ $equipo?->numero_serie ?? '—' }}</td>
        </tr>
        <tr>
            <td class="l">Color</td><td class="v">{{ $equipo?->color ?? '—' }}</td>
            <td class="l">Medida</td><td class="v">{{ $equipo?->medida ?? '—' }}</td>
        </tr>
        <tr>
            <td class="l">Adicional</td><td class="v">{{ $equipo?->adicional ?? '—' }}</td>
            <td class="l">Observaciones</td><td class="v">{{ $equipo?->observaciones ?? '—' }}</td>
        </tr>
    </table>
</div>

{{-- 5. Problema reportado y trabajo a realizar --}}
<div class="bloque">
    <h2>Problema reportado</h2>
    <div class="texto">{{ $ingreso->diagnostico_inicial ?: '—' }}</div>
</div>
<div class="bloque">
    <h2>Trabajo a realizar</h2>
    <div class="texto">{{ $ot?->descripcion_trabajo ?: '—' }}</div>
    @if($ingreso->observaciones)
        <div class="texto"><strong>Observaciones:</strong> {{ $ingreso->observaciones }}</div>
    @endif
</div>

{{-- 6. Componentes revisados y accesorios recibidos --}}
<div class="bloque">
    <h2>Componentes revisados y accesorios recibidos</h2>
    @if($ingreso->componentes->isNotEmpty())
        <table class="lista">
            <tr><th style="width:34%">Componente</th><th style="width:12%">Funciona</th><th style="width:16%">Acción</th><th>Detalle</th></tr>
            @foreach($ingreso->componentes as $c)
                <tr>
                    <td>{{ $c->nombre }}</td>
                    <td>{{ $c->funciona ? 'Sí' : 'No' }}</td>
                    <td>{{ (int) $c->accion === 1 ? 'Reemplazo' : 'Reparación' }}</td>
                    <td>{{ $c->descripcion }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="texto">Sin componentes registrados.</div>
    @endif
</div>

{{-- 7. Fotos del equipo (hasta 4) --}}
<div class="bloque">
    <h2>Fotos del equipo</h2>
    <div class="fotos">
        @for($i = 0; $i < 4; $i++)
            @if(isset($fotos[$i]))
                <div class="foto"><div class="caja"><span><img src="{{ $fotos[$i] }}" alt="Foto {{ $i + 1 }}"></span></div></div>
            @else
                <div class="foto vacia"><div class="caja"><span>—</span></div></div>
            @endif
        @endfor
    </div>
</div>

{{-- 8. Técnico --}}
<div class="bloque">
    <h2>Técnico asignado</h2>
    <div class="texto">{{ $ot?->tecnico?->nombre ?? 'Sin asignar' }}</div>
</div>

{{-- 9. Condiciones del servicio (texto configurable: Taller > Configuración) --}}
<div class="bloque">
    <h2>Condiciones del servicio</h2>
    <div class="condiciones">{{ $condiciones ?: '' }}</div>
</div>

{{-- 10. Firmas --}}
<div class="firmas">
    <div class="firma"><div class="linea">Entregado por (cliente)</div><div class="sub">Fecha: ____ / ____ / ________</div></div>
    <div class="firma"><div class="linea">Recibido por (Altamira)</div><div class="sub">Fecha: ____ / ____ / ________</div></div>
</div>

{{-- 11. Pie --}}
<div class="pie">
    Conserve este documento para retirar su equipo. <strong>{{ $ot?->numero ?? '' }}</strong>
    <div class="imp">Impreso el {{ now()->format('d/m/Y H:i') }}</div>
</div>

</body>
</html>
