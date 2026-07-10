<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;
               color:#1A1A2E;background:#fff; }

        .portada { page-break-after:always;padding:60px 50px;
                   text-align:center;background:#064E3B;color:#fff;min-height:297mm; }
        .portada-logo { font-size:28px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#F59E0B; }
        .portada-sub  { font-size:11px;color:#A7F3D0;margin-bottom:60px; }
        .portada-divider { width:60px;height:3px;background:#F59E0B;margin:0 auto 50px; }
        .portada-titulo { font-size:22px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:14px;color:#F59E0B;margin-bottom:8px; }
        .portada-ver    { font-size:10px;color:#6EE7B7;margin-bottom:70px; }
        .portada-meta   { font-size:9px;color:#A7F3D0;line-height:1.8; }
        .portada-meta strong { color:#D1FAE5; }

        .page { padding:22px 26px 18px; }

        .header-page { display:table;width:100%;margin-bottom:12px;
                       padding-bottom:8px;border-bottom:2px solid #064E3B; }
        .hp-left  { display:table-cell;vertical-align:middle;width:65%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:35%; }
        .hp-empresa { font-size:10px;font-weight:bold;color:#064E3B; }
        .hp-titulo  { font-size:7px;color:#555770;margin-top:1px; }
        .hp-pag     { font-size:7px;color:#555770; }

        .sec { margin-bottom:16px; }
        .sec-num { display:inline-block;background:#064E3B;color:#F59E0B;
                   font-size:7px;font-weight:bold;padding:2px 6px;
                   border-radius:3px;margin-bottom:5px;letter-spacing:0.5px;
                   text-transform:uppercase; }
        .sec-titulo { font-size:13px;font-weight:bold;color:#064E3B;margin-bottom:2px; }
        .sec-desc { font-size:8.5px;color:#444;line-height:1.6;margin-bottom:8px; }

        h3 { font-size:9.5px;font-weight:bold;color:#064E3B;
             margin-bottom:4px;margin-top:10px; }

        table { width:100%;border-collapse:collapse;margin-bottom:8px; }
        thead tr { background:#064E3B; }
        thead th { padding:5px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;color:#fff;text-transform:uppercase;letter-spacing:0.3px; }
        thead th.c { text-align:center; }
        tbody td { padding:4.5px 7px;border-bottom:1px solid #E2E8F0;
                   font-size:8px;color:#1A1A2E;vertical-align:top; }
        tbody tr:nth-child(even) { background:#F0FDF4; }
        tbody td strong { color:#064E3B; }

        .box { border-left:3px solid #F59E0B;background:#FFFBEB;
               padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .box-titulo { font-size:7.5px;font-weight:bold;color:#92400E;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:3px; }
        .box p { font-size:8px;color:#78350F;line-height:1.6;margin:0; }

        .nota { border-left:3px solid #10B981;background:#ECFDF5;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .nota p { font-size:8px;color:#065F46;line-height:1.6;margin:0; }

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
        .paso-cont strong { color:#064E3B; }

        .badge { display:inline-block;padding:1px 6px;border-radius:10px;
                 font-size:7px;font-weight:bold;margin:1px; }
        .b-azul   { background:#DBEAFE;color:#1E40AF; }
        .b-verde  { background:#DCFCE7;color:#166534; }
        .b-rojo   { background:#FEE2E2;color:#991B1B; }
        .b-gris   { background:#F1F5F9;color:#475569; }
        .b-dorado { background:#FEF3C7;color:#92400E; }
        .b-morado { background:#F3E8FF;color:#6D28D9; }

        .diagrama { font-family:monospace;font-size:7.5px;color:#475569;
                    background:#F0FDF4;border:1px solid #A7F3D0;
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
    <div class="portada-modulo">Módulo Contabilidad</div>
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
            <div class="hp-titulo">Manual de Uso · Módulo Contabilidad</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">Índice &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Navegación</div>
        <div class="sec-titulo">Tabla de Contenidos</div>
        <div class="sec-desc">Altamira ERP — Módulo Contabilidad · 4 páginas de contenido. Use esta tabla para ubicar rápidamente cada sección.</div>

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
                    <td><span class="badge b-verde">§ 1</span> Plan de Cuentas</td>
                    <td>Estructura de códigos (5 niveles) · Tipos · Crear / editar cuentas · Importar y exportar Excel</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-verde">§ 2</span> Asientos Contables</td>
                    <td>Crear asiento manual · Partida doble (DEBE = HABER) · Estados · Ver PDF · Filtros y búsqueda</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">2</td>
                    <td><span class="badge b-verde">§ 3</span> Asientos Automáticos</td>
                    <td>Eventos que generan asientos: Ventas, Compras, Nómina, Bancos, Devoluciones</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-verde">§ 4</span> Ejercicios Contables</td>
                    <td>Abrir / cerrar período mensual · Cierre Fiscal Anual (6 pasos) · Alertas de período cerrado</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">3</td>
                    <td><span class="badge b-verde">§ 5</span> Parámetros Contables</td>
                    <td>39 parámetros en 6 grupos · Asignar cuentas · Autoconfigurar · Fallback automático AsientoService</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td><span class="badge b-verde">§ 6</span> Reportes Contables</td>
                    <td>Libro Diario · Mayor por Cuenta · Balance de Comprobación · Balance General · Estado de Resultados</td>
                </tr>
                <tr>
                    <td style="text-align:center;font-weight:bold">4</td>
                    <td>Referencia Rápida</td>
                    <td>Flujo contable mensual recomendado: 9 pasos</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="nota"><p><strong>Ruta en el menú:</strong> Contabilidad → Plan de Cuentas / Asientos / Ejercicios / Parámetros / Reportes. El módulo bloquea automáticamente operaciones en períodos cerrados.</p></div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Módulo Contabilidad · v2.0</div>
        <div class="f-r">{{ now()->format('d/m/Y') }} · Índice de contenidos</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 1 ══════════════════════════════ --}}
<div class="page">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Contabilidad</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Introducción</div>
        <div class="sec-titulo">¿Para qué sirve el módulo Contabilidad?</div>
        <div class="sec-desc">
            El módulo Contabilidad es el <strong>registro oficial de todas las operaciones financieras</strong> de la empresa.
            Cada venta, compra, pago, cobro y nómina genera automáticamente un asiento contable que cumple con las
            normas NEC (Normas Ecuatorianas de Contabilidad) y los requisitos del SRI. El módulo incluye el plan
            de cuentas, los asientos manuales y automáticos, el control de períodos fiscales, los parámetros de
            configuración y los reportes oficiales.
        </div>

        <div class="diagrama">Menú: Contabilidad
├── Plan de Cuentas     → Catálogo de cuentas de la empresa
├── Asientos            → Registro manual y automático de movimientos
├── Ejercicios          → Control de períodos contables (mensual/anual)
├── Parámetros          → Asignación de cuentas por tipo de operación
└── Reportes            → Libro Diario, Mayor, Balances, Estado de Resultados</div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 1</div>
        <div class="sec-titulo">Plan de Cuentas</div>
        <div class="sec-desc">
            El Plan de Cuentas es el catálogo numerado de todas las cuentas contables que la empresa utiliza.
            Sigue la estructura del esquema ecuatoriano con 5 niveles jerárquicos: Clase · Grupo · Subgrupo · Cuenta · Auxiliar.
        </div>

        <h3>1.1 Estructura de código de cuentas</h3>
        <table>
            <thead>
                <tr>
                    <th>Nivel</th><th>Ejemplo</th><th>Descripción</th><th class="c">Permite asientos</th>
                </tr>
            </thead>
            <tbody>
                <tr><td><strong>1 — Clase</strong></td><td>1</td><td>Activo</td><td class="c">No</td></tr>
                <tr><td><strong>2 — Grupo</strong></td><td>1.1</td><td>Activo Corriente</td><td class="c">No</td></tr>
                <tr><td><strong>3 — Subgrupo</strong></td><td>1.1.01</td><td>Efectivo y equivalentes</td><td class="c">No</td></tr>
                <tr><td><strong>4 — Cuenta</strong></td><td>1.1.01.01</td><td>Caja General</td><td class="c">Sí</td></tr>
                <tr><td><strong>5 — Auxiliar</strong></td><td>1.1.01.01.01</td><td>Caja Matriz</td><td class="c">Sí</td></tr>
            </tbody>
        </table>

        <div class="col2">
            <div class="col2-l">
                <h3>1.2 Tipos de cuenta</h3>
                <table>
                    <thead><tr><th>Tipo</th><th>Naturaleza</th></tr></thead>
                    <tbody>
                        <tr><td><strong>Activo</strong></td><td>Bienes y derechos de la empresa</td></tr>
                        <tr><td><strong>Pasivo</strong></td><td>Obligaciones y deudas</td></tr>
                        <tr><td><strong>Patrimonio</strong></td><td>Capital y reservas</td></tr>
                        <tr><td><strong>Ingreso</strong></td><td>Ventas y otros ingresos</td></tr>
                        <tr><td><strong>Gasto</strong></td><td>Costos y gastos operativos</td></tr>
                        <tr><td><strong>Costo</strong></td><td>Costo de ventas</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="col2-r">
                <h3>1.3 Clasificación por clase</h3>
                <table>
                    <thead><tr><th>Clase</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td><span class="badge b-azul">1</span></td><td>Activo</td></tr>
                        <tr><td><span class="badge b-rojo">2</span></td><td>Pasivo</td></tr>
                        <tr><td><span class="badge b-morado">3</span></td><td>Patrimonio</td></tr>
                        <tr><td><span class="badge b-verde">4</span></td><td>Ingresos</td></tr>
                        <tr><td><span class="badge b-dorado">5</span></td><td>Gastos y Costos</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <h3>1.4 Crear y editar cuentas</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Contabilidad → Plan de Cuentas</strong>. Se muestra el árbol completo de cuentas con su código, nombre y tipo.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Hacer clic en <strong>+ Nueva Cuenta</strong> (botón verde, esquina superior derecha).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Ingresar: <strong>Código</strong> (respeta la jerarquía numérica), <strong>Nombre</strong>, <strong>Tipo</strong> y marcar si la cuenta <strong>Permite asientos</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Guardar. El sistema detecta automáticamente el nivel y la cuenta padre según el código ingresado.</div>
            </div>
        </div>

        <div class="nota"><p><strong>Regla:</strong> Solo las cuentas de nivel 4 y 5 (con "Permite asientos" marcado) pueden recibir débitos y créditos. Las cuentas de agrupación (niveles 1–3) son solo de resumen.</p></div>

        <h3>1.5 Importar Plan de Cuentas desde Excel</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Hacer clic en el botón verde <strong>Importar Excel</strong> (junto al botón de exportar en la barra superior).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar un archivo <strong>.xlsx</strong> o <strong>.xls</strong> con columnas: <strong>codigo · nombre · tipo · permite_asientos</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El sistema crea las cuentas nuevas y <strong>omite</strong> automáticamente los códigos que ya existen (no genera duplicados).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Al terminar se muestra un resumen: <em>X creadas · Y omitidas · Z errores</em>.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Exportar Excel</div>
            <p>El botón <strong>Exportar Excel</strong> (dorado) descarga todas las cuentas activas en formato .xlsx. Útil para revisar o preparar el archivo de importación con los mismos encabezados requeridos.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 1 Plan de Cuentas &nbsp;·&nbsp; Módulo Contabilidad</div>
        <div class="f-r">Pág. 1 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 2 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Contabilidad</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 2</div>
        <div class="sec-titulo">Asientos Contables</div>
        <div class="sec-desc">
            Un asiento contable es el registro de una operación económica en el libro de contabilidad.
            En Altamira ERP, los asientos siguen el principio de <strong>partida doble</strong>: la suma del Debe siempre
            debe ser igual a la suma del Haber. El sistema bloquea automáticamente cualquier asiento desbalanceado.
        </div>

        <div class="alerta"><p><strong>Regla fundamental:</strong> DEBE = HABER. Si los totales no coinciden, el sistema lanza un error y no guarda el asiento. No existe excepción a esta regla.</p></div>

        <h3>2.1 Crear un asiento manual</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Contabilidad → Asientos</strong> y hacer clic en <strong>+ Nuevo Asiento</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Seleccionar la <strong>Fecha</strong> y escribir el <strong>Concepto</strong> del asiento.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Agregar partidas: para cada fila elegir la <strong>Cuenta</strong> (solo cuentas con "permite asientos"), ingresar valor en <strong>Debe</strong> o <strong>Haber</strong>, y una <strong>descripción</strong> opcional.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Verificar que <strong>Total Debe = Total Haber</strong> antes de guardar. El contador en pantalla actualiza en tiempo real.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>5</span></div>
                <div class="paso-cont">Hacer clic en <strong>Guardar</strong>. El sistema asigna el número correlativo automáticamente.</div>
            </div>
        </div>

        <h3>2.2 Estados de un asiento</h3>
        <table>
            <thead><tr><th>Estado</th><th>Significado</th><th>Acción disponible</th></tr></thead>
            <tbody>
                <tr>
                    <td><span class="badge b-verde">ACTIVO</span></td>
                    <td>Asiento válido y vigente, afecta los saldos de cuentas</td>
                    <td>Ver PDF · Anular</td>
                </tr>
                <tr>
                    <td><span class="badge b-rojo">ANULADO</span></td>
                    <td>Asiento revertido, no afecta saldos pero queda en el historial</td>
                    <td>Solo ver (no se elimina)</td>
                </tr>
                <tr>
                    <td><span class="badge b-gris">AUTOMÁTICO</span></td>
                    <td>Generado por el sistema (venta, compra, nómina, etc.)</td>
                    <td>Ver PDF · Anular (si el período está abierto)</td>
                </tr>
            </tbody>
        </table>

        <div class="alerta"><p><strong>Importante:</strong> Los asientos en períodos <strong>cerrados</strong> no pueden crearse ni anularse. El sistema bloquea la operación y muestra el mensaje "Período contable cerrado".</p></div>

        <h3>2.3 Imprimir asiento en PDF</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">En el listado de asientos, hacer clic en el ícono de <strong>impresora</strong> de la fila correspondiente.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">El PDF se abre en un <strong>modal dentro de la misma ventana</strong>. Desde ahí se puede imprimir o descargar sin salir de la página.</div>
            </div>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 3</div>
        <div class="sec-titulo">Asientos Automáticos</div>
        <div class="sec-desc">
            El sistema genera asientos contables automáticamente al registrar operaciones en otros módulos.
            No es necesario crear estos asientos manualmente: se generan en el momento de la transacción
            usando las cuentas configuradas en <strong>Parámetros Contables</strong>.
        </div>

        <table>
            <thead>
                <tr>
                    <th>Operación</th><th>Módulo origen</th><th>Descripción del asiento</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Venta en efectivo / transferencia</strong></td>
                    <td>Ventas</td>
                    <td>Caja/Banco DEBE · Ventas HABER · IVA Ventas HABER</td>
                </tr>
                <tr>
                    <td><strong>Venta a crédito (CxC)</strong></td>
                    <td>Ventas</td>
                    <td>Clientes DEBE · Ventas HABER · IVA Ventas HABER</td>
                </tr>
                <tr>
                    <td><strong>Cobro de CxC</strong></td>
                    <td>Ventas / CxC</td>
                    <td>Banco/Caja DEBE · Clientes HABER</td>
                </tr>
                <tr>
                    <td><strong>Anticipo de cliente</strong></td>
                    <td>Ventas</td>
                    <td>Caja/Banco DEBE · Anticipos Clientes HABER</td>
                </tr>
                <tr>
                    <td><strong>Nota de crédito emitida</strong></td>
                    <td>Ventas</td>
                    <td>Ventas DEBE · Clientes / Caja HABER</td>
                </tr>
                <tr>
                    <td><strong>Registro de compra</strong></td>
                    <td>Compras</td>
                    <td>Inventario/Gasto DEBE · IVA Crédito DEBE · Proveedores HABER</td>
                </tr>
                <tr>
                    <td><strong>Pago a proveedor</strong></td>
                    <td>Compras / CxP</td>
                    <td>Proveedores DEBE · Banco/Caja HABER</td>
                </tr>
                <tr>
                    <td><strong>Anticipo a proveedor</strong></td>
                    <td>Compras</td>
                    <td>Anticipos Proveedores DEBE · Banco/Caja HABER</td>
                </tr>
                <tr>
                    <td><strong>Nómina</strong></td>
                    <td>RRHH</td>
                    <td>Sueldos DEBE · Aporte Patronal DEBE · IESS x Pagar HABER · Nómina x Pagar HABER</td>
                </tr>
                <tr>
                    <td><strong>Lote Datafast</strong></td>
                    <td>Bancos</td>
                    <td>Vouchers DEBE · Ventas HABER</td>
                </tr>
                <tr>
                    <td><strong>Liquidación Datafast</strong></td>
                    <td>Bancos</td>
                    <td>Banco DEBE · Comisión DEBE · Retenciones DEBE · Vouchers HABER</td>
                </tr>
                <tr>
                    <td><strong>Cierre de caja</strong></td>
                    <td>Bancos</td>
                    <td>Banco DEBE · Caja HABER (traslado de efectivo)</td>
                </tr>
                <tr>
                    <td><strong>Ajuste de inventario</strong></td>
                    <td>Inventario</td>
                    <td>Inventario DEBE/HABER · Ajuste Inventario HABER/DEBE</td>
                </tr>
            </tbody>
        </table>

        <div class="nota"><p><strong>Si el período está cerrado o las cuentas no están configuradas</strong>, el asiento automático no bloquea la operación. El registro (venta, pago, etc.) se guarda igualmente y el asiento queda pendiente de creación manual.</p></div>
    </div>

    <div class="footer">
        <div class="f-l">§ 2 Asientos Contables &nbsp;·&nbsp; § 3 Asientos Automáticos &nbsp;·&nbsp; Módulo Contabilidad</div>
        <div class="f-r">Pág. 2 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 3 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Contabilidad</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 4</div>
        <div class="sec-titulo">Ejercicios Contables (Períodos)</div>
        <div class="sec-desc">
            Un ejercicio contable es un período fiscal (generalmente mensual) durante el cual se registran
            los movimientos contables. El sistema requiere tener al menos un período <strong>Abierto</strong> para
            poder crear asientos. Cuando se cierra un período, sus asientos quedan bloqueados.
        </div>

        <h3>4.1 Crear un período contable</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Contabilidad → Ejercicios</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Hacer clic en <strong>+ Nuevo Período</strong> e ingresar año y mes (ej. 2026 / Junio).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El período se crea en estado <span class="badge b-verde">Abierto</span> y ya puede recibir asientos.</div>
            </div>
        </div>

        <h3>4.2 Cerrar un período mensual</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Verificar que todos los movimientos del mes estén registrados (ventas, compras, pagos, nómina).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">En la fila del período, hacer clic en el botón <strong>Cerrar período</strong> (ícono de candado).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">El período cambia a estado <span class="badge b-rojo">Cerrado</span>. Ningún asiento puede modificarse dentro de él.</div>
            </div>
        </div>

        <div class="alerta"><p><strong>¡No hay marcha atrás automática!</strong> Al cerrar un período, los asientos quedan protegidos. Si necesita reabrir un período debe comunicarse con el Super Administrador del sistema.</p></div>

        <h3>4.3 Cierre Fiscal Anual</h3>
        <div class="sec-desc">
            El Cierre Fiscal Anual es un proceso especial ejecutado <strong>una sola vez al año</strong> por el Super Administrador,
            generalmente en enero del siguiente período. Requiere que los 12 períodos mensuales del año estén cerrados.
        </div>

        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Cerrar todos los 12 períodos mensuales del año a liquidar.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Hacer clic en el botón morado <strong>Cierre Fiscal Anual</strong> (visible solo para Super Administrador).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Seleccionar el <strong>año a cerrar</strong> y escribir el <strong>motivo</strong> (mínimo 10 caracteres).</div>
            </div>
            <div class="paso"><div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Confirmar. El sistema registra la operación en el log de auditoría y genera el asiento de cierre.</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Seguridad del Cierre Fiscal</div>
            <p>El sistema verifica automáticamente que: (1) todos los períodos del año estén cerrados, (2) el año no haya sido cerrado fiscalmente antes. Ambas condiciones deben cumplirse para que el proceso proceda. El cierre queda registrado permanentemente en el log de cambios críticos.</p>
        </div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Sección 5</div>
        <div class="sec-titulo">Parámetros Contables</div>
        <div class="sec-desc">
            Los parámetros contables definen <strong>qué cuenta contable se usa automáticamente</strong> en cada tipo de operación.
            Si un parámetro no está configurado, la operación se guarda pero no genera asiento automático.
        </div>

        <h3>5.1 Configurar parámetros</h3>
        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Ir a <strong>Contabilidad → Parámetros</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Para cada parámetro vacío, hacer clic en el ícono de edición y seleccionar la cuenta del plan de cuentas.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Opcionalmente: usar el botón <strong>Autoconfigurar</strong> (busca cuentas por código y las asigna automáticamente).</div>
            </div>
        </div>

        <div class="col2">
            <div class="col2-l">
                <h3>Grupo Ventas</h3>
                <table>
                    <thead><tr><th>Parámetro</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td>cta_caja_general</td><td>Caja para cobros en efectivo</td></tr>
                        <tr><td>cta_bancos_locales</td><td>Banco para transferencias</td></tr>
                        <tr><td>cta_vouchers</td><td>Dinero electrónico / Datafast</td></tr>
                        <tr><td>cta_clientes_locales</td><td>CxC a crédito</td></tr>
                        <tr><td>cta_ventas_locales</td><td>Ingresos por ventas</td></tr>
                        <tr><td>cta_iva_ventas</td><td>IVA en ventas por pagar</td></tr>
                        <tr><td>cta_anticipos_clientes</td><td>Reservas de clientes</td></tr>
                        <tr><td>cta_costo_ventas</td><td>Costo de ventas</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="col2-r">
                <h3>Grupo Compras</h3>
                <table>
                    <thead><tr><th>Parámetro</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td>cta_proveedores_locales</td><td>CxP proveedores</td></tr>
                        <tr><td>cta_iva_compras</td><td>Crédito tributario IVA</td></tr>
                        <tr><td>cta_retencion_ir</td><td>Ret. fuente IR por pagar</td></tr>
                        <tr><td>cta_retencion_iva</td><td>Ret. IVA por pagar</td></tr>
                        <tr><td>cta_gasto_compras_default</td><td>Gasto genérico (fallback compras sin producto)</td></tr>
                        <tr><td>cta_anticipos_proveedores</td><td>Anticipos a proveedores</td></tr>
                        <tr><td>cta_inventario_mercaderia</td><td>Inventario de mercadería</td></tr>
                        <tr><td>cta_ajuste_inventario</td><td>Ajustes / mermas</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="col2">
            <div class="col2-l">
                <h3>Grupo Gastos Operativos</h3>
                <table>
                    <thead><tr><th>Parámetro</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td>cta_gasto_compras_default</td><td>Gasto genérico (fallback)</td></tr>
                        <tr><td>cta_gasto_servicios</td><td>Honorarios profesionales</td></tr>
                        <tr><td>cta_gasto_arrendamiento</td><td>Arrendamientos de locales</td></tr>
                        <tr><td>cta_gasto_servicios_basicos</td><td>Agua, Luz, Internet</td></tr>
                        <tr><td>cta_gasto_publicidad</td><td>Publicidad y marketing</td></tr>
                    </tbody>
                </table>
                <div class="nota"><p><strong>Automático:</strong> En compras sin producto de inventario el sistema usa <strong>cta_gasto_compras_default</strong> como cuenta de gasto.</p></div>
            </div>
            <div class="col2-r">
                <h3>Grupo Bancos</h3>
                <table>
                    <thead><tr><th>Parámetro</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td>cta_comisiones_bancarias</td><td>Comisiones Datafast</td></tr>
                        <tr><td>cta_retencion_iva_cobrada</td><td>Crédito ret. IVA</td></tr>
                        <tr><td>cta_retencion_ir_cobrada</td><td>Crédito ret. IR</td></tr>
                    </tbody>
                </table>
                <h3>Grupo Nómina</h3>
                <table>
                    <thead><tr><th>Parámetro</th><th>Descripción</th></tr></thead>
                    <tbody>
                        <tr><td>cta_sueldos_salarios</td><td>Sueldos y salarios</td></tr>
                        <tr><td>cta_aporte_patronal</td><td>Aporte patronal 11.15%</td></tr>
                        <tr><td>cta_iess_por_pagar</td><td>Obligaciones IESS</td></tr>
                        <tr><td>cta_nomina_por_pagar</td><td>Nómina por pagar</td></tr>
                        <tr><td>cta_anticipos_empleados</td><td>Préstamos empleados</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 4 Ejercicios Contables &nbsp;·&nbsp; § 5 Parámetros Contables &nbsp;·&nbsp; Módulo Contabilidad</div>
        <div class="f-r">Pág. 3 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 4 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Contabilidad</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v2.0</div>
        </div>
    </div>

    <div class="sec">
        <div class="sec-num">Sección 6</div>
        <div class="sec-titulo">Reportes Contables</div>
        <div class="sec-desc">
            Los reportes contables se generan desde <strong>Contabilidad → Reportes</strong>. Todos se abren como PDF
            en un modal dentro de la misma pantalla. Se puede imprimir o abrir en nueva pestaña sin salir de la aplicación.
        </div>

        <h3>6.1 Libro Diario</h3>
        <div class="sec-desc">
            Lista todos los asientos contables del período seleccionado en orden cronológico, con el detalle de cada partida (cuenta, debe, haber, descripción). Es el reporte base de la contabilidad.
        </div>

        <div class="pasos">
            <div class="paso"><div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Seleccionar <strong>Fecha desde</strong> y <strong>Fecha hasta</strong>.</div>
            </div>
            <div class="paso"><div class="paso-num"><span>2</span></div>
                <div class="paso-cont">Hacer clic en <strong>Generar Libro Diario</strong>. El PDF se abre en modal.</div>
            </div>
        </div>

        <h3>6.2 Mayor General (por cuenta)</h3>
        <div class="sec-desc">
            Muestra todos los movimientos de una cuenta específica en un período: saldo inicial, débitos, créditos y saldo final. Equivale al "libro mayor" de la contabilidad tradicional.
        </div>

        <h3>6.3 Balance de Comprobación</h3>
        <div class="sec-desc">
            Lista todas las cuentas con movimientos en el período, con sus sumas de débitos, créditos y saldo. Permite verificar que la contabilidad esté cuadrada. Se genera por fecha de corte.
        </div>

        <h3>6.4 Balance General</h3>
        <div class="sec-desc">
            Reporte de situación financiera a una fecha determinada. Muestra: <strong>Activo = Pasivo + Patrimonio</strong>.
            Incluye las clases 1 (Activo), 2 (Pasivo) y 3 (Patrimonio).
        </div>

        <h3>6.5 Estado de Resultados</h3>
        <div class="sec-desc">
            Muestra la rentabilidad del período: <strong>Ingresos − Costos − Gastos = Utilidad/Pérdida</strong>.
            Incluye las clases 4 (Ingresos) y 5 (Gastos y Costos).
        </div>

        <table>
            <thead>
                <tr>
                    <th>Reporte</th>
                    <th>Parámetros de entrada</th>
                    <th>Formatos</th>
                    <th>Uso típico</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Libro Diario</strong></td>
                    <td>Fecha desde · Fecha hasta</td>
                    <td><span class="badge b-rojo">PDF</span></td>
                    <td>Revisión diaria / mensual</td>
                </tr>
                <tr>
                    <td><strong>Mayor por cuenta</strong></td>
                    <td>Cuenta · Fecha desde · Fecha hasta</td>
                    <td><span class="badge b-rojo">PDF</span></td>
                    <td>Auditoría de una cuenta</td>
                </tr>
                <tr>
                    <td><strong>Balance de Comprobación</strong></td>
                    <td>Fecha de corte</td>
                    <td><span class="badge b-rojo">PDF</span></td>
                    <td>Verificación mensual</td>
                </tr>
                <tr>
                    <td><strong>Balance General</strong></td>
                    <td>Fecha de corte</td>
                    <td><span class="badge b-rojo">PDF</span></td>
                    <td>Presentación financiera</td>
                </tr>
                <tr>
                    <td><strong>Estado de Resultados</strong></td>
                    <td>Fecha desde · Fecha hasta</td>
                    <td><span class="badge b-rojo">PDF</span></td>
                    <td>Rentabilidad del período</td>
                </tr>
            </tbody>
        </table>

        <div class="nota"><p>Todos los reportes se abren como <strong>PDF en modal</strong> dentro de la aplicación. El botón "Abrir en nueva pestaña" permite ver el PDF a pantalla completa sin cerrar el reporte.</p></div>
    </div>

    <hr class="sep">

    <div class="sec">
        <div class="sec-num">Referencia rápida</div>
        <div class="sec-titulo">Flujo contable mensual recomendado</div>

        <div class="diagrama">Inicio del mes
    │
    ├── 1. Verificar que el período del mes esté ABIERTO
    │       └── Si no existe: Contabilidad → Ejercicios → + Nuevo Período
    │
    ├── 2. Las operaciones del módulo generan asientos automáticamente
    │       ├── Ventas → asientos de cobro/crédito
    │       ├── Compras → asientos de gasto/CxP
    │       ├── Bancos → asientos Datafast / cierre caja
    │       └── RRHH → asiento de nómina
    │
    ├── 3. Crear asientos manuales adicionales si se requieren
    │       └── Contabilidad → Asientos → + Nuevo Asiento
    │
    ├── 4. Revisar Balance de Comprobación
    │       └── Contabilidad → Reportes → Balance de Comprobación
    │
    ├── 5. Cerrar el período mensual
    │       └── Contabilidad → Ejercicios → Cerrar período
    │
    └── Fin del año fiscal: Cierre Fiscal Anual (Solo Super Admin)</div>

        <div class="box">
            <div class="box-titulo">Acceso requerido</div>
            <p>El módulo Contabilidad requiere el permiso <strong>"Contabilidad - Ver"</strong> para leer y <strong>"Contabilidad - Crear"</strong> para ingresar asientos. Solo el perfil <strong>Super Administrador</strong> puede ejecutar el Cierre Fiscal Anual y reabrir períodos cerrados.</p>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">§ 6 Reportes &nbsp;·&nbsp; Referencia Rápida &nbsp;·&nbsp; Módulo Contabilidad · v2.0</div>
        <div class="f-r">Pág. 4 de 4 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}</div>
    </div>
</div>

</body>
</html>
