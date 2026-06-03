<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tipo === 'lista' ? 'Listado de Proveedores' : 'Ficha de Proveedor' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; padding: 24px; }
        .header { border-bottom: 2px solid #f59e0b; padding-bottom: 12px; margin-bottom: 16px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { font-size: 18px; font-weight: 700; color: #f59e0b; letter-spacing: -0.5px; }
        .brand-sub { font-size: 9px; color: #64748b; margin-top: 1px; }
        .header-meta { text-align: right; font-size: 9px; color: #64748b; line-height: 1.6; }
        .empresa-nombre { font-size: 11px; font-weight: 600; color: #1e293b; }
        h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin-top: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        thead tr { background: #f1f5f9; }
        th { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
             padding: 5px 6px; border: 1px solid #e2e8f0; text-align: left; font-weight: 600; }
        td { padding: 4px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .no-col { width: 24px; text-align: center; color: #94a3b8; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 8px; font-weight: 600; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }
        .badge-slate  { background: #f1f5f9; color: #475569; }
        .total-row { font-weight: 600; background: #f1f5f9; font-size: 9px; color: #475569; }
        .ficha { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-top: 12px; }
        .ficha-titulo { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        .ficha-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
        .ficha-item label { font-size: 8px; text-transform: uppercase; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 1px; }
        .ficha-item .valor { font-size: 10px; color: #1e293b; }
        .seccion-titulo { font-size: 10px; font-weight: 700; color: #f59e0b; margin: 12px 0 6px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #fef3c7; padding-bottom: 3px; }
    </style>
</head>
<body>

<div class="header">
    <div class="header-top">
        <div>
            <div class="brand">ERP Altamira</div>
            <div class="brand-sub">Sistema de Gestión Empresarial</div>
        </div>
        <div class="header-meta">
            <div class="empresa-nombre">{{ $empresa->nombre_comercial ?? $empresa->razon_social }}</div>
            <div>RUC: {{ $empresa->ruc }}</div>
            <div>Generado por: {{ $usuario->nombre }}</div>
            <div>{{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>
    <h2>{{ $tipo === 'lista' ? 'Listado de Proveedores' : 'Ficha de Proveedor' }}</h2>
</div>

@if($tipo === 'lista')
<table>
    <thead>
        <tr>
            <th class="no-col">N°</th>
            <th>Tipo</th>
            <th>RUC / ID</th>
            <th>Nombre / Razón Social</th>
            <th>País</th>
            <th>Ciudad</th>
            <th>Crédito</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($proveedores as $i => $p)
        <tr>
            <td class="no-col muted">{{ $i + 1 }}</td>
            <td>
                <span class="badge {{ $p->tipo === 'nacional' ? 'badge-slate' : 'badge-blue' }}">
                    {{ $p->tipo === 'nacional' ? 'Nacional' : 'Intl.' }}
                </span>
            </td>
            <td class="mono">{{ $p->ruc_cedula ?? '—' }}</td>
            <td>{{ $p->nombre }}</td>
            <td class="muted">
                {{ $p->pais }}
                @if($p->divisa) <span class="mono">({{ $p->divisa }})</span> @endif
            </td>
            <td class="muted">{{ $p->ciudad ?? '—' }}</td>
            <td>
                @if($p->tiene_credito)
                    <span class="badge badge-blue">{{ $p->dias_credito }} días</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td>
                <span class="badge {{ $p->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $p->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align:center; color:#94a3b8; padding:12px;">
                No hay proveedores registrados.
            </td>
        </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="8" style="padding:5px 6px;">
                Total: {{ $proveedores->count() }} proveedor{{ $proveedores->count() !== 1 ? 'es' : '' }}
            </td>
        </tr>
    </tbody>
</table>

@else
<div class="ficha">
    <div class="ficha-titulo">
        {{ $proveedor->nombre }}
        <span class="badge {{ $proveedor->tipo === 'nacional' ? 'badge-slate' : 'badge-blue' }}" style="margin-left:8px; font-size:9px;">
            {{ $proveedor->tipo === 'nacional' ? 'Nacional' : 'Internacional' }}
        </span>
    </div>

    <div class="seccion-titulo">Datos generales</div>
    <div class="ficha-grid">
        <div class="ficha-item">
            <label>RUC / Identificación</label>
            <div class="valor mono">{{ $proveedor->ruc_cedula ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Estado</label>
            <div class="valor">
                <span class="badge {{ $proveedor->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $proveedor->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
        </div>
        <div class="ficha-item">
            <label>País</label>
            <div class="valor">{{ $proveedor->pais }}</div>
        </div>
        <div class="ficha-item">
            <label>Ciudad</label>
            <div class="valor">{{ $proveedor->ciudad ?? '—' }}</div>
        </div>
        @if($proveedor->divisa)
        <div class="ficha-item">
            <label>Divisa</label>
            <div class="valor mono">{{ $proveedor->divisa }}</div>
        </div>
        @endif
        <div class="ficha-item">
            <label>Teléfono</label>
            <div class="valor mono">{{ $proveedor->telefono ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Email</label>
            <div class="valor">{{ $proveedor->email ?? '—' }}</div>
        </div>
        <div class="ficha-item" style="grid-column: span 2;">
            <label>Dirección</label>
            <div class="valor">{{ $proveedor->direccion ?? '—' }}</div>
        </div>
    </div>

    @if($proveedor->tiene_credito)
    <div class="seccion-titulo">Crédito</div>
    <div class="ficha-grid">
        <div class="ficha-item">
            <label>Días de crédito</label>
            <div class="valor">{{ $proveedor->dias_credito }} días</div>
        </div>
    </div>
    @endif

    @if($proveedor->observaciones)
    <div class="seccion-titulo">Observaciones</div>
    <p style="font-size:10px; color:#475569; margin-top:4px;">{{ $proveedor->observaciones }}</p>
    @endif
</div>
@endif

</body>
</html>
