<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function showForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->checkRateLimit($request);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), 60);

            $this->auditoria->sesion('login_fail', $request->email);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales proporcionadas son incorrectas.',
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate();

        $user = Auth::user();
        $user->update(['ultimo_acceso' => now()]);

        $this->auditoria->sesion('login_ok', $user->email);

        // Si el usuario tiene acceso a más de una empresa, redirigir a selección
        if ($user->empresas->count() > 1) {
            return redirect()->route('empresa.seleccionar');
        }

        // Empresa por defecto
        $primeraEmpresa = $user->empresas->first() ?? $user->empresa;
        if ($primeraEmpresa) {
            $request->session()->put('empresa_activa_id', $primeraEmpresa->id);
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->auditoria->sesion('logout');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function checkRateLimit(Request $request): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            $seconds = RateLimiter::availableIn($this->throttleKey($request));

            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Por favor espera {$seconds} segundos.",
            ]);
        }
    }

    private function throttleKey(Request $request): string
    {
        return 'login.' . $request->ip();
    }
}
