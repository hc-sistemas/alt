<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use App\Models\Colaborador;
use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $query = Usuario::with(['perfil', 'empresa'])
            ->when($request->search, fn($q) => $q->where(function($q) use ($request) {
                $q->where('nombre', 'ilike', "%{$request->search}%")
                  ->orWhere('email', 'ilike', "%{$request->search}%")
                  ->orWhere('username', 'ilike', "%{$request->search}%");
            }))
            ->when($request->perfil_id, fn($q) => $q->where('perfil_id', $request->perfil_id))
            ->when($request->estado !== null, fn($q) => $q->where('estado', $request->estado === 'activo'));

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
            'perfiles' => Perfil::orderBy('nombre')->get(['id', 'nombre']),
            'empresas' => Empresa::where('estado', true)->orderBy('nombre_comercial')->get(['id', 'nombre_comercial', 'ruc']),
            'centros_costo' => CentroCosto::where('estado', true)->with('empresa')->orderBy('nombre')->get(['id', 'nombre', 'empresa_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

        $usuario = Usuario::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'codigo_aprobacion' => isset($data['codigo_aprobacion']) ? Hash::make($data['codigo_aprobacion']) : null,
        ]);

        $usuario->empresas()->sync($data['empresas']);

        $this->auditoria->documento('crear', 'configuracion', 'usuarios', $usuario->id,
            "Usuario {$usuario->username} creado con perfil {$usuario->perfil->nombre}");

        return redirect()->route('configuracion.usuarios.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function edit(Usuario $usuario): Response
    {
        return Inertia::render('Configuracion/Usuarios/Form', [
            'usuario' => $usuario->load(['perfil', 'empresas', 'centroCosto']),
            'perfiles' => Perfil::orderBy('nombre')->get(['id', 'nombre']),
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

        $usuario->update($data);
        $usuario->empresas()->sync($data['empresas']);

        $this->auditoria->documento('editar', 'configuracion', 'usuarios', $usuario->id,
            "Usuario {$usuario->username} actualizado");

        return redirect()->route('configuracion.usuarios.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function toggleEstado(Usuario $usuario): RedirectResponse
    {
        $usuario->update(['estado' => !$usuario->estado]);

        $this->auditoria->documento('editar', 'configuracion', 'usuarios', $usuario->id,
            "Usuario {$usuario->username} " . ($usuario->estado ? 'activado' : 'desactivado'));

        return back()->with('success', 'Estado actualizado.');
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

        DB::transaction(function () use ($usuario, $data) {
            // Quitar cualquier vínculo anterior de este usuario
            Colaborador::where('usuario_id', $usuario->id)->update(['usuario_id' => null]);

            if (!empty($data['colaborador_id'])) {
                // Quitar cualquier usuario previo que tuviera este colaborador
                Colaborador::where('id', $data['colaborador_id'])
                    ->update(['usuario_id' => $usuario->id]);
            }
        });

        $this->auditoria->documento('editar', 'configuracion', 'usuarios', $usuario->id,
            "Colaborador vinculado al usuario {$usuario->username}");

        return back()->with('success', 'Colaborador actualizado correctamente.');
    }
}
