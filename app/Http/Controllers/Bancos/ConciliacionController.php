<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\ConciliacionBancaria;
use App\Models\MovimientoBancario;
use App\Models\PartidaTransito;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConciliacionController extends Controller
{
    public function __construct(private readonly AsientoService $asientoService) {}

    public function index(): Response
    {
        $empresaId      = session('empresa_activa_id');
        $conciliaciones = ConciliacionBancaria::where('empresa_id', $empresaId)
            ->with('bancoCaja')
            ->orderByDesc('fecha_corte')
            ->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'banco'         => $c->bancoCaja?->nombre,
                'fecha_corte'   => $c->fecha_corte?->format('d/m/Y'),
                'saldo_banco'   => $c->saldo_banco,
                'saldo_sistema' => $c->saldo_sistema,
                'diferencia'    => $c->diferencia,
                'estado'        => $c->estado,
                'tiene_dif'     => $c->tieneDiferencia(),
                'created_at'    => $c->created_at?->format('d/m/Y'),
            ]);

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->bancos()->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'saldo_actual']);

        return Inertia::render('Bancos/Conciliaciones/Index', [
            'conciliaciones' => $conciliaciones,
            'bancos'         => $bancos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'banco_caja_id' => 'required|exists:bancos_cajas,id',
            'fecha_corte'   => 'required|date',
            'saldo_banco'   => 'required|numeric',
            'descripcion'   => 'nullable|string|max:300',
            'archivo'       => 'nullable|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $banco        = BancoCaja::findOrFail($request->banco_caja_id);
        $saldoSistema = $banco->saldo_actual;
        $diferencia   = $request->saldo_banco - $saldoSistema;
        $archivoPath  = null;

        if ($request->hasFile('archivo')) {
            $archivoPath = $request->file('archivo')->store('conciliaciones', 'local');
        }

        $conciliacion = ConciliacionBancaria::create([
            'empresa_id'    => $empresaId,
            'banco_caja_id' => $request->banco_caja_id,
            'fecha_corte'   => $request->fecha_corte,
            'saldo_banco'   => $request->saldo_banco,
            'saldo_sistema' => $saldoSistema,
            'diferencia'    => $diferencia,
            'descripcion'   => $request->descripcion,
            'archivo_csv'   => $archivoPath,
            'estado'        => 'pendiente',
            'created_by'    => Auth::id(),
            'created_at'    => now(),
        ]);

        $movimientosNoConciliados = MovimientoBancario::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $request->banco_caja_id)
            ->where('fecha', '<=', $request->fecha_corte)
            ->where('conciliado', false)
            ->where('anulado', false)
            ->get();

        foreach ($movimientosNoConciliados as $mov) {
            PartidaTransito::create([
                'conciliacion_id' => $conciliacion->id,
                'tipo'            => 'sistema',
                'fecha'           => $mov->fecha,
                'descripcion'     => $mov->descripcion,
                'monto'           => $mov->monto,
                'movimiento_id'   => $mov->id,
                'conciliada'      => false,
            ]);
        }

        $msg = "Conciliación creada. Saldo banco: \${$request->saldo_banco} · Saldo sistema: \${$saldoSistema}";
        if (abs($diferencia) > 0.01) {
            $msg .= ' · Diferencia: $' . number_format(abs($diferencia), 2);
        }

        return redirect()->route('bancos.conciliaciones.show', $conciliacion)
            ->with(abs($diferencia) > 0.01 ? 'warning' : 'success', $msg);
    }

    public function show(ConciliacionBancaria $conciliacion): Response
    {
        $conciliacion->load(['bancoCaja', 'partidas.movimiento']);

        $mapPartida = fn($p) => [
            'id'          => $p->id,
            'tipo'        => $p->tipo,
            'fecha'       => $p->fecha?->format('Y-m-d'),
            'descripcion' => $p->descripcion,
            'monto'       => $p->monto,
            'conciliada'  => $p->conciliada,
            'movimiento'  => $p->movimiento ? [
                'id'          => $p->movimiento->id,
                'descripcion' => $p->movimiento->descripcion,
                'monto'       => $p->movimiento->monto,
                'tipo'        => $p->movimiento->tipo,
                'sub_tipo'    => $p->movimiento->sub_tipo,
            ] : null,
        ];

        $partidas          = $conciliacion->partidas;
        $partidasSistema   = $partidas->where('tipo', 'sistema')->values()->map($mapPartida);
        $partidasBanco     = $partidas->where('tipo', 'banco')->values()->map($mapPartida);
        $partidasConciliadas = $partidas->where('conciliada', true)->count();
        $partidasPendientes  = $partidas->where('conciliada', false)->count();

        return Inertia::render('Bancos/Conciliaciones/Show', [
            'conciliacion'        => [
                'id'            => $conciliacion->id,
                'estado'        => $conciliacion->estado,
                'fecha_corte'   => $conciliacion->fecha_corte?->format('Y-m-d'),
                'saldo_banco'   => $conciliacion->saldo_banco,
                'saldo_sistema' => $conciliacion->saldo_sistema,
                'diferencia'    => $conciliacion->diferencia,
                'descripcion'   => $conciliacion->descripcion,
                'banco_caja'    => [
                    'nombre'      => $conciliacion->bancoCaja?->nombre,
                    'saldo_actual' => $conciliacion->bancoCaja?->saldo_actual,
                ],
            ],
            'partidas_sistema'    => $partidasSistema,
            'partidas_banco'      => $partidasBanco,
            'resumen'             => [
                'total_sistema'   => $partidasSistema->count(),
                'total_banco'     => $partidasBanco->count(),
                'conciliadas'     => $partidasConciliadas,
                'pendientes'      => $partidasPendientes,
            ],
        ]);
    }

    // ── Upload CSV / estado de cuenta bancario ─────────────────────────────────
    public function uploadEstadoCuenta(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }

        $contenido = file_get_contents($request->file('archivo')->getRealPath());
        // Quitar BOM UTF-8
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $lineas    = array_filter(explode("\n", str_replace("\r\n", "\n", $contenido)));
        $lineas    = array_values($lineas);

        if (count($lineas) < 2) {
            return back()->with('error', 'El archivo CSV está vacío o no tiene datos.');
        }

        // Detectar delimitador (coma o punto y coma)
        $cabecera   = $lineas[0];
        $delimitador = str_contains($cabecera, ';') ? ';' : ',';
        $columnas    = array_map('trim', str_getcsv($cabecera, $delimitador));

        // Mapear columnas por palabras clave
        $idxFecha  = $this->encontrarColumna($columnas, ['fecha', 'date', 'dia']);
        $idxDesc   = $this->encontrarColumna($columnas, ['descripcion', 'concepto', 'detalle', 'description']);
        $idxMonto  = $this->encontrarColumna($columnas, ['monto', 'valor', 'importe', 'amount', 'credito', 'debito']);

        if ($idxFecha === null || $idxMonto === null) {
            return back()->with('error', 'No se pudo detectar las columnas de fecha y monto en el CSV.');
        }

        $importadas = 0;
        $errores    = 0;

        DB::transaction(function () use ($lineas, $delimitador, $idxFecha, $idxDesc, $idxMonto, $conciliacion, &$importadas, &$errores) {
            foreach (array_slice($lineas, 1) as $linea) {
                if (empty(trim($linea))) continue;
                $cols = array_map('trim', str_getcsv($linea, $delimitador));

                $fechaRaw = $cols[$idxFecha] ?? null;
                $monto    = isset($cols[$idxMonto])
                    ? (float) str_replace([',', ' '], ['.', ''], $cols[$idxMonto])
                    : null;
                $desc     = $idxDesc !== null ? ($cols[$idxDesc] ?? 'Sin descripción') : 'Sin descripción';

                if (!$fechaRaw || !$monto) { $errores++; continue; }

                $fecha = null;
                foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $fmt) {
                    $dt = \DateTime::createFromFormat($fmt, $fechaRaw);
                    if ($dt) { $fecha = $dt->format('Y-m-d'); break; }
                }
                if (!$fecha) { $errores++; continue; }

                PartidaTransito::create([
                    'conciliacion_id' => $conciliacion->id,
                    'tipo'            => 'banco',
                    'fecha'           => $fecha,
                    'descripcion'     => substr($desc, 0, 300),
                    'monto'           => abs($monto),
                    'conciliada'      => false,
                ]);
                $importadas++;
            }
        });

        $msg = "CSV importado: {$importadas} movimientos del banco cargados.";
        if ($errores > 0) $msg .= " ({$errores} filas con errores omitidas)";

        return back()->with('success', $msg);
    }

    // ── Cruce manual de una partida sistema con una partida banco ─────────────
    public function conciliarPartida(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate([
            'partida_sistema_id' => 'required|exists:partidas_transito,id',
            'partida_banco_id'   => 'required|exists:partidas_transito,id',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }

        DB::transaction(function () use ($request, $conciliacion) {
            $pSistema = PartidaTransito::findOrFail($request->partida_sistema_id);
            $pBanco   = PartidaTransito::findOrFail($request->partida_banco_id);

            // Verificar que ambas pertenecen a esta conciliación
            if ($pSistema->conciliacion_id !== $conciliacion->id || $pBanco->conciliacion_id !== $conciliacion->id) {
                abort(422, 'Las partidas no pertenecen a esta conciliación.');
            }
            if ($pSistema->tipo !== 'sistema' || $pBanco->tipo !== 'banco') {
                abort(422, 'Las partidas deben ser una de sistema y otra de banco.');
            }

            $pSistema->update(['conciliada' => true]);
            $pBanco->update(['conciliada' => true]);

            // Marcar el movimiento como conciliado si la diferencia es ≤ $0.01
            if ($pSistema->movimiento_id && abs((float)$pSistema->monto - (float)$pBanco->monto) <= 0.01) {
                MovimientoBancario::where('id', $pSistema->movimiento_id)->update(['conciliado' => true]);
            }
        });

        return back()->with('success', 'Partidas cruzadas correctamente.');
    }

    // ── Generar asiento de ajuste para la diferencia ──────────────────────────
    public function generarAsientoAjuste(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:300',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }
        if (!$conciliacion->tieneDiferencia()) {
            return back()->with('error', 'No hay diferencia que ajustar (cuentas cuadradas).');
        }

        $empresaId = session('empresa_activa_id');
        $desc      = $request->descripcion ?: "Ajuste conciliación bancaria #{$conciliacion->id}";

        DB::transaction(function () use ($conciliacion, $empresaId, $desc) {
            $asiento = $this->asientoService->ajusteConciliacion(
                $empresaId,
                $conciliacion->id,
                (float) $conciliacion->diferencia,
                $desc,
            );

            // Crear partida banco para el ajuste
            PartidaTransito::create([
                'conciliacion_id'    => $conciliacion->id,
                'tipo'               => 'banco',
                'fecha'              => now()->format('Y-m-d'),
                'descripcion'        => $desc,
                'monto'              => abs((float) $conciliacion->diferencia),
                'conciliada'         => true,
                'asiento_generado_id' => $asiento->id,
            ]);
        });

        return back()->with('success', 'Asiento de ajuste generado correctamente.');
    }

    // ── Cerrar conciliación ───────────────────────────────────────────────────
    public function cerrar(ConciliacionBancaria $conciliacion): RedirectResponse
    {
        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }

        $pendientes = $conciliacion->partidas()->where('conciliada', false)->count();
        if ($pendientes > 0) {
            return back()->with('error', "No se puede cerrar: hay {$pendientes} partida(s) sin conciliar. Crúcelas o genere un asiento de ajuste primero.");
        }

        DB::transaction(function () use ($conciliacion) {
            $movIds = $conciliacion->partidas()
                ->where('tipo', 'sistema')
                ->whereNotNull('movimiento_id')
                ->pluck('movimiento_id');

            MovimientoBancario::whereIn('id', $movIds)->update(['conciliado' => true]);
            $conciliacion->update(['estado' => 'conciliada']);
        });

        return back()->with('success', 'Conciliación cerrada correctamente.');
    }

    // ── Eliminar conciliación (solo si está pendiente) ─────────────────────────
    public function destroy(ConciliacionBancaria $conciliacion): RedirectResponse
    {
        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'No se puede eliminar una conciliación ya cerrada.');
        }

        DB::transaction(function () use ($conciliacion) {
            $conciliacion->partidas()->delete();
            $conciliacion->delete();
        });

        return redirect()->route('bancos.conciliaciones.index')
            ->with('success', 'Conciliación eliminada.');
    }

    public function marcarConciliada(ConciliacionBancaria $conciliacion): RedirectResponse
    {
        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'Esta conciliación ya está marcada como conciliada.');
        }

        $movIds = $conciliacion->partidas()
            ->whereNotNull('movimiento_id')
            ->pluck('movimiento_id');

        MovimientoBancario::whereIn('id', $movIds)->update(['conciliado' => true]);
        $conciliacion->update(['estado' => 'conciliada']);

        return back()->with('success', 'Conciliación marcada como conciliada correctamente.');
    }

    // ── Helper: detectar columna por palabras clave ───────────────────────────
    private function encontrarColumna(array $columnas, array $palabras): ?int
    {
        foreach ($columnas as $i => $col) {
            $colNorm = strtolower(trim($col));
            foreach ($palabras as $palabra) {
                if (str_contains($colNorm, $palabra)) return $i;
            }
        }
        return null;
    }
}
