<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Producto;
use App\Models\TallerOrdenTrabajo;
use App\Models\TallerOtRepuesto;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiquidacionController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
        private InventarioServiceInterface $inventario,
    ) {}

    public function show(TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }

    public function liquidar(Request $request, TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }

    public function agregarRepuesto(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        $data = $request->validate([
            'producto_id'  => 'required|integer|exists:productos,id',
            'cantidad'     => 'required|integer|min:1',
            'numero_serie' => 'nullable|string',
            'precio_venta' => 'required|numeric|min:0',
        ]);

        $producto = Producto::findOrFail($data['producto_id']);

        $empresaId = session('empresa_activa_id');
        $bodega = Bodega::where('empresa_id', $empresaId)->where('estado', true)->where('tipo', 'general')->first()
            ?? Bodega::where('empresa_id', $empresaId)->where('estado', true)->first();

        if (!$bodega) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'No hay bodega activa configurada para la empresa.']);
        }

        try {
            DB::transaction(function () use ($orden, $data, $producto, $bodega) {
                $this->inventario->reservarStock($data['producto_id'], $bodega->id, $data['cantidad'], 'taller_ot', $orden->id);

                TallerOtRepuesto::create([
                    'orden_id'       => $orden->id,
                    'producto_id'    => $data['producto_id'],
                    'numero_serie'   => $data['numero_serie'] ?? null,
                    'cantidad'       => $data['cantidad'],
                    'costo_unitario' => $producto->costo,
                    'precio_venta'   => $data['precio_venta'],
                    'estado'         => 'reservado',
                ]);

                $orden->costo_repuestos += $data['precio_venta'] * $data['cantidad'];
                $orden->costo_total = $orden->costo_mano_obra + $orden->costo_repuestos;
                $orden->save();
            });
        } catch (\RuntimeException $e) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
        }

        $this->auditoria->documento('crear', 'taller', 'ot_repuestos', $orden->id,
            "Repuesto {$producto->nombre} agregado a la orden {$orden->numero}");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => 'Repuesto agregado correctamente.']);
    }
}
