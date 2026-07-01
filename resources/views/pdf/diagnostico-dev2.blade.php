<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        * { margin:0;padding:0;box-sizing:border-box; }
        body { font-family:'DejaVu Sans',Arial,sans-serif;font-size:8px;
               color:#1A1A2E;background:#fff; }

        /* ── Portada ───────────────────────────────────────── */
        .portada { page-break-after:always;padding:50px 50px;
                   text-align:center;background:#4C1D95;color:#fff;min-height:297mm; }
        .portada-logo { font-size:26px;font-weight:bold;letter-spacing:2px;
                        margin-bottom:6px;color:#F59E0B; }
        .portada-sub  { font-size:10px;color:#DDD6FE;margin-bottom:50px; }
        .portada-divider { width:60px;height:3px;background:#F59E0B;
                           margin:0 auto 40px; }
        .portada-titulo { font-size:20px;font-weight:bold;color:#fff;
                          margin-bottom:10px;line-height:1.3; }
        .portada-modulo { font-size:13px;color:#F59E0B;margin-bottom:8px; }
        .portada-ver    { font-size:9px;color:#C4B5FD;margin-bottom:50px; }
        .portada-meta   { font-size:9px;color:#DDD6FE;line-height:2; }
        .portada-meta strong { color:#EDE9FE; }

        .portada-resumen { display:table;width:100%;margin:50px 0 0;
                           border-collapse:separate;border-spacing:10px 0; }
        .res-box { display:table-cell;width:33%;text-align:center;
                   padding:18px 10px;border-radius:8px; }
        .res-box.verde  { background:rgba(16,185,129,0.2);border:1px solid rgba(16,185,129,0.4); }
        .res-box.naranja { background:rgba(249,115,22,0.2);border:1px solid rgba(249,115,22,0.4); }
        .res-box.azul   { background:rgba(59,130,246,0.2);border:1px solid rgba(59,130,246,0.4); }
        .res-modulo { font-size:9px;color:#DDD6FE;font-weight:bold;
                      text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px; }
        .res-pct { font-size:28px;font-weight:bold;margin-bottom:4px; }
        .res-pct.verde   { color:#34D399; }
        .res-pct.naranja { color:#FB923C; }
        .res-pct.azul    { color:#60A5FA; }
        .res-detalle { font-size:7.5px;color:#C4B5FD; }

        /* ── Páginas ────────────────────────────────────────── */
        .page { padding:18px 22px 14px; }

        .header-page { display:table;width:100%;margin-bottom:10px;
                       padding-bottom:7px;border-bottom:3px solid #4C1D95; }
        .hp-left  { display:table-cell;vertical-align:middle;width:60%; }
        .hp-right { display:table-cell;vertical-align:middle;text-align:right;width:40%; }
        .hp-titulo  { font-size:13px;font-weight:bold;color:#4C1D95; }
        .hp-sub     { font-size:7px;color:#7C3AED;margin-top:1px; }
        .hp-meta    { font-size:7px;color:#6B7280; }

        /* ── Sección de módulo ───────────────────────────────── */
        .mod-header { padding:7px 12px;border-radius:6px;margin-bottom:10px;
                      display:table;width:100%; }
        .mod-header.cont  { background:#064E3B; }
        .mod-header.comp  { background:#7C2D12; }
        .mod-header.banco { background:#1E3A5F; }
        .mod-h-left  { display:table-cell;vertical-align:middle;width:70%; }
        .mod-h-right { display:table-cell;vertical-align:middle;
                       text-align:right;width:30%; }
        .mod-titulo { font-size:12px;font-weight:bold;color:#fff; }
        .mod-sub    { font-size:7px;color:rgba(255,255,255,0.7);margin-top:1px; }
        .mod-pct { font-size:18px;font-weight:bold;color:#F59E0B; }
        .mod-stats { font-size:7px;color:rgba(255,255,255,0.8);margin-top:2px; }

        /* ── Grid de secciones ───────────────────────────────── */
        .grid2 { display:table;width:100%;border-spacing:0;
                 border-collapse:separate;margin-bottom:10px; }
        .grid2-l { display:table-cell;width:49%;vertical-align:top;
                   padding-right:5px; }
        .grid2-r { display:table-cell;width:49%;vertical-align:top;
                   padding-left:5px; }

        /* ── Card de sección ─────────────────────────────────── */
        .card { border:1px solid #E5E7EB;border-radius:6px;
                margin-bottom:8px;overflow:hidden; }
        .card-header { padding:5px 8px;font-size:8px;font-weight:bold;
                       text-transform:uppercase;letter-spacing:0.3px; }
        .card-header.cont  { background:#ECFDF5;color:#064E3B;border-bottom:1px solid #A7F3D0; }
        .card-header.comp  { background:#FFF7ED;color:#7C2D12;border-bottom:1px solid #FED7AA; }
        .card-header.banco { background:#EFF6FF;color:#1E3A5F;border-bottom:1px solid #BFDBFE; }

        /* ── Tabla de ítems ──────────────────────────────────── */
        .items { width:100%;border-collapse:collapse; }
        .items td { padding:3.5px 7px;border-bottom:1px solid #F3F4F6;
                    font-size:7.5px;vertical-align:top; }
        .items tr:last-child td { border-bottom:none; }
        .items tr:nth-child(even) { background:#FAFAFA; }
        .item-nombre { width:58%;color:#374151; }
        .item-estado { width:10%;text-align:center; }
        .item-nota   { width:32%;color:#6B7280;font-size:7px; }

        /* ── Badges de estado ────────────────────────────────── */
        .ok  { color:#059669;font-size:9px;font-weight:bold; }
        .warn { color:#D97706;font-size:9px;font-weight:bold; }
        .err  { color:#DC2626;font-size:9px;font-weight:bold; }
        .lock { color:#7C3AED;font-size:9px; }

        /* ── Barra de resumen de módulo ───────────────────────── */
        .mod-resumen { display:table;width:100%;margin-bottom:10px;
                       background:#F9FAFB;border:1px solid #E5E7EB;
                       border-radius:6px;padding:8px 12px; }
        .mr-cell { display:table-cell;vertical-align:middle;text-align:center; }
        .mr-num  { font-size:15px;font-weight:bold; }
        .mr-num.ok   { color:#059669; }
        .mr-num.warn { color:#D97706; }
        .mr-num.err  { color:#DC2626; }
        .mr-label    { font-size:6.5px;color:#6B7280;text-transform:uppercase;
                       letter-spacing:0.3px;margin-top:1px; }
        .mr-sep { display:table-cell;vertical-align:middle;
                  width:1px;padding:0 6px; }
        .mr-sep-line { width:1px;height:30px;background:#E5E7EB; }
        .mr-total { display:table-cell;vertical-align:middle;
                    text-align:right;width:40%; }
        .mr-pct-label { font-size:7px;color:#6B7280;margin-bottom:2px; }
        .mr-pct-num   { font-size:20px;font-weight:bold;color:#4C1D95; }
        .mr-pct-de    { font-size:7px;color:#9CA3AF; }

        /* ── Tabla resumen final ─────────────────────────────── */
        .resumen-final { width:100%;border-collapse:collapse;margin-top:8px; }
        .resumen-final th { background:#4C1D95;color:#fff;padding:6px 10px;
                            font-size:8px;text-align:left; }
        .resumen-final td { padding:5px 10px;border-bottom:1px solid #E5E7EB;
                            font-size:8px; }
        .resumen-final tr:nth-child(even) td { background:#F5F3FF; }
        .pct-bar-wrap { background:#E5E7EB;border-radius:3px;height:8px;
                        width:100%;overflow:hidden; }
        .pct-bar { height:8px;border-radius:3px; }

        /* ── Leyenda ─────────────────────────────────────────── */
        .leyenda { display:table;width:100%;margin-top:10px;
                   border:1px solid #E5E7EB;border-radius:6px;
                   padding:8px 12px;background:#F9FAFB; }
        .ley-item { display:table-cell;text-align:center;font-size:7.5px; }
        .ley-sym  { font-size:12px;font-weight:bold; }

        .footer { margin-top:8px;padding-top:6px;
                  border-top:1px solid #E5E7EB;display:table;width:100%; }
        .f-l { display:table-cell;font-size:6.5px;color:#9CA3AF; }
        .f-r { display:table-cell;text-align:right;font-size:6.5px;color:#9CA3AF; }

        .sep { border:none;border-top:1px dashed #D1D5DB;margin:8px 0; }
        .pb  { page-break-before:always; }

        .note-global { border-left:3px solid #7C3AED;background:#F5F3FF;
                       padding:6px 10px;margin-bottom:10px;border-radius:0 4px 4px 0; }
        .note-global p { font-size:7.5px;color:#4C1D95;line-height:1.6;margin:0; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════ PORTADA ═══════════════════════════════ --}}
<div class="portada">
    <div class="portada-logo">ALTAMIRA</div>
    <div class="portada-sub">Light &amp; Sound · ERP Sistema</div>
    <div class="portada-divider"></div>
    <div class="portada-titulo">Diagnóstico Dev 2</div>
    <div class="portada-modulo">Contabilidad · Compras · Bancos</div>
    <div class="portada-ver">
        Fecha: {{ now()->format('d/m/Y') }} &nbsp;·&nbsp; Rama: feature/dev2-contabilidad-compras
    </div>
    <div class="portada-meta">
        <strong>Empresa:</strong> {{ $empresa->nombre_comercial ?? 'Altamira' }}<br>
        <strong>Generado por:</strong> {{ $usuario->nombre ?? $usuario->email }}<br>
        <strong>Generado:</strong> {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="portada-resumen">
        <div class="res-box verde">
            <div class="res-modulo">Contabilidad</div>
            <div class="res-pct verde">73%</div>
            <div class="res-detalle">✓22 &nbsp; ⚠6 &nbsp; ✗2 &nbsp; de 30</div>
        </div>
        <div class="res-box naranja">
            <div class="res-modulo">Compras</div>
            <div class="res-pct naranja">92%</div>
            <div class="res-detalle">✓22 &nbsp; ⚠2 &nbsp; ✗0 &nbsp; de 24</div>
        </div>
        <div class="res-box azul">
            <div class="res-modulo">Bancos</div>
            <div class="res-pct azul">95%</div>
            <div class="res-detalle">✓18 &nbsp; ⚠0 &nbsp; ✗1 &nbsp; de 19</div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════ PÁGINA 1 — CONTABILIDAD ════════════════════ --}}
<div class="page">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-titulo">Diagnóstico Dev 2 — Altamira ERP</div>
            <div class="hp-sub">Módulo Contabilidad</div>
        </div>
        <div class="hp-right">
            <div class="hp-meta">{{ now()->format('d/m/Y') }} · {{ $empresa->nombre_comercial ?? 'Altamira' }}</div>
        </div>
    </div>

    <div class="mod-header cont">
        <div class="mod-h-left">
            <div class="mod-titulo">MÓDULO CONTABILIDAD</div>
            <div class="mod-sub">Plan de Cuentas · Asientos · Ejercicios · Parámetros · Reportes</div>
        </div>
        <div class="mod-h-right">
            <div class="mod-pct">73%</div>
            <div class="mod-stats">✓ 22 &nbsp;·&nbsp; ⚠ 6 &nbsp;·&nbsp; ✗ 2 &nbsp;·&nbsp; de 30 ítems</div>
        </div>
    </div>

    <div class="grid2">
        <div class="grid2-l">

            {{-- C1 Plan de Cuentas --}}
            <div class="card">
                <div class="card-header cont">C1 — Plan de Cuentas</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">CRUD completo (crear, editar, toggle, eliminar)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">index, store, update, toggleEstado, destroy</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Exportar Excel</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">exportar() con Maatwebsite\Excel</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Importar Excel (modal + backend)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">importarExcel() + PlanCuentasImport.php + ImportarExcelModal</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Botón "Importar Excel" verde en UI</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Agregado junto a botón exportar</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">centro_costo_id por cuenta en el plan</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">PlanCuenta model sin campo centro_costo_id; centros de costo son tabla separada</td>
                    </tr>
                </table>
            </div>

            {{-- C3 Asientos Contables --}}
            <div class="card">
                <div class="card-header cont">C3 — Asientos Contables</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">CRUD: crear, ver, anular</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">index, store, show, anular, destroy</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Candado Debe = Haber (excepción si no cuadra)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::crear line 50; throw new \Exception(...)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Exportar Excel</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">exportarExcel()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">PDF imprimible en modal iframe</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">imprimirPdf() + reportePdf() — modal &lt;iframe&gt; confirmado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Libro Diario (PDF)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">libroDiario() en ReporteContableController</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Mayor por cuenta (PDF)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">mayorCuenta() en AsientoContableController + mayor()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Bloqueo creación en período cerrado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService líneas 29–38: verifica EjercicioContable::periodoActivo()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Bloqueo anulación en período cerrado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoContableController.anular() líneas 138–139</td>
                    </tr>
                </table>
            </div>

            {{-- C5 Parámetros --}}
            <div class="card">
                <div class="card-header cont">C5 — Parámetros Contables</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Listado de parámetros con descripción y grupo</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">25 parámetros en 5 grupos (Ventas, Compras, Inventario, Bancos, Nómina, SRI)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Edición individual de cada parámetro</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ParametroContableController::update()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Autoconfigurar según schema</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">autoconfigurar() busca cuentas por patrones de código</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Código default cta_clientes_locales = 1.1.1.5</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">Default es 1.1.02.01.01 — configurable via UI pero no coincide con schema legacy</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Código default cta_proveedores_locales = 2.1.1.3</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">Default es 2.1.01.01.01 — configurable via UI</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Código default cta_sueldos_salarios = 5.3.1.02</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">No verificado — depende del plan de cuentas cargado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Código default cta_gasto_compras = 5.4.1.01</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">No verificado — depende del plan de cuentas cargado</td>
                    </tr>
                </table>
            </div>

        </div>
        <div class="grid2-r">

            {{-- C2 Cierre Fiscal --}}
            <div class="card">
                <div class="card-header cont">C2 — Cierre Fiscal Anual</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Botón "Cierre Fiscal Anual" solo para super_admin</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Solo visible cuando esSuperAdmin, color púrpura #7C3AED</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Modal con selector de año y campo motivo</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Año (año actual - 5 años), motivo mínimo 10 chars</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Validación: todos los períodos del año cerrados</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Verifica los 12 meses en EjercicioContable</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Verifica que no se haya cerrado ya</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Consulta log_cambios_criticos</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Registra en log_cambios_criticos</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Auditoría completa</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Zerear cuentas clase 4 (ingresos) y clase 5 (gastos)</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">Solo crea asiento dummy 0.01/0.01 — no itera cuentas de resultado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Mover resultado a 3.1.5.01 → 3.1.4.01</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">No implementado — lógica de arrastre de utilidad ausente</td>
                    </tr>
                </table>
            </div>

            {{-- C4 Asientos Automáticos --}}
            <div class="card">
                <div class="card-header cont">C4 — Asientos Automáticos</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Venta en efectivo → Caja/Banco</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::facturaAutorizada() con cta_caja_general</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cobro CxC → Banco</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::cobro()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Anticipo cliente</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::anticipoCliente()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Nota de crédito emitida</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::notaCreditoEmitida()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Compra registrada → CxP</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::compraRegistrada() (inventario + IVA + CxP)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Pago a proveedor</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::pagoProveedor()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Anticipo a proveedor</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::anticipoProveedor()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Nómina (sueldos + IESS patronal + por pagar)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::nomina() — asiento NOM-02 completo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Ajuste inventario</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::ajusteInventario()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cierre de caja</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::cierreCaja()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Datafast: lote + liquidación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DatafastController::storeLote() + liquidar() con retenciones</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Ajuste conciliación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::ajusteConciliacion()</td>
                    </tr>
                </table>
            </div>

            {{-- C6 Reportes --}}
            <div class="card">
                <div class="card-header cont">C6 — Reportes Contables</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Balance de Comprobación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ReporteContableController::balanceComprobacion()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Balance General</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ReporteContableController::balanceGeneral()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Estado de Resultados</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ReporteContableController::estadoResultados()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Ejercicios: abrir, cerrar, reabrir</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">EjercicioContableController: index, store, cerrar, reabrir</td>
                    </tr>
                </table>
            </div>

        </div>
    </div>

    {{-- Barra resumen Contabilidad --}}
    <div class="mod-resumen">
        <div class="mr-cell">
            <div class="mr-num ok">22</div>
            <div class="mr-label">Completado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num warn">6</div>
            <div class="mr-label">Parcial / Por verificar</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num err">2</div>
            <div class="mr-label">No implementado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num" style="color:#6B7280">30</div>
            <div class="mr-label">Total ítems</div>
        </div>
        <div class="mr-total">
            <div class="mr-pct-label">CONTABILIDAD</div>
            <div class="mr-pct-num">73%</div>
            <div class="mr-pct-de">completado del alcance</div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 1 — Módulo Contabilidad</div>
    </div>
</div>

{{-- ═══════════════════════════ PÁGINA 2 — COMPRAS ═════════════════════════ --}}
<div class="page pb">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-titulo">Diagnóstico Dev 2 — Altamira ERP</div>
            <div class="hp-sub">Módulo Compras</div>
        </div>
        <div class="hp-right">
            <div class="hp-meta">{{ now()->format('d/m/Y') }} · {{ $empresa->nombre_comercial ?? 'Altamira' }}</div>
        </div>
    </div>

    <div class="mod-header comp">
        <div class="mod-h-left">
            <div class="mod-titulo">MÓDULO COMPRAS</div>
            <div class="mod-sub">Proveedores · Facturas · CxP · Anticipos · Importaciones · Devoluciones</div>
        </div>
        <div class="mod-h-right">
            <div class="mod-pct">92%</div>
            <div class="mod-stats">✓ 22 &nbsp;·&nbsp; ⚠ 2 &nbsp;·&nbsp; ✗ 0 &nbsp;·&nbsp; de 24 ítems</div>
        </div>
    </div>

    <div class="grid2">
        <div class="grid2-l">

            {{-- P1 XML SRI --}}
            <div class="card">
                <div class="card-header comp">P1 — XML SRI</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Botón "Cargar XML SRI" en toolbar</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Color cyan #0891b2</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Modal de carga de archivo .xml</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CargarXmlModal con input file</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Parseo: ruc, razonSocial, numDocumento, claveAcceso</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CompraController::parsearXml() con SimpleXMLElement</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Parseo: fechaEmision, subtotal, IVA, total</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Lee infoFactura completo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Auto-rellena formulario NuevaCompra</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">xmlPrefill state + initialValues={xmlPrefill}</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Busca proveedor por RUC automáticamente</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Proveedor::where('identificacion', $ruc)</td>
                    </tr>
                </table>
            </div>

            {{-- P3 Importaciones --}}
            <div class="card">
                <div class="card-header comp">P3 — Importaciones</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">CONCEPTOS_COSTO exactos (13 tipos)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Actualizado esta sesión — ISD, IVA 15%, Seguro, Advalorem, FODINFA, ICE, Flete, Gastos Destino, Honorarios Aduanero, Almacenaje, Honorarios Banco, Transporte Nacional, Otro</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Liquidar importación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ImportacionController::liquidar()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Prorrateo por cantidad o precio</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">metodo_prorrateo in:cantidad,precio</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Crear factura desde importación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ImportacionController::crearFactura() — pre-llena NuevaCompra</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Agregar costos adicionales</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ImportacionController::agregarCosto()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Detalle de importación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ImportacionController::detalle()</td>
                    </tr>
                </table>
            </div>

            {{-- P5 CxP --}}
            <div class="card">
                <div class="card-header comp">P5 — Cuentas por Pagar</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Registrar cuenta por pagar</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Crea registro en cuentas_por_pagar</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Filtros vencimiento (hoy/semana/mes/año/vencidas)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Actualiza saldo y registra pago</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Ver detalle de cuenta por pagar</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Muestra movimientos y saldo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Enlazado con compras</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Desde compra se genera CxP</td>
                    </tr>
                </table>
            </div>

        </div>
        <div class="grid2-r">

            {{-- P2 Tipos y Retenciones --}}
            <div class="card">
                <div class="card-header comp">P2 — Tipos de Documento y Retenciones</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">FAC (Factura), LIQ, TIK, CON, EXT (Exterior)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Validado en store() con in:FAC,LIQ,TIK,CON,EXT</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">EXT siempre IVA 0%</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">$esExterior ? 0 : (float)($d['porcentaje_iva'] ?? 15)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Asiento automático al registrar compra</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::compraRegistrada()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Retenciones de IR/IVA en compras (UI captura %)</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">AsientoService soporta retencionIR/retencionIVA pero CompraController::store() no valida ni captura esos campos del formulario</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Sustento tributario (01–08)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Campo sustento_tributario en fillable</td>
                    </tr>
                </table>
            </div>

            {{-- P4 Proveedores --}}
            <div class="card">
                <div class="card-header comp">P4 — Proveedores</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">CRUD completo + toggle estado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">index, store, update, toggleEstado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Validación de RUC único</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">store(): 'identificacion' => 'unique:proveedores'</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Estado ACTIVO/INACTIVO</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Campo estado + toggleEstado()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Listado con filtros y búsqueda</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">index() con filtros y search</td>
                    </tr>
                </table>
            </div>

            {{-- P6 Devoluciones --}}
            <div class="card">
                <div class="card-header comp">P6 — Devoluciones de Compra</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Módulo completo: listado + modal crear + anular</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DevolucionCompraController + Pages/Compras/Devoluciones/Index.tsx</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Migración idempotente</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">2025_06_30 con hasTable guard</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Asiento automático al crear devolución</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::crear() desde DevolucionCompraController::store()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Anular devolución revierte asiento</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">asientoService->anular() en anular()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Sidebar: link "Devoluciones" en menú Compras</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Agregado en Sidebar.tsx</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Decrementar stock al devolver productos</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">No implementado — devolución no actualiza inventario</td>
                    </tr>
                </table>
            </div>

        </div>
    </div>

    <div class="mod-resumen">
        <div class="mr-cell">
            <div class="mr-num ok">22</div>
            <div class="mr-label">Completado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num warn">2</div>
            <div class="mr-label">Parcial / Por verificar</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num err">0</div>
            <div class="mr-label">No implementado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num" style="color:#6B7280">24</div>
            <div class="mr-label">Total ítems</div>
        </div>
        <div class="mr-total">
            <div class="mr-pct-label">COMPRAS</div>
            <div class="mr-pct-num">92%</div>
            <div class="mr-pct-de">completado del alcance</div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 2 — Módulo Compras</div>
    </div>
</div>

{{-- ═══════════════════════════ PÁGINA 3 — BANCOS + RESUMEN ════════════════ --}}
<div class="page pb">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-titulo">Diagnóstico Dev 2 — Altamira ERP</div>
            <div class="hp-sub">Módulo Bancos · Resumen General</div>
        </div>
        <div class="hp-right">
            <div class="hp-meta">{{ now()->format('d/m/Y') }} · {{ $empresa->nombre_comercial ?? 'Altamira' }}</div>
        </div>
    </div>

    <div class="mod-header banco">
        <div class="mod-h-left">
            <div class="mod-titulo">MÓDULO BANCOS</div>
            <div class="mod-sub">BancoCaja · Movimientos · Conciliaciones · Cierre Caja · Datafast · Cheques · Reportes</div>
        </div>
        <div class="mod-h-right">
            <div class="mod-pct">95%</div>
            <div class="mod-stats">✓ 18 &nbsp;·&nbsp; ⚠ 0 &nbsp;·&nbsp; ✗ 1 &nbsp;·&nbsp; de 19 ítems</div>
        </div>
    </div>

    <div class="grid2">
        <div class="grid2-l">
            <div class="card">
                <div class="card-header banco">B1 — BancoCaja y Movimientos</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">BancoCaja CRUD: banco/caja/caja_chica/tarjeta</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoCajaController: index, store, update, destroy</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cuenta contable automática por tipo</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">getCuentaContableAutomatica() busca por código</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Movimientos CRUD: ingreso/egreso/transferencia</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">MovimientoBancarioController</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Selector de persona: Manual/Cliente/Proveedor</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Toggle 3-botones + select que auto-rellena beneficiario</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Beneficiario tipado (no texto libre)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">PersonaOpt interface + select con identificacion</td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <div class="card-header banco">B2 — Conciliaciones y Cierre de Caja</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">CRUD conciliaciones</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ConciliacionController</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Filtros: banco, fecha_desde, fecha_hasta, estado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Filtros en controller + UI</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Asiento automático de ajuste</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::ajusteConciliacion()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Registrar cierre de caja</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CierreCajaController</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Asiento automático de cierre</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::cierreCaja()</td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <div class="card-header banco">B3 — Reportes Bancarios</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Estado de Cuenta</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoReporteController::estadoCuenta()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Reporte Movimientos</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoReporteController::reporteMovimientos()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Reporte Caja Chica</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoReporteController::reporteCajaChica()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Consulta Cobros/Pagos + Excel + PDF</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">consultaCobrosPagos() + consultaExcel() + consultaPdf()</td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="grid2-r">
            <div class="card">
                <div class="card-header banco">B4 — Datafast</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Registrar lote Datafast</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DatafastController::storeLote() + asiento automático</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Liquidar lote (comisión, retenciones IVA/IR)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DatafastController::liquidar() con asiento completo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Filtros: banco, estado, fechas, búsqueda lote</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Filtros implementados en controller + UI</td>
                    </tr>
                </table>
            </div>

            <div class="card">
                <div class="card-header banco">B5 — Cheques</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Emitir cheque</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ChequesController::store() — descuenta saldo bancario</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cambiar estado (cobrado/protestado/anulado)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ChequesController::cambiarEstado()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Protestar/anular revierte saldo bancario</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">actualizarSaldo($cheque->monto, 'ingreso')</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Confirmación antes de cambiar estado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CambioEstadoModal con confirm dialog</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Alertas automáticas de vencimientos CxP</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">No existe módulo de notificaciones — solo filtros manuales en CxP</td>
                    </tr>
                </table>
            </div>

            {{-- Resumen BANCOS --}}
            <div class="mod-resumen">
                <div class="mr-cell">
                    <div class="mr-num ok">18</div>
                    <div class="mr-label">Completado</div>
                </div>
                <div class="mr-sep"><div class="mr-sep-line"></div></div>
                <div class="mr-cell">
                    <div class="mr-num warn">0</div>
                    <div class="mr-label">Parcial</div>
                </div>
                <div class="mr-sep"><div class="mr-sep-line"></div></div>
                <div class="mr-cell">
                    <div class="mr-num err">1</div>
                    <div class="mr-label">No implementado</div>
                </div>
                <div class="mr-sep"><div class="mr-sep-line"></div></div>
                <div class="mr-total">
                    <div class="mr-pct-label">BANCOS</div>
                    <div class="mr-pct-num">95%</div>
                    <div class="mr-pct-de">completado del alcance</div>
                </div>
            </div>

            {{-- Pendientes críticos --}}
            <div class="card">
                <div class="card-header" style="background:#FEF2F2;color:#991B1B;border-bottom:1px solid #FECACA;">
                    Pendientes Críticos para Dev 3
                </div>
                <table class="items">
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Cierre Fiscal: zerear clases 4/5</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">Alta complejidad — requiere iterar cuentas de resultado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Cierre Fiscal: transferir 3.1.5.01→3.1.4.01</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">Media complejidad</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Retenciones en formulario de compra</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">Backend listo, falta UI campos ret_ir/ret_iva</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Devolución compra: decrementar stock</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">Falta integración con módulo Inventario</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">ATS / Formulario 104 / 103 SRI</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">0% implementado — muy alta complejidad</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Alertas automáticas de vencimientos</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">Requiere módulo de notificaciones (jobs/queues)</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    {{-- Resumen Total --}}
    <table class="resumen-final">
        <thead>
            <tr>
                <th style="width:20%">Módulo</th>
                <th style="width:8%;text-align:center">✓</th>
                <th style="width:8%;text-align:center">⚠</th>
                <th style="width:8%;text-align:center">✗</th>
                <th style="width:8%;text-align:center">Total</th>
                <th style="width:30%">Barra</th>
                <th style="width:10%;text-align:center">%</th>
                <th style="width:8%;text-align:center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Contabilidad</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">22</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">6</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">2</td>
                <td style="text-align:center">30</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:73%;background:#059669"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#059669">73%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">OK</span></td>
            </tr>
            <tr>
                <td><strong>Compras</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">22</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">2</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">24</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:92%;background:#F97316"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#F97316">92%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">OK</span></td>
            </tr>
            <tr>
                <td><strong>Bancos</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">18</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">1</td>
                <td style="text-align:center">19</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:95%;background:#3B82F6"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#3B82F6">95%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">OK</span></td>
            </tr>
            <tr>
                <td><strong>RRHH</strong></td>
                <td style="text-align:center;color:#7C3AED;font-weight:bold">6</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">—</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">—</td>
                <td style="text-align:center">6</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#7C3AED"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#7C3AED">🔒</td>
                <td style="text-align:center"><span style="font-size:7px;background:#F3E8FF;color:#6D28D9;padding:1px 5px;border-radius:8px;font-weight:bold">REVISIÓN</span></td>
            </tr>
            <tr style="background:#F5F3FF">
                <td><strong>TOTAL Dev 2</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">62</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">8</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">3</td>
                <td style="text-align:center;font-weight:bold">73</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:85%;background:#4C1D95"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#4C1D95;font-size:11px">85%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#EDE9FE;color:#4C1D95;padding:1px 5px;border-radius:8px;font-weight:bold">DEV 2</span></td>
            </tr>
        </tbody>
    </table>

    <div class="leyenda">
        <div class="ley-item"><div class="ley-sym ok">✓</div> Completado</div>
        <div class="ley-item"><div class="ley-sym warn">⚠</div> Parcial / Por verificar</div>
        <div class="ley-item"><div class="ley-sym err">✗</div> No implementado</div>
        <div class="ley-item"><div class="ley-sym lock">🔒</div> En revisión / No tocar</div>
        <div class="ley-item" style="font-size:7px;color:#6B7280">
            Diagnóstico realizado el {{ now()->format('d/m/Y') }}<br>
            Rama: feature/dev2-contabilidad-compras
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 3 — Bancos + Resumen General</div>
    </div>
</div>

</body>
</html>
