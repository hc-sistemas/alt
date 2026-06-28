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

        // Solo modulos Dev 2 (excluye RRHH, Ventas, Inventario y Personas que son Dev 1)
        $modulos = [
            'contabilidad' => ['nombre' => 'Contabilidad', 'html' => $this->htmlContabilidad()],
            'compras'      => ['nombre' => 'Compras',      'html' => $this->htmlCompras()],
            'bancos'       => ['nombre' => 'Bancos',       'html' => $this->htmlBancos()],
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
       BANCOS
    ════════════════════════════════════════════════════════ */
    private function htmlBancos(): string
    {
        return '
    <div class="page">
      <div class="titulo-modulo">
        <div class="nombre">BANCOS</div>
        <div class="sub">Bancos y Cajas &middot; Movimientos &middot; Cheques &middot; Cierre de Cajas &middot; Conciliacion &middot; Datafast &middot; Reportes</div>
        <div class="linea"></div>
      </div>

      <div class="intro"><p>
        <strong>Para que sirve:</strong> Controla todo el dinero de la empresa: saldos en bancos y cajas,
        entradas y salidas de efectivo, cheques emitidos, cierre diario de cajas, conciliacion con el
        estado de cuenta del banco y liquidacion de pagos con tarjeta (Datafast).
        Cada movimiento genera automaticamente un asiento contable en Contabilidad.
      </p></div>

      <h2>COMO SE CONECTA CON EL RESTO DEL SISTEMA</h2>
      <div class="rel-box">
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Compras &rarr; Pagar CxP</strong> crea un MovimientoBancario (egreso) automaticamente</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Ventas &rarr; Cobrar CxC</strong> crea un MovimientoBancario (ingreso) automaticamente</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Cada movimiento bancario</strong> genera un asiento contable doble en Contabilidad</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Datafast</strong> usa parametros contables: cta_vouchers, cta_ventas_locales, cta_comisiones_bancarias</div>
        <div class="rel-linea"><span class="rel-icono">-&gt;</span>
          <strong>Cada banco/caja</strong> debe tener una cuenta del plan de cuentas asignada</div>
      </div>

      <h2>1. BANCOS Y CAJAS (catalogo)</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Bancos y Cajas</span></p>
      <p>Catalogo de todas las cuentas bancarias y cajas de la empresa.
      Cada banco/caja tiene un saldo que se actualiza automaticamente con cada movimiento.</p>

      <h3>Tipos disponibles</h3>
      <div style="margin:6px 0 12px;">
        <span class="badge az">banco</span>
        <span class="badge vd">caja</span>
        <span class="badge am">caja chica</span>
        <span class="badge mo">tarjeta</span>
      </div>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Tipo</strong></td><td>banco / caja / caja_chica / tarjeta</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Nombre</strong></td><td>Ej: "Banco Pichincha Cta. Corriente", "Caja Showroom"</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>N. Cuenta</strong></td><td>Numero de cuenta bancaria</td><td>No</td></tr>
        <tr><td><strong>Tipo cuenta</strong></td><td>ahorros o corriente (solo para bancos)</td><td>No</td></tr>
        <tr><td><strong>Cuenta contable</strong></td><td>Cuenta del plan de cuentas que representa este banco/caja.
            Si se deja vacio el sistema asigna una automaticamente segun el tipo.</td><td>No</td></tr>
        <tr><td><strong>Saldo inicial</strong></td><td>Saldo de apertura al registrarlo por primera vez en el sistema</td><td>No</td></tr>
      </table>

      <div class="tip box"><p><strong>Cuentas automaticas si no se asigna una:</strong>
      banco: Bancos (1.1.1.3) &middot; caja: Caja (1.1.1.1) &middot;
      caja_chica: Caja Chica (1.1.1.2) &middot; tarjeta: Tarjetas (1.1.1.5).</p></div>

      <div class="alerta box"><p>No se puede desactivar ni eliminar un banco/caja si tiene
      saldo diferente de cero o si ya tiene movimientos registrados.</p></div>

      <h2>2. MOVIMIENTOS BANCARIOS</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Movimientos</span></p>
      <p>Registro de todas las entradas y salidas de dinero.
      El saldo del banco se actualiza automaticamente con cada movimiento guardado.</p>

      <h3>Registrar un movimiento</h3>
      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Banco / Caja</strong></td><td>Donde entra o sale el dinero</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Tipo</strong></td><td>ingreso (entra dinero) o egreso (sale dinero)</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Sub-tipo</strong></td><td>transferencia / cheque / efectivo / deposito</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha</strong></td><td>Fecha del movimiento</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Monto</strong></td><td>Mayor a $0.01. Si es egreso el banco debe tener saldo suficiente.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Descripcion</strong></td><td>Detalle del movimiento. Ej: "Pago factura proveedor XYZ"</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Cuenta contrapartida</strong></td><td>Cuenta contable del otro lado del asiento (debe tener "permite asientos = Si" en el plan de cuentas)</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Beneficiario</strong></td><td>Nombre de quien recibe o envia el dinero</td><td>No</td></tr>
        <tr><td><strong>N. Documento</strong></td><td>Referencia: N. transferencia, N. factura, etc.</td><td>No</td></tr>
      </table>

      <div class="tip box"><p>El sistema genera el asiento contable automaticamente al guardar:
      Ingreso &rarr; DEBE Banco / HABER Contrapartida.
      Egreso &rarr; DEBE Contrapartida / HABER Banco.</p></div>

      <h3>Anular un movimiento</h3>
      <ul class="lista">
        <li>Clic en "Anular" en el detalle del movimiento. Escribir el motivo (minimo 10 caracteres).</li>
        <li>El saldo del banco se revierte automaticamente</li>
        <li>Queda registrado en el log de cambios criticos con el motivo ingresado</li>
        <li><strong>No se puede anular</strong> si el movimiento ya fue conciliado con el banco</li>
      </ul>

      <h3>Filtros y exportacion</h3>
      <p>Filtrar por banco/caja, tipo (ingreso/egreso), rango de fechas y busqueda por texto
      (descripcion, beneficiario o N. documento).
      Boton <strong>Excel</strong> descarga el listado con los filtros aplicados en formato .xlsx.</p>

      <h2>3. CHEQUES</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Cheques</span></p>
      <p>Registro y seguimiento de los cheques que emite la empresa.
      Permite saber cuales fueron cobrados, cuales siguen pendientes y cuales protestaron.</p>

      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Banco / Caja</strong></td><td>De que cuenta bancaria se emite el cheque</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Numero</strong></td><td>Numero del cheque fisico. Unico por banco.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Monto</strong></td><td>Valor del cheque (mayor a $0.01)</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha emision</strong></td><td>Cuando se emite el cheque</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Beneficiario</strong></td><td>A quien va dirigido el cheque</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Banco emisor</strong></td><td>Nombre del banco que aparece en el cheque (informativo)</td><td>No</td></tr>
        <tr><td><strong>Fecha cobro esperada</strong></td><td>Cuando se espera que lo presenten al cobro (mayor o igual a la emision)</td><td>No</td></tr>
        <tr><td><strong>Observacion</strong></td><td>Notas adicionales</td><td>No</td></tr>
      </table>

      <h3>Estados del cheque</h3>
      <table class="t">
        <tr><th>Estado</th><th>Significado</th><th>Puede cambiar a...</th></tr>
        <tr><td><span class="badge am">emitido</span></td><td>Entregado al beneficiario, pendiente de cobro</td><td>cobrado / protestado / anulado</td></tr>
        <tr><td><span class="badge vd">cobrado</span></td><td>El beneficiario ya lo cobro en el banco</td><td>Estado final</td></tr>
        <tr><td><span class="badge rj">protestado</span></td><td>El banco lo rechazo (sin fondos u otro motivo)</td><td>Registra en log critico</td></tr>
        <tr><td><span class="badge gr">anulado</span></td><td>Cancelado antes de ser presentado al banco</td><td>Estado final</td></tr>
      </table>

      <div class="nota box"><p>Solo se puede cambiar el estado de un cheque que este en <em>emitido</em>.
      Si protesta queda registrado automaticamente en el log de cambios criticos.</p></div>

      <h2>4. CIERRE DE CAJAS</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Cierre de Cajas</span></p>
      <p>Flujo diario para abrir y cerrar las cajas de la empresa.
      Permite cuadrar lo que registro el sistema contra lo que hay fisicamente en caja.</p>

      <h3>Abrir caja</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "Abrir Caja" &rarr; seleccionar la caja y el monto inicial</div>
        <div class="detalle">El monto inicial son los billetes y monedas que hay en caja al empezar el dia.
        No puede haber otra apertura de la misma caja en el mismo dia.</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Guardar &rarr; la caja queda en estado "abierto"</div>
      </div></div>

      <h3>Cerrar caja (al final del dia)</h3>
      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "Cerrar Caja" en la apertura del dia</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Contar el dinero fisico e ingresar los totales por forma de pago</div>
        <div class="detalle">Total efectivo + Total tarjeta + Total cheque + Total transferencia.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">El sistema calcula la diferencia y guarda el cierre</div>
        <div class="detalle">Diferencia = Total cobrado fisico menos Total facturado por el sistema.
        Si supera $0.01 muestra una advertencia.</div>
      </div></div>

      <h2>5. CONCILIACION BANCARIA</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Conciliacion</span></p>
      <p>Comparar los movimientos del sistema contra el estado de cuenta del banco
      para verificar que los saldos coincidan al cierre del mes.</p>

      <div class="paso"><div class="num">1</div><div class="paso-cont">
        <div class="titulo">Clic en "+ Nueva Conciliacion"</div>
      </div></div>
      <div class="paso"><div class="num">2</div><div class="paso-cont">
        <div class="titulo">Seleccionar el banco y la fecha de corte</div>
        <div class="detalle">La fecha de corte es la fecha del estado de cuenta del banco. Ej: 30 de junio.</div>
      </div></div>
      <div class="paso"><div class="num">3</div><div class="paso-cont">
        <div class="titulo">Ingresar el saldo segun el banco</div>
        <div class="detalle">El valor que aparece en el estado de cuenta fisico enviado por el banco.</div>
      </div></div>
      <div class="paso"><div class="num">4</div><div class="paso-cont">
        <div class="titulo">Subir el archivo del banco si lo tienes (CSV, TXT o Excel, max 5 MB)</div>
      </div></div>
      <div class="paso"><div class="num">5</div><div class="paso-cont">
        <div class="titulo">El sistema muestra la diferencia y las partidas en transito</div>
        <div class="detalle">Partidas en transito: movimientos del sistema que aun no aparecen en el estado
        de cuenta del banco porque estan en camino (cheques pendientes, transferencias en proceso).</div>
      </div></div>
      <div class="paso"><div class="num">6</div><div class="paso-cont">
        <div class="titulo">Marcar individualmente cada movimiento como conciliado</div>
      </div></div>
      <div class="paso"><div class="num">7</div><div class="paso-cont">
        <div class="titulo">Cuando todo cuadra: "Marcar como Conciliada"</div>
        <div class="detalle">Todos los movimientos quedan con conciliado = Si.
        Un movimiento conciliado NO se puede anular despues.</div>
      </div></div>

      <table class="t">
        <tr><th>Estado</th><th>Significado</th></tr>
        <tr><td><span class="badge am">pendiente</span></td><td>Creada, en proceso de revision y marcacion</td></tr>
        <tr><td><span class="badge vd">conciliada</span></td><td>Cuadrada y cerrada definitivamente</td></tr>
      </table>

      <h2>6. DATAFAST (pagos con tarjeta)</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Datafast</span></p>
      <p>Gestiona los lotes de vouchers de tarjeta procesados por Datafast.
      Tiene dos pasos: <strong>crear el lote</strong> cuando se procesan los vouchers del dia,
      y <strong>liquidarlo</strong> cuando el banco hace el deposito neto.</p>

      <h3>Crear un lote</h3>
      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Banco / Caja</strong></td><td>Donde se depositara el valor neto de la liquidacion</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>N. Lote</strong></td><td>Numero de lote de Datafast. Unico por empresa.</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Fecha</strong></td><td>Fecha del lote</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Total vouchers</strong></td><td>Suma total de todos los vouchers del lote</td><td><span class="req">Si</span></td></tr>
      </table>

      <h3>Liquidar el lote (cuando el banco deposita)</h3>
      <table class="t">
        <tr><th>Campo</th><th>Descripcion</th><th>Req.</th></tr>
        <tr><td><strong>Fecha deposito</strong></td><td>Cuando el banco hizo el deposito neto</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Valor bruto</strong></td><td>Total que Datafast declara antes de descuentos</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Comision Datafast</strong></td><td>Lo que cobra Datafast por el servicio (porcentaje)</td><td><span class="req">Si</span></td></tr>
        <tr><td><strong>Retencion IVA</strong></td><td>Retencion del IVA que aplica Datafast si corresponde</td><td>No</td></tr>
        <tr><td><strong>Retencion IR</strong></td><td>Retencion en la fuente que aplica si corresponde</td><td>No</td></tr>
        <tr><td><strong>Banco destino</strong></td><td>La cuenta bancaria donde llega el deposito neto</td><td><span class="req">Si</span></td></tr>
      </table>

      <div class="ejemplo">
        <div class="ej-titulo">Ejemplo de liquidacion Datafast</div>
        <p>Valor bruto: $1,000 &middot; Comision Datafast: $20 &middot; Retencion IVA: $15 &middot; Retencion IR: $5</p>
        <p>Valor neto que deposita el banco: $1,000 - $20 - $15 - $5 = <strong>$960</strong></p>
        <p>Asiento automatico: DEBE Banco $960 + DEBE Comision $20 + DEBE Ret.IVA $15 &middot; HABER Vouchers $1,000</p>
      </div>

      <h2>7. REPORTES</h2>
      <p>Ruta: <span class="ruta">Bancos &rarr; Reportes</span></p>
      <table class="t">
        <tr><th>Reporte</th><th>Que muestra</th><th>Filtros disponibles</th></tr>
        <tr><td><strong>Estado de Cuenta</strong></td><td>Saldo inicial, movimientos del periodo con saldo acumulado corriente, saldo final, total ingresos y total egresos</td><td>Banco/caja + rango de fechas</td></tr>
        <tr><td><strong>Movimientos</strong></td><td>Listado de movimientos sin anular en formato horizontal para impresion</td><td>Banco/caja, tipo, rango de fechas</td></tr>
        <tr><td><strong>Caja Chica</strong></td><td>Resumen de aperturas y cierres de cajas con diferencias registradas</td><td>Banco/caja + rango de fechas</td></tr>
      </table>

      <div class="tip box"><p>El boton <strong>Excel</strong> en la vista de Movimientos descarga el
      listado actual con los filtros aplicados en formato .xlsx con encabezados y totales automaticos.</p></div>
    </div>';
    }
}
