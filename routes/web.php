<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\EmpresaController as AuthEmpresaController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Configuracion\UsuarioController;
use App\Http\Controllers\Configuracion\PermisoController;
use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Inventario\MarcaController;
use App\Http\Controllers\Inventario\CategoriaProductoController;
use App\Http\Controllers\Inventario\BodegaController;
use App\Http\Controllers\Inventario\ProductoController;
use App\Http\Controllers\Inventario\KardexController;
use App\Http\Controllers\Inventario\TrasladoController;
use App\Http\Controllers\Inventario\ActivoFijoController;
use App\Http\Controllers\Personas\ClienteController;
use App\Http\Controllers\Personas\ProveedorController;
use App\Http\Controllers\Personas\TransportistaController;
use App\Http\Controllers\Contabilidad\PlanCuentaController;
use App\Http\Controllers\Contabilidad\EjercicioContableController;
use App\Http\Controllers\Contabilidad\AsientoContableController;
use App\Http\Controllers\Compras\ProveedorController as ComprasProveedorController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\Compras\CuentaPagarController;
use App\Http\Controllers\Compras\ImportacionController;
use App\Http\Controllers\Ventas\AprobacionController;
use App\Http\Controllers\Ventas\FacturaController;
use App\Http\Controllers\Ventas\ProformaController;
use App\Http\Controllers\Ventas\PrefacturaController;
use App\Http\Controllers\Ventas\NotaCreditoController;
use App\Http\Controllers\Ventas\RetencionController;
use App\Http\Controllers\Ventas\GuiaRemisionController;
use App\Http\Controllers\Ventas\CuentaCobrarController;
use Illuminate\Support\Facades\Route;

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// Selección de empresa
Route::middleware('auth')->group(function () {
    Route::get('/empresa/seleccionar', [AuthEmpresaController::class, 'seleccionar'])->name('empresa.seleccionar');
    Route::post('/empresa/cambiar', [AuthEmpresaController::class, 'cambiar'])->name('empresa.cambiar');
});

// App principal
Route::middleware('auth')->group(function () {
    Route::get('/', fn() => redirect()->route('dashboard'));
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Configuración - Usuarios
    Route::prefix('configuracion/usuarios')->name('configuracion.usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('/crear', [UsuarioController::class, 'create'])->name('create');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::get('/{usuario}/editar', [UsuarioController::class, 'edit'])->name('edit');
        Route::put('/{usuario}', [UsuarioController::class, 'update'])->name('update');
        Route::patch('/{usuario}/toggle-estado', [UsuarioController::class, 'toggleEstado'])->name('toggle-estado');
        Route::get('/{usuario}/accesos', [UsuarioController::class, 'show'])->name('show');
    });

    // Configuración - Permisos
    Route::prefix('configuracion/permisos')->name('configuracion.permisos.')->group(function () {
        Route::get('/', [PermisoController::class, 'index'])->name('index');
        Route::post('/actualizar', [PermisoController::class, 'actualizar'])->name('actualizar');
        Route::post('/limite', [PermisoController::class, 'actualizarLimite'])->name('limite');
    });

    // Contabilidad - Plan de Cuentas
    Route::prefix('contabilidad/plan-cuentas')->name('contabilidad.plan-cuentas.')->group(function () {
        Route::get('/', [PlanCuentaController::class, 'index'])->name('index');
        Route::get('/exportar', [PlanCuentaController::class, 'exportar'])->name('exportar');
        Route::post('/', [PlanCuentaController::class, 'store'])->name('store');
        Route::put('/{cuenta}', [PlanCuentaController::class, 'update'])->name('update');
        Route::patch('/{cuenta}/toggle-estado', [PlanCuentaController::class, 'toggleEstado'])->name('toggle-estado');
        Route::delete('/{cuenta}', [PlanCuentaController::class, 'destroy'])->name('destroy');
    });

    // Configuración - Empresa
    Route::prefix('configuracion/empresa')->name('configuracion.empresa.')->group(function () {
        Route::get('/', [EmpresaController::class, 'index'])->name('index');
        Route::put('/', [EmpresaController::class, 'update'])->name('update');
        Route::patch('/secuencial/{secuencial}', [EmpresaController::class, 'actualizarSecuencial'])->name('secuencial');
    });

    // Inventario — Productos + Kárdex
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
        Route::get('productos/reporte/lista', [ProductoController::class, 'reporteLista'])->name('productos.reporte.lista');
        Route::resource('productos', ProductoController::class)->except(['show']);

        // Kárdex — rutas estáticas antes de las dinámicas para evitar colisiones
        Route::get('kardex/saldo', [KardexController::class, 'getSaldo'])->name('kardex.getSaldo');
        Route::get('kardex/saldos', [KardexController::class, 'saldos'])->name('kardex.saldos');
        Route::get('kardex/reporte/saldos', [KardexController::class, 'reporteSaldos'])->name('kardex.reporte.saldos');
        Route::get('kardex/ajuste', [KardexController::class, 'ajuste'])->name('kardex.ajuste');
        Route::post('kardex/ajuste', [KardexController::class, 'storeAjuste'])->name('kardex.storeAjuste');
        Route::get('kardex', [KardexController::class, 'index'])->name('kardex.index');

        // Traslados
        Route::get('traslados', [TrasladoController::class, 'index'])->name('traslados.index');
        Route::get('traslados/nuevo', [TrasladoController::class, 'create'])->name('traslados.create');
        Route::post('traslados', [TrasladoController::class, 'store'])->name('traslados.store');
        Route::post('traslados/{traslado}/confirmar', [TrasladoController::class, 'confirmar'])->name('traslados.confirmar');
        Route::post('traslados/{traslado}/anular', [TrasladoController::class, 'anular'])->name('traslados.anular');
        Route::get('traslados/{traslado}', [TrasladoController::class, 'show'])->name('traslados.show');

        // Activos Fijos
        Route::get('activos/reporte/lista', [ActivoFijoController::class, 'reporteLista'])->name('activos.reporte.lista');
        Route::resource('activos', ActivoFijoController::class)->except(['show']);
        Route::get('activos/{activoFijo}', [ActivoFijoController::class, 'show'])->name('activos.show');
        Route::post('activos/{activoFijo}/depreciar', [ActivoFijoController::class, 'depreciar'])->name('activos.depreciar');
    });

    // Inventario — Configuración
    Route::prefix('inventario/configuracion')->name('inventario.config.')->group(function () {
        Route::resource('marcas', MarcaController::class)->except(['show', 'create', 'edit']);
        Route::resource('categorias', CategoriaProductoController::class)->except(['show', 'create', 'edit']);
        Route::resource('bodegas', BodegaController::class)->except(['show', 'create', 'edit']);
    });

    // Personas
    Route::prefix('personas')->name('personas.')->group(function () {
        // Clientes — rutas estáticas primero para evitar colisión con {cliente}
        Route::get('clientes/search', [ClienteController::class, 'search'])->name('clientes.search');
        Route::get('clientes/reporte/lista', [ClienteController::class, 'reporteLista'])->name('clientes.reporte.lista');
        Route::resource('clientes', ClienteController::class);
        Route::get('clientes/{cliente}/reporte', [ClienteController::class, 'reporteIndividual'])->name('clientes.reporte.individual');

        // Proveedores — rutas estáticas primero
        Route::get('proveedores/reporte/lista', [ProveedorController::class, 'reporteLista'])->name('proveedores.reporte.lista');
        Route::resource('proveedores', ProveedorController::class)
            ->parameters(['proveedores' => 'proveedor']);
        Route::get('proveedores/{proveedor}/reporte', [ProveedorController::class, 'reporteIndividual'])->name('proveedores.reporte.individual');

        // Transportistas — rutas estáticas primero
        Route::get('transportistas/reporte/lista', [TransportistaController::class, 'reporteLista'])->name('transportistas.reporte.lista');
        Route::resource('transportistas', TransportistaController::class)->except(['show']);
        Route::get('transportistas/{transportista}/reporte', [TransportistaController::class, 'reporteIndividual'])->name('transportistas.reporte.individual');
    });

    // Contabilidad - Ejercicios
    Route::prefix('contabilidad/ejercicios')->name('contabilidad.ejercicios.')->group(function () {
        Route::get('/',                      [EjercicioContableController::class, 'index']) ->name('index');
        Route::post('/',                     [EjercicioContableController::class, 'store']) ->name('store');
        Route::patch('/{ejercicio}/cerrar',  [EjercicioContableController::class, 'cerrar'])->name('cerrar');
        Route::patch('/{ejercicio}/reabrir', [EjercicioContableController::class, 'reabrir'])->name('reabrir');
    });

    // Contabilidad - Asientos
    Route::prefix('contabilidad/asientos')->name('contabilidad.asientos.')->group(function () {
        Route::get('/',                    [AsientoContableController::class, 'index'])        ->name('index');
        Route::post('/',                   [AsientoContableController::class, 'store'])        ->name('store');
        Route::get('/exportar-excel',      [AsientoContableController::class, 'exportarExcel'])->name('exportar-excel');
        Route::get('/reporte-pdf',         [AsientoContableController::class, 'reportePdf'])   ->name('reporte-pdf');
        Route::get('/{asiento}',           [AsientoContableController::class, 'show'])         ->name('show');
        Route::get('/{asiento}/pdf',       [AsientoContableController::class, 'imprimirPdf'])  ->name('pdf');
        Route::patch('/{asiento}/anular',  [AsientoContableController::class, 'anular'])       ->name('anular');
        Route::delete('/{asiento}',        [AsientoContableController::class, 'destroy'])      ->name('destroy');
    });

    // Compras - Proveedores
    Route::prefix('compras/proveedores')->name('compras.proveedores.')->group(function () {
        Route::get('/',                     [ComprasProveedorController::class, 'index'])       ->name('index');
        Route::post('/',                    [ComprasProveedorController::class, 'store'])       ->name('store');
        Route::put('/{proveedor}',          [ComprasProveedorController::class, 'update'])      ->name('update');
        Route::patch('/{proveedor}/toggle', [ComprasProveedorController::class, 'toggleEstado'])->name('toggle');
    });

    // Compras - Facturas
    Route::prefix('compras/facturas')->name('compras.facturas.')->group(function () {
        Route::get('/',                  [CompraController::class, 'index']) ->name('index');
        Route::post('/',                 [CompraController::class, 'store']) ->name('store');
        Route::get('/{compra}',          [CompraController::class, 'show'])  ->name('show');
        Route::patch('/{compra}/anular', [CompraController::class, 'anular'])->name('anular');
    });

    // Compras - CxP
    Route::get('compras/cuentas-pagar',
        [CuentaPagarController::class, 'index'])->name('compras.cxp.index');

    // Compras - Importaciones
    Route::prefix('compras/importaciones')->name('compras.importaciones.')->group(function () {
        Route::get('/',                         [ImportacionController::class, 'index'])   ->name('index');
        Route::post('/',                        [ImportacionController::class, 'store'])   ->name('store');
        Route::put('/{importacion}',            [ImportacionController::class, 'update'])  ->name('update');
        Route::patch('/{importacion}/liquidar', [ImportacionController::class, 'liquidar'])->name('liquidar');
    });

    // Ventas — validación de aprobaciones (fuera del prefix para mantener URL /api/ventas/...)
    Route::post('/api/ventas/validar-aprobacion', [AprobacionController::class, 'validar'])
        ->name('ventas.validar-aprobacion');

    // Ventas
    Route::prefix('ventas')->name('ventas.')->group(function () {

        // Facturas
        Route::get('/facturas',                          [FacturaController::class, 'index'])        ->name('facturas.index');
        Route::get('/facturas/crear',                    [FacturaController::class, 'create'])       ->name('facturas.create');
        Route::post('/facturas/cliente-guardar',         [FacturaController::class, 'clienteGuardar'])->name('facturas.cliente-guardar');
        Route::post('/facturas',                         [FacturaController::class, 'store'])        ->name('facturas.store');
        Route::get('/facturas/{factura}',                [FacturaController::class, 'show'])         ->name('facturas.show');
        Route::post('/facturas/{factura}/anular',        [FacturaController::class, 'anular'])       ->name('facturas.anular');
        Route::post('/facturas/{factura}/enviar-sri',    [FacturaController::class, 'enviarSri'])    ->name('facturas.enviar-sri');

        // Proformas
        Route::get('/proformas',                         [ProformaController::class, 'index'])           ->name('proformas.index');
        Route::get('/proformas/crear',                   [ProformaController::class, 'create'])          ->name('proformas.create');
        Route::post('/proformas',                        [ProformaController::class, 'store'])           ->name('proformas.store');
        Route::get('/proformas/{proforma}',              [ProformaController::class, 'show'])            ->name('proformas.show');
        Route::delete('/proformas/{proforma}',           [ProformaController::class, 'destroy'])         ->name('proformas.destroy');
        Route::post('/proformas/{proforma}/convertir',   [ProformaController::class, 'convertirAFactura'])->name('proformas.convertir');

        // Prefacturas
        Route::get('/prefacturas',                       [PrefacturaController::class, 'index'])           ->name('prefacturas.index');
        Route::get('/prefacturas/crear',                 [PrefacturaController::class, 'create'])          ->name('prefacturas.create');
        Route::post('/prefacturas',                      [PrefacturaController::class, 'store'])           ->name('prefacturas.store');
        Route::get('/prefacturas/{prefactura}',          [PrefacturaController::class, 'show'])            ->name('prefacturas.show');
        Route::post('/prefacturas/{prefactura}/abonar',  [PrefacturaController::class, 'abonar'])          ->name('prefacturas.abonar');
        Route::post('/prefacturas/{prefactura}/convertir',[PrefacturaController::class, 'convertirAFactura'])->name('prefacturas.convertir');

        // Notas de Crédito
        Route::get('/notas-credito',                          [NotaCreditoController::class, 'index'])    ->name('notas-credito.index');
        Route::get('/notas-credito/crear',                    [NotaCreditoController::class, 'create'])   ->name('notas-credito.create');
        Route::post('/notas-credito',                         [NotaCreditoController::class, 'store'])    ->name('notas-credito.store');
        Route::get('/notas-credito/{notaCredito}',            [NotaCreditoController::class, 'show'])     ->name('notas-credito.show');
        Route::post('/notas-credito/{notaCredito}/enviar-sri',[NotaCreditoController::class, 'enviarSri'])->name('notas-credito.enviar-sri');

        // Retenciones
        Route::get('/retenciones',                         [RetencionController::class, 'index'])    ->name('retenciones.index');
        Route::get('/retenciones/crear',                   [RetencionController::class, 'create'])   ->name('retenciones.create');
        Route::post('/retenciones',                        [RetencionController::class, 'store'])    ->name('retenciones.store');
        Route::get('/retenciones/{retencion}',             [RetencionController::class, 'show'])     ->name('retenciones.show');
        Route::post('/retenciones/{retencion}/enviar-sri', [RetencionController::class, 'enviarSri'])->name('retenciones.enviar-sri');

        // Guías de Remisión
        Route::get('/guias-remision',                            [GuiaRemisionController::class, 'index'])    ->name('guias-remision.index');
        Route::get('/guias-remision/crear',                      [GuiaRemisionController::class, 'create'])   ->name('guias-remision.create');
        Route::post('/guias-remision',                           [GuiaRemisionController::class, 'store'])    ->name('guias-remision.store');
        Route::get('/guias-remision/{guiaRemision}',             [GuiaRemisionController::class, 'show'])     ->name('guias-remision.show');
        Route::post('/guias-remision/{guiaRemision}/enviar-sri', [GuiaRemisionController::class, 'enviarSri'])->name('guias-remision.enviar-sri');

        // Cuentas por Cobrar
        Route::get('/cxc',                             [CuentaCobrarController::class, 'index'])         ->name('cxc.index');
        Route::get('/cxc/{cuentaCobrar}',              [CuentaCobrarController::class, 'show'])          ->name('cxc.show');
        Route::post('/cxc/{cuentaCobrar}/cobrar',      [CuentaCobrarController::class, 'registrarCobro'])->name('cxc.cobrar');
        Route::post('/cxc/{cuentaCobrar}/castigar',    [CuentaCobrarController::class, 'castigo'])       ->name('cxc.castigar');
    });
});
