<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;
               color:#1A1A2E;background:#fff; }

        .portada { page-break-after:always;padding:60px 50px;
                   text-align:center;background:#581C87;color:#fff;min-height:297mm; }
        .portada-logo { font-size:28px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#A855F7; }
        .portada-sub  { font-size:11px;color:#E9D5FF;margin-bottom:60px; }
        .portada-divider { width:60px;height:3px;background:#A855F7;margin:0 auto 50px; }
        .portada-titulo { font-size:22px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:14px;color:#A855F7;margin-bottom:8px; }
        .portada-ver    { font-size:10px;color:#D8B4FE;margin-bottom:70px; }
        .portada-meta   { font-size:9px;color:#E9D5FF;line-height:1.8; }
        .portada-meta strong { color:#F3E8FF; }

        .page { padding:22px 26px 18px; }

        .header-page { display:table;width:100%;margin-bottom:12px;
                       padding-bottom:8px;border-bottom:2px solid #581C87; }
        .hp-left  { display:table-cell;vertical-align:middle;width:65%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:35%; }
        .hp-empresa { font-size:10px;font-weight:bold;color:#581C87; }
        .hp-titulo  { font-size:7px;color:#555770;margin-top:1px; }
        .hp-pag     { font-size:7px;color:#555770; }

        .sec { margin-bottom:16px; }
        .sec-num { display:inline-block;background:#581C87;color:#D8B4FE;
                   font-size:7px;font-weight:bold;padding:2px 6px;
                   border-radius:3px;margin-bottom:5px;letter-spacing:0.5px;
                   text-transform:uppercase; }
        .sec-titulo { font-size:13px;font-weight:bold;color:#581C87;margin-bottom:2px; }
        .sec-desc { font-size:8.5px;color:#444;line-height:1.6;margin-bottom:8px; }

        h3 { font-size:9.5px;font-weight:bold;color:#581C87;
             margin-bottom:4px;margin-top:10px; }

        table { width:100%;border-collapse:collapse;margin-bottom:8px; }
        thead tr { background:#581C87; }
        thead th { padding:5px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;color:#fff;text-transform:uppercase;letter-spacing:0.3px; }
        thead th.c { text-align:center; }
        tbody td { padding:4.5px 7px;border-bottom:1px solid #E2E8F0;
                   font-size:8px;color:#1A1A2E;vertical-align:top; }
        tbody tr:nth-child(even) { background:#FAF5FF; }
        tbody td strong { color:#581C87; }

        .box { border-left:3px solid #A855F7;background:#FAF5FF;
               padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .box-titulo { font-size:7.5px;font-weight:bold;color:#6B21A8;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:3px; }
        .box p { font-size:8px;color:#581C87;line-height:1.6;margin:0; }

        .nota { border-left:3px solid #8B5CF6;background:#F5F3FF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .nota p { font-size:8px;color:#5B21B6;line-height:1.6;margin:0; }

        .alerta { border-left:3px solid #EF4444;background:#FEF2F2;
                  padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .alerta p { font-size:8px;color:#991B1B;line-height:1.6;margin:0; }

        .info { border-left:3px solid #3B82F6;background:#EFF6FF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .info p { font-size:8px;color:#1E40AF;line-height:1.6;margin:0; }

        .pasos { margin-bottom:8px; }
        .paso { display:table;width:100%;margin-bottom:4px; }
        .paso-num  { display:table-cell;width:20px;vertical-align:top; }
        .paso-num span { display:inline-block;background:#A855F7;color:#fff;
                         font-size:7.5px;font-weight:bold;width:16px;height:16px;
                         border-radius:50%;text-align:center;line-height:16px; }
        .paso-cont { display:table-cell;vertical-align:top;font-size:8px;
                     color:#444;line-height:1.6;padding-left:4px; }
        .paso-cont strong { color:#581C87; }

        .badge { display:inline-block;padding:1px 6px;border-radius:10px;
                 font-size:7px;font-weight:bold;margin:1px; }
        .b-azul   { background:#DBEAFE;color:#1E40AF; }
        .b-verde  { background:#DCFCE7;color:#166534; }
        .b-rojo   { background:#FEE2E2;color:#991B1B; }
        .b-gris   { background:#F1F5F9;color:#475569; }
        .b-lila   { background:#F3E8FF;color:#6B21A8; }
        .b-violeta { background:#EDE9FE;color:#5B21B6; }

        .diagrama { font-family:monospace;font-size:7.5px;color:#475569;
                    background:#FAF5FF;border:1px solid #E9D5FF;
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
    <div class="portada-modulo">Módulo Inventario</div>
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
            <div class="hp-titulo">Manual de Uso · Módulo Inventario</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">Índice &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Navegación</div>
        <div class="sec-titulo">Tabla de Contenidos</div>
        <div class="sec-desc">Altamira ERP — Módulo Inventario · 5 páginas de contenido. Use esta tabla para ubicar rápidamente cada sección.</div>

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
                    <td><span class="badge b-lila">§ 1</span> Marcas y Categorías</td>
                    <td>Marcas: nombre, logo, ícono · Categorías: árbol jerárquico padre-hijo</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">1</td>
                    <td><span class="badge b-lila">§ 2</span> Bodegas</td>
                    <td>Tipos de bodega · Centro de costo · Bodegas virtuales</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-lila">§ 3</span> Productos</td>
                    <td>Formulario por pestañas: General, Precios, Inventario, Contabilidad</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-lila">§ 4</span> Listas de Precio</td>
                    <td>PVP / PVD por producto · Promociones temporales · Importación por Excel</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-lila">§ 5</span> Kárdex</td>
                    <td>Historial de movimientos por producto · Saldos · Ajustes manuales</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td><span class="badge b-lila">§ 6</span> Traslados entre Bodegas</td>
                    <td>Envío, reserva de stock, confirmación y rechazo</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">5</td>
                    <td><span class="badge b-lila">§ 7</span> Activos Fijos</td>
                    <td>Registro y depreciación mensual</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">5</td>
                    <td>Referencia Rápida</td>
                    <td>Flujo típico de ingreso de mercadería · Próximamente</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="nota"><p><strong>Ruta en el menú:</strong> Inventario → Productos / Configuración / Listas de Precio / Kárdex / Traslados / Activos Fijos. Cada producto que se vende o se traslada actualiza automáticamente los saldos de inventario.</p></div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Módulo Inventario · v1.0</div>
        <div class="f-r">{{ now()->format('d/m/Y') }} · Índice de contenidos</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 1 ══════════════════════════════ --}}
<div class="page">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Inventario</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Introducción</div>
        <div class="sec-titulo">¿Para qué sirve el módulo Inventario?</div>
        <div class="sec-desc">
            El módulo Inventario administra el catálogo de productos, sus precios, el stock disponible por bodega
            y los activos fijos de la empresa. Cada venta, compra, traslado o ajuste que se registra en el sistema
            actualiza automáticamente los saldos de inventario (kárdex).
        </div>

        <div class="diagrama">Menú: Inventario
├── Productos            → Catálogo de productos con ficha por pestañas
├── Configuración        → Marcas, Categorías y Bodegas
├── Listas de Precio     → PVP / PVD por producto y promociones temporales
├── Kárdex               → Movimientos y saldos de inventario por producto
├── Traslados            → Traslados de mercadería entre bodegas
└── Activos Fijos        → Registro y depreciación mensual</div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 1</div>
        <div class="sec-titulo">Marcas y Categorías</div>
        <div class="sec-desc">
            Marcas y categorías se configuran en <strong>Inventario → Configuración</strong> y se usan para clasificar
            y filtrar productos en todo el sistema.
        </div>

        <h3>1.1 Marcas</h3>
        <table>
            <thead><tr><th>Campo</th><th>Descripción</th><th>Obligatorio</th></tr></thead>
            <tbody>
                <tr><td><strong>Nombre</strong></td><td>Nombre de la marca (ej: Pioneer, JBL)</td><td class="c">Sí</td></tr>
                <tr><td><strong>Logo</strong></td><td>Referencia de imagen del logo</td><td class="c">No</td></tr>
                <tr><td><strong>Ícono</strong></td><td>Ícono representativo de la marca</td><td class="c">No</td></tr>
                <tr><td><strong>Estado</strong></td><td>Activa / Inactiva</td><td class="c">No</td></tr>
            </tbody>
        </table>
        <div class="nota"><p><strong>Eliminar marca:</strong> No se puede eliminar una marca que tenga productos asociados. Primero hay que reasignar o eliminar esos productos.</p></div>

        <h3>1.2 Categorías</h3>
        <div class="sec-desc">Las categorías forman un árbol jerárquico de un solo nivel de profundidad: cada categoría puede tener una categoría padre.</div>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Configuración → Categorías</strong> y crear la categoría raíz o hija, seleccionando la <strong>categoría padre</strong> (opcional).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Guardar. El sistema impide asignar una subcategoría como padre de su propia categoría padre (evita ciclos).</div>
            </div>
        </div>
        <div class="nota"><p><strong>Eliminar categoría:</strong> No se puede eliminar una categoría que tenga subcategorías o productos asociados.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 2</div>
        <div class="sec-titulo">Bodegas</div>
        <div class="sec-desc">
            Las bodegas representan las ubicaciones físicas o virtuales donde se almacena el inventario.
            Cada bodega pertenece a la empresa activa y opcionalmente a un centro de costo.
        </div>

        <h3>2.1 Tipos de bodega</h3>
        <table>
            <thead><tr><th>Tipo</th><th>Uso</th></tr></thead>
            <tbody>
                <tr><td><span class="badge b-azul">General</span></td><td>Bodega estándar de almacenamiento</td></tr>
                <tr><td><span class="badge b-lila">Importación</span></td><td>Mercadería en proceso de importación</td></tr>
                <tr><td><span class="badge b-violeta">Taller</span></td><td>Repuestos e insumos de Altamira Fix</td></tr>
                <tr><td><span class="badge b-gris">Reserva</span></td><td>Stock reservado, no disponible para venta inmediata</td></tr>
                <tr><td><span class="badge b-rojo">Cuarentena</span></td><td>Mercadería retenida (defectuosa, en revisión, etc.)</td></tr>
            </tbody>
        </table>

        <h3>2.2 Crear una bodega</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Configuración → Bodegas</strong> y hacer clic en <strong>+ Nueva Bodega</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ingresar <strong>Nombre</strong>, seleccionar <strong>Tipo</strong> y, opcionalmente, un <strong>Centro de Costo</strong> (debe pertenecer a la misma empresa activa).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Marcar <strong>Es virtual</strong> si la bodega no representa un espacio físico real. Guardar.</div>
            </div>
        </div>
        <div class="nota"><p><strong>Eliminar bodega:</strong> No se puede eliminar una bodega que ya tenga saldos de inventario registrados.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 1 Marcas y Categorías &nbsp;·&nbsp; § 2 Bodegas &nbsp;·&nbsp; Módulo Inventario</div>
        <div class="f-r">Pág. 1 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 2 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Inventario</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 3</div>
        <div class="sec-titulo">Productos</div>
        <div class="sec-desc">
            El formulario de producto se organiza en <strong>4 pestañas</strong>: General, Precios, Inventario y Contabilidad.
            El código de producto es único dentro de cada empresa.
        </div>

        <h3>3.1 Pestaña General</h3>
        <table>
            <thead><tr><th>Campo</th><th>Descripción</th><th>Obligatorio</th></tr></thead>
            <tbody>
                <tr><td><strong>Código</strong></td><td>Código único del producto para la empresa activa</td><td class="c">Sí</td></tr>
                <tr><td><strong>Nombre</strong></td><td>Nombre comercial del producto</td><td class="c">Sí</td></tr>
                <tr><td><strong>Tipo</strong></td><td>Producto / Servicio / Repuesto / Insumo</td><td class="c">Sí</td></tr>
                <tr><td><strong>Unidad</strong></td><td>Unidad / Par / Caja / Metro / Hora / Kit</td><td class="c">Sí</td></tr>
                <tr><td><strong>Marca / Categoría</strong></td><td>Clasificación del producto</td><td class="c">No</td></tr>
                <tr><td><strong>Descripción</strong></td><td>Detalle adicional del producto</td><td class="c">No</td></tr>
                <tr><td><strong>Requiere número de serie</strong></td><td>Los números de serie se registran al ingresar stock desde Kárdex</td><td class="c">No</td></tr>
                <tr><td><strong>Producto activo</strong></td><td>Si está inactivo no aparece en los selectores de venta/traslado</td><td class="c">No</td></tr>
            </tbody>
        </table>

        <h3>3.2 Pestaña Precios</h3>
        <div class="sec-desc">El sistema calcula y muestra en pantalla el <strong>margen estimado</strong> (PVP vs. Costo) mientras se editan los valores.</div>
        <table>
            <thead><tr><th>Campo</th><th>Descripción</th></tr></thead>
            <tbody>
                <tr><td><strong>PVP</strong></td><td>Precio de Venta al Público</td></tr>
                <tr><td><strong>PVD</strong></td><td>Precio de Venta a Distribuidor</td></tr>
                <tr><td><strong>Costo</strong></td><td>Costo del producto</td></tr>
                <tr><td><strong>Descuento máximo (%)</strong></td><td>Tope de descuento que un vendedor puede aplicar sin autorización</td></tr>
                <tr><td><strong>IVA (%)</strong></td><td>0% / 5% / 15%</td></tr>
                <tr><td><strong>ICE (%)</strong></td><td>Impuesto a los Consumos Especiales, si aplica</td></tr>
            </tbody>
        </table>

        <div class="col2">
            <div class="col2-l">
                <h3>3.3 Pestaña Inventario</h3>
                <div class="info"><p>Esta pestaña solo configura los umbrales de alerta (<strong>stock mínimo</strong> y <strong>stock máximo</strong>). El stock real del producto se gestiona desde el módulo <strong>Kárdex</strong>, no desde aquí.</p></div>
            </div>
            <div class="col2-r">
                <h3>3.4 Pestaña Contabilidad</h3>
                <div class="box">
                    <div class="box-titulo">Cuentas contables</div>
                    <p>Se seleccionan de un listado del Plan de Cuentas: <strong>Cuenta de Inventario</strong>, <strong>Cuenta Costo de Ventas</strong> y <strong>Cuenta de Ventas</strong>.</p>
                </div>
            </div>
        </div>

        <div class="nota"><p><strong>Eliminar producto:</strong> No se puede eliminar un producto que tenga stock registrado en alguna bodega, ni si tiene números de serie que no estén en estado "vendido".</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 3 Productos &nbsp;·&nbsp; Módulo Inventario</div>
        <div class="f-r">Pág. 2 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 3 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Inventario</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 4</div>
        <div class="sec-titulo">Listas de Precio</div>
        <div class="sec-desc">
            El listado muestra, por cada producto, el PVP y PVD configurados en la ficha del producto y el precio
            de lista específico de la empresa activa (si se ha editado), con su descuento máximo.
        </div>

        <h3>4.1 Editar precio de un producto</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Listas de Precio</strong> y hacer clic en la fila del producto a editar.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ajustar <strong>PVP</strong>, <strong>PVD</strong>, <strong>descuento máximo PVP</strong> y <strong>descuento máximo PVD</strong>. Guardar.</div>
            </div>
        </div>

        <h3>4.2 Promociones temporales</h3>
        <div class="sec-desc">
            Es posible configurar un <strong>descuento promocional</strong> adicional sobre el PVP, válido solo durante un rango de fechas.
        </div>
        <table>
            <thead><tr><th>Campo</th><th>Descripción</th><th>Obligatorio</th></tr></thead>
            <tbody>
                <tr><td><strong>Descuento promo (%)</strong></td><td>Porcentaje de descuento promocional sobre el PVP</td><td class="c">No</td></tr>
                <tr><td><strong>Vigencia desde / hasta</strong></td><td>Rango de fechas en que la promoción está activa</td><td class="c">Sí, si hay descuento promo</td></tr>
            </tbody>
        </table>
        <div class="alerta"><p><strong>Importante:</strong> Si se define un descuento promocional, las dos fechas de vigencia (desde y hasta) son obligatorias. La promoción aplica únicamente al PVP, no al PVD.</p></div>

        <h3>4.3 Edición masiva por Excel</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Preparar un archivo <strong>.xlsx</strong> con columnas: <strong>codigo</strong>, <strong>pvp_lista</strong>, <strong>pvd_lista</strong>, <strong>descuento_pvp</strong>, <strong>descuento_pvd</strong>, <strong>vigencia_desde</strong>, <strong>vigencia_hasta</strong>, <strong>descuento_promo</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Listas de Precio → Importar</strong> y subir el archivo.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El sistema procesa fila por fila: si una fila tiene un error (código no encontrado, fechas de promo incompletas o inválidas), se reporta esa fila y continúa con las demás sin abortar el archivo completo.</div>
            </div>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 5</div>
        <div class="sec-titulo">Kárdex</div>
        <div class="sec-desc">
            El Kárdex muestra el historial de movimientos de inventario. En su versión actual, la vista está organizada
            <strong>por producto</strong>: cada producto listado muestra su propio historial de entradas, salidas, traslados,
            ajustes y reservas, con el saldo anterior y posterior de cada movimiento.
        </div>

        <h3>5.1 Consultar movimientos</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Kárdex</strong>. Los productos se listan paginados, priorizando los que tienen movimientos.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Usar los filtros de <strong>búsqueda</strong> (código/nombre), <strong>bodega</strong>, <strong>fecha desde/hasta</strong> y <strong>tipo de movimiento</strong> (entrada, salida, traslado, ajuste, reserva) para acotar los resultados.</div>
            </div>
        </div>

        <h3>5.2 Saldos por bodega</h3>
        <div class="sec-desc">La pantalla de <strong>Saldos</strong> consolida el stock actual por producto y bodega, con filtro de <strong>solo críticos</strong> (stock igual o menor al stock mínimo configurado en el producto).</div>

        <h3>5.3 Ajuste manual de inventario</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Desde Kárdex o Saldos, seleccionar <strong>Ajustar</strong>: elegir <strong>producto</strong>, <strong>bodega</strong>, tipo de ajuste (<strong>positivo</strong> o <strong>negativo</strong>), <strong>cantidad</strong> y <strong>motivo</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Si el ajuste es positivo, se debe indicar el <strong>costo unitario</strong> del ingreso. Guardar registra el movimiento y actualiza el saldo de la bodega.</div>
            </div>
        </div>

        <div class="nota"><p><strong>Diseño multi-producto:</strong> Un rediseño de Kárdex con vista tipo tabla multi-producto y filtros avanzados está aprobado, pero todavía no se ha implementado. La vista actual funciona por producto individual.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 4 Listas de Precio &nbsp;·&nbsp; § 5 Kárdex &nbsp;·&nbsp; Módulo Inventario</div>
        <div class="f-r">Pág. 3 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 4 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Inventario</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 6</div>
        <div class="sec-titulo">Traslados entre Bodegas</div>
        <div class="sec-desc">
            Un traslado mueve mercadería de una bodega origen a una bodega destino. Pasa por dos etapas:
            se crea en estado <strong>pendiente</strong> y luego el receptor lo <strong>confirma</strong> (con conteo físico) o lo <strong>rechaza</strong>.
        </div>

        <h3>6.1 Crear un traslado</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Traslados</strong> y hacer clic en <strong>+ Nuevo Traslado</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar <strong>bodega origen</strong> y <strong>bodega destino</strong> (deben ser distintas).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Agregar los <strong>productos</strong> con la <strong>cantidad a enviar</strong>. El sistema valida que haya stock disponible suficiente en la bodega origen para cada producto.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Guardar. El traslado queda <span class="badge b-lila">PENDIENTE</span> con número automático (formato <strong>TRA-000001</strong>) y el stock de la bodega origen queda <strong>reservado</strong> (no disponible para venta, pero tampoco descontado todavía).</div>
            </div>
        </div>

        <h3>6.2 Confirmar (aceptar) un traslado</h3>
        <div class="sec-desc">Solo usuarios con perfil <strong>Super Admin</strong>, <strong>Admin</strong> o <strong>Bodeguero</strong> pueden confirmar o rechazar traslados.</div>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En el detalle del traslado pendiente, hacer clic en <strong>Confirmar</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ajustar la <strong>cantidad recibida</strong> por línea según el conteo físico (por defecto es igual a la cantidad enviada).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Guardar. El sistema egresa el stock definitivo de la bodega origen y lo ingresa en la bodega destino (con el costo promedio de origen), actualizando los saldos de <strong>ambas</strong> bodegas. El traslado pasa a <span class="badge b-verde">ACEPTADO</span>.</div>
            </div>
        </div>

        <h3>6.3 Rechazar (anular) un traslado</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En el detalle del traslado pendiente, hacer clic en <strong>Anular</strong> e ingresar el <strong>motivo</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">El sistema libera la reserva de stock en la bodega origen. El traslado pasa a <span class="badge b-rojo">RECHAZADO</span>.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Solo traslados pendientes</div>
            <p>Confirmar y anular únicamente están disponibles mientras el traslado está en estado <strong>pendiente</strong>. Un traslado ya aceptado o rechazado no se puede modificar.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 6 Traslados entre Bodegas &nbsp;·&nbsp; Módulo Inventario</div>
        <div class="f-r">Pág. 4 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 5 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Inventario</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 7</div>
        <div class="sec-titulo">Activos Fijos</div>
        <div class="sec-desc">
            Los activos fijos son bienes de uso prolongado de la empresa (equipos, mobiliario, vehículos, etc.) que
            se deprecian mes a mes. Cada activo se vincula opcionalmente a una cuenta contable.
        </div>

        <h3>7.1 Registrar un activo fijo</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Inventario → Activos Fijos</strong> y hacer clic en <strong>+ Nuevo Activo</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Ingresar <strong>código</strong> (único), <strong>nombre</strong>, <strong>descripción</strong>, <strong>fecha de adquisición</strong>, <strong>costo de adquisición</strong>, <strong>valor residual</strong> (opcional, debe ser menor al costo) y <strong>vida útil en años</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Opcionalmente, asociar una <strong>cuenta contable</strong> del Plan de Cuentas. Guardar.</div>
            </div>
        </div>

        <div class="alerta"><p><strong>Limitación conocida:</strong> El formulario todavía no tiene un selector de categoría del activo — todo activo nuevo se guarda con la categoría <strong>"General"</strong> por defecto. Esto se corregirá cuando se agregue el campo correspondiente al formulario.</p></div>

        <h3>7.2 Registrar la depreciación mensual</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En el detalle del activo, hacer clic en <strong>Depreciar</strong> e indicar el <strong>año</strong> y <strong>mes</strong> del período.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">El sistema calcula el monto a depreciar (el menor entre la cuota mensual y el saldo disponible hasta el valor residual) y actualiza la depreciación acumulada y el valor en libros.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Cuando el valor en libros llega al valor residual, el activo pasa automáticamente a estado <span class="badge b-rojo">DADO DE BAJA</span>.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Reglas de la depreciación</div>
            <p>Los períodos deben registrarse en <strong>orden cronológico</strong>, sin repetir un período ya registrado, sin adelantarse al mes actual y nunca antes de la fecha de adquisición. Un activo que no está en estado <strong>activo</strong> no se puede editar. No se puede eliminar un activo que ya tenga depreciaciones registradas.</p>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Referencia rápida</div>
        <div class="sec-titulo">Flujo típico de ingreso y salida de mercadería</div>

        <div class="diagrama">Ingreso de mercadería (compra o importación)
    │
    ├── Se registra desde Compras/Importaciones (módulo Compras)
    │       └── Genera movimiento de entrada en inventario_movimientos
    │
    ├── El stock queda disponible en la bodega de recepción
    │
    ├── Movimiento entre bodegas:
    │       └── Inventario → Traslados → + Nuevo Traslado → Confirmar
    │
    ├── Salida por venta:
    │       └── Se descuenta automáticamente al facturar (módulo Ventas)
    │
    └── Ajuste manual (sobrante/faltante):
            └── Inventario → Kárdex → Ajustar</div>

        <div class="box">
            <div class="box-titulo">Acceso requerido</div>
            <p>El módulo Inventario requiere el permiso <strong>"Inventario - Ver"</strong> para consultar. Crear, editar y eliminar requieren los permisos correspondientes de cada acción. Confirmar o rechazar traslados requiere además perfil <strong>Super Admin</strong>, <strong>Admin</strong> o <strong>Bodeguero</strong>.</p>
        </div>

        <div class="nota"><p><strong>Próximamente:</strong> El módulo de <strong>Inventario Físico</strong> (toma de inventario con conteo, comparativo contra el sistema y ajuste automático) está planificado pero aún no se ha implementado. Tampoco está disponible todavía el rediseño de Kárdex con vista multi-producto y filtros avanzados — ver nota en la Sección 5.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 7 Activos Fijos &nbsp;·&nbsp; Referencia Rápida &nbsp;·&nbsp; Módulo Inventario · v1.0</div>
        <div class="f-r">Pág. 5 de 5 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

</body>
</html>
