<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

class GenerarDocumentacion extends Command
{
    protected $signature   = 'altamira:generar-docs';
    protected $description = 'Genera PDFs de manual de usuario por módulo en carpeta usos/';

    private string $css = '
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1a1a2e; background: #fff; }
        .page { padding: 28px 36px; }
        h2 { font-size: 15px; font-weight: 700; color: #1F2D3D; border-left: 4px solid #F59E0B;
             padding-left: 10px; margin: 22px 0 9px; }
        h3 { font-size: 11px; font-weight: 700; color: #2C5F8A; margin: 14px 0 5px; }
        h4 { font-size: 10px; font-weight: 700; color: #374151; margin: 10px 0 4px; }
        p  { font-size: 10px; color: #374151; line-height: 1.6; margin-bottom: 5px; }
        .modulo-titulo { text-align:center; padding:26px 0 18px; }
        .modulo-titulo .nombre { font-size:30px; font-weight:900; color:#1F2D3D; letter-spacing:2px; }
        .modulo-titulo .sub { color:#F59E0B; font-size:11px; margin-top:4px; }
        .modulo-titulo .linea { width:60px; height:3px; background:#F59E0B; margin:12px auto 0; }
        .intro { background:#f0f9ff; border:1px solid #bae6fd; border-radius:6px;
                 padding:11px 14px; margin-bottom:16px; }
        .intro p { color:#0c4a6e; }
        .paso { display:flex; align-items:flex-start; margin-bottom:7px; }
        .paso .num { min-width:22px; height:22px; background:#F59E0B; color:#fff; border-radius:50%;
                     font-size:9.5px; font-weight:700; display:flex; align-items:center;
                     justify-content:center; margin-right:9px; margin-top:1px; flex-shrink:0; }
        .paso .titulo { font-weight:700; font-size:10px; color:#1F2D3D; }
        .paso .detalle { font-size:9.5px; color:#6b7280; line-height:1.5; margin-top:2px; }
        .campos { width:100%; border-collapse:collapse; margin:7px 0 14px; font-size:9.5px; }
        .campos th { background:#1F2D3D; color:#F59E0B; padding:5px 9px; text-align:left;
                     font-size:8.5px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        .campos td { padding:4px 9px; border-bottom:1px solid #e5e7eb; color:#374151; vertical-align:top; }
        .campos tr:nth-child(even) td { background:#f9fafb; }
        .req { color:#ef4444; font-weight:700; }
        .nota { background:#fffbeb; border-left:3px solid #F59E0B; padding:7px 11px;
                margin:9px 0 14px; border-radius:0 4px 4px 0; }
        .nota p { color:#92400e; font-size:9.5px; }
        .tip  { background:#f0fdf4; border-left:3px solid #22c55e; padding:7px 11px;
                margin:9px 0 14px; border-radius:0 4px 4px 0; }
        .tip p { color:#166534; font-size:9.5px; }
        .alerta { background:#fef2f2; border-left:3px solid #ef4444; padding:7px 11px;
                  margin:9px 0 14px; border-radius:0 4px 4px 0; }
        .alerta p { color:#991b1b; font-size:9.5px; }
        .info { background:#f5f3ff; border-left:3px solid #8b5cf6; padding:7px 11px;
                margin:9px 0 14px; border-radius:0 4px 4px 0; }
        .info p { color:#5b21b6; font-size:9.5px; }
        .badge { display:inline-block; padding:2px 7px; border-radius:20px;
                 font-size:8px; font-weight:700; margin:2px 3px 2px 0; }
        .b-am  { background:#fef3c7; color:#92400e; }
        .b-az  { background:#dbeafe; color:#1e40af; }
        .b-vd  { background:#dcfce7; color:#166534; }
        .b-rj  { background:#fee2e2; color:#991b1b; }
        .b-gr  { background:#f3f4f6; color:#4b5563; }
        .b-mo  { background:#ede9fe; color:#6d28d9; }
        .divider { border:none; border-top:1px solid #e5e7eb; margin:16px 0; }
        .ruta { font-family:DejaVu Sans Mono, monospace; background:#f3f4f6; padding:1px 5px;
                border-radius:3px; font-size:9px; color:#1F2D3D; }
        .grid2 { display:table; width:100%; margin:7px 0 14px; }
        .col2  { display:table-cell; width:50%; padding-right:8px; vertical-align:top; }
        .col2:last-child { padding-right:0; padding-left:8px; }
        .card  { border:1px solid #e5e7eb; border-radius:6px; padding:9px 11px;
                 background:#fafafa; margin-bottom:8px; }
        .card-title { font-size:10px; font-weight:700; color:#1F2D3D; margin-bottom:3px; }
        .card-body  { font-size:9.5px; color:#6b7280; line-height:1.5; }
        table.rel { width:100%; border-collapse:collapse; margin:7px 0 14px; }
        table.rel th { background:#2C5F8A; color:#fff; padding:5px 9px; font-size:9px; text-align:left; }
        table.rel td { padding:4px 9px; border-bottom:1px solid #e5e7eb; font-size:9.5px; vertical-align:top; }
        table.rel tr:nth-child(even) td { background:#f8fafc; }
        .header-banda { background:#1F2D3D; color:#F59E0B; padding:7px 36px;
                        font-size:8.5px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; }
        .footer-banda { color:#9ca3af; font-size:8px; text-align:center; padding:9px;
                        border-top:1px solid #e5e7eb; margin-top:18px; }
        .estado-lista { margin:5px 0 12px; }
    ';

    public function handle(): int
    {
        $dir = base_path('usos');
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $modulos = [
            'contabilidad' => ['nombre' => 'Contabilidad', 'html' => $this->htmlContabilidad()],
            'compras'      => ['nombre' => 'Compras',      'html' => $this->htmlCompras()],
            'inventario'   => ['nombre' => 'Inventario',   'html' => $this->htmlInventario()],
            'personas'     => ['nombre' => 'Personas',     'html' => $this->htmlPersonas()],
            'ventas'       => ['nombre' => 'Ventas',       'html' => $this->htmlVentas()],
        ];

        foreach ($modulos as $key => $m) {
            $this->line("Generando {$m['nombre']}...");
            $pdf = Pdf::loadHTML($this->wrap($m['html'], $m['nombre']))->setPaper('a4', 'portrait');
            $pdf->save("{$dir}/{$key}.pdf");
            $this->info("  → usos/{$key}.pdf");
        }

        $this->newLine();
        $this->info('✅ ' . count($modulos) . ' PDFs en carpeta usos/');
        return self::SUCCESS;
    }

    private function wrap(string $body, string $modulo): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
        <style>{$this->css}</style></head><body>
        <div class='header-banda'>Altamira Light &amp; Sound · ERP · Módulo {$modulo}</div>
        {$body}
        <div class='footer-banda'>Manual de usuario · ERP Altamira · " . now()->format('d/m/Y') . "</div>
        </body></html>";
    }

    /* ═══════════════════════════════════════════════════════════════
     * CONTABILIDAD
     * ══════════════════════════════════════════════════════════════ */
    private function htmlContabilidad(): string { return '
    <div class="page">

        <div class="modulo-titulo">
            <div class="nombre">CONTABILIDAD</div>
            <div class="sub">Plan de Cuentas · Ejercicios (por mes) · Asientos · Reportes</div>
            <div class="linea"></div>
        </div>

        <div class="intro"><p><strong>¿Qué hace este módulo?</strong> Es la base contable del sistema.
        Todos los módulos transaccionales (Ventas, Compras, Bancos) generan asientos automáticos en
        Contabilidad. Aquí se configura el catálogo de cuentas, se abren/cierran los períodos y
        se registran asientos manuales de ajuste.</p></div>

        <!-- ── PREREQUISITO ── -->
        <h2>PREREQUISITO: PARÁMETROS CONTABLES</h2>
        <p>Ruta: <span class="ruta">Contabilidad → Parámetros Contables</span></p>
        <p>Antes de usar cualquier módulo transaccional, el contador debe mapear las cuentas del sistema.
        Sin esto, los asientos automáticos de Ventas, Compras y Bancos <strong>no se generarán</strong>.</p>

        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Clic en "Auto-configurar"</div>
            <div class="detalle">El sistema busca cuentas por código exacto y las asigna automáticamente.
            Retorna un reporte de cuáles encontró y cuáles no.</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Revisar los no encontrados y asignar manualmente</div>
            <div class="detalle">Para cada parámetro sin cuenta: clic en el selector y buscar la cuenta del plan.</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Guardar</div>
        </div></div>

        <table class="campos">
            <tr><th>Parámetro clave</th><th>Descripción</th><th>Módulo que lo usa</th></tr>
            <tr><td>cta_clientes_locales</td><td>CxC — lo que nos deben</td><td>Ventas</td></tr>
            <tr><td>cta_ventas_locales</td><td>Ingresos por venta</td><td>Ventas</td></tr>
            <tr><td>cta_iva_ventas</td><td>IVA en ventas por pagar al SRI</td><td>Ventas</td></tr>
            <tr><td>cta_proveedores_locales</td><td>CxP — lo que debemos</td><td>Compras</td></tr>
            <tr><td>cta_iva_compras</td><td>IVA en compras (crédito tributario)</td><td>Compras</td></tr>
            <tr><td>cta_inventario_mercaderia</td><td>Valor del inventario en libros</td><td>Compras/Inventario</td></tr>
            <tr><td>cta_bancos_locales</td><td>Cuentas bancarias</td><td>Bancos/Pagos</td></tr>
            <tr><td>cta_retencion_ir_cobrada</td><td>Retenciones en la fuente recibidas</td><td>Ventas</td></tr>
        </table>

        <!-- ── PLAN DE CUENTAS ── -->
        <h2>1. PLAN DE CUENTAS</h2>
        <p>Ruta: <span class="ruta">Contabilidad → Plan de Cuentas</span></p>
        <p>Catálogo jerárquico de hasta 4 niveles (ej: 1 → 1.1 → 1.1.01 → 1.1.01.01).
        Solo las cuentas hoja (<em>permite_asientos = Sí</em>) pueden recibir movimientos.</p>

        <h3>Crear cuenta</h3>
        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Código</strong></td><td>Ej: 1.1.01.01.08 — sigue la jerarquía del plan</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Nombre</strong></td><td>Nombre descriptivo de la cuenta</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Tipo</strong></td><td>activo / pasivo / patrimonio / ingreso / gasto</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Cuenta padre</strong></td><td>Cuenta de nivel superior (NULL = nivel raíz)</td><td>No</td></tr>
            <tr><td><strong>Permite asientos</strong></td><td>Sí solo para cuentas hoja (sin hijos). Las de grupo: No.</td><td><span class="req">Sí</span></td></tr>
        </table>
        <div class="alerta"><p><strong>Restricción:</strong> No se puede desactivar una cuenta que ya tiene
        asientos registrados (total_asientos > 0).</p></div>

        <!-- ── EJERCICIOS ── -->
        <h2>2. EJERCICIOS CONTABLES (POR MES)</h2>
        <p>Ruta: <span class="ruta">Contabilidad → Ejercicios</span></p>
        <p>Un ejercicio es un <strong>mes</strong> del año (no el año completo). Solo puede haber
        <strong>un ejercicio abierto</strong> por empresa en cualquier momento.</p>

        <h3>Abrir un período</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Clic en "+ Nuevo Ejercicio"</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Seleccionar Año y Mes</div>
            <div class="detalle">Ejemplo: Año 2026, Mes 6 → genera "Junio 2026".
            No se puede crear un ejercicio si ya hay uno abierto.</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Guardar — queda en estado "abierto"</div>
        </div></div>

        <h3>Cerrar un período</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Clic en "Cerrar Ejercicio"</div>
            <div class="detalle">El sistema valida que todos los asientos del período estén cuadrados
            (|total_debe − total_haber| ≤ 0.0001). Si hay desbalances, bloquea el cierre.</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Confirmar</div>
            <div class="detalle">Se registra en log_cambios_criticos. Solo super_admin puede reabrirlo.</div>
        </div></div>

        <div class="alerta"><p><strong>Crítico:</strong> Sin un ejercicio abierto que cubra la fecha actual,
        ningún módulo podrá generar asientos automáticos. Los documentos se guardarán pero el asiento
        contable quedará pendiente.</p></div>

        <table class="rel">
            <tr><th>Estado</th><th>Qué permite</th></tr>
            <tr><td><span class="badge b-vd">abierto</span></td><td>Crear, editar y anular asientos</td></tr>
            <tr><td><span class="badge b-gr">cerrado</span></td><td>Solo lectura — auditoría</td></tr>
        </table>

        <!-- ── ASIENTOS ── -->
        <h2>3. ASIENTOS CONTABLES</h2>
        <p>Ruta: <span class="ruta">Contabilidad → Asientos</span></p>

        <div class="grid2">
            <div class="col2">
                <div class="card"><div class="card-title">Automáticos</div>
                <div class="card-body">Los genera el sistema al guardar facturas, compras, pagos,
                movimientos bancarios, etc. Identificados con <em>is_automatico = true</em>.
                No se crean manualmente.</div></div>
            </div>
            <div class="col2">
                <div class="card"><div class="card-title">Manuales</div>
                <div class="card-body">Solo perfiles: super_admin, admin, contador.
                Para ajustes, correcciones y entradas que el sistema no genera.</div></div>
            </div>
        </div>

        <h3>Registrar asiento manual</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Clic en "+ Nuevo Asiento"</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Ingresar Concepto y Fecha</div>
            <div class="detalle">El número se genera automático: AS-YYYY-0001 (reinicia por año).</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Agregar partidas (mínimo 2)</div>
            <div class="detalle">Cada partida: cuenta + DEBE o HABER (nunca ambos a la vez).
            Descripción de la partida es opcional.</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Guardar</div>
            <div class="detalle">El sistema valida que ∑ Debe = ∑ Haber. Si no cuadra, rechaza.</div>
        </div></div>

        <div class="nota"><p><strong>Regla de oro:</strong> Cada partida tiene DEBE o HABER, nunca los dos.
        El total de todos los DEBEs debe igualar el total de todos los HABERs.</p></div>

        <h3>Anular un asiento</h3>
        <p>Clic en "Anular" en el detalle del asiento. El sistema crea automáticamente un
        <strong>asiento reverso</strong> (mismas cuentas con DEBE y HABER invertidos) y marca el
        original como anulado. Solo super_admin puede anular, y solo dentro de las 24 horas siguientes.</p>

        <!-- ── REPORTES ── -->
        <h2>4. REPORTES</h2>
        <p>Ruta: <span class="ruta">Contabilidad → Reportes</span></p>
        <table class="campos">
            <tr><th>Reporte</th><th>¿Qué muestra?</th><th>Filtros</th></tr>
            <tr><td><strong>Libro Diario</strong></td><td>Todos los asientos vigentes ordenados por fecha</td><td>Rango de fechas</td></tr>
            <tr><td><strong>Mayor General</strong></td><td>Movimientos por cuenta con saldo acumulado corriente</td><td>Cuenta + rango fechas</td></tr>
        </table>
    </div>'; }

    /* ═══════════════════════════════════════════════════════════════
     * COMPRAS
     * ══════════════════════════════════════════════════════════════ */
    private function htmlCompras(): string { return '
    <div class="page">

        <div class="modulo-titulo">
            <div class="nombre">COMPRAS</div>
            <div class="sub">Proveedores · Facturas de Compra · CxP · Anticipos · Importaciones</div>
            <div class="linea"></div>
        </div>

        <div class="intro"><p><strong>¿Qué hace este módulo?</strong> Gestiona el ciclo completo de
        compras: registro de facturas de proveedores, control de cuentas por pagar, anticipos y
        proceso de importaciones. Al <em>activar</em> una compra, el sistema ingresa el stock al
        inventario, genera el asiento contable y crea la CxP si es a crédito.</p></div>

        <!-- ── FLUJO ── -->
        <h2>FLUJO COMPLETO</h2>
        <table class="rel">
            <tr><th>#</th><th>Paso</th><th>Dónde</th><th>Qué genera</th></tr>
            <tr><td>1</td><td>Registrar proveedor (1ª vez)</td><td>Personas → Proveedores</td><td>—</td></tr>
            <tr><td>2</td><td>Ingresar factura de compra</td><td>Compras → Compras</td><td>Estado: <em>pendiente</em></td></tr>
            <tr><td>3</td><td><strong>Activar la compra</strong></td><td>Compras → Compras → Activar</td><td>Stock ↑ · CxP · Asiento</td></tr>
            <tr><td>4</td><td>Recibir en bodega</td><td>Inventario → Recepciones</td><td>Stock confirmado en bodega</td></tr>
            <tr><td>5</td><td>Pagar CxP cuando vence</td><td>Compras → CxP → Pagar</td><td>Movimiento bancario · Asiento</td></tr>
        </table>
        <div class="nota"><p><strong>Importante:</strong> Una compra en estado <em>pendiente</em> NO genera
        ningún movimiento (ni stock, ni CxP, ni asiento). Debe ser <strong>activada</strong> para tener efecto.</p></div>

        <!-- ── PROVEEDOR ── -->
        <h2>1. PROVEEDORES</h2>
        <p>Ruta: <span class="ruta">Personas → Proveedores</span> (también accesible desde Compras → Proveedores)</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Tipo</strong></td><td>nacional / internacional</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Tipo ID</strong></td><td>RUC, cédula, pasaporte</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Identificación</strong></td><td>Número del documento</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Razón Social</strong></td><td>Nombre legal</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>País</strong></td><td>Auto = ECUADOR si tipo=nacional</td><td>No</td></tr>
            <tr><td><strong>Divisa</strong></td><td>Solo internacionales (USD, EUR, etc.)</td><td>No</td></tr>
            <tr><td><strong>Días crédito</strong></td><td>Plazo de pago habitual con este proveedor</td><td>No</td></tr>
        </table>

        <!-- ── FACTURAS DE COMPRA ── -->
        <h2>2. FACTURAS DE COMPRA</h2>
        <p>Ruta: <span class="ruta">Compras → Compras → + Nueva Compra</span></p>

        <h3>Tipos de documento aceptados</h3>
        <div class="estado-lista">
            <span class="badge b-az">FAC — Factura</span>
            <span class="badge b-am">LIQ — Liquidación</span>
            <span class="badge b-gr">TIK — Tiquete</span>
            <span class="badge b-mo">CON — Contrato</span>
            <span class="badge b-vd">EXT — Exterior</span>
        </div>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Proveedor</strong></td><td>Buscar por RUC o nombre. Debe existir en el sistema.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Tipo documento</strong></td><td>FAC / LIQ / TIK / CON / EXT</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>N° Documento</strong></td><td>Número de la factura del proveedor (001-001-000000123)</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Fecha emisión</strong></td><td>Fecha que dice la factura física</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Días crédito</strong></td><td>0 = contado. &gt;0 = calcula fecha vencimiento automáticamente</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Bodega destino</strong></td><td>Bodega donde ingresará el stock al activar</td><td>No</td></tr>
            <tr><td><strong>Gasto no deducible</strong></td><td>Afecta el tipo de asiento generado</td><td>No</td></tr>
            <tr><td><strong>Detalles</strong></td><td>Mínimo 1 línea. Descripción + Cantidad + Precio + %IVA</td><td><span class="req">Sí</span></td></tr>
        </table>

        <div class="nota"><p><strong>Duplicados:</strong> El sistema no permite registrar dos documentos con el
        mismo número para el mismo proveedor.</p></div>

        <h3>Estados de la compra</h3>
        <table class="rel">
            <tr><th>Estado</th><th>¿Qué significa?</th><th>¿Genera movimientos?</th></tr>
            <tr><td><span class="badge b-am">pendiente</span></td><td>Ingresada, esperando confirmación</td><td>No</td></tr>
            <tr><td><span class="badge b-vd">activa</span></td><td>Confirmada, efectiva en el sistema</td><td>Sí: stock + CxP + asiento</td></tr>
            <tr><td><span class="badge b-rj">anulada</span></td><td>Reversiones aplicadas</td><td>Reversiones</td></tr>
        </table>

        <h3>Al activar una compra, el sistema hace automáticamente:</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Ingresa stock al inventario</div>
            <div class="detalle">Crea un InventarioMovimiento tipo "entrada" y actualiza el saldo en la bodega seleccionada.
            También actualiza el costo unitario del producto.</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Crea Recepción de Bodega (si tiene bodega_id)</div>
            <div class="detalle">Genera automáticamente un registro en Inventario → Recepciones para confirmar
            la entrada física de los productos.</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Crea CxP (si dias_credito &gt; 0)</div>
            <div class="detalle">Genera una Cuenta por Pagar con fecha de vencimiento calculada.</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Genera asiento contable automático</div>
            <div class="detalle">Débito: Inventario Mercadería + IVA Compras. Crédito: Proveedores Locales.
            Si es gasto no deducible: débito a la cuenta de gasto correspondiente.</div>
        </div></div>

        <!-- ── CxP ── -->
        <h2>3. CUENTAS POR PAGAR (CxP)</h2>
        <p>Ruta: <span class="ruta">Compras → Cuentas por Pagar</span></p>
        <p>Se crean automáticamente al activar una compra con días de crédito. No se crean manualmente.</p>

        <h3>Pagar una CxP</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Buscar la CxP (filtrar por proveedor, estado o fecha vencimiento)</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Clic en "Pagar" — llenar formulario</div>
        </div></div>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th></tr>
            <tr><td><strong>Fecha pago</strong></td><td>Fecha del desembolso</td></tr>
            <tr><td><strong>Banco / Caja</strong></td><td>Desde dónde sale el dinero. Valida saldo disponible.</td></tr>
            <tr><td><strong>Forma de pago</strong></td><td>transferencia, cheque, efectivo</td></tr>
            <tr><td><strong>Monto</strong></td><td>Puede ser pago parcial o total del saldo</td></tr>
        </table>

        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">El sistema procesa automáticamente</div>
            <div class="detalle">Decrements saldo CxP → si saldo ≤ 0.001: estado=pagada / si no: estado=parcial.
            Crea MovimientoBancario (egreso). Decrementa saldo del banco. Genera asiento de pago.</div>
        </div></div>

        <div class="estado-lista">
            <span class="badge b-am">pendiente</span>
            <span class="badge b-az">parcial</span>
            <span class="badge b-vd">pagada</span>
            <span class="badge b-rj">vencida</span>
        </div>

        <!-- ── ANTICIPOS ── -->
        <h2>4. ANTICIPOS A PROVEEDORES</h2>
        <p>Ruta: <span class="ruta">Compras → Anticipos</span></p>
        <p>Pago adelantado al proveedor antes de recibir la factura.</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Proveedor</strong></td><td>A quién se le paga el anticipo</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Fecha</strong></td><td>Fecha del desembolso</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Monto</strong></td><td>Mínimo $0.01. El banco debe tener saldo suficiente.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Banco</strong></td><td>Cuenta desde la que sale el dinero</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>N° Transferencia</strong></td><td>Referencia bancaria</td><td>No</td></tr>
            <tr><td><strong>Importación</strong></td><td>Vincular a una importación si aplica</td><td>No</td></tr>
        </table>

        <h3>Cruzar anticipo contra una CxP</h3>
        <p>Cuando llega la factura y se activa la compra, el anticipo puede descontarse de la CxP generada.
        El sistema decrementa el saldo del anticipo y el saldo de la CxP simultáneamente.
        Si el anticipo cubre toda la CxP → estado: <span class="badge b-vd">cruzado</span> /
        <span class="badge b-vd">pagada</span>.</p>

        <!-- ── IMPORTACIONES ── -->
        <h2>5. IMPORTACIONES</h2>
        <p>Ruta: <span class="ruta">Compras → Importaciones</span></p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Nombre</strong></td><td>Identificador de la importación (ej: "Importación Yamaha Jun-26")</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Costo FOB</strong></td><td>Valor del producto en puerto de origen (USD)</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Proveedor</strong></td><td>Proveedor internacional de origen</td><td>No</td></tr>
            <tr><td><strong>N° Invoice</strong></td><td>Número de factura del proveedor exterior</td><td>No</td></tr>
            <tr><td><strong>País embarque</strong></td><td>País de origen de la mercadería</td><td>No</td></tr>
            <tr><td><strong>Fecha partida / llegada</strong></td><td>Llegada debe ser ≥ partida</td><td>No</td></tr>
        </table>

        <table class="rel">
            <tr><th>Estado</th><th>Descripción</th></tr>
            <tr><td><span class="badge b-az">en_transito</span></td><td>Mercadería viajando desde el exterior</td></tr>
            <tr><td><span class="badge b-am">en_aduana</span></td><td>Llegó a Ecuador, en proceso de desaduanización</td></tr>
            <tr><td><span class="badge b-vd">liquidada</span></td><td>Costos distribuidos a los productos, importación cerrada</td></tr>
        </table>

        <h3>Prorrateo de costos (al liquidar)</h3>
        <p>Al liquidar una importación, los costos adicionales (flete, seguro, arancel, otros) se
        distribuyen entre las facturas de compra vinculadas. Método:</p>
        <div class="grid2">
            <div class="col2"><div class="card">
                <div class="card-title">Por Cantidad</div>
                <div class="card-body">Distribuye el costo en proporción a la cantidad de unidades
                de cada compra vinculada.</div>
            </div></div>
            <div class="col2"><div class="card">
                <div class="card-title">Por Precio (FOB)</div>
                <div class="card-body">Distribuye el costo en proporción al valor FOB de cada
                compra vinculada. Actualiza el costo unitario de los productos.</div>
            </div></div>
        </div>
    </div>'; }

    /* ═══════════════════════════════════════════════════════════════
     * INVENTARIO
     * ══════════════════════════════════════════════════════════════ */
    private function htmlInventario(): string { return '
    <div class="page">

        <div class="modulo-titulo">
            <div class="nombre">INVENTARIO</div>
            <div class="sub">Productos · Kárdex · Traslados · Activos Fijos · Recepciones</div>
            <div class="linea"></div>
        </div>

        <div class="intro"><p><strong>¿Qué hace este módulo?</strong> Controla el stock de productos
        en cada bodega. Registra todas las entradas (desde Compras), ajustes manuales, traslados entre bodegas
        y activos fijos. El saldo de inventario se actualiza automáticamente cuando Compras activa
        una factura o cuando se confirma una recepción de bodega.</p></div>

        <!-- ── RELACIÓN CON DEV 1 ── -->
        <div class="info"><p><strong>Módulo Dev 1 — Productos/Personas:</strong> El catálogo de productos
        (crear, editar, configurar precio/IVA/marca/categoría) fue desarrollado por Dev 1 en la rama
        <em>feature/dev1-inventario-productos</em>. Dev 2 consume los productos ya creados para
        movimientos, recepciones y traslados.</p></div>

        <!-- ── CONFIGURACIÓN ── -->
        <h2>1. CONFIGURACIÓN PREVIA (Dev 1 — hacer una sola vez)</h2>
        <div class="grid2">
            <div class="col2">
                <div class="card"><div class="card-title">Marcas</div>
                <div class="card-body"><span class="ruta">Inventario → Configuración → Marcas</span><br>
                Yamaha, JBL, Shure, etc. Solo nombre requerido.</div></div>
                <div class="card"><div class="card-title">Categorías</div>
                <div class="card-body"><span class="ruta">Inventario → Configuración → Categorías</span><br>
                Jerarquía padre–hijo. Ej: Instrumentos → Guitarras.</div></div>
            </div>
            <div class="col2">
                <div class="card"><div class="card-title">Bodegas</div>
                <div class="card-body"><span class="ruta">Inventario → Configuración → Bodegas</span><br>
                Bodega Principal, Showroom, Taller, etc.</div></div>
                <div class="card"><div class="card-title">Listas de Precio</div>
                <div class="card-body"><span class="ruta">Inventario → Listas de Precio</span><br>
                Precio Público, Distribuidor, Especial.</div></div>
            </div>
        </div>

        <!-- ── PRODUCTOS ── -->
        <h2>2. PRODUCTOS (Dev 1)</h2>
        <p>Ruta: <span class="ruta">Inventario → Productos</span></p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Código (SKU)</strong></td><td>Único por empresa. Ej: YAM-P125</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Nombre</strong></td><td>Descripción completa del producto</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Tipo</strong></td><td>producto / servicio / repuesto / insumo</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Unidad</strong></td><td>UND, KG, L, PAR, etc.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>% IVA</strong></td><td>0% o 15%. Por defecto 15%.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>PVP</strong></td><td>Precio venta público</td><td>No</td></tr>
            <tr><td><strong>Costo</strong></td><td>Costo inicial. Se actualiza automáticamente con cada compra/recepción.</td><td>No</td></tr>
            <tr><td><strong>Stock mínimo</strong></td><td>Alerta cuando el stock baje de este nivel</td><td>No</td></tr>
            <tr><td><strong>Requiere serie</strong></td><td>Para equipos con seguimiento individual por número de serie</td><td>No</td></tr>
            <tr><td><strong>Tiene ICE</strong></td><td>Impuesto a consumos especiales</td><td>No</td></tr>
        </table>

        <div class="tip"><p><strong>Stock inicial:</strong> Al crear el producto el stock es 0.
        Para dar el saldo inicial usar Inventario → Kárdex → Ajuste de Inventario (tipo: Ingreso).</p></div>

        <!-- ── KARDEX ── -->
        <h2>3. KÁRDEX</h2>
        <p>Ruta: <span class="ruta">Inventario → Kárdex</span></p>
        <p>Registro cronológico de todos los movimientos de un producto en una bodega, con saldo corriente.</p>

        <h3>Tipos de movimiento</h3>
        <table class="rel">
            <tr><th>Tipo</th><th>Origen</th><th>Efecto en stock</th></tr>
            <tr><td><span class="badge b-vd">entrada</span></td><td>Al activar una Compra o Recepción</td><td>Stock ↑</td></tr>
            <tr><td><span class="badge b-rj">salida</span></td><td>Al confirmar una Venta (cuando integre)</td><td>Stock ↓</td></tr>
            <tr><td><span class="badge b-am">ajuste</span></td><td>Manual — Kárdex → Ajuste de Inventario</td><td>↑ o ↓</td></tr>
            <tr><td><span class="badge b-az">traslado</span></td><td>Al confirmar un Traslado de Bodega</td><td>Redistribuye</td></tr>
        </table>

        <h3>Ajuste manual de inventario</h3>
        <p>Ruta: <span class="ruta">Inventario → Kárdex → Ajuste</span></p>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Seleccionar Producto y Bodega</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Tipo: Ingreso (aumentar) o Egreso (reducir)</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Ingresar Cantidad y Motivo</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Guardar — el saldo se actualiza en tiempo real</div>
        </div></div>

        <h3>Saldos por bodega</h3>
        <p>Ruta: <span class="ruta">Inventario → Kárdex → Saldos</span> — muestra el stock actual
        de todos los productos por bodega con su costo unitario y valor total en libros.</p>

        <!-- ── TRASLADOS ── -->
        <h2>4. TRASLADOS ENTRE BODEGAS</h2>
        <p>Ruta: <span class="ruta">Inventario → Traslados</span></p>
        <p>Mueve productos de una bodega a otra. No afecta el stock total de la empresa,
        solo redistribuye entre bodegas.</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Bodega origen</strong></td><td>De dónde sale la mercadería</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Bodega destino</strong></td><td>A dónde llega. Debe ser distinta de origen.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Detalles</strong></td><td>Producto + Cantidad enviada (min 1 línea). Valida stock disponible en origen.</td><td><span class="req">Sí</span></td></tr>
        </table>

        <table class="rel">
            <tr><th>Estado</th><th>Descripción</th></tr>
            <tr><td><span class="badge b-am">pendiente</span></td><td>Creado pero no ejecutado — stock sin cambios</td></tr>
            <tr><td><span class="badge b-vd">confirmado</span></td><td>Ejecutado — stock movido entre bodegas</td></tr>
        </table>

        <div class="alerta"><p><strong>Restricción:</strong> Si la cantidad a trasladar es mayor al stock disponible
        en la bodega origen, el sistema bloquea la operación.</p></div>

        <!-- ── RECEPCIONES ── -->
        <h2>5. RECEPCIONES DE BODEGA</h2>
        <p>Ruta: <span class="ruta">Inventario → Recepciones</span></p>
        <p>Confirma la entrada física de mercadería vinculada a una compra.</p>

        <div class="tip"><p><strong>Creación automática:</strong> Cuando se activa una Compra que tiene
        bodega_id asignada, el sistema crea automáticamente la Recepción de Bodega en estado
        <em>pendiente</em> con las cantidades esperadas. No se crean manualmente desde cero.</p></div>

        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Buscar la recepción pendiente (ya existe desde la compra)</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Verificar cantidades físicamente recibidas</div>
            <div class="detalle">Si se recibió menos que lo esperado, ajustar la cantidad_recibida
            (cantidad_recibida ≤ cantidad_esperada).</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Confirmar recepción</div>
            <div class="detalle">El stock queda registrado en la bodega destino y se generan las
            etiquetas si el producto lo requiere.</div>
        </div></div>

        <!-- ── ACTIVOS ── -->
        <h2>6. ACTIVOS FIJOS</h2>
        <p>Ruta: <span class="ruta">Inventario → Activos Fijos</span></p>
        <p>Bienes de la empresa (vehículos, equipos, muebles, tecnología).</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th></tr>
            <tr><td><strong>Código</strong></td><td>Identificador interno del activo</td></tr>
            <tr><td><strong>Nombre / Descripción</strong></td><td>Descripción del bien</td></tr>
            <tr><td><strong>Categoría</strong></td><td>Mueble, Vehículo, Equipo, Tecnología, etc.</td></tr>
            <tr><td><strong>Fecha adquisición</strong></td><td>Cuándo se compró o recibió</td></tr>
            <tr><td><strong>Valor original</strong></td><td>Costo de compra</td></tr>
            <tr><td><strong>Vida útil (años)</strong></td><td>Para calcular depreciación</td></tr>
            <tr><td><strong>% Depreciación anual</strong></td><td>Según tablas SRI del Ecuador</td></tr>
            <tr><td><strong>Custodio / Ubicación</strong></td><td>Quién lo tiene y dónde está</td></tr>
        </table>
    </div>'; }

    /* ═══════════════════════════════════════════════════════════════
     * PERSONAS
     * ══════════════════════════════════════════════════════════════ */
    private function htmlPersonas(): string { return '
    <div class="page">

        <div class="modulo-titulo">
            <div class="nombre">PERSONAS</div>
            <div class="sub">Clientes · Proveedores · Transportistas</div>
            <div class="linea"></div>
        </div>

        <div class="intro"><p><strong>¿Qué hace este módulo?</strong> Centraliza el catálogo de terceros
        del sistema. Todos los módulos transaccionales (Ventas, Compras, Guías de Remisión)
        referencian este catálogo. Desarrollado principalmente por Dev 1 en
        <em>feature/dev1-personas-clientes</em>. Dev 2 usa estos registros en Compras, Ventas e
        Importaciones.</p></div>

        <!-- ── RELACIONES ── -->
        <h2>RELACIONES CON OTROS MÓDULOS</h2>
        <table class="rel">
            <tr><th>Para hacer...</th><th>Necesita en Personas...</th><th>Módulo consumidor</th></tr>
            <tr><td>Emitir una factura</td><td>Cliente registrado</td><td>Ventas → Facturas</td></tr>
            <tr><td>Hacer una proforma/prefactura</td><td>Cliente registrado</td><td>Ventas → Proformas/Prefacturas</td></tr>
            <tr><td>Registrar una compra</td><td>Proveedor registrado</td><td>Compras → Facturas Compra</td></tr>
            <tr><td>Anticipar a un proveedor</td><td>Proveedor registrado</td><td>Compras → Anticipos</td></tr>
            <tr><td>Emitir guía de remisión</td><td>Transportista registrado</td><td>Ventas → Guías de Remisión</td></tr>
            <tr><td>Registrar importación</td><td>Proveedor (tipo internacional)</td><td>Compras → Importaciones</td></tr>
        </table>

        <!-- ── CLIENTES ── -->
        <h2>1. CLIENTES</h2>
        <p>Ruta: <span class="ruta">Personas → Clientes</span></p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Tipo identificación</strong></td><td>cedula / ruc / pasaporte</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Identificación</strong></td><td>Número de cédula, RUC o pasaporte</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Razón Social</strong></td><td>Nombre completo o razón social legal</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Nombre comercial</strong></td><td>Nombre con el que se conoce el negocio</td><td>No</td></tr>
            <tr><td><strong>Email</strong></td><td>Para envío de facturas electrónicas</td><td>No</td></tr>
            <tr><td><strong>Teléfono / Celular</strong></td><td>Contacto</td><td>No</td></tr>
            <tr><td><strong>Dirección</strong></td><td>Dirección que aparece en la factura</td><td>No</td></tr>
            <tr><td><strong>Tiene crédito</strong></td><td>¿Se le puede vender a crédito?</td><td>No</td></tr>
            <tr><td><strong>Días crédito</strong></td><td>Plazo de pago habitual (si tiene_credito = Sí)</td><td>No</td></tr>
            <tr><td><strong>Cupo máximo</strong></td><td>Límite de deuda pendiente permitida</td><td>No</td></tr>
            <tr><td><strong>Agente retención</strong></td><td>¿El cliente retiene impuestos? (afecta factura)</td><td>No</td></tr>
        </table>

        <div class="tip"><p><strong>Consumidor Final:</strong> Para ventas al público sin datos del cliente,
        usar el cliente genérico "CONSUMIDOR FINAL" (RUC: 9999999999001) preconfigurado en el sistema.
        No requiere crear uno nuevo.</p></div>

        <div class="nota"><p><strong>Eliminar vs. Desactivar:</strong> Si el cliente tiene facturas, CxC,
        proformas o prefacturas registradas, no se puede eliminar. El sistema lo desactiva (estado = false)
        en su lugar para preservar el historial.</p></div>

        <!-- ── PROVEEDORES ── -->
        <h2>2. PROVEEDORES</h2>
        <p>Ruta: <span class="ruta">Personas → Proveedores</span>
        (equivalente a <span class="ruta">Compras → Proveedores</span> — misma BD)</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Tipo</strong></td><td>nacional / internacional</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Tipo ID</strong></td><td>RUC, cédula, pasaporte</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Identificación</strong></td><td>Número del documento</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Razón Social</strong></td><td>Nombre legal</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>País</strong></td><td>Auto = ECUADOR si tipo=nacional</td><td>No</td></tr>
            <tr><td><strong>Divisa</strong></td><td>Moneda (para importaciones: USD, EUR…)</td><td>No</td></tr>
            <tr><td><strong>Días crédito</strong></td><td>Plazo habitual de pago (pre-llena al crear compra)</td><td>No</td></tr>
        </table>

        <div class="nota"><p><strong>Proveedores internacionales:</strong> Para importaciones, el proveedor
        debe ser de tipo <em>internacional</em> para aparecer en el selector de Compras → Importaciones.</p></div>

        <!-- ── TRANSPORTISTAS ── -->
        <h2>3. TRANSPORTISTAS</h2>
        <p>Ruta: <span class="ruta">Personas → Transportistas</span></p>
        <p>Requerido para emitir Guías de Remisión (documento SRI obligatorio al transportar mercadería).</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Razón Social</strong></td><td>Nombre del transportista o empresa</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Identificación</strong></td><td>CI o RUC (único en el sistema)</td><td>No</td></tr>
            <tr><td><strong>Placa</strong></td><td>Placa del vehículo — requerida por el SRI en la guía</td><td>No*</td></tr>
            <tr><td><strong>Teléfono</strong></td><td>Solo dígitos, guiones y paréntesis (7–20 caracteres)</td><td>No</td></tr>
            <tr><td><strong>Email</strong></td><td>Correo de contacto</td><td>No</td></tr>
        </table>

        <div class="nota"><p>* La placa es técnicamente opcional en el formulario, pero el SRI la exige
        para autorizar la guía de remisión electrónica. Se recomienda siempre registrarla.</p></div>

        <div class="nota"><p><strong>Eliminar vs. Desactivar:</strong> Si el transportista tiene guías de
        remisión registradas, el sistema lo desactiva en lugar de eliminarlo.</p></div>
    </div>'; }

    /* ═══════════════════════════════════════════════════════════════
     * VENTAS
     * ══════════════════════════════════════════════════════════════ */
    private function htmlVentas(): string { return '
    <div class="page">

        <div class="modulo-titulo">
            <div class="nombre">VENTAS</div>
            <div class="sub">Proformas · Prefacturas · Facturas · NC · Retenciones · CxC</div>
            <div class="linea"></div>
        </div>

        <div class="intro"><p><strong>¿Qué hace este módulo?</strong> Gestiona el ciclo completo de ventas:
        desde la cotización (proforma) hasta la factura electrónica SRI, notas de crédito, retenciones
        recibidas, guías de remisión y cobro de cuentas por cobrar. Al emitir una factura se genera
        automáticamente el asiento contable y la CxC si la venta es a crédito.</p></div>

        <!-- ── PREREQUISITOS ── -->
        <h2>PREREQUISITOS</h2>
        <table class="rel">
            <tr><th>Necesitas tener...</th><th>Dónde crearlo</th><th>¿Para qué?</th></tr>
            <tr><td>Cliente registrado</td><td>Personas → Clientes</td><td>Facturas, Proformas, NC</td></tr>
            <tr><td>Productos registrados (Dev 1)</td><td>Inventario → Productos</td><td>Líneas de detalle</td></tr>
            <tr><td>Parámetros contables configurados</td><td>Contabilidad → Parámetros</td><td>Asiento automático</td></tr>
            <tr><td>Ejercicio contable abierto</td><td>Contabilidad → Ejercicios</td><td>Que el asiento se genere</td></tr>
            <tr><td>Transportista registrado</td><td>Personas → Transportistas</td><td>Guías de Remisión</td></tr>
        </table>

        <!-- ── FLUJO ── -->
        <h2>FLUJO DE VENTA (DE MENOR A MAYOR COMPROMISO)</h2>
        <table class="rel">
            <tr><th>Documento</th><th>¿Qué es?</th><th>¿Genera asiento?</th><th>¿Afecta stock?</th></tr>
            <tr><td><strong>Proforma</strong></td><td>Cotización al cliente — no oficial</td><td>No</td><td>No</td></tr>
            <tr><td><strong>Prefactura</strong></td><td>Documento interno con posible aprobación</td><td>No</td><td>No</td></tr>
            <tr><td><strong>Factura</strong></td><td>Documento oficial SRI</td><td>Sí</td><td>No *</td></tr>
            <tr><td><strong>Nota Crédito</strong></td><td>Reverso total/parcial de factura</td><td>Sí (reverso)</td><td>No *</td></tr>
        </table>
        <div class="info"><p>* Las ventas NO decrementan el stock automáticamente (modelo diferido).
        Los traslados sí validan stock disponible.</p></div>

        <!-- ── PROFORMAS ── -->
        <h2>1. PROFORMAS (Cotizaciones)</h2>
        <p>Ruta: <span class="ruta">Ventas → Proformas → + Nueva Proforma</span></p>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Seleccionar cliente</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Agregar productos — el precio se carga automáticamente desde el catálogo</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Guardar e imprimir / compartir con el cliente</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Cuando el cliente acepta: "Convertir a Factura" o "Convertir a Prefactura"</div>
            <div class="detalle">Traslada todos los datos automáticamente al nuevo documento.</div>
        </div></div>

        <!-- ── PREFACTURAS ── -->
        <h2>2. PREFACTURAS</h2>
        <p>Ruta: <span class="ruta">Ventas → Prefacturas → + Nueva Prefactura</span></p>
        <p>Documento interno de venta que puede requerir aprobación antes de facturar
        (cuando el descuento supera el límite del vendedor).</p>

        <table class="rel">
            <tr><th>Estado</th><th>Descripción</th><th>Siguiente paso</th></tr>
            <tr><td><span class="badge b-am">pendiente</span></td><td>Creada, esperando revisión</td><td>Aprobar o rechazar</td></tr>
            <tr><td><span class="badge b-vd">aprobada</span></td><td>Lista para facturar</td><td>Convertir a Factura</td></tr>
            <tr><td><span class="badge b-rj">rechazada</span></td><td>Supervisor la rechazó</td><td>Editar y reenviar</td></tr>
            <tr><td><span class="badge b-gr">facturada</span></td><td>Ya se emitió la factura</td><td>—</td></tr>
        </table>

        <h3>Flujo de aprobación especial</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Vendedor crea prefactura con descuento mayor al límite de su perfil</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">El sistema la marca como "requiere aprobación"</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Supervisor va a Ventas → Aprobaciones e ingresa su PIN para aprobar</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Vendedor puede emitir la factura desde la prefactura aprobada</div>
        </div></div>

        <!-- ── FACTURAS ── -->
        <h2>3. FACTURAS</h2>
        <p>Ruta: <span class="ruta">Ventas → Facturas → + Nueva Factura</span></p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Cliente</strong></td><td>Buscar por RUC/nombre. Usar "CONSUMIDOR FINAL" si no hay datos.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Fecha</strong></td><td>Por defecto hoy</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Detalles</strong></td><td>Mínimo 1 línea: producto + cantidad + precio + %descuento</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Formas de pago</strong></td><td>Mínimo 1. La suma debe igualar el total (tolerancia ±$0.01).</td><td><span class="req">Sí</span></td></tr>
        </table>

        <h3>Formas de pago disponibles</h3>
        <div class="estado-lista">
            <span class="badge b-vd">efectivo</span>
            <span class="badge b-az">transferencia</span>
            <span class="badge b-mo">tarjeta</span>
            <span class="badge b-am">cheque</span>
            <span class="badge b-gr">credito</span>
        </div>
        <div class="nota"><p><strong>Pago a crédito:</strong> Si alguna forma de pago es "credito",
        se debe indicar el plazo (días). El sistema crea automáticamente la CxC con fecha de vencimiento.</p></div>

        <h3>Al emitir la factura el sistema hace automáticamente:</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Asigna número secuencial SRI</div>
            <div class="detalle">Formato: {establecimiento}-{punto emisión}-{secuencial 9 dígitos}.
            Ej: 001-001-000000001. El secuencial se configura en Configuración → Empresa.</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Genera asiento contable</div>
            <div class="detalle">Débito: Clientes (o Caja/Banco si es contado). Crédito: Ventas + IVA por pagar.</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Crea CxC (si hay forma de pago = crédito)</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Registra aprobación especial (si hubo descuento sobre el límite)</div>
        </div></div>

        <h3>Estados de la factura</h3>
        <table class="rel">
            <tr><th>Estado</th><th>Estado SRI</th><th>Descripción</th></tr>
            <tr><td><span class="badge b-vd">activa</span></td><td><span class="badge b-am">pendiente</span></td><td>Emitida localmente, pendiente de enviar al SRI*</td></tr>
            <tr><td><span class="badge b-vd">activa</span></td><td><span class="badge b-vd">autorizada</span></td><td>Autorizada por el SRI (cuando se integre el webservice)</td></tr>
            <tr><td><span class="badge b-rj">anulada</span></td><td><span class="badge b-rj">anulada</span></td><td>Solo super_admin, solo el mismo día antes de las 23:59</td></tr>
        </table>
        <div class="info"><p>* La integración automática con el webservice del SRI (firma electrónica + XML)
        está pendiente de desarrollo. Actualmente el estado_sri queda en "pendiente".</p></div>

        <!-- ── NC ── -->
        <h2>4. NOTAS DE CRÉDITO</h2>
        <p>Ruta: <span class="ruta">Ventas → Notas de Crédito → + Nueva NC</span></p>
        <p>Para reversar total o parcialmente una factura emitida (devoluciones, errores de precio).</p>

        <div class="alerta"><p><strong>Restricción:</strong> Solo se puede hacer nota de crédito sobre
        facturas con estado = <em>activa</em> y estado_sri = <em>autorizada</em>. Facturas en estado
        "pendiente SRI" no pueden tener NC.</p></div>

        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Seleccionar la factura de referencia</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Ingresar motivo de la nota de crédito</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Seleccionar líneas a reversar y cantidades</div>
            <div class="detalle">Solo las líneas del FacturaDetalle original. El sistema calcula el
            monto reverso proporcional (cantidad_nc / cantidad_original × monto_original).</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Emitir</div>
            <div class="detalle">Genera asiento de reverso (débito a Ventas, crédito a Clientes) y
            disminuye el saldo de la CxC si existía.</div>
        </div></div>

        <!-- ── RETENCIONES ── -->
        <h2>5. RETENCIONES RECIBIDAS</h2>
        <p>Ruta: <span class="ruta">Ventas → Retenciones</span></p>
        <p>Cuando un cliente agente de retención nos entrega un comprobante de retención.</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th></tr>
            <tr><td><strong>Factura retenida</strong></td><td>La factura sobre la que se aplica la retención</td></tr>
            <tr><td><strong>N° comprobante retención</strong></td><td>Número del documento que da el cliente</td></tr>
            <tr><td><strong>Fecha</strong></td><td>Fecha del comprobante de retención</td></tr>
            <tr><td><strong>% Retención IVA</strong></td><td>Porcentaje retenido del IVA (0%, 30%, 70%, 100%)</td></tr>
            <tr><td><strong>% Retención IR</strong></td><td>Porcentaje de retención en la fuente (según tabla SRI)</td></tr>
        </table>

        <!-- ── GUIAS ── -->
        <h2>6. GUÍAS DE REMISIÓN</h2>
        <p>Ruta: <span class="ruta">Ventas → Guías de Remisión</span></p>
        <p>Documento SRI obligatorio para transportar mercadería. El transportista debe estar registrado.</p>

        <table class="campos">
            <tr><th>Campo</th><th>Descripción</th><th>Req.</th></tr>
            <tr><td><strong>Transportista</strong></td><td>Persona o empresa que transporta (Personas → Transportistas)</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Dirección partida</strong></td><td>Desde dónde sale la mercadería</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Dirección destino</strong></td><td>A dónde llega la mercadería</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Fecha inicio transporte</strong></td><td>Cuándo empieza el traslado</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Fecha fin transporte</strong></td><td>Máximo 90 días desde inicio. Debe ser ≥ inicio.</td><td><span class="req">Sí</span></td></tr>
            <tr><td><strong>Factura referencia</strong></td><td>Factura que ampara el envío (opcional)</td><td>No</td></tr>
            <tr><td><strong>Detalles</strong></td><td>Descripción + Cantidad + Unidad de la mercadería</td><td><span class="req">Sí</span></td></tr>
        </table>

        <!-- ── CxC ── -->
        <h2>7. CUENTAS POR COBRAR (CxC)</h2>
        <p>Ruta: <span class="ruta">Ventas → CxC</span></p>
        <p>Se crean automáticamente al emitir una factura con forma de pago "crédito". No se crean manualmente.</p>

        <h3>Registrar cobro</h3>
        <div class="paso"><div class="num">1</div><div class="contenido">
            <div class="titulo">Buscar la CxC por cliente o número de factura</div>
        </div></div>
        <div class="paso"><div class="num">2</div><div class="contenido">
            <div class="titulo">Clic en "Cobrar"</div>
        </div></div>
        <div class="paso"><div class="num">3</div><div class="contenido">
            <div class="titulo">Registrar el pago recibido</div>
            <div class="detalle">Fecha, banco/caja destino, forma de pago, monto. Puede ser pago parcial.</div>
        </div></div>
        <div class="paso"><div class="num">4</div><div class="contenido">
            <div class="titulo">Guardar</div>
            <div class="detalle">El saldo de la CxC se decrementa. Si saldo ≤ $0.001: estado = pagada.
            Se genera asiento de cobro. El saldo del banco/caja aumenta.</div>
        </div></div>

        <div class="estado-lista">
            <span class="badge b-am">pendiente</span>
            <span class="badge b-az">parcial</span>
            <span class="badge b-vd">pagada</span>
            <span class="badge b-rj">vencida</span>
        </div>

        <div class="tip"><p>En el detalle de una CxC (clic en ver) puedes ver el historial de pagos parciales,
        retenciones aplicadas y el saldo pendiente actual por fecha.</p></div>
    </div>'; }
}
