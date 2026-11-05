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
        // Una sola lista de estados (02-10 §2.11): se filtra por el estado de la orden de trabajo
        if ($request->filled('estado')) {
            $query->whereHas('ordenesTrabajo', fn($q) => $q->where('estado', $request->estado));
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
        return Inertia::render('Taller/Ingresos/Form', $this->datosFormulario() + ['ingreso' => null, 'fotos_extra' => []]);
    }

    public function edit(TallerIngreso $ingreso): Response|RedirectResponse
    {
        $this->autorizarEmpresa($ingreso);
        $ingreso->load(['cliente', 'equipo.tipo', 'componentes', 'ordenesTrabajo']);

        if ($ingreso->ordenesTrabajo->contains('estado', 'facturado')) {
            return redirect()->route('taller.ingresos.show', $ingreso->id)
                ->with('flash', ['tipo' => 'error', 'mensaje' => 'El ingreso ya fue facturado y no se puede editar.']);
        }

        return Inertia::render('Taller/Ingresos/Form', $this->datosFormulario() + [
            'ingreso'     => $ingreso,
            'fotos_extra' => DB::table('taller_ingreso_fotos')->where('ingreso_id', $ingreso->id)->orderBy('orden')->pluck('orden')->values(),
        ]);
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
            'equipo.tipo_id'                 => 'required|integer|exists:taller_tipos_equipo,id',
            'equipo.marca'                   => 'required|string|max:100',
            'equipo.modelo'                  => 'required|string|max:100',
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
            // Fotos 2 a 4 del equipo (la 1 es imagen_data); el documento de ingreso imprime hasta 4
            'fotos_extra'                    => 'nullable|array|max:3',
            'fotos_extra.*.orden'            => 'required|integer|in:2,3,4',
            'fotos_extra.*.data'             => ['nullable', 'string', 'max:8000000', 'regex:#^data:image/(jpeg|png|webp);base64,#'],
            'fotos_extra.*.quitar'           => 'nullable|boolean',
            'componentes'                    => 'nullable|array|max:50',
            'componentes.*.nombre'           => 'required|string|max:150',
            'componentes.*.funciona'         => 'required|boolean',
            'componentes.*.accion'           => 'required|integer|in:0,1',
            'componentes.*.descripcion'      => 'nullable|string',
            'componentes.*.costo'            => 'nullable|numeric|min:0',
        ];
    }

    /** Mensajes en español para los campos obligatorios del equipo. */
    private function mensajes(): array
    {
        return [
            'diagnostico_inicial.required' => 'El diagnóstico inicial (problema reportado) es obligatorio.',
            'equipo.tipo_id.required'      => 'El tipo de equipo es obligatorio.',
            'equipo.marca.required'        => 'La marca del equipo es obligatoria.',
            'equipo.modelo.required'       => 'El modelo del equipo es obligatorio.',
            'cliente_id.required'          => 'Debe seleccionar un cliente.',
        ];
    }

    /** Fotos 2 a 4: se guardan en taller_ingreso_fotos; el orden 1 sigue siendo ingresos.imagen. */
    private function sincronizarFotosExtra(TallerIngreso $ingreso, array $fotos): void
    {
        foreach ($fotos as $f) {
            $orden    = (int) $f['orden'];
            $existente = DB::table('taller_ingreso_fotos')->where('ingreso_id', $ingreso->id)->where('orden', $orden)->first();

            if (!empty($f['data'])) {
                $ruta = $this->guardarImagen($f['data'], $ingreso->id);
                if (!$ruta) {
                    continue;
                }
                if ($existente) {
                    $this->borrarImagen($existente->ruta);
                    DB::table('taller_ingreso_fotos')->where('id', $existente->id)->update(['ruta' => $ruta, 'updated_at' => now()]);
                } else {
                    DB::table('taller_ingreso_fotos')->insert([
                        'ingreso_id' => $ingreso->id, 'ruta' => $ruta, 'orden' => $orden,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            } elseif (!empty($f['quitar']) && $existente) {
                $this->borrarImagen($existente->ruta);
                DB::table('taller_ingreso_fotos')->where('id', $existente->id)->delete();
            }
        }
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
        $ruta = "taller/ingresos/{$ingresoId}-" . now()->format('YmdHis') . '-' . substr(md5($base64), 0, 6) . ".{$ext}";
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
        $data = $request->validate($this->reglas($empresaId), $this->mensajes());

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
            $this->sincronizarFotosExtra($ingreso, $data['fotos_extra'] ?? []);

            // Turno por orden de llegada (02-10 §2.4): correlativo del día para la empresa
            $turno = 1 + (int) TallerOrdenTrabajo::where('empresa_id', $empresaId)
                ->whereDate('fecha_inicio', now()->toDateString())->max('turno');

            TallerOrdenTrabajo::create([
                'empresa_id'          => $empresaId,
                'turno'               => $turno,
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

        // Al guardar se abre el documento de ingreso para imprimirlo (02-10 §2.3)
        return redirect()->route('taller.ordenes.show', [
                'orden' => $ingreso->ordenesTrabajo()->value('id'), 'imprimir' => 1,
            ])
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

        $data = $request->validate($this->reglas($empresaId), $this->mensajes());

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
            $this->sincronizarFotosExtra($ingreso, $data['fotos_extra'] ?? []);

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
            foreach (DB::table('taller_ingreso_fotos')->where('ingreso_id', $ingreso->id)->get() as $f) {
                $this->borrarImagen($f->ruta);
            }
            DB::table('taller_ingreso_fotos')->where('ingreso_id', $ingreso->id)->delete();
            $ingreso->ordenesTrabajo()->delete();
            $ingreso->componentes()->delete();
            $ingreso->delete();
        });

        $this->auditoria->documento('eliminar', 'taller', 'ingresos', $ingreso->id, "Ingreso de taller #{$ingreso->id} eliminado");

        return redirect()->route('taller.ingresos.index')
            ->with('success', 'Ingreso eliminado correctamente.');
    }

    /**
     * D1 (CHECKLIST_ERRORES_COMPLICACIONES.md): Ingresos y Órdenes de Trabajo
     * se unieron en una sola pantalla. Como ahora un ingreso solo puede tener
     * una orden (constraint UNIQUE en taller_ordenes_trabajo.ingreso_id — ver
     * migración), "ver ingreso" redirige directo a esa orden, que ya trae
     * cliente/equipo/diagnóstico/repuestos/liquidación en una sola vista.
     */
    public function show(TallerIngreso $ingreso): RedirectResponse
    {
        $this->autorizarEmpresa($ingreso);

        $ordenId = $ingreso->ordenesTrabajo()->value('id');

        if (!$ordenId) {
            // No debería pasar (store() siempre crea la orden junto al
            // ingreso), pero si un dato viejo no la tiene, no se rompe.
            abort(404, 'Este ingreso no tiene una orden de trabajo asociada.');
        }

        return redirect()->route('taller.ordenes.show', $ordenId);
    }

    /** Sirve la foto del equipo (disco privado) solo a usuarios de la misma empresa. */
    public function imagen(TallerIngreso $ingreso): StreamedResponse|HttpResponse
    {
        $this->autorizarEmpresa($ingreso);

        $ruta = $ingreso->imagen;
        $n = (int) request('foto', 1);
        if ($n >= 2 && $n <= 4) {
            $ruta = DB::table('taller_ingreso_fotos')->where('ingreso_id', $ingreso->id)->where('orden', $n)->value('ruta');
        }
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

        // Hasta 4 fotos en miniatura
        $fotos = $imagenDataUri ? [$imagenDataUri] : [];
        foreach (DB::table('taller_ingreso_fotos')->where('ingreso_id', $ingreso->id)->orderBy('orden')->get() as $f) {
            if (count($fotos) >= 4) {
                break;
            }
            if (!preg_match('#^https?://#i', $f->ruta) && Storage::disk('local')->exists($f->ruta)) {
                $fotos[] = 'data:' . (Storage::disk('local')->mimeType($f->ruta) ?: 'image/jpeg') . ';base64,'
                    . base64_encode(Storage::disk('local')->get($f->ruta));
            }
        }

        // El taller factura y se identifica con el RUC de Altamira UIO (empresa del centro de costo FIX)
        $empresaTaller = Empresa::find(
            DB::table('centros_costo')->where('es_taller', true)->where('estado', true)->orderBy('id')->value('empresa_id')
        ) ?? Empresa::find($ingreso->empresa_id);

        $condiciones = DB::table('configuraciones')->where('empresa_id', $ingreso->empresa_id)
            ->where('clave', 'taller_condiciones_servicio')->value('valor')
            ?? DB::table('configuraciones')->where('clave', 'taller_condiciones_servicio')->value('valor');

        $pdf = Pdf::loadView('pdf.taller.orden-trabajo', [
            'ingreso'       => $ingreso,
            'ot'            => $ot,
            'empresa'       => $empresaTaller,
            'recibidoPor'   => \App\Models\Usuario::find($ingreso->usuario_id)?->nombre,
            'condiciones'   => $condiciones,
            'logoPath'      => public_path('images/logo-altamira.png'),
            'fotos'         => $fotos,
        ])->setPaper('a4', 'portrait');

        $nombreArchivo = 'orden-trabajo-' . ($ot?->id ?? $ingreso->id) . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($nombreArchivo)
            : $pdf->stream($nombreArchivo);
    }
}
