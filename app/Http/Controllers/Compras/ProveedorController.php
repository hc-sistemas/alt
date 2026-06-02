<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function index(): Response
    {
        $empresaId   = session('empresa_activa_id');
        $proveedores = Proveedor::where('empresa_id', $empresaId)
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

        return Inertia::render('Compras/Proveedores/Index', [
            'proveedores' => $proveedores,
            'stats' => [
                'total'           => $proveedores->count(),
                'nacionales'      => $proveedores->where('tipo', 'nacional')->count(),
                'internacionales' => $proveedores->where('tipo', 'internacional')->count(),
                'con_saldo'       => $proveedores->where('saldo_pendiente', '>', 0)->count(),
            ],
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
}
