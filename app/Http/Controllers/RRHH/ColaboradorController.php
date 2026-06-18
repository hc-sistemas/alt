<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Horario;
use App\Models\PuestoTrabajo;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ColaboradorController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = Colaborador::with(['puesto', 'horario'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->where('nombres', 'ilike', "%{$q}%")
                   ->orWhere('apellidos', 'ilike', "%{$q}%")
                   ->orWhere('cedula_ruc', 'ilike', "%{$q}%")
                   ->orWhere('cargo', 'ilike', "%{$q}%")
            );
        }

        if ($request->filled('departamento')) {
            $query->where('departamento', $request->departamento);
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado === 'activo');
        }

        $colaboradores = $query->orderBy('apellidos')->orderBy('nombres')
            ->paginate(20)->withQueryString();

        $puestos  = PuestoTrabajo::where('empresa_id', $empresaId)
            ->where('estado', true)->orderBy('nombre')
            ->get(['id', 'nombre', 'cargo', 'departamento']);

        $horarios = Horario::orderBy('descripcion')
            ->get(['id', 'descripcion', 'hora_entrada', 'hora_salida', 'tolerancia_minutos']);

        $usuarios = Usuario::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email']);

        $departamentos = Colaborador::where('empresa_id', $empresaId)
            ->whereNotNull('departamento')
            ->distinct()->pluck('departamento')->sort()->values();

        return Inertia::render('RRHH/Colaboradores/Index', [
            'colaboradores' => $colaboradores,
            'puestos'       => $puestos,
            'horarios'      => $horarios,
            'usuarios'      => $usuarios,
            'departamentos' => $departamentos,
            'filtros'       => $request->only(['buscar', 'departamento', 'estado']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'cedula_ruc'          => "required|string|max:13|unique:colaboradores,cedula_ruc",
            'apellidos'           => 'required|string|max:100',
            'nombres'             => 'required|string|max:100',
            'email'               => 'nullable|email|max:200|unique:colaboradores,email',
            'telefono'            => 'nullable|string|max:20',
            'celular'             => 'nullable|string|max:20',
            'direccion'           => 'nullable|string|max:300',
            'fecha_nacimiento'    => 'nullable|date',
            'sexo'                => 'nullable|in:M,F',
            'estado_civil'        => 'nullable|string|max:20',
            'fecha_ingreso'       => 'required|date',
            'fecha_salida'        => 'nullable|date|after_or_equal:fecha_ingreso',
            'tipo_contrato'       => 'nullable|in:indefinido,plazo_fijo,honorarios',
            'cargo'               => 'nullable|string|max:100',
            'departamento'        => 'nullable|string|max:100',
            'comision_porcentaje' => 'numeric|min:0|max:100',
            'sueldo_base'         => 'required|numeric|min:0',
            'decimo_tercero'      => 'in:acumula,mensualiza',
            'decimo_cuarto'       => 'in:acumula,mensualiza',
            'fondos_reserva'      => 'in:acumula,mensualiza',
            'banco'               => 'nullable|string|max:100',
            'tipo_cuenta'         => 'nullable|in:ahorros,corriente',
            'numero_cuenta'       => 'nullable|string|max:30',
            'puesto_id'           => 'nullable|exists:puestos_trabajo,id',
            'horario_id'          => 'nullable|exists:horarios,id',
            'usuario_id'          => 'nullable|exists:usuarios,id',
        ]);

        Colaborador::create(array_merge($data, ['empresa_id' => $empresaId, 'estado' => true]));

        return back()->with('success', "Colaborador {$data['apellidos']} {$data['nombres']} creado correctamente.");
    }

    public function update(Request $request, Colaborador $colaborador): RedirectResponse
    {
        $data = $request->validate([
            'cedula_ruc'          => "required|string|max:13|unique:colaboradores,cedula_ruc,{$colaborador->id}",
            'apellidos'           => 'required|string|max:100',
            'nombres'             => 'required|string|max:100',
            'email'               => "nullable|email|max:200|unique:colaboradores,email,{$colaborador->id}",
            'telefono'            => 'nullable|string|max:20',
            'celular'             => 'nullable|string|max:20',
            'direccion'           => 'nullable|string|max:300',
            'fecha_nacimiento'    => 'nullable|date',
            'sexo'                => 'nullable|in:M,F',
            'estado_civil'        => 'nullable|string|max:20',
            'fecha_ingreso'       => 'required|date',
            'fecha_salida'        => 'nullable|date|after_or_equal:fecha_ingreso',
            'tipo_contrato'       => 'nullable|in:indefinido,plazo_fijo,honorarios',
            'cargo'               => 'nullable|string|max:100',
            'departamento'        => 'nullable|string|max:100',
            'comision_porcentaje' => 'numeric|min:0|max:100',
            'sueldo_base'         => 'required|numeric|min:0',
            'decimo_tercero'      => 'in:acumula,mensualiza',
            'decimo_cuarto'       => 'in:acumula,mensualiza',
            'fondos_reserva'      => 'in:acumula,mensualiza',
            'banco'               => 'nullable|string|max:100',
            'tipo_cuenta'         => 'nullable|in:ahorros,corriente',
            'numero_cuenta'       => 'nullable|string|max:30',
            'puesto_id'           => 'nullable|exists:puestos_trabajo,id',
            'horario_id'          => 'nullable|exists:horarios,id',
            'usuario_id'          => 'nullable|exists:usuarios,id',
        ]);

        $colaborador->update($data);

        return back()->with('success', "Colaborador {$colaborador->apellidos} {$colaborador->nombres} actualizado.");
    }

    public function toggle(Colaborador $colaborador): RedirectResponse
    {
        $colaborador->update(['estado' => !$colaborador->estado]);
        $estado = $colaborador->estado ? 'activado' : 'desactivado';

        return back()->with('success', "Colaborador {$estado}.");
    }
}
