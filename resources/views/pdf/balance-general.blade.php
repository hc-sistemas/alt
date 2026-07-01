<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:9px; color:#1F2D3D; padding:16px 20px; }
        .header { display:table; width:100%; margin-bottom:12px; padding-bottom:10px; border-bottom:2px solid #1F2D3D; }
        .h-left  { display:table-cell; vertical-align:middle; width:60%; }
        .h-right { display:table-cell; vertical-align:middle; text-align:right; width:40%; }
        .empresa { font-size:14px; font-weight:bold; }
        .titulo  { font-size:12px; font-weight:bold; margin-top:3px; }
        .sub     { font-size:7.5px; color:#6b7280; margin-top:2px; }
        .cols    { display:table; width:100%; border-spacing:12px 0; }
        .col     { display:table-cell; width:50%; vertical-align:top; }
        .seccion { background:#1F2D3D; color:white; padding:5px 8px; font-size:8px; font-weight:bold; text-transform:uppercase; }
        table    { width:100%; border-collapse:collapse; }
        tbody td { padding:3px 6px; border-bottom:1px solid #e5e7eb; font-size:8px; }
        tbody td.right { text-align:right; font-family:monospace; font-weight:bold; }
        tbody tr:nth-child(even) { background:#f5f7fa; }
        .cod { font-family:monospace; font-size:7px; color:#6b7280; }
        .total-row td { background:#1F2D3D; color:white; font-weight:bold; padding:5px 6px; }
        .total-row td.right { text-align:right; font-family:monospace; }
        .cuadre { margin-top:14px; border:2px solid #1F2D3D; border-radius:6px; padding:8px 12px; text-align:center; }
    </style>
</head>
<body>

<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa?->nombre ?? 'Altamira' }}</div>
        <div class="titulo">ESTADO DE SITUACIÓN FINANCIERA</div>
        <div class="sub">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
    <div class="h-right">
        <div style="font-size:8px; color:#6b7280;">RUC: {{ $empresa?->ruc ?? '—' }}</div>
    </div>
</div>

<div class="cols">
    {{-- COLUMNA ACTIVOS --}}
    <div class="col">
        <div class="seccion">ACTIVOS</div>
        <table>
            <tbody>
                @foreach($activos as $c)
                <tr>
                    <td>
                        <span class="cod">{{ $c['codigo'] }}</span>
                        {{ $c['nombre'] }}
                    </td>
                    <td class="right">${{ number_format($c['saldo'], 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL ACTIVOS</td>
                    <td class="right">${{ number_format($totales['activos'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>

    {{-- COLUMNA PASIVOS + PATRIMONIO --}}
    <div class="col">
        <div class="seccion">PASIVOS Y PATRIMONIO</div>
        <table>
            <tbody>
                @foreach($pasivos as $c)
                <tr>
                    <td>
                        <span class="cod">{{ $c['codigo'] }}</span>
                        {{ $c['nombre'] }}
                        @if($c['tipo'] === 'patrimonio')
                            <span style="font-size:6.5px; color:#0c4a6e;">(Patrimonio)</span>
                        @endif
                    </td>
                    <td class="right">${{ number_format(abs($c['saldo']), 2) }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td>TOTAL PAS. + PAT.</td>
                    <td class="right">${{ number_format($totales['pasivos'], 2) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<div class="cuadre">
    @php $diferencia = round($totales['activos'] - $totales['pasivos'], 2); @endphp
    @if(abs($diferencia) < 0.02)
        <span style="color:#059669; font-weight:bold; font-size:9px;">✓ Balance cuadrado — ACTIVOS = PASIVOS + PATRIMONIO</span>
    @else
        <span style="color:#dc2626; font-weight:bold; font-size:9px;">⚠ Diferencia: ${{ number_format(abs($diferencia), 2) }}</span>
    @endif
</div>

</body>
</html>
