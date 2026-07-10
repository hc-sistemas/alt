<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;
               color:#1A1A2E;background:#fff; }

        .portada { page-break-after:always;padding:60px 50px;
                   text-align:center;background:#7C2D12;color:#fff;min-height:297mm; }
        .portada-logo { font-size:28px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#F59E0B; }
        .portada-sub  { font-size:11px;color:#FED7AA;margin-bottom:60px; }
        .portada-divider { width:60px;height:3px;background:#F59E0B;margin:0 auto 50px; }
        .portada-titulo { font-size:22px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:14px;color:#F59E0B;margin-bottom:8px; }
        .portada-ver    { font-size:10px;color:#FDBA74;margin-bottom:70px; }
        .portada-meta   { font-size:9px;color:#FED7AA;line-height:1.8; }
        .portada-meta strong { color:#FFEDD5; }

        .page { padding:22px 26px 18px; }

        .header-page { display:table;width:100%;margin-bottom:12px;
                       padding-bottom:8px;border-bottom:2px solid #7C2D12; }
        .hp-left  { display:table-cell;vertical-align:middle;width:65%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:35%; }
        .hp-empresa { font-size:10px;font-weight:bold;color:#7C2D12; }
        .hp-titulo  { font-size:7px;color:#555770;margin-top:1px; }
        .hp-pag     { font-size:7px;color:#555770; }

        .sec { margin-bottom:16px; }
        .sec-num { display:inline-block;background:#7C2D12;color:#F59E0B;
                   font-size:7px;font-weight:bold;padding:2px 6px;
                   border-radius:3px;margin-bottom:5px;letter-spacing:0.5px;
                   text-transform:uppercase; }
        .sec-titulo { font-size:13px;font-weight:bold;color:#7C2D12;margin-bottom:2px; }
        .sec-desc { font-size:8.5px;color:#444;line-height:1.6;margin-bottom:8px; }

        h3 { font-size:9.5px;font-weight:bold;color:#7C2D12;
             margin-bottom:4px;margin-top:10px; }

        table { width:100%;border-collapse:collapse;margin-bottom:8px; }
        thead tr { background:#7C2D12; }
        thead th { padding:5px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;color:#fff;text-transform:uppercase;letter-spacing:0.3px; }
        thead th.c { text-align:center; }
        tbody td { padding:4.5px 7px;border-bottom:1px solid #E2E8F0;
                   font-size:8px;color:#1A1A2E;vertical-align:top; }
        tbody tr:nth-child(even) { background:#FFF7ED; }
        tbody td strong { color:#7C2D12; }

        .box { border-left:3px solid #F59E0B;background:#FFFBEB;
               padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .box-titulo { font-size:7.5px;font-weight:bold;color:#92400E;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:3px; }
        .box p { font-size:8px;color:#78350F;line-height:1.6;margin:0; }

        .nota { border-left:3px solid #F97316;background:#FFF7ED;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .nota p { font-size:8px;color:#9A3412;line-height:1.6;margin:0; }

        .alerta { border-left:3px solid #EF4444;background:#FEF2F2;
                  padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .alerta p { font-size:8px;color:#991B1B;line-height:1.6;margin:0; }

        .info { border-left:3px solid #3B82F6;background:#EFF6FF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .info p { font-size:8px;color:#1E40AF;line-height:1.6;margin:0; }

        .pasos { margin-bottom:8px; }
        .paso { display:table;width:100%;margin-bottom:4px; }
        .paso-num  { display:table-cell;width:20px;vertical-align:top; }
        .paso-num span { display:inline-block;background:#F59E0B;color:#fff;
                         font-size:7.5px;font-weight:bold;width:16px;height:16px;
                         border-radius:50%;text-align:center;line-height:16px; }
        .paso-cont { display:table-cell;vertical-align:top;font-size:8px;
                     color:#444;line-height:1.6;padding-left:4px; }
        .paso-cont strong { color:#7C2D12; }

        .badge { display:inline-block;padding:1px 6px;border-radius:10px;
                 font-size:7px;font-weight:bold;margin:1px; }
        .b-azul   { background:#DBEAFE;color:#1E40AF; }
        .b-verde  { background:#DCFCE7;color:#166534; }
        .b-rojo   { background:#FEE2E2;color:#991B1B; }
        .b-gris   { background:#F1F5F9;color:#475569; }
        .b-dorado { background:#FEF3C7;color:#92400E; }
        .b-naranja { background:#FFEDD5;color:#9A3412; }

        .diagrama { font-family:monospace;font-size:7.5px;color:#475569;
                    background:#FFF7ED;border:1px solid #FED7AA;
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
    <div class="portada-modulo">Módulo Compras</div>
    <div class="portada-ver">
        Versión 2.0 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}
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
            <div class="hp-titulo">Manual de Uso · Módulo Compras</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">Índice &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Navegación</div>
        <div class="sec-titulo">Tabla de Contenidos</div>
        <div class="sec-desc">Altamira ERP — Módulo Compras · 4 páginas de contenido. Use esta tabla para ubicar rápidamente cada sección.</div>

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
                    <td><span class="badge b-naranja">§ 1</span> Proveedores</td>
                    <td>Crear / editar proveedores · RUC único · Estado activo / inactivo · Filtros y búsqueda</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">1–2</td>
                    <td><span class="badge b-naranja">§ 2</span> Registro de Compras</td>
                    <td>Tipos de documento (FAC, LIQ, TIK, CON, EXT) · Retenciones IR/IVA · Asiento automático al activar</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-naranja">§ 2</span> Cargar XML SRI</td>
                    <td>Parsear XML del SRI · Auto-rellenar formulario · Buscar proveedor por RUC</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-naranja">§ 3</span> Cuentas por Pagar</td>
                    <td>Registrar CxP · Filtros de vencimiento · Pagar · Detalle de movimientos</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-naranja">§ 4</span> Anticipos a Proveedores</td>
                    <td>Registrar anticipo · Asiento automático · Filtros y estados</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-naranja">§ 5</span> Importaciones</td>
                    <td>13 conceptos de costo (ISD, Advalorem, FODINFA…) · Liquidar · Prorrateo · Crear factura</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td><span class="badge b-naranja">§ 6</span> Devoluciones de Compra</td>
                    <td>Crear devolución · Seleccionar ítems de compra original · Asiento automático · Anular</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td>Referencia Rápida</td>
                    <td>Flujo típico de una compra: 6 pasos</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="nota"><p><strong>Ruta en el menú:</strong> Compras → Facturas / Proveedores / CxP / Anticipos / Importaciones / Devoluciones. Cada compra activada genera automáticamente asiento contable y actualiza CxP.</p></div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Módulo Compras · v2.0</div>
        <div class="f-r">{{ now()->format('d/m/Y') }} · Índice de contenidos</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 1 ══════════════════════════════ --}}
<div class="page">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Compras</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Introducción</div>
        <div class="sec-titulo">¿Para qué sirve el módulo Compras?</div>
        <div class="sec-desc">
            El módulo Compras gestiona todo el ciclo de abastecimiento de la empresa: desde el registro de proveedores
            hasta el pago de las facturas. Incluye importaciones internacionales, devoluciones y el control de
            saldos pendientes (Cuentas por Pagar). Cada compra registrada genera automáticamente el asiento
            contable correspondiente.
        </div>

        <div class="diagrama">Menú: Compras
├── Proveedores         → Catálogo de proveedores locales e internacionales
├── Compras             → Registro de facturas, liquidaciones y contratos
├── Cuentas por Pagar   → Saldos pendientes con proveedores
├── Anticipos           → Pagos adelantados a proveedores
├── Importaciones       → Gestión de importaciones con prorrateo de costos
└── Devoluciones        → Devoluciones de mercadería a proveedores</div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 1</div>
        <div class="sec-titulo">Proveedores</div>
        <div class="sec-desc">
            Los proveedores son las personas o empresas de quienes se adquieren bienes o servicios.
            Pueden ser locales (Ecuador) o internacionales.
        </div>

        <h3>1.1 Crear un proveedor</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Compras → Proveedores</strong> y hacer clic en <strong>+ Nuevo Proveedor</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ingresar: <strong>Tipo de identificación</strong> (RUC, Cédula, Pasaporte), <strong>Número</strong>, <strong>Razón Social</strong>, <strong>Nombre Comercial</strong>, dirección, teléfono y correo.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Seleccionar <strong>País</strong> (Ecuador para locales, otro país para internacionales).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Guardar. El proveedor queda activo y disponible para registrar compras.</div>
            </div>
        </div>

        <table>
            <thead><tr><th>Campo</th><th>Descripción</th><th>Obligatorio</th></tr></thead>
            <tbody>
                <tr><td><strong>Tipo ID</strong></td><td>RUC / Cédula / Pasaporte / Otro</td><td class="c">Sí</td></tr>
                <tr><td><strong>Identificación</strong></td><td>RUC o número de documento</td><td class="c">Sí</td></tr>
                <tr><td><strong>Razón Social</strong></td><td>Nombre legal del proveedor</td><td class="c">Sí</td></tr>
                <tr><td><strong>Nombre Comercial</strong></td><td>Nombre abreviado para búsquedas</td><td class="c">No</td></tr>
                <tr><td><strong>Teléfono / Email</strong></td><td>Datos de contacto</td><td class="c">No</td></tr>
                <tr><td><strong>País</strong></td><td>Ecuador u otro para internacionales</td><td class="c">No</td></tr>
                <tr><td><strong>Días de crédito</strong></td><td>Plazo default para facturas de este proveedor</td><td class="c">No</td></tr>
            </tbody>
        </table>

        <div class="col2">
            <div class="col2-l">
                <div class="box">
                    <div class="box-titulo">Exportar listado</div>
                    <p>El botón <strong>PDF</strong> genera un listado de todos los proveedores activos. El botón <strong>Excel</strong> descarga el mismo listado en formato .xlsx para análisis.</p>
                </div>
            </div>
            <div class="col2-r">
                <div class="nota"><p><strong>Inactivar proveedor:</strong> El toggle de estado permite desactivar un proveedor sin eliminarlo. Los proveedores inactivos no aparecen en los selectores de nuevas compras.</p></div>
            </div>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 2</div>
        <div class="sec-titulo">Registro de Compras</div>
        <div class="sec-desc">
            El módulo de compras registra todas las adquisiciones de la empresa: facturas locales, liquidaciones de compra,
            tickets, contratos de servicio y facturas de importación. Cada compra genera una Cuenta por Pagar
            y un asiento contable automático.
        </div>

        <h3>2.1 Tipos de documento de compra</h3>
        <table>
            <thead><tr><th>Código</th><th>Tipo</th><th>Descripción</th><th>IVA</th></tr></thead>
            <tbody>
                <tr>
                    <td><span class="badge b-naranja">FAC</span></td>
                    <td><strong>Factura</strong></td>
                    <td>Factura con IVA de proveedores locales</td>
                    <td>15% / 0%</td>
                </tr>
                <tr>
                    <td><span class="badge b-azul">LIQ</span></td>
                    <td><strong>Liquidación de Compra</strong></td>
                    <td>Para personas naturales no obligadas a facturar</td>
                    <td>Variable</td>
                </tr>
                <tr>
                    <td><span class="badge b-gris">TIK</span></td>
                    <td><strong>Ticket / Nota de Venta</strong></td>
                    <td>Compras de consumidor final (RISE)</td>
                    <td>Incluido</td>
                </tr>
                <tr>
                    <td><span class="badge b-verde">CON</span></td>
                    <td><strong>Contrato de Servicio</strong></td>
                    <td>Servicios recurrentes o de largo plazo</td>
                    <td>Variable</td>
                </tr>
                <tr>
                    <td><span class="badge b-dorado">EXT</span></td>
                    <td><strong>Factura Exterior</strong></td>
                    <td>Facturas de proveedores internacionales</td>
                    <td>0%</td>
                </tr>
            </tbody>
        </table>

        <div class="nota"><p><strong>Facturas de Exterior (EXT):</strong> Siempre tienen IVA 0%, independientemente del porcentaje configurado en los detalles. El sistema aplica esta regla automáticamente.</p></div>

        <h3>2.2 Registrar una compra</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Compras → Compras</strong> y hacer clic en <strong>+ Nueva Compra</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar <strong>Proveedor</strong> y el <strong>Tipo de documento</strong> (FAC/LIQ/TIK/CON/EXT).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Ingresar el <strong>número de documento</strong> (el sistema verifica que no esté duplicado para el mismo proveedor).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Completar <strong>Fecha</strong>, <strong>Días de crédito</strong> (0 = pago contado), <strong>Bodega</strong> de recepción y <strong>detalles</strong> de productos/servicios.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>5</span></div>
                <div class="paso-cont">Guardar. Se crea la CxP y el asiento contable automático.</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 1 Proveedores &nbsp;·&nbsp; § 2 Registro de Compras &nbsp;·&nbsp; Módulo Compras</div>
        <div class="f-r">Pág. 1 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 2 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Compras</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 2 (cont.)</div>
        <div class="sec-titulo">Cargar XML del SRI</div>
        <div class="sec-desc">
            El sistema puede leer archivos XML de facturas electrónicas autorizadas por el SRI y rellenar automáticamente
            el formulario de nueva compra. Esto elimina errores de tipeo y acelera el registro.
        </div>

        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En la pantalla de Compras, hacer clic en el botón celeste <strong>Cargar XML SRI</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar el archivo <strong>.xml</strong> descargado del portal del SRI o del proveedor.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El sistema extrae automáticamente: RUC, razón social, número de documento, clave de acceso, fecha, subtotal, IVA y total.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Si el proveedor ya existe en el sistema (por RUC), se selecciona automáticamente. Si no existe, se debe crear primero en Proveedores.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>5</span></div>
                <div class="paso-cont">El formulario de nueva compra aparece con todos los datos del XML pre-cargados. Solo completar los detalles de productos y guardar.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Dato extraído del XML</div>
            <p>RUC del emisor · Razón social · Número de documento (establecimiento-punto de emisión-secuencial) · Clave de acceso SRI · Fecha de emisión · Subtotal sin impuestos · IVA · Total con impuestos · Detalles de productos</p>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 3</div>
        <div class="sec-titulo">Cuentas por Pagar (CxP)</div>
        <div class="sec-desc">
            Las Cuentas por Pagar registran el saldo pendiente con cada proveedor. Cada compra a crédito genera
            automáticamente una CxP con la fecha de vencimiento calculada según los días de crédito configurados.
        </div>

        <h3>3.1 Ver el listado de CxP</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Compras → Cuentas por Pagar</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Usar los filtros de vencimiento para priorizar pagos:</div>
            </div>
        </div>

        <table>
            <thead><tr><th>Filtro</th><th>Muestra</th></tr></thead>
            <tbody>
                <tr><td><span class="badge b-rojo">Vencidas</span></td><td>CxP cuya fecha de vencimiento ya pasó</td></tr>
                <tr><td><span class="badge b-naranja">Hoy</span></td><td>CxP que vencen el día de hoy</td></tr>
                <tr><td><span class="badge b-dorado">Esta semana</span></td><td>CxP que vencen en los próximos 7 días</td></tr>
                <tr><td><span class="badge b-azul">Este mes</span></td><td>CxP que vencen en el mes actual</td></tr>
                <tr><td><span class="badge b-verde">Este año</span></td><td>CxP que vencen en el año actual</td></tr>
            </tbody>
        </table>

        <h3>3.2 Registrar un pago</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En la fila de la CxP a pagar, hacer clic en el botón <strong>Pagar</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ingresar el <strong>monto a pagar</strong> (puede ser un abono parcial o el total), la <strong>fecha de pago</strong>, el <strong>banco/caja</strong> desde donde se paga y el <strong>método</strong> (transferencia, efectivo, cheque).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Guardar. El saldo de la CxP se reduce automáticamente. Si el pago es total, la CxP queda como <span class="badge b-verde">PAGADA</span>.</div>
            </div>
        </div>

        <div class="nota"><p><strong>Abonos parciales:</strong> Se pueden registrar múltiples pagos contra la misma CxP. El saldo pendiente se actualiza en tiempo real y el historial de pagos queda registrado.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 4</div>
        <div class="sec-titulo">Anticipos a Proveedores</div>
        <div class="sec-desc">
            Un anticipo es un pago adelantado a un proveedor antes de recibir la factura. Es común en importaciones
            y proveedores que requieren un depósito previo.
        </div>

        <h3>4.1 Registrar un anticipo</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Compras → Anticipos</strong> y hacer clic en <strong>+ Nuevo Anticipo</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar el <strong>Proveedor</strong>, ingresar el <strong>monto</strong>, <strong>fecha</strong>, <strong>banco origen</strong> y el <strong>concepto</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Guardar. El anticipo genera el asiento: <em>Anticipos Proveedores DEBE · Banco HABER</em>.</td>
            </div>
        </div>

        <h3>4.2 Cruzar anticipo con una CxP</h3>
        <div class="sec-desc">
            Cuando llega la factura del proveedor, se puede aplicar (cruzar) el anticipo contra la CxP generada.
        </div>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En la fila del anticipo, hacer clic en <strong>Cruzar</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Se muestran las CxP pendientes del mismo proveedor. Seleccionar la CxP a cruzar.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El anticipo se aplica como pago parcial o total contra la CxP. El asiento revierte el anticipo: <em>Proveedores DEBE · Anticipos Proveedores HABER</em>.</div>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 2 XML SRI &nbsp;·&nbsp; § 3 CxP &nbsp;·&nbsp; § 4 Anticipos &nbsp;·&nbsp; Módulo Compras</div>
        <div class="f-r">Pág. 2 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 3 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Compras</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 5</div>
        <div class="sec-titulo">Importaciones</div>
        <div class="sec-desc">
            El módulo de importaciones gestiona el proceso completo de traer mercadería desde el exterior:
            desde el registro inicial hasta la liquidación con prorrateo de costos de importación (CIF).
            Cada importación puede tener múltiples costos adicionales que se distribuyen entre los productos.
        </div>

        <h3>5.1 Crear una importación</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Compras → Importaciones</strong> y hacer clic en <strong>+ Nueva Importación</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ingresar: <strong>nombre/referencia</strong> de la importación, <strong>proveedor</strong> internacional, <strong>país de embarque</strong>, <strong>divisa</strong>, <strong>tipo de cambio</strong> y <strong>costo FOB</strong> (precio en origen).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Agregar los <strong>productos</strong> de la importación con cantidad y precio unitario en la divisa del proveedor.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Guardar. La importación queda en estado <span class="badge b-azul">En tránsito</span>.</div>
            </div>
        </div>

        <h3>5.2 Agregar costos de importación</h3>
        <div class="sec-desc">
            Los costos de importación son gastos adicionales al precio FOB que forman parte del costo total de la mercadería (CIF + gastos de destino). Se agregan al detalle de la importación antes de liquidarla.
        </div>

        <table>
            <thead><tr><th colspan="2">Conceptos de Costo disponibles</th></tr></thead>
            <tbody>
                <tr><td><strong>ISD</strong></td><td>Impuesto a la Salida de Divisas</td></tr>
                <tr><td><strong>IVA 15%</strong></td><td>IVA pagado en aduana</td></tr>
                <tr><td><strong>Seguro Transporte Internacional</strong></td><td>Seguro de la carga en tránsito</td></tr>
                <tr><td><strong>Advalorem</strong></td><td>Arancel sobre el valor de la mercancía</td></tr>
                <tr><td><strong>FODINFA</strong></td><td>Fondo de Desarrollo para la Infancia</td></tr>
                <tr><td><strong>ICE</strong></td><td>Impuesto a los Consumos Especiales</td></tr>
                <tr><td><strong>Flete Marítimo</strong></td><td>Costo del transporte internacional</td></tr>
                <tr><td><strong>Gastos Destino Ecuador</strong></td><td>Gastos en el puerto ecuatoriano</td></tr>
                <tr><td><strong>Honorarios Aduanero</strong></td><td>Comisión del agente de aduana</td></tr>
                <tr><td><strong>Almacenaje</strong></td><td>Bodegaje en zona franca o puerto</td></tr>
                <tr><td><strong>Honorarios Banco</strong></td><td>Comisiones bancarias por la operación</td></tr>
                <tr><td><strong>Transporte Nacional</strong></td><td>Flete desde el puerto a la bodega</td></tr>
                <tr><td><strong>Otro</strong></td><td>Cualquier costo adicional no listado</td></tr>
            </tbody>
        </table>

        <h3>5.3 Liquidar la importación (prorrateo CIF)</h3>
        <div class="sec-desc">
            Liquidar es el proceso que distribuye los costos adicionales entre los productos de la importación
            y actualiza el costo unitario de cada uno en el inventario.
        </div>

        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Asegurarse de que todos los costos adicionales estén registrados.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Hacer clic en <strong>Liquidar</strong> en la importación.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Seleccionar el <strong>método de prorrateo</strong>:
                    <span class="badge b-azul">Por Cantidad</span> distribuye costos en proporción a las unidades de cada producto.
                    <span class="badge b-verde">Por Precio</span> distribuye costos en proporción al valor FOB de cada producto.
                </div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Confirmar. El sistema calcula el costo unitario final de cada producto (FOB + costos prorrateados) y actualiza el inventario.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Crear factura de importación</div>
            <p>Desde la importación liquidada, el botón <strong>Crear Factura de Compra</strong> pre-llena automáticamente el formulario de nueva compra con el tipo <span class="badge b-dorado">EXT</span>, el proveedor internacional, los productos y el costo total liquidado.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 5 Importaciones &nbsp;·&nbsp; Módulo Compras</div>
        <div class="f-r">Pág. 3 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 4 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Compras</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 6</div>
        <div class="sec-titulo">Devoluciones de Compra</div>
        <div class="sec-desc">
            Una devolución de compra registra la mercadería que se retorna a un proveedor por defecto,
            exceso o cualquier otro motivo. Genera automáticamente el asiento contable que revierte
            el efecto de la compra original.
        </div>

        <h3>6.1 Registrar una devolución</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Compras → Devoluciones</strong> y hacer clic en <strong>+ Nueva Devolución</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar el <strong>Proveedor</strong>. Opcionalmente, vincular la devolución a una <strong>Compra origen</strong> (para tener trazabilidad).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Ingresar el <strong>número de nota de crédito</strong> del proveedor, la <strong>fecha</strong> y el <strong>motivo</strong> de la devolución.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Agregar los <strong>detalles</strong>: productos devueltos con cantidad y precio unitario. El sistema calcula subtotal e IVA en tiempo real.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>5</span></div>
                <div class="paso-cont">Guardar. Se crea el asiento: <em>Proveedores DEBE · IVA Compras HABER · Inventario/Gasto HABER</em>.</div>
            </div>
        </div>

        <table>
            <thead><tr><th>Campo</th><th>Descripción</th></tr></thead>
            <tbody>
                <tr><td><strong>Proveedor</strong></td><td>Proveedor al que se retorna la mercadería</td></tr>
                <tr><td><strong>Compra origen</strong></td><td>Compra original de donde proviene la mercadería (opcional)</td></tr>
                <tr><td><strong>Nº documento</strong></td><td>Número de nota de crédito emitida por el proveedor</td></tr>
                <tr><td><strong>Fecha</strong></td><td>Fecha de la devolución</td></tr>
                <tr><td><strong>Motivo</strong></td><td>Razón de la devolución (defecto, exceso, error, etc.)</td></tr>
                <tr><td><strong>% IVA</strong></td><td>Porcentaje de IVA aplicable (0%, 5%, 15%)</td></tr>
                <tr><td><strong>Detalles</strong></td><td>Filas de productos: descripción, cantidad, precio unitario</td></tr>
            </tbody>
        </table>

        <h3>6.2 Anular una devolución</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En la lista de devoluciones, hacer clic en el botón <strong>Anular</strong> de la fila correspondiente.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ingresar el <strong>motivo de la anulación</strong> en el cuadro de confirmación.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El sistema anula el asiento contable generado y cambia el estado a <span class="badge b-rojo">ANULADA</span>. La anulación no se puede revertir.</div>
            </div>
        </div>

        <div class="alerta"><p><strong>Importante:</strong> Anular una devolución no restaura automáticamente el stock del inventario. Si se requiere ajuste de inventario, realizarlo manualmente en el módulo de Inventario.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Referencia rápida</div>
        <div class="sec-titulo">Flujo típico de una compra</div>

        <div class="diagrama">Recibir factura del proveedor
    │
    ├── Opción A: Cargar XML del SRI (automático)
    │       └── Botón "Cargar XML SRI" → seleccionar archivo → datos pre-llenados
    │
    ├── Opción B: Ingreso manual
    │       └── + Nueva Compra → completar formulario
    │
    ├── El sistema verifica:
    │       ├── Que el documento no esté duplicado (mismo proveedor + nº documento)
    │       └── Que el período contable esté abierto
    │
    ├── Al guardar:
    │       ├── Crea registro de compra
    │       ├── Crea Cuenta por Pagar (si días crédito > 0)
    │       └── Genera asiento contable automático
    │
    ├── Pagar la CxP:
    │       └── Compras → CxP → Pagar (abono parcial o pago total)
    │
    └── Si hay devolución:
            └── Compras → Devoluciones → + Nueva Devolución</div>

        <div class="box">
            <div class="box-titulo">Acceso requerido</div>
            <p>El módulo Compras requiere el permiso <strong>"Compras - Ver"</strong> para consultar y <strong>"Compras - Crear"</strong> para registrar. El registro de pagos requiere <strong>"Compras - Editar"</strong>. La anulación de compras requiere <strong>"Compras - Eliminar"</strong> o ser administrador.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 6 Devoluciones &nbsp;·&nbsp; Referencia Rápida &nbsp;·&nbsp; Módulo Compras · v2.0</div>
        <div class="f-r">Pág. 4 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

</body>
</html>
