<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\NominaCalculoService;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
        private NominaCalculoService $nominaCalculoService,
    ) {}

    public function index(Request $request): Response
    {
        $query = Usuario::with(['perfil', 'empresa'])
            ->when($request->search, fn($q) => $q->where(function($q) use ($request) {
                $q->where('nombre', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%")
                  ->orWhere('username', 'ilike', "%{$request->search}%");
            }))
            ->when($request->perfil_id, fn($q) => $q->where('perfil_id', $request->perfil_id))
            ->when($request->estado !== null, fn($q) => $q->where('estado', $request->estado === 'activo'))
            ->orderBy('nombre');

        return Inertia::render('Configuracion/Usuarios/Index', [
            'usuarios'      => $query->paginate(15)->withQueryString(),
            'perfiles'      => Perfil::orderBy('nombre')->get(['id', 'nombre']),
            'colaboradores' => Colaborador::where('empresa_id', session('empresa_activa_id'))
                ->where('estado', true)
                ->orderBy('apellidos')
                ->get(['id', 'apellidos', 'nombres', 'usuario_id']),
            'filters' => $request->only(['search', 'perfil_id', 'estado']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Configuracion/Usuarios/Form', [
            'perfiles' => $this->perfilesAsignables(),
            'empresas' => Empresa::where('estado', true)->orderBy('nombre_comercial')->get(['id', 'nombre_comercial', 'ruc']),
            'centros_costo' => CentroCosto::where('estado', true)->with('empresa')->orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
            'colaboradores_disponibles' => $this->colaboradoresDisponibles(),
        ]);
    }

    /** Perfiles que el usuario actual puede asignar (super_admin solo lo ven los super_admin). */
    private function perfilesAsignables()
    {
        return Perfil::orderBy('nombre')
            ->when(Auth::user()?->perfil?->nombre !== 'super_admin', fn($q) => $q->where('nombre', '!=', 'super_admin'))
            ->get(['id', 'nombre']);
    }

    /** Solo un super_admin puede crear/asignar/modificar a un super_admin. */
    private function soloSuperAdminPuede(?int $perfilId = null, ?Usuario $objetivo = null): bool
    {
        $soy = Auth::user()?->perfil?->nombre === 'super_admin';
        if ($soy) {
            return true;
        }

        $perfilNuevoEsSuper = $perfilId !== null
            && Perfil::where('id', $perfilId)->where('nombre', 'super_admin')->exists();
        $objetivoEsSuper = $objetivo !== null && $objetivo->perfil?->nombre === 'super_admin';

        return !$perfilNuevoEsSuper && !$objetivoEsSuper;
    }

    // Colaboradores activos que todavía no tienen usuario (para vincular al crear uno).
    private function colaboradoresDisponibles()
    {
        return Colaborador::whereNull('usuario_id')
            ->where('estado', true)
            ->orderBy('apellidos')->orderBy('nombres')
            ->get(['id', 'empresa_id', 'apellidos', 'nombres', 'cedula_ruc']);
    }

    public function store(Request $request): RedirectResponse
    {
        $reglasEmpleado = [
            'es_empleado'      => ['boolean'],
            'colaborador_modo' => ['nullable', 'in:crear,vincular'],
        ];
        if ($request->boolean('es_empleado')) {
            if ($request->input('colaborador_modo') === 'vincular') {
                $reglasEmpleado['colaborador_id'] = ['required', 'integer', 'exists:colaboradores,id',
                    Rule::unique('colaboradores', 'id')->where(fn($q) => $q->whereNotNull('usuario_id'))];
            } else {
                $reglasEmpleado += [
                    'colab_apellidos'     => ['required', 'string', 'max:100'],
                    'colab_nombres'       => ['required', 'string', 'max:100'],
                    'colab_cedula_ruc'    => ['required', 'string', 'max:13', 'unique:colaboradores,cedula_ruc'],
                    'colab_fecha_ingreso' => ['required', 'date'],
                    'colab_sueldo_base'   => ['required', 'numeric', 'min:0'],
                    'colab_cargo'         => ['nullable', 'string', 'max:100'],
                ];
            }
        }

        $request->validate($reglasEmpleado);

        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:usuarios,email'],
            'username' => ['required', 'string', 'max:50', 'unique:usuarios,username', 'alpha_dash'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'empresa_id' => ['required', 'exists:empresas,id'],
            'centro_costo_id' => ['nullable', 'exists:centros_costo,id'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'codigo_aprobacion' => ['nullable', 'string', 'min:4', 'max:6', 'confirmed'],
            'empresas' => ['required', 'array', 'min:1'],
            'empresas.*' => ['exists:empresas,id'],
            'estado' => ['boolean'],
        ]);

        if (!$this->soloSuperAdminPuede((int) $data['perfil_id'])) {
            return back()->withErrors(['perfil_id' => 'Solo un superadministrador puede asignar el perfil superadministrador.'])->withInput();
        }

        $usuario = DB::transaction(function () use ($data, $request) {
            $usuario = Usuario::create([
                ...$data,
                'password' => Hash::make($data['password']),
                'codigo_aprobacion' => isset($data['codigo_aprobacion']) ? Hash::make($data['codigo_aprobacion']) : null,
            ]);

            $usuario->empresas()->sync($data['empresas']);

            if ($request->boolean('es_empleado')) {
                if ($request->input('colaborador_modo') === 'vincular') {
                    $colaborador = Colaborador::findOrFail($request->input('colaborador_id'));
                    $tieneAcceso = in_array((int) $colaborador->empresa_id, array_map('intval', $data['empresas']), true);
                    if (!$tieneAcceso) {
                        throw \Illuminate\Validation\ValidationException::withMessages([
                            'colaborador_id' => 'El usuario debe tener acceso a la empresa del colaborador.',
                        ]);
                    }
                } else {
                    $colaborador = Colaborador::create([
                        'empresa_id'    => $data['empresa_id'],
                        'apellidos'     => $request->input('colab_apellidos'),
                        'nombres'       => $request->input('colab_nombres'),
                        'cedula_ruc'    => $request->input('colab_cedula_ruc'),
                        'fecha_ingreso' => $request->input('colab_fecha_ingreso'),
                        'sueldo_base'   => $request->input('colab_sueldo_base'),
                        'cargo'         => $request->input('colab_cargo'),
                        'email'         => Colaborador::where('email', $data['email'])->exists() ? null : $data['email'],
                        'telefono'      => $data['telefono'] ?? null,
                        'estado'        => (bool) ($data['estado'] ?? true),
                    ]);
                    $this->nominaCalculoService->agregarANominasAbiertas($colaborador);
                }

                $colaborador->vincularUsuario($usuario);
            }

            return $usuario;
        });

        $this->auditoria->documento('crear', 'configuracion', 'usuarios', $usuario->id,
            "Usuario {$usuario->username} creado con perfil {$usuario->perfil->nombre}");

        return redirect()->route('configuracion.usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Usuario $usuario): Response
    {
        return Inertia::render('Configuracion/Usuarios/Form', [
            'usuario' => $usuario->load(['perfil', 'empresas', 'centroCosto']),
            'perfiles' => $this->perfilesAsignables(),
            'empresas' => Empresa::where('estado', true)->orderBy('nombre_comercial')->get(['id', 'nombre_comercial', 'ruc']),
            'centros_costo' => CentroCosto::where('estado', true)->with('empresa')->orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
        ]);
    }

    public function update(Request $request, Usuario $usuario): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', "unique:usuarios,email,{$usuario->id}"],
            'username' => ['required', 'string', 'max:50', "unique:usuarios,username,{$usuario->id}", 'alpha_dash'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'empresa_id' => ['required', 'exists:empresas,id'],
            'centro_costo_id' => ['nullable', 'exists:centros_costo,id'],
            'password' => ['nullable', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'codigo_aprobacion' => ['nullable', 'string', 'min:4', 'max:6', 'confirmed'],
            'empresas' => ['required', 'array', 'min:1'],
            'empresas.*' => ['exists:empresas,id'],
            'estado' => ['boolean'],
        ]);

        if (!$this->soloSuperAdminPuede((int) $data['perfil_id'], $usuario)) {
            return back()->withErrors(['perfil_id' => 'Solo un superadministrador puede modificar o asignar el perfil superadministrador.'])->withInput();
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if (!empty($data['codigo_aprobacion'])) {
            $data['codigo_aprobacion'] = Hash::make($data['codigo_aprobacion']);
        } else {
            unset($data['codigo_aprobacion']);
        }

        $estadoAnterior = (bool) $usuario->estado;

        $usuario->update($data);
        $usuario->empresas()->sync($data['empresas']);

        if ($estadoAnterior !== (bool) $usuario->estado) {
            $this->sincronizarEstadoColaborador($usuario);
        }

        $this->auditoria->documento('editar', 'configuracion', 'usuarios', $usuario->id,
            "Usuario {$usuario->username} actualizado");

        return redirect()->route('configuracion.usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleEstado(Usuario $usuario): RedirectResponse
    {
        if (!$this->soloSuperAdminPuede(null, $usuario)) {
            return back()->withErrors(['error' => 'Solo un superadministrador puede activar o desactivar a otro superadministrador.']);
        }

        $usuario->update(['estado' => !$usuario->estado]);
        $this->sincronizarEstadoColaborador($usuario);

        $this->auditoria->documento('editar', 'configuracion', 'usuarios', $usuario->id,
            "Usuario {$usuario->username} " . ($usuario->estado ? 'activado' : 'desactivado'));

        return back()->with('success', 'Estado actualizado.');
    }

    // Activar/desactivar el usuario mantiene en sincronía al colaborador vinculado
    // (el sentido inverso ya lo hace ColaboradorController::toggle()).
    private function sincronizarEstadoColaborador(Usuario $usuario): void
    {
        Colaborador::where('usuario_id', $usuario->id)
            ->update(['estado' => (bool) $usuario->estado]);
    }

    public function show(Usuario $usuario): Response
    {
        return Inertia::render('Configuracion/Usuarios/Show', [
            'usuario' => $usuario->load('perfil'),
            'accesos' => $usuario->logSesiones()
                ->latest('created_at')
                ->limit(30)
                ->get(),
        ]);
    }

    public function vincularColaborador(Request $request, Usuario $usuario): RedirectResponse
    {
        $data = $request->validate([
            'colaborador_id' => ['nullable', 'integer', 'exists:colaboradores,id'],
        ]);

        $nuevo = !empty($data['colaborador_id']) ? Colaborador::findOrFail($data['colaborador_id']) : null;

        if ($nuevo) {
            $tieneAcceso = (int) $usuario->empresa_id === (int) $nuevo->empresa_id
                || $usuario->empresas()->where('empresas.id', $nuevo->empresa_id)->exists();
            if (!$tieneAcceso) {
                return back()->with('error', 'El usuario no tiene acceso a la empresa de ese colaborador.');
            }
        }

        DB::transaction(function () use ($usuario, $nuevo) {
            // Libera el colaborador que este usuario tuviera antes (ambas columnas)
            $actual = Colaborador::where('usuario_id', $usuario->id)->first();
            if ($actual && (!$nuevo || $actual->id !== $nuevo->id)) {
                $actual->vincularUsuario(null);
            }

            if ($nuevo) {
                $nuevo->vincularUsuario($usuario);
            } else {
                Usuario::where('id', $usuario->id)->update(['colaborador_id' => null]);
            }
        });

        $this->auditoria->documento('editar', 'configuracion', 'usuarios', $usuario->id,
            "Colaborador vinculado al usuario {$usuario->username}");

        return back()->with('success', 'Colaborador actualizado correctamente.');
    }
}
