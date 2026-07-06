<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'DejaVu Sans',Arial,sans-serif; font-size:8.5px; color:#1A1A2E; padding:20px 24px; }

.header { display:table; width:100%; margin-bottom:14px; padding-bottom:10px; border-bottom:3px solid #1A1A2E; }
.h-left  { display:table-cell; vertical-align:middle; width:65%; }
.h-right { display:table-cell; vertical-align:middle; text-align:right; width:35%; }
.empresa  { font-size:15px; font-weight:bold; }
.titulo   { font-size:12px; font-weight:bold; margin-top:3px; color:#1F2D3D; }
.sub      { font-size:7.5px; color:#555770; margin-top:2px; }
.badge    { display:inline-block; background:#B45309; color:white; font-size:8px;
            font-weight:bold; padding:3px 10px; border-radius:3px; letter-spacing:.5px; }
.badge-sri { display:inline-block; background:#F59E0B; color:white; font-size:7px;
             font-weight:bold; padding:2px 7px; border-radius:3px; margin-top:4px; }

.intro { border:1px solid #FDE68A; border-radius:6px; padding:8px 12px; margin-bottom:14px;
         background:#FFFBEB; }
.intro-title { font-size:8.5px; font-weight:bold; color:#92400E; margin-bottom:4px; }
.intro-text  { font-size:7.5px; color:#78350F; line-height:1.5; }

.sec { margin-bottom:16px; }
.sec-header { display:table; width:100%; padding:6px 10px;
              margin-bottom:0; border-radius:4px 4px 0 0; }
.sec-num   { display:table-cell; width:30px; font-size:11px; font-weight:bold; vertical-align:middle; }
.sec-title { display:table-cell; font-size:10px; font-weight:bold; vertical-align:middle; }
.sec-sub   { display:table-cell; text-align:right; font-size:7px; vertical-align:middle; opacity:.8; }
.sec-body  { border:1px solid #D8DCE6; border-top:none; padding:10px 12px;
             border-radius:0 0 4px 4px; background:white; }

.ats-header  { background:#B45309; color:white; }
.f103-header { background:#1A3A5C; color:white; }
.f104-header { background:#166534; color:white; }

.step { display:table; width:100%; margin-bottom:5px; padding:5px 8px;
        border:1px solid #E8EAF0; border-radius:4px; background:#FAFAFA; }
.step-num  { display:table-cell; width:22px; height:22px; border-radius:50%;
             color:white; font-weight:bold; font-size:8px;
             text-align:center; vertical-align:middle; }
.step-num-ats  { background:#B45309; }
.step-num-f103 { background:#1A3A5C; }
.step-num-f104 { background:#166534; }
.step-body { display:table-cell; padding-left:8px; vertical-align:middle; }
.step-title { font-weight:bold; font-size:8px; color:#1A1A2E; }
.step-desc  { font-size:7.5px; color:#555770; margin-top:1px; }

.tip  { background:#FEF9EC; border-left:3px solid #F59E0B; padding:5px 10px;
        margin:6px 0; border-radius:0 4px 4px 0; font-size:7.5px; color:#92400E; }
.info { background:#EFF6FF; border-left:3px solid #3B82F6; padding:5px 10px;
        margin:6px 0; border-radius:0 4px 4px 0; font-size:7.5px; color:#1E40AF; }
.ok   { background:#F0FDF4; border-left:3px solid #22C55E; padding:5px 10px;
        margin:6px 0; border-radius:0 4px 4px 0; font-size:7.5px; color:#166534; }

table.tabla { width:100%; border-collapse:collapse; margin:6px 0; }
table.tabla thead th { color:white; padding:4px 8px; font-size:7px;
                       font-weight:bold; text-align:left; text-transform:uppercase; }
table.tabla thead th.r { text-align:right; }
table.tabla tbody td { padding:4px 8px; border-bottom:1px solid #E8EAF0; font-size:7.5px; }
table.tabla tbody td.cod { font-family:monospace; font-weight:bold; }
table.tabla tbody tr:nth-child(even) { background:#F9FAFB; }

.grid2 { display:table; width:100%; }
.g2l   { display:table-cell; width:49%; vertical-align:top; padding-right:6px; }
.g2r   { display:table-cell; width:49%; vertical-align:top; padding-left:6px; }

.btn-sim { display:inline-block; padding:3px 10px; border-radius:4px; font-size:7px;
           font-weight:bold; color:white; margin:1px 2px; }
.btn-ats  { background:#B45309; }
.btn-xml  { background:transparent; border:1px solid #B45309; color:#B45309; }
.btn-f103 { background:#1A3A5C; }
.btn-f104 { background:#166534; }

.campo-resumen { display:table; width:100%; border:1px solid #E8EAF0; border-radius:6px;
                 overflow:hidden; margin:8px 0; }
.campo-row { display:table-row; }
.campo-row:nth-child(even) .campo-a,
.campo-row:nth-child(even) .campo-b { background:#F9FAFB; }
.campo-a { display:table-cell; width:30%; padding:5px 8px; font-size:7.5px;
           font-weight:bold; border-bottom:1px solid #E8EAF0; color:#374151; }
.campo-b { display:table-cell; padding:5px 8px; font-size:7.5px;
           border-bottom:1px solid #E8EAF0; color:#555770; }

.footer { margin-top:16px; padding-top:6px; border-top:1px solid #D8DCE6; display:table; width:100%; }
.f-left  { display:table-cell; font-size:6px; color:#888; }
.f-right { display:table-cell; text-align:right; font-size:6px; color:#888; }
</style>
</head>
<body>

<!-- CABECERA -->
<div class="header">
    <div class="h-left">
        <div class="empresa">{{ $empresa->razon_social ?? 'Altamira Light & Sound' }}</div>
        <div class="titulo">Reportes SRI — Manual de Uso</div>
        <div class="sub">RUC: {{ $empresa->ruc ?? '—' }} &nbsp;·&nbsp; ERP Altamira v2.0 &nbsp;·&nbsp; Generado: {{ now()->format('d/m/Y') }}</div>
    </div>
    <div class="h-right">
        <div><span class="badge">REPORTES SRI</span></div>
        <div style="margin-top:4px;"><span class="badge-sri">SRI ECUADOR</span></div>
        <div class="sub" style="margin-top:4px;">ATS · Formulario 103 · Formulario 104</div>
    </div>
</div>

<!-- INTRO -->
<div class="intro">
    <div class="intro-title">¿Para qué sirve este módulo?</div>
    <div class="intro-text">
        El módulo de Reportes SRI permite generar los documentos necesarios para las declaraciones mensuales ante el Servicio de Rentas Internas de Ecuador:
        el <strong>ATS</strong> (Anexo Transaccional Simplificado) en formato XML para subir al portal del SRI y en PDF para revisión interna;
        el <strong>Formulario 103</strong> con el detalle de retenciones en la fuente del IR; y el
        <strong>Formulario 104</strong> con la liquidación mensual del IVA 15%.
        <br>Los datos se toman directamente de las compras, ventas y retenciones registradas en el sistema para el período seleccionado.
    </div>
</div>

<!-- SECCIÓN 1: ACCESO AL MÓDULO -->
<div class="sec">
    <div class="sec-header" style="background:#1F2D3D;color:white;border-radius:4px 4px 0 0;">
        <div class="sec-num" style="color:#F59E0B;">↗</div>
        <div class="sec-title">Cómo acceder al módulo</div>
        <div class="sec-sub">Menú: Reportes → Reportes SRI</div>
    </div>
    <div class="sec-body">
        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num step-num-ats">1</div>
                    <div class="step-body">
                        <div class="step-title">Abrir el menú Reportes</div>
                        <div class="step-desc">En el sidebar izquierdo, clic en <strong>Reportes</strong>. Se despliega el submenú con <strong>Reportes SRI</strong>.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-ats">2</div>
                    <div class="step-body">
                        <div class="step-title">Seleccionar Reportes SRI</div>
                        <div class="step-desc">Clic en <strong>Reportes SRI</strong>. Se abre la pantalla con los 3 reportes disponibles para el período fiscal.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num step-num-ats">3</div>
                    <div class="step-body">
                        <div class="step-title">Seleccionar período fiscal</div>
                        <div class="step-desc">En cada reporte hay un selector de <strong>Período fiscal</strong> (mes y año). El sistema muestra los últimos 24 meses disponibles.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-ats">4</div>
                    <div class="step-body">
                        <div class="step-title">Datos requeridos</div>
                        <div class="step-desc">Asegurarse de que las compras y ventas del período estén registradas en el sistema antes de generar los reportes.</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="info"><strong>Requisito previo:</strong> Los reportes toman datos de las tablas de <em>compras</em>, <em>facturas de venta</em> y <em>retenciones</em> del período seleccionado. Si aún no han sido registrados todos los documentos del mes, el reporte estará incompleto.</div>
    </div>
</div>

<!-- SECCIÓN 2: ATS -->
<div class="sec">
    <div class="sec-header ats-header" style="border-radius:4px 4px 0 0;">
        <div class="sec-num">ATS</div>
        <div class="sec-title">Anexo Transaccional Simplificado</div>
        <div class="sec-sub">Declaración mensual de compras, ventas y retenciones</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            El ATS es la declaración mensual más importante para contribuyentes especiales y agentes de retención.
            Resume todas las transacciones del período con detalle de IVA, base imponible e identificación del proveedor/cliente.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num step-num-ats">1</div>
                    <div class="step-body">
                        <div class="step-title">Seleccionar período</div>
                        <div class="step-desc">Elegir el mes y año en el selector <strong>Período fiscal</strong> de la card ATS.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-ats">2</div>
                    <div class="step-body">
                        <div class="step-title">Descargar XML</div>
                        <div class="step-desc">Clic en <span class="btn-sim btn-xml">Descargar XML</span>. El sistema genera el ATS en formato XML v2.0.0 compatible con el portal SRI.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num step-num-ats">3</div>
                    <div class="step-body">
                        <div class="step-title">Ver resumen PDF</div>
                        <div class="step-desc">Clic en <span class="btn-sim btn-ats">Ver PDF</span>. Se abre un modal con el resumen en PDF: tablas de compras, ventas y retenciones del período.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-ats">4</div>
                    <div class="step-body">
                        <div class="step-title">Subir al SRI</div>
                        <div class="step-desc">Ingresar al portal <strong>sri.gob.ec → DIMM Formularios</strong> y subir el archivo XML descargado en el paso anterior.</div>
                    </div>
                </div>
            </div>
        </div>

        <table class="tabla">
            <thead style="background:#B45309;">
                <tr>
                    <th>Sección ATS</th>
                    <th>Contenido</th>
                    <th>Fuente de datos</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Compras</strong></td>
                    <td>Facturas de compra por proveedor: base 0%, base gravada, IVA</td>
                    <td>Tabla <span style="font-family:monospace;font-size:7px;">compras</span></td>
                </tr>
                <tr>
                    <td><strong>Ventas</strong></td>
                    <td>Facturas de venta por cliente: base 0%, base 15%, IVA generado</td>
                    <td>Tabla <span style="font-family:monospace;font-size:7px;">facturas</span></td>
                </tr>
                <tr>
                    <td><strong>Retenciones</strong></td>
                    <td>Comprobantes de retención emitidos a proveedores</td>
                    <td>Tabla <span style="font-family:monospace;font-size:7px;">retenciones</span></td>
                </tr>
            </tbody>
        </table>

        <div class="tip"><strong>Nombre del archivo XML:</strong> <span style="font-family:monospace;">ATS_[RUC]_[AAAAMM].xml</span> — p. ej. <span style="font-family:monospace;">ATS_1711293454001_202606.xml</span></div>
    </div>
</div>

<!-- SECCIÓN 3: FORMULARIO 103 -->
<div class="sec">
    <div class="sec-header f103-header" style="border-radius:4px 4px 0 0;">
        <div class="sec-num" style="font-size:9px;">103</div>
        <div class="sec-title">Formulario 103 — Retenciones en la Fuente del IR</div>
        <div class="sec-sub">Declaración mensual de retenciones IR</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Declaración mensual del Impuesto a la Renta retenido a proveedores y prestadores de servicios.
            El sistema agrupa automáticamente las retenciones por código SRI y porcentaje aplicado.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num step-num-f103">1</div>
                    <div class="step-body">
                        <div class="step-title">Seleccionar período</div>
                        <div class="step-desc">Elegir mes/año en el selector de la card <strong>Formulario 103</strong>.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-f103">2</div>
                    <div class="step-body">
                        <div class="step-title">Generar PDF</div>
                        <div class="step-desc">Clic en <span class="btn-sim btn-f103">Generar Formulario 103 PDF</span>. Se abre el visor con el resumen de retenciones agrupado por código.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num step-num-f103">3</div>
                    <div class="step-body">
                        <div class="step-title">Revisar códigos SRI</div>
                        <div class="step-desc">El PDF muestra código, concepto, porcentaje, base imponible y valor retenido. El total a pagar aparece en la tabla de liquidación.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-f103">4</div>
                    <div class="step-body">
                        <div class="step-title">Declarar en el SRI</div>
                        <div class="step-desc">Ingresar los valores al <strong>DIMM Formularios</strong> del portal SRI usando los totales del PDF generado.</div>
                    </div>
                </div>
            </div>
        </div>

        <table class="tabla">
            <thead style="background:#1A3A5C;">
                <tr>
                    <th>Código SRI</th>
                    <th>Concepto principal</th>
                    <th>% Retención</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="cod" style="color:#1A3A5C;">303</td>
                    <td>Honorarios profesionales y dietas</td>
                    <td>10%</td>
                </tr>
                <tr>
                    <td class="cod" style="color:#1A3A5C;">304</td>
                    <td>Servicios donde predomina la mano de obra</td>
                    <td>2%</td>
                </tr>
                <tr>
                    <td class="cod" style="color:#1A3A5C;">307</td>
                    <td>Servicios entre sociedades</td>
                    <td>2%</td>
                </tr>
                <tr>
                    <td class="cod" style="color:#1A3A5C;">310</td>
                    <td>Transferencia de bienes de naturaleza corporal</td>
                    <td>1%</td>
                </tr>
                <tr>
                    <td class="cod" style="color:#1A3A5C;">341</td>
                    <td>Otras retenciones aplicables</td>
                    <td>1%</td>
                </tr>
            </tbody>
        </table>

        <div class="info"><strong>Los códigos se asignan al registrar la compra.</strong> Asegurarse de que cada línea de retención en el módulo de Compras tenga el código SRI correcto según el tipo de gasto o servicio contratado.</div>
    </div>
</div>

<!-- SECCIÓN 4: FORMULARIO 104 -->
<div class="sec">
    <div class="sec-header f104-header" style="border-radius:4px 4px 0 0;">
        <div class="sec-num" style="font-size:9px;">104</div>
        <div class="sec-title">Formulario 104 — Declaración del IVA</div>
        <div class="sec-sub">Liquidación mensual IVA 15%</div>
    </div>
    <div class="sec-body">
        <p style="font-size:8px;color:#555770;margin-bottom:8px;">
            Declaración mensual del Impuesto al Valor Agregado. El sistema calcula automáticamente el IVA causado
            en ventas, el crédito tributario de compras y las retenciones de IVA recibidas, para determinar
            el saldo a pagar o el crédito para el siguiente período.
        </p>

        <div class="grid2">
            <div class="g2l">
                <div class="step">
                    <div class="step-num step-num-f104">1</div>
                    <div class="step-body">
                        <div class="step-title">Seleccionar período</div>
                        <div class="step-desc">Elegir mes/año en el selector de la card <strong>Formulario 104</strong>.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-f104">2</div>
                    <div class="step-body">
                        <div class="step-title">Generar PDF</div>
                        <div class="step-desc">Clic en <span class="btn-sim btn-f104">Generar Formulario 104 PDF</span>. Se abre el visor con la liquidación completa del IVA.</div>
                    </div>
                </div>
            </div>
            <div class="g2r">
                <div class="step">
                    <div class="step-num step-num-f104">3</div>
                    <div class="step-body">
                        <div class="step-title">Revisar la liquidación</div>
                        <div class="step-desc">El PDF muestra ventas (401/402), compras (552), retenciones (609) y el resultado final: impuesto a pagar o crédito tributario.</div>
                    </div>
                </div>
                <div class="step">
                    <div class="step-num step-num-f104">4</div>
                    <div class="step-body">
                        <div class="step-title">Declarar en el SRI</div>
                        <div class="step-desc">Ingresar los valores al portal SRI antes del <strong>28 del mes siguiente</strong> (contribuyentes especiales según calificación).</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="campo-resumen">
            <div class="campo-row">
                <div class="campo-a">Casilla 401</div>
                <div class="campo-b">Ventas netas tarifa 15% (base gravada)</div>
            </div>
            <div class="campo-row">
                <div class="campo-a">Casilla 402</div>
                <div class="campo-b">Ventas netas tarifa 0% (exentas)</div>
            </div>
            <div class="campo-row">
                <div class="campo-a">Casilla 431</div>
                <div class="campo-b">IVA causado en ventas (15% × base 401)</div>
            </div>
            <div class="campo-row">
                <div class="campo-a">Casilla 552</div>
                <div class="campo-b">Crédito tributario de compras (IVA pagado)</div>
            </div>
            <div class="campo-row">
                <div class="campo-a">Casilla 609</div>
                <div class="campo-b">Retenciones de IVA recibidas de clientes</div>
            </div>
            <div class="campo-row" style="background:#F0FDF4;">
                <div class="campo-a" style="color:#166534;font-weight:bold;">Casilla 601</div>
                <div class="campo-b" style="color:#166534;">Impuesto a pagar = 431 − 552 − 609 (si positivo)</div>
            </div>
            <div class="campo-row" style="background:#FEF9EC;">
                <div class="campo-a" style="color:#92400E;font-weight:bold;">Casilla 615</div>
                <div class="campo-b" style="color:#92400E;">Crédito tributario próximo período (si negativo)</div>
            </div>
        </div>

        <div class="ok"><strong>Saldo positivo (casilla 601):</strong> Se debe pagar al SRI antes del vencimiento. <strong>Saldo negativo (casilla 615):</strong> El exceso de crédito tributario se arrastra al siguiente mes como saldo a favor.</div>
    </div>
</div>

<!-- FOOTER -->
<div class="footer">
    <div class="f-left">ERP Altamira · Reportes SRI · {{ $empresa->razon_social ?? 'Altamira' }}</div>
    <div class="f-right">Versión 2.0 · Generado: {{ now()->format('d/m/Y H:i') }}</div>
</div>

</body>
</html>
