<?php

namespace App\Providers;

use App\Models\Usuario;
use App\Observers\UsuarioObserver;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Usuario::observe(UsuarioObserver::class);

        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }
    }
}
