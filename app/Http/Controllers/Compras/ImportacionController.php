<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Importacion;
use App\Models\Proveedor;
use App\Models\Compra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ImportacionController extends Controller
{
    public function index(): Response
    {
        $empresaId    = session('empresa_activa_id');
        $importaciones = Importacion::with('proveedor')
            ->where('empresa_id', $empresaId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($i) => [
                'id'                => $i->id,
                'nombre'            => $i->nombre,
                'num_invoice'       => $i->num_invoice,
                'proveedor'         => $i->proveedor?->razon_social,
                'pais_embarque'     => $i->pais_embarque,
                'costo_fob'         => $i->costo_fob,
                'total_costos_extra'=> $i->total_costos_extra,
                'costo_total'       => $i->costo_total,
                'fecha_partida'     => $i->fecha_partida?->format('d/m/Y'),
                'fecha_llegada'     => $i->fecha_llegada?->format('d/m/Y'),
                'fecha_liquidacion' => $i->fecha_liquidacion?->format('d/m/Y'),
                'estado'            => $i->estado,
                'estado_label'      => $i->estado_label,
                'estado_color'      => $i->estado_color,
                'observaciones'     => $i->observaciones,
            ]);

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->where('tipo', 'internacional')
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social', 'pais', 'divisa']);

        return Inertia::render('Compras/Importaciones/Index', [
            'importaciones' => $importaciones,
            'proveedores'   => $proveedores,
            'stats' => [
                'total'       => $importaciones->count(),
                'en_transito' => $importaciones->where('estado', 'en_transito')->count(),
                'en_aduana'   => $importaciones->where('estado', 'en_aduana')->count(),
                'liquidadas'  => $importaciones->where('estado', 'liquidada')->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'nombre'        => 'required|string|max:200',
            'proveedor_id'  => 'nullable|exists:proveedores,id',
            'num_invoice'   => 'nullable|string|max:100',
            'costo_fob'     => 'required|numeric|min:0',
            'pais_embarque' => 'nullable|string|max:100',
            'fecha_partida' => 'nullable|date',
            'fecha_llegada' => 'nullable|date|after_or_equal:fecha_partida',
        ]);

        Importacion::create([
            ...$request->only([
                'nombre', 'proveedor_id', 'num_invoice', 'agente_aduanero',
                'pais_embarque', 'costo_fob', 'divisa', 'fecha_partida',
                'fecha_llegada', 'observaciones',
            ]),
            'empresa_id' => $empresaId,
            'estado'     => 'en_transito',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success',
            "Importación {$request->nombre} creada correctamente.");
    }

    public function update(Request $request, Importacion $importacion): RedirectResponse
    {
        if ($importacion->estaLiquidada()) {
            return back()->with('error',
                'No se puede editar una importación ya liquidada.');
        }

        $request->validate([
            'estado'        => 'nullable|in:en_transito,en_aduana,liquidada',
            'fecha_llegada' => 'nullable|date',
        ]);

        $importacion->update($request->only([
            'nombre', 'agente_aduanero', 'pais_embarque', 'costo_fob',
            'divisa', 'fecha_partida', 'fecha_llegada', 'estado', 'observaciones',
        ]));

        return back()->with('success', 'Importación actualizada correctamente.');
    }

    public function liquidar(Request $request, Importacion $importacion): RedirectResponse
    {
        if ($importacion->estaLiquidada()) {
            return back()->with('error', 'Esta importación ya está liquidada.');
        }

        $request->validate([
            'metodo_prorrateo'  => 'required|in:cantidad,precio,peso',
            'fecha_liquidacion' => 'required|date',
        ]);

        $compras = Compra::where('importacion_id', $importacion->id)
            ->with('detalles')->get();

        if ($compras->isEmpty()) {
            return back()->with('error',
                'No hay compras asociadas a esta importación para prorratear.');
        }

        $totalCostosExtra = $compras->sum('total');

        $importacion->update([
            'metodo_prorrateo'  => $request->metodo_prorrateo,
            'total_costos_extra'=> $totalCostosExtra,
            'costo_total'       => $importacion->costo_fob + $totalCostosExtra,
            'fecha_liquidacion' => $request->fecha_liquidacion,
            'estado'            => 'liquidada',
        ]);

        return back()->with('success',
            "Importación {$importacion->nombre} liquidada. " .
            "Costo total: $" . number_format($importacion->costo_total, 2));
    }
}
