<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\CierreCaja;
use App\Services\AsientoService;
use App\Services\CajaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class CierreCajaController extends Controller
{
    // Una caja se supone que abre y cierra el mismo día — cualquiera que
    // siga "abierto" desde el día anterior o antes ya es sospechosa (ver
    // AlertaCajasAbiertas, que usa el mismo umbral para la notificación).
    public const UMBRAL_DIAS_SOSPECHOSA = 1;

    public function __construct(private AsientoService $asientoService, private CajaService $caja) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $cajas   = BancoCaja::where('empresa_id', $empresaId)
            ->cajas()->activos()->orderBy('nombre')->get();
        $centros = CentroCosto::where('empresa_id', $empresaId)->get(['id', 'nombre']);

        $cierres = null;

        if ($request->boolean('buscado')) {
            $query = CierreCaja::where('empresa_id', $empresaId)
                ->with(['bancoCaja', 'centroCosto', 'usuarioApertura', 'usuarioCierre']);

            if ($request->filled('banco_caja_id')) {
                $query->where('banco_caja_id', $request->banco_caja_id);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('centro_costo_id')) {
                $query->where('centro_costo_id', $request->centro_costo_id);
            }
            if ($request->filled('fecha_desde')) {
                $query->where('fecha', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->where('fecha', '<=', $request->fecha_hasta);
            }
            if ($request->filled('buscar')) {
                $q = $request->buscar;
                $query->where(fn($qb) =>
                    $qb->where('observaciones', 'ilike', "%{$q}%")
                       ->orWhereHas('usuarioApertura', fn($u) => $u->where('nombre', 'ilike', "%{$q}%"))
                       ->orWhereHas('usuarioCierre',   fn($u) => $u->where('nombre', 'ilike', "%{$q}%"))
                );
            }

            $cierres = $query->orderByDesc('fecha')->orderByDesc('id')
                ->take(30)->get()
                ->map(function ($c) use ($empresaId) {
                    // OJO: el orden importa — Carbon\Carbon::diffInDays() no es
                    // conmutativo, `now()->diffInDays($fecha)` da NEGATIVO cuando
                    // $fecha es anterior a hoy (confirmado con dato real: -5.85 en
                    // vez de +5). `$fecha->diffInDays(now())` da el signo correcto;
                    // abs()+(int) por seguridad extra ante cualquier variación de
                    // Carbon.
                    $diasAbierta = $c->estado === 'abierto' ? (int) abs($c->fecha->diffInDays(now())) : 0;
                    // Resumen del efectivo del día (solo cajas abiertas): facturas cobradas,
                    // facturado, cobrado y saldo esperado.
                    $resumen = $c->estado === 'abierto' ? $this->caja->resumen($c) : null;

                    return [
                        'resumen'          => $resumen,
                        'saldo_esperado'   => $c->saldo_esperado,
                        'facturas_efectivo' => $c->facturas_efectivo,
                        'total_facturado'  => $c->total_facturado,
                        'observaciones'    => $c->observaciones,
                        'id'               => $c->id,
                        'caja'             => $c->bancoCaja?->nombre,
                        'centro_costo'     => $c->centroCosto?->nombre,
                        'fecha'            => $c->fecha?->format('d/m/Y'),
                        'monto_inicial'    => $c->monto_inicial,
                        'total_cobrado'    => $c->total_cobrado,
                        'total_efectivo'   => $c->total_efectivo,
                        'total_tarjeta'    => $c->total_tarjeta,
                        'diferencia'       => $c->diferencia,
                        'estado'           => $c->estado,
                        'hora_apertura'    => $c->hora_apertura?->format('H:i'),
                        'hora_cierre'      => $c->hora_cierre?->format('H:i'),
                        'usuario_apertura' => $c->usuarioApertura?->nombre,
                        'usuario_cierre'   => $c->usuarioCierre?->nombre,
                        'tiene_diferencia' => $c->tieneDiferencia(),
                        'dias_abierta'     => $diasAbierta,
                        'sospechosa'       => $c->estado === 'abierto' && $diasAbierta >= self::UMBRAL_DIAS_SOSPECHOSA,
                    ];
                });
        }

        $cajaAbiertaModel = CierreCaja::where('empresa_id', $empresaId)
            ->where('estado', 'abierto')
            ->where('fecha', now()->toDateString())
            ->with('bancoCaja')
            ->first();

        // Igual que en $cierres arriba: hora_apertura se formatea aquí porque
        // el cast del modelo es 'datetime' — pasar el modelo crudo serializa
        // un ISO completo (ej. "2026-08-01T02:34:00.000000Z") en vez de "21:34".
        $cajaAbierta = $cajaAbiertaModel ? [
            'id'            => $cajaAbiertaModel->id,
            'banco_caja'    => $cajaAbiertaModel->bancoCaja ? ['nombre' => $cajaAbiertaModel->bancoCaja->nombre] : null,
            'monto_inicial' => $cajaAbiertaModel->monto_inicial,
            'hora_apertura' => $cajaAbiertaModel->hora_apertura?->format('H:i'),
            'resumen'       => $this->caja->resumen($cajaAbiertaModel),
        ] : null;

        // Por caja: ¿es el primer día (lleva conteo) o el saldo inicial viene del día anterior?
        $hoy = now()->toDateString();
        $aperturas = $cajas->mapWithKeys(fn ($c) => [$c->id => $this->caja->estadoApertura((int) $empresaId, $c->id, $hoy)]);

        return Inertia::render('Bancos/Cajas/Index', [
            'cierres'     => $cierres,
            'cajas'       => $cajas,
            'centros'     => $centros,
            'cajaAbierta' => $cajaAbierta,
            'aperturas'   => $aperturas,
            'denominaciones' => collect(CajaService::DENOMINACIONES)->map(fn ($v, $k) => ['clave' => $k, 'valor' => $v, 'tipo' => str_starts_with($k, 'm_') ? 'Moneda' : 'Billete'])->values(),
            'filtros'     => $request->only([
                'banco_caja_id', 'estado', 'centro_costo_id', 'fecha_desde', 'fecha_hasta', 'buscar',
            ]),
        ]);
    }

    /**
     * Apertura (02-05 §2.2): el primer día se escribe el conteo por denominación; desde el
     * segundo día el saldo inicial es automáticamente el saldo final del día anterior.
     */
    public function abrir(Request $request): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        $request->validate([
            'banco_caja_id'   => 'required|exists_empresa:bancos_cajas',
            'centro_costo_id' => 'nullable|exists_empresa:centros_costo',
            'conteo'          => 'nullable|array',
        ], ['banco_caja_id.required' => 'Seleccione la caja que va a abrir.']);

        $hoy = now()->toDateString();
        $caja = BancoCaja::where('empresa_id', $empresaId)->findOrFail($request->banco_caja_id);

        $yaHoy = CierreCaja::where('empresa_id', $empresaId)->where('banco_caja_id', $caja->id)
            ->where('fecha', $hoy)->first();
        if ($yaHoy) {
            return back()->with('error', $yaHoy->estaCerrada()
                ? 'La caja de hoy ya se cerró. Un día cerrado no se vuelve a abrir; corrija con un movimiento de ajuste mañana.'
                : 'Ya hay una caja abierta para hoy en esta caja.');
        }

        $estado = $this->caja->estadoApertura($empresaId, $caja->id, $hoy);
        if ($estado['anterior_abierto']) {
            return back()->with('error', "El día {$estado['fecha_anterior']} de esta caja sigue abierto. Ciérrelo antes de abrir hoy.");
        }

        $conteo = null;
        if ($estado['primer_dia']) {
            $conteo = CajaService::limpiarConteo((array) $request->input('conteo', []));
            $montoInicial = CajaService::totalConteo($conteo)['total'];
            if ($montoInicial <= 0) {
                return back()->with('error', 'Es el primer día de esta caja: ingrese el conteo de efectivo (denominación × cantidad).');
            }
        } else {
            $montoInicial = (float) $estado['saldo_inicial'];
        }

        // Si no se indica centro de costo, se toma el de la caja (así el cierre puede leer las ventas)
        $centroCostoId = $request->centro_costo_id ?: $caja->centro_costo_id;

        CierreCaja::create([
            'empresa_id'          => $empresaId,
            'banco_caja_id'       => $caja->id,
            'centro_costo_id'     => $centroCostoId,
            'fecha'               => $hoy,
            'usuario_apertura_id' => Auth::id(),
            'monto_inicial'       => $montoInicial,
            'conteo_apertura'     => $conteo,
            'estado'              => 'abierto',
            'hora_apertura'       => now(),
            'created_at'          => now(),
        ]);

        return back()->with('success', $estado['primer_dia']
            ? 'Caja abierta con un saldo inicial de $' . number_format($montoInicial, 2) . '.'
            : 'Caja abierta. Saldo inicial automático (cierre anterior): $' . number_format($montoInicial, 2) . '.');
    }

    /**
     * Cierre (02-05 §2.4): conteo físico por denominación; diferencia = conteo − saldo esperado.
     * Con diferencia se exige observación y el asiento de sobrante/faltante se genera al cerrar
     * (PC-CAJ-12 / PC-CAJ-13). Si el asiento no se puede generar, el cierre no se guarda.
     */
    public function cerrar(Request $request, CierreCaja $cierre): RedirectResponse
    {
        if ($cierre->estaCerrada()) {
            return back()->with('error', 'Esta caja ya está cerrada. Un día cerrado no se modifica.');
        }

        $request->validate([
            'conteo'        => 'required|array',
            'observaciones' => 'nullable|string|max:500',
        ], ['conteo.required' => 'Ingrese el conteo físico de efectivo.']);

        $conteo   = CajaService::limpiarConteo((array) $request->conteo);
        $fisico   = CajaService::totalConteo($conteo)['total'];
        $resumen  = $this->caja->resumen($cierre);
        $esperado = $resumen['esperado'];
        $dif      = round($fisico - $esperado, 2);

        if (abs($dif) > 0.01 && strlen(trim((string) $request->observaciones)) < 5) {
            return back()->withErrors(['observaciones' => 'Hay una diferencia de $' . number_format(abs($dif), 2) . ': escriba la observación para poder cerrar.']);
        }

        $caja = $cierre->bancoCaja;
        $empresaId = (int) $cierre->empresa_id;

        try {
            if (abs($dif) > 0.01) {
                if (!$caja?->cuenta_id) {
                    throw new \DomainException('La caja no tiene cuenta contable: no se puede registrar el ' . ($dif > 0 ? 'sobrante' : 'faltante') . '.');
                }
                $this->asientoService->validarConfiguracion($empresaId, ['cta_ajuste_conciliacion'], $cierre->fecha->toDateString());
            }

            DB::transaction(function () use ($cierre, $request, $conteo, $fisico, $resumen, $esperado, $dif, $caja, $empresaId) {
                $cierre->update([
                    'usuario_cierre_id'   => Auth::id(),
                    'conteo_cierre'       => $conteo,
                    'total_efectivo'      => $fisico,          // conteo físico
                    'total_facturado'     => $resumen['facturado'],
                    'total_cobrado'       => $resumen['cobrado'],
                    'facturas_efectivo'   => $resumen['facturas'],
                    'saldo_esperado'      => $esperado,
                    'total_tarjeta'       => 0,                // la caja solo cuenta efectivo
                    'total_cheque'        => 0,
                    'total_transferencia' => 0,
                    'diferencia'          => $dif,
                    'observaciones'       => $request->observaciones,
                    'estado'              => 'cerrado',
                    'hora_cierre'         => now(),
                ]);

                $this->asientoService->cierreCaja(
                    empresaId:      $empresaId,
                    cierreId:       $cierre->id,
                    codigo:         "CAJA-{$cierre->id}",
                    fecha:          $cierre->fecha->format('Y-m-d'),
                    montoDeclarado: $fisico,
                    montoEsperado:  $esperado,
                    cuentaCajaId:   (int) $caja->cuenta_id,
                    centroCostoId:  $cierre->centro_costo_id ?? $caja->centro_costo_id,
                );

                // El saldo de la caja pasa a ser lo contado: el sobrante o faltante ya quedó asentado.
                if (abs($dif) > 0.01) {
                    $caja->actualizarSaldo(abs($dif), $dif > 0 ? 'ingreso' : 'egreso');
                }
            });
        } catch (\Throwable $e) {
            Log::warning('Cierre de caja no guardado: ' . $e->getMessage());
            return back()->with('error', 'No se pudo cerrar la caja: ' . $e->getMessage());
        }

        $msg = 'Caja cerrada correctamente.';
        if (abs($dif) > 0.01) {
            $msg .= ' ' . ($dif > 0 ? 'Sobrante' : 'Faltante') . ' de $' . number_format(abs($dif), 2) . ' registrado.';
        }

        return back()->with(abs($dif) > 0.01 ? 'warning' : 'success', $msg);
    }
}
