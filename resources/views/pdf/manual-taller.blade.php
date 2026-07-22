<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;
               color:#1A1A2E;background:#fff; }

        .portada { page-break-after:always;padding:60px 50px;
                   text-align:center;background:#3F3F46;color:#fff;min-height:297mm; }
        .portada-logo { font-size:28px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#A1A1AA; }
        .portada-sub  { font-size:11px;color:#D4D4D8;margin-bottom:60px; }
        .portada-divider { width:60px;height:3px;background:#A1A1AA;margin:0 auto 50px; }
        .portada-titulo { font-size:22px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:14px;color:#A1A1AA;margin-bottom:8px; }
        .portada-ver    { font-size:10px;color:#D4D4D8;margin-bottom:70px; }
        .portada-meta   { font-size:9px;color:#E4E4E7;line-height:1.8; }
        .portada-meta strong { color:#FAFAFA; }

        .page { padding:22px 26px 18px; }

        .header-page { display:table;width:100%;margin-bottom:12px;
                       padding-bottom:8px;border-bottom:2px solid #3F3F46; }
        .hp-left  { display:table-cell;vertical-align:middle;width:65%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:35%; }
        .hp-empresa { font-size:10px;font-weight:bold;color:#3F3F46; }
        .hp-titulo  { font-size:7px;color:#555770;margin-top:1px; }
        .hp-pag     { font-size:7px;color:#555770; }

        .sec { margin-bottom:16px; }
        .sec-num { display:inline-block;background:#3F3F46;color:#D4D4D8;
                   font-size:7px;font-weight:bold;padding:2px 6px;
                   border-radius:3px;margin-bottom:5px;letter-spacing:0.5px;
                   text-transform:uppercase; }
        .sec-titulo { font-size:13px;font-weight:bold;color:#3F3F46;margin-bottom:2px; }
        .sec-desc { font-size:8.5px;color:#444;line-height:1.6;margin-bottom:8px; }

        h3 { font-size:9.5px;font-weight:bold;color:#3F3F46;
             margin-bottom:4px;margin-top:10px; }

        table { width:100%;border-collapse:collapse;margin-bottom:8px; }
        thead tr { background:#3F3F46; }
        thead th { padding:5px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;color:#fff;text-transform:uppercase;letter-spacing:0.3px; }
        thead th.c { text-align:center; }
        tbody td { padding:4.5px 7px;border-bottom:1px solid #E2E8F0;
                   font-size:8px;color:#1A1A2E;vertical-align:top; }
        tbody tr:nth-child(even) { background:#F4F4F5; }
        tbody td strong { color:#3F3F46; }

        .box { border-left:3px solid #71717A;background:#F4F4F5;
               padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .box-titulo { font-size:7.5px;font-weight:bold;color:#3F3F46;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:3px; }
        .box p { font-size:8px;color:#27272A;line-height:1.6;margin:0; }

        .nota { border-left:3px solid #64748B;background:#F1F5F9;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .nota p { font-size:8px;color:#334155;line-height:1.6;margin:0; }

        .alerta { border-left:3px solid #EF4444;background:#FEF2F2;
                  padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .alerta p { font-size:8px;color:#991B1B;line-height:1.6;margin:0; }

        .info { border-left:3px solid #3B82F6;background:#EFF6FF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .info p { font-size:8px;color:#1E40AF;line-height:1.6;margin:0; }

        .pasos { margin-bottom:8px; }
        .paso { display:table;width:100%;margin-bottom:4px; }
        .paso-num  { display:table-cell;width:20px;vertical-align:top; }
        .paso-num span { display:inline-block;background:#71717A;color:#fff;
                         font-size:7.5px;font-weight:bold;width:16px;height:16px;
                         border-radius:50%;text-align:center;line-height:16px; }
        .paso-cont { display:table-cell;vertical-align:top;font-size:8px;
                     color:#444;line-height:1.6;padding-left:4px; }
        .paso-cont strong { color:#3F3F46; }

        .badge { display:inline-block;padding:1px 6px;border-radius:10px;
                 font-size:7px;font-weight:bold;margin:1px; }
        .b-azul   { background:#DBEAFE;color:#1E40AF; }
        .b-verde  { background:#DCFCE7;color:#166534; }
        .b-rojo   { background:#FEE2E2;color:#991B1B; }
        .b-gris   { background:#F1F5F9;color:#475569; }
        .b-acero  { background:#E4E4E7;color:#3F3F46; }
        .b-pizarra { background:#E2E8F0;color:#334155; }

        .diagrama { font-family:monospace;font-size:7.5px;color:#475569;
                    background:#F4F4F5;border:1px solid #D4D4D8;
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
    <div class="portada-modulo">Módulo Taller (Altamira Fix)</div>
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
            <div class="hp-titulo">Manual de Uso · Módulo Taller</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">Índice &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Navegación</div>
        <div class="sec-titulo">Tabla de Contenidos</div>
        <div class="sec-desc">Altamira ERP — Módulo Taller (Altamira Fix) · 4 páginas de contenido. Use esta tabla para ubicar rápidamente cada sección.</div>

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
                    <td><span class="badge b-acero">§ 1</span> Tipos de Equipo</td>
                    <td>Catálogo simple de tipos de equipo</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">1</td>
                    <td><span class="badge b-acero">§ 2</span> Ingreso de Equipos</td>
                    <td>Cliente + equipo · Imagen por URL · N° de OT automático</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-acero">§ 3</span> Órdenes de Trabajo</td>
                    <td>Lista con filtros · Estados · Reasignación de técnico</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-acero">§ 4</span> Diagnóstico</td>
                    <td>Diagnóstico técnico · Aprobación del cliente</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-acero">§ 5</span> Repuestos y Liquidación</td>
                    <td>Reserva de repuestos · Factura final · Asiento automático</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td>Referencia Rápida</td>
                    <td>Flujo típico de una reparación · Próximamente</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="nota"><p><strong>Ruta en el menú:</strong> Taller → Ingresos / Órdenes de Trabajo / Tipos de Equipo. El Diagnóstico y la Liquidación se acceden desde el detalle de cada Orden de Trabajo.</p></div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Módulo Taller · v1.0</div>
        <div class="f-r">{{ now()->format('d/m/Y') }} · Índice de contenidos</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 1 ══════════════════════════════ --}}
<div class="page">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Taller</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Introducción</div>
        <div class="sec-titulo">¿Para qué sirve el módulo Taller?</div>
        <div class="sec-desc">
            El módulo Taller administra el ciclo de reparación de equipos de Altamira Fix: desde que el equipo
            ingresa hasta que se factura y entrega. Cada Orden de Trabajo (OT) sigue un recorrido de estados,
            pasa por un diagnóstico con aprobación del cliente, y se cierra con una liquidación que genera la
            factura correspondiente.
        </div>

        <div class="diagrama">Menú: Taller
├── Ingresos             → Registro de equipos que entran a reparación
├── Órdenes de Trabajo   → Seguimiento del estado de cada reparación
├── Tipos de Equipo      → Catálogo de tipos de equipo
├── Diagnóstico          → Se accede desde el detalle de la OT
└── Liquidación          → Se accede desde el detalle de la OT</div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 1</div>
        <div class="sec-titulo">Tipos de Equipo</div>
        <div class="sec-desc">
            Catálogo simple para clasificar los equipos que ingresan al taller (ej: consola de sonido, luces,
            micrófono inalámbrico).
        </div>

        <table>
            <thead><tr><th>Campo</th><th>Descripción</th><th>Obligatorio</th></tr></thead>
            <tbody>
                <tr><td><strong>Descripción</strong></td><td>Nombre del tipo de equipo</td><td class="c">Sí</td></tr>
                <tr><td><strong>Estado</strong></td><td>Activo / Inactivo</td><td class="c">No</td></tr>
            </tbody>
        </table>
        <div class="nota"><p><strong>Eliminar tipo de equipo:</strong> No se puede eliminar un tipo que ya tenga equipos asociados.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 2</div>
        <div class="sec-titulo">Ingreso de Equipos</div>
        <div class="sec-desc">
            Registra el equipo que un cliente trae a reparar y crea automáticamente su primera Orden de Trabajo.
        </div>

        <h3>2.1 Registrar un ingreso</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Taller → Ingresos</strong> y hacer clic en <strong>+ Nuevo Ingreso</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar el <strong>cliente</strong>. Ingresar los datos del <strong>equipo</strong>: tipo, marca, modelo, número de serie, color, medida y observaciones. Si el número de serie ya existe en el sistema, se reutiliza el equipo en vez de crear uno nuevo.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Completar el <strong>diagnóstico inicial</strong> (obligatorio) y observaciones generales.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Opcionalmente, pegar el campo <strong>Imagen (URL)</strong>. Guardar.</div>
            </div>
        </div>

        <div class="info"><p><strong>Imagen (URL):</strong> este campo es un enlace de texto a una imagen ya alojada en otro sitio — el sistema no sube ni almacena archivos de fotos todavía. No es una casilla de subida de archivos.</p></div>

        <div class="col2">
            <div class="col2-l">
                <div class="box">
                    <div class="box-titulo">Número de OT automático</div>
                    <p>Al guardar el ingreso se crea su primera Orden de Trabajo con número generado automáticamente en formato <strong>OT-AÑO-000000</strong> (ej: OT-2026-000123).</p>
                </div>
            </div>
            <div class="col2-r">
                <div class="nota"><p><strong>Técnico:</strong> el ingreso no asigna ningún técnico automáticamente. La asignación se hace después, manualmente, desde la Orden de Trabajo.</p></div>
            </div>
        </div>

        <h3>2.2 Imprimir la orden de trabajo</h3>
        <div class="sec-desc">Desde el detalle del ingreso, el botón de PDF genera el comprobante de orden de trabajo para entregar al cliente.</div>
    </div>

    <div class="footer">
        <div class="f-l">§ 1 Tipos de Equipo &nbsp;·&nbsp; § 2 Ingreso de Equipos &nbsp;·&nbsp; Módulo Taller</div>
        <div class="f-r">Pág. 1 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 2 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Taller</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 3</div>
        <div class="sec-titulo">Órdenes de Trabajo</div>
        <div class="sec-desc">
            Cada Orden de Trabajo (OT) representa el seguimiento de una reparación. La lista se consulta como
            tabla con filtros — no existe una vista de tablero (kanban) ni un indicador visual de días de demora.
        </div>

        <h3>3.1 Consultar y filtrar</h3>
        <div class="sec-desc">En <strong>Taller → Órdenes de Trabajo</strong> se puede filtrar por <strong>cliente/identificación</strong>, <strong>estado</strong> y <strong>técnico</strong>.</div>

        <table>
            <thead><tr><th>Estado</th><th>Significado</th></tr></thead>
            <tbody>
                <tr><td><span class="badge b-gris">Pendiente</span></td><td>Recién ingresada, sin diagnóstico todavía</td></tr>
                <tr><td><span class="badge b-azul">En proceso</span></td><td>Con diagnóstico registrado o cliente ya aprobó</td></tr>
                <tr><td><span class="badge b-verde">Listo</span></td><td>Reparación terminada, pendiente de entrega</td></tr>
                <tr><td><span class="badge b-pizarra">Entregado</span></td><td>El equipo ya fue entregado al cliente</td></tr>
                <tr><td><span class="badge b-verde">Facturado</span></td><td>La OT fue liquidada y generó su factura</td></tr>
                <tr><td><span class="badge b-pizarra">Garantía</span></td><td>Etiqueta de estado disponible para reparaciones de garantía</td></tr>
            </tbody>
        </table>

        <h3>3.2 Cambiar estado y reasignar técnico</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Abrir el detalle de la OT y seleccionar el nuevo <strong>estado</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">En el mismo paso se puede elegir o cambiar el <strong>técnico</strong> asignado, con la opción <strong>"Sin asignar"</strong> disponible en cualquier momento.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Guardar. Si el nuevo estado es <strong>Listo</strong>, el sistema registra automáticamente la fecha de finalización real.</div>
            </div>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 4</div>
        <div class="sec-titulo">Diagnóstico</div>
        <div class="sec-desc">
            El técnico documenta el diagnóstico de la reparación y se registra si el cliente lo aprueba.
        </div>

        <h3>4.1 Registrar el diagnóstico</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Desde el detalle de la OT, ir a <strong>Diagnóstico</strong> y describir el <strong>diagnóstico</strong> (obligatorio) y, opcionalmente, el <strong>tiempo estimado</strong> (en horas o días).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Guardar. Si la OT estaba en <strong>Pendiente</strong>, pasa automáticamente a <strong>En proceso</strong>.</div>
            </div>
        </div>

        <h3>4.2 Aprobación del cliente</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Registrar si el cliente <strong>aprueba</strong> o <strong>rechaza</strong> el diagnóstico, con una observación opcional.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Si aprueba, la OT queda (o se mantiene) en estado <strong>En proceso</strong>.</div>
            </div>
        </div>

        <div class="alerta"><p><strong>Importante:</strong> aprobar el diagnóstico no reserva ni mueve repuestos de inventario. La reserva de repuestos y su consumo real ocurren por separado, en la pantalla de <strong>Liquidación</strong> (ver Sección 5).</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 3 Órdenes de Trabajo &nbsp;·&nbsp; § 4 Diagnóstico &nbsp;·&nbsp; Módulo Taller</div>
        <div class="f-r">Pág. 2 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 3 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Taller</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 5</div>
        <div class="sec-titulo">Repuestos y Liquidación</div>
        <div class="sec-desc">
            Los repuestos se agregan y la orden se cierra desde la misma pantalla: <strong>Liquidación</strong>.
            Ahí se reservan los repuestos, se calcula el costo total y se genera la factura final.
        </div>

        <h3>5.1 Agregar repuestos</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Desde el detalle de la OT, ir a <strong>Liquidación</strong> y buscar el <strong>repuesto</strong> — el buscador solo ofrece productos con stock físico en <strong>Bodega Taller</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Indicar <strong>cantidad</strong>, <strong>número de serie</strong> (opcional) y <strong>precio de venta</strong>. Agregar.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El repuesto queda en estado <span class="badge b-pizarra">RESERVADO</span> y el sistema reserva esa cantidad en Bodega Taller — todavía no se descuenta del stock.</div>
            </div>
        </div>

        <div class="alerta"><p><strong>Reservas sin liberar:</strong> mientras la OT no llegue a liquidarse, los repuestos agregados permanecen reservados de forma indefinida. Hoy no existe una forma de cancelar la orden o liberar esa reserva manualmente.</p></div>

        <h3>5.2 Liquidar la orden</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En <strong>Liquidación</strong>, ingresar el <strong>costo de mano de obra</strong> y la <strong>forma de pago</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Confirmar. El sistema genera una <strong>Factura</strong> real con una línea por cada repuesto y una línea adicional de <strong>mano de obra</strong> (sin IVA), más su forma de pago.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Cada repuesto reservado pasa a estado <span class="badge b-verde">USADO</span> y su reserva se confirma como salida definitiva de Bodega Taller. La OT queda en estado <span class="badge b-verde">FACTURADO</span>.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Factura sin envío al SRI</div>
            <p>La factura generada al liquidar es un registro real del sistema (con su asiento contable automático), pero <strong>no pasa por ningún ciclo de envío al SRI</strong> — esa funcionalidad todavía no está implementada para ningún documento del sistema, no solo para Taller.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 5 Repuestos y Liquidación &nbsp;·&nbsp; Módulo Taller</div>
        <div class="f-r">Pág. 3 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 4 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Taller</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Referencia rápida</div>
        <div class="sec-titulo">Flujo típico de una reparación</div>

        <div class="diagrama">Ingreso del equipo (cliente + equipo + diagnóstico inicial)
    │
    ├── Se crea automáticamente la Orden de Trabajo (OT-AÑO-000000)
    │
    ├── Diagnóstico:
    │       └── Técnico registra diagnóstico → OT pasa a "En proceso"
    │       └── Se registra si el cliente aprueba
    │
    ├── Liquidación:
    │       ├── Agregar repuestos → quedan "Reservados" en Bodega Taller
    │       ├── Ingresar costo de mano de obra + forma de pago
    │       └── Liquidar → genera Factura, repuestos pasan a "Usado", OT = "Facturado"
    │
    └── Cambiar estado a "Entregado" cuando el cliente retira el equipo</div>

        <div class="box">
            <div class="box-titulo">Acceso requerido</div>
            <p>Todo el módulo Taller requiere el permiso <strong>"Taller - Ver"</strong> para acceder. No se identificaron restricciones adicionales por perfil dentro de Diagnóstico o Liquidación más allá de las de sesión (empresa activa).</p>
        </div>

        <div class="nota">
            <p><strong>Próximamente:</strong> las siguientes funciones descritas en el diseño original del módulo todavía no están implementadas:</p>
            <p>· Subida real de fotos del equipo (hoy el campo Imagen solo acepta una URL de texto).</p>
            <p>· Vista de tablero (kanban) para Órdenes de Trabajo.</p>
            <p>· Alerta visual cuando una OT lleva varios días en "En proceso".</p>
            <p>· Botón para enviar un presupuesto en PDF al cliente antes de aprobar el diagnóstico.</p>
            <p>· Caso especial de garantía con factura en $0 y repuesto cargado a una cuenta de mermas — hoy "Garantía" es solo una etiqueta de estado, sin lógica de facturación distinta.</p>
            <p>· Notificación automática al vendedor cuando el equipo queda listo para entregar.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Referencia Rápida &nbsp;·&nbsp; Módulo Taller · v1.0</div>
        <div class="f-r">Pág. 4 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

</body>
</html>
