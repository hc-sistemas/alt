<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\EmpresaController as AuthEmpresaController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Configuracion\UsuarioController;
use App\Http\Controllers\Configuracion\PermisoController;
use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Contabilidad\PlanCuentaController;
use App\Http\Controllers\Contabilidad\EjercicioContableController;
use App\Http\Controllers\Contabilidad\AsientoContableController;
use App\Http\Controllers\Contabilidad\ParametroContableController;
use App\Http\Controllers\Compras\ProveedorController as ComprasProveedorController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\Compras\CuentaPagarController;
use App\Http\Controllers\Compras\AnticipoProveedorController;
use App\Http\Controllers\Compras\ImportacionController;
use App\Http\Controllers\Inventario\MarcaController;
use App\Http\Controllers\Inventario\CategoriaProductoController;
use App\Http\Controllers\Inventario\BodegaController;
use App\Http\Controllers\Inventario\ProductoController;
use App\Http\Controllers\Inventario\KardexController;
use App\Http\Controllers\Inventario\TrasladoController;
use App\Http\Controllers\Inventario\ActivoFijoController;
use App\Http\Controllers\Personas\ClienteController;
use App\Http\Controllers\Personas\ProveedorController as PersonasProveedorController;
use App\Http\Controllers\Personas\TransportistaController;
use App\Http\Controllers\Bancos\BancoCajaController;
use App\Http\Controllers\Bancos\MovimientoBancarioController;
use App\Http\Controllers\Bancos\CierreCajaController;
use App\Http\Controllers\Bancos\DatafastController;
use App\Http\Controllers\Bancos\ConciliacionController;
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

    // Configuración - Empresa
    Route::prefix('configuracion/empresa')->name('configuracion.empresa.')->group(function () {
        Route::get('/', [EmpresaController::class, 'index'])->name('index');
        Route::put('/', [EmpresaController::class, 'update'])->name('update');
        Route::patch('/secuencial/{secuencial}', [EmpresaController::class, 'actualizarSecuencial'])->name('secuencial');
    });

    // Contabilidad - Parámetros Contables
    Route::prefix('contabilidad/parametros')->name('contabilidad.parametros.')->group(function () {
        Route::get('/',     [ParametroContableController::class, 'index'])         ->name('index');
        Route::post('/',    [ParametroContableController::class, 'update'])        ->name('update');
        Route::post('/auto',[ParametroContableController::class, 'autoconfigurar'])->name('auto');
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
        Route::get('/libro-diario',        [AsientoContableController::class, 'libroDiario'])  ->name('libro-diario');
        Route::get('/mayor-cuenta',        [AsientoContableController::class, 'mayorCuenta'])  ->name('mayor-cuenta');
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
        Route::get('/pdf',                  [ComprasProveedorController::class, 'pdf'])         ->name('pdf');
        Route::get('/excel',                [ComprasProveedorController::class, 'excel'])       ->name('excel');
    });

    // Compras - Facturas
    Route::prefix('compras/facturas')->name('compras.facturas.')->group(function () {
        Route::get('/',                  [CompraController::class, 'index']) ->name('index');
        Route::post('/',                 [CompraController::class, 'store']) ->name('store');
        Route::get('/pdf',               [CompraController::class, 'pdf'])   ->name('pdf');
        Route::get('/excel',             [CompraController::class, 'excel']) ->name('excel');
        Route::get('/{compra}',          [CompraController::class, 'show'])        ->name('show');
        Route::get('/{compra}/pdf',      [CompraController::class, 'pdfIndividual'])->name('pdf-individual');
        Route::patch('/{compra}/anular', [CompraController::class, 'anular'])       ->name('anular');
    });

    // Compras - CxP
    Route::prefix('compras/cuentas-pagar')->name('compras.cxp.')->group(function () {
        Route::get('/',                          [CuentaPagarController::class, 'index'])->name('index');
        Route::get('/pdf',                       [CuentaPagarController::class, 'pdf'])  ->name('pdf');
        Route::get('/excel',                     [CuentaPagarController::class, 'excel'])->name('excel');
        Route::post('/{cuentaPagar}/pagar',      [CuentaPagarController::class, 'pagar'])->name('pagar');
    });

    // Compras - Anticipos Proveedores
    Route::prefix('compras/anticipos')->name('compras.anticipos.')->group(function () {
        Route::get('/',                    [AnticipoProveedorController::class, 'index'])->name('index');
        Route::post('/',                   [AnticipoProveedorController::class, 'store'])->name('store');
        Route::patch('/{anticipo}/cruzar', [AnticipoProveedorController::class, 'cruzar'])->name('cruzar');
        Route::patch('/{anticipo}/anular', [AnticipoProveedorController::class, 'anular'])->name('anular');
    });

    // Compras - Importaciones
    Route::prefix('compras/importaciones')->name('compras.importaciones.')->group(function () {
        Route::get('/',                         [ImportacionController::class, 'index'])   ->name('index');
        Route::post('/',                        [ImportacionController::class, 'store'])   ->name('store');
        Route::put('/{importacion}',            [ImportacionController::class, 'update'])  ->name('update');
        Route::patch('/{importacion}/liquidar', [ImportacionController::class, 'liquidar'])->name('liquidar');
    });

    // Inventario — Productos + Kárdex
    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
        Route::resource('productos', ProductoController::class)->except(['show']);

        // Kárdex — rutas estáticas antes de las dinámicas para evitar colisiones
        Route::get('kardex/saldo', [KardexController::class, 'getSaldo'])->name('kardex.getSaldo');
        Route::get('kardex/saldos', [KardexController::class, 'saldos'])->name('kardex.saldos');
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
        Route::get('proveedores/reporte/lista', [PersonasProveedorController::class, 'reporteLista'])->name('proveedores.reporte.lista');
        Route::resource('proveedores', PersonasProveedorController::class)
            ->parameters(['proveedores' => 'proveedor']);
        Route::get('proveedores/{proveedor}/reporte', [PersonasProveedorController::class, 'reporteIndividual'])->name('proveedores.reporte.individual');

        // Transportistas — rutas estáticas primero
        Route::get('transportistas/reporte/lista', [TransportistaController::class, 'reporteLista'])->name('transportistas.reporte.lista');
        Route::resource('transportistas', TransportistaController::class)->except(['show']);
        Route::get('transportistas/{transportista}/reporte', [TransportistaController::class, 'reporteIndividual'])->name('transportistas.reporte.individual');
    });

    // Bancos - Catálogo bancos y cajas
    Route::prefix('bancos/catalogo')->name('bancos.catalogo.')->group(function () {
        Route::get('/',                 [BancoCajaController::class, 'index'])       ->name('index');
        Route::post('/',                [BancoCajaController::class, 'store'])       ->name('store');
        Route::put('/{banco}',          [BancoCajaController::class, 'update'])      ->name('update');
        Route::patch('/{banco}/toggle', [BancoCajaController::class, 'toggleEstado'])->name('toggle');
        Route::delete('/{banco}',       [BancoCajaController::class, 'destroy'])     ->name('destroy');
    });

    // Bancos - Movimientos
    Route::prefix('bancos/movimientos')->name('bancos.movimientos.')->group(function () {
        Route::get('/',                      [MovimientoBancarioController::class, 'index'])      ->name('index');
        Route::post('/',                     [MovimientoBancarioController::class, 'store'])      ->name('store');
        Route::patch('/{movimiento}/anular', [MovimientoBancarioController::class, 'anular'])     ->name('anular');
        Route::get('/exportar-xml',          [MovimientoBancarioController::class, 'exportarXml'])->name('exportar-xml');
    });

    // Bancos - Cajas
    Route::prefix('bancos/cajas')->name('bancos.cajas.')->group(function () {
        Route::get('/',                  [CierreCajaController::class, 'index']) ->name('index');
        Route::post('/abrir',            [CierreCajaController::class, 'abrir']) ->name('abrir');
        Route::patch('/{cierre}/cerrar', [CierreCajaController::class, 'cerrar'])->name('cerrar');
    });

    // Bancos - Datafast
    Route::prefix('bancos/datafast')->name('bancos.datafast.')->group(function () {
        Route::get('/',                  [DatafastController::class, 'index'])    ->name('index');
        Route::post('/lote',             [DatafastController::class, 'storeLote'])->name('lote');
        Route::patch('/{lote}/liquidar', [DatafastController::class, 'liquidar']) ->name('liquidar');
    });

    // Bancos - Conciliaciones
    Route::prefix('bancos/conciliaciones')->name('bancos.conciliaciones.')->group(function () {
        Route::get('/',                             [ConciliacionController::class, 'index'])           ->name('index');
        Route::post('/',                            [ConciliacionController::class, 'store'])           ->name('store');
        Route::get('/{conciliacion}',               [ConciliacionController::class, 'show'])            ->name('show');
        Route::patch('/{conciliacion}/conciliar',   [ConciliacionController::class, 'marcarConciliada'])->name('conciliar');
    });
});
