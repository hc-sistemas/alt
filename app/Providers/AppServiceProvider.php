<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Observers\UsuarioObserver;
use App\Services\Contracts\InventarioServiceInterface;
use App\Services\InventarioService;
use App\Services\SecuencialService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventarioServiceInterface::class, InventarioService::class);
        $this->app->singleton(SecuencialService::class);
    }

    public function boot(): void
    {
        Usuario::observe(UsuarioObserver::class);

        // Aislamiento por empresa (02-01 §2.1): «exists_empresa:tabla» exige que el ID recibido exista Y pertenezca a la
        // empresa activa. Evita usar el banco, cliente, proveedor, bodega… de la otra empresa enviando su ID a mano.
        Validator::extend('exists_empresa', function ($attribute, $value, $parametros) {
            return is_numeric($value) && DB::table($parametros[0])
                ->where($parametros[1] ?? 'id', $value)
                ->where('empresa_id', session('empresa_activa_id') ?: 0)
                ->exists();
        }, 'El registro seleccionado no pertenece a la empresa activa.');

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
