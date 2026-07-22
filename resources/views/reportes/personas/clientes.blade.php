<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tipo === 'lista' ? 'Listado de Clientes' : 'Ficha de Cliente' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1e293b; padding: 24px; }

        /* ── Encabezado ── */
        .header { border-bottom: 2px solid #f59e0b; padding-bottom: 12px; margin-bottom: 16px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { font-size: 18px; font-weight: 700; color: #f59e0b; letter-spacing: -0.5px; }
        .brand-sub { font-size: 9px; color: #64748b; margin-top: 1px; }
        .header-meta { text-align: right; font-size: 9px; color: #64748b; line-height: 1.6; }
        .empresa-nombre { font-size: 11px; font-weight: 600; color: #1e293b; }
        h2 { font-size: 13px; font-weight: 700; color: #1e293b; margin-top: 10px; }

        /* ── Tabla lista ── */
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        thead tr { background: #f1f5f9; }
        th { font-size: 8px; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b;
             padding: 5px 6px; border: 1px solid #e2e8f0; text-align: left; font-weight: 600; }
        td { padding: 4px 6px; border: 1px solid #e2e8f0; vertical-align: top; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        tbody tr:hover { background: #fef9ec; }
        .no-col { width: 24px; text-align: center; color: #94a3b8; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 9px; }
        .muted { color: #64748b; }
        .badge { display: inline-block; padding: 1px 7px; border-radius: 999px; font-size: 8px; font-weight: 600; }
        .badge-green { background: #dcfce7; color: #166534; }
        .badge-red   { background: #fee2e2; color: #991b1b; }
        .badge-blue  { background: #dbeafe; color: #1e40af; }
        .total-row { font-weight: 600; background: #f1f5f9; font-size: 9px; color: #475569; }

        /* ── Ficha individual ── */
        .ficha { border: 1px solid #e2e8f0; border-radius: 6px; padding: 14px; margin-top: 12px; }
        .ficha-titulo { font-size: 14px; font-weight: 700; color: #1e293b; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        .ficha-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 20px; }
        .ficha-item label { font-size: 8px; text-transform: uppercase; color: #94a3b8; font-weight: 600; display: block; margin-bottom: 1px; }
        .ficha-item .valor { font-size: 10px; color: #1e293b; }
        .seccion-titulo { font-size: 10px; font-weight: 700; color: #f59e0b; margin: 12px 0 6px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #fef3c7; padding-bottom: 3px; }
    </style>
</head>
<body>

{{-- ── Encabezado ── --}}
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
    <h2>{{ $tipo === 'lista' ? 'Listado de Clientes' : 'Ficha de Cliente' }}</h2>
</div>

@if($tipo === 'lista')
{{-- ── Lista ── --}}
<table>
    <thead>
        <tr>
            <th class="no-col">N°</th>
            <th>RUC / Cédula</th>
            <th>Nombre / Razón Social</th>
            <th>Ciudad</th>
            <th>Teléfono</th>
            <th>Email</th>
            <th>Crédito</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($clientes as $i => $c)
        <tr>
            <td class="no-col muted">{{ $i + 1 }}</td>
            <td class="mono">{{ $c->ruc_cedula }}</td>
            <td>
                {{ $c->nombre }}
                @if($c->es_agente_retencion)
                    <span class="badge badge-blue">Ag. Ret.</span>
                @endif
            </td>
            <td class="muted">{{ $c->ciudad ?? '—' }}</td>
            <td class="muted mono">{{ $c->telefono ?? '—' }}</td>
            <td class="muted">{{ $c->email ?? '—' }}</td>
            <td>
                @if($c->tiene_credito)
                    <span class="badge badge-blue">{{ $c->dias_credito }} días</span>
                @else
                    <span class="muted">—</span>
                @endif
            </td>
            <td>
                <span class="badge {{ $c->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $c->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="8" style="text-align:center; color:#94a3b8; padding:12px;">
                No hay clientes registrados.
            </td>
        </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="8" style="padding:5px 6px;">
                Total: {{ $clientes->count() }} cliente{{ $clientes->count() !== 1 ? 's' : '' }}
            </td>
        </tr>
    </tbody>
</table>

@else
{{-- ── Ficha individual ── --}}
<div class="ficha">
    <div class="ficha-titulo">{{ $cliente->nombre }}</div>

    <div class="seccion-titulo">Datos generales</div>
    <div class="ficha-grid">
        <div class="ficha-item">
            <label>RUC / Cédula</label>
            <div class="valor mono">{{ $cliente->ruc_cedula }}</div>
        </div>
        <div class="ficha-item">
            <label>Estado</label>
            <div class="valor">
                <span class="badge {{ $cliente->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $cliente->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </div>
        </div>
        <div class="ficha-item">
            <label>Ciudad</label>
            <div class="valor">{{ $cliente->ciudad ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>País</label>
            <div class="valor">{{ $cliente->pais ?? 'Ecuador' }}</div>
        </div>
        <div class="ficha-item" style="grid-column: span 2;">
            <label>Dirección</label>
            <div class="valor">{{ $cliente->direccion ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Teléfono</label>
            <div class="valor mono">{{ $cliente->telefono ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Email</label>
            <div class="valor">{{ $cliente->email ?? '—' }}</div>
        </div>
        <div class="ficha-item">
            <label>Agente de retención</label>
            <div class="valor">{{ $cliente->es_agente_retencion ? 'Sí' : 'No' }}</div>
        </div>
    </div>

    @if($cliente->tiene_credito)
    <div class="seccion-titulo">Crédito</div>
    <div class="ficha-grid">
        <div class="ficha-item">
            <label>Días de crédito</label>
            <div class="valor">{{ $cliente->dias_credito }} días</div>
        </div>
        @if($cliente->cupo_credito)
        <div class="ficha-item">
            <label>Cupo de crédito</label>
            <div class="valor mono">$ {{ number_format($cliente->cupo_credito, 2) }}</div>
        </div>
        @endif
    </div>
    @endif

    @if($cliente->observaciones)
    <div class="seccion-titulo">Observaciones</div>
    <p style="font-size:10px; color:#475569; margin-top:4px;">{{ $cliente->observaciones }}</p>
    @endif
</div>
@endif

</body>
</html>
