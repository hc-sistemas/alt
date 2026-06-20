<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Saldos de Inventario</title>
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
        .critico { color: #dc2626; font-weight: 700; }
        .badge { display: inline-block; padding: 1px 5px; border-radius: 999px; font-size: 7px; font-weight: 600; }
        .badge-red { background: #fee2e2; color: #991b1b; }
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
    <h2>Saldos de Inventario (Kárdex)</h2>
</div>

@php
    $valorTotal = $saldos->sum(fn($s) => (float)$s->cantidad * (float)$s->costo_promedio);
    $totalItems = $saldos->count();
@endphp

<table>
    <thead>
        <tr>
            <th class="no-col">N°</th>
            <th>Código</th>
            <th>Producto</th>
            <th>Bodega</th>
            <th class="right">Stock Actual</th>
            <th class="right">Reservado</th>
            <th class="right">Disponible</th>
            <th class="right">Costo Prom.</th>
            <th class="right">Valor Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($saldos as $i => $s)
        @php
            $producto    = $s->producto;
            $bodega      = $s->bodega;
            $cantidad    = (float) $s->cantidad;
            $reservado   = (float) ($s->cantidad_reservada ?? 0);
            $disponible  = $cantidad - $reservado;
            $costo       = (float) $s->costo_promedio;
            $valor        = $cantidad * $costo;
            $esCritico   = $producto && $producto->stock_minimo > 0 && $cantidad <= $producto->stock_minimo;
        @endphp
        <tr>
            <td class="no-col muted">{{ $i + 1 }}</td>
            <td class="mono">{{ $producto->codigo ?? '—' }}</td>
            <td>
                {{ $producto->nombre ?? '—' }}
                @if($esCritico)
                    <span class="badge badge-red">CRÍTICO</span>
                @endif
            </td>
            <td class="muted">{{ $bodega->nombre ?? '—' }}</td>
            <td class="right mono {{ $esCritico ? 'critico' : '' }}">{{ number_format($cantidad, 4) }}</td>
            <td class="right mono muted">{{ number_format($reservado, 4) }}</td>
            <td class="right mono {{ $disponible < 0 ? 'critico' : '' }}">{{ number_format($disponible, 4) }}</td>
            <td class="right mono muted">{{ number_format($costo, 4) }}</td>
            <td class="right mono">{{ number_format($valor, 2) }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="9" style="text-align:center; color:#94a3b8; padding:10px;">
                No hay saldos de inventario registrados.
            </td>
        </tr>
        @endforelse
        <tr class="total-row">
            <td colspan="8" style="padding:4px 5px; text-align:right;">Valor total inventario:</td>
            <td class="right mono" style="padding:4px 5px; font-weight:700;">
                $ {{ number_format($valorTotal, 2) }}
            </td>
        </tr>
    </tbody>
</table>

<div class="summary">
    <div class="summary-item">
        <label>Total registros</label>
        <div class="val">{{ $totalItems }}</div>
    </div>
    <div class="summary-item">
        <label>Valor total inventario</label>
        <div class="val">$ {{ number_format($valorTotal, 2) }}</div>
    </div>
</div>

<div class="footer">
    <span>ERP Altamira — {{ $empresa->razon_social }}</span>
    <span>Página <span class="pagenum"></span></span>
</div>

</body>
</html>
