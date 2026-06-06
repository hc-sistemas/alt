<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\ActivoDepreciacion;
use App\Models\ActivoFijo;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ActivoFijoController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = ActivoFijo::where('empresa_id', $empresaId)
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->orderBy('codigo');

        return Inertia::render('Inventario/Activos/Index', [
            'activos' => $query->paginate(20)->withQueryString(),
            'estados' => ActivoFijo::ESTADOS,
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Inventario/Activos/Form', [
            'activoFijo' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'codigo'            => ['required', 'string', 'max:50', Rule::unique('activos_fijos')->where('empresa_id', $empresaId)],
            'nombre'            => ['required', 'string', 'max:255'],
            'descripcion'       => ['nullable', 'string'],
            'fecha_adquisicion' => ['required', 'date'],
            'costo_adquisicion' => ['required', 'numeric', 'min:0'],
            'valor_residual'    => ['nullable', 'numeric', 'min:0'],
            'vida_util_anios'   => ['required', 'integer', 'min:1', 'max:100'],
            'cuenta_id'         => ['nullable', 'integer'],
        ]);

        $data['empresa_id']             = $empresaId;
        $data['depreciacion_acumulada'] = 0;
        $data['valor_residual']         = $data['valor_residual'] ?? 0;
        $data['valor_en_libros']        = $data['costo_adquisicion'];
        $data['estado']                 = 'activo';

        $activo = ActivoFijo::create($data);

        $this->auditoria->documento('crear', 'inventario', 'activos_fijos', $activo->id,
            "Activo {$activo->codigo} — {$activo->nombre} creado");

        return redirect()->route('inventario.activos.index')
            ->with('success', 'Activo fijo creado correctamente.');
    }

    public function show(ActivoFijo $activoFijo): Response
    {
        abort_if($activoFijo->empresa_id !== session('empresa_activa_id'), 403);

        return Inertia::render('Inventario/Activos/Show', [
            'activo' => $activoFijo->load([
                'depreciaciones' => fn($q) => $q->orderByDesc('periodo_año')->orderByDesc('periodo_mes'),
            ]),
        ]);
    }

    public function edit(ActivoFijo $activoFijo): Response
    {
        abort_if($activoFijo->empresa_id !== session('empresa_activa_id'), 403);

        return Inertia::render('Inventario/Activos/Form', [
            'activoFijo' => $activoFijo,
        ]);
    }

    public function update(Request $request, ActivoFijo $activoFijo): RedirectResponse
    {
        abort_if($activoFijo->empresa_id !== session('empresa_activa_id'), 403);

        if ($activoFijo->estado !== 'activo') {
            abort(422, 'No se puede editar un activo dado de baja o vendido.');
        }

        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'codigo'            => ['required', 'string', 'max:50', Rule::unique('activos_fijos')->where('empresa_id', $empresaId)->ignore($activoFijo->id)],
            'nombre'            => ['required', 'string', 'max:255'],
            'descripcion'       => ['nullable', 'string'],
            'fecha_adquisicion' => ['required', 'date'],
            'costo_adquisicion' => ['required', 'numeric', 'min:0'],
            'valor_residual'    => ['nullable', 'numeric', 'min:0'],
            'vida_util_anios'   => ['required', 'integer', 'min:1', 'max:100'],
            'cuenta_id'         => ['nullable', 'integer'],
        ]);

        $data['valor_residual']  = $data['valor_residual'] ?? 0;
        $data['valor_en_libros'] = $data['costo_adquisicion'] - $activoFijo->depreciacion_acumulada;

        $activoFijo->update($data);

        $this->auditoria->documento('editar', 'inventario', 'activos_fijos', $activoFijo->id,
            "Activo {$activoFijo->codigo} actualizado");

        return redirect()->route('inventario.activos.index')
            ->with('success', 'Activo fijo actualizado correctamente.');
    }

    public function destroy(ActivoFijo $activoFijo): RedirectResponse
    {
        abort_if($activoFijo->empresa_id !== session('empresa_activa_id'), 403);

        if ($activoFijo->depreciaciones()->exists()) {
            abort(422, 'No se puede eliminar: tiene depreciaciones registradas.');
        }

        $this->auditoria->documento('eliminar', 'inventario', 'activos_fijos', $activoFijo->id,
            "Activo {$activoFijo->codigo} — {$activoFijo->nombre} eliminado");

        $activoFijo->delete();

        return back()->with('success', 'Activo eliminado correctamente.');
    }

    public function depreciar(Request $request, ActivoFijo $activoFijo): RedirectResponse
    {
        abort_if($activoFijo->empresa_id !== session('empresa_activa_id'), 403);

        $data = $request->validate([
            'periodo_año' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periodo_mes' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        if ($activoFijo->estado !== 'activo') {
            abort(422, 'El activo no está en estado activo.');
        }

        $disponible = (float) $activoFijo->valor_en_libros - (float) $activoFijo->valor_residual;
        if ($disponible <= 0) {
            abort(422, 'El activo ya está totalmente depreciado.');
        }

        $yaExiste = ActivoDepreciacion::where('activo_id', $activoFijo->id)
            ->where('periodo_año', $data['periodo_año'])
            ->where('periodo_mes', $data['periodo_mes'])
            ->exists();

        if ($yaExiste) {
            abort(422, "Ya existe una depreciación para {$data['periodo_año']}/{$data['periodo_mes']}.");
        }

        $monto           = min($activoFijo->depreciacionMensual(), $disponible);
        $nuevaAcumulada  = (float) $activoFijo->depreciacion_acumulada + $monto;
        $nuevoValorLibro = (float) $activoFijo->costo_adquisicion - $nuevaAcumulada;

        DB::transaction(function () use ($activoFijo, $data, $monto, $nuevaAcumulada, $nuevoValorLibro) {
            ActivoDepreciacion::create([
                'activo_id'                         => $activoFijo->id,
                'periodo_año'                       => $data['periodo_año'],
                'periodo_mes'                       => $data['periodo_mes'],
                'monto'                             => $monto,
                'depreciacion_acumulada_al_periodo'  => $nuevaAcumulada,
                'valor_libro_al_periodo'             => $nuevoValorLibro,
            ]);

            $nuevoEstado = $nuevoValorLibro <= (float) $activoFijo->valor_residual ? 'dado_de_baja' : 'activo';

            $activoFijo->update([
                'depreciacion_acumulada' => $nuevaAcumulada,
                'valor_en_libros'        => $nuevoValorLibro,
                'estado'                 => $nuevoEstado,
            ]);
        });

        $this->auditoria->documento('depreciar', 'inventario', 'activos_fijos', $activoFijo->id,
            "Depreciación {$data['periodo_año']}/{$data['periodo_mes']} — monto: {$monto}");

        return redirect()->route('inventario.activos.show', $activoFijo)
            ->with('success', 'Depreciación registrada correctamente.');
    }
}
