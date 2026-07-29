<?php

namespace App\Http\Controllers\Bancos;

use App\Exports\MovimientosExport;
use App\Http\Controllers\Controller;
use App\Jobs\ExportarMovimientosJob;
use App\Models\AsientoContable;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\PlanCuenta;
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

class MovimientoBancarioController extends Controller
{
    // Calibrado con curl real (no tinker) contra la plantilla pdf.bancos-movimientos,
    // con php artisan serve en puerto real y filtros de fecha sobre los movimientos
    // de volumen de prueba ya presentes en la BD (ver project_volumen_test_data):
    // 224 filas ~5.8s, 388 filas ~4.7s, 539 filas ~6.6s, 608 filas ~7.5s, 672 filas
    // ~8.5s, 736 filas ~10.4s (empieza a notarse, mismo punto donde CxP —con su
    // propia plantilla, distinta— cortó en 600 al ver 800 filas en 10.65s). Se corta
    // en 600 por el mismo criterio: cada plantilla se mide por separado, no se asume
    // el número de otra pantalla, pero aquí coincide con el de CxP.
    private const MAX_FILAS_EXPORT = 600;

    private const FILTROS_KEYS = [
        'banco_caja_id', 'tipo', 'fecha_desde', 'fecha_hasta', 'buscar',
        'centro_costo_id', 'persona_tipo', 'persona_id',
    ];

    public function __construct(private AsientoService $asientoService) {}

    private function queryFiltrada(Request $request)
    {
        $empresaId = session('empresa_activa_id');
        $query = MovimientoBancario::with(['bancoCaja', 'cuentaContrapartida', 'creadoPor'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('banco_caja_id')) {
            $query->where('banco_caja_id', $request->banco_caja_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }
        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->centro_costo_id);
        }
        if ($request->filled('persona_id') && $request->filled('persona_tipo')) {
            $query->where('persona_tipo', $request->persona_tipo)
                  ->where('persona_id', $request->persona_id);
        }
        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->where('descripcion',    'ilike', "%{$q}%")
                   ->orWhere('beneficiario', 'ilike', "%{$q}%")
                   ->orWhere('num_documento','ilike', "%{$q}%")
            );
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $haBuscado = $request->boolean('buscado');

        $movimientos = null;
        $stats       = null;

        if ($haBuscado) {
            $movimientos = $this->queryFiltrada($request)
                                 ->orderByDesc('fecha')
                                 ->orderByDesc('id')
                                 ->paginate(25)
                                 ->withQueryString();

            $stats = [
                'total_ingresos'       => MovimientoBancario::where('empresa_id', $empresaId)
                    ->where('tipo', 'ingreso')->where('anulado', false)->sum('monto'),
                'total_egresos'        => MovimientoBancario::where('empresa_id', $empresaId)
                    ->where('tipo', 'egreso')->where('anulado', false)->sum('monto'),
                'pendientes_conciliar' => MovimientoBancario::where('empresa_id', $empresaId)
                    ->where('conciliado', false)->where('anulado', false)->count(),
            ];
        }

        $bancos  = BancoCaja::where('empresa_id', $empresaId)
                    ->activos()->orderBy('nombre')
                    ->get(['id', 'nombre', 'tipo', 'saldo_actual']);
        $cuentas = PlanCuenta::where('permite_asientos', true)
                    ->where('estado', true)->orderBy('codigo')
                    ->get(['id', 'codigo', 'nombre']);

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social as nombre', 'identificacion']);
        $clientes = Cliente::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social as nombre', 'identificacion']);
        $centrosCosto = CentroCosto::where('empresa_id', $empresaId)
            ->where('estado', true)->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Bancos/Movimientos/Index', [
            'movimientos'  => $movimientos,
            'bancos'       => $bancos,
            'cuentas'      => $cuentas,
            'proveedores'  => $proveedores,
            'clientes'     => $clientes,
            'centrosCosto' => $centrosCosto,
            'filtros'      => $request->only(self::FILTROS_KEYS),
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'banco_caja_id'           => 'required|exists:bancos_cajas,id',
            'tipo'                    => 'required|in:ingreso,egreso',
            'sub_tipo'                => 'required|in:transferencia,cheque,efectivo,deposito',
            'fecha'                   => 'required|date',
            'monto'                   => 'required|numeric|min:0.01',
            'beneficiario'            => 'nullable|string|max:200',
            'num_documento'           => 'nullable|string|max:50',
            'descripcion'             => 'required|string|max:500',
            'cuenta_contrapartida_id' => 'required|exists:plan_cuentas,id',
        ], [
            'monto.min'                           => 'El monto debe ser mayor a 0.',
            'cuenta_contrapartida_id.required'    => 'Selecciona la cuenta contable de contrapartida.',
            'descripcion.required'                => 'La descripción es obligatoria.',
        ]);

        try {
            DB::transaction(function () use ($request, $empresaId) {
                $banco = BancoCaja::findOrFail($request->banco_caja_id);

                if ($request->tipo === 'egreso' && $banco->saldo_actual < $request->monto) {
                    throw new \Exception(
                        "Saldo insuficiente. Saldo disponible: \${$banco->saldo_actual}"
                    );
                }

                $movimiento = MovimientoBancario::create([
                    ...$request->only([
                        'banco_caja_id', 'tipo', 'sub_tipo', 'fecha', 'monto',
                        'persona_tipo', 'persona_id', 'beneficiario',
                        'num_documento', 'num_cheque', 'fecha_cheque',
                        'descripcion', 'documento_tipo', 'documento_id',
                        'cuenta_contrapartida_id', 'es_postfechado',
                    ]),
                    'empresa_id' => $empresaId,
                    'anulado'    => false,
                    'conciliado' => false,
                    'created_by' => Auth::id(),
                ]);

                $banco->actualizarSaldo($request->monto, $request->tipo);

                try {
                    $ctaBanco = $banco->cuenta_id;
                    $ctaContra = $request->cuenta_contrapartida_id;

                    if ($ctaBanco) {
                        $partidas = $request->tipo === 'ingreso'
                            ? [
                                ['cuenta_id' => $ctaBanco,  'debe' => $request->monto, 'haber' => 0, 'descripcion' => $request->descripcion],
                                ['cuenta_id' => $ctaContra, 'debe' => 0, 'haber' => $request->monto, 'descripcion' => $request->descripcion],
                              ]
                            : [
                                ['cuenta_id' => $ctaContra, 'debe' => $request->monto, 'haber' => 0, 'descripcion' => $request->descripcion],
                                ['cuenta_id' => $ctaBanco,  'debe' => 0, 'haber' => $request->monto, 'descripcion' => $request->descripcion],
                              ];

                        $asiento = $this->asientoService->crear(
                            empresaId:    $empresaId,
                            concepto:     "{$request->tipo} — {$request->descripcion}",
                            partidas:     $partidas,
                            documentoTipo:'BANCO',
                            documentoId:  $movimiento->id,
                            esAutomatico: true,
                        );
                        $movimiento->update(['asiento_id' => $asiento->id]);
                    }
                } catch (\Exception $e) {
                    // No bloquear si período cerrado o cuenta sin plan
                }
            });

            return back()->with('success', 'Movimiento registrado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $empresaId = session('empresa_activa_id');
        $filtros   = $request->only(self::FILTROS_KEYS);
        $fecha     = now()->format('Y-m-d');

        return Excel::download(
            new MovimientosExport($empresaId, $filtros),
            "movimientos-bancarios-{$fecha}.xlsx"
        );
    }

    // Respeta los mismos filtros que el listado y la exportación Excel — antes
    // este endpoint ignoraba cualquier filtro aplicado en pantalla y siempre
    // exportaba TODOS los movimientos no anulados de la empresa.
    public function exportarXml(Request $request): \Illuminate\Http\Response
    {
        $empresaId   = session('empresa_activa_id');
        $movimientos = $this->queryFiltrada($request)
            ->where('anulado', false)
            ->orderByDesc('fecha')
            ->get();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<movimientos_bancarios>' . "\n";
        $xml .= '  <empresa_id>' . $empresaId . '</empresa_id>' . "\n";
        $xml .= '  <generado>' . now()->format('Y-m-d H:i:s') . '</generado>' . "\n";
        $xml .= '  <total>' . $movimientos->count() . '</total>' . "\n";

        foreach ($movimientos as $m) {
            $xml .= '  <movimiento>' . "\n";
            $xml .= '    <id>'            . $m->id                                                  . '</id>' . "\n";
            $xml .= '    <fecha>'         . ($m->fecha?->format('Y-m-d') ?? '')                     . '</fecha>' . "\n";
            $xml .= '    <banco>'         . htmlspecialchars($m->bancoCaja?->nombre ?? '')           . '</banco>' . "\n";
            $xml .= '    <tipo>'          . $m->tipo                                                 . '</tipo>' . "\n";
            $xml .= '    <sub_tipo>'      . ($m->sub_tipo ?? '')                                     . '</sub_tipo>' . "\n";
            $xml .= '    <monto>'         . number_format($m->monto, 2, '.', '')                    . '</monto>' . "\n";
            $xml .= '    <beneficiario>'  . htmlspecialchars($m->beneficiario ?? '')                 . '</beneficiario>' . "\n";
            $xml .= '    <descripcion>'   . htmlspecialchars($m->descripcion ?? '')                  . '</descripcion>' . "\n";
            $xml .= '    <num_documento>' . htmlspecialchars($m->num_documento ?? '')                . '</num_documento>' . "\n";
            $xml .= '    <conciliado>'    . ($m->conciliado ? 'true' : 'false')                     . '</conciliado>' . "\n";
            $xml .= '  </movimiento>' . "\n";
        }

        $xml .= '</movimientos_bancarios>';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="movimientos-' . now()->format('Y-m-d') . '.xml"',
        ]);
    }

    public function pdf(Request $request): \Illuminate\Http\Response|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $total = $this->queryFiltrada($request)->count();
        if ($total > self::MAX_FILAS_EXPORT) {
            return response()->json([
                'message' => "Hay {$total} movimientos con estos filtros — demasiados para generar un PDF de una vez " .
                    '(máximo ' . self::MAX_FILAS_EXPORT . '). Aplica un filtro más específico.',
            ], 422);
        }

        $movimientos = $this->queryFiltrada($request)
            ->orderByDesc('fecha')->orderByDesc('id')->get();

        // Ingresos/egresos del resumen excluyen anulados (igual que las tarjetas de
        // stats del listado) aunque la tabla del PDF sí incluye anulados con su badge
        // de Estado — misma distinción que ya existe entre index()'s `stats` (excluye
        // anulados) y sus filas (los muestra atenuados).
        $totalIngresos = $this->queryFiltrada($request)
            ->where('tipo', 'ingreso')->where('anulado', false)->sum('monto');
        $totalEgresos = $this->queryFiltrada($request)
            ->where('tipo', 'egreso')->where('anulado', false)->sum('monto');

        $empresa = Empresa::find($empresaId);
        $pdf = Pdf::loadView('pdf.bancos-movimientos', compact('movimientos', 'empresa', 'totalIngresos', 'totalEgresos'))
            ->setPaper('a4');
        return $pdf->stream('movimientos-bancarios-' . now()->format('Y-m-d') . '.pdf');
    }

    // Chequeo liviano (sin generar nada) para que el frontend decida, ANTES de pedir
    // el PDF, si el filtro actual entra en el camino rápido (síncrono) o necesita el
    // camino de segundo plano — mismo patrón que AsientoContableController/CuentaPagarController.
    public function contarExportables(Request $request): JsonResponse
    {
        $total = $this->queryFiltrada($request)->count();

        return response()->json([
            'total'  => $total,
            'limite' => self::MAX_FILAS_EXPORT,
            'excede' => $total > self::MAX_FILAS_EXPORT,
        ]);
    }

    // Camino de segundo plano: sin límite de filas, genera el PDF completo en el
    // worker de colas y avisa por notificación cuando está listo. El link de
    // descarga se construye con el host REAL de esta request
    // ($request->getSchemeAndHttpHost()), no con config('app.url') — un Job corre
    // sin request activa, así que route() ahí cae al host fijo de config/app.php,
    // que puede no ser el que realmente sirvió la petición (mismo bug que rompió
    // antes la descarga de Excel de Asientos en un entorno con puerto distinto al
    // .env). Se captura el host aquí, donde sí hay una request real, y se pasa al Job.
    public function exportarSegundoPlano(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $filtros   = $request->only(self::FILTROS_KEYS);
        $baseUrl   = $request->getSchemeAndHttpHost();

        ExportarMovimientosJob::dispatch(
            (int) $empresaId,
            (int) Auth::id(),
            $filtros,
            $baseUrl,
        );

        return back()->with('success',
            'Tu PDF de Movimientos Bancarios se está procesando en segundo plano. Te avisaremos por notificación cuando esté listo para descargar.');
    }

    // Sirve el PDF generado por ExportarMovimientosJob. Autorización simple: el
    // nombre de archivo lleva el usuario_id como prefijo (ver el Job), y basename()
    // descarta cualquier intento de path traversal.
    public function descargarExportacion(string $archivo): \Symfony\Component\HttpFoundation\Response
    {
        $archivo = basename($archivo);

        if (!str_starts_with($archivo, Auth::id() . '_')) {
            abort(403, 'No tienes acceso a este archivo.');
        }

        $ruta = ExportarMovimientosJob::CARPETA . "/{$archivo}";
        if (!Storage::disk('local')->exists($ruta)) {
            abort(404, 'El archivo expiró o ya no está disponible (las exportaciones se conservan 48 horas). Genera la exportación nuevamente.');
        }

        return Storage::disk('local')->download($ruta, 'movimientos-bancarios-' . now()->format('Y-m-d') . '.pdf');
    }

    public function anular(Request $request, MovimientoBancario $movimiento): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:300',
        ]);

        if ($movimiento->anulado) {
            return back()->with('error', 'Este movimiento ya está anulado.');
        }
        if ($movimiento->conciliado) {
            return back()->with('error',
                'No se puede anular: el movimiento ya fue conciliado con el banco.');
        }

        DB::transaction(function () use ($movimiento, $request) {
            $tipoReversa = $movimiento->tipo === 'ingreso' ? 'egreso' : 'ingreso';

            // Anular el asiento contable original (genera su propio asiento de reversa)
            // ANTES de crear el movimiento de reversión, para poder enlazarlo.
            $asientoReversaId = null;
            if ($movimiento->asiento_id) {
                $asiento = AsientoContable::find($movimiento->asiento_id);
                if ($asiento && !$asiento->estaAnulado()) {
                    try {
                        $asientoReversa   = $this->asientoService->anular($asiento, $request->motivo);
                        $asientoReversaId = $asientoReversa->id;
                    } catch (\Exception) {
                        // No bloquear si el asiento no puede anularse (período cerrado, etc.)
                    }
                }
            }

            // El movimiento original NUNCA se borra ni se modifica: queda como evidencia
            // histórica, solo marcado como anulado. La reversión real del saldo se hace
            // con un movimiento NUEVO, de signo contrario, enlazado al original.
            $movimiento->update(['anulado' => true]);

            $reversion = MovimientoBancario::create([
                'empresa_id'              => $movimiento->empresa_id,
                'banco_caja_id'           => $movimiento->banco_caja_id,
                'tipo'                    => $tipoReversa,
                'sub_tipo'                => $movimiento->sub_tipo,
                'fecha'                   => now()->toDateString(),
                'monto'                   => $movimiento->monto,
                'persona_tipo'            => $movimiento->persona_tipo,
                'persona_id'              => $movimiento->persona_id,
                'beneficiario'            => $movimiento->beneficiario,
                'descripcion'             => "Reversión de movimiento #{$movimiento->id} — {$request->motivo}",
                'documento_tipo'          => 'ANULACION_MOV',
                'documento_id'            => $movimiento->id,
                'cuenta_contrapartida_id' => $movimiento->cuenta_contrapartida_id,
                'asiento_id'              => $asientoReversaId,
                'anulado'                 => false,
                'conciliado'              => false,
                'created_by'              => Auth::id(),
            ]);

            $movimiento->bancoCaja->actualizarSaldo((float) $movimiento->monto, $tipoReversa);

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'empresa_id'     => $movimiento->empresa_id,
                'tabla'          => 'movimientos_bancarios',
                'registro_id'    => $movimiento->id,
                'campo'          => 'anulado',
                'valor_anterior' => 'false',
                'valor_nuevo'    => "true — {$request->motivo} (reversión: movimiento #{$reversion->id})",
                'ip_address'     => $request->ip(),
            ]);
        });

        return back()->with('success', 'Movimiento anulado. Se generó un movimiento de reversión y su asiento contable.');
    }
}
