<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:10px;color:#1A1A2E; }

        .page { padding:20px 24px; }
        .page-break { page-break-after:always; }

        /* Badge de página (Original / Copia/Cliente / Copia/Técnico) */
        .page-label { display:inline-block;padding:3px 10px;margin-bottom:8px;
                      background:#EDEDED;border:1px solid #C7C7C7;border-radius:3px;
                      font-size:9px;font-weight:bold;color:#3A3A3A; }

        /* Header: logo + badge de número */
        .header { display:table;width:100%;margin-bottom:14px; }
        .h-logo { display:table-cell;vertical-align:middle;width:55%; }
        .h-logo img { max-width:230px;max-height:70px; }
        .h-num  { display:table-cell;vertical-align:middle;text-align:right;width:45%; }
        .num-badge { display:inline-block;padding:8px 16px;
                     background:#ACA5E8;border:1px solid #7E74D6;border-radius:5px;
                     font-size:13px;font-weight:bold;color:#26205C; }

        /* Barras de título de sección */
        .seccion { background:#D9D9D9;padding:5px 8px;margin-bottom:8px;
                   font-size:10px;font-weight:bold;color:#1A1A1A; }
        .seccion .sub { font-weight:normal; }

        /* Bloque CLIENTE */
        .cliente-bloque { margin-bottom:12px; }
        .cliente-bloque p { margin-bottom:3px;font-size:9.5px; }
        .cliente-bloque .lbl { font-weight:bold;color:#1A1A2E; }

        /* Tabla EQUIPO */
        .equipo-tabla { width:100%;border-collapse:collapse;margin-bottom:0; }
        .equipo-tabla td { border:1px solid #B9B9B9;padding:6px 10px;
                           font-size:9.5px;vertical-align:top; }
        .img-cell { width:32%; }
        .img-box { width:100%;height:112px;border:1px solid #C7C7C7;border-radius:4px;
                   display:table;text-align:center; }
        .img-box .img-inner { display:table-cell;vertical-align:middle; }
        .img-box img { max-width:100%;max-height:108px; }
        .img-placeholder { color:#3FA34D;font-style:italic;font-size:9px; }
        .eq-lbl { font-weight:bold;color:#1E4E9C;padding-bottom:14px; }
        .eq-val { color:#1A1A2E; }
        .desc-row td { border-top:none; }
        .desc-lbl { font-weight:bold;color:#1A1A2E; }
        .desc-val { color:#1E4E9C;font-weight:bold; }

        /* Bloque TRABAJO SOLICITADO */
        .trabajo-bloque { margin-top:12px;margin-bottom:8px; }
        .trabajo-bloque p { margin-bottom:6px;font-size:9.5px; }
        .trabajo-bloque .lbl { font-weight:bold;color:#1A1A2E; }
        .nota { margin-top:6px;font-size:8.5px;color:#1A1A1A; }

        /* Firmas — margen amplio arriba de la línea para que quepa una
           firma real a mano, no solo el mínimo visual */
        .firmas { display:table;width:100%;margin-top:70px; }
        .firma-col { display:table-cell;width:33.33%;text-align:center;padding:0 8px; }
        .firma-linea { border-top:1px solid #1A1A1A;padding-top:5px;
                       font-size:9px;font-weight:bold;color:#1A1A1A; }
    </style>
</head>
<body>

@php
    $paginas = [
        ['badge' => 'Original',       'cliente' => true,  'firmas' => true],
        ['badge' => 'Copia/Cliente',  'cliente' => true,  'firmas' => true],
        ['badge' => 'Copia/Técnico',  'cliente' => false, 'firmas' => true],
    ];
@endphp

@foreach ($paginas as $i => $pagina)
<div class="page {{ $i < count($paginas) - 1 ? 'page-break' : '' }}">

    <div class="page-label">{{ $pagina['badge'] }}</div>

    <div class="header">
        <div class="h-logo">
            <img src="{{ $logoPath }}">
        </div>
        <div class="h-num">
            <span class="num-badge">ORDEN DE TRABAJO No:{{ $ot?->id ?? '—' }}</span>
        </div>
    </div>

    @if($pagina['cliente'])
    <div class="seccion">CLIENTE</div>
    <div class="cliente-bloque">
        <p><span class="lbl">C.C / Ruc:</span> {{ $ingreso->cliente?->identificacion ?? '—' }}</p>
        <p><span class="lbl">Cliente:</span> {{ $ingreso->cliente?->razon_social ?? '—' }}</p>
        <p><span class="lbl">Telefono:</span> {{ $ingreso->cliente?->telefono ?? '—' }}</p>
        <p><span class="lbl">Direccion:</span> {{ $ingreso->cliente?->direccion ?? '—' }}</p>
        <p><span class="lbl">E-mail:</span> {{ $ingreso->cliente?->email ?? '—' }}</p>
    </div>
    @endif

    <div class="seccion">EQUIPO&nbsp;&nbsp;/&nbsp;&nbsp;
        <span class="sub" style="text-transform:uppercase">{{ $ingreso->equipo?->tipo?->descripcion ?? '—' }}</span>
    </div>
    <table class="equipo-tabla">
        <tr>
            <td class="img-cell" rowspan="2">
                <div class="img-box">
                    <div class="img-inner">
                        @if($ingreso->imagen)
                            <img src="{{ $ingreso->imagen }}">
                        @else
                            <span class="img-placeholder">Imagen del Equipo</span>
                        @endif
                    </div>
                </div>
            </td>
            <td class="eq-lbl" style="width:12%">Modelo:</td>
            <td class="eq-val" style="width:21%">{{ $ingreso->equipo?->modelo ?? '—' }}</td>
            <td class="eq-lbl" style="width:12%">Serie:</td>
            <td class="eq-val" style="width:23%">{{ $ingreso->equipo?->numero_serie ?? '—' }}</td>
        </tr>
        <tr>
            <td class="eq-lbl">Color:</td>
            <td class="eq-val">{{ $ingreso->equipo?->color ?? '—' }}</td>
            <td class="eq-lbl">Medida:</td>
            <td class="eq-val">{{ $ingreso->equipo?->medida ?? '—' }}</td>
        </tr>
        <tr class="desc-row">
            <td class="desc-lbl">Descripcion:</td>
            <td class="desc-val" colspan="4">{{ $ingreso->diagnostico_inicial ?? '—' }}</td>
        </tr>
    </table>

    <div class="seccion" style="margin-top:8px">TRABAJO SOLICITADO&nbsp;&nbsp;/&nbsp;&nbsp;
        <span class="sub">Técnico {{ $ot?->tecnico?->nombre ?? '—' }}</span>
    </div>
    <div class="trabajo-bloque">
        <p><span class="lbl">Descripcion:</span> {{ $ot?->descripcion_trabajo ?? '' }}</p>
        <p><span class="lbl">Fecha Inicio:</span> {{ $ot?->fecha_inicio ? \Carbon\Carbon::parse($ot->fecha_inicio)->format('Y-m-d') : '—' }}</p>
        <p><span class="lbl">Observaciones generales:</span> {{ $ingreso->observaciones ?? '' }}</p>
        <p class="nota">Nota: Si en el lapso de 3 meses luego de la notificación de arreglo su equipo no es retirado podrá ser rematado.</p>
    </div>

    @if($pagina['firmas'])
    <div class="firmas">
        <div class="firma-col"><div class="firma-linea">CLIENTE:</div></div>
        <div class="firma-col"><div class="firma-linea">TÉCNICO:</div></div>
        <div class="firma-col"><div class="firma-linea">APROBADO POR:</div></div>
    </div>
    @endif

</div>
@endforeach

</body>
</html>
