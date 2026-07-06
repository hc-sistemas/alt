<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9px;color:#1A1A2E;padding:16px 20px; }
        .header { display:table;width:100%;margin-bottom:14px;
                  padding-bottom:10px;border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell;vertical-align:middle;width:65%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:35%; }
        .empresa { font-size:15px;font-weight:bold;color:#1A1A2E; }
        .titulo  { font-size:12px;font-weight:bold;color:#1A1A2E;margin-top:3px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }

        .resumen { display:table;width:100%;margin-bottom:12px;
                   border:1px solid #D8DCE6; }
        .res-item { display:table-cell;text-align:center;padding:7px;
                    border-right:1px solid #D8DCE6; }
        .res-item:last-child { border-right:none; }
        .res-label { font-size:7px;font-weight:bold;text-transform:uppercase;
                     color:#555770;letter-spacing:0.3px; }
        .res-valor { font-size:14px;font-weight:bold;margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1F2D3D; }
        thead th { padding:6px 7px;text-align:left;font-size:8px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody td { padding:5px 7px;border-bottom:1px solid #D8DCE6;
                   font-size:8.5px;color:#1A1A2E; }
        .badge { display:inline-block;padding:1px 6px;border-radius:3px;
                 font-size:7px;font-weight:bold;border:1px solid #D8DCE6; }
        .b-nac { background:#EEF2F8;color:#2C5F8A; }
        .b-int { background:#F5F7FA;color:#555770; }
        .b-act { background:#EEF2F8;color:#1A1A2E; }
        .b-ina { background:#F5F7FA;color:#555770; }
        .b-cred{ background:#EEF2F8;color:#2C5F8A; }
        .footer { margin-top:12px;padding-top:8px;
                  border-top:1px solid #D8DCE6;display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#555770; }
        .f-r { display:table-cell;text-align:right;font-size:7px;color:#555770; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">
            {{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}
        </div>
        <div class="titulo">CATÁLOGO DE PROVEEDORES</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot;
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1A1A2E">
            {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">Total: {{ $proveedores->count() }} proveedores</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Total</div>
        <div class="res-valor">{{ $proveedores->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Nacionales</div>
        <div class="res-valor">{{ $proveedores->where('tipo','nacional')->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Internacionales</div>
        <div class="res-valor" style="color:#555770">
            {{ $proveedores->where('tipo','internacional')->count() }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:15%">Identificación</th>
            <th style="width:28%">Razón Social</th>
            <th style="width:8%">Tipo</th>
            <th style="width:18%">Email</th>
            <th style="width:10%">Teléfono</th>
            <th style="width:10%">Ciudad</th>
            <th style="width:7%">Crédito</th>
            <th style="width:4%">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($proveedores as $p)
        <tr>
            <td style="font-family:monospace;font-weight:bold;color:#2C5F8A">
                {{ $p->identificacion }}
            </td>
            <td>
                <div style="font-weight:600;color:#1A1A2E">{{ $p->razon_social }}</div>
                @if($p->nombre_comercial)
                <div style="font-size:7.5px;color:#555770">{{ $p->nombre_comercial }}</div>
                @endif
            </td>
            <td>
                <span class="badge {{ $p->tipo === 'nacional' ? 'b-nac' : 'b-int' }}">
                    {{ ucfirst($p->tipo) }}
                </span>
            </td>
            <td style="font-size:7.5px">{{ $p->email ?? '—' }}</td>
            <td>{{ $p->telefono ?? '—' }}</td>
            <td>{{ $p->ciudad ?? '—' }}</td>
            <td>
                @if($p->tiene_credito)
                <span class="badge b-cred">{{ $p->dias_credito }}d</span>
                @else
                <span style="color:#555770">Contado</span>
                @endif
            </td>
            <td>
                <span class="badge {{ $p->estado ? 'b-act' : 'b-ina' }}">
                    {{ $p->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira &middot; Catálogo de Proveedores</div>
    <div class="f-r">
        Impreso: {{ now()->format('d/m/Y H:i') }} &middot;
        Usuario: {{ auth()->user()?->email }}
    </div>
</div>


<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
