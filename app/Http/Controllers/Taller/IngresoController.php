<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Taller\Concerns\ListaTecnicosTaller;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\TallerEquipo;
use App\Models\TallerIngreso;
use App\Models\TallerOrdenTrabajo;
use App\Models\TallerTipoEquipo;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngresoController extends Controller
{
    use ListaTecnicosTaller;

    public function __construct(private AuditoriaService $auditoria) {}

    /** Los ingresos solo se ven/editan desde la empresa que los registró. */
    private function autorizarEmpresa(TallerIngreso $ingreso): void
    {
        abort_if((int) $ingreso->empresa_id !== (int) session('empresa_activa_id'), 403);
    }

    /**
     * Equipos visibles para la empresa activa: los que ya ingresaron por ella
     * o los que aún no tienen ningún ingreso. taller_equipos no tiene empresa_id,
     * así que sin este filtro un usuario vería equipos (y sus datos) de otra empresa.
     */
    private function equiposDeEmpresa(): Builder
    {
        $empresaId = session('empresa_activa_id');

        return TallerEquipo::query()->where(function ($q) use ($empresaId) {
            $q->whereHas('ingresos', fn($i) => $i->where('empresa_id', $empresaId))
              ->orDoesntHave('ingresos');
        });
    }

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = TallerIngreso::with(['cliente', 'equipo', 'ordenesTrabajo'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $term = "%{$request->search}%";
            $query->where(function ($w) use ($term) {
                $w->whereHas('cliente', fn($q) => $q
                        ->where('razon_social', 'ilike', $term)
                        ->orWhere('identificacion', 'ilike', $term))
                    ->orWhereHas('equipo', fn($q) => $q
                        ->where('marca', 'ilike', $term)
                        ->orWhere('modelo', 'ilike', $term)
                        ->orWhere('numero_serie', 'ilike', $term))
                    ->orWhereHas('ordenesTrabajo', fn($q) => $q->where('numero', 'ilike', $term));
            });
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->hasta);
        }

        return Inertia::render('Taller/Ingresos/Index', [
            'ingresos' => $query->paginate(15)->withQueryString(),
            'filtros'  => $request->only(['search', 'estado', 'desde', 'hasta']),
        ]);
    }

    private function datosFormulario(): array
    {
        $empresaId = session('empresa_activa_id');

        return [
            'clientes' => Cliente::where('empresa_id', $empresaId)
                ->select('id', 'identificacion', 'razon_social', 'tipo_identificacion', 'email', 'telefono', 'direccion', 'ciudad')
                ->orderBy('razon_social')
                ->get(),
            'tiposEquipo' => TallerTipoEquipo::where('estado', true)
                ->select('id', 'descripcion')
                ->orderBy('descripcion')
                ->get(),
            'tecnicos'       => $this->listaTecnicos(),
            'empresa_activa' => Empresa::findOrFail($empresaId),
        ];
    }

    public function create(): Response
    {
        return Inertia::render('Taller/Ingresos/Form', $this->datosFormulario() + ['ingreso' => null]);
    }

    public function edit(TallerIngreso $ingreso): Response|RedirectResponse
    {
        $this->autorizarEmpresa($ingreso);
        $ingreso->load(['cliente', 'equipo.tipo', 'componentes', 'ordenesTrabajo']);

        if ($ingreso->ordenesTrabajo->contains('estado', 'facturado')) {
            return redirect()->route('taller.ingresos.show', $ingreso->id)
                ->with('flash', ['tipo' => 'error', 'mensaje' => 'El ingreso ya fue facturado y no se puede editar.']);
        }

        return Inertia::render('Taller/Ingresos/Form', $this->datosFormulario() + ['ingreso' => $ingreso]);
    }

    public function buscarEquipo(Request $request): JsonResponse
    {
        $serie = trim((string) $request->get('serie', ''));
        if ($serie === '') {
            return response()->json(['found' => false, 'equipo' => null]);
        }

        $equipo = $this->equiposDeEmpresa()->with('tipo')
            ->where('numero_serie', $serie)
            ->first();

        return response()->json(['found' => (bool) $equipo, 'equipo' => $equipo]);
    }

    /** Reglas de validación compartidas por store() y update(). */
    private function reglas(int $empresaId): array
    {
        return [
            'cliente_id'                     => ['required', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $empresaId)],
            'equipo.equipo_id'               => 'nullable|integer|exists:taller_equipos,id',
            'equipo.tipo_id'                 => 'nullable|integer|exists:taller_tipos_equipo,id',
            'equipo.marca'                   => 'nullable|string|max:100',
            'equipo.modelo'                  => 'nullable|string|max:100',
            'equipo.numero_serie'            => 'nullable|string|max:100',
            'equipo.color'                   => 'nullable|string|max:50',
            'equipo.medida'                  => 'nullable|string|max:50',
            'equipo.adicional'               => 'nullable|string|max:200',
            'equipo.observaciones'           => 'nullable|string',
            'diagnostico_inicial'            => 'required|string',
            'observaciones'                  => 'nullable|string',
            'tecnico_id'                     => 'nullable|integer|exists:usuarios,id',
            'descripcion_trabajo'            => 'nullable|string',
            'imagen_data'                    => ['nullable', 'string', 'max:8000000', 'regex:#^data:image/(jpeg|png|webp);base64,#'],
            'quitar_imagen'                  => 'nullable|boolean',
            'componentes'                    => 'nullable|array|max:50',
            'componentes.*.nombre'           => 'required|string|max:150',
            'componentes.*.funciona'         => 'required|boolean',
            'componentes.*.accion'           => 'required|integer|in:0,1',
            'componentes.*.descripcion'      => 'nullable|string',
            'componentes.*.costo'            => 'nullable|numeric|min:0',
        ];
    }

    /** Guarda la foto (data URL ya validada) en disco privado y devuelve su ruta relativa. */
    private function guardarImagen(string $dataUrl, int $ingresoId): ?string
    {
        [$cabecera, $base64] = explode(',', $dataUrl, 2);
        $binario = base64_decode($base64, true);
        if ($binario === false || @getimagesizefromstring($binario) === false) {
            return null;
        }

        $ext  = str_contains($cabecera, 'png') ? 'png' : (str_contains($cabecera, 'webp') ? 'webp' : 'jpg');
        $ruta = "taller/ingresos/{$ingresoId}-" . now()->format('YmdHis') . ".{$ext}";
        Storage::disk('local')->put($ruta, $binario);

        return $ruta;
    }

    private function borrarImagen(?string $ruta): void
    {
        if ($ruta && !preg_match('#^https?://#i', $ruta) && Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->delete($ruta);
        }
    }

    /** Crea o actualiza el equipo según el número de serie / id enviado. */
    private function resolverEquipo(array $equipoData): TallerEquipo
    {
        $atributos = [
            'tipo_id'       => $equipoData['tipo_id'] ?? null,
            'marca'         => $equipoData['marca'] ?? null,
            'modelo'        => $equipoData['modelo'] ?? null,
            'numero_serie'  => $equipoData['numero_serie'] ?? null,
            'color'         => $equipoData['color'] ?? null,
            'medida'        => $equipoData['medida'] ?? null,
            'adicional'     => $equipoData['adicional'] ?? null,
            'observaciones' => $equipoData['observaciones'] ?? null,
        ];

        $equipo = null;
        if (!empty($equipoData['equipo_id'])) {
            $equipo = $this->equiposDeEmpresa()->find($equipoData['equipo_id']);
        }
        if (!$equipo && !empty($equipoData['numero_serie'])) {
            $equipo = $this->equiposDeEmpresa()->where('numero_serie', $equipoData['numero_serie'])->first();
        }

        if ($equipo) {
            // Como en el legacy, los datos editados del equipo existente se conservan.
            $equipo->update($atributos);
            return $equipo;
        }

        return TallerEquipo::create($atributos);
    }

    private function sincronizarComponentes(TallerIngreso $ingreso, array $componentes): void
    {
        $ingreso->componentes()->delete();
        foreach ($componentes as $c) {
            $ingreso->componentes()->create([
                'nombre'      => $c['nombre'],
                'funciona'    => (bool) $c['funciona'],
                'accion'      => (int) $c['accion'],
                'descripcion' => $c['descripcion'] ?? null,
                'costo'       => (float) ($c['costo'] ?? 0),
            ]);
        }
    }

    /** El técnico solo puede ser alguien de la empresa activa. */
    private function tecnicoValido(?int $tecnicoId): ?int
    {
        if (!$tecnicoId) {
            return null;
        }

        return $this->listaTecnicos()->contains('id', $tecnicoId) ? $tecnicoId : null;
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $data = $request->validate($this->reglas($empresaId));

        $ingreso = DB::transaction(function () use ($data, $empresaId) {
            $equipo = $this->resolverEquipo($data['equipo'] ?? []);

            $ingreso = TallerIngreso::create([
                'empresa_id'          => $empresaId,
                'cliente_id'          => $data['cliente_id'],
                'equipo_id'           => $equipo->id,
                'usuario_id'          => Auth::id(),
                'fecha'               => now()->toDateString(),
                'hora'                => now()->toTimeString(),
                'diagnostico_inicial' => $data['diagnostico_inicial'],
                'observaciones'       => $data['observaciones'] ?? null,
                'estado'              => 0,
            ]);

            if (!empty($data['imagen_data'])) {
                $ingreso->update(['imagen' => $this->guardarImagen($data['imagen_data'], $ingreso->id)]);
            }

            $this->sincronizarComponentes($ingreso, $data['componentes'] ?? []);

            TallerOrdenTrabajo::create([
                'empresa_id'          => $empresaId,
                'ingreso_id'          => $ingreso->id,
                'tecnico_id'          => $this->tecnicoValido($data['tecnico_id'] ?? null),
                'numero'              => sprintf('OT-%s-%06d', now()->year, $ingreso->id),
                'fecha_inicio'        => now()->toDateString(),
                'hora_inicio'         => now()->toTimeString(),
                'descripcion_trabajo' => $data['descripcion_trabajo'] ?? null,
                'tipo_orden'          => 1,
                'estado'              => 'pendiente',
            ]);

            return $ingreso;
        });

        $this->auditoria->documento('crear', 'taller', 'ingresos', $ingreso->id, "Ingreso de taller #{$ingreso->id} creado");

        return redirect()->route('taller.ingresos.show', $ingreso->id)
            ->with('success', 'Ingreso registrado correctamente.');
    }

    public function update(Request $request, TallerIngreso $ingreso): RedirectResponse
    {
        $this->autorizarEmpresa($ingreso);
        $empresaId = session('empresa_activa_id');
        $ingreso->load('ordenesTrabajo');

        if ($ingreso->ordenesTrabajo->contains('estado', 'facturado')) {
            return back()->withErrors(['error' => 'El ingreso ya fue facturado y no se puede editar.']);
        }

        $data = $request->validate($this->reglas($empresaId));

        DB::transaction(function () use ($ingreso, $data) {
            $equipo = $this->resolverEquipo($data['equipo'] ?? []);

            $cambios = [
                'cliente_id'          => $data['cliente_id'],
                'equipo_id'           => $equipo->id,
                'diagnostico_inicial' => $data['diagnostico_inicial'],
                'observaciones'       => $data['observaciones'] ?? null,
            ];

            if (!empty($data['imagen_data'])) {
                $nueva = $this->guardarImagen($data['imagen_data'], $ingreso->id);
                if ($nueva) {
                    $this->borrarImagen($ingreso->imagen);
                    $cambios['imagen'] = $nueva;
                }
            } elseif (!empty($data['quitar_imagen'])) {
                $this->borrarImagen($ingreso->imagen);
                $cambios['imagen'] = null;
            }

            $ingreso->update($cambios);
            $this->sincronizarComponentes($ingreso, $data['componentes'] ?? []);

            $ot = $ingreso->ordenesTrabajo->sortByDesc('id')->first();
            if ($ot) {
                $ot->update([
                    'tecnico_id'          => $this->tecnicoValido($data['tecnico_id'] ?? null),
                    'descripcion_trabajo' => $data['descripcion_trabajo'] ?? null,
                ]);
            }
        });

        $this->auditoria->documento('editar', 'taller', 'ingresos', $ingreso->id, "Ingreso de taller #{$ingreso->id} actualizado");

        return redirect()->route('taller.ingresos.show', $ingreso->id)
            ->with('success', 'Ingreso actualizado correctamente.');
    }

    /** Solo se elimina un ingreso que no ha tenido ningún trabajo (OT pendiente sin diagnósticos ni repuestos). */
    public function destroy(TallerIngreso $ingreso): RedirectResponse
    {
        $this->autorizarEmpresa($ingreso);
        $ingreso->load('ordenesTrabajo');

        foreach ($ingreso->ordenesTrabajo as $ot) {
            if ($ot->estado !== 'pendiente' || $ot->diagnosticos()->exists() || $ot->repuestos()->exists()) {
                return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'No se puede eliminar: la orden ya tiene trabajo registrado.']);
            }
        }

        DB::transaction(function () use ($ingreso) {
            $this->borrarImagen($ingreso->imagen);
            $ingreso->ordenesTrabajo()->delete();
            $ingreso->componentes()->delete();
            $ingreso->delete();
        });

        $this->auditoria->documento('eliminar', 'taller', 'ingresos', $ingreso->id, "Ingreso de taller #{$ingreso->id} eliminado");

        return redirect()->route('taller.ingresos.index')
            ->with('success', 'Ingreso eliminado correctamente.');
    }

    public function show(TallerIngreso $ingreso): Response
    {
        $this->autorizarEmpresa($ingreso);

        $ingreso->load(['cliente', 'equipo.tipo', 'usuario', 'ordenesTrabajo.tecnico', 'componentes']);

        return Inertia::render('Taller/Ingresos/Show', [
            'ingreso' => $ingreso,
        ]);
    }

    /** Sirve la foto del equipo (disco privado) solo a usuarios de la misma empresa. */
    public function imagen(TallerIngreso $ingreso): StreamedResponse|HttpResponse
    {
        $this->autorizarEmpresa($ingreso);

        $ruta = $ingreso->imagen;
        abort_if(!$ruta || preg_match('#^https?://#i', $ruta) || !Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta);
    }

    public function pdfOrdenTrabajo(Request $request, TallerIngreso $ingreso): HttpResponse
    {
        $this->autorizarEmpresa($ingreso);

        $ingreso->load(['cliente', 'equipo.tipo', 'ordenesTrabajo.tecnico', 'componentes']);

        // Hoy siempre hay exactamente una OT por ingreso (se crean juntas en
        // store()), pero el modelo permite varias a futuro — se imprime la
        // más reciente.
        $ot = $ingreso->ordenesTrabajo->sortByDesc('id')->first();

        // Nota: no se habilita isRemoteEnabled (SSRF). La foto del equipo se
        // lee del disco local y se incrusta como data URI.
        $imagenDataUri = null;
        if ($ingreso->imagen && !preg_match('#^https?://#i', $ingreso->imagen) && Storage::disk('local')->exists($ingreso->imagen)) {
            $mime = Storage::disk('local')->mimeType($ingreso->imagen) ?: 'image/jpeg';
            $imagenDataUri = 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('local')->get($ingreso->imagen));
        }

        $pdf = Pdf::loadView('pdf.taller.orden-trabajo', [
            'ingreso'       => $ingreso,
            'ot'            => $ot,
            'logoPath'      => public_path('images/logo-altamira.png'),
            'imagenDataUri' => $imagenDataUri,
        ])->setPaper('a4', 'portrait');

        $nombreArchivo = 'orden-trabajo-' . ($ot?->id ?? $ingreso->id) . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($nombreArchivo)
            : $pdf->stream($nombreArchivo);
    }
}
