<?php

namespace App\Jobs\Concerns;

/**
 * Arma la URL absoluta de descarga para la notificación de un Job en
 * segundo plano usando el host REAL capturado por el controller en el
 * momento del dispatch ($request->getSchemeAndHttpHost()), nunca con
 * route() a secas: dentro de un Job no hay request activa, así que route()
 * cae al host fijo de config('app.url'), que puede no coincidir con el que
 * realmente sirvió la petición. Ese descuido rompió antes la descarga de
 * Excel de Asientos y reapareció en Movimientos Bancarios porque el fix se
 * aplicó copy-paste job por job en vez de centralizarse — de aquí en
 * adelante, todo Job que notifique un link de descarga debe pasar por esto.
 */
trait ConstruyeUrlDescargaExportacion
{
    private function urlDescarga(string $baseUrl, string $routeName, array $parametros): string
    {
        return $baseUrl . route($routeName, $parametros, false);
    }
}
