<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Listado de Productos</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #1e293b; padding: 20px; }

        /* ── Encabezado ── */
        .header { border-bottom: 2px solid #f59e0b; padding-bottom: 10px; margin-bottom: 14px; }
        .header-top { display: flex; justify-content: space-between; align-items: flex-start; }
        .brand { font-size: 17px; font-weight: 700; color: #f59e0b; letter-spacing: -0.5px; }
        .brand-sub { font-size: 8px; color: #64748b; margin-top: 1px; }
        .header-meta { text-align: right; font-size: 8px; color: #64748b; line-height: 1.6; }
        .empresa-nombre { font-size: 10px; font-weight: 600; color: #1e293b; }
        h2 { font-size: 12px; font-weight: 700; color: #1e293b; margin-top: 8px; }

        /* ── Tabla ── */
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        thead tr { background: #f1f5f9; }
        th { font-size: 7px; text-transform: uppercase; letter-spacing: 0.4px; color: #64748b;
             padding: 4px 5px; border: 1px solid #e2e8f0; text-align: left; font-weight: 600; }
        td { padding: 3px 5px; border: 1px solid #e2e8f0; vertical-align: middle; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .no-col { width: 20px; text-align: center; color: #94a3b8; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; }
        .muted { color: #64748b; }
        .right { text-align: right; }
        .center { text-align: center; }
        .badge { display: inline-block; padding: 1px 5px; border-radius: 999px; font-size: 7px; font-weight: 600; }
        .badge-green  { background: #dcfce7; color: #166534; }
        .badge-red    { background: #fee2e2; color: #991b1b; }
        .badge-blue   { background: #dbeafe; color: #1e40af; }
        .badge-emerald { background: #d1fae5; color: #065f46; }
        .badge-orange { background: #ffedd5; color: #9a3412; }
        .badge-violet { background: #ede9fe; color: #5b21b6; }
        .badge-slate  { background: #f1f5f9; color: #475569; }
        .total-row { font-weight: 600; background: #f1f5f9; font-size: 8px; color: #475569; }

        /* ── Footer ── */
        .footer { position: fixed; bottom: 12px; left: 20px; right: 20px;
                  font-size: 7px; color: #94a3b8; border-top: 1px solid #e2e8f0;
                  padding-top: 4px; display: flex; justify-content: space-between; }
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
            <div>Generado por: {{ $usuario->nombre ?? $usuario->name }}</div>
            <div>{{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>
    <h2>Listado de Productos</h2>
</div>

<table>
    <thead>
        <tr>
            <th class="no-col">N°</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Marca</th>
            <th>Categoría</th>
            <th class="right">PVP</th>
            <th class="right">PVD</th>
            <th class="right">Costo</th>
            <th class="right">Desc.Máx%</th>
            <th class="right">IVA%</th>
            <th class="right">Stk.Mín</th>
            <th class="right">Stk.Máx</th>
            <th>Tipo</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($productos as $i => $p)
        <tr>
            <td class="no-col muted">{{ $i + 1 }}</td>
            <td class="mono">{{ $p->codigo }}</td>
            <td>{{ $p->nombre }}</td>
            <td class="muted">{{ $p->marca->nombre ?? '—' }}</td>
            <td class="muted">{{ $p->categoria->nombre ?? '—' }}</td>
            <td class="right mono">{{ number_format($p->pvp, 2) }}</td>
            <td class="right mono muted">{{ number_format($p->pvd, 2) }}</td>
            <td class="right mono muted">{{ number_format($p->costo, 2) }}</td>
            <td class="right mono muted">{{ number_format($p->descuento_maximo, 1) }}</td>
            <td class="right mono muted">{{ number_format($p->porcentaje_iva, 0) }}</td>
            <td class="right mono muted">{{ $p->stock_minimo ?? 0 }}</td>
            <td class="right mono muted">{{ $p->stock_maximo ?? 0 }}</td>
            <td>
                @php
                    $tipoCls = match($p->tipo) {
                        'producto' => 'badge-blue',
                        'servicio' => 'badge-emerald',
                        'repuesto' => 'badge-orange',
                        'insumo'   => 'badge-violet',
                        default    => 'badge-slate',
                    };
                    $tipoLbl = match($p->tipo) {
                        'producto' => 'Producto',
                        'servicio' => 'Servicio',
                        'repuesto' => 'Repuesto',
                        'insumo'   => 'Insumo',
                        default    => $p->tipo,
                    };
                @endphp
                <span class="badge {{ $tipoCls }}">{{ $tipoLbl }}</span>
            </td>
            <td>
                <span class="badge {{ $p->estado ? 'badge-green' : 'badge-red' }}">
                    {{ $p->estado ? 'Activo' : 'Inactivo' }}
                </span>
            </td>
        </tr>
        @empty
        <tr>
            <td colspan="14" style="text-align:center; color:#94a3b8; padding:10px;">
                No hay productos registrados.
            </td>
        </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="14" style="padding:4px 5px;">
                Total: {{ $productos->count() }} producto{{ $productos->count() !== 1 ? 's' : '' }}
            </td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <span>ERP Altamira — {{ $empresa->razon_social }}</span>
    <span>Página <span class="pagenum"></span></span>
</div>

</body>
</html>
