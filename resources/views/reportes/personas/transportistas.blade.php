<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tipo === 'lista' ? 'Listado de Transportistas' : 'Ficha de Transportista' }}</title>
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
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red   { background: #fee2e2; color: #991b1b; }
        .total-row { font-weight: 600; background: #f1f5f9; font-size: 9px; color: #475569; }
        .ficha { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-top: 12px; }
        .ficha-titulo { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        .ficha-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
        .ficha-item label { font-size: 8px; text-transform: uppercase; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 1px; }
        .ficha-item .valor { font-size: 10px; color: #1e293b; }
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
    <h2>{{ $tipo === 'lista' ? 'Listado de Transportistas' : 'Ficha de Transportista' }}</h2>
</div>

@if($tipo === 'lista')
<table>
    <thead>
        <tr>
            <th class="no-col">N°</th>
            <th>Razón Social</th>
            <th>RUC</th>
            <th>Placa</th>
            <th>Contacto</th>
            <th>Teléfono</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($transportistas as $i => $t)
        <tr>
            <td class="no-col muted">{{ $i + 1 }}</td>
            <td>{{ $t->razon_social }}</td>
            <td class="mono">{{ $t->ruc }}</td>
            <td class="muted mono">{{ $t->placa ?? '—' }}</td>
            <td class="muted">{{ $t->contacto ?? '—' }}</td>
            <td class="muted mono">{{ $t->telefono ?? '—' }}</td>
            <td>
                <span class="badge {{ $t->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $t->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align:center; color:#94a3b8; padding:12px;">
                No hay transportistas registrados.
            </td>
        </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="7" style="padding:5px 6px;">
                Total: {{ $transportistas->count() }} transportista{{ $transportistas->count() !== 1 ? 's' : '' }}
            </td>
        </tr>
    </tbody>
</table>

@else
<div class="ficha">
    <div class="ficha-titulo">{{ $transportista->razon_social }}</div>
    <div class="ficha-grid">
        <div class="ficha-item">
            <label>RUC</label>
            <div class="valor mono">{{ $transportista->ruc }}</div>
        </div>
        <div class="ficha-item">
            <label>Estado</label>
            <div class="valor">
                <span class="badge {{ $transportista->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $transportista->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
        </div>
        <div class="ficha-item">
            <label>Placa</label>
            <div class="valor mono">{{ $transportista->placa ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Contacto</label>
            <div class="valor">{{ $transportista->contacto ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Teléfono</label>
            <div class="valor mono">{{ $transportista->telefono ?? '—' }}</div>
        </div>
    </div>
</div>
@endif

</body>
</html>
