<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Exports\CxPExport;
use App\Jobs\ExportarCxPJob;
use App\Models\BancoCaja;
use App\Models\CuentaPagar;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\Proveedor;
use App\Services\AsientoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CuentaPagarController extends Controller
{
    // Calibrado con curl real (no tinker) contra la plantilla pdf.cxp
    // específica (php artisan serve puerto real, no simulado): 300 filas
    // ~3.35s/140MB, 600 filas ~7.1s/264MB (sigue sintiéndose rápido), 800
    // filas ~10.65s/364MB (empieza a notarse), 1200 filas revienta
    // memory_limit (500 sin cuerpo, mismo patrón de crash ya visto en
    // Facturas de Compra/Proveedores). 600 es el punto de corte, igual que
    // para Facturas de Compra y Proveedores cada plantilla Blade se midió
    // por separado — no se asumió el número de otra pantalla.
    private const MAX_FILAS_EXPORT = 600;

    private function queryFiltrada(Request $request)
    {
        $empresaId = session('empresa_activa_id');
        $query = CuentaPagar::with(['proveedor', 'compra'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } else {
            $query->whereIn('estado', ['pendiente', 'parcial']);
        }
        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }
        if ($request->filled('periodo')) {
            $hoy = now();
            match ($request->periodo) {
                'hoy'    => $query->whereDate('fecha_vencimiento', $hoy),
                'semana' => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfWeek(), $hoy->copy()->endOfWeek()
                ]),
                'mes'    => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()
                ]),
                'anio'   => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()
                ]),
                'vencidas' => $query->where('fecha_vencimiento', '<', $hoy),
                default  => null,
            };
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_vencimiento', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_vencimiento', '<=', $request->fecha_hasta);
        }
        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->whereHas('proveedor', fn($p) => $p->where('razon_social', 'ilike', "%{$q}%"))
                   ->orWhereHas('compra', fn($c) => $c->where('num_documento', 'ilike', "%{$q}%"))
            );
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        // Carga bajo demanda: mismo patrón que Asientos/Plan de Cuentas/
        // Facturas de Compra/Proveedores — la query solo se ejecuta cuando
        // el usuario dispara una búsqueda explícita (botón lupa).
        $cxp = null;

        if ($request->boolean('buscado')) {
            $cxp = $this->queryFiltrada($request)->orderBy('fecha_vencimiento')->get()
                ->map(fn($c) => [
                    'id'               => $c->id,
                    'compra_id'        => $c->compra_id,
                    'proveedor'        => $c->proveedor?->razon_social,
                    'num_documento'    => $c->compra?->num_documento,
                    'monto'            => $c->monto,
                    'saldo'            => $c->saldo,
                    'fecha_emision'    => $c->fecha_emision?->format('d/m/Y'),
                    'fecha_vencimiento'=> $c->fecha_vencimiento?->format('d/m/Y'),
                    'estado'           => $c->estado,
                    'compra_anulada'   => $c->compra?->estado === 'anulada',
                    'urgencia'         => $c->urgencia,
                    'color_urgencia'   => $c->color_urgencia,
                    'dias_vencimiento' => $c->dias_vencimiento,
                ]);
        }

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'saldo_actual']);

        return Inertia::render('Compras/CuentasPagar/Index', [
            'cxp'         => $cxp,
            'proveedores' => $proveedores,
            'bancos'      => $bancos,
            'filtros'     => $request->only(['buscar', 'estado', 'proveedor_id', 'periodo', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function pagar(Request $request, CuentaPagar $cuentaPagar): RedirectResponse
    {
        $request->validate([
            'monto_pago'    => "required|numeric|min:0.01|max:{$cuentaPagar->saldo}",
            'banco_caja_id' => 'required|exists:bancos_cajas,id',
            'fecha_pago'    => 'required|date',
            'referencia'    => 'nullable|string|max:100',
        ]);

        if ($cuentaPagar->estado === 'pagada') {
            return back()->with('error', 'Esta cuenta ya está pagada.');
        }

        $banco = BancoCaja::findOrFail($request->banco_caja_id);

        if ((float) $banco->saldo_actual < (float) $request->monto_pago) {
            return back()->with('error',
                "Saldo insuficiente en {$banco->nombre}. " .
                'Disponible: $' . number_format((float) $banco->saldo_actual, 2) . '. ' .
                'Requerido: $' . number_format((float) $request->monto_pago, 2) . '.'
            );
        }

        DB::transaction(function () use ($request, $cuentaPagar, $banco) {
            $monto  = (float) $request->monto_pago;

            $nuevoSaldo  = max(0, (float) $cuentaPagar->saldo - $monto);
            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagada' : 'parcial';

            $cuentaPagar->update(['saldo' => $nuevoSaldo, 'estado' => $nuevoEstado]);

            if ($nuevoEstado === 'pagada' && $cuentaPagar->compra) {
                $cuentaPagar->compra->update(['tiene_pago' => true]);
            }

            $movimiento = MovimientoBancario::create([
                'empresa_id'     => $cuentaPagar->empresa_id,
                'banco_caja_id'  => $banco->id,
                'tipo'           => 'egreso',
                'sub_tipo'       => 'pago_proveedor',
                'fecha'          => $request->fecha_pago,
                'monto'          => $monto,
                'persona_tipo'   => 'proveedor',
                'persona_id'     => $cuentaPagar->proveedor_id,
                'beneficiario'   => $cuentaPagar->proveedor?->razon_social,
                'num_documento'  => $cuentaPagar->compra?->num_documento,
                'centro_costo_id' => $cuentaPagar->compra?->centro_costo_id,
                'descripcion'    => $request->referencia ?? 'Pago CxP',
                'documento_tipo' => 'COMPRA',
                'documento_id'   => $cuentaPagar->compra_id,
                'created_by'     => Auth::id(),
            ]);

            $banco->actualizarSaldo($monto, 'egreso');

            app(AsientoService::class)->pagoProveedor(
                $cuentaPagar->empresa_id,
                $cuentaPagar->id,
                $request->referencia ?? "Pago #{$movimiento->id}",
                $monto,
                $cuentaPagar->compra?->centro_costo_id,
                $request->fecha_pago,
            );
        });

        return back()->with('success', 'Pago registrado correctamente.');
    }

    public function pdf(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $total = $this->queryFiltrada($request)->count();
        if ($total > self::MAX_FILAS_EXPORT) {
            return response()->json([
                'message' => "Hay {$total} cuentas por pagar con estos filtros — demasiadas para generar un PDF de una vez " .
                    '(máximo ' . self::MAX_FILAS_EXPORT . '). Aplica un filtro más específico.',
            ], 422);
        }

        $cxp     = $this->queryFiltrada($request)->orderBy('fecha_vencimiento')->get();
        $empresa = Empresa::find($empresaId);
        $pdf = Pdf::loadView('pdf.cxp', compact('cxp', 'empresa'))->setPaper('a4', 'landscape');
        return $pdf->stream('cuentas-pagar-' . now()->format('Y-m-d') . '.pdf');
    }

    public function excel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $filtros   = $request->only(['estado', 'proveedor_id', 'periodo', 'fecha_desde', 'fecha_hasta', 'buscar']);

        $total = $this->queryFiltrada($request)->count();
        if ($total > self::MAX_FILAS_EXPORT) {
            return response()->json([
                'message' => "Hay {$total} cuentas por pagar con estos filtros — demasiadas para exportar de una vez " .
                    '(máximo ' . self::MAX_FILAS_EXPORT . '). Aplica un filtro más específico.',
            ], 422);
        }

        return Excel::download(
            new CxPExport((int) $empresaId, $filtros),
            'cuentas-pagar-' . now()->format('Y-m-d') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    // Chequeo liviano (sin generar nada) para que el frontend decida, ANTES
    // de pedir Excel/PDF, si el filtro actual entra en el camino rápido
    // (síncrono) o necesita el camino de segundo plano — mismo patrón que
    // AsientoContableController::contarExportables()/ProveedorController.
    public function contarExportables(Request $request): JsonResponse
    {
        $total = $this->queryFiltrada($request)->count();

        return response()->json([
            'total'  => $total,
            'limite' => self::MAX_FILAS_EXPORT,
            'excede' => $total > self::MAX_FILAS_EXPORT,
        ]);
    }

    // Camino de segundo plano: sin límite de filas, genera el archivo
    // completo en el worker de colas y avisa por notificación cuando está
    // listo. El link de descarga se construye con el host REAL de esta
    // request ($request->getSchemeAndHttpHost()), no con config('app.url')
    // — un Job corre sin request activo, así que route() ahí cae al host
    // fijo de config/app.php, que puede no ser el que realmente sirvió la
    // petición (ej. en desarrollo con `php artisan serve --port=X` distinto
    // al APP_URL del .env). Se captura el host aquí, en el controller,
    // donde sí hay una request real, y se pasa al Job.
    public function exportarSegundoPlano(Request $request): RedirectResponse
    {
        $request->validate(['formato' => 'required|in:excel,pdf']);

        $empresaId = session('empresa_activa_id');
        $filtros   = $request->only(['estado', 'proveedor_id', 'periodo', 'fecha_desde', 'fecha_hasta', 'buscar']);
        $baseUrl   = $request->getSchemeAndHttpHost();

        ExportarCxPJob::dispatch(
            (int) $empresaId,
            (int) Auth::id(),
            $filtros,
            $request->string('formato')->toString(),
            $baseUrl,
        );

        return back()->with('success',
            'Tu exportación de Cuentas por Pagar se está procesando en segundo plano. Te avisaremos por notificación cuando esté lista para descargar.');
    }

    // Sirve el archivo generado por ExportarCxPJob. Autorización simple: el
    // nombre de archivo lleva el usuario_id como prefijo (ver el Job), y
    // basename() descarta cualquier intento de path traversal.
    //
    // Firma de retorno: Storage::disk('local')->download() en realidad
    // devuelve un StreamedResponse (Symfony), no un Illuminate\Http\Response
    // — confirmado con un 500 real (TypeError) al descargar el .xlsx
    // generado por este mismo endpoint. Se usa el tipo amplio
    // \Symfony\Component\HttpFoundation\Response, que sí cubre
    // StreamedResponse/BinaryFileResponse, igual que ya hace
    // AsientoContableController::descargarExportacion() — Proveedores y
    // Facturas de Compra declaran la firma estrecha (\Illuminate\Http\Response)
    // y están expuestos al mismo bug en su propio endpoint de descarga;
    // reportado, no corregido aquí por estar fuera del alcance de esta tarea.
    public function descargarExportacion(string $archivo): \Symfony\Component\HttpFoundation\Response
    {
        $archivo = basename($archivo);

        if (!str_starts_with($archivo, Auth::id() . '_')) {
            abort(403, 'No tienes acceso a este archivo.');
        }

        $ruta = ExportarCxPJob::CARPETA . "/{$archivo}";
        if (!Storage::disk('local')->exists($ruta)) {
            abort(404, 'El archivo expiró o ya no está disponible (las exportaciones se conservan 48 horas). Genera la exportación nuevamente.');
        }

        $extension      = pathinfo($archivo, PATHINFO_EXTENSION);
        $nombreDescarga = 'cuentas-pagar-' . now()->format('Y-m-d') . ".{$extension}";

        return Storage::disk('local')->download($ruta, $nombreDescarga);
    }
}
