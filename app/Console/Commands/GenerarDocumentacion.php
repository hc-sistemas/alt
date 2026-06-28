<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

class GenerarDocumentacion extends Command
{
    protected $signature   = 'altamira:generar-docs';
    protected $description = 'Genera PDFs de manual de usuario por módulo en carpeta usos/';

    private string $css = '
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:DejaVu Sans,sans-serif; font-size:10.5px; color:#1a1a2e; background:#fff; }
        .page { padding:30px 38px; }

        .titulo-modulo { text-align:center; padding:28px 0 20px; }
        .titulo-modulo .nombre { font-size:32px; font-weight:900; color:#1F2D3D; letter-spacing:2px; }
        .titulo-modulo .sub { color:#F59E0B; font-size:11px; margin-top:5px; }
        .titulo-modulo .linea { width:60px; height:3px; background:#F59E0B; margin:13px auto 0; }

        .intro { background:#f0f9ff; border:1px solid #bae6fd; border-radius:7px;
                 padding:12px 15px; margin-bottom:18px; }
        .intro p { color:#0c4a6e; font-size:10.5px; line-height:1.7; }

        h2 { font-size:15px; font-weight:700; color:#1F2D3D; border-left:4px solid #F59E0B;
             padding-left:10px; margin:24px 0 10px; }
        h3 { font-size:11.5px; font-weight:700; color:#2C5F8A; margin:15px 0 6px; }
        p  { font-size:10.5px; color:#374151; line-height:1.7; margin-bottom:6px; }

        .paso { display:flex; align-items:flex-start; margin-bottom:9px; }
        .num  { min-width:24px; height:24px; background:#F59E0B; color:#fff; border-radius:50%;
                font-size:10px; font-weight:700; display:flex; align-items:center;
                justify-content:center; margin-right:10px; margin-top:1px; flex-shrink:0; }
        .paso-cont .titulo  { font-weight:700; font-size:10.5px; color:#1F2D3D; }
        .paso-cont .detalle { font-size:10px; color:#6b7280; line-height:1.6; margin-top:2px; }

        table.t { width:100%; border-collapse:collapse; margin:8px 0 15px; font-size:10px; }
        table.t th { background:#1F2D3D; color:#F59E0B; padding:6px 10px; text-align:left;
                     font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; }
        table.t td { padding:5px 10px; border-bottom:1px solid #e5e7eb; color:#374151;
                     vertical-align:top; line-height:1.6; }
        table.t tr:nth-child(even) td { background:#f9fafb; }
        .req { color:#ef4444; font-weight:700; }

        .box { border-radius:0 5px 5px 0; padding:9px 13px; margin:10px 0 15px; }
        .box p { font-size:10px; line-height:1.65; }
        .nota   { background:#fffbeb; border-left:4px solid #F59E0B; }
        .nota p { color:#92400e; }
        .alerta   { background:#fef2f2; border-left:4px solid #ef4444; }
        .alerta p { color:#991b1b; }
        .tip   { background:#f0fdf4; border-left:4px solid #22c55e; }
        .tip p { color:#166534; }
        .info   { background:#f5f3ff; border-left:4px solid #8b5cf6; }
        .info p { color:#5b21b6; }

        .badge { display:inline-block; padding:2px 8px; border-radius:20px;
                 font-size:8.5px; font-weight:700; margin:2px 3px 2px 0; }
        .am { background:#fef3c7; color:#92400e; }
        .az { background:#dbeafe; color:#1e40af; }
        .vd { background:#dcfce7; color:#166534; }
        .rj { background:#fee2e2; color:#991b1b; }
        .gr { background:#f3f4f6; color:#4b5563; }
        .mo { background:#ede9fe; color:#6d28d9; }

        .cards { display:table; width:100%; margin:8px 0 15px; }
        .card  { display:table-cell; width:50%; vertical-align:top; padding-right:8px; }
        .card:last-child { padding-right:0; padding-left:8px; }
        .card-inner { border:1px solid #e5e7eb; border-radius:6px; padding:10px 12px;
                      background:#fafafa; }
        .card-titulo { font-size:10.5px; font-weight:700; color:#1F2D3D; margin-bottom:4px; }
        .card-cuerpo { font-size:10px; color:#6b7280; line-height:1.6; }

        .flujo { margin:10px 0 15px; }
        .flujo-fila { display:table; width:100%; border-collapse:collapse; }
        .flujo-paso { display:table-cell; background:#1F2D3D; color:#fff; border-radius:5px;
                      padding:7px 6px; font-size:9px; font-weight:700; text-align:center;
                      vertical-align:middle; width:16%; }
        .flujo-paso .fsub { font-size:8px; color:#9ca3af; font-weight:400; margin-top:2px; }
        .flujo-arrow { display:table-cell; color:#F59E0B; font-size:18px; font-weight:900;
                       text-align:center; vertical-align:middle; width:4%; }

        .ruta { font-family:DejaVu Sans Mono,monospace; background:#f3f4f6; padding:1px 6px;
                border-radius:3px; font-size:9.5px; color:#1F2D3D; }

        .ejemplo { background:#f8fafc; border:1px solid #cbd5e1; border-radius:6px;
                   padding:10px 13px; margin:10px 0 15px; }
        .ejemplo .ej-titulo { font-size:10px; font-weight:700; color:#2C5F8A;
                              margin-bottom:6px; text-transform:uppercase; letter-spacing:.4px; }
        .ejemplo p { font-size:10px; color:#374151; margin-bottom:3px; }

        .rel-box { border:1px solid #e5e7eb; border-radius:6px; padding:12px 14px;
                   margin:8px 0 15px; background:#fcfcfc; }
        .rel-linea { font-size:10px; color:#374151; padding:4px 0;
                     border-bottom:1px dashed #e5e7eb; line-height:1.6; }
        .rel-linea:last-child { border-bottom:none; }
        .rel-icono { color:#F59E0B; font-weight:700; margin-right:6px; }

        ul.lista { margin:5px 0 12px 18px; }
        ul.lista li { font-size:10.5px; color:#374151; line-height:1.7; }

        .header-banda { background:#1F2D3D; color:#F59E0B; padding:8px 38px;
                        font-size:9px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; }
        .footer-banda { color:#9ca3af; font-size:8.5px; text-align:center;
                        padding:10px; border-top:1px solid #e5e7eb; margin-top:20px; }
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
            Pdf::loadHTML($this->wrap($m['html'], $m['nombre']))
               ->setPaper('a4', 'portrait')
               ->save(base_path("usos/{$key}.pdf"));
            $this->info("  -> usos/{$key}.pdf");
        }

        $this->newLine();
        $this->info('5 PDFs generados en carpeta usos/');
        return self::SUCCESS;
    }

    private function wrap(string $body, string $modulo): string
    {
        return "<!DOCTYPE html><html><head><meta charset='UTF-8'>
        <style>{$this->css}</style></head><body>
        <div class='header-banda'>Altamira Light &amp; Sound &middot; ERP &middot; Modulo {$modulo} &middot; Manual de Usuario</div>
        {$body}
        <div class='footer-banda'>Manual de usuario &mdash; ERP Altamira &middot; " . now()->format('d/m/Y') . " &middot; Documento de uso interno</div>
        </body></html>";
    }

    /* ═══════════════════════════════════════════════════════
       CONTABILIDAD
    ════════════════════════════════════════════════════════ */
    private function htmlContabilidad(): string
    {
        return '
    <div class="page">
      <div class="titulo-modulo">
        <div class="nombre">CONTABILIDAD</div>
        <div class="sub">Plan de Cuentas &middot; Ejercicios por Mes &middot; Asientos &middot; Reportes</div>
        <div class="linea"></div>
      </div>

      <div class="intro"><p>
        <strong>Para que sirve:</strong> Es la base financiera del ERP. Todos los demas modulos
        (Ventas, Compras, Bancos) generan asientos contables aqui de forma automatica.
        El contador tambien puede ingresar asientos manuales de ajuste.
        Antes de usar el sistema por primera vez hay que configurar los
        <strong>Parametros Contables</strong> y abrir el primer <strong>Ejercicio</strong> del mes.
      </p></div>

      <h2>COMO FUNCIONA: VISION GENERAL</h2>
      <div class="rel-box">
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Ventas</strong> emite una factura &rarr; asiento automatico: Clientes (DEBE) / Ventas + IVA (HABER)</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Compras</strong> activa una factura &rarr; asiento: Inventario + IVA Compras (DEBE) / Proveedores (HABER)</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Bancos</strong> registra un movimiento &rarr; asiento: Banco / Contrapartida</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Pago de CxP o CxC</strong> &rarr; asiento de cancelacion + movimiento bancario</div>
        <div class="rel-linea"><span class="rel-icono">*</span>
          <strong>Asientos manuales</strong> &rarr; solo Contador, Admin o Super Admin</div>
      </div>

      <div class="alerta box"><p><strong>Requisito critico:</strong> Debe existir un ejercicio abierto que
      cubra la fecha del documento. Si no hay ejercicio abierto, el documento se guarda pero el asiento
      contable NO se genera. Hay que abrir el ejercicio de cada mes antes de operar.</p></div>

      <h2>PASO 0 &mdash; PARAMETROS CONTABLES (una sola vez)</h2>
      <p>Ruta: <span class="ruta">Contabilidad &rarr; Parametros Contables</span></p>
      <p>Le dice al sistema que cuenta usar para cada tipo de movimiento. Sin esto los asientos
      automaticos de Ventas, Compras y Bancos no funcionan correctamente.</p>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "Auto-configurar"</div>
        <div class="detalle">El sistema busca las cuentas mas comunes del plan y las asigna
        automaticamente. Muestra cuales encontro y cuales no.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Asignar manualmente las que no encontro</div>
        <div class="detalle">Clic en el selector de cada parametro sin cuenta y buscar en el plan.</div>
      </div></div>

      <table class="t">
        <tr><th>Parametro</th><th>Que cuenta representa</th><th>Lo usa...</th></tr>
        <tr><td>cta_clientes_locales</td><td>Lo que nos deben los clientes (CxC)</td><td>Ventas</td></tr>
        <tr><td>cta_ventas_locales</td><td>Ingresos por ventas</td><td>Ventas</td></tr>
        <tr><td>cta_iva_ventas</td><td>IVA cobrado que se paga al SRI</td><td>Ventas</td></tr>
        <tr><td>cta_proveedores_locales</td><td>Lo que debemos a proveedores (CxP)</td><td>Compras</td></tr>
        <tr><td>cta_iva_compras</td><td>IVA pagado en compras (credito tributario)</td><td>Compras</td></tr>
        <tr><td>cta_inventario_mercaderia</td><td>Valor del inventario en libros</td><td>Compras / Inventario</td></tr>
        <tr><td>cta_bancos_locales</td><td>Cuentas bancarias de la empresa</td><td>Bancos / Pagos</td></tr>
        <tr><td>cta_retencion_ir_cobrada</td><td>Retenciones en la fuente recibidas de clientes</td><td>Ventas</td></tr>
      </table>

      <h2>1. PLAN DE CUENTAS</h2>
      <p>Ruta: <span class="ruta">Contabilidad &rarr; Plan de Cuentas</span></p>
      <p>Catalogo de todas las cuentas contables de la empresa. Tiene hasta 4 niveles de jerarquia
      (ejemplo: 1 &rarr; 1.1 &rarr; 1.1.01 &rarr; 1.1.01.01). Solo las cuentas del ultimo nivel
      (cuentas hoja) pueden recibir asientos contables.</p>

      <table class="t">
        <tr><th>Campo</th><th>Que es</th><th>Req.</th></tr>
        <tr><td><strong>Codigo</strong></td><td>Numero jerarquico. Ej: 1.1.01.01. Debe seguir la estructura del plan existente.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Nombre</strong></td><td>Descripcion de la cuenta</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Tipo</strong></td><td>activo / pasivo / patrimonio / ingreso / gasto</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Cuenta padre</strong></td><td>La cuenta de nivel superior a la que pertenece</td><td>No</td></tr>
        <tr><td><strong>Permite asientos</strong></td><td>Marcar "Si" solo en cuentas hoja (sin cuentas hijo). Las de grupo: "No".</td><td><span class="req">Si</span></td></tr>
      </table>

      <div class="nota box"><p>No se puede desactivar una cuenta que ya tiene asientos registrados.
      Si necesitas reemplazarla, crear una cuenta nueva.</p></div>

      <h2>2. EJERCICIOS CONTABLES (UN MES A LA VEZ)</h2>
      <p>Ruta: <span class="ruta">Contabilidad &rarr; Ejercicios</span></p>
      <p>Un ejercicio es <strong>un mes del ano</strong>, no el ano completo.
      Solo puede haber <strong>un ejercicio abierto</strong> a la vez por empresa.
      El mes debe estar abierto para que los documentos de ese periodo generen asientos.</p>

      <h3>Abrir el mes</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "+ Nuevo Ejercicio"</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Elegir Ano y Mes &mdash; ejemplo: 2026 / Junio</div>
        <div class="detalle">No se puede crear uno nuevo si ya hay un ejercicio abierto.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Guardar &rarr; queda en estado "abierto"</div>
      </div></div>

      <h3>Cerrar el mes</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "Cerrar Ejercicio"</div>
        <div class="detalle">El sistema verifica que todos los asientos del mes esten cuadrados
        (suma DEBE = suma HABER). Si hay asientos desbalanceados, bloquea el cierre.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Confirmar &rarr; queda en "cerrado" (solo lectura)</div>
        <div class="detalle">Solo Super Admin puede reabrir un ejercicio cerrado.</div>
      </div></div>

      <h2>3. ASIENTOS CONTABLES</h2>
      <p>Ruta: <span class="ruta">Contabilidad &rarr; Asientos</span></p>
      <p>Registro doble de cada operacion: lo que entra (DEBE) y lo que sale (HABER).
      Regla de oro: <strong>total DEBE = total HABER siempre</strong>.
      El numero se asigna automaticamente: AS-2026-0001 (reinicia cada ano).</p>

      <div class="cards">
        <div class="card"><div class="card-inner">
          <div class="card-titulo">Automaticos</div>
          <div class="card-cuerpo">Los crea el sistema al guardar facturas, compras activas,
          pagos y movimientos bancarios. No se pueden editar manualmente.</div>
        </div></div>
        <div class="card"><div class="card-inner">
          <div class="card-titulo">Manuales</div>
          <div class="card-cuerpo">Para ajustes o correcciones que el sistema no genera solo.
          Solo pueden crearlos: Super Admin, Admin y Contador.</div>
        </div></div>
      </div>

      <h3>Crear un asiento manual</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "+ Nuevo Asiento" &mdash; ingresar Concepto y Fecha</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Agregar las partidas (minimo 2)</div>
        <div class="detalle">Cada partida: elegir una cuenta + escribir el monto en DEBE
        o en HABER (nunca en los dos al mismo tiempo).</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Guardar</div>
        <div class="detalle">El sistema valida que suma de DEBEs = suma de HABERs.
        Si no cuadra, no guarda.</div>
      </div></div>

      <div class="ejemplo">
        <div class="ej-titulo">Ejemplo: asiento manual de comision bancaria</div>
        <p>Concepto: "Comision bancaria Pichincha junio 2026"</p>
        <p>Partida 1 &rarr; Cuenta "Gastos Financieros" &mdash; DEBE $12.50</p>
        <p>Partida 2 &rarr; Cuenta "Banco Pichincha" &mdash; HABER $12.50</p>
        <p>DEBE $12.50 = HABER $12.50 &rarr; el sistema guarda el asiento.</p>
      </div>

      <div class="nota box"><p><strong>Anular un asiento:</strong> Clic en "Anular" en el detalle.
      El sistema crea automaticamente un asiento reverso (mismas cuentas con DEBE y HABER invertidos)
      y marca el original como anulado. Solo Super Admin puede anular, y solo dentro de las 24 horas
      siguientes a su creacion.</p></div>

      <h2>4. REPORTES</h2>
      <p>Ruta: <span class="ruta">Contabilidad &rarr; Reportes</span></p>
      <table class="t">
        <tr><th>Reporte</th><th>Que muestra</th><th>Filtros</th></tr>
        <tr><td><strong>Libro Diario</strong></td><td>Todos los asientos ordenados por fecha con sus partidas</td><td>Rango de fechas</td></tr>
        <tr><td><strong>Mayor General</strong></td><td>Movimientos de una cuenta con saldo acumulado corriente</td><td>Cuenta + rango de fechas</td></tr>
      </table>
    </div>';
    }

    /* ═══════════════════════════════════════════════════════
       COMPRAS
    ════════════════════════════════════════════════════════ */
    private function htmlCompras(): string
    {
        return '
    <div class="page">
      <div class="titulo-modulo">
        <div class="nombre">COMPRAS</div>
        <div class="sub">Proveedores &middot; Facturas &middot; Etiquetas &middot; CxP &middot; Anticipos &middot; Importaciones</div>
        <div class="linea"></div>
      </div>

      <div class="intro"><p>
        <strong>Para que sirve:</strong> Gestiona todo lo que la empresa compra: facturas de proveedores,
        control de lo que se les debe (CxP), anticipos enviados al exterior e importaciones completas
        con distribucion de costos. Al <strong>activar</strong> una compra el stock entra automaticamente
        al inventario y se genera el asiento contable.
      </p></div>

      <h2>FLUJO COMPLETO DE UNA COMPRA</h2>
      <div class="flujo">
        <div class="flujo-fila">
          <div class="flujo-paso">1. Proveedor<div class="fsub">registrarlo</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">2. Factura<div class="fsub">ingresar datos</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">3. Etiquetas<div class="fsub">generar PDF</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">4. ACTIVAR<div class="fsub">confirmar compra</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">5. Pagar CxP<div class="fsub">al vencer</div></div>
        </div>
      </div>

      <div class="alerta box"><p><strong>Muy importante:</strong> Una compra en estado
      <span class="badge am">pendiente</span> NO ingresa stock, NO crea CxP y NO genera asiento contable.
      Solo al <strong>Activarla</strong> ocurren todos esos efectos.</p></div>

      <h2>1. PROVEEDORES</h2>
      <p>Ruta: <span class="ruta">Personas &rarr; Proveedores</span> &mdash;
      tambien desde <span class="ruta">Compras &rarr; Proveedores</span> (misma pantalla)</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Tipo</strong></td><td>nacional (Ecuador) o internacional (exterior).
            Los internacionales aparecen en Importaciones.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Tipo ID</strong></td><td>RUC, cedula o pasaporte</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Identificacion</strong></td><td>Numero del documento</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Razon Social</strong></td><td>Nombre legal completo</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Pais / Divisa</strong></td><td>Para internacionales: pais de origen y moneda (USD, EUR...)</td><td>No</td></tr>
        <tr><td><strong>Dias credito</strong></td><td>Plazo habitual de pago. Se pre-llena al crear una compra.</td><td>No</td></tr>
      </table>

      <h2>2. INGRESAR FACTURA DE COMPRA</h2>
      <p>Ruta: <span class="ruta">Compras &rarr; Compras &rarr; + Nueva Compra</span></p>
      <p>Tipos de documento que acepta el sistema:</p>
      <div style="margin:6px 0 12px;">
        <span class="badge az">FAC &mdash; Factura</span>
        <span class="badge am">LIQ &mdash; Liquidacion de compras</span>
        <span class="badge gr">TIK &mdash; Tiquete de maquina registradora</span>
        <span class="badge mo">CON &mdash; Contrato de servicio</span>
        <span class="badge vd">EXT &mdash; Factura del exterior</span>
      </div>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Proveedor</strong></td><td>Buscar por RUC o nombre. Debe existir en el sistema.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Tipo documento</strong></td><td>FAC / LIQ / TIK / CON / EXT</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>N. Documento</strong></td><td>El numero de la factura fisica del proveedor. Ej: 001-001-000012345</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha emision</strong></td><td>La fecha que dice la factura del proveedor</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Dias credito</strong></td><td>0 = contado. Mayor a 0 = calcula fecha de vencimiento automaticamente.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Bodega destino</strong></td><td>A que bodega ingresara el stock cuando se active la compra</td><td>No</td></tr>
        <tr><td><strong>Importacion</strong></td><td>Vincular a una importacion si esta compra viene del exterior</td><td>No</td></tr>
        <tr><td><strong>Gasto no deducible</strong></td><td>Marcar si es un gasto que NO va al inventario (flete, seguro, aduana, etc.)</td><td>No</td></tr>
        <tr><td><strong>Detalles (lineas)</strong></td><td>Al menos 1 linea: Descripcion + Cantidad + Precio + % IVA</td><td><span class="req">Si</span></td></tr>
      </table>

      <div class="nota box"><p>El sistema no permite ingresar dos documentos con el mismo numero
      para el mismo proveedor. Verificar bien el numero antes de guardar.</p></div>

      <h2>3. ETIQUETAS DE CODIGO DE BARRAS</h2>
      <p>Ruta: <span class="ruta">Compras &rarr; Compras &rarr; (abrir factura) &rarr; Etiquetas</span></p>
      <p>Al ingresar una compra, el sistema puede generar etiquetas con <strong>codigo de barras</strong>
      para pegar en cada producto recibido. Esto permite escanear en bodega y confirmar que cada unidad
      llego fisicamente.</p>

      <h3>Que es una etiqueta</h3>
      <p>Cada etiqueta tiene un codigo unico del tipo <strong>AMP-000023</strong> donde:</p>
      <ul class="lista">
        <li><strong>AMP</strong> = prefijo tomado del codigo del producto</li>
        <li><strong>000023</strong> = numero correlativo unico y secuencial en todo el sistema</li>
      </ul>
      <p>El sistema guarda el rango de correlativos de cada compra (desde &ndash; hasta) para
      validar el escaneo en la recepcion de bodega.</p>

      <h3>Generar las etiquetas (una sola vez por factura)</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Abrir el detalle de la factura &rarr; clic en "Generar Etiquetas"</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Seleccionar los productos a etiquetar y confirmar</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">El sistema descarga un PDF listo para imprimir</div>
        <div class="detalle">Formato 9 cm x 3.5 cm por etiqueta, compatible con impresoras de etiquetas.
        Contiene: codigo de barras CODE-128, codigo del producto y nombre.</div>
      </div></div>

      <div class="alerta box"><p><strong>Solo se generan una vez:</strong> Si necesitas reimprimirlas
      despues, usar los botones <strong>"Reimprimir todas"</strong> (todas las etiquetas de la compra)
      o <strong>"Reimprimir seleccion"</strong> (elegir codigos especificos uno por uno).</p></div>

      <div class="tip box"><p><strong>En la recepcion de bodega:</strong> El bodeguero escanea cada
      etiqueta en <span class="ruta">Inventario &rarr; Recepciones</span>. El sistema valida que el
      codigo escaneado pertenezca a esa compra. Si no pertenece, rechaza el escaneo.</p></div>

      <h2>4. ACTIVAR LA COMPRA</h2>
      <p>Ruta: <span class="ruta">Compras &rarr; Compras &rarr; (abrir factura) &rarr; Activar</span></p>
      <p>Al activar, el sistema hace <strong>cuatro cosas automaticamente</strong>:</p>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Ingresa el stock al inventario</div>
        <div class="detalle">Crea un movimiento tipo "entrada" y actualiza el saldo en la bodega.
        Tambien actualiza el costo promedio del producto.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Crea la Recepcion de Bodega (si tiene bodega asignada)</div>
        <div class="detalle">Genera el registro en Inventario &rarr; Recepciones para que el bodeguero
        confirme la llegada fisica.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Crea la CxP si la compra es a credito (dias credito mayor a 0)</div>
        <div class="detalle">Registra la deuda con el proveedor con fecha de vencimiento calculada.</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">Genera el asiento contable automaticamente</div>
        <div class="detalle">DEBE: Inventario Mercaderia + IVA Compras. HABER: Proveedores Locales.</div>
      </div></div>

      <h2>5. PAGAR LAS CUENTAS POR PAGAR (CxP)</h2>
      <p>Ruta: <span class="ruta">Compras &rarr; Cuentas por Pagar</span></p>
      <p>Las CxP se crean solas al activar una compra a credito. No se crean manualmente.</p>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Buscar la CxP por proveedor, estado o fecha de vencimiento</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Clic en "Pagar" y llenar: fecha, banco/caja, forma de pago y monto</div>
        <div class="detalle">El banco debe tener saldo suficiente. El pago puede ser parcial.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">El sistema procesa todo automaticamente</div>
        <div class="detalle">Reduce el saldo de la CxP. Si llega a $0: estado pagada.
        Si queda saldo: estado parcial. Crea MovimientoBancario (egreso) y asiento de pago.</div>
      </div></div>

      <div style="margin:6px 0 14px;">
        <span class="badge am">pendiente</span>
        <span class="badge az">parcial</span>
        <span class="badge vd">pagada</span>
        <span class="badge rj">vencida</span>
      </div>

      <h2>6. ANTICIPOS A PROVEEDORES</h2>
      <p>Ruta: <span class="ruta">Compras &rarr; Anticipos</span></p>
      <p>Pago al proveedor <strong>antes de recibir la factura</strong>. Muy comun en importaciones
      donde se transfiere dinero al proveedor exterior antes de que embarque la mercaderia.</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Proveedor</strong></td><td>A quien se le hace el pago</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha</strong></td><td>Fecha del desembolso</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Monto</strong></td><td>El banco debe tener saldo suficiente (minimo $0.01)</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Banco</strong></td><td>Cuenta desde donde sale el dinero</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>N. Transferencia</strong></td><td>Referencia bancaria de la transferencia</td><td>No</td></tr>
        <tr><td><strong>Importacion</strong></td><td>Vincular el anticipo a una importacion especifica para tener registro completo</td><td>No</td></tr>
      </table>

      <h3>Cruzar un anticipo con una CxP</h3>
      <p>Cuando llega la factura del proveedor y se activa la compra, el anticipo se puede aplicar
      para reducir la CxP generada:</p>
      <ul class="lista">
        <li>El saldo del anticipo se reduce en el monto cruzado</li>
        <li>El saldo de la CxP se reduce en el mismo monto</li>
        <li>Si el anticipo cubre todo: anticipo &rarr; <span class="badge vd">cruzado</span> y CxP &rarr; <span class="badge vd">pagada</span></li>
        <li>Si el anticipo cubre solo parte: la CxP queda en <span class="badge az">parcial</span></li>
      </ul>

      <h2>7. IMPORTACIONES &mdash; FLUJO COMPLETO</h2>
      <p>Ruta: <span class="ruta">Compras &rarr; Importaciones</span></p>
      <p>Gestiona el proceso completo de traer mercaderia del exterior: desde que sale del proveedor
      hasta que los costos adicionales quedan distribuidos en el costo de cada producto.</p>

      <h3>Como se conecta una importacion con el resto del sistema</h3>
      <div class="rel-box">
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Proveedor internacional</strong>: debe existir en Personas como tipo = internacional</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Anticipos</strong>: los pagos adelantados al proveedor se vinculan a esta importacion
          (campo "Importacion" en el anticipo)</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Facturas de compra de productos</strong>: al crear la factura de compra, elegir esta
          importacion en el campo "Importacion"</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Gastos adicionales</strong> (flete, seguro, aduana): se registran como facturas
          de compra separadas con "Gasto no deducible = Si" y vinculadas a esta misma importacion</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Al liquidar</strong>: el sistema distribuye los costos adicionales entre todos los
          productos de las compras vinculadas, actualizando el costo unitario en inventario</div>
      </div>

      <h3>Crear la importacion</h3>
      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Nombre</strong></td><td>Identificador. Ej: "Importacion Yamaha Jun-2026"</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Costo FOB</strong></td><td>Valor de la mercaderia en el puerto de origen en USD</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Proveedor</strong></td><td>Proveedor internacional del que viene la mercaderia</td><td>No</td></tr>
        <tr><td><strong>N. Invoice</strong></td><td>Numero de factura del proveedor exterior</td><td>No</td></tr>
        <tr><td><strong>Agente aduanero</strong></td><td>Empresa o persona que tramita la desaduanizacion</td><td>No</td></tr>
        <tr><td><strong>Pais embarque</strong></td><td>Pais de donde viene la mercaderia</td><td>No</td></tr>
        <tr><td><strong>Fecha partida / llegada</strong></td><td>Fecha de salida y de llegada a Ecuador (llegada debe ser mayor o igual a partida)</td><td>No</td></tr>
      </table>

      <h3>Estados de la importacion</h3>
      <table class="t">
        <tr><th>Estado</th><th>Que significa</th><th>Se puede editar</th></tr>
        <tr><td><span class="badge az">en transito</span></td><td>La mercaderia esta viajando desde el exterior</td><td>Si</td></tr>
        <tr><td><span class="badge am">en aduana</span></td><td>Llego a Ecuador, en proceso de desaduanizacion</td><td>Si</td></tr>
        <tr><td><span class="badge vd">liquidada</span></td><td>Costos distribuidos &mdash; proceso cerrado definitivamente</td><td>No</td></tr>
      </table>

      <h3>Liquidar la importacion (distribuir los costos adicionales)</h3>
      <p>Antes de liquidar: todas las facturas de compra de productos deben estar
      <strong>activadas</strong>. Los gastos (flete, seguro, aduana) deben estar registrados
      como compras con "gasto no deducible = Si" vinculadas a esta importacion.</p>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Abrir la importacion &rarr; clic en "Liquidar"</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Elegir el metodo de distribucion de costos</div>
        <div class="detalle">
          <strong>Por cantidad:</strong> los costos extra se reparten segun las unidades de cada compra.<br>
          <strong>Por precio (FOB):</strong> los costos se reparten segun el valor total de cada compra.
        </div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Ingresar los costos extra si no estan como compras vinculadas</div>
        <div class="detalle">Descripcion + monto de cada costo: flete, seguro, arancel, comisiones, otros.</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">Elegir fecha de liquidacion y confirmar</div>
      </div></div>
      <div class="paso"><div class="num">5</div><div class="paso-cont">
        <div class="titulo">El sistema distribuye automaticamente</div>
        <div class="detalle">Actualiza el costo promedio en inventario y el costo unitario de cada
        producto. La importacion queda en estado "liquidada" de forma definitiva.</div>
      </div></div>

      <div class="ejemplo">
        <div class="ej-titulo">Ejemplo de distribucion por cantidad</div>
        <p><strong>Costos extra totales:</strong> $200 (flete $150 + seguro $50)</p>
        <p><strong>Compra 1:</strong> 100 amplificadores a $8 c/u = 100 unidades</p>
        <p><strong>Compra 2:</strong> 50 microfonos a $4 c/u = 50 unidades &mdash; Total: 150 unidades</p>
        <p>&rarr; Amplificadores reciben: 100/150 = 66.7% de $200 = <strong>$133.40</strong></p>
        <p>&rarr; Nuevo costo amplificador: $8.00 + $1.334 = <strong>$9.334</strong></p>
        <p>&rarr; Microfonos reciben: 50/150 = 33.3% de $200 = <strong>$66.60</strong></p>
        <p>&rarr; Nuevo costo microfono: $4.00 + $1.332 = <strong>$5.332</strong></p>
      </div>
    </div>';
    }

    /* ═══════════════════════════════════════════════════════
       INVENTARIO
    ════════════════════════════════════════════════════════ */
    private function htmlInventario(): string
    {
        return '
    <div class="page">
      <div class="titulo-modulo">
        <div class="nombre">INVENTARIO</div>
        <div class="sub">Productos &middot; Kardex &middot; Saldos &middot; Traslados &middot; Recepciones &middot; Activos Fijos</div>
        <div class="linea"></div>
      </div>

      <div class="intro"><p>
        <strong>Para que sirve:</strong> Controla cuantos productos hay en cada bodega y a que costo.
        Todas las entradas de stock vienen automaticamente de <strong>Compras</strong> al activar
        una factura. Los traslados redistribuyen entre bodegas sin afectar el total.
        Los ajustes manuales corrigen diferencias de inventario fisico.
      </p></div>

      <div class="info box"><p>
        <strong>Dev 1 y Dev 2:</strong> El catalogo de productos (crear, configurar precio, categoria, marca)
        fue construido por Dev 1. Dev 2 construyo los movimientos: recepciones, traslados, kardex y
        activos fijos. Ambas partes se usan juntas.
      </p></div>

      <h2>1. CONFIGURACION PREVIA (una sola vez, Dev 1)</h2>
      <div class="cards">
        <div class="card"><div class="card-inner">
          <div class="card-titulo">Bodegas</div>
          <div class="card-cuerpo"><span class="ruta">Inventario &rarr; Config &rarr; Bodegas</span><br>
          Crear: Bodega Principal, Showroom, Taller, etc.
          Son los lugares fisicos donde vive el stock.</div>
        </div></div>
        <div class="card"><div class="card-inner">
          <div class="card-titulo">Marcas y Categorias</div>
          <div class="card-cuerpo"><span class="ruta">Inventario &rarr; Config &rarr; Marcas / Categorias</span><br>
          Yamaha, JBL, Shure... Las categorias pueden tener subcategorias
          (Instrumentos &rarr; Guitarras).</div>
        </div></div>
      </div>

      <h2>2. PRODUCTOS (Dev 1)</h2>
      <p>Ruta: <span class="ruta">Inventario &rarr; Productos</span></p>
      <p>Catalogo de lo que la empresa vende y compra. Cada producto tiene un codigo unico (SKU).
      Al crear un producto el stock empieza en <strong>cero</strong>.
      Para dar stock inicial usar un Ajuste de Inventario.</p>

      <table class="t">
        <tr><th>Campo</th><th>Que es</th><th>Req.</th></tr>
        <tr><td><strong>Codigo (SKU)</strong></td><td>Unico por empresa. Ej: YAM-P125.
            El prefijo se usa en las etiquetas de codigo de barras.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Nombre</strong></td><td>Descripcion completa del producto</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Tipo</strong></td><td>producto / servicio / repuesto / insumo</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Unidad</strong></td><td>UND, KG, L, PAR, etc.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>% IVA</strong></td><td>0% o 15% segun el tipo de producto</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>PVP</strong></td><td>Precio de venta al publico</td><td>No</td></tr>
        <tr><td><strong>Costo</strong></td><td>Costo de compra. Se actualiza solo con cada compra activada y al liquidar importaciones.</td><td>No</td></tr>
        <tr><td><strong>Stock minimo</strong></td><td>Genera alerta cuando el stock baja de este nivel</td><td>No</td></tr>
        <tr><td><strong>Requiere serie</strong></td><td>Para equipos que se rastrean por numero de serie individual (amplificadores, consolas, etc.)</td><td>No</td></tr>
        <tr><td><strong>Tiene ICE</strong></td><td>Impuesto a consumos especiales si aplica</td><td>No</td></tr>
      </table>

      <div class="tip box"><p><strong>Dar stock inicial:</strong> Ir a
      <span class="ruta">Inventario &rarr; Kardex &rarr; Ajuste</span> y crear un movimiento tipo
      <em>Ingreso</em> con la cantidad y el costo promedio del momento.</p></div>

      <h2>3. KARDEX &mdash; HISTORIAL DE MOVIMIENTOS</h2>
      <p>Ruta: <span class="ruta">Inventario &rarr; Kardex</span></p>
      <p>Registro cronologico de todo lo que ha entrado y salido de un producto en cada bodega,
      con saldo corriente actualizado en tiempo real.</p>

      <table class="t">
        <tr><th>Tipo</th><th>Como se genera</th><th>Efecto en stock</th></tr>
        <tr><td><span class="badge vd">entrada</span></td><td>Al activar una Compra o confirmar una Recepcion</td><td>Stock sube</td></tr>
        <tr><td><span class="badge rj">salida</span></td><td>Al registrar una Venta (modulo Ventas)</td><td>Stock baja</td></tr>
        <tr><td><span class="badge am">ajuste</span></td><td>Manual desde Kardex &rarr; Ajuste de Inventario</td><td>Sube o baja segun tipo</td></tr>
        <tr><td><span class="badge az">traslado</span></td><td>Al confirmar un Traslado de Bodega</td><td>Redistribuye entre bodegas</td></tr>
      </table>

      <h3>Hacer un ajuste manual de inventario</h3>
      <p>Ruta: <span class="ruta">Inventario &rarr; Kardex &rarr; Ajuste</span></p>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Seleccionar Producto y Bodega</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Elegir tipo: Ingreso (aumentar stock) o Egreso (reducir stock)</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Ingresar Cantidad y Motivo</div>
        <div class="detalle">Ejemplos de motivo: "Inventario fisico junio 2026", "Merma por dano", "Saldo inicial".</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">Guardar &mdash; el saldo se actualiza de inmediato</div>
      </div></div>

      <h3>Ver saldos actuales por bodega</h3>
      <p>Ruta: <span class="ruta">Inventario &rarr; Kardex &rarr; Saldos</span></p>
      <p>Muestra el stock actual de todos los productos por bodega con costo unitario
      y valor total en libros.</p>

      <h2>4. TRASLADOS ENTRE BODEGAS</h2>
      <p>Ruta: <span class="ruta">Inventario &rarr; Traslados</span></p>
      <p>Mueve productos de una bodega a otra <strong>sin cambiar el total de la empresa</strong>.
      Ejemplo: pasar 5 guitarras del showroom al taller para reparacion.</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Bodega origen</strong></td><td>De donde sale la mercaderia</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Bodega destino</strong></td><td>A donde llega. Debe ser diferente al origen.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Detalles</strong></td><td>Producto + Cantidad. Minimo 1 linea. Valida que haya stock
            suficiente en la bodega origen.</td><td><span class="req">Si</span></td></tr>
      </table>

      <table class="t">
        <tr><th>Estado</th><th>Significado</th></tr>
        <tr><td><span class="badge am">pendiente</span></td><td>Creado pero no ejecutado &mdash; el stock no ha cambiado todavia</td></tr>
        <tr><td><span class="badge vd">confirmado</span></td><td>Ejecutado &mdash; el stock ya se movio entre las bodegas</td></tr>
      </table>

      <div class="alerta box"><p>Si se intenta trasladar mas unidades de las que hay en la bodega
      de origen, el sistema bloquea la operacion con un mensaje de error.</p></div>

      <h2>5. RECEPCIONES DE BODEGA</h2>
      <p>Ruta: <span class="ruta">Inventario &rarr; Recepciones</span></p>
      <p>El bodeguero confirma que la mercaderia llego fisicamente y esta en buen estado.</p>

      <div class="tip box"><p><strong>Se crea automaticamente:</strong> Cuando se activa una Compra que
      tiene bodega asignada, el sistema genera la Recepcion en estado pendiente con las cantidades
      esperadas. No hay que crearla a mano.</p></div>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Ir a Inventario &rarr; Recepciones y buscar la pendiente</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Verificar las cantidades fisicamente</div>
        <div class="detalle">Si se recibio menos de lo esperado, ajustar la cantidad recibida.
        No puede ser mayor a la cantidad esperada.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Si los productos tienen etiquetas: escanear cada codigo de barras</div>
        <div class="detalle">El sistema valida que el codigo pertenezca a esa compra.
        Ejemplo: escanear AMP-000023. Si no pertenece, el sistema lo rechaza con un mensaje de error.
        Cada codigo escaneado queda registrado como "recibido".</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">Confirmar recepcion &rarr; el stock queda registrado en la bodega</div>
      </div></div>

      <h2>6. ACTIVOS FIJOS</h2>
      <p>Ruta: <span class="ruta">Inventario &rarr; Activos Fijos</span></p>
      <p>Bienes de la empresa que duran mas de un ano: vehiculos, equipos de audio, muebles, tecnologia.</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th></tr>
        <tr><td><strong>Codigo</strong></td><td>Identificador interno. Ej: VEH-001, EQ-015</td></tr>
        <tr><td><strong>Nombre / Descripcion</strong></td><td>Que es el bien</td></tr>
        <tr><td><strong>Categoria</strong></td><td>Mueble, Vehiculo, Equipo de audio, Tecnologia, etc.</td></tr>
        <tr><td><strong>Fecha adquisicion</strong></td><td>Cuando se compro o recibio</td></tr>
        <tr><td><strong>Valor original</strong></td><td>Costo de compra</td></tr>
        <tr><td><strong>Vida util (anos)</strong></td><td>Cuantos anos se espera que dure segun el SRI</td></tr>
        <tr><td><strong>% Depreciacion anual</strong></td><td>Segun tablas SRI del Ecuador</td></tr>
        <tr><td><strong>Custodio / Ubicacion</strong></td><td>Quien lo tiene y donde esta fisicamente</td></tr>
      </table>
    </div>';
    }

    /* ═══════════════════════════════════════════════════════
       PERSONAS
    ════════════════════════════════════════════════════════ */
    private function htmlPersonas(): string
    {
        return '
    <div class="page">
      <div class="titulo-modulo">
        <div class="nombre">PERSONAS</div>
        <div class="sub">Clientes &middot; Proveedores &middot; Transportistas</div>
        <div class="linea"></div>
      </div>

      <div class="intro"><p>
        <strong>Para que sirve:</strong> Directorio central de todos los terceros del sistema.
        Clientes, proveedores y transportistas se registran aqui una sola vez, y desde aqui
        los usan todos los demas modulos: Ventas, Compras, Guias de Remision e Importaciones.
      </p></div>

      <div class="info box"><p>
        <strong>Dev 1 y Dev 2:</strong> Este modulo fue construido principalmente por Dev 1
        (rama dev1-personas-clientes). Dev 2 consume estos registros en Compras, Importaciones
        y Guias de Remision.
      </p></div>

      <h2>QUIEN USA ESTE MODULO</h2>
      <div class="rel-box">
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Ventas &rarr; Facturas / Proformas / NC</strong> &mdash; necesita que el cliente este aqui</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Compras &rarr; Facturas / Anticipos</strong> &mdash; necesita que el proveedor este aqui</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Compras &rarr; Importaciones</strong> &mdash; necesita proveedor de tipo internacional</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Ventas &rarr; Guias de Remision</strong> &mdash; necesita que el transportista este aqui</div>
      </div>

      <h2>1. CLIENTES</h2>
      <p>Ruta: <span class="ruta">Personas &rarr; Clientes</span></p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Tipo identificacion</strong></td><td>cedula / ruc / pasaporte</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Identificacion</strong></td><td>Numero del documento. Unico en el sistema.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Razon Social</strong></td><td>Nombre completo o razon social legal</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Nombre comercial</strong></td><td>Como se conoce el negocio (puede diferir)</td><td>No</td></tr>
        <tr><td><strong>Email</strong></td><td>Para envio de factura electronica (cuando se integre el SRI)</td><td>No</td></tr>
        <tr><td><strong>Telefono / Celular</strong></td><td>Datos de contacto</td><td>No</td></tr>
        <tr><td><strong>Direccion</strong></td><td>Aparece impresa en la factura</td><td>No</td></tr>
        <tr><td><strong>Tiene credito</strong></td><td>Si = se le puede vender a credito. Habilita los campos de credito.</td><td>No</td></tr>
        <tr><td><strong>Dias credito</strong></td><td>Cuantos dias tiene para pagar (se pre-llena en la factura)</td><td>No</td></tr>
        <tr><td><strong>Cupo maximo</strong></td><td>Deuda maxima permitida. Si la supera no se le puede vender a credito.</td><td>No</td></tr>
        <tr><td><strong>Agente retencion</strong></td><td>Si el cliente retiene impuestos marcar Si &mdash; afecta el calculo de la factura</td><td>No</td></tr>
      </table>

      <div class="tip box"><p><strong>Consumidor Final:</strong> Para ventas al publico sin datos del cliente
      usar el registro "CONSUMIDOR FINAL" (RUC: 9999999999001) que viene preconfigurado en el sistema.
      No hay que crear uno nuevo cada vez.</p></div>

      <div class="nota box"><p><strong>Eliminar vs. Desactivar:</strong> Si el cliente ya tiene facturas,
      CxC, proformas o prefacturas no se puede eliminar. El sistema lo desactiva automaticamente
      (queda oculto pero el historial se preserva).</p></div>

      <h2>2. PROVEEDORES</h2>
      <p>Ruta: <span class="ruta">Personas &rarr; Proveedores</span>
      (misma pantalla que <span class="ruta">Compras &rarr; Proveedores</span>)</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Tipo</strong></td><td><strong>nacional</strong> = de Ecuador.
            <strong>internacional</strong> = del exterior (aparece en el selector de Importaciones).</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Tipo ID</strong></td><td>RUC, cedula o pasaporte</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Identificacion</strong></td><td>Numero del documento</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Razon Social</strong></td><td>Nombre legal completo</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Pais</strong></td><td>Se llena solo si tipo = nacional (ECUADOR automatico)</td><td>No</td></tr>
        <tr><td><strong>Divisa</strong></td><td>Para internacionales: moneda en que factura (USD, EUR, JPY...)</td><td>No</td></tr>
        <tr><td><strong>Dias credito</strong></td><td>Plazo habitual de pago. Se pre-llena cuando creas una compra de este proveedor.</td><td>No</td></tr>
      </table>

      <div class="nota box"><p>Para que un proveedor aparezca en el selector de Compras &rarr; Importaciones,
      debe estar registrado como tipo <strong>internacional</strong>.</p></div>

      <h2>3. TRANSPORTISTAS</h2>
      <p>Ruta: <span class="ruta">Personas &rarr; Transportistas</span></p>
      <p>Personas o empresas que transportan la mercaderia. Se necesitan para emitir
      <strong>Guias de Remision</strong>, que es el documento obligatorio del SRI para movilizar
      mercaderia fuera del establecimiento.</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Razon Social</strong></td><td>Nombre del transportista o empresa de transporte</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Identificacion</strong></td><td>CI o RUC. Unico en el sistema.</td><td>No *</td></tr>
        <tr><td><strong>Placa</strong></td><td>Placa del vehiculo que transporta</td><td>No *</td></tr>
        <tr><td><strong>Telefono</strong></td><td>Solo digitos, guiones y parentesis (7 a 20 caracteres)</td><td>No</td></tr>
        <tr><td><strong>Email</strong></td><td>Correo de contacto</td><td>No</td></tr>
      </table>

      <div class="nota box"><p>* La identificacion y la placa son opcionales en el formulario del sistema,
      pero el <strong>SRI las exige</strong> para autorizar la Guia de Remision electronica.
      Se recomienda completarlas siempre.</p></div>

      <div class="nota box"><p><strong>Eliminar vs. Desactivar:</strong> Si el transportista ya tiene guias
      de remision emitidas, el sistema lo desactiva en lugar de eliminarlo para preservar el historial.</p></div>
    </div>';
    }

    /* ═══════════════════════════════════════════════════════
       VENTAS
    ════════════════════════════════════════════════════════ */
    private function htmlVentas(): string
    {
        return '
    <div class="page">
      <div class="titulo-modulo">
        <div class="nombre">VENTAS</div>
        <div class="sub">Proformas &middot; Prefacturas &middot; Facturas &middot; NC &middot; Retenciones &middot; Guias &middot; CxC</div>
        <div class="linea"></div>
      </div>

      <div class="intro"><p>
        <strong>Para que sirve:</strong> Gestiona todo el ciclo de ventas desde la cotizacion (proforma)
        hasta la factura oficial, cobros y devoluciones. Al emitir una factura se genera el asiento
        contable automaticamente y, si la venta es a credito, tambien se crea la Cuenta por Cobrar.
      </p></div>

      <h2>ANTES DE USAR VENTAS &mdash; QUE SE NECESITA</h2>
      <div class="rel-box">
        <div class="rel-linea"><span class="rel-icono">v</span>
          <strong>Clientes</strong> registrados en Personas &rarr; Clientes (Dev 1)</div>
        <div class="rel-linea"><span class="rel-icono">v</span>
          <strong>Productos con precio</strong> configurados en Inventario &rarr; Productos (Dev 1)</div>
        <div class="rel-linea"><span class="rel-icono">v</span>
          <strong>Parametros Contables</strong> configurados en Contabilidad &rarr; Parametros</div>
        <div class="rel-linea"><span class="rel-icono">v</span>
          <strong>Ejercicio contable abierto</strong> en Contabilidad &rarr; Ejercicios</div>
        <div class="rel-linea"><span class="rel-icono">v</span>
          <strong>Transportistas</strong> registrados en Personas &rarr; Transportistas (para Guias de Remision)</div>
      </div>

      <div class="alerta box"><p>
        <strong>Las ventas NO bajan el stock automaticamente.</strong> Las facturas registran la venta
        pero el inventario no se decrementa de forma automatica (modelo diferido). Cuando se integre
        la gestion de stock con ventas se actualizara este comportamiento.
      </p></div>

      <h2>FLUJO COMPLETO DE VENTA</h2>
      <div class="flujo">
        <div class="flujo-fila">
          <div class="flujo-paso">Proforma<div class="fsub">cotizacion</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">Prefactura<div class="fsub">doc. interno</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">Factura<div class="fsub">doc. SRI</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">CxC<div class="fsub">si es credito</div></div>
          <div class="flujo-arrow">&rarr;</div>
          <div class="flujo-paso">Cobro<div class="fsub">cancela CxC</div></div>
        </div>
      </div>

      <h2>1. PROFORMAS (cotizaciones)</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; Proformas &rarr; + Nueva Proforma</span></p>
      <p>Documento no oficial para presentar precio al cliente. No genera asiento ni afecta inventario.
      Cuando el cliente acepta se convierte a Prefactura o directamente a Factura.</p>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Seleccionar cliente y agregar productos</div>
        <div class="detalle">El precio se carga desde el catalogo automaticamente.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Guardar e imprimir / compartir con el cliente</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Cuando el cliente acepta: "Convertir a Prefactura" o "Convertir a Factura"</div>
        <div class="detalle">Todos los datos pasan al nuevo documento sin reescribir nada.</div>
      </div></div>

      <h2>2. PREFACTURAS (documento interno previo a factura)</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; Prefacturas &rarr; + Nueva Prefactura</span></p>
      <p>Documento previo a la factura. Puede requerir aprobacion cuando el descuento supera
      el limite autorizado para el perfil del vendedor.</p>

      <table class="t">
        <tr><th>Estado</th><th>Que significa</th><th>Que sigue</th></tr>
        <tr><td><span class="badge am">pendiente</span></td><td>Esperando revision del supervisor</td><td>Aprobar o rechazar</td></tr>
        <tr><td><span class="badge vd">aprobada</span></td><td>Lista para convertir a factura</td><td>Clic en "Convertir a Factura"</td></tr>
        <tr><td><span class="badge rj">rechazada</span></td><td>Supervisor la rechazo con motivo</td><td>Vendedor corrige y reenvía</td></tr>
        <tr><td><span class="badge gr">facturada</span></td><td>Ya se emitio la factura</td><td>&mdash;</td></tr>
      </table>

      <h3>Flujo de aprobacion especial por descuento</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Vendedor aplica descuento mayor al limite de su perfil</div>
        <div class="detalle">El limite se configura en Configuracion &rarr; Limites de Descuento.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">La prefactura queda en estado "pendiente" automaticamente</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">El supervisor va a Ventas &rarr; Aprobaciones e ingresa su PIN de 4 digitos</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">El vendedor convierte la prefactura aprobada en factura</div>
      </div></div>

      <h2>3. FACTURAS (documento oficial SRI)</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; Facturas &rarr; + Nueva Factura</span></p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Cliente</strong></td><td>Buscar por RUC o nombre. Si no hay datos usar "CONSUMIDOR FINAL".</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha</strong></td><td>Por defecto el dia de hoy</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Detalles (lineas)</strong></td><td>Al menos 1 linea: producto + cantidad + precio + % descuento</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Formas de pago</strong></td><td>Al menos 1. La suma de los montos debe igualar el total de la factura (tolerancia de +/- $0.01).</td><td><span class="req">Si</span></td></tr>
      </table>

      <div style="margin:6px 0 12px;">
        <strong>Formas de pago:</strong>&nbsp;
        <span class="badge vd">efectivo</span>
        <span class="badge az">transferencia</span>
        <span class="badge mo">tarjeta</span>
        <span class="badge am">cheque</span>
        <span class="badge gr">credito</span>
      </div>

      <div class="nota box"><p><strong>Pago a credito:</strong> Al agregar "credito" como forma de pago,
      ingresar los dias de plazo. El sistema crea automaticamente la CxC con la fecha de vencimiento
      calculada.</p></div>

      <h3>Al emitir la factura el sistema hace automaticamente</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Asigna el numero secuencial SRI</div>
        <div class="detalle">Formato: 001-001-000000001 (establecimiento + punto emision + 9 digitos).
        El siguiente numero lo gestiona solo desde Configuracion &rarr; Empresa.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Genera el asiento contable</div>
        <div class="detalle">DEBE: Clientes (o Caja/Banco si es contado). HABER: Ventas + IVA por pagar.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Crea la CxC si hubo pago a credito</div>
      </div></div>

      <h3>Estados de una factura</h3>
      <table class="t">
        <tr><th>Estado</th><th>Estado SRI</th><th>Que significa</th></tr>
        <tr><td><span class="badge vd">activa</span></td><td><span class="badge am">pendiente</span></td>
            <td>Emitida en el sistema, pendiente de enviar al SRI (integracion en desarrollo)</td></tr>
        <tr><td><span class="badge vd">activa</span></td><td><span class="badge vd">autorizada</span></td>
            <td>Autorizada electronicamentepor el SRI (cuando se integre)</td></tr>
        <tr><td><span class="badge rj">anulada</span></td><td><span class="badge rj">anulada</span></td>
            <td>Solo Super Admin puede anular, solo el mismo dia antes de las 23:59</td></tr>
      </table>

      <div class="info box"><p><strong>Integracion SRI:</strong> La firma electronica y el envio automatico
      al webservice del SRI esta pendiente de implementacion. Actualmente el estado SRI queda en
      "pendiente" en todas las facturas.</p></div>

      <h2>4. NOTAS DE CREDITO (devoluciones)</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; Notas de Credito &rarr; + Nueva NC</span></p>
      <p>Para reversar parcial o totalmente una factura (devoluciones, error de precio, etc.).</p>

      <div class="alerta box"><p><strong>Restriccion actual:</strong> Solo se puede hacer NC sobre facturas
      con estado SRI = autorizada. Como actualmente todas las facturas quedan en "pendiente" (la
      integracion SRI no esta implementada), las NC estan bloqueadas en la practica hasta completar
      esa integracion.</p></div>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Seleccionar la factura original de referencia</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Escribir el motivo de la devolucion</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Seleccionar las lineas a reversar y las cantidades</div>
        <div class="detalle">Solo lineas de la factura original. La cantidad de la NC no puede
        superar la cantidad original.</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">Emitir &rarr; genera asiento reverso y reduce el saldo de la CxC si habia</div>
      </div></div>

      <h2>5. RETENCIONES RECIBIDAS</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; Retenciones</span></p>
      <p>Cuando un cliente agente de retencion nos entrega el comprobante de retencion,
      se registra aqui para cuadrar con la factura emitida.</p>

      <table class="t">
        <tr><th>Campo</th><th>Que es</th></tr>
        <tr><td><strong>Factura retenida</strong></td><td>La factura sobre la que aplica la retencion</td></tr>
        <tr><td><strong>N. comprobante</strong></td><td>Numero del comprobante que entrega el cliente</td></tr>
        <tr><td><strong>Fecha</strong></td><td>Fecha del comprobante de retencion</td></tr>
        <tr><td><strong>% Retencion IVA</strong></td><td>0%, 30%, 70% o 100% del IVA segun el tipo de cliente</td></tr>
        <tr><td><strong>% Retencion IR</strong></td><td>Porcentaje de retencion en la fuente segun tabla SRI</td></tr>
      </table>

      <h2>6. GUIAS DE REMISION</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; Guias de Remision</span></p>
      <p>Documento <strong>obligatorio del SRI</strong> para trasladar mercaderia fuera del establecimiento.
      El transportista debe estar registrado en Personas &rarr; Transportistas.</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Transportista</strong></td><td>Quien transporta (debe existir en Personas &rarr; Transportistas)</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Direccion de partida</strong></td><td>Desde donde sale la mercaderia</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Direccion de destino</strong></td><td>A donde llega la mercaderia</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha inicio transporte</strong></td><td>Cuando empieza el traslado</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha fin transporte</strong></td><td>Maximo 90 dias desde el inicio. Debe ser mayor o igual al inicio.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Factura referencia</strong></td><td>La factura que ampara el envio (recomendado pero opcional)</td><td>No</td></tr>
        <tr><td><strong>Detalle de mercaderia</strong></td><td>Descripcion + Cantidad + Unidad. Minimo 1 linea.</td><td><span class="req">Si</span></td></tr>
      </table>

      <h2>7. CUENTAS POR COBRAR (CxC)</h2>
      <p>Ruta: <span class="ruta">Ventas &rarr; CxC</span></p>
      <p>Se crean automaticamente al emitir una factura con forma de pago "credito". No se crean
      manualmente. Puedes ver el historial completo de pagos parciales y retenciones aplicadas
      en el detalle de cada CxC.</p>

      <h3>Registrar un cobro</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Buscar la CxC por cliente, numero de factura o fecha de vencimiento</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Clic en "Cobrar" &mdash; ingresar: fecha, banco/caja destino, forma de cobro y monto</div>
        <div class="detalle">El cobro puede ser parcial (el cliente abona solo una parte).</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Guardar</div>
        <div class="detalle">El saldo de la CxC se reduce. Si llega a $0: estado pagada.
        Si queda saldo: estado parcial. El banco/caja recibe el ingreso y se genera el asiento de cobro.</div>
      </div></div>

      <div style="margin:6px 0 14px;">
        <span class="badge am">pendiente</span>
        <span class="badge az">parcial</span>
        <span class="badge vd">pagada</span>
        <span class="badge rj">vencida</span>
      </div>
    </div>';
    }
}
