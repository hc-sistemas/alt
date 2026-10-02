<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\VentasResumenExport;
use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Support\LandingRoute;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class DashboardController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $user = Auth::user();
        $empresaId = session('empresa_activa_id');

        // El Dashboard se puede ocultar por perfil (CHECKLIST_ERRORES_COMPLICACIONES.md,
        // ítem C3): quien no tenga permiso aterriza en el primer módulo que sí
        // vea, en vez de forzarlo siempre a esta pantalla.
        if (!LandingRoute::puedeVerDashboard($user, $empresaId)) {
            return redirect()->route(LandingRoute::primeraDisponible($user, $empresaId));
        }

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'ventas_hoy' => 0,
                'ventas_ayer' => 0,
                'meta_mes' => 0,
                'ventas_mes' => 0,
            ],
            'notificaciones_count' => $user->notificacionesNoLeidas()->count(),
        ]);
    }

    /**
     * Ventas mensual/trimestral/anual + descarga PDF/Excel (CHECKLIST_ERRORES_COMPLICACIONES.md,
     * ítem F1). Decisión: el dashboard descarga un resumen de ventas del
     * período (PDF/Excel), no los XML del SRI. `fecha` es cualquier fecha
     * dentro del período que se quiere ver (por defecto, hoy); sirve para
     * "el mes pasado", "el trimestre anterior", etc. sin más parámetros.
     */
    public function resumenVentas(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        [$periodo, $desde, $hasta] = $this->rangoPeriodo($request);

        $query = Factura::where('empresa_id', $empresaId)
            ->where('estado', 'activa')
            ->whereBetween('fecha_emision', [$desde->toDateString(), $hasta->toDateString()]);

        return response()->json([
            'periodo'      => $periodo,
            'fecha_desde'  => $desde->toDateString(),
            'fecha_hasta'  => $hasta->toDateString(),
            'total_ventas' => (float) $query->clone()->sum('total'),
            'cantidad'     => $query->clone()->count(),
        ]);
    }

    public function resumenVentasPdf(Request $request)
    {
        $empresaId = session('empresa_activa_id');
        [$periodo, $desde, $hasta] = $this->rangoPeriodo($request);

        $empresa = \App\Models\Empresa::find($empresaId);
        $facturas = Factura::with('cliente')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'activa')
            ->whereBetween('fecha_emision', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha_emision')
            ->get();

        $pdf = Pdf::loadView('pdf.dashboard-ventas-resumen', [
            'empresa'  => $empresa,
            'periodo'  => $periodo,
            'desde'    => $desde,
            'hasta'    => $hasta,
            'facturas' => $facturas,
            'total'    => $facturas->sum('total'),
        ])->setPaper('letter', 'portrait');

        return $pdf->stream("ventas-{$periodo}-{$desde->format('Ymd')}-{$hasta->format('Ymd')}.pdf");
    }

    public function resumenVentasExcel(Request $request)
    {
        $empresaId = session('empresa_activa_id');
        [$periodo, $desde, $hasta] = $this->rangoPeriodo($request);

        return Excel::download(
            new VentasResumenExport($empresaId, $desde, $hasta),
            "ventas-{$periodo}-{$desde->format('Ymd')}-{$hasta->format('Ymd')}.xlsx",
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * @return array{0: string, 1: Carbon, 2: Carbon} [periodo normalizado, desde, hasta]
     */
    private function rangoPeriodo(Request $request): array
    {
        $periodo = $request->input('periodo', 'mensual');
        if (!in_array($periodo, ['mensual', 'trimestral', 'anual'], true)) {
            $periodo = 'mensual';
        }

        $fechaRef = $request->filled('fecha') ? Carbon::parse($request->input('fecha')) : now();

        $desde = match ($periodo) {
            'trimestral' => $fechaRef->clone()->firstOfQuarter(),
            'anual'      => $fechaRef->clone()->startOfYear(),
            default      => $fechaRef->clone()->startOfMonth(),
        };
        $hasta = match ($periodo) {
            'trimestral' => $fechaRef->clone()->lastOfQuarter(),
            'anual'      => $fechaRef->clone()->endOfYear(),
            default      => $fechaRef->clone()->endOfMonth(),
        };

        return [$periodo, $desde->startOfDay(), $hasta->endOfDay()];
    }
}
