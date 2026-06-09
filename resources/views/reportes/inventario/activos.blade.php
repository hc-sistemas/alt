<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activos Fijos</title>
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
        .badge-slate  { background: #f1f5f9; color: #475569; }
        .badge-amber  { background: #fef3c7; color: #92400e; }
        .depreciado   { color: #d97706; font-weight: 600; }
        .total-row { font-weight: 600; background: #f1f5f9; font-size: 8px; color: #475569; }
        .summary { margin-top: 12px; font-size: 8px; color: #475569;
                   border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 4px;
                   display: flex; gap: 24px; }
        .summary-item label { text-transform: uppercase; font-size: 7px; color: #94a3b8; display: block; margin-bottom: 1px; }
        .summary-item .val { font-size: 10px; font-weight: 700; font-family: 'DejaVu Sans Mono', monospace; color: #1e293b; }

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
    <h2>Listado de Activos Fijos</h2>
</div>

@php
    $totalCosto     = $activos->sum(fn($a) => (float)$a->costo_adquisicion);
    $totalDepAcum   = $activos->sum(fn($a) => (float)$a->depreciacion_acumulada);
    $totalLibros    = $activos->sum(fn($a) => (float)$a->valor_en_libros);
@endphp

<table>
    <thead>
        <tr>
            <th class="no-col">N°</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Fecha Adq.</th>
            <th class="right">Costo Adq.</th>
            <th class="right">Vida Útil</th>
            <th class="right">Dep. Mensual</th>
            <th class="right">Dep. Acumulada</th>
            <th class="right">Valor en Libros</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($activos as $i => $a)
        @php
            $costo      = (float) $a->costo_adquisicion;
            $residual   = (float) $a->valor_residual;
            $depAcum    = (float) $a->depreciacion_acumulada;
            $libros     = (float) $a->valor_en_libros;
            $depMensual = $a->depreciacionMensual();
            $totDep     = ($costo - $residual) > 0 && $libros <= $residual;

            $estadoCls = match($a->estado) {
                'activo'       => 'badge-green',
                'dado_de_baja' => 'badge-red',
                'vendido'      => 'badge-slate',
                default        => 'badge-slate',
            };
            $estadoLbl = match($a->estado) {
                'activo'       => 'Activo',
                'dado_de_baja' => 'Baja',
                'vendido'      => 'Vendido',
                default        => $a->estado,
            };
        @endphp
        <tr>
            <td class="no-col muted">{{ $i + 1 }}</td>
            <td class="mono">{{ $a->codigo }}</td>
            <td>{{ $a->nombre }}</td>
            <td class="mono muted">{{ $a->fecha_adquisicion->format('d/m/Y') }}</td>
            <td class="right mono">{{ number_format($costo, 2) }}</td>
            <td class="right mono muted">{{ $a->vida_util_anios }} años</td>
            <td class="right mono muted">{{ number_format($depMensual, 2) }}</td>
            <td class="right mono muted">{{ number_format($depAcum, 2) }}</td>
            <td class="right mono {{ $totDep ? 'depreciado' : '' }}">{{ number_format($libros, 2) }}</td>
            <td><span class="badge {{ $estadoCls }}">{{ $estadoLbl }}</span></td>
        </tr>
        @empty
        <tr>
            <td colspan="10" style="text-align:center; color:#94a3b8; padding:10px;">
                No hay activos fijos registrados.
            </td>
        </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="4" style="padding:4px 5px;">Total: {{ $activos->count() }} activo{{ $activos->count() !== 1 ? 's' : '' }}</td>
            <td class="right mono" style="padding:4px 5px;">{{ number_format($totalCosto, 2) }}</td>
            <td colspan="2" style="padding:4px 5px;"></td>
            <td class="right mono" style="padding:4px 5px;">{{ number_format($totalDepAcum, 2) }}</td>
            <td class="right mono" style="padding:4px 5px;">{{ number_format($totalLibros, 2) }}</td>
            <td style="padding:4px 5px;"></td>
        </tr>
    </tbody>
</table>

<div class="summary">
    <div class="summary-item">
        <label>Total activos</label>
        <div class="val">{{ $activos->count() }}</div>
    </div>
    <div class="summary-item">
        <label>Costo total adquisición</label>
        <div class="val">$ {{ number_format($totalCosto, 2) }}</div>
    </div>
    <div class="summary-item">
        <label>Depreciación acumulada</label>
        <div class="val">$ {{ number_format($totalDepAcum, 2) }}</div>
    </div>
    <div class="summary-item">
        <label>Valor en libros total</label>
        <div class="val">$ {{ number_format($totalLibros, 2) }}</div>
    </div>
</div>

<div class="footer">
    <span>ERP Altamira — {{ $empresa->razon_social }}</span>
    <span>Página <span class="pagenum"></span></span>
</div>

</body>
</html>
