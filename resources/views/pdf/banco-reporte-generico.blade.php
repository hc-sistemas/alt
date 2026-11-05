<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;color:#1A1A2E;padding:16px 20px; }
        .header { margin-bottom:12px;padding-bottom:10px;border-bottom:2px solid #1F2D3D; }
        .empresa { font-size:14px;font-weight:bold; }
        .titulo  { font-size:12px;font-weight:bold;margin-top:3px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }
        h3 { font-size:10px;margin:12px 0 5px;color:#1F2D3D; }
        table { width:100%;border-collapse:collapse;margin-bottom:6px; }
        thead tr { background:#1F2D3D; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;font-weight:bold;text-transform:uppercase;color:white; }
        thead th.n, td.n { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:5px 7px;border-bottom:1px solid #D8DCE6;font-size:8.5px; }
        td.n { font-family:monospace; }
        tr.total td { background:#1F2D3D;color:white;font-weight:bold;padding:7px; }
        .vacio { padding:10px;color:#555770;font-style:italic; }
        .footer { margin-top:10px;padding-top:8px;border-top:1px solid #D8DCE6;font-size:7px;color:#555770; }
    </style>
</head>
<body>
<div class="header">
    <div class="empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
    <div class="titulo">{{ $datos['titulo'] }}</div>
    <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} &middot; {{ $datos['subtitulo'] ?? '' }} &middot; Generado: {{ now()->format('d/m/Y H:i') }}</div>
</div>

@forelse ($datos['secciones'] as $sec)
    @if (!empty($sec['titulo']))<h3>{{ $sec['titulo'] }}</h3>@endif
    <table>
        <thead>
            <tr>
                @foreach ($sec['columnas'] as $c)
                    <th class="{{ $c['n'] ? 'n' : '' }}">{{ $c['t'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($sec['filas'] as $fila)
                <tr>
                    @foreach ($fila as $i => $v)
                        <td class="{{ ($sec['columnas'][$i]['n'] ?? false) ? 'n' : '' }}">{{ $v }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td class="vacio" colspan="{{ count($sec['columnas']) }}">Sin registros para los filtros indicados.</td></tr>
            @endforelse
            @if (!empty($sec['totales']))
                <tr class="total">
                    @foreach ($sec['totales'] as $i => $v)
                        <td class="{{ ($sec['columnas'][$i]['n'] ?? false) ? 'n' : '' }}">{{ $v }}</td>
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>
@empty
    <p class="vacio">Sin datos para mostrar.</p>
@endforelse

<div class="footer">Altamira ERP &middot; {{ $datos['titulo'] }}</div>
</body>
</html>
