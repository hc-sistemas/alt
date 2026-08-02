<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Horario;
use App\Models\Nomina;
use App\Models\NominaDetalle;
use App\Models\Perfil;
use App\Models\PuestoTrabajo;
use App\Models\Usuario;
use App\Services\NominaCalculoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ColaboradorController extends Controller
{
    // Roles de acceso disponibles desde la ficha del colaborador (sección 5. Seguridad y Sistema).
    // super_admin y contador se gestionan únicamente desde Configuración > Usuarios.
    private const PERFILES_ACCESO = ['admin', 'vendedor', 'tecnico', 'bodeguero'];

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

        $usuarios = Usuario::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'email']);

        $departamentos = Colaborador::where('empresa_id', $empresaId)
            ->whereNotNull('departamento')
            ->distinct()->pluck('departamento')->sort()->values();

        $perfiles = Perfil::whereIn('nombre', self::PERFILES_ACCESO)
            ->orderBy('nombre')->get(['id', 'nombre']);

        return Inertia::render('RRHH/Colaboradores/Index', [
            'colaboradores' => $colaboradores,
            'puestos'       => $puestos,
            'horarios'      => $horarios,
            'usuarios'      => $usuarios,
            'departamentos' => $departamentos,
            'perfiles'      => $perfiles,
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
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $rules = $this->reglasBase();

        // Sección 5. Seguridad y Sistema — crea el usuario del ERP en el mismo flujo.
        // Todos nullable por defecto: un colaborador sin acceso al sistema es válido
        // (personas bajo contrato/factura que no necesitan iniciar sesión).
        $rules['username']       = ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:usuarios,username'];
        $rules['password']       = ['nullable', 'string'];
        $rules['perfil_id']      = ['nullable', 'exists:perfiles,id'];
        $rules['estado_usuario'] = ['boolean'];

        if ($request->filled('username')) {
            // Si se va a crear el usuario, el correo es obligatorio (login/notificaciones)
            // y debe ser único también en la tabla usuarios, no solo en colaboradores.
            $rules['email'][0] = 'required';
            $rules['email'][]  = Rule::unique('usuarios', 'email');
            $rules['password'] = ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()];
            $rules['perfil_id'] = ['required', 'exists:perfiles,id'];
        }

        $data = $request->validate($rules);

        $colaborador = DB::transaction(function () use ($data, $empresaId) {
            $colaborador = Colaborador::create([
                ...collect($data)->except(['username', 'password', 'perfil_id', 'estado_usuario'])->toArray(),
                'empresa_id' => $empresaId,
                'estado'     => true,
            ]);

            if (!empty($data['username'])) {
                $usuario = Usuario::create([
                    'empresa_id'     => $empresaId,
                    'perfil_id'      => $data['perfil_id'],
                    'colaborador_id' => $colaborador->id,
                    'nombre'         => "{$colaborador->apellidos} {$colaborador->nombres}",
                    'email'          => $data['email'],
                    'username'       => $data['username'],
                    'password'       => Hash::make($data['password']),
                    'estado'         => $data['estado_usuario'] ?? true,
                ]);
                $usuario->empresas()->sync([$empresaId]);

                $colaborador->usuario_id = $usuario->id;
                $colaborador->save();
            }

            $this->crearFilasNominaVigente($colaborador);

            return $colaborador;
        });

        $mensaje = "Colaborador {$colaborador->apellidos} {$colaborador->nombres} creado correctamente.";
        if (!empty($data['username'])) {
            $mensaje .= " Usuario '{$data['username']}' creado con acceso al sistema.";
        }

        return back()->with('success', $mensaje);
    }

    // Al registrar un colaborador nuevo, debe aparecer automáticamente en
    // cualquier nómina del ejercicio vigente que YA exista pero siga en
    // 'borrador' (mensual o quincenal) — evita el registro huérfano de tener
    // que re-generar o editar manualmente la nómina para incluirlo. Nóminas
    // ya 'procesado'/'pagado' no se tocan (están cerradas contablemente); si
    // no hay ninguna nómina en borrador para el mes actual, no hay nada que
    // crear todavía — se generará con normalidad cuando el usuario presione
    // "Generar Nómina", momento en que el colaborador ya estará activo y se
    // incluirá solo.
    private function crearFilasNominaVigente(Colaborador $colaborador): void
    {
        $nominasAbiertas = Nomina::where('empresa_id', $colaborador->empresa_id)
            ->where('estado', 'borrador')
            ->where('anio', now()->year)
            ->where('mes', now()->month)
            ->get();

        foreach ($nominasAbiertas as $nomina) {
            $yaExiste = NominaDetalle::where('nomina_id', $nomina->id)
                ->where('colaborador_id', $colaborador->id)
                ->exists();

            if ($yaExiste) {
                continue;
            }

            $detalle = $this->nominaCalculoService->calcularDetalle($colaborador, [
                'periodo_tipo' => $nomina->periodo_tipo,
                'anio'         => $nomina->anio,
                'mes'          => $nomina->mes,
                'quincena'     => $nomina->quincena,
            ], $nomina->id);

            NominaDetalle::create($detalle);

            $nomina->update([
                'total_ingresos' => round((float) $nomina->total_ingresos + (float) $detalle['total_ingresos'], 2),
                'total_egresos'  => round((float) $nomina->total_egresos + (float) $detalle['total_egresos'], 2),
                'total_neto'     => round((float) $nomina->total_neto + (float) $detalle['neto_pagar'], 2),
            ]);
        }
    }

    public function update(Request $request, Colaborador $colaborador): RedirectResponse
    {
        $rules = $this->reglasBase($colaborador->id);
        $rules['estado_usuario'] = ['boolean'];

        $data = $request->validate($rules);

        $estadoUsuario = $data['estado_usuario'] ?? null;
        unset($data['estado_usuario']);

        DB::transaction(function () use ($colaborador, $data, $estadoUsuario) {
            $colaborador->update($data);

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
        });

        $mensaje = 'Colaborador ' . ($nuevoEstado ? 'activado' : 'desactivado') . '.';
        if ($usuarioBloqueado) {
            $mensaje .= ' Su usuario del sistema fue bloqueado automáticamente.';
        }

        return back()->with('success', $mensaje);
    }
}
