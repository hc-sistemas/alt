<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:9px;color:#1f2937;padding:16px 20px; }
        .header { display:table;width:100%;margin-bottom:14px;
                  padding-bottom:10px;border-bottom:3px solid #F59E0B; }
        .h-left  { display:table-cell;vertical-align:middle;width:65%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:35%; }
        .empresa { font-size:15px;font-weight:bold;color:#F59E0B; }
        .titulo  { font-size:12px;font-weight:bold;color:#1f2937;margin-top:3px; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }

        .cards { display:table;width:100%;margin-bottom:12px; }
        .card  { display:table-cell;text-align:center;padding:7px;
                 border-radius:6px; }
        .c1 { background:#dbeafe;border-left:3px solid #1e40af; }
        .c2 { background:#d1fae5;border-left:3px solid #059669;margin:0 4px; }
        .c3 { background:#fef3c7;border-left:3px solid #F59E0B; }
        .card-label { font-size:7px;font-weight:bold;
                      text-transform:uppercase;color:#6b7280; }
        .card-value { font-size:14px;font-weight:bold;margin-top:2px; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#F59E0B; }
        thead th { padding:6px 7px;text-align:left;font-size:8px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        tbody td { padding:5px 7px;border-bottom:1px solid #e5e7eb;
                   font-size:8.5px; }
        .badge { display:inline-block;padding:1px 6px;border-radius:8px;
                 font-size:7px;font-weight:bold; }
        .b-nac { background:#d1fae5;color:#065f46; }
        .b-int { background:#dbeafe;color:#1e40af; }
        .b-act { background:#d1fae5;color:#065f46; }
        .b-ina { background:#fee2e2;color:#991b1b; }
        .b-cred{ background:#fef3c7;color:#92400e; }
        .footer { margin-top:12px;padding-top:8px;
                  border-top:1px solid #e5e7eb;
                  display:table;width:100%; }
        .f-l { display:table-cell;font-size:7px;color:#9ca3af; }
        .f-r { display:table-cell;text-align:right;
               font-size:7px;color:#9ca3af; }
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
            RUC: {{ $empresa->ruc ?? '—' }} ·
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1f2937">
            {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">Total: {{ $proveedores->count() }} proveedores</div>
    </div>
</div>

<div class="cards">
    <div class="card c1">
        <div class="card-label">Total</div>
        <div class="card-value" style="color:#1e40af">
            {{ $proveedores->count() }}
        </div>
    </div>
    <div class="card c2">
        <div class="card-label">Nacionales</div>
        <div class="card-value" style="color:#059669">
            {{ $proveedores->where('tipo','nacional')->count() }}
        </div>
    </div>
    <div class="card c3">
        <div class="card-label">Internacionales</div>
        <div class="card-value" style="color:#F59E0B">
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
            <td style="font-family:monospace;font-weight:bold;color:#F59E0B">
                {{ $p->identificacion }}
            </td>
            <td>
                <div style="font-weight:600;color:#1f2937">
                    {{ $p->razon_social }}
                </div>
                @if($p->nombre_comercial)
                <div style="font-size:7.5px;color:#9ca3af">
                    {{ $p->nombre_comercial }}
                </div>
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
                <span class="badge b-cred">
                    {{ $p->dias_credito }}d
                </span>
                @else
                <span style="color:#9ca3af">Contado</span>
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
    <div class="f-l">ERP Altamira · Catálogo de Proveedores</div>
    <div class="f-r">
        Impreso: {{ now()->format('d/m/Y H:i') }} ·
        Usuario: {{ auth()->user()?->email }}
    </div>
</div>

</body>
</html>
