<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:9px;
               color:#1A1A2E;background:#fff; }

        /* ── Portada ───────────────────────────────────────── */
        .portada { page-break-after:always;padding:60px 50px;
                   text-align:center;background:#1F2D3D;color:#fff;min-height:297mm; }
        .portada-logo { font-size:28px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#F59E0B; }
        .portada-sub  { font-size:11px;color:#CBD5E0;margin-bottom:60px; }
        .portada-divider { width:60px;height:3px;background:#F59E0B;
                           margin:0 auto 50px; }
        .portada-titulo { font-size:22px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:14px;color:#F59E0B;margin-bottom:8px; }
        .portada-ver    { font-size:10px;color:#94A3B8;margin-bottom:70px; }
        .portada-meta   { font-size:9px;color:#94A3B8;line-height:1.8; }
        .portada-meta strong { color:#CBD5E0; }

        /* ── Layout general ─────────────────────────────────── */
        .page { padding:22px 26px 18px; }

        .header-page { display:table;width:100%;margin-bottom:12px;
                       padding-bottom:8px;border-bottom:2px solid #1F2D3D; }
        .hp-left  { display:table-cell;vertical-align:middle;width:65%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:35%; }
        .hp-empresa { font-size:10px;font-weight:bold;color:#1F2D3D; }
        .hp-titulo  { font-size:7px;color:#555770;margin-top:1px; }
        .hp-pag     { font-size:7px;color:#555770; }

        /* ── Secciones ──────────────────────────────────────── */
        .sec { margin-bottom:16px; }
        .sec-num { display:inline-block;background:#1F2D3D;color:#F59E0B;
                   font-size:7px;font-weight:bold;padding:2px 6px;
                   border-radius:3px;margin-bottom:5px;letter-spacing:0.5px;
                   text-transform:uppercase; }
        .sec-titulo { font-size:13px;font-weight:bold;color:#1F2D3D;
                      margin-bottom:2px; }
        .sec-desc { font-size:8.5px;color:#444;line-height:1.6;
                    margin-bottom:8px; }

        h3 { font-size:9.5px;font-weight:bold;color:#1F2D3D;
             margin-bottom:4px;margin-top:10px; }

        /* ── Tablas ─────────────────────────────────────────── */
        table { width:100%;border-collapse:collapse;margin-bottom:8px; }
        thead tr { background:#1F2D3D; }
        thead th { padding:5px 7px;text-align:left;font-size:7.5px;
                   font-weight:bold;color:#fff;text-transform:uppercase;
                   letter-spacing:0.3px; }
        thead th.c { text-align:center; }
        tbody td { padding:4.5px 7px;border-bottom:1px solid #E2E8F0;
                   font-size:8px;color:#1A1A2E;vertical-align:top; }
        tbody tr:nth-child(even) { background:#F8FAFC; }
        tbody td strong { color:#1F2D3D; }

        /* ── Cajas informativas ──────────────────────────────── */
        .box { border-left:3px solid #F59E0B;background:#FFFBEB;
               padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .box-titulo { font-size:7.5px;font-weight:bold;color:#92400E;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:3px; }
        .box p { font-size:8px;color:#78350F;line-height:1.6;margin:0; }

        .nota { border-left:3px solid #3B82F6;background:#EFF6FF;
                padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .nota p { font-size:8px;color:#1E40AF;line-height:1.6;margin:0; }

        .alerta { border-left:3px solid #EF4444;background:#FEF2F2;
                  padding:7px 10px;margin-bottom:8px;border-radius:0 4px 4px 0; }
        .alerta p { font-size:8px;color:#991B1B;line-height:1.6;margin:0; }

        .rel { border:1px solid #E2E8F0;background:#F8FAFC;
               padding:7px 10px;margin-bottom:8px;border-radius:4px; }
        .rel-titulo { font-size:7.5px;font-weight:bold;color:#475569;
                      text-transform:uppercase;letter-spacing:0.3px;margin-bottom:4px; }
        .rel p { font-size:8px;color:#475569;line-height:1.7;margin:0; }

        /* ── Flujo paso a paso ───────────────────────────────── */
        .pasos { margin-bottom:8px; }
        .paso { display:table;width:100%;margin-bottom:4px; }
        .paso-num  { display:table-cell;width:20px;vertical-align:top; }
        .paso-num span { display:inline-block;background:#F59E0B;color:#fff;
                         font-size:7.5px;font-weight:bold;width:16px;height:16px;
                         border-radius:50%;text-align:center;line-height:16px; }
        .paso-cont { display:table-cell;vertical-align:top;
                     font-size:8px;color:#444;line-height:1.6;
                     padding-left:4px; }
        .paso-cont strong { color:#1F2D3D; }

        /* ── Badges tipo ─────────────────────────────────────── */
        .badge { display:inline-block;padding:1px 6px;border-radius:10px;
                 font-size:7px;font-weight:bold;margin:1px; }
        .b-azul   { background:#DBEAFE;color:#1E40AF; }
        .b-verde  { background:#DCFCE7;color:#166534; }
        .b-rojo   { background:#FEE2E2;color:#991B1B; }
        .b-gris   { background:#F1F5F9;color:#475569; }
        .b-dorado { background:#FEF3C7;color:#92400E; }

        /* ── Diagrama relaciones ─────────────────────────────── */
        .diagrama { font-family:monospace;font-size:7.5px;color:#475569;
                    background:#F8FAFC;border:1px solid #E2E8F0;
                    padding:8px 10px;border-radius:4px;margin-bottom:8px;
                    line-height:1.8;white-space:pre; }

        /* ── Footer ──────────────────────────────────────────── */
        .footer { margin-top:10px;padding-top:7px;
                  border-top:1px solid #E2E8F0;display:table;width:100%; }
        .f-l { display:table-cell;font-size:6.5px;color:#94A3B8; }
        .f-r { display:table-cell;text-align:right;font-size:6.5px;color:#94A3B8; }

        /* ── Separadores ─────────────────────────────────────── */
        .sep { border:none;border-top:1px dashed #CBD5E0;margin:12px 0; }
        .pb { page-break-before:always; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════ PORTADA ═══════════════════════════════ --}}
<div class="portada">
    <div class="portada-logo">ALTAMIRA</div>
    <div class="portada-sub">Light &amp; Sound · ERP Sistema</div>
    <div class="portada-divider"></div>
    <div class="portada-titulo">Manual de Uso</div>
    <div class="portada-modulo">Módulo Bancos</div>
    <div class="portada-ver">
        Versión 1.0 &nbsp;·&nbsp; {{ now()->format('d/m/Y') }}
    </div>
    <div class="portada-meta">
        <strong>Empresa:</strong> {{ $empresa->nombre_comercial ?? 'Altamira' }}<br>
        <strong>Usuario:</strong> {{ $usuario->nombre ?? $usuario->email }}<br>
        <strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 1 ══════════════════════════════ --}}
<div class="page">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Bancos</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    {{-- ── SECCIÓN 1 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 1</div>
        <div class="sec-titulo">¿Para qué sirve el módulo Bancos?</div>
        <div class="sec-desc">
            El módulo Bancos es el <strong>control total del dinero de la empresa</strong>. Registra cada centavo
            que entra y sale de todas las cuentas bancarias, cajas y fondos de la empresa, y lo conecta
            automáticamente con la contabilidad. Cada movimiento que registras aquí genera un asiento
            contable sin que tengas que hacerlo tú.
        </div>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona este módulo?</div>
            <div class="rel">
                <table>
                    <thead>
                        <tr>
                            <th>Módulo</th>
                            <th>Qué hace la conexión</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>Compras / CxP</strong></td>
                            <td>Cuando pagas una factura de proveedor, el banco descuenta el dinero automáticamente</td>
                        </tr>
                        <tr>
                            <td><strong>Contabilidad</strong></td>
                            <td>Cada movimiento genera un asiento automático en el libro diario</td>
                        </tr>
                        <tr>
                            <td><strong>RRHH</strong></td>
                            <td>Los pagos de nómina se registran como egresos bancarios</td>
                        </tr>
                        <tr>
                            <td><strong>Datafast</strong></td>
                            <td>Las ventas con tarjeta pasan por cuenta puente hasta que el banco deposita</td>
                        </tr>
                        <tr>
                            <td><strong>Ventas (futuro)</strong></td>
                            <td>Los cobros de clientes serán ingresos bancarios automáticos</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <hr class="sep">

    {{-- ── SECCIÓN 2 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 2</div>
        <div class="sec-titulo">Bancos y Cajas (catálogo)</div>
        <div class="sec-desc">
            Es el catálogo de todas las cuentas de dinero de la empresa. Antes de registrar cualquier
            movimiento, la cuenta debe existir aquí. El sistema asigna automáticamente la cuenta contable
            correcta según el tipo.
        </div>

        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Cuándo usar</th>
                    <th>Cuenta contable automática</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge b-azul">Banco</span></td>
                    <td>Cuentas bancarias reales (Pichincha, Pacífico, Guayaquil…)</td>
                    <td>1.1.1.3 Bancos Locales</td>
                </tr>
                <tr>
                    <td><span class="badge b-verde">Caja</span></td>
                    <td>Efectivo en mostrador o local</td>
                    <td>1.1.1.1 Caja General</td>
                </tr>
                <tr>
                    <td><span class="badge b-dorado">Caja Chica</span></td>
                    <td>Fondo fijo para gastos menores</td>
                    <td>1.1.1.2 Cajas Chicas y Fondos</td>
                </tr>
                <tr>
                    <td><span class="badge b-gris">Tarjeta</span></td>
                    <td>Datáfono, vouchers por cobrar</td>
                    <td>1.1.1.5 Dinero Electrónico</td>
                </tr>
            </tbody>
        </table>

        <div class="alerta">
            <p><strong>Regla importante:</strong> No elimines una cuenta que ya tiene movimientos.
            Si ya no se usa, cámbiala a estado <strong>Inactivo</strong> en lugar de eliminarla.
            El sistema bloqueará la eliminación si hay movimientos registrados.</p>
        </div>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona?</div>
            <div class="rel"><p>Con Movimientos (origen de cada egreso e ingreso) · Con Datafast (cuenta puente tarjetas) · Con Conciliación (se concilia cuenta por cuenta)</p></div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira Light &amp; Sound · Módulo Bancos · Manual de Uso</div>
        <div class="f-r">{{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 2 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Bancos</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    {{-- ── SECCIÓN 3 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 3</div>
        <div class="sec-titulo">Movimientos Bancarios</div>
        <div class="sec-desc">
            Registra manualmente cualquier entrada o salida de dinero que no venga de otro módulo
            (compras, nómina, ventas). Por ejemplo: pago de servicios básicos, depósitos de socios,
            gastos varios. <strong>Al guardar, el saldo de la cuenta se actualiza y se genera el asiento contable.</strong>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Sub-tipo</th>
                    <th>Cuándo usar</th>
                    <th>Campos adicionales</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge b-azul">Transferencia</span></td>
                    <td>Movimiento electrónico entre cuentas</td>
                    <td>N° documento (referencia bancaria)</td>
                </tr>
                <tr>
                    <td><span class="badge b-dorado">Cheque</span></td>
                    <td>Pago con cheque físico</td>
                    <td>N° cheque y fecha de cobro del cheque</td>
                </tr>
                <tr>
                    <td><span class="badge b-verde">Efectivo</span></td>
                    <td>Ingreso o egreso en efectivo</td>
                    <td>Solo beneficiario y descripción</td>
                </tr>
                <tr>
                    <td><span class="badge b-gris">Depósito</span></td>
                    <td>Depósito bancario por ventanilla</td>
                    <td>N° comprobante de depósito</td>
                </tr>
            </tbody>
        </table>

        <h3>¿Qué pasa automáticamente al guardar?</h3>
        <div class="pasos">
            <div class="paso">
                <div class="paso-num"><span>1</span></div>
                <div class="paso-cont"><strong>Saldo actualizado:</strong> El saldo de la cuenta sube (ingreso) o baja (egreso) en tiempo real</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>2</span></div>
                <div class="paso-cont"><strong>Asiento automático:</strong> Se genera el asiento contable (DEBE/HABER) según el tipo de movimiento</div>
            </div>
        </div>

        <div class="alerta">
            <p><strong>Candado de saldo insuficiente:</strong> Si intentas registrar un egreso mayor al saldo disponible,
            el sistema te bloqueará con un error. Esto previene saldos negativos. Solo el Super Administrador
            puede anular movimientos ya registrados.</p>
        </div>

        <div class="nota">
            <p><strong>Asiento ingreso:</strong> DEBE → cuenta banco / HABER → cuenta contrapartida<br>
            <strong>Asiento egreso:</strong> DEBE → cuenta contrapartida / HABER → cuenta banco</p>
        </div>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona?</div>
            <div class="rel"><p>Con BancoCaja (actualiza saldo_actual) · Con Contabilidad (asiento automático) · Con Conciliación (estos movimientos son los que se concilian) · Con CxP cuando se usa para pagos a proveedores</p></div>
        </div>
    </div>

    <hr class="sep">

    {{-- ── SECCIÓN 4 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 4</div>
        <div class="sec-titulo">Cajas (apertura y cierre diario)</div>
        <div class="sec-desc">
            Controla el efectivo físico en los locales. Cada día se abre una caja al inicio del turno
            y se cierra al final. El sistema calcula la diferencia entre lo esperado y lo declarado.
        </div>

        <div class="pasos">
            <div class="paso">
                <div class="paso-num"><span>1</span></div>
                <div class="paso-cont"><strong>Al inicio del turno:</strong> Click en "+" → Abrir Caja. El sistema registra quién abrió, a qué hora y cuánto efectivo había (monto inicial)</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>2</span></div>
                <div class="paso-cont"><strong>Durante el día:</strong> Las ventas en efectivo se acumulan en el sistema (cuando esté conectado el módulo Ventas)</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>3</span></div>
                <div class="paso-cont"><strong>Al final del turno:</strong> Click "Cerrar Caja". Declaras el efectivo contado, tarjetas, cheques y transferencias. El sistema compara vs. lo facturado</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>4</span></div>
                <div class="paso-cont"><strong>Diferencia:</strong> Si no cuadra, aparece una alerta en rojo. El cajero debe explicar la diferencia en Observaciones antes de cerrar</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Nota importante</div>
            <p>Una caja abierta no puede volver a abrirse hasta que se cierre. Si el sistema muestra
            diferencia, revisa los tickets de venta del día y cuenta el efectivo físico nuevamente
            antes de cerrar.</p>
        </div>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona?</div>
            <div class="rel"><p>Con BancoCaja (apertura usa el saldo de la caja) · Con Ventas (las ventas en efectivo se comparan al cierre) · Con Contabilidad (el cierre genera un asiento)</p></div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira Light &amp; Sound · Módulo Bancos · Manual de Uso</div>
        <div class="f-r">{{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 3 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Bancos</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    {{-- ── SECCIÓN 5 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 5</div>
        <div class="sec-titulo">Datafast (Tarjetas de Crédito/Débito)</div>
        <div class="sec-desc">
            Controla los pagos recibidos con datáfono. El proceso tiene dos pasos porque el banco
            no deposita el dinero el mismo día que se cobra.
        </div>

        <h3>Paso 1 — Lotes (cierre diario del datáfono)</h3>
        <div class="sec-desc">
            Al final del día, el datáfono imprime un "lote" con el total de ventas con tarjeta.
            Ese número se registra en el sistema. El dinero va a la <strong>cuenta puente 1.1.1.5 Dinero Electrónico</strong>
            (todavía no está en el banco físicamente).
        </div>

        <h3>Paso 2 — Liquidaciones (cuando el banco deposita, 24-48h después)</h3>
        <div class="sec-desc">
            El banco deposita el dinero pero descuenta su comisión y retenciones. Aquí confirmas
            cuánto depositó realmente. El sistema calcula la comisión automáticamente.
        </div>

        <div class="nota">
            <p><strong>Fórmula de liquidación:</strong><br>
            Valor depositado (neto) = Valor bruto − Comisión Datafast − Retención IVA − Retención IR<br><br>
            <strong>Ejemplo:</strong> Bruto $1,000 − Comisión $20 − Ret. IVA $15 − Ret. IR $5 = <strong>Neto $960</strong></p>
        </div>

        <h3>¿Qué asientos genera automáticamente?</h3>
        <table>
            <thead>
                <tr><th>Momento</th><th>DEBE</th><th>HABER</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td>Al crear el lote</td>
                    <td>1.1.1.5 Dinero Electrónico</td>
                    <td>4.1.x Ventas del día</td>
                </tr>
                <tr>
                    <td rowspan="4">Al liquidar</td>
                    <td>1.1.1.3 Banco (valor neto)</td>
                    <td rowspan="4">1.1.1.5 Dinero Electrónico (total bruto)</td>
                </tr>
                <tr>
                    <td>5.3.x Comisiones Bancarias</td>
                </tr>
                <tr>
                    <td>1.1.5.x Crédito Trib. Ret. IVA</td>
                </tr>
                <tr>
                    <td>1.1.5.x Crédito Trib. Ret. IR</td>
                </tr>
            </tbody>
        </table>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona?</div>
            <div class="rel"><p>Con Contabilidad (genera dos asientos: apertura y liquidación) · Con Bancos (el dinero líquido llega a la cuenta bancaria seleccionada) · Con SRI (las retenciones quedan registradas para declaraciones)</p></div>
        </div>
    </div>

    <hr class="sep">

    {{-- ── SECCIÓN 6 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 6</div>
        <div class="sec-titulo">Conciliación Bancaria</div>
        <div class="sec-desc">
            Es el proceso de comparar lo que el banco dice que tienes con lo que el sistema tiene
            registrado. Se hace al menos <strong>una vez al mes</strong> para detectar errores,
            depósitos no registrados, o fraudes.
        </div>

        <div class="pasos">
            <div class="paso">
                <div class="paso-num"><span>1</span></div>
                <div class="paso-cont">Descarga el <strong>estado de cuenta</strong> de tu banco (Pichincha, Pacífico, etc.) en formato CSV o Excel</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>2</span></div>
                <div class="paso-cont">En el sistema: <strong>Nueva Conciliación</strong> → elige el banco → ingresa el saldo del estado de cuenta</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>3</span></div>
                <div class="paso-cont">Sube el archivo CSV del banco con <strong>"Cargar CSV banco"</strong>. El sistema detecta columnas automáticamente</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>4</span></div>
                <div class="paso-cont">Selecciona una partida del sistema (panel izquierdo) y su correspondiente en el banco (panel derecho), luego click <strong>"Cruzar partidas"</strong></div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>5</span></div>
                <div class="paso-cont">Si queda diferencia: usa <strong>"Asiento Ajuste"</strong> para registrar el ajuste contable</div>
            </div>
            <div class="paso">
                <div class="paso-num"><span>6</span></div>
                <div class="paso-cont">Cuando todo cuadre: <strong>"Cerrar Conciliación"</strong>. Queda bloqueada para edición</div>
            </div>
        </div>

        <div class="box">
            <div class="box-titulo">Partidas en tránsito</div>
            <p>Son movimientos que existen en el banco pero no en el sistema (o viceversa).
            Causas comunes: cheques emitidos no cobrados aún, depósitos en tránsito,
            comisiones bancarias no registradas.</p>
        </div>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona?</div>
            <div class="rel"><p>Con Movimientos (cruza contra movimientos del sistema) · Con Contabilidad (el asiento de ajuste va al libro diario) · Con Cheques (cheques emitidos no cobrados son partidas en tránsito normales)</p></div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira Light &amp; Sound · Módulo Bancos · Manual de Uso</div>
        <div class="f-r">{{ now()->format('d/m/Y') }}</div>
    </div>
</div>

{{-- ═══════════════════════════════ PÁGINA 4 ══════════════════════════════ --}}
<div class="page pb">

    <div class="header-page">
        <div class="hp-left">
            <div class="hp-empresa">{{ $empresa->nombre_comercial ?? 'Altamira Light & Sound' }}</div>
            <div class="hp-titulo">Manual de Uso · Módulo Bancos</div>
        </div>
        <div class="hp-right">
            <div class="hp-pag">{{ now()->format('d/m/Y') }} &nbsp;·&nbsp; v1.0</div>
        </div>
    </div>

    {{-- ── SECCIÓN 7 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 7</div>
        <div class="sec-titulo">Cheques</div>
        <div class="sec-desc">
            Controla los cheques que la empresa emite como forma de pago. Al registrar un cheque,
            el sistema descuenta el monto del saldo bancario inmediatamente.
        </div>

        <h3>Ciclo de vida de un cheque</h3>
        <table>
            <thead>
                <tr><th>Estado</th><th>Qué significa</th><th>Qué hace el sistema</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><span class="badge b-dorado">Emitido</span></td>
                    <td>Cheque registrado y entregado al beneficiario</td>
                    <td>Descuenta el saldo del banco</td>
                </tr>
                <tr>
                    <td><span class="badge b-verde">Cobrado</span></td>
                    <td>El beneficiario lo cobró en el banco</td>
                    <td>Marca como cobrado, registra fecha</td>
                </tr>
                <tr>
                    <td><span class="badge b-rojo">Protestado</span></td>
                    <td>El banco lo rechazó (fondos insuficientes)</td>
                    <td>Revierte el descuento del saldo bancario</td>
                </tr>
                <tr>
                    <td><span class="badge b-gris">Anulado</span></td>
                    <td>Se canceló antes de entregar o cobrar</td>
                    <td>Revierte el descuento del saldo bancario</td>
                </tr>
            </tbody>
        </table>

        <div class="alerta">
            <p><strong>Cheque protestado:</strong> Si el banco rechaza el cheque, el sistema revierte
            automáticamente el descuento del saldo. Debes resolver la situación con el proveedor y
            re-emitir el pago. El sistema registra el evento en el log de cambios críticos.</p>
        </div>

        <div class="rel">
            <div class="rel-titulo">¿Con qué se relaciona?</div>
            <div class="rel"><p>Con Movimientos (cada cheque genera un movimiento egreso) · Con CxP (los pagos a proveedores con cheque cancelan cuentas por pagar) · Con Conciliación (cheques emitidos no cobrados son partidas en tránsito)</p></div>
        </div>
    </div>

    <hr class="sep">

    {{-- ── SECCIÓN 8 ────────────────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 8</div>
        <div class="sec-titulo">Reportes del Módulo</div>

        <table>
            <thead>
                <tr><th>Reporte</th><th>Qué muestra</th><th>Cuándo usarlo</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Estado de Cuenta</strong></td>
                    <td>Historial de una cuenta con saldo acumulado fila por fila</td>
                    <td>Verificar saldo de una cuenta en un período</td>
                </tr>
                <tr>
                    <td><strong>Movimientos</strong></td>
                    <td>Lista filtrable de todos los movimientos por banco, tipo y fechas</td>
                    <td>Reportes de gestión, auditorías, revisión de pagos</td>
                </tr>
                <tr>
                    <td><strong>Reporte de Caja</strong></td>
                    <td>Historial de cierres de caja con diferencias</td>
                    <td>Rendir cuentas del fondo fijo, solicitar reposición</td>
                </tr>
                <tr>
                    <td><strong>Consulta Cobros/Pagos</strong></td>
                    <td>Búsqueda avanzada con 6 filtros simultáneos + exportar</td>
                    <td>Encontrar un pago específico, verificar si se pagó una factura</td>
                </tr>
            </tbody>
        </table>

        <div class="box">
            <div class="box-titulo">Exportar reportes</div>
            <p>Los reportes de Movimientos y Consulta Avanzada se pueden exportar en <strong>Excel</strong> (para análisis en planilla)
            o en <strong>PDF</strong> (para enviar por correo o imprimir). El PDF se abre directamente en la pantalla sin
            descargar ningún archivo.</p>
        </div>
    </div>

    <hr class="sep">

    {{-- ── SECCIÓN 9 — DIAGRAMA ──────────────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 9</div>
        <div class="sec-titulo">Relaciones entre módulos</div>

        <div class="diagrama">COMPRAS                    BANCOS                     CONTABILIDAD
──────────                 ──────                     ────────────
Factura / CxP  ──pago──►  Movimiento egreso    ──►   Asiento automático
                           Saldo banco baja           DEBE: 2.1.1.1 Proveedores
                                                      HABER: 1.1.1.3 Bancos

NÓMINA                     BANCOS                     CONTABILIDAD
──────                     ──────                     ────────────
Rol procesado  ──pago──►   Movimiento egreso    ──►   Asiento automático
                           Saldo banco baja           DEBE: 2.1.4.1 Nómina por pagar
                                                      HABER: 1.1.1.3 Bancos

DATAFAST                   BANCOS                     CONTABILIDAD
────────                   ──────                     ────────────
Lote datáfono  ──────────► 1.1.1.5 cuenta puente ──► DEBE: 1.1.1.5 / HABER: Ventas
Liquidación    ──────────► 1.1.1.3 banco real     ──► DEBE: Banco+Comisión / HABER: 1.1.1.5</div>
    </div>

    <hr class="sep">

    {{-- ── SECCIÓN 10 — ERRORES FRECUENTES ────────────────────── --}}
    <div class="sec">
        <div class="sec-num">Sección 10</div>
        <div class="sec-titulo">Errores frecuentes y soluciones</div>

        <table>
            <thead>
                <tr><th style="width:28%">Error</th><th style="width:36%">Causa</th><th style="width:36%">Solución</th></tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>Saldo insuficiente</strong></td>
                    <td>El egreso supera el saldo disponible</td>
                    <td>Verifica el saldo actual antes de registrar. Si es correcto, confirma con el banco</td>
                </tr>
                <tr>
                    <td><strong>Diferencia en conciliación</strong></td>
                    <td>Movimiento en banco no registrado en sistema</td>
                    <td>Busca en el estado de cuenta del banco y registra el movimiento faltante en el sistema</td>
                </tr>
                <tr>
                    <td><strong>Cheque protestado</strong></td>
                    <td>Fondos insuficientes al momento del cobro</td>
                    <td>El saldo se revierte automáticamente. Contacta al proveedor para re-emitir el cheque</td>
                </tr>
                <tr>
                    <td><strong>Datafast sin liquidar más de 72h</strong></td>
                    <td>Se olvidó confirmar el depósito del banco</td>
                    <td>Revisa el estado de cuenta bancario y liquida el lote pendiente</td>
                </tr>
                <tr>
                    <td><strong>Caja con diferencia al cerrar</strong></td>
                    <td>Ventas en efectivo no coinciden con el efectivo físico</td>
                    <td>Revisar tickets de venta del día y contar el efectivo físico nuevamente</td>
                </tr>
                <tr>
                    <td><strong>CSV no importa en conciliación</strong></td>
                    <td>Formato no compatible o columnas no detectadas</td>
                    <td>Verificar que el archivo tiene columnas de Fecha y Monto. Guardar como CSV UTF-8 desde Excel</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <div class="f-l">Altamira Light &amp; Sound · Módulo Bancos · Manual de Uso &nbsp;·&nbsp; Versión 1.0</div>
        <div class="f-r">Generado: {{ now()->format('d/m/Y H:i') }}</div>
    </div>
</div>

</body>
</html>
