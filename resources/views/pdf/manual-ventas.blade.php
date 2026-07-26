<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;
               color:#1A1A2E;background:#fff; }

        .portada { page-break-after:always;padding:60px 50px;
                   text-align:center;background:#155E75;color:#fff;min-height:297mm; }
        .portada-logo { font-size:28px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#67E8F9; }
        .portada-sub  { font-size:11px;color:#A5F3FC;margin-bottom:60px; }
        .portada-divider { width:60px;height:3px;background:#67E8F9;margin:0 auto 50px; }
        .portada-titulo { font-size:22px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:14px;color:#67E8F9;margin-bottom:8px; }
        .portada-ver    { font-size:10px;color:#A5F3FC;margin-bottom:70px; }
        .portada-meta   { font-size:9px;color:#CFFAFE;line-height:1.8; }
        .portada-meta strong { color:#ECFEFF; }

        .page { padding:22px 26px 18px; }

        .header-page { display:table;width:100%;margin-bottom:12px;
                       padding-bottom:8px;border-bottom:2px solid #155E75; }
        .hp-left  { display:table-cell;vertical-align:middle;width:65%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:35%; }
        .hp-empresa { font-size:10px;font-weight:bold;color:#155E75; }
        .hp-titulo  { font-size:7px;color:#555770;margin-top:1px; }
        .hp-pag     { font-size:7px;color:#555770; }

        .sec { margin-bottom:16px; }
        .sec-num { display:inline-block;background:#155E75;color:#A5F3FC;
                   font-size:7px;font-weight:bold;padding:2px 6px;
                   border-radius:3px;margin-bottom:5px;letter-spacing:0.5px;
                   text-transform:uppercase; }
        .sec-titulo { font-size:13px;font-weight:bold;color:#155E75;margin-bottom:2px; }
        .sec-desc { font-size:8.5px;color:#444;line-height:1.6;margin-bottom:8px; }

        h3 { font-size:9.5px;font-weight:bold;color:#155E75;
             margin-bottom:4px;margin-top:10px; }

        table { width:100%;border-collapse:collapse;margin-bottom:8px; }
        thead tr { background:#155E75; }
        thead th { padding:5px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;color:#fff;text-transform:uppercase;letter-spacing:0.3px; }
        thead th.c { text-align:center; }
        tbody td { padding:4.5px 7px;border-bottom:1px solid #E2E8F0;
                   font-size:8px;color:#1A1A2E;vertical-align:top; }
        tbody tr:nth-child(even) { background:#ECFEFF; }
        tbody td strong { color:#155E75; }

        .box { border-left:3px solid #06B6D4;background:#ECFEFF;
               padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .box-titulo { font-size:7.5px;font-weight:bold;color:#0E7490;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:3px; }
        .box p { font-size:8px;color:#164E63;line-height:1.6;margin:0; }

        .nota { border-left:3px solid #22D3EE;background:#ECFEFF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .nota p { font-size:8px;color:#155E75;line-height:1.6;margin:0; }

        .alerta { border-left:3px solid #EF4444;background:#FEF2F2;
                  padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .alerta p { font-size:8px;color:#991B1B;line-height:1.6;margin:0; }

        .info { border-left:3px solid #3B82F6;background:#EFF6FF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .info p { font-size:8px;color:#1E40AF;line-height:1.6;margin:0; }

        .pasos { margin-bottom:8px; }
        .paso { display:table;width:100%;margin-bottom:4px; }
        .paso-num  { display:table-cell;width:20px;vertical-align:top; }
        .paso-num span { display:inline-block;background:#06B6D4;color:#fff;
                         font-size:7.5px;font-weight:bold;width:16px;height:16px;
                         border-radius:50%;text-align:center;line-height:16px; }
        .paso-cont { display:table-cell;vertical-align:top;font-size:8px;
                     color:#444;line-height:1.6;padding-left:4px; }
        .paso-cont strong { color:#155E75; }

        .badge { display:inline-block;padding:1px 6px;border-radius:10px;
                 font-size:7px;font-weight:bold;margin:1px; }
        .b-azul   { background:#DBEAFE;color:#1E40AF; }
        .b-verde  { background:#DCFCE7;color:#166534; }
        .b-rojo   { background:#FEE2E2;color:#991B1B; }
        .b-gris   { background:#F1F5F9;color:#475569; }
        .b-cian   { background:#CFFAFE;color:#155E75; }
        .b-celeste { background:#E0F2FE;color:#075985; }

        .diagrama { font-family:monospace;font-size:7.5px;color:#475569;
                    background:#ECFEFF;border:1px solid #A5F3FC;
                    padding:8px 10px;border-radius:4px;margin-bottom:8px;
                    line-height:1.8;white-space:pre; }

        .footer { margin-top:10px;padding-top:7px;
                  border-top:1px solid #E2E8F0;display:table;width:100%; }
        .f-l { display:table-cell;font-size:6.5px;color:#94A3B8; }
        .f-r { display:table-cell;text-align:right;font-size:6.5px;color:#94A3B8; }

        .sep { border:none;border-top:1px dashed #CBD5E0;margin:12px 0; }
        .pb { page-break-before:always; }
        .col2 { display:table;width:100%;margin-bottom:8px; }
        .col2-l { display:table-cell;width:49%;vertical-align:top;padding-right:6px; }
        .col2-r { display:table-cell;width:49%;vertical-align:top;padding-left:6px; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════ PORTADA ═══════════════════════════════ --}}
<div class="portada">
    <div class="portada-logo">ALTAMIRA</div>
    <div class="portada-sub">Light &amp; Sound · ERP Sistema</div>
    <div class="portada-divider"></div>
    <div class="portada-titulo">Manual de Uso</div>
    <div class="portada-modulo">Módulo Ventas</div>
    <div class="portada-ver">
        Versión 1.0 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}
    </div>
    <div class="portada-meta">
        <strong>Empresa:</strong> {{ $empresa->nombre_comercial ?? 'Altamira' }}<br>
        <strong>Usuario:</strong> {{ $usuario->nombre ?? $usuario->email }}<br>
        <strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

{{-- ══════════════════════════════ ÍNDICE ═══════════════════════════════════ --}}
<div class="page pb">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light &amp; Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Ventas</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">Índice &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Navegación</div>
        <div class="sec-titulo">Tabla de Contenidos</div>
        <div class="sec-desc">Altamira ERP — Módulo Ventas · 5 páginas de contenido. Use esta tabla para ubicar rápidamente cada sección.</div>

        <table>
            <thead>
                <tr>
                    <th style="width:12%;text-align:center">Pág.</th>
                    <th style="width:30%">Sección</th>
                    <th>Contenido</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align:center;font-weight:bold">1</td>
                    <td>Introducción</td>
                    <td>¿Para qué sirve el módulo? · Mapa del menú lateral</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">1</td>
                    <td><span class="badge b-cian">§ 1</span> Facturas</td>
                    <td>Descuentos con doble techo · Precio bajo costo bloqueado · Anulación con PIN real</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-cian">§ 2</span> Proformas</td>
                    <td>Cotización sin stock · Conversión a factura en un clic</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-cian">§ 3</span> Prefacturas</td>
                    <td>Traslado atómico de stock · Panel de abonos · Botón Crear Factura</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-cian">§ 4</span> Notas de Crédito</td>
                    <td>Validación de cantidades · Bodega Cuarentena · Cruce contra CxC</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-cian">§ 5</span> Retenciones</td>
                    <td>Catálogo IR/IVA provisional · Cálculo de valores retenidos</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td><span class="badge b-cian">§ 6</span> Guías de Remisión</td>
                    <td>Transportista · Direcciones y fechas · Asociación opcional a factura</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td><span class="badge b-cian">§ 7</span> Cuentas por Cobrar</td>
                    <td>Antigüedad 30/60/90 en vivo · Registrar cobro · Castigar deuda</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">5</td>
                    <td>Referencia Rápida</td>
                    <td>Flujo típico de una venta · Próximamente</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="nota"><p><strong>Ruta en el menú:</strong> Ventas → Facturas / Proformas / Prefacturas / CxC / Notas de Crédito / Retenciones / Guías de Remisión.</p></div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Módulo Ventas · v1.0</div>
        <div class="f-r">{{ now()->format('d/m/Y') }} · Índice de contenidos</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 1 ══════════════════════════════ --}}
<div class="page">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Ventas</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Introducción</div>
        <div class="sec-titulo">¿Para qué sirve el módulo Ventas?</div>
        <div class="sec-desc">
            El módulo Ventas administra todo el ciclo comercial de Altamira: cotizaciones (Proformas), reservas
            con abono parcial (Prefacturas), la Factura como comprobante real de venta, sus devoluciones (Notas
            de Crédito), las Retenciones que le practican los clientes agentes de retención, el transporte de
            mercadería (Guías de Remisión) y el seguimiento de la cartera (Cuentas por Cobrar).
        </div>

        <div class="diagrama">Menú: Ventas
├── Facturas           → Comprobante de venta real, con IVA y stock definitivo
├── Proformas          → Cotización sin compromiso de stock, un clic a Factura
├── Prefacturas        → Reserva de stock con abonos parciales antes de facturar
├── CxC                → Cuentas por Cobrar: reporte de antigüedad y cobros
├── Notas de Crédito   → Devoluciones sobre una factura ya emitida
├── Retenciones        → Comprobante de retención IR/IVA por cliente agente de retención
└── Guías de Remisión  → Transporte de mercadería con o sin factura asociada</div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 1</div>
        <div class="sec-titulo">Facturas</div>
        <div class="sec-desc">
            La Factura es el comprobante real de venta: descuenta stock de forma definitiva, genera su asiento
            contable automático y, si incluye pago a crédito, crea la Cuenta por Cobrar correspondiente.
        </div>

        <h3>1.1 Crear una factura</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Ventas → Facturas → + Nueva Factura</strong> y seleccionar el <strong>cliente</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Agregar los <strong>productos</strong> con cantidad, precio y descuento por línea. El sistema valida en tiempo real el stock disponible en <strong>Bodega Principal UIO</strong> para cada producto que no sea de tipo servicio.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Definir una o varias <strong>formas de pago</strong>. La suma de todas debe cuadrar con el total (tolerancia de $0.01).</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Pago a crédito → Cuenta por Cobrar automática</div>
            <p>Si alguna forma de pago es <strong>crédito</strong>, al guardar la factura el sistema crea automáticamente su Cuenta por Cobrar: saldo igual al monto a crédito y vencimiento calculado según los <strong>días de crédito</strong> configurados en el cliente (30 días por defecto si no tiene).</p>
        </div>

        <h3>1.2 Descuentos y aprobación especial</h3>
        <div class="sec-desc">
            Cada línea se valida contra un <strong>doble techo</strong>: el descuento máximo del producto (o de su
            promoción vigente en la lista de precios) y el límite de descuento del perfil del vendedor. Si el
            porcentaje solicitado supera cualquiera de los dos, la factura exige una <strong>aprobación especial</strong>
            ya autorizada — pedida por el mismo usuario, del tipo correcto y no usada previamente — cuyo valor
            aprobado debe cubrir el porcentaje solicitado.
        </div>

        <h3>1.3 Precio menor a costo — bloqueado con aprobación especial</h3>
        <div class="box">
            <div class="box-titulo">Control activo</div>
            <p>El costo real de cada producto siempre se lee desde la tabla de productos, nunca del valor que envía el formulario, así que no se puede manipular el precio en el navegador para saltarse el control. Si el precio de una línea queda por debajo de su costo, esa línea exige una <strong>aprobación especial de precio bajo costo</strong> — de un solo uso, no reutilizable en otra línea ni en otra factura.</p>
        </div>

        <h3>1.4 Anulación de factura</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Solo el perfil <strong>Super Admin</strong> puede anular, y únicamente facturas emitidas <strong>el mismo día</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Se exige una <strong>aprobación especial real</strong> de tipo anulación de factura, validada contra el flujo de aprobaciones (pedida por el mismo usuario, no usada previamente) — ya no es un campo de texto libre sin validar.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Al confirmar, el sistema reingresa a inventario todo el stock que la factura había egresado y marca la factura (y su estado SRI) como <span class="badge b-rojo">ANULADA</span>.</div>
            </div>
        </div>

        <div class="nota"><p><strong>Envío al SRI:</strong> Próximamente. La facturación electrónica todavía no está implementada.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 1 Facturas &nbsp;·&nbsp; Módulo Ventas</div>
        <div class="f-r">Pág. 1 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 2 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Ventas</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 2</div>
        <div class="sec-titulo">Proformas</div>
        <div class="sec-desc">
            La Proforma es una cotización: <strong>no reserva ni descuenta stock</strong>, solo muestra de forma
            informativa el stock disponible en Bodega Principal UIO al momento de armarla.
        </div>

        <h3>2.1 Crear y gestionar una proforma</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Ventas → Proformas → + Nueva Proforma</strong>, elegir cliente y productos. Si no se indica, la <strong>fecha de vencimiento</strong> se fija automáticamente a 15 días.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Los descuentos se validan con el mismo esquema de doble techo que las Facturas, con una diferencia: el <strong>tope de producto</strong> es una capa dura que ni la aprobación especial puede superar; solo el <strong>límite de perfil</strong> admite aprobación especial.</div>
            </div>
        </div>

        <div class="col2">
            <div class="col2-l">
                <div class="box">
                    <div class="box-titulo">Anular proforma</div>
                    <p>Solo disponible mientras la proforma está en estado <strong>pendiente</strong>.</p>
                </div>
            </div>
            <div class="col2-r">
                <div class="box">
                    <div class="box-titulo">Convertir a factura — un clic</div>
                    <p>También solo desde <strong>pendiente</strong>: se eligen las formas de pago finales y el sistema genera la Factura con los mismos detalles, marcando la proforma como <span class="badge b-verde">FACTURADA</span>.</p>
                </div>
            </div>
        </div>

        <div class="nota"><p><strong>Estado "vencida" automático y alerta 24h antes del vencimiento:</strong> Próximamente. Hoy el estado de la proforma solo cambia mediante una acción explícita del usuario (anular o convertir a factura).</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 3</div>
        <div class="sec-titulo">Prefacturas</div>
        <div class="sec-desc">
            La Prefactura reserva físicamente el stock desde el momento en que se crea y permite recibir
            <strong>abonos parciales</strong> antes de emitir la factura final.
        </div>

        <h3>3.1 Crear una prefactura — traslado atómico de stock</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Ventas → Prefacturas → + Nueva Prefactura</strong>, elegir cliente y productos.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Por cada línea, el sistema valida el stock disponible en <strong>Bodega Principal UIO</strong> y, dentro de la misma transacción, egresa esa cantidad de Principal e ingresa y <strong>reserva</strong> esa misma cantidad en <strong>Bodega Reservas</strong> — un traslado atómico: o se mueve todo o no se mueve nada.</div>
            </div>
        </div>

        <h3>3.2 Panel de abonos</h3>
        <div class="sec-desc">Desde el detalle de la prefactura se registran <strong>abonos</strong> (pagos parciales): valor, forma de pago y fecha.</div>
        <div class="box">
            <div class="box-titulo">Asiento contable por abono</div>
            <p>Cada abono genera automáticamente su propio asiento de <strong>anticipo de cliente</strong>. El sistema acumula el <strong>total abonado</strong> y recalcula el <strong>saldo pendiente</strong>; cuando el saldo llega a cero, la prefactura pasa a estado <span class="badge b-verde">LIQUIDADA</span>.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 2 Proformas &nbsp;·&nbsp; § 3 Prefacturas &nbsp;·&nbsp; Módulo Ventas</div>
        <div class="f-r">Pág. 2 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 3 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Ventas</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-desc" style="margin-top:-4px">
            <em>Continuación de § 3 Prefacturas.</em>
        </div>

        <h3>3.3 Crear Factura — botón habilitado solo con saldo = 0</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">El botón <strong>"Crear Factura"</strong> solo se habilita cuando el <strong>saldo pendiente</strong> de la prefactura llega a cero; el backend vuelve a validar esta misma condición aunque el botón ya esté deshabilitado en pantalla.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Al confirmar, el sistema <strong>confirma la salida definitiva</strong> del stock reservado en Bodega Reservas, copia los detalles a una nueva <strong>Factura</strong>, asocia las formas de pago finales y marca la prefactura como <span class="badge b-verde">LIQUIDADA</span> vinculada a esa factura.</div>
            </div>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 4</div>
        <div class="sec-titulo">Notas de Crédito</div>
        <div class="sec-desc">
            Una Nota de Crédito registra la devolución total o parcial de una factura ya emitida.
        </div>

        <h3>4.1 Seleccionar la factura origen</h3>
        <div class="sec-desc">Se busca la <strong>factura</strong> por número; solo se exige que esté en estado <strong>activa</strong> (la verificación adicional de "autorizada por el SRI" está deshabilitada temporalmente en el código, ya que el envío al SRI todavía no existe para ningún documento del sistema).</div>

        <h3>4.2 Validación de cantidades</h3>
        <div class="sec-desc">No se puede devolver más que la <strong>cantidad original</strong> de cada línea de la factura, ni más de lo que ya se ha devuelto acumulado entre varias notas de crédito activas de esa misma factura.</div>

        <h3>4.3 Bodega Cuarentena real</h3>
        <div class="sec-desc">Cada producto devuelto ingresa físicamente a la bodega de tipo <strong>Cuarentena</strong> de la empresa activa (si existe configurada).</div>

        <h3>4.4 Cruce contra CxC o saldo a favor</h3>
        <div class="box">
            <div class="box-titulo">Cruce automático</div>
            <p>Si la factura tiene una Cuenta por Cobrar <strong>pendiente</strong>, la nota de crédito descuenta su total de ese saldo (y la marca <span class="badge b-verde">COBRADA</span> si llega a cero). Si no hay CxC pendiente, la nota queda marcada como generadora de <strong>saldo a favor</strong> del cliente.</p>
        </div>

        <div class="nota"><p><strong>Envío al SRI:</strong> Próximamente.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 3 Prefacturas (cont.) &nbsp;·&nbsp; § 4 Notas de Crédito &nbsp;·&nbsp; Módulo Ventas</div>
        <div class="f-r">Pág. 3 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 4 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Ventas</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 5</div>
        <div class="sec-titulo">Retenciones</div>
        <div class="sec-desc">
            Registra la retención de IR/IVA que un cliente agente de retención practica sobre una factura.
        </div>

        <h3>5.1 Requisito: cliente agente de retención</h3>
        <div class="sec-desc">Solo se puede crear una retención sobre una factura cuyo <strong>cliente</strong> tenga marcado <strong>agente de retención</strong>; si no lo es, el sistema bloquea la creación.</div>

        <h3>5.2 Catálogo de porcentajes IR/IVA</h3>
        <table>
            <thead><tr><th>Código</th><th>Descripción</th><th>Tipo</th><th class="c">%</th></tr></thead>
            <tbody>
                <tr><td>303</td><td>Honorarios profesionales</td><td>IR</td><td class="c">10%</td></tr>
                <tr><td>304</td><td>Servicios donde predomina mano de obra</td><td>IR</td><td class="c">3%</td></tr>
                <tr><td>312</td><td>Transferencia de bienes muebles</td><td>IR</td><td class="c">2%</td></tr>
                <tr><td>343</td><td>Arrendamiento de bienes inmuebles</td><td>IR</td><td class="c">10%</td></tr>
                <tr><td>725</td><td>Retención IVA — bienes</td><td>IVA</td><td class="c">30%</td></tr>
                <tr><td>727</td><td>Retención IVA — servicios</td><td>IVA</td><td class="c">70%</td></tr>
                <tr><td>729</td><td>Retención IVA — servicios profesionales</td><td>IVA</td><td class="c">100%</td></tr>
            </tbody>
        </table>

        <div class="alerta"><p><strong>Catálogo provisional de prueba:</strong> estos códigos y porcentajes están marcados explícitamente en el código como valores de prueba — no son los porcentajes oficiales vigentes del SRI. Deben reemplazarse por el catálogo oficial antes de emitir retenciones reales en producción.</p></div>

        <h3>5.3 Registrar la retención</h3>
        <div class="sec-desc">Por cada línea se indica la <strong>base imponible</strong> y el <strong>porcentaje</strong>; el sistema calcula el valor retenido de cada una y el total de la retención.</div>

        <div class="nota"><p><strong>Envío al SRI:</strong> Próximamente.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 6</div>
        <div class="sec-titulo">Guías de Remisión</div>
        <div class="sec-desc">
            Documenta el transporte de mercadería, con o sin factura asociada.
        </div>

        <h3>6.1 Crear una guía de remisión</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Ventas → Guías de Remisión → + Nueva Guía</strong> y elegir el <strong>transportista</strong> (catálogo de transportistas activos).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Completar <strong>dirección de partida y destino</strong>, y <strong>fecha de inicio y fin</strong> de transporte (fin no puede ser anterior al inicio).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Agregar los <strong>ítems</strong> a transportar (descripción, cantidad y unidad; el producto del catálogo es opcional).</div>
            </div>
        </div>

        <div class="nota"><p><strong>Asociación a factura:</strong> es opcional — una guía de remisión puede crearse de forma independiente o vinculada a una factura existente. <strong>Envío al SRI:</strong> Próximamente.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 5 Retenciones &nbsp;·&nbsp; § 6 Guías de Remisión &nbsp;·&nbsp; Módulo Ventas</div>
        <div class="f-r">Pág. 4 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 5 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Ventas</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 7</div>
        <div class="sec-titulo">Cuentas por Cobrar (CxC)</div>
        <div class="sec-desc">
            Las Cuentas por Cobrar se generan automáticamente cuando una factura incluye una forma de pago
            <strong>crédito</strong> (ver Sección 1.1).
        </div>

        <h3>7.1 Reporte de antigüedad en vivo</h3>
        <div class="sec-desc">La pantalla de <strong>Ventas → CxC</strong> muestra métricas de cartera calculadas <strong>en tiempo real</strong> cada vez que se abre: total de cartera, por vencer, y vencido en los tramos 1–30, 31–60 y más de 60 días. No existe un campo de antigüedad guardado ni un proceso por lotes que las precalcule.</div>

        <h3>7.2 Registrar cobro</h3>
        <div class="sec-desc">El valor del cobro no puede superar el <strong>saldo pendiente</strong>. Al registrarlo, el sistema actualiza el saldo y el estado (<span class="badge b-cian">PARCIAL</span> o <span class="badge b-verde">COBRADA</span> si llega a cero) y genera su asiento contable de cobro.</div>

        <h3>7.3 Castigar deuda</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Solo disponible para el perfil <strong>Super Admin</strong>, y únicamente sobre cuentas con <strong>más de 360 días</strong> de vencimiento.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Exige una <strong>aprobación especial real</strong> de tipo castigo de cartera, validada contra el flujo de aprobaciones — mismo esquema que la anulación de facturas.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Al confirmar, la cuenta pasa a estado <span class="badge b-rojo">CASTIGADA</span>.</div>
            </div>
        </div>

        <div class="nota"><p><strong>Alertas automáticas de vencimiento (0/15/30 días):</strong> Próximamente.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Referencia rápida</div>
        <div class="sec-titulo">Flujo típico de una venta</div>

        <div class="diagrama">Cotización (opcional):
    Proforma → Convertir a Factura (un clic)

Venta con abono parcial:
    Prefactura → Traslado atómico a Bodega Reservas
        → Panel de Abonos (cada uno con su asiento) → saldo = 0
        → Crear Factura (botón habilitado solo con saldo = 0)

Venta directa:
    Factura → ¿forma de pago "crédito"? → Cuenta por Cobrar
        → Registrar Cobro → COBRADA
        → +360 días vencida → Castigar deuda (Super Admin + aprobación especial)

Devolución sobre una factura:
    Factura → Nota de Crédito → Bodega Cuarentena + cruce contra CxC / saldo a favor</div>

        <div class="box">
            <div class="box-titulo">Acceso requerido</div>
            <p>Todo el módulo Ventas requiere el permiso <strong>"Ventas - Ver"</strong> para consultar. Crear y editar cada documento requiere sus permisos correspondientes. <strong>Anular factura</strong> y <strong>castigar deuda</strong> requieren además el perfil <strong>Super Admin</strong>, aprobación especial válida y — en el caso de anulación — que la factura sea del mismo día.</p>
        </div>

        <div class="nota">
            <p><strong>Próximamente:</strong> lo siguiente todavía no está implementado en el módulo Ventas:</p>
            <p>· <strong>Facturación electrónica SRI:</strong> el método de envío al SRI existe como un stub en Facturas, Notas de Crédito, Retenciones y Guías de Remisión — todavía no hay firma electrónica, generación de XML ni comunicación con el webservice del SRI.</p>
            <p>· <strong>Catálogo oficial de retenciones IR/IVA:</strong> los porcentajes actuales son provisionales de prueba (ver Sección 5.2).</p>
            <p>· <strong>Alertas automáticas de vencimiento de CxC</strong> a los 0/15/30 días.</p>
            <p>· <strong>Estado "vencida" automático de Proformas</strong> y alerta 24 horas antes del vencimiento.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 7 Cuentas por Cobrar &nbsp;·&nbsp; Referencia Rápida &nbsp;·&nbsp; Módulo Ventas · v1.0</div>
        <div class="f-r">Pág. 5 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

</body>
</html>
