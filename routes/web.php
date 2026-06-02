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
use App\Http\Controllers\Compras\ProveedorController;
use App\Http\Controllers\Compras\CompraController;
use App\Http\Controllers\Compras\CuentaPagarController;
use App\Http\Controllers\Compras\ImportacionController;
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
        Route::get('/',                     [ProveedorController::class, 'index'])       ->name('index');
        Route::post('/',                    [ProveedorController::class, 'store'])       ->name('store');
        Route::put('/{proveedor}',          [ProveedorController::class, 'update'])      ->name('update');
        Route::patch('/{proveedor}/toggle', [ProveedorController::class, 'toggleEstado'])->name('toggle');
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

});
