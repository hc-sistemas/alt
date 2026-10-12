<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\TallerOrdenTrabajo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vista del bodeguero: equipos que el técnico ya dejó en estado "listo" y que
 * siguen en bodega esperando que el cliente los retire. Al facturarse o
 * entregarse la orden deja de estar en estado "listo" y sale de esta lista.
 */
class EquiposTallerController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = TallerOrdenTrabajo::with(['ingreso.cliente:id,razon_social,identificacion,telefono,celular', 'ingreso.equipo.tipo'])
            ->where('empresa_id', $empresaId)
            ->where('estado', 'listo')
            // Los que llevan más tiempo esperando primero: son los que hay que llamar ya.
            ->orderByRaw('COALESCE(fecha_fin_real, updated_at::date) ASC')
            ->orderBy('id');

        if ($request->filled('search')) {
            $term = "%{$request->search}%";
            $query->where(function ($w) use ($term) {
                $w->where('numero', 'ilike', $term)
                    ->orWhereHas('ingreso.cliente', fn ($q) => $q
                        ->where('razon_social', 'ilike', $term)
                        ->orWhere('identificacion', 'ilike', $term)
                        ->orWhere('telefono', 'ilike', $term)
                        ->orWhere('celular', 'ilike', $term))
                    ->orWhereHas('ingreso.equipo', fn ($q) => $q
                        ->where('marca', 'ilike', $term)
                        ->orWhere('modelo', 'ilike', $term)
                        ->orWhere('numero_serie', 'ilike', $term));
            });
        }

        $hoy = now()->startOfDay();

        $equipos = $query->get()->map(function (TallerOrdenTrabajo $o) use ($hoy) {
            $cliente = $o->ingreso?->cliente;
            $equipo  = $o->ingreso?->equipo;
            $desde   = $o->fecha_fin_real ? \Carbon\Carbon::parse($o->fecha_fin_real) : $o->updated_at;

            return [
                'id'            => $o->id,
                'orden_numero'  => $o->numero ?? "#{$o->id}",
                'cliente'       => $cliente?->razon_social,
                'identificacion' => $cliente?->identificacion,
                'telefono'      => collect([$cliente?->celular, $cliente?->telefono])->filter()->unique()->implode(' / ') ?: null,
                'producto'      => trim(implode(' ', array_filter([$equipo?->tipo?->descripcion, $equipo?->marca, $equipo?->modelo]))) ?: '—',
                'numero_serie'  => $equipo?->numero_serie,
                'listo_desde'   => $desde?->toDateString(),
                'dias_bodega'   => $desde ? (int) $desde->copy()->startOfDay()->diffInDays($hoy) : 0,
            ];
        })->values();

        return Inertia::render('Inventario/EquiposTaller/Index', [
            'equipos' => $equipos,
            'filtros' => $request->only('search'),
        ]);
    }
}
