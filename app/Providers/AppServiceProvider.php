<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Observers\UsuarioObserver;
use App\Services\Contracts\InventarioServiceInterface;
use App\Services\InventarioService;
use App\Services\SecuencialService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

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

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
