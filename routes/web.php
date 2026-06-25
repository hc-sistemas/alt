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
use App\Http\Controllers\Contabilidad\ReporteContableController;
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
use App\Http\Controllers\Inventario\ListaPrecioController;
use App\Http\Controllers\Inventario\RecepcionController;
use App\Http\Controllers\Personas\ClienteController;
use App\Http\Controllers\Personas\ProveedorController as PersonasProveedorController;
use App\Http\Controllers\Personas\TransportistaController;
use App\Http\Controllers\Bancos\BancoCajaController;
use App\Http\Controllers\Bancos\MovimientoBancarioController;
use App\Http\Controllers\Bancos\CierreCajaController;
use App\Http\Controllers\Bancos\DatafastController;
use App\Http\Controllers\Bancos\ConciliacionController;
use App\Http\Controllers\Bancos\ChequesController;
use App\Http\Controllers\Bancos\BancoReporteController;
use App\Http\Controllers\Ventas\AprobacionController;
use App\Http\Controllers\Ventas\FacturaController;
use App\Http\Controllers\Ventas\ProformaController;
use App\Http\Controllers\Ventas\PrefacturaController;
use App\Http\Controllers\Ventas\NotaCreditoController;
use App\Http\Controllers\Ventas\RetencionController;
use App\Http\Controllers\Ventas\GuiaRemisionController;
use App\Http\Controllers\Ventas\CuentaCobrarController;
use App\Http\Controllers\RRHH\ColaboradorController;
use App\Http\Controllers\RRHH\AsistenciaController;
use App\Http\Controllers\RRHH\HorasExtrasController;
use App\Http\Controllers\RRHH\NominaController;
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

    // Configuración
    Route::middleware('permiso:configuracion,ver')->group(function () {
        Route::prefix('configuracion/usuarios')->name('configuracion.usuarios.')->group(function () {
            Route::get('/', [UsuarioController::class, 'index'])->name('index');
            Route::get('/crear', [UsuarioController::class, 'create'])->name('create');
            Route::post('/', [UsuarioController::class, 'store'])->name('store');
            Route::get('/{usuario}/editar', [UsuarioController::class, 'edit'])->name('edit');
            Route::put('/{usuario}', [UsuarioController::class, 'update'])->name('update');
            Route::patch('/{usuario}/toggle-estado', [UsuarioController::class, 'toggleEstado'])->name('toggle-estado');
            Route::get('/{usuario}/accesos', [UsuarioController::class, 'show'])->name('show');
        });

        Route::prefix('configuracion/permisos')->name('configuracion.permisos.')->group(function () {
            Route::get('/', [PermisoController::class, 'index'])->name('index');
            Route::post('/actualizar', [PermisoController::class, 'actualizar'])->name('actualizar');
            Route::post('/limite', [PermisoController::class, 'actualizarLimite'])->name('limite');
        });

        Route::prefix('configuracion/empresa')->name('configuracion.empresa.')->group(function () {
            Route::get('/', [EmpresaController::class, 'index'])->name('index');
            Route::put('/', [EmpresaController::class, 'update'])->name('update');
            Route::patch('/secuencial/{secuencial}', [EmpresaController::class, 'actualizarSecuencial'])->name('secuencial');
        });
    });

    // Contabilidad
    Route::middleware('permiso:contabilidad,ver')->group(function () {
        Route::prefix('contabilidad/parametros')->name('contabilidad.parametros.')->group(function () {
            Route::get('/',     [ParametroContableController::class, 'index'])         ->name('index');
            Route::post('/',    [ParametroContableController::class, 'update'])        ->name('update');
            Route::post('/auto',[ParametroContableController::class, 'autoconfigurar'])->name('auto');
        });

        Route::prefix('contabilidad/plan-cuentas')->name('contabilidad.plan-cuentas.')->group(function () {
            Route::get('/', [PlanCuentaController::class, 'index'])->name('index');
            Route::get('/exportar', [PlanCuentaController::class, 'exportar'])->name('exportar');
            Route::post('/', [PlanCuentaController::class, 'store'])->name('store');
            Route::put('/{cuenta}', [PlanCuentaController::class, 'update'])->name('update');
            Route::patch('/{cuenta}/toggle-estado', [PlanCuentaController::class, 'toggleEstado'])->name('toggle-estado');
            Route::delete('/{cuenta}', [PlanCuentaController::class, 'destroy'])->name('destroy');
        });

        Route::prefix('contabilidad/ejercicios')->name('contabilidad.ejercicios.')->group(function () {
            Route::get('/',                      [EjercicioContableController::class, 'index']) ->name('index');
            Route::post('/',                     [EjercicioContableController::class, 'store']) ->name('store');
            Route::patch('/{ejercicio}/cerrar',  [EjercicioContableController::class, 'cerrar'])->name('cerrar');
            Route::patch('/{ejercicio}/reabrir', [EjercicioContableController::class, 'reabrir'])->name('reabrir');
        });

        Route::prefix('contabilidad/reportes')->name('contabilidad.reportes.')->group(function () {
            Route::get('/',             [ReporteContableController::class, 'index'])      ->name('index');
            Route::get('/libro-diario', [ReporteContableController::class, 'libroDiario'])->name('libro-diario');
            Route::get('/mayor',        [ReporteContableController::class, 'mayor'])      ->name('mayor');
        });

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
    });

    // Compras
    Route::middleware('permiso:compras,ver')->group(function () {
        Route::prefix('compras/proveedores')->name('compras.proveedores.')->group(function () {
            Route::get('/',                     [ComprasProveedorController::class, 'index'])       ->name('index');
            Route::post('/',                    [ComprasProveedorController::class, 'store'])       ->name('store');
            Route::put('/{proveedor}',          [ComprasProveedorController::class, 'update'])      ->name('update');
            Route::patch('/{proveedor}/toggle', [ComprasProveedorController::class, 'toggleEstado'])->name('toggle');
            Route::get('/pdf',                  [ComprasProveedorController::class, 'pdf'])         ->name('pdf');
            Route::get('/excel',                [ComprasProveedorController::class, 'excel'])       ->name('excel');
        });

        Route::prefix('compras/facturas')->name('compras.facturas.')->group(function () {
            Route::get('/',                  [CompraController::class, 'index']) ->name('index');
            Route::post('/',                 [CompraController::class, 'store']) ->name('store');
            Route::get('/pdf',               [CompraController::class, 'pdf'])   ->name('pdf');
            Route::get('/excel',             [CompraController::class, 'excel']) ->name('excel');
            Route::get('/{compra}',                    [CompraController::class, 'show'])              ->name('show');
            Route::get('/{compra}/pdf',               [CompraController::class, 'pdfIndividual'])    ->name('pdf-individual');
            Route::patch('/{compra}/anular',          [CompraController::class, 'anular'])            ->name('anular');
            Route::post('/{compra}/activar',          [CompraController::class, 'activar'])           ->name('activar');
            Route::get('/{compra}/etiquetas-data',                  [CompraController::class, 'etiquetasData'])           ->name('etiquetas-data');
            Route::post('/{compra}/etiquetas-pdf',                  [CompraController::class, 'generarEtiquetasPdf'])     ->name('etiquetas-pdf');
            Route::get('/{compra}/etiquetas-reimprimir',            [CompraController::class, 'reimprimirEtiquetasPdf'])  ->name('etiquetas-reimprimir');
            Route::get('/{compra}/etiquetas-listado',               [CompraController::class, 'etiquetasListado'])         ->name('etiquetas-listado');
            Route::post('/{compra}/etiquetas-reimprimir-seleccion', [CompraController::class, 'reimprimirSeleccion'])      ->name('etiquetas-reimprimir-seleccion');
            Route::get('/{compra}/verificar-anulacion',             [CompraController::class, 'verificarAnulacion'])       ->name('verificar-anulacion');
        });

        Route::prefix('compras/cuentas-pagar')->name('compras.cxp.')->group(function () {
            Route::get('/',                          [CuentaPagarController::class, 'index'])->name('index');
            Route::get('/pdf',                       [CuentaPagarController::class, 'pdf'])  ->name('pdf');
            Route::get('/excel',                     [CuentaPagarController::class, 'excel'])->name('excel');
            Route::post('/{cuentaPagar}/pagar',      [CuentaPagarController::class, 'pagar'])->name('pagar');
        });

        Route::prefix('compras/anticipos')->name('compras.anticipos.')->group(function () {
            Route::get('/',                      [AnticipoProveedorController::class, 'index'])      ->name('index');
            Route::post('/',                     [AnticipoProveedorController::class, 'store'])      ->name('store');
            Route::get('/cxp-pendientes',        [AnticipoProveedorController::class, 'cxpPendientes'])->name('cxp-pendientes');
            Route::patch('/{anticipo}/cruzar',   [AnticipoProveedorController::class, 'cruzar'])    ->name('cruzar');
            Route::patch('/{anticipo}/anular',   [AnticipoProveedorController::class, 'anular'])    ->name('anular');
        });

        Route::prefix('compras/importaciones')->name('compras.importaciones.')->group(function () {
            Route::get('/',                         [ImportacionController::class, 'index'])   ->name('index');
            Route::post('/',                        [ImportacionController::class, 'store'])   ->name('store');
            Route::get('/{importacion}/detalle',    [ImportacionController::class, 'detalle']) ->name('detalle');
            Route::put('/{importacion}',            [ImportacionController::class, 'update'])  ->name('update');
            Route::patch('/{importacion}/liquidar', [ImportacionController::class, 'liquidar'])->name('liquidar');
        });
    });

    // Inventario
    Route::middleware('permiso:inventario,ver')->group(function () {
        Route::prefix('inventario')->name('inventario.')->group(function () {
            Route::get('productos/buscar', [ProductoController::class, 'buscar'])->name('productos.buscar');
            Route::resource('productos', ProductoController::class)->except(['show']);

            Route::get('kardex/saldo', [KardexController::class, 'getSaldo'])->name('kardex.getSaldo');
            Route::get('kardex/saldos', [KardexController::class, 'saldos'])->name('kardex.saldos');
            Route::get('kardex/ajuste', [KardexController::class, 'ajuste'])->name('kardex.ajuste');
            Route::post('kardex/ajuste', [KardexController::class, 'storeAjuste'])->name('kardex.storeAjuste');
            Route::get('kardex', [KardexController::class, 'index'])->name('kardex.index');

            Route::get('traslados', [TrasladoController::class, 'index'])->name('traslados.index');
            Route::get('traslados/nuevo', [TrasladoController::class, 'create'])->name('traslados.create');
            Route::post('traslados', [TrasladoController::class, 'store'])->name('traslados.store');
            Route::post('traslados/{traslado}/confirmar', [TrasladoController::class, 'confirmar'])->name('traslados.confirmar');
            Route::post('traslados/{traslado}/anular', [TrasladoController::class, 'anular'])->name('traslados.anular');
            Route::get('traslados/{traslado}', [TrasladoController::class, 'show'])->name('traslados.show');

            Route::resource('activos', ActivoFijoController::class)->except(['show']);
            Route::get('activos/{activoFijo}', [ActivoFijoController::class, 'show'])->name('activos.show');
            Route::post('activos/{activoFijo}/depreciar', [ActivoFijoController::class, 'depreciar'])->name('activos.depreciar');

            Route::get('listas', [ListaPrecioController::class, 'index'])->name('listas.index');
            Route::put('listas/{producto}', [ListaPrecioController::class, 'update'])->name('listas.update');
            Route::post('listas/importar', [ListaPrecioController::class, 'importar'])->name('listas.importar');

            Route::get('recepciones', [RecepcionController::class, 'index'])->name('recepciones.index');
            Route::get('recepciones/buscar-compra', [RecepcionController::class, 'buscarCompra'])->name('recepciones.buscarCompra');
            Route::get('recepciones/buscar-producto', [RecepcionController::class, 'buscarProducto'])->name('recepciones.buscarProducto');
            Route::post('recepciones', [RecepcionController::class, 'store'])->name('recepciones.store');
            Route::get('recepciones/{recepcion}', [RecepcionController::class, 'show'])->name('recepciones.show');
            Route::post('recepciones/{recepcion}/escanear', [RecepcionController::class, 'escanear'])->name('recepciones.escanear');
            Route::get('recepciones/{recepcion}/etiquetas', [RecepcionController::class, 'etiquetasPendientes'])->name('recepciones.etiquetas');
            Route::post('recepciones/{recepcion}/confirmar', [RecepcionController::class, 'confirmar'])->name('recepciones.confirmar');
        });

        Route::prefix('inventario/configuracion')->name('inventario.config.')->group(function () {
            Route::resource('marcas', MarcaController::class)->except(['show', 'create', 'edit']);
            Route::resource('categorias', CategoriaProductoController::class)->except(['show', 'create', 'edit']);
            Route::resource('bodegas', BodegaController::class)->except(['show', 'create', 'edit']);
        });
    }); // cierra permiso:inventario,ver

    // Compras - Facturas
    Route::prefix('compras/facturas')->name('compras.facturas.')->group(function () {
        Route::get('/',                  [CompraController::class, 'index']) ->name('index');
        Route::post('/',                 [CompraController::class, 'store']) ->name('store');
        Route::get('/pdf',               [CompraController::class, 'pdf'])   ->name('pdf');
        Route::get('/excel',             [CompraController::class, 'excel']) ->name('excel');
        Route::get('/{compra}',                    [CompraController::class, 'show'])              ->name('show');
        Route::get('/{compra}/pdf',               [CompraController::class, 'pdfIndividual'])    ->name('pdf-individual');
        Route::patch('/{compra}/anular',          [CompraController::class, 'anular'])            ->name('anular');
        Route::post('/{compra}/activar',          [CompraController::class, 'activar'])           ->name('activar');
        Route::get('/{compra}/etiquetas-data',                  [CompraController::class, 'etiquetasData'])           ->name('etiquetas-data');
        Route::post('/{compra}/etiquetas-pdf',                  [CompraController::class, 'generarEtiquetasPdf'])     ->name('etiquetas-pdf');
        Route::get('/{compra}/etiquetas-reimprimir',            [CompraController::class, 'reimprimirEtiquetasPdf'])  ->name('etiquetas-reimprimir');
        Route::get('/{compra}/etiquetas-listado',               [CompraController::class, 'etiquetasListado'])         ->name('etiquetas-listado');
        Route::post('/{compra}/etiquetas-reimprimir-seleccion', [CompraController::class, 'reimprimirSeleccion'])      ->name('etiquetas-reimprimir-seleccion');
        Route::get('/{compra}/verificar-anulacion',             [CompraController::class, 'verificarAnulacion'])       ->name('verificar-anulacion');
        Route::post('/{compra}/anular-pago',                    [CompraController::class, 'anularPago'])                ->name('anular-pago');
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
        Route::get('/',                      [AnticipoProveedorController::class, 'index'])        ->name('index');
        Route::post('/',                     [AnticipoProveedorController::class, 'store'])        ->name('store');
        Route::get('/cxp-pendientes',        [AnticipoProveedorController::class, 'cxpPendientes'])->name('cxp-pendientes');
        Route::patch('/{anticipo}/cruzar',   [AnticipoProveedorController::class, 'cruzar'])      ->name('cruzar');
        Route::patch('/{anticipo}/anular',   [AnticipoProveedorController::class, 'anular'])      ->name('anular');
    });

    // Compras - Importaciones
    Route::prefix('compras/importaciones')->name('compras.importaciones.')->group(function () {
        Route::get('/',                              [ImportacionController::class, 'index'])          ->name('index');
        Route::post('/',                             [ImportacionController::class, 'store'])          ->name('store');
        Route::get('/{importacion}/detalle',         [ImportacionController::class, 'detalle'])        ->name('detalle');
        Route::put('/{importacion}',                 [ImportacionController::class, 'update'])         ->name('update');
        Route::patch('/{importacion}/liquidar',      [ImportacionController::class, 'liquidar'])       ->name('liquidar');
        Route::post('/{importacion}/agregar-costo',  [ImportacionController::class, 'agregarCosto'])  ->name('agregar-costo');
        Route::post('/{importacion}/crear-factura',  [ImportacionController::class, 'crearFactura'])  ->name('crear-factura');
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

    // Bancos
    Route::middleware('permiso:bancos,ver')->group(function () {
        Route::prefix('bancos/catalogo')->name('bancos.catalogo.')->group(function () {
            Route::get('/',                 [BancoCajaController::class, 'index'])       ->name('index');
            Route::post('/',                [BancoCajaController::class, 'store'])       ->name('store');
            Route::put('/{banco}',          [BancoCajaController::class, 'update'])      ->name('update');
            Route::patch('/{banco}/toggle', [BancoCajaController::class, 'toggleEstado'])->name('toggle');
            Route::delete('/{banco}',       [BancoCajaController::class, 'destroy'])     ->name('destroy');
        });

        Route::prefix('bancos/movimientos')->name('bancos.movimientos.')->group(function () {
            Route::get('/',                      [MovimientoBancarioController::class, 'index'])      ->name('index');
            Route::post('/',                     [MovimientoBancarioController::class, 'store'])      ->name('store');
            Route::patch('/{movimiento}/anular', [MovimientoBancarioController::class, 'anular'])     ->name('anular');
            Route::get('/exportar-xml',          [MovimientoBancarioController::class, 'exportarXml'])->name('exportar-xml');
        });

        Route::prefix('bancos/cajas')->name('bancos.cajas.')->group(function () {
            Route::get('/',                  [CierreCajaController::class, 'index']) ->name('index');
            Route::post('/abrir',            [CierreCajaController::class, 'abrir']) ->name('abrir');
            Route::patch('/{cierre}/cerrar', [CierreCajaController::class, 'cerrar'])->name('cerrar');
        });

        Route::prefix('bancos/datafast')->name('bancos.datafast.')->group(function () {
            Route::get('/',                  [DatafastController::class, 'index'])    ->name('index');
            Route::post('/lote',             [DatafastController::class, 'storeLote'])->name('lote');
            Route::patch('/{lote}/liquidar', [DatafastController::class, 'liquidar']) ->name('liquidar');
        });

        Route::prefix('bancos/conciliaciones')->name('bancos.conciliaciones.')->group(function () {
            Route::get('/',                             [ConciliacionController::class, 'index'])           ->name('index');
            Route::post('/',                            [ConciliacionController::class, 'store'])           ->name('store');
            Route::get('/{conciliacion}',               [ConciliacionController::class, 'show'])            ->name('show');
            Route::patch('/{conciliacion}/conciliar',   [ConciliacionController::class, 'marcarConciliada'])->name('conciliar');
        });

        Route::prefix('bancos/cheques')->name('bancos.cheques.')->group(function () {
            Route::get('/',                      [ChequesController::class, 'index'])        ->name('index');
            Route::post('/',                     [ChequesController::class, 'store'])        ->name('store');
            Route::patch('/{cheque}/estado',     [ChequesController::class, 'cambiarEstado'])->name('estado');
        });

        Route::prefix('bancos/reportes')->name('bancos.reportes.')->group(function () {
            Route::get('/',                [BancoReporteController::class, 'index'])             ->name('index');
            Route::get('/estado-cuenta',   [BancoReporteController::class, 'estadoCuenta'])      ->name('estado-cuenta');
            Route::get('/movimientos',     [BancoReporteController::class, 'reporteMovimientos']) ->name('movimientos');
            Route::get('/caja-chica',      [BancoReporteController::class, 'reporteCajaChica'])  ->name('caja-chica');
        });
    }); // cierra permiso:bancos,ver

    // RRHH
    Route::middleware('permiso:rrhh,ver')->prefix('rrhh')->name('rrhh.')->group(function () {

        Route::prefix('colaboradores')->name('colaboradores.')->group(function () {
            Route::get('/',                          [ColaboradorController::class, 'index'])  ->name('index');
            Route::post('/',                         [ColaboradorController::class, 'store'])  ->name('store');
            Route::put('/{colaborador}',             [ColaboradorController::class, 'update']) ->name('update');
            Route::patch('/{colaborador}/toggle',    [ColaboradorController::class, 'toggle']) ->name('toggle');
        });

        Route::prefix('asistencia')->name('asistencia.')->group(function () {
            Route::get('/',        [AsistenciaController::class, 'index'])           ->name('index');
            Route::post('/entrada',[AsistenciaController::class, 'registrarEntrada'])->name('entrada');
            Route::post('/salida', [AsistenciaController::class, 'registrarSalida']) ->name('salida');
        });

        Route::prefix('horas-extras')->name('horas-extras.')->group(function () {
            Route::get('/',                            [HorasExtrasController::class, 'index'])   ->name('index');
            Route::patch('/{horaExtra}/aprobar',       [HorasExtrasController::class, 'aprobar']) ->name('aprobar');
            Route::patch('/{horaExtra}/rechazar',      [HorasExtrasController::class, 'rechazar'])->name('rechazar');
        });

        Route::prefix('nomina')->name('nomina.')->group(function () {
            Route::get('/',                        [NominaController::class, 'index'])         ->name('index');
            Route::post('/generar',                [NominaController::class, 'generar'])       ->name('generar');
            Route::get('/{id}',                    [NominaController::class, 'show'])          ->name('show');
            Route::put('/{id}/detalle/{did}',      [NominaController::class, 'update'])        ->name('update');
            Route::post('/{id}/procesar',          [NominaController::class, 'procesar'])      ->name('procesar');
            Route::post('/{id}/pagar',             [NominaController::class, 'pagar'])         ->name('pagar');
            Route::delete('/{id}',                 [NominaController::class, 'destroy'])       ->name('destroy');
            Route::get('/{id}/pdf/{did}',          [NominaController::class, 'pdfIndividual']) ->name('pdf-individual');
            Route::get('/{id}/zip',                [NominaController::class, 'pdfMasivo'])     ->name('pdf-masivo');
        });
    });

    // Ventas
    Route::middleware('permiso:ventas,ver')->group(function () {
        Route::post('ventas/aprobacion/validar', [AprobacionController::class, 'validar'])->name('ventas.aprobacion.validar');

        Route::prefix('ventas/facturas')->name('ventas.facturas.')->group(function () {
            Route::get('/',                       [FacturaController::class, 'index'])          ->name('index');
            Route::get('/crear',                  [FacturaController::class, 'create'])         ->name('create');
            Route::post('/',                      [FacturaController::class, 'store'])          ->name('store');
            Route::get('/{factura}',              [FacturaController::class, 'show'])           ->name('show');
            Route::patch('/{factura}/anular',     [FacturaController::class, 'anular'])         ->name('anular');
            Route::post('/{factura}/enviar-sri',  [FacturaController::class, 'enviarSri'])      ->name('enviar-sri');
            Route::post('/cliente-guardar',       [FacturaController::class, 'clienteGuardar']) ->name('cliente-guardar');
        });

        Route::prefix('ventas/prefacturas')->name('ventas.prefacturas.')->group(function () {
            Route::get('/',                                  [PrefacturaController::class, 'index'])              ->name('index');
            Route::get('/crear',                             [PrefacturaController::class, 'create'])             ->name('create');
            Route::post('/',                                 [PrefacturaController::class, 'store'])              ->name('store');
            Route::get('/{prefactura}',                      [PrefacturaController::class, 'show'])               ->name('show');
            Route::post('/{prefactura}/abonar',              [PrefacturaController::class, 'abonar'])             ->name('abonar');
            Route::post('/{prefactura}/convertir-a-factura', [PrefacturaController::class, 'convertirAFactura']) ->name('convertir');
        });

        Route::prefix('ventas/proformas')->name('ventas.proformas.')->group(function () {
            Route::get('/',                                 [ProformaController::class, 'index'])             ->name('index');
            Route::get('/crear',                            [ProformaController::class, 'create'])            ->name('create');
            Route::post('/',                                [ProformaController::class, 'store'])             ->name('store');
            Route::get('/{proforma}',                       [ProformaController::class, 'show'])              ->name('show');
            Route::delete('/{proforma}',                    [ProformaController::class, 'destroy'])           ->name('destroy');
            Route::post('/{proforma}/convertir-a-factura',  [ProformaController::class, 'convertirAFactura'])->name('convertir');
        });

        Route::prefix('ventas/notas-credito')->name('ventas.notas-credito.')->group(function () {
            Route::get('/',                            [NotaCreditoController::class, 'index'])     ->name('index');
            Route::get('/crear',                       [NotaCreditoController::class, 'create'])    ->name('create');
            Route::post('/',                           [NotaCreditoController::class, 'store'])     ->name('store');
            Route::get('/{notaCredito}',               [NotaCreditoController::class, 'show'])      ->name('show');
            Route::post('/{notaCredito}/enviar-sri',   [NotaCreditoController::class, 'enviarSri']) ->name('enviar-sri');
        });

        Route::prefix('ventas/cxc')->name('ventas.cxc.')->group(function () {
            Route::get('/',                             [CuentaCobrarController::class, 'index'])          ->name('index');
            Route::get('/{cuentaCobrar}',               [CuentaCobrarController::class, 'show'])           ->name('show');
            Route::post('/{cuentaCobrar}/cobrar',       [CuentaCobrarController::class, 'registrarCobro']) ->name('cobrar');
            Route::patch('/{cuentaCobrar}/castigo',     [CuentaCobrarController::class, 'castigo'])        ->name('castigo');
        });

        Route::prefix('ventas/retenciones')->name('ventas.retenciones.')->group(function () {
            Route::get('/',                           [RetencionController::class, 'index'])     ->name('index');
            Route::get('/crear',                      [RetencionController::class, 'create'])    ->name('create');
            Route::post('/',                          [RetencionController::class, 'store'])     ->name('store');
            Route::get('/{retencion}',                [RetencionController::class, 'show'])      ->name('show');
            Route::post('/{retencion}/enviar-sri',    [RetencionController::class, 'enviarSri']) ->name('enviar-sri');
        });

        Route::prefix('ventas/guias-remision')->name('ventas.guias-remision.')->group(function () {
            Route::get('/',                            [GuiaRemisionController::class, 'index'])     ->name('index');
            Route::get('/crear',                       [GuiaRemisionController::class, 'create'])    ->name('create');
            Route::post('/',                           [GuiaRemisionController::class, 'store'])     ->name('store');
            Route::get('/{guiaRemision}',              [GuiaRemisionController::class, 'show'])      ->name('show');
            Route::post('/{guiaRemision}/enviar-sri',  [GuiaRemisionController::class, 'enviarSri']) ->name('enviar-sri');
        });
    });
});
