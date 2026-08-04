<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\Colaborador;
use App\Models\PrestamoEmpleado;
use App\Services\AsientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PrestamosController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $prestamos = null;

        if ($request->boolean('buscado')) {
            $query = PrestamoEmpleado::with('colaborador')
                ->whereHas('colaborador', fn($q) => $q->where('empresa_id', $empresaId));

            if ($request->filled('colaborador_id')) {
                $query->where('colaborador_id', $request->colaborador_id);
            }
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('anio')) {
                $query->whereYear('fecha', $request->anio);
            }
            if ($request->filled('buscar')) {
                $q = $request->buscar;
                $query->whereHas('colaborador', fn($qc) => $qc
                    ->where('apellidos', 'ilike', "%{$q}%")
                    ->orWhere('nombres', 'ilike', "%{$q}%")
                    ->orWhere('cedula_ruc', 'ilike', "%{$q}%")
                );
            }

            $prestamos = $query->orderByDesc('id')->paginate(20)->withQueryString();
        }

        $colaboradores = Colaborador::where('empresa_id', $empresaId)
            ->activos()->orderBy('apellidos')->orderBy('nombres')
            ->get(['id', 'cedula_ruc', 'apellidos', 'nombres', 'sueldo_base']);

        return Inertia::render('RRHH/Prestamos/Index', [
            'prestamos'     => $prestamos,
            'colaboradores' => $colaboradores,
            'filtros'       => $request->only(['colaborador_id', 'tipo', 'estado', 'anio', 'buscar']),
            'anios'         => range(now()->year, now()->year - 3),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'colaborador_id' => 'required|integer|exists:colaboradores,id',
            'tipo'           => 'required|in:anticipo,prestamo',
            'monto_total'    => 'required|numeric|min:1|max:99999',
            'cuota'          => 'nullable|numeric|min:0',
            'fecha'          => 'required|date',
            'descripcion'    => 'nullable|string|max:300',
        ]);

        // Verificar que el colaborador pertenece a la empresa activa
        $col = Colaborador::where('id', $data['colaborador_id'])
            ->where('empresa_id', $empresaId)->firstOrFail();

        $data['cuota'] = $data['tipo'] === 'anticipo' ? 0 : ($data['cuota'] ?? 0);

        try {
            DB::transaction(function () use ($data, $empresaId) {
                $prestamo = PrestamoEmpleado::create(array_merge($data, [
                    'saldo'      => $data['monto_total'],
                    'estado'     => 'activo',
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                ]));

                // Asiento contable: DEBE 1.1.3.04 / HABER 1.1.1.03
                $prestamo->load('colaborador');
                $this->asientoService->prestamoEmpleado($prestamo, $empresaId);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al registrar: ' . $e->getMessage());
        }

        $tipo = $data['tipo'] === 'anticipo' ? 'Anticipo' : 'Préstamo';
        return back()->with('success', "{$tipo} registrado y asiento contable generado.");
    }

    // Liquidación manual fuera de nómina (ej. el colaborador paga en efectivo o
    // transferencia directa) — distinto del descuento automático vía
    // NominaController::procesar() (Regla NOM-02), que sí recupera el saldo cuota
    // a cuota y genera el HABER 1.1.3.04 correspondiente en el asiento de nómina.
    // Esta acción manual NO genera ningún asiento contable: es solo un cambio de
    // estado. Si el saldo restante no fue realmente cobrado (ej. se usa para
    // "perdonar" el saldo), la cuenta 1.1.3.04 quedará sobrevalorada frente al
    // registro — el frontend advierte esto explícitamente en el mensaje de
    // confirmación para que no se use sin entender esa consecuencia.
    public function pagar(int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $prestamo = PrestamoEmpleado::whereHas('colaborador',
            fn($q) => $q->where('empresa_id', $empresaId)
        )->findOrFail($id);

        if ($prestamo->estado === 'pagado') {
            return back()->with('error', 'Este registro ya está marcado como pagado.');
        }

        $prestamo->update(['estado' => 'pagado', 'saldo' => 0]);

        return back()->with('success', 'Marcado como pagado.');
    }

    public function destroy(int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $prestamo = PrestamoEmpleado::whereHas('colaborador',
            fn($q) => $q->where('empresa_id', $empresaId)
        )->findOrFail($id);

        if ($prestamo->estado !== 'activo') {
            return back()->with('error', 'Solo se pueden eliminar préstamos activos.');
        }

        // Mismo criterio de inmutabilidad ya aplicado a Facturas de Compra pagadas:
        // si el saldo ya bajó del monto original es porque tiene cuotas descontadas
        // en nómina (NominaController::procesar()) — eliminar el registro perdería
        // ese historial sin revertir los descuentos ya aplicados al colaborador.
        if ((float)$prestamo->saldo < (float)$prestamo->monto_total) {
            return back()->with('error',
                'No se puede eliminar: ya tiene cuotas descontadas en nómina. Revierta esos descuentos antes de eliminar el registro.');
        }

        try {
            DB::transaction(function () use ($prestamo, $empresaId) {
                // Sin cuotas descontadas todavía: el préstamo sigue intacto, pero el
                // asiento original (DEBE 1.1.3.04 / HABER 1.1.1.03) queda huérfano si
                // no se anula — mismo patrón que revertirInventarioYAsiento() en Compras.
                $asiento = AsientoContable::where('empresa_id', $empresaId)
                    ->where('documento_tipo', 'PREST')
                    ->where('documento_id', $prestamo->id)
                    ->first();

                if ($asiento && !$asiento->estaAnulado()) {
                    $this->asientoService->anular($asiento, "Eliminación de préstamo/anticipo #{$prestamo->id}");
                }

                $prestamo->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }

        return back()->with('success', 'Registro eliminado.');
    }
}
