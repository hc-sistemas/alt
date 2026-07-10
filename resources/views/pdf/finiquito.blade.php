<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:9px; color:#111; }
        .header { text-align:center; border-bottom:2px solid #1A1A2E; padding-bottom:10px; margin-bottom:14px; }
        .empresa-nombre { font-size:14px; font-weight:bold; color:#1A1A2E; }
        .empresa-datos  { font-size:8px; color:#555; margin-top:2px; }
        .titulo { font-size:12px; font-weight:bold; text-align:center;
                  text-transform:uppercase; letter-spacing:1px;
                  border:1px solid #1A1A2E; padding:6px; margin-bottom:12px; }
        .seccion { margin-bottom:10px; }
        .seccion-titulo { font-size:8.5px; font-weight:bold; text-transform:uppercase;
                          letter-spacing:0.5px; color:#4C1D95;
                          border-bottom:1px solid #DDD6FE; padding-bottom:2px; margin-bottom:6px; }
        .grid2 { display:table; width:100%; border-collapse:collapse; }
        .col { display:table-cell; width:50%; vertical-align:top; padding-right:8px; }
        .col:last-child { padding-right:0; padding-left:8px; }
        .campo { margin-bottom:4px; }
        .campo label { font-size:7.5px; color:#666; text-transform:uppercase; letter-spacing:0.3px; }
        .campo .val { font-size:9px; font-weight:bold; color:#111; }
        .tabla { width:100%; border-collapse:collapse; margin-top:4px; }
        .tabla th { background:#1A1A2E; color:#fff; padding:4px 8px; font-size:8px; text-align:left; }
        .tabla td { padding:4px 8px; border-bottom:1px solid #E5E7EB; font-size:9px; }
        .tabla tr:nth-child(even) td { background:#F9FAFB; }
        .tabla .monto { text-align:right; font-weight:bold; }
        .tabla .total-row td { background:#F5F3FF; font-weight:bold; font-size:9.5px; }
        .total-neto { text-align:center; margin:12px 0;
                      border:2px solid #4C1D95; border-radius:6px; padding:10px; }
        .total-neto .label { font-size:8px; color:#6B7280; text-transform:uppercase; }
        .total-neto .monto { font-size:18px; font-weight:bold; color:#4C1D95; margin-top:3px; }
        .motivo-badge { display:inline-block; background:#EDE9FE; color:#4C1D95;
                        padding:2px 8px; border-radius:4px; font-size:8px; font-weight:bold; }
        .firmas { margin-top:30px; display:table; width:100%; }
        .firma { display:table-cell; width:33%; text-align:center; vertical-align:bottom; padding:0 8px; }
        .firma-linea { border-top:1px solid #111; margin-bottom:4px; }
        .firma-label { font-size:8px; color:#555; }
        .firma-nombre { font-size:8.5px; font-weight:bold; }
        .nota-legal { margin-top:14px; border:1px solid #E5E7EB; border-radius:4px;
                      padding:8px 10px; font-size:7.5px; color:#555; line-height:1.6; }
        .footer { margin-top:16px; padding-top:6px; border-top:1px solid #E5E7EB;
                  font-size:7px; color:#999; text-align:center; }
    </style>
</head>
<body>
<div class="header">
    <div class="empresa-nombre">{{ $empresa->nombre_comercial ?? $empresa->razon_social }}</div>
    <div class="empresa-datos">
        RUC: {{ $empresa->ruc }} &nbsp;·&nbsp; {{ $empresa->direccion_matriz ?? '' }}
    </div>
</div>

<div class="titulo">Acta de Finiquito — {{ strtoupper($liquidacion->motivo ?? 'liquidación') }}</div>

<div class="seccion">
    <div class="seccion-titulo">Datos del Empleado</div>
    <div class="grid2">
        <div class="col">
            <div class="campo">
                <label>Apellidos y Nombres</label>
                <div class="val">{{ $colaborador->apellidos }} {{ $colaborador->nombres }}</div>
            </div>
            <div class="campo">
                <label>Cédula / RUC</label>
                <div class="val">{{ $colaborador->cedula_ruc }}</div>
            </div>
            <div class="campo">
                <label>Cargo</label>
                <div class="val">{{ $colaborador->cargo ?? '—' }}</div>
            </div>
        </div>
        <div class="col">
            <div class="campo">
                <label>Fecha de Ingreso</label>
                <div class="val">{{ \Carbon\Carbon::parse($colaborador->fecha_ingreso)->format('d/m/Y') }}</div>
            </div>
            <div class="campo">
                <label>Fecha de Salida</label>
                <div class="val">{{ \Carbon\Carbon::parse($liquidacion->fecha_salida)->format('d/m/Y') }}</div>
            </div>
            <div class="campo">
                <label>Motivo de Salida</label>
                <div class="val">
                    <span class="motivo-badge">
                        {{ ['renuncia'=>'Renuncia Voluntaria','despido'=>'Despido Intempestivo','fin_contrato'=>'Fin de Contrato'][$liquidacion->motivo] ?? $liquidacion->motivo }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="seccion">
    <div class="seccion-titulo">Liquidación de Haberes</div>
    <table class="tabla">
        <thead>
            <tr>
                <th style="width:60%">Concepto</th>
                <th style="width:20%;text-align:right">Monto</th>
                <th style="width:20%;text-align:right">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Décimos acumulados proporcionales<br>
                    <span style="font-size:7.5px;color:#6B7280">13ro y 14to (según modalidad acumula)</span>
                </td>
                <td class="monto">${{ number_format((float)$liquidacion->decimos_acumulados, 2) }}</td>
                <td class="monto" style="color:#059669">+${{ number_format((float)$liquidacion->decimos_acumulados, 2) }}</td>
            </tr>
            <tr>
                <td>Vacaciones no gozadas proporcionales<br>
                    <span style="font-size:7.5px;color:#6B7280">Sueldo × días laborados / 720</span>
                </td>
                <td class="monto">${{ number_format((float)$liquidacion->vacaciones, 2) }}</td>
                <td class="monto" style="color:#059669">+${{ number_format((float)$liquidacion->vacaciones, 2) }}</td>
            </tr>
            @if((float)$liquidacion->fondos_reserva > 0)
            <tr>
                <td>Fondos de reserva proporcionales<br>
                    <span style="font-size:7.5px;color:#6B7280">Aplica desde mes 13 de contrato</span>
                </td>
                <td class="monto">${{ number_format((float)$liquidacion->fondos_reserva, 2) }}</td>
                <td class="monto" style="color:#059669">+${{ number_format((float)$liquidacion->fondos_reserva, 2) }}</td>
            </tr>
            @endif
            @if((float)$liquidacion->anticipos_descontar > 0)
            <tr>
                <td>Descuento: Préstamos y anticipos pendientes<br>
                    <span style="font-size:7.5px;color:#6B7280">Saldo pendiente en cuenta 1.1.3.04</span>
                </td>
                <td class="monto" style="color:#DC2626">-${{ number_format((float)$liquidacion->anticipos_descontar, 2) }}</td>
                <td class="monto" style="color:#DC2626">-${{ number_format((float)$liquidacion->anticipos_descontar, 2) }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td colspan="2" style="text-align:right;padding-right:16px">TOTAL NETO A PAGAR:</td>
                <td class="monto" style="color:#4C1D95;font-size:11px">
                    ${{ number_format((float)$liquidacion->total_liquidacion, 2) }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="nota-legal">
    <strong>Declaración:</strong> Las partes declaran estar conformes con los valores detallados en el presente documento
    y manifiestan que no tienen ningún valor pendiente de liquidación por concepto de remuneraciones, beneficios sociales
    u otros rubros establecidos en el Código del Trabajo de la República del Ecuador. El presente acuerdo se suscribe
    conforme a lo establecido en el Art. 169 y siguientes del Código del Trabajo.
</div>

<div class="firmas">
    <div class="firma">
        <div style="height:40px"></div>
        <div class="firma-linea"></div>
        <div class="firma-nombre">{{ $colaborador->apellidos }} {{ $colaborador->nombres }}</div>
        <div class="firma-label">Empleado · C.I. {{ $colaborador->cedula_ruc }}</div>
    </div>
    <div class="firma">
        <div style="height:40px"></div>
        <div class="firma-linea"></div>
        <div class="firma-nombre">Representante Legal</div>
        <div class="firma-label">{{ $empresa->nombre_comercial ?? $empresa->razon_social }}</div>
    </div>
    <div class="firma">
        <div style="height:40px"></div>
        <div class="firma-linea"></div>
        <div class="firma-nombre">Inspector del Trabajo</div>
        <div class="firma-label">Ministerio del Trabajo — Ecuador</div>
    </div>
</div>

<div class="footer">
    Documento generado: {{ now()->format('d/m/Y H:i') }} &nbsp;·&nbsp;
    {{ $empresa->nombre_comercial ?? $empresa->razon_social }} · RUC: {{ $empresa->ruc }} &nbsp;·&nbsp;
    Este acta es válida con las firmas de las partes
</div>

<div style="margin-top:10px; border-top:1px solid #e5e7eb; padding-top:4px; font-size:7px; color:#9ca3af;">
    Impreso por: {{ auth()->user()?->nombre ?? '—' }} &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }}
</div>
</body>
</html>
