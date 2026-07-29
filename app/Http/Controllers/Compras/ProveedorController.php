<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Exports\ProveedoresExport;
use App\Jobs\ExportarProveedoresJob;
use App\Models\Empresa;
use App\Models\Proveedor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ProveedorController extends Controller
{
    // DomPDF/PhpSpreadsheet no escalan bien a tablas muy grandes (mismo
    // hallazgo confirmado con Facturas de Compra: DomPDF agota memory_limit
    // en Cellmap::resolve_border() en tablas de cientos/miles de filas). Un
    // solo umbral sirve para Excel y PDF: 1 proveedor = 1 fila en ambos
    // formatos (a diferencia de Asientos, que cuenta líneas de detalle
    // distinto de asientos).
    //
    // Calibrado con curl real (no tinker) contra esta plantilla específica
    // (pdf.proveedores), no copiado del valor de Facturas de Compra: 300
    // filas ~3.7s, 500 filas ~6.7s (aceptable), 800 filas ~13.2s (demasiado
    // lento para el camino "rápido" — ya no se siente instantáneo), 1200
    // filas revienta memory_limit igual que en Compras. 500 es el punto
    // donde el camino síncrono sigue sintiéndose rápido.
    private const MAX_FILAS_EXPORT = 500;

    private function queryFiltrada(Request $request)
    {
        $empresaId = session('empresa_activa_id');
        $query = Proveedor::where('empresa_id', $empresaId);

        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado === 'activo');
        }
        if ($request->filled('credito')) {
            $query->where('tiene_credito', $request->credito === 'con');
        }
        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->where('razon_social', 'ilike', "%{$q}%")
                   ->orWhere('identificacion', 'ilike', "%{$q}%")
                   ->orWhere('nombre_comercial', 'ilike', "%{$q}%")
                   ->orWhere('email', 'ilike', "%{$q}%")
            );
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        // Carga bajo demanda: mismo patrón que Asientos/Plan de Cuentas/
        // Facturas de Compra — la query solo se ejecuta cuando el usuario
        // dispara una búsqueda explícita (botón lupa del FilterToolbar).
        $proveedores = null;

        if ($request->boolean('buscado')) {
            $proveedores = $this->queryFiltrada($request)
                ->orderBy('razon_social')
                ->get()
                ->map(fn($p) => [
                    'id'               => $p->id,
                    'tipo'             => $p->tipo,
                    'tipo_identificacion' => $p->tipo_identificacion,
                    'identificacion'   => $p->identificacion,
                    'razon_social'     => $p->razon_social,
                    'nombre_comercial' => $p->nombre_comercial,
                    'email'            => $p->email,
                    'telefono'         => $p->telefono,
                    'direccion'        => $p->direccion,
                    'ciudad'           => $p->ciudad,
                    'pais'             => $p->pais,
                    'divisa'           => $p->divisa,
                    'tiene_credito'    => $p->tiene_credito,
                    'dias_credito'     => $p->dias_credito,
                    'estado'           => $p->estado,
                    'saldo_pendiente'  => $p->saldo_pendiente,
                ]);
        }

        return Inertia::render('Compras/Proveedores/Index', [
            'proveedores' => $proveedores,
            'filtros'     => $request->only(['buscar', 'tipo', 'estado', 'credito']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'tipo'             => 'required|in:nacional,internacional',
            'tipo_identificacion' => 'required|string|max:20',
            'identificacion'   => 'required|string|max:20',
            'razon_social'     => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:200',
            'email'            => 'nullable|email|max:200',
            'telefono'         => 'nullable|string|max:20',
            'direccion'        => 'nullable|string|max:300',
            'ciudad'           => 'nullable|string|max:100',
            'pais'             => 'nullable|string|max:100',
            'divisa'           => 'nullable|string|max:10',
            'tiene_credito'    => 'boolean',
            'dias_credito'     => 'integer|min:0|max:365',
        ]);

        $existe = Proveedor::where('empresa_id', $empresaId)
            ->where('identificacion', $request->identificacion)
            ->whereNull('deleted_at')
            ->exists();

        if ($existe) {
            return back()->with('error',
                "Ya existe un proveedor con identificación {$request->identificacion}.");
        }

        Proveedor::create([
            ...$request->only([
                'tipo', 'tipo_identificacion', 'identificacion', 'razon_social',
                'nombre_comercial', 'email', 'telefono', 'direccion', 'ciudad',
                'pais', 'divisa', 'tiene_credito', 'dias_credito',
            ]),
            'empresa_id' => $empresaId,
            'estado'     => true,
        ]);

        return back()->with('success',
            "Proveedor {$request->razon_social} creado correctamente.");
    }

    public function update(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $request->validate([
            'razon_social'     => 'required|string|max:200',
            'nombre_comercial' => 'nullable|string|max:200',
            'email'            => 'nullable|email|max:200',
            'telefono'         => 'nullable|string|max:20',
            'direccion'        => 'nullable|string|max:300',
            'ciudad'           => 'nullable|string|max:100',
            'pais'             => 'nullable|string|max:100',
            'divisa'           => 'nullable|string|max:10',
            'tiene_credito'    => 'boolean',
            'dias_credito'     => 'integer|min:0|max:365',
        ]);

        $proveedor->update($request->only([
            'razon_social', 'nombre_comercial', 'email', 'telefono',
            'direccion', 'ciudad', 'pais', 'divisa',
            'tiene_credito', 'dias_credito',
        ]));

        return back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function toggleEstado(Proveedor $proveedor): RedirectResponse
    {
        if ($proveedor->estado && $proveedor->saldo_pendiente > 0) {
            return back()->with('error',
                "No se puede desactivar: tiene \${$proveedor->saldo_pendiente} pendiente de pago.");
        }
        $proveedor->update(['estado' => !$proveedor->estado]);
        $accion = $proveedor->estado ? 'activado' : 'desactivado';
        return back()->with('success', "Proveedor {$accion} correctamente.");
    }

    public function pdf(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $total = $this->queryFiltrada($request)->count();
        if ($total > self::MAX_FILAS_EXPORT) {
            return response()->json([
                'message' => "Hay {$total} proveedores con estos filtros — demasiados para generar un PDF de una vez " .
                    '(máximo ' . self::MAX_FILAS_EXPORT . '). Aplica un filtro más específico.',
            ], 422);
        }

        $proveedores = $this->queryFiltrada($request)->orderBy('razon_social')->get();
        $empresa     = Empresa::find($empresaId);
        $pdf = Pdf::loadView('pdf.proveedores', compact('proveedores', 'empresa'))
            ->setPaper('a4', 'landscape');
        return $pdf->stream('proveedores-' . now()->format('Y-m-d') . '.pdf');
    }

    public function excel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $filtros   = $request->only(['tipo', 'estado', 'credito', 'buscar']);

        $total = $this->queryFiltrada($request)->count();
        if ($total > self::MAX_FILAS_EXPORT) {
            return response()->json([
                'message' => "Hay {$total} proveedores con estos filtros — demasiados para exportar de una vez " .
                    '(máximo ' . self::MAX_FILAS_EXPORT . '). Aplica un filtro más específico.',
            ], 422);
        }

        return Excel::download(
            new ProveedoresExport((int) $empresaId, $filtros),
            'proveedores-' . now()->format('Y-m-d') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    // Chequeo liviano (sin generar nada) para que el frontend decida, ANTES
    // de pedir Excel/PDF, si el filtro actual entra en el camino rápido
    // (síncrono) o necesita el camino de segundo plano (Job en cola) — mismo
    // patrón que AsientoContableController::contarExportables().
    public function contarExportables(Request $request): JsonResponse
    {
        $total = $this->queryFiltrada($request)->count();

        return response()->json([
            'total'  => $total,
            'limite' => self::MAX_FILAS_EXPORT,
            'excede' => $total > self::MAX_FILAS_EXPORT,
        ]);
    }

    // Camino de segundo plano: sin límite de filas (ExportarProveedoresJob
    // no aplica MAX_FILAS_EXPORT), genera el archivo completo en el worker
    // de colas y avisa por notificación cuando está listo — ver CLAUDE.md
    // para el requisito de QUEUE_CONNECTION + `php artisan queue:work`.
    public function exportarSegundoPlano(Request $request): RedirectResponse
    {
        $request->validate(['formato' => 'required|in:excel,pdf']);

        $empresaId = session('empresa_activa_id');
        $filtros   = $request->only(['tipo', 'estado', 'credito', 'buscar']);

        ExportarProveedoresJob::dispatch((int) $empresaId, (int) Auth::id(), $filtros, $request->string('formato')->toString());

        return back()->with('success',
            'Tu exportación de Proveedores se está procesando en segundo plano. Te avisaremos por notificación cuando esté lista para descargar.');
    }

    // Sirve el archivo generado por ExportarProveedoresJob. Autorización
    // simple: el nombre de archivo lleva el usuario_id como prefijo (ver el
    // Job), y basename() descarta cualquier intento de path traversal.
    public function descargarExportacion(string $archivo): \Illuminate\Http\Response
    {
        $archivo = basename($archivo);

        if (!str_starts_with($archivo, Auth::id() . '_')) {
            abort(403, 'No tienes acceso a este archivo.');
        }

        $ruta = ExportarProveedoresJob::CARPETA . "/{$archivo}";
        if (!Storage::disk('local')->exists($ruta)) {
            abort(404, 'El archivo expiró o ya no está disponible (las exportaciones se conservan 48 horas). Genera la exportación nuevamente.');
        }

        $extension      = pathinfo($archivo, PATHINFO_EXTENSION);
        $nombreDescarga = 'proveedores-' . now()->format('Y-m-d') . ".{$extension}";

        return Storage::disk('local')->download($ruta, $nombreDescarga);
    }
}
