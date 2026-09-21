<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Departamento;
use App\Models\Horario;
use App\Models\Nomina;
use App\Models\NominaDetalle;
use App\Models\PuestoTrabajo;
use App\Models\Usuario;
use App\Services\NominaCalculoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ColaboradorController extends Controller
{
    public function __construct(private NominaCalculoService $nominaCalculoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $colaboradores = null;

        if ($request->boolean('buscado')) {
            $query = Colaborador::with(['puesto', 'horario', 'usuario:id,username,estado'])
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
        }

        $puestos  = PuestoTrabajo::where('empresa_id', $empresaId)
            ->where('estado', true)->orderBy('nombre')
            ->get(['id', 'nombre', 'cargo', 'departamento']);

        $horarios = Horario::orderBy('descripcion')
            ->get(['id', 'descripcion', 'hora_entrada', 'hora_salida', 'tolerancia_minutos']);

        // Usuarios con acceso a esta empresa, con el colaborador al que ya están
        // vinculados (null = libre) para poder ofrecerlos en "Seguridad y Sistema".
        $vinculados = Colaborador::whereNotNull('usuario_id')->pluck('id', 'usuario_id');

        $usuarios = Usuario::with('perfil:id,nombre')
            ->where(fn($q) => $q->where('empresa_id', $empresaId)
                ->orWhereHas('empresas', fn($e) => $e->where('empresas.id', $empresaId)))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email', 'username', 'perfil_id', 'estado'])
            ->map(fn($u) => [
                'id'             => $u->id,
                'nombre'         => $u->nombre,
                'email'          => $u->email,
                'username'       => $u->username,
                'perfil'         => $u->perfil?->nombre,
                'estado'         => (bool) $u->estado,
                'colaborador_id' => $vinculados[$u->id] ?? null,
            ]);

        $departamentos = Departamento::where('empresa_id', $empresaId)
            ->orderBy('nombre')->get(['id', 'nombre', 'estado']);

        return Inertia::render('RRHH/Colaboradores/Index', [
            'colaboradores' => $colaboradores,
            'puestos'       => $puestos,
            'horarios'      => $horarios,
            'usuarios'      => $usuarios,
            'departamentos' => $departamentos,
            'filtros'       => $request->only(['buscar', 'departamento', 'estado']),
        ]);
    }

    private function reglasBase(?int $colaboradorId = null): array
    {
        $uniqueCedula = $colaboradorId
            ? "required|string|max:13|unique:colaboradores,cedula_ruc,{$colaboradorId}"
            : 'required|string|max:13|unique:colaboradores,cedula_ruc';

        return [
            'cedula_ruc'          => $uniqueCedula,
            'apellidos'           => 'required|string|max:100',
            'nombres'             => 'required|string|max:100',
            'email'               => ['nullable', 'email', 'max:200', Rule::unique('colaboradores', 'email')->ignore($colaboradorId)],
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
            'departamento_id'     => 'nullable|integer|exists:departamentos,id',
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
            'usuario_id'          => ['nullable', 'exists:usuarios,id', Rule::unique('colaboradores', 'usuario_id')->ignore($colaboradorId)],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate($this->reglasBase());
        $this->resolverDepartamento($data, (int) $empresaId);

        $usuario = null;
        if (!empty($data['usuario_id'])) {
            $usuario = Usuario::findOrFail($data['usuario_id']);
            $this->asegurarMismaEmpresa($usuario, (int) $empresaId);
        }

        $colaborador = DB::transaction(function () use ($data, $empresaId, $usuario) {
            $colaborador = Colaborador::create([
                ...collect($data)->except(['usuario_id'])->toArray(),
                'empresa_id' => $empresaId,
                'estado'     => true,
            ]);

            if ($usuario) {
                $colaborador->vincularUsuario($usuario);
            }

            $this->nominaCalculoService->agregarANominasAbiertas($colaborador);

            return $colaborador;
        });

        $mensaje = "Colaborador {$colaborador->apellidos} {$colaborador->nombres} creado correctamente.";
        if ($usuario) {
            $mensaje .= " Vinculado al usuario '{$usuario->username}'.";
        }

        return back()->with('success', $mensaje);
    }

    // Resuelve el departamento del catálogo (debe ser de la misma empresa) y guarda
    // también su nombre en colaboradores.departamento (desnormalizado para filtros).
    private function resolverDepartamento(array &$data, int $empresaId): void
    {
        if (empty($data['departamento_id'])) {
            $data['departamento_id'] = null;
            $data['departamento']    = null;
            return;
        }

        $dep = Departamento::where('empresa_id', $empresaId)->find($data['departamento_id']);
        if (!$dep) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'departamento_id' => 'El departamento no pertenece a esta empresa.',
            ]);
        }

        $data['departamento'] = $dep->nombre;
    }

    // El usuario a vincular debe tener acceso a la empresa del colaborador.
    private function asegurarMismaEmpresa(Usuario $usuario, int $empresaId): void
    {
        $tieneAcceso = (int) $usuario->empresa_id === $empresaId
            || $usuario->empresas()->where('empresas.id', $empresaId)->exists();

        if (!$tieneAcceso) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'usuario_id' => 'El usuario no tiene acceso a la empresa del colaborador.',
            ]);
        }
    }

    public function update(Request $request, Colaborador $colaborador): RedirectResponse
    {
        $rules = $this->reglasBase($colaborador->id);
        $rules['estado_usuario'] = ['boolean'];

        $data = $request->validate($rules);
        $this->resolverDepartamento($data, (int) $colaborador->empresa_id);

        $estadoUsuario = $data['estado_usuario'] ?? null;
        unset($data['estado_usuario']);

        $nuevoUsuarioId = $data['usuario_id'] ?? null;
        unset($data['usuario_id']);

        if ($nuevoUsuarioId) {
            $this->asegurarMismaEmpresa(Usuario::findOrFail($nuevoUsuarioId), (int) $colaborador->empresa_id);
        }

        DB::transaction(function () use ($colaborador, $data, $estadoUsuario, $nuevoUsuarioId) {
            $colaborador->update($data);

            if ((int) $nuevoUsuarioId !== (int) $colaborador->usuario_id) {
                $colaborador->vincularUsuario($nuevoUsuarioId ? Usuario::find($nuevoUsuarioId) : null);
            }

            // Bloqueo/desbloqueo manual del acceso desde la propia ficha del colaborador,
            // independiente del estado laboral (estado del colaborador).
            if ($colaborador->usuario_id && $estadoUsuario !== null) {
                Usuario::where('id', $colaborador->usuario_id)->update(['estado' => $estadoUsuario]);
            }
        });

        return back()->with('success', "Colaborador {$colaborador->apellidos} {$colaborador->nombres} actualizado.");
    }

    public function toggle(Colaborador $colaborador): RedirectResponse
    {
        $nuevoEstado = !$colaborador->estado;
        $usuarioBloqueado = false;

        DB::transaction(function () use ($colaborador, $nuevoEstado, &$usuarioBloqueado) {
            $colaborador->update(['estado' => $nuevoEstado]);

            // Candado de bloqueo inmediato: si el empleado sale (colaborador inactivo),
            // su usuario del sistema se bloquea automáticamente.
            if (!$nuevoEstado && $colaborador->usuario_id) {
                Usuario::where('id', $colaborador->usuario_id)->update(['estado' => false]);
                $usuarioBloqueado = true;
            }

            // Simétrico: al reactivar al colaborador se reactiva su usuario vinculado.
            if ($nuevoEstado && $colaborador->usuario_id) {
                Usuario::where('id', $colaborador->usuario_id)->update(['estado' => true]);
            }
        });

        $mensaje = 'Colaborador ' . ($nuevoEstado ? 'activado' : 'desactivado') . '.';
        if ($usuarioBloqueado) {
            $mensaje .= ' Su usuario del sistema fue bloqueado automáticamente.';
        }

        return back()->with('success', $mensaje);
    }
}
