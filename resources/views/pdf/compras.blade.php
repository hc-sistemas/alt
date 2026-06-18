<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:8.5px;color:#1A1A2E;padding:16px 20px; }
        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#1A1A2E; }
        .titulo  { font-size:11px;font-weight:bold;color:#1A1A2E;margin-top:3px; }
        .sub     { font-size:8px;color:#555770;margin-top:2px; }

        .resumen { display:table;width:100%;margin-bottom:12px;
                   border:1px solid #D8DCE6; }
        .res-item { display:table-cell;text-align:center;padding:7px;
                    border-right:1px solid #D8DCE6; }
        .res-item:last-child { border-right:none; }
        .res-label { font-size:7px;font-weight:bold;text-transform:uppercase;
                     color:#555770;letter-spacing:0.3px; }
        .res-valor { font-size:13px;font-weight:bold;margin-top:2px;color:#1A1A2E; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1F2D3D; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#F5F7FA; }
        tbody tr.anulada { opacity:0.5; }
        tbody td { padding:5px 7px;border-bottom:1px solid #D8DCE6;
                   font-size:8px;color:#1A1A2E; }
        tbody td.right { text-align:right;font-family:monospace; }
        .num   { font-family:monospace;font-weight:bold;color:#2C5F8A; }
        .badge { display:inline-block;padding:1px 5px;border-radius:3px;
                 font-size:7px;font-weight:bold;border:1px solid #D8DCE6; }
        .b-act { background:#EEF2F8;color:#1A1A2E; }
        .b-anu { background:#F5F7FA;color:#555770; }

        .fila-total { background:#1F2D3D; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         font-size:9px!important;padding:6px 7px!important; }

        .footer { margin-top:10px;padding-top:8px;
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
        <div class="titulo">REPORTE DE FACTURAS DE COMPRA</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} &middot;
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1A1A2E">
            {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">Total: {{ $compras->count() }} facturas</div>
        <div class="sub">Usuario: {{ auth()->user()?->email }}</div>
    </div>
</div>

<div class="resumen">
    <div class="res-item">
        <div class="res-label">Total Facturas</div>
        <div class="res-valor">{{ $compras->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Activas</div>
        <div class="res-valor">{{ $compras->where('estado','activa')->count() }}</div>
    </div>
    <div class="res-item">
        <div class="res-label">Anuladas</div>
        <div class="res-valor" style="color:#555770">
            {{ $compras->where('estado','anulada')->count() }}
        </div>
    </div>
    <div class="res-item">
        <div class="res-label">Monto Total Activas</div>
        <div class="res-valor" style="font-size:11px">
            ${{ number_format($compras->where('estado','activa')->sum('total'), 2) }}
        </div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width:14%">N° Documento</th>
            <th style="width:9%">Fecha</th>
            <th style="width:30%">Proveedor</th>
            <th style="width:5%">Tipo</th>
            <th class="right" style="width:10%">Subtotal</th>
            <th class="right" style="width:8%">IVA</th>
            <th class="right" style="width:10%">Total</th>
            <th style="width:9%">Vencimiento</th>
            <th style="width:5%">Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($compras as $c)
        <tr class="{{ $c->estado === 'anulada' ? 'anulada' : '' }}">
            <td class="num">{{ $c->num_documento }}</td>
            <td>{{ $c->fecha_emision?->format('d/m/Y') }}</td>
            <td>
                <div style="font-weight:600;color:#1A1A2E">
                    {{ Str::limit($c->proveedor?->razon_social ?? '—', 35) }}
                </div>
            </td>
            <td style="text-align:center">
                <span style="background:#EEF2F8;color:#2C5F8A;padding:1px 4px;
                             border-radius:2px;font-size:7px;font-weight:bold">
                    {{ $c->tipo_documento }}
                </span>
            </td>
            <td class="right">
                ${{ number_format((float)$c->subtotal_0 + (float)$c->subtotal_iva, 2) }}
            </td>
            <td class="right">
                ${{ number_format((float)$c->total_iva, 2) }}
            </td>
            <td class="right" style="font-weight:bold">
                ${{ number_format((float)$c->total, 2) }}
            </td>
            <td>{{ $c->fecha_vencimiento?->format('d/m/Y') ?? 'Contado' }}</td>
            <td>
                <span class="badge {{ $c->estado === 'activa' ? 'b-act' : 'b-anu' }}">
                    {{ ucfirst($c->estado) }}
                </span>
            </td>
        </tr>
        @endforeach
        <tr class="fila-total">
            <td colspan="6">TOTAL FACTURAS ACTIVAS</td>
            <td class="right">
                ${{ number_format($compras->where('estado','activa')->sum('total'), 2) }}
            </td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira &middot; Reporte de Facturas de Compra</div>
    <div class="f-r">Impreso: {{ now()->format('d/m/Y H:i') }}</div>
</div>

</body>
</html>
