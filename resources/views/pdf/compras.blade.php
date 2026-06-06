<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;
               font-size:8.5px;color:#1f2937;padding:16px 20px; }
        .header { display:table;width:100%;margin-bottom:12px;
                  padding-bottom:10px;border-bottom:3px solid #F59E0B; }
        .h-left  { display:table-cell;vertical-align:middle;width:60%; }
        .h-right { display:table-cell;vertical-align:middle;
                   text-align:right;width:40%; }
        .empresa { font-size:14px;font-weight:bold;color:#F59E0B; }
        .titulo  { font-size:11px;font-weight:bold;color:#1f2937;margin-top:3px; }
        .sub     { font-size:8px;color:#6b7280;margin-top:2px; }

        .cards { display:table;width:100%;margin-bottom:12px; }
        .card  { display:table-cell;text-align:center;padding:7px;
                 border-radius:6px; }
        .c1 { background:#dbeafe;border-left:3px solid #1e40af; }
        .c2 { background:#d1fae5;border-left:3px solid #059669;margin:0 4px; }
        .c3 { background:#fee2e2;border-left:3px solid #dc2626;margin:0 4px; }
        .c4 { background:#fef3c7;border-left:3px solid #F59E0B; }
        .card-label { font-size:7px;font-weight:bold;
                      text-transform:uppercase;color:#6b7280; }
        .card-value { font-size:13px;font-weight:bold;margin-top:2px; }

        table { width:100%;border-collapse:collapse; }
        thead tr { background:#1f2937; }
        thead th { padding:6px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;text-transform:uppercase;color:white; }
        thead th.right { text-align:right; }
        tbody tr:nth-child(even) { background:#f9fafb; }
        tbody tr.anulada { opacity:0.5; }
        tbody td { padding:5px 7px;border-bottom:1px solid #e5e7eb;
                   font-size:8px; }
        tbody td.right { text-align:right;font-family:monospace; }
        .num   { font-family:monospace;font-weight:bold;color:#F59E0B; }
        .dorado{ color:#F59E0B;font-weight:bold; }
        .verde { color:#059669; }
        .badge { display:inline-block;padding:1px 5px;border-radius:8px;
                 font-size:7px;font-weight:bold; }
        .b-act { background:#d1fae5;color:#065f46; }
        .b-anu { background:#fee2e2;color:#991b1b; }

        .fila-total { background:#1f2937; }
        .fila-total td { color:white!important;font-weight:bold!important;
                         font-size:9px!important;padding:6px 7px!important; }

        .footer { margin-top:10px;padding-top:8px;
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
        <div class="titulo">REPORTE DE FACTURAS DE COMPRA</div>
        <div class="sub">
            RUC: {{ $empresa->ruc ?? '—' }} ·
            {{ $empresa->direccion_matriz ?? 'Quito, Ecuador' }}
        </div>
    </div>
    <div class="h-right">
        <div style="font-size:10px;font-weight:bold;color:#1f2937">
            {{ now()->format('d/m/Y H:i') }}
        </div>
        <div class="sub">Total: {{ $compras->count() }} facturas</div>
        <div class="sub">
            Usuario: {{ auth()->user()?->email }}
        </div>
    </div>
</div>

<div class="cards">
    <div class="card c1">
        <div class="card-label">Total Facturas</div>
        <div class="card-value" style="color:#1e40af">
            {{ $compras->count() }}
        </div>
    </div>
    <div class="card c2">
        <div class="card-label">Activas</div>
        <div class="card-value" style="color:#059669">
            {{ $compras->where('estado','activa')->count() }}
        </div>
    </div>
    <div class="card c3">
        <div class="card-label">Anuladas</div>
        <div class="card-value" style="color:#dc2626">
            {{ $compras->where('estado','anulada')->count() }}
        </div>
    </div>
    <div class="card c4">
        <div class="card-label">Monto Total</div>
        <div class="card-value" style="color:#F59E0B;font-size:11px">
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
                <div style="font-weight:600;color:#1f2937">
                    {{ Str::limit($c->proveedor?->razon_social ?? '—', 35) }}
                </div>
            </td>
            <td style="text-align:center">
                <span style="background:#dbeafe;color:#1e40af;padding:1px 4px;
                             border-radius:4px;font-size:7px;font-weight:bold">
                    {{ $c->tipo_documento }}
                </span>
            </td>
            <td class="right">
                ${{ number_format((float)$c->subtotal_0 + (float)$c->subtotal_iva, 2) }}
            </td>
            <td class="right verde">
                ${{ number_format((float)$c->total_iva, 2) }}
            </td>
            <td class="right dorado">
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
            <td class="right" style="color:#F59E0B!important">
                ${{ number_format($compras->where('estado','activa')->sum('total'), 2) }}
            </td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

<div class="footer">
    <div class="f-l">ERP Altamira · Reporte de Facturas de Compra</div>
    <div class="f-r">
        Impreso: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

</body>
</html>
