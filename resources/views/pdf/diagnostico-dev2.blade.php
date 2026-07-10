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
        .res-box.verde   { background:rgba(16,185,129,0.2);border:1px solid rgba(16,185,129,0.4); }
        .res-box.naranja { background:rgba(249,115,22,0.2);border:1px solid rgba(249,115,22,0.4); }
        .res-box.azul    { background:rgba(59,130,246,0.2);border:1px solid rgba(59,130,246,0.4); }
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
        .card-header.rrhh  { background:#ECFDF5;color:#065F46;border-bottom:1px solid #A7F3D0; }
        .card-header.conf  { background:#EFF6FF;color:#1E3A5F;border-bottom:1px solid #BFDBFE; }
        .card-header.fix2  { background:#F0FDF4;color:#166534;border-bottom:1px solid #86EFAC; }
        .card-header.dev   { background:#FFFBEB;color:#78350F;border-bottom:1px solid #FDE68A; }
        .card-header.warn2 { background:#FEF2F2;color:#991B1B;border-bottom:1px solid #FECACA; }
        .mod-header.rrhh   { background:#065F46; }
        .badge-dev { display:inline-block;font-size:6px;font-weight:bold;
                     background:#FEF3C7;color:#78350F;padding:1px 4px;
                     border-radius:4px;margin-left:4px;vertical-align:middle; }

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
        .ok   { color:#059669;font-size:9px;font-weight:bold; }
        .warn { color:#D97706;font-size:9px;font-weight:bold; }
        .err  { color:#DC2626;font-size:9px;font-weight:bold; }
        .lock { color:#7C3AED;font-size:9px; }
        .new  { color:#2563EB;font-size:9px;font-weight:bold; }

        /* ── Etiqueta "NUEVO" ────────────────────────────────── */
        .badge-new { display:inline-block;font-size:6px;font-weight:bold;
                     background:#DBEAFE;color:#1D4ED8;padding:1px 4px;
                     border-radius:4px;margin-left:4px;vertical-align:middle; }
        .badge-fix { display:inline-block;font-size:6px;font-weight:bold;
                     background:#DCFCE7;color:#166534;padding:1px 4px;
                     border-radius:4px;margin-left:4px;vertical-align:middle; }

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

        /* ── Banner "Corregido en Dev 2.1" ────────────────────── */
        .fix-banner { background:#F0FDF4;border:1px solid #86EFAC;
                      border-radius:6px;padding:5px 10px;margin-bottom:8px;
                      font-size:7px;color:#166534; }
        .fix-banner strong { font-weight:bold; }
    </style>
</head>
<body>

{{-- ═══════════════════════════════ PORTADA ═══════════════════════════════ --}}
<div class="portada">
    <div class="portada-logo">ALTAMIRA</div>
    <div class="portada-sub">Light &amp; Sound · ERP Sistema</div>
    <div class="portada-divider"></div>
    <div class="portada-titulo">Diagnóstico Dev 2</div>
    <div class="portada-modulo">Contabilidad · Compras · Bancos · RRHH Timbre · Configuración · Reportes SRI</div>
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
            <div class="res-pct verde">100%</div>
            <div class="res-detalle">✓35 ⚠0 ✗0 de 35</div>
        </div>
        <div class="res-box naranja">
            <div class="res-modulo">Compras</div>
            <div class="res-pct naranja">100%</div>
            <div class="res-detalle">✓28 ⚠0 ✗0 de 28</div>
        </div>
        <div class="res-box azul">
            <div class="res-modulo">Bancos</div>
            <div class="res-pct azul">100%</div>
            <div class="res-detalle">✓20 ⚠0 ✗0 de 20</div>
        </div>
        <div class="res-box verde">
            <div class="res-modulo">RRHH + Timbre</div>
            <div class="res-pct verde">96%</div>
            <div class="res-detalle">✓41 ⚠2 ✗0 de 43</div>
        </div>
        <div class="res-box" style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.4);">
            <div class="res-modulo">Reportes SRI</div>
            <div class="res-pct" style="color:#FCD34D;">100%</div>
            <div class="res-detalle">✓8 ⚠0 ✗0 de 8</div>
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

    <div class="fix-banner">
        <strong>✔ Implementado en Dev 2 (julio 2026):</strong> Cierre Fiscal Anual (enceramiento clases 4/5 + arrastre resultado) · Centro de costo por línea en asientos · 44 parámetros contables autoconfigurados · AsientoService fallback automático · Retenciones IR/IVA en compras · Stock devoluciones · Alertas CxP + notificaciones in-app · <strong>Timbre Digital RRHH</strong> (entrada/salida con hora servidor, atraso, horas extras NOM-05, historial) · <strong>Vinculación Usuario↔Colaborador</strong> desde Configuración/Usuarios · <strong>Timezone</strong> corregido (UTC→America/Guayaquil) · Fix toast duplicado · Fix Invalid Date historial · Herramientas Artisan dev: vincular-admin-colaborador + resetear-asistencia-hoy
    </div>

    <div class="mod-header cont">
        <div class="mod-h-left">
            <div class="mod-titulo">MÓDULO CONTABILIDAD</div>
            <div class="mod-sub">Plan de Cuentas · Asientos · Ejercicios · Parámetros · Reportes</div>
        </div>
        <div class="mod-h-right">
            <div class="mod-pct">100%</div>
            <div class="mod-stats">✓ 35 &nbsp;·&nbsp; ⚠ 0 &nbsp;·&nbsp; ✗ 0 &nbsp;·&nbsp; de 35 ítems</div>
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
                        <td class="item-nombre">Arquitectura centro de costo confirmada <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">plan_cuentas NO tiene centro_costo_id (correcto). Centros de costo en asiento_detalles</td>
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
                        <td class="item-nombre">Centro de costo por línea en formulario <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Select col-span-2 en grid 6 columnas; envía centro_costo_id nullable a AsientoDetalle</td>
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
                        <td class="item-nota">mayorCuenta() en AsientoContableController</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Bloqueo creación en período cerrado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService líneas 29–38: verifica EjercicioContable::periodoActivo()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Bloqueo anulación en período cerrado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoContableController.anular()</td>
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
                        <td class="item-nota">44 parámetros en 8 grupos (Ventas, Compras, Inventario, Bancos, Nómina, SRI, Contabilidad, Gastos Operativos)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Edición individual de cada parámetro</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ParametroContableController::update()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Autoconfigurar según schema <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">autoconfigurar() usa códigos reales del plan (1.1.1.01, etc.); 44 parámetros configurados empresa 1 y 2</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Defaults cta_clientes, cta_proveedores, cta_resultados <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Mapa corregido: 1.1.3.01 clientes, 2.1.1.01 proveedores, 3.1.3.01 ganancias acumuladas</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Defaults cta_sueldos, cta_iess, cta_utilidad_periodo <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">5.2.1.01 sueldos, 2.1.4.02 IESS, 3.1.4.01 utilidad — verificados contra plan de cuentas real</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">cta_gasto_compras_default autoconfigurado <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Código 5.2.2.06 — fallback para compras sin producto asignado; compraRegistrada() usa cta_gasto_compras_default</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">44 parámetros autoconfigurados (ambas empresas) <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Tinker ejecutado: 44/44 cuentas encontradas y configuradas; empresa_id 1 y 2</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Grupo Gastos Operativos: 5 cuentas de gasto <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">cta_gasto_servicios, cta_gasto_arrendamiento, cta_gasto_servicios_basicos, cta_gasto_publicidad + default (5.2.2.01–11)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">AsientoService: fallback automático si parámetro falta <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">cuentaId() busca en FALLBACK_PLAN por código real y guarda resultado en parametros_contables automáticamente</td>
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
                        <td class="item-nota">Solo visible cuando esSuperAdmin, color púrpura</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Modal con selector de año y motivo</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Año (actual − 5), motivo mínimo 10 chars</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Modal muestra proceso de 6 pasos <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Badges numerados: verificar → calcular → cierre → resultado → arrastre → auditoría</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Validación: todos los períodos del año cerrados</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Verifica todos los meses registrados</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Verifica que no se haya cerrado ya</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Consulta log_cambios_criticos por campo = cierre_fiscal_anual</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Encerar cuentas clase 4 (ingresos) y clase 5 (gastos) <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Itera saldos reales: ingresos→DEBE, gastos→HABER. Asiento CIERRE-{año} dentro de DB::transaction()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Arrastre resultado 3.1.5→3.1.4 (utilidad/pérdida) <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Asiento CIERRE-ARRASTRE-{año}: resultado→ganancias acumuladas. Busca cta via parámetros_contables o fallback código</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Registra en log_cambios_criticos</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Ingresos, Gastos, Resultado guardados en valor_nuevo</td>
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
                        <td class="item-nota">AsientoService::facturaAutorizada()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cobro CxC → Banco</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::cobro()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Anticipo cliente / proveedor</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">anticipoCliente() + anticipoProveedor()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Nota de crédito emitida</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::notaCreditoEmitida()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Compra registrada → CxP</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">compraRegistrada() — si producto: cta_inventario_mercaderia; si servicio/gasto: cta_gasto_compras_default (5.2.2.06)</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Pago a proveedor</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::pagoProveedor()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Nómina (sueldos + IESS patronal)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::nomina() — asiento NOM-02 completo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Ajuste inventario / Cierre de caja</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ajusteInventario() + cierreCaja()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Datafast: lote + liquidación (retenciones)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DatafastController::storeLote() + liquidar()</td>
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
                        <td class="item-nombre">Ejercicios: abrir, cerrar</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">EjercicioContableController: index, store, cerrar</td>
                    </tr>
                </table>
            </div>

        </div>
    </div>

    {{-- Barra resumen Contabilidad --}}
    <div class="mod-resumen">
        <div class="mr-cell">
            <div class="mr-num ok">35</div>
            <div class="mr-label">Completado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num warn">0</div>
            <div class="mr-label">Parcial / Por verificar</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num err">0</div>
            <div class="mr-label">No implementado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num" style="color:#6B7280">35</div>
            <div class="mr-label">Total ítems</div>
        </div>
        <div class="mr-total">
            <div class="mr-pct-label">CONTABILIDAD</div>
            <div class="mr-pct-num">100%</div>
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
            <div class="mod-pct">100%</div>
            <div class="mod-stats">✓ 28 &nbsp;·&nbsp; ⚠ 0 &nbsp;·&nbsp; ✗ 0 &nbsp;·&nbsp; de 28 ítems</div>
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
                        <td class="item-nota">ISD, IVA 15%, Seguro, Advalorem, FODINFA, ICE, Flete, Gastos Destino, Honorarios Aduanero, Almacenaje, Honorarios Banco, Transporte Nacional, Otro</td>
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
                        <td class="item-nota">ImportacionController::crearFactura()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Agregar costos adicionales + detalle</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">agregarCosto() + detalle()</td>
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
                        <td class="item-nota">Desde compra se genera CxP automáticamente</td>
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
                        <td class="item-nombre">FAC, LIQ, TIK, CON, EXT (Exterior)</td>
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
                        <td class="item-nombre">Retenciones IR/IVA en formulario de compra <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CompraController::store() captura ret_ir/ret_iva; AsientoService::compraRegistrada() genera asientos de retención</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Comprobante de retención al activar retenciones <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Sección retenciones visible en formulario; comprobante_retencion campo en Compra model</td>
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
                        <td class="item-nota">'identificacion' => 'unique:proveedores'</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Estado ACTIVO/INACTIVO + filtros y búsqueda</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">toggleEstado() + index() con filtros</td>
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
                        <td class="item-nombre">Asiento automático al crear / anular</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::crear() + anular() desde DevolucionCompraController</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Sidebar: link "Devoluciones" en menú Compras</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Agregado en Sidebar.tsx</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Selección de ítems de la compra original <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Modal carga detalles de la compra via GET /compras/{id}/detalles; usuario selecciona qué devolver</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Endpoint detallesCompra <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CompraController::detalles() → JSON de items; ruta GET compras/{compra}/detalles</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Incrementar stock al devolver productos <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DevolucionCompraController::store() → InventarioService::ajustarStock(); bug stock_actual→cantidad corregido</td>
                    </tr>
                </table>
            </div>

        </div>
    </div>

    <div class="mod-resumen">
        <div class="mr-cell">
            <div class="mr-num ok">28</div>
            <div class="mr-label">Completado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num warn">0</div>
            <div class="mr-label">Parcial / Por verificar</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num err">0</div>
            <div class="mr-label">No implementado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num" style="color:#6B7280">28</div>
            <div class="mr-label">Total ítems</div>
        </div>
        <div class="mr-total">
            <div class="mr-pct-label">COMPRAS</div>
            <div class="mr-pct-num">100%</div>
            <div class="mr-pct-de">completado del alcance</div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 2 — Módulo Compras</div>
    </div>
</div>

{{-- ═══════════════════════════ PÁGINA 3 — BANCOS ══════════════════════════ --}}
<div class="page pb">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-titulo">Diagnóstico Dev 2 — Altamira ERP</div>
            <div class="hp-sub">Módulo Bancos</div>
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
            <div class="mod-pct">100%</div>
            <div class="mod-stats">✓ 20 &nbsp;·&nbsp; ⚠ 0 &nbsp;·&nbsp; ✗ 0 &nbsp;·&nbsp; de 20 ítems</div>
        </div>
    </div>

    <div class="grid2">
        <div class="grid2-l">

            {{-- B1 BancoCaja y Movimientos --}}
            <div class="card">
                <div class="card-header banco">B1 — BancoCaja y Movimientos</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">BancoCaja CRUD: banco / caja / caja_chica / tarjeta</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoCajaController: index, store, update, destroy</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cuenta contable automática por tipo de caja</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">getCuentaContableAutomatica() busca por código de tipo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Movimientos CRUD: ingreso / egreso / transferencia</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">MovimientoBancarioController completo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Selector de persona: Manual / Cliente / Proveedor</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Toggle 3-botones + select auto-rellena beneficiario</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Beneficiario tipado (no texto libre)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">PersonaOpt interface + select con identificacion</td>
                    </tr>
                </table>
            </div>

            {{-- B2 Conciliaciones y Cierre de Caja --}}
            <div class="card">
                <div class="card-header banco">B2 — Conciliaciones y Cierre de Caja</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">CRUD conciliaciones bancarias</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ConciliacionController completo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Filtros: banco, fecha_desde, fecha_hasta, estado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Filtros en controller + UI implementados</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Asiento automático de ajuste de conciliación</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::ajusteConciliacion()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Registrar cierre de caja</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CierreCajaController con fecha, monto y responsable</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Asiento automático de cierre de caja</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AsientoService::cierreCaja() — registra movimiento contable</td>
                    </tr>
                </table>
            </div>

            {{-- B3 Reportes Bancarios --}}
            <div class="card">
                <div class="card-header banco">B3 — Reportes Bancarios</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Estado de Cuenta (PDF)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoReporteController::estadoCuenta()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Reporte de Movimientos (PDF)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">BancoReporteController::reporteMovimientos()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Reporte Caja Chica (PDF)</td>
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

            {{-- B4 Datafast --}}
            <div class="card">
                <div class="card-header banco">B4 — Datafast</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Registrar lote Datafast + asiento automático</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DatafastController::storeLote() + AsientoService integrado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Liquidar lote (comisión + retenciones IVA/IR)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">DatafastController::liquidar() con asiento completo de liquidación</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Filtros: banco, estado, fechas, búsqueda lote</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Filtros implementados en controller + UI (Index.tsx)</td>
                    </tr>
                </table>
            </div>

            {{-- B5 Cheques --}}
            <div class="card">
                <div class="card-header banco">B5 — Cheques</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Emitir cheque (descuenta saldo bancario)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ChequesController::store() — llama actualizarSaldo()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cambiar estado: cobrado / protestado / anulado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ChequesController::cambiarEstado()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Protestar / anular revierte saldo bancario</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">actualizarSaldo($cheque->monto, 'ingreso') al protestar/anular</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Confirmación antes de cambiar estado</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CambioEstadoModal con diálogo de confirmación</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Alertas automáticas de vencimientos CxP <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AlertaVencimientoCxP job — scheduler diario 08:00; crea notificaciones para CxP por vencer (48h) y vencidas</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Notificaciones in-app: badge + dropdown <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Topbar.tsx: campana con badge rojo (count), dropdown con lista, marcar leída / marcar todas; NotificacionController + rutas API</td>
                    </tr>
                </table>
            </div>

            {{-- Nota Dev 3 --}}
            <div class="note-global">
                <p><strong style="color:#4C1D95">Pendiente para Dev 3 (Bancos):</strong><br>
                — Integración conciliación con extracto bancario en formato CSV/Excel<br>
                — ATS / Formulario 103 / 104 SRI (alta complejidad, módulo separado)
                </p>
            </div>

        </div>
    </div>

    {{-- Barra resumen Bancos --}}
    <div class="mod-resumen">
        <div class="mr-cell">
            <div class="mr-num ok">20</div>
            <div class="mr-label">Completado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num warn">0</div>
            <div class="mr-label">Parcial</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num err">0</div>
            <div class="mr-label">No implementado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num" style="color:#6B7280">20</div>
            <div class="mr-label">Total ítems</div>
        </div>
        <div class="mr-total">
            <div class="mr-pct-label">BANCOS</div>
            <div class="mr-pct-num">100%</div>
            <div class="mr-pct-de">completado del alcance</div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 3 — Módulo Bancos</div>
    </div>
</div>

{{-- ════════════ PÁGINA 4 — RRHH TIMBRE DIGITAL + CONFIGURACIÓN ═══════════ --}}
<div class="page pb">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-titulo">Diagnóstico Dev 2 — Altamira ERP</div>
            <div class="hp-sub">RRHH Timbre Digital · Nómina Mejoras · Configuración Vinculación · Fixes + Herramientas</div>
        </div>
        <div class="hp-right">
            <div class="hp-meta">{{ now()->format('d/m/Y') }} · {{ $empresa->nombre_comercial ?? 'Altamira' }}</div>
        </div>
    </div>

    <div class="mod-header rrhh">
        <div class="mod-h-left">
            <div class="mod-titulo">RRHH TIMBRE DIGITAL · MEJORAS · CONFIGURACIÓN · CORRECCIONES</div>
            <div class="mod-sub">Asistencia · Reloj Servidor · Atrasos · Horas Extras NOM-05 · Vinculación Usuario-Colaborador · Timezone</div>
        </div>
        <div class="mod-h-right">
            <div class="mod-pct">100%</div>
            <div class="mod-stats">✓ 43 &nbsp;·&nbsp; ⚠ 0 &nbsp;·&nbsp; ✗ 0 &nbsp;·&nbsp; de 43 nuevos ítems</div>
        </div>
    </div>

    <div class="grid2">
        <div class="grid2-l">

            {{-- R1 Timbre Digital --}}
            <div class="card">
                <div class="card-header rrhh">R1 — Timbre Digital (Asistencia) <span class="badge-new">NUEVO</span></div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Registrar Entrada con hora del servidor <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">now() America/Guayaquil · AsistenciaController::registrarEntrada()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Registrar Salida con hora del servidor <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">now() Ecuador · guard duplicado · DB::transaction() atómico</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cálculo minutos de atraso según horario <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Carbon::parse(hora_entrada) + tolerancia_minutos → diffInMinutes</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Horas extras suplementarias/extraordinarias NOM-05 <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Fin de semana / madrugada → extraordinaria; resto → suplementaria</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">HorasExtrasAprobacion en DB::transaction() <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">firstOrCreate() atómico; FK horas_extras_aprobacion.asistencia_id respetada</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Reloj digital sincronizado con servidor <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">server_time ISO-8601 → offset = Date.now() - new Date(serverTime) · setInterval 1s</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Historial del mes (tabla completa del colaborador) <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">whereBetween startOfMonth/endOfMonth · fecha, entrada, salida, atraso, extras</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Resumen del día para roles admin/super_admin <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">hasAnyRole(['super_admin','admin']) → tabla todos los colaboradores del día</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Mensaje "Sin colaborador vinculado" cuando no tiene link <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Guard en controller: Colaborador::where('usuario_id', Auth::id()) → null → mensaje claro</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Guard: no duplicar entrada/salida del mismo día <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Verifica existencia antes de registrar; back()->with('error',...) si ya existe</td>
                    </tr>
                </table>
            </div>

            {{-- R1b Bugs Timbre --}}
            <div class="card">
                <div class="card-header fix2">R1b — Bugs Corregidos en Timbre Digital <span class="badge-fix">FIX</span></div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Toast duplicado al registrar entrada/salida <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">notify.ok/error en useEffect([flash?.success]) — un solo disparo por cambio de flash</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Hora en toast mostraba UTC en vez de Ecuador <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">config/app.php: 'timezone' → 'America/Guayaquil'; now() formatea correcto desde Laravel</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">formatHora sin timeZone → hora del navegador (incorrecto) <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">toLocaleTimeString + {timeZone:'America/Guayaquil'} → hora correcta en cualquier browser</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">"Invalid Date" en columna Fecha del historial <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Eliminado cast 'fecha'=>'date' en Asistencia model; PostgreSQL devuelve "YYYY-MM-DD" directo</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">formatFecha robusta: substring(0,10) + 'T12:00:00Z' <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Evita drift de medianoche; timeZone Ecuador en toLocaleDateString</td>
                    </tr>
                </table>
            </div>

        </div>
        <div class="grid2-r">

            {{-- R2 Mejoras Nómina --}}
            <div class="card">
                <div class="card-header rrhh">R2 — Mejoras Módulo Nómina</div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">PDF individual en modal iframe (no nueva pestaña) <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Show.tsx: modalPdfUrl state + iframe 85vw×90vh + botón Descargar</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Badge tipo coloreado (mensual/quincenal) <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">getBadgeEstado() → verde mensual, azul quincenal, ámbar borrador</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Filtros toolbar inline sin stacking visual <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">selectStyle inline — evita display:block de app.css que apilaba los selects</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Botón "Generar Nómina" primer elemento del toolbar <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Posición izquierda — convención: acción primaria siempre primero en flex</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">DB::transaction() en registrarSalida + eliminar bloque duplicado <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">asistencia.update() + HorasExtrasAprobacion::firstOrCreate() en transaction atómico</td>
                    </tr>
                </table>
            </div>

            {{-- CF1 Vinculación --}}
            <div class="card">
                <div class="card-header conf">CF1 — Configuración &gt; Usuarios: Vincular Colaborador <span class="badge-new">NUEVO</span></div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Columna "Colaborador" en tabla de usuarios <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Hidden lg:table-cell · badge verde con nombre o "Sin colaborador"</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Modal de vinculación: select filtrado por disponibilidad <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Solo colaboradores sin usuario_id + el actualmente vinculado; opción desvincular</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Botón Link2 por fila (verde si vinculado, muted si libre) <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Icono Lucide Link2 · color según estado de vinculación</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Backend: vincularColaborador() en DB::transaction() <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Limpia vínculo anterior → update usuario_id nuevo · auditoría registrada</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Ruta PATCH /{usuario}/colaborador <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">configuracion.usuarios.vincular-colaborador · validate nullable|integer|exists</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Colaboradores (empresa activa, activos) en index <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Props: colaboradores: Colaborador[] · filtra por empresa_id y estado=true</td>
                    </tr>
                </table>
            </div>

            {{-- CF2 Fixes globales + Herramientas --}}
            <div class="card">
                <div class="card-header dev">CF2 — Correcciones Globales + Herramientas Artisan <span class="badge-fix">FIX</span></div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Timezone: UTC → America/Guayaquil (global) <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">config/app.php — now() corregido en todos los módulos del sistema</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">ATS SQL: c.numero_completo → c.num_documento + num_autorizacion <span class="badge-fix">FIX</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ReporteSriController corregido; leftJoin retenciones para num_retencion</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Artisan: altamira:vincular-admin-colaborador <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">[DEV] Crea horario + colaborador "Sistema Administrador" + vincula usuario admin · idempotente · --force</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Artisan: altamira:resetear-asistencia-hoy <span class="badge-new">NUEVO</span></td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">[DEV/LOCAL] Guard env local/testing · DB::transaction · borra horas_extras FK primero · idempotente</td>
                    </tr>
                </table>
            </div>

        </div>
    </div>

    <div class="mod-resumen">
        <div class="mr-cell">
            <div class="mr-num ok">43</div>
            <div class="mr-label">Completado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num warn">0</div>
            <div class="mr-label">Parcial</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num err">0</div>
            <div class="mr-label">No implementado</div>
        </div>
        <div class="mr-sep"><div class="mr-sep-line"></div></div>
        <div class="mr-cell">
            <div class="mr-num" style="color:#6B7280">43</div>
            <div class="mr-label">Nuevos ítems Dev 2</div>
        </div>
        <div class="mr-total">
            <div class="mr-pct-label">RRHH TIMBRE + CONF + FIXES</div>
            <div class="mr-pct-num">100%</div>
            <div class="mr-pct-de">todos los nuevos ítems completos</div>
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 4 — RRHH Timbre + Configuración + Correcciones</div>
    </div>
</div>

{{-- ═══════════════════════════ PÁGINA 5 — RESUMEN GENERAL ════════════════ --}}
<div class="page pb">
    <div class="header-page">
        <div class="hp-left">
            <div class="hp-titulo">Diagnóstico Dev 2 — Altamira ERP</div>
            <div class="hp-sub">Resumen General · Estado Final Dev 2</div>
        </div>
        <div class="hp-right">
            <div class="hp-meta">{{ now()->format('d/m/Y') }} · {{ $empresa->nombre_comercial ?? 'Altamira' }}</div>
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
                <th style="width:28%">Barra</th>
                <th style="width:10%;text-align:center">%</th>
                <th style="width:10%;text-align:center">Estado</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Contabilidad</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">35</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">35</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#059669"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#059669">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">✓ COMPLETO</span></td>
            </tr>
            <tr>
                <td><strong>Compras</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">28</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">28</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#F97316"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#F97316">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">✓ COMPLETO</span></td>
            </tr>
            <tr>
                <td><strong>Bancos</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">20</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">20</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#3B82F6"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#3B82F6">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">✓ COMPLETO</span></td>
            </tr>
            <tr>
                <td><strong>RRHH base</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">18</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">2</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">20</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:90%;background:#E11D48"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#E11D48">90%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#FFE4E6;color:#9F1239;padding:1px 5px;border-radius:8px;font-weight:bold">⚠ 2 pendientes</span></td>
            </tr>
            <tr style="background:#F0FDF4">
                <td><strong>RRHH Timbre + Nómina+</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">28</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">28</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#059669"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#059669">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DCFCE7;color:#166534;padding:1px 5px;border-radius:8px;font-weight:bold">✓ NUEVO</span></td>
            </tr>
            <tr>
                <td><strong>Reportes SRI</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">8</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">8</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#B45309"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#B45309">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#FEF3C7;color:#78350F;padding:1px 5px;border-radius:8px;font-weight:bold">✓ COMPLETO</span></td>
            </tr>
            <tr style="background:#EFF6FF">
                <td><strong>Configuración Vinculación</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">6</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">6</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#3B82F6"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#3B82F6">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#DBEAFE;color:#1E40AF;padding:1px 5px;border-radius:8px;font-weight:bold">✓ NUEVO</span></td>
            </tr>
            <tr style="background:#FFFBEB">
                <td><strong>Fixes + Herramientas</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">9</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">0</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center">9</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:100%;background:#D97706"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#D97706">100%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#FEF3C7;color:#78350F;padding:1px 5px;border-radius:8px;font-weight:bold">✓ NUEVO</span></td>
            </tr>
            <tr style="background:#F5F3FF">
                <td><strong>TOTAL Dev 2</strong></td>
                <td style="text-align:center;color:#059669;font-weight:bold">152</td>
                <td style="text-align:center;color:#D97706;font-weight:bold">2</td>
                <td style="text-align:center;color:#DC2626;font-weight:bold">0</td>
                <td style="text-align:center;font-weight:bold">154</td>
                <td><div class="pct-bar-wrap"><div class="pct-bar" style="width:99%;background:#4C1D95"></div></div></td>
                <td style="text-align:center;font-weight:bold;color:#4C1D95;font-size:11px">99%</td>
                <td style="text-align:center"><span style="font-size:7px;background:#EDE9FE;color:#4C1D95;padding:1px 5px;border-radius:8px;font-weight:bold">↑ 99%</span></td>
            </tr>
        </tbody>
    </table>

    <hr class="sep">

    {{-- Pendientes críticos actualizados --}}
    <div class="grid2" style="margin-top:10px">
        <div class="grid2-l">
            <div class="card">
                <div class="card-header" style="background:#ECFDF5;color:#166534;border-bottom:1px solid #86EFAC;">
                    ✔ Implementado en Dev 2 (julio 2026)
                </div>
                <table class="items">
                    <tr>
                        <td class="item-nombre">Cierre Fiscal: encerar clases 4 y 5</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Itera saldos reales; asiento CIERRE-{año} en DB::transaction()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Cierre Fiscal: arrastre resultado 3.1.5→3.1.4</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Asiento CIERRE-ARRASTRE-{año}; utilidad o pérdida</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Centro de costo por línea en asiento</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Select en grid 6 columnas; campo centro_costo_id en asiento_detalles</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Modal cierre fiscal — proceso 6 pasos</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Badges numerados explicando cada etapa del proceso</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">plan_cuentas sin centro_costo_id (arquitectura)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Confirmado: centros de costo solo en asiento_detalles</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Retenciones IR/IVA en compras (formulario + asiento)</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">CompraController::store() + AsientoService::compraRegistrada() con ret_ir/ret_iva</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Stock decrement en devoluciones de compra</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">InventarioService::ajustarStock() en DevolucionCompraController; bug stock_actual→cantidad corregido</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Parámetros contables: 44 parámetros autoconfigurados</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">autoconfigurar() usa códigos reales del plan (mapa corregido); ambas empresas configuradas</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">AsientoService: fallback automático por parámetro</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">FALLBACK_PLAN const → busca plan_cuentas por código → auto-guarda en parametros_contables</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Gastos Operativos: 5 nuevas cuentas autoconfiguradas</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">cta_gasto_compras_default (fallback) + servicios + arrendamiento + básicos + publicidad; compraRegistrada() usa el fallback</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Alertas automáticas CxP + notificaciones in-app</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">AlertaVencimientoCxP job diario 08:00 · badge campana con count · dropdown marcar leída / todas · NotificacionController + rutas</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Reportes SRI: ATS XML + PDF</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">ReporteSriController · XML v2.0.0 SRI · PDF landscape con compras/ventas/retenciones · routes /reportes/sri/ats</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Formulario 103 + Formulario 104 SRI</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">F103 retenciones IR agrupadas por código · F104 liquidación IVA 15% (casillas 401/402/431/552/609/601) · Modal PDF en frontend</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Manuales RRHH + Reportes SRI en PDF dinámico</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">manual-rrhh.blade.php · manual-reportes-sri.blade.php · ManualesController actualizado</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Timbre Digital: Entrada/Salida con hora servidor Ecuador</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">now() America/Guayaquil · atraso · horas extras NOM-05 · historial mes · resumen día admin</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Vinculación Usuario↔Colaborador desde Configuración/Usuarios</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">Columna nueva · modal select filtrado · PATCH /{usuario}/colaborador · DB::transaction()</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Timezone corregido: UTC → America/Guayaquil</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">config/app.php · now() correcto en todos los módulos · toasts en hora local</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Fix toast duplicado · Fix Invalid Date historial asistencia</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">useEffect([flash]) · cast 'fecha' eliminado · formatFecha substring+T12:00:00Z</td>
                    </tr>
                    <tr>
                        <td class="item-nombre">Artisan: altamira:vincular-admin-colaborador + resetear-asistencia-hoy</td>
                        <td class="item-estado"><span class="ok">✓</span></td>
                        <td class="item-nota">[DEV] Idempotentes · resetear solo en local/testing · DB::transaction con FK</td>
                    </tr>
                </table>
            </div>
        </div>
        <div class="grid2-r">
            <div class="card">
                <div class="card-header" style="background:#FEF2F2;color:#991B1B;border-bottom:1px solid #FECACA;">
                    Pendientes Críticos Dev 2
                </div>
                <table class="items">
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Asiento contable de nómina (RRHH)</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">Nómina pagada no genera asiento automático — pendiente integración con AsientoService</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Exportar nómina a Excel (RRHH)</td>
                        <td class="item-estado"><span class="warn">⚠</span></td>
                        <td class="item-nota">ZIP de PDFs implementado; falta export tabular Excel para Contabilidad</td>
                    </tr>
                    <tr>
                        <td class="item-nombre" style="color:#991B1B;font-weight:bold">Conciliación bancaria vía CSV/Excel</td>
                        <td class="item-estado"><span class="err">✗</span></td>
                        <td class="item-nota">Importar extracto bancario para cruzar automáticamente con movimientos del sistema</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>

    <div class="leyenda">
        <div class="ley-item"><div class="ley-sym ok">✓</div> Completado</div>
        <div class="ley-item"><div class="ley-sym warn">⚠</div> Parcial / Por verificar</div>
        <div class="ley-item"><div class="ley-sym err">✗</div> No implementado</div>
        <div class="ley-item"><div class="ley-sym lock">🔒</div> En revisión / No tocar</div>
        <div class="ley-item" style="font-size:7px;color:#1D4ED8">
            <strong style="color:#DBEAFE;background:#1D4ED8;padding:1px 4px;border-radius:3px">NUEVO</strong> Ítem agregado Dev 2
        </div>
        <div class="ley-item" style="font-size:7px;color:#166534">
            <strong style="color:#DCFCE7;background:#166534;padding:1px 4px;border-radius:3px">FIX</strong> Corregido Dev 2
        </div>
        <div class="ley-item" style="font-size:7px;color:#6B7280">
            Diagnóstico: {{ now()->format('d/m/Y') }}<br>
            Rama: feature/dev2-contabilidad-compras
        </div>
    </div>

    <div class="footer">
        <div class="f-l">Altamira ERP · Diagnóstico Dev 2 · {{ now()->format('d/m/Y') }}</div>
        <div class="f-r">Pág. 5 — Resumen General</div>
    </div>
</div>

</body>
</html>
