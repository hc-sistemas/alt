<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\CentroCosto;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class BodegaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = Bodega::with('centroCosto')
            ->where('empresa_id', $empresaId)
            ->when($request->tipo, fn($q) => $q->where('tipo', $request->tipo))
            ->orderBy('nombre');

        $centrosCosto = CentroCosto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return Inertia::render('Inventario/Configuracion/Bodegas/Index', [
            'bodegas'      => $query->paginate(15)->withQueryString(),
            'centrosCosto' => $centrosCosto,
            'filters'      => $request->only(['tipo']),
            'tipos'        => Bodega::TIPOS,
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'nombre'          => ['required', 'string', 'max:150'],
            'tipo'            => ['required', 'string', 'in:' . implode(',', Bodega::TIPOS)],
            'centro_costo_id' => ['nullable', 'integer', 'exists:centros_costo,id'],
            'es_virtual'      => ['boolean'],
            'estado'          => ['boolean'],
        ]);

        if (!empty($data['centro_costo_id'])) {
            $cc = CentroCosto::where('id', $data['centro_costo_id'])
                ->where('empresa_id', $empresaId)
                ->first();
            if (!$cc) {
                return response()->json(['message' => 'El centro de costo no pertenece a esta empresa.'], 422);
            }
        }

        $bodega = Bodega::create([...$data, 'empresa_id' => $empresaId]);

        $this->auditoria->documento('crear', 'inventario', 'bodegas', $bodega->id,
            "Bodega {$bodega->nombre} creada");

        return back()->with('success', 'Bodega creada correctamente.');
    }

    public function update(Request $request, Bodega $bodega): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'nombre'          => ['required', 'string', 'max:150'],
            'tipo'            => ['required', 'string', 'in:' . implode(',', Bodega::TIPOS)],
            'centro_costo_id' => ['nullable', 'integer', 'exists:centros_costo,id'],
            'es_virtual'      => ['boolean'],
            'estado'          => ['boolean'],
        ]);

        if (!empty($data['centro_costo_id'])) {
            $cc = CentroCosto::where('id', $data['centro_costo_id'])
                ->where('empresa_id', $empresaId)
                ->first();
            if (!$cc) {
                return response()->json(['message' => 'El centro de costo no pertenece a esta empresa.'], 422);
            }
        }

        $bodega->update($data);

        $this->auditoria->documento('editar', 'inventario', 'bodegas', $bodega->id,
            "Bodega {$bodega->nombre} actualizada");

        return back()->with('success', 'Bodega actualizada correctamente.');
    }

    public function destroy(Bodega $bodega): RedirectResponse|JsonResponse
    {
        if (Schema::hasTable('inventario_saldos')) {
            $total = \DB::table('inventario_saldos')->where('bodega_id', $bodega->id)->count();
            if ($total > 0) {
                return response()->json([
                    'message' => 'No se puede eliminar: tiene movimientos registrados',
                ], 422);
            }
        }

        $nombre = $bodega->nombre;
        $id     = $bodega->id;
        $bodega->delete();

        $this->auditoria->documento('eliminar', 'inventario', 'bodegas', $id,
            "Bodega {$nombre} eliminada");

        return back()->with('success', 'Bodega eliminada correctamente.');
    }
}
