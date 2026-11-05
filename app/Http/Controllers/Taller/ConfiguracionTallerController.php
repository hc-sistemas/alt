<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ajustes del Taller por empresa (02-10): condiciones del servicio impresas en el documento de ingreso
 * (DEC-054, texto editable mientras Gerencia no lo defina) y el IVA que se aplica al servicio técnico
 * (DEC-052 define el valor del servicio; el IVA queda configurable).
 */
class ConfiguracionTallerController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    private function valor(int $empresaId, string $clave, string $defecto): string
    {
        $v = DB::table('configuraciones')->where('empresa_id', $empresaId)->where('clave', $clave)->value('valor');

        return $v === null ? $defecto : (string) $v;
    }

    public function index(): Response
    {
        $empresaId = (int) session('empresa_activa_id');

        return Inertia::render('Taller/Configuracion/Index', [
            'condiciones'  => $this->valor($empresaId, 'taller_condiciones_servicio', ''),
            'iva_servicio' => (float) $this->valor($empresaId, 'taller_servicio_iva_pct', '15'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');

        $data = $request->validate([
            'condiciones'  => 'required|string|max:3000',
            'iva_servicio' => 'required|numeric|min:0|max:100',
        ], [
            'condiciones.required' => 'Las condiciones del servicio no pueden quedar vacías.',
        ]);

        foreach ([
            'taller_condiciones_servicio' => trim($data['condiciones']),
            'taller_servicio_iva_pct'     => (string) $data['iva_servicio'],
        ] as $clave => $valor) {
            $existe = DB::table('configuraciones')->where('empresa_id', $empresaId)->where('clave', $clave)->exists();
            if ($existe) {
                DB::table('configuraciones')->where('empresa_id', $empresaId)->where('clave', $clave)
                    ->update(['valor' => $valor, 'updated_at' => now()]);
            } else {
                DB::table('configuraciones')->insert([
                    'empresa_id' => $empresaId, 'clave' => $clave, 'valor' => $valor, 'tipo' => 'texto',
                    'descripcion' => 'Taller', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        $this->auditoria->documento('editar', 'taller', 'configuracion', $empresaId, 'Configuración del Taller actualizada');

        return back()->with('success', 'Configuración del Taller guardada.');
    }
}
