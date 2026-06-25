<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\ConciliacionBancaria;
use App\Models\MovimientoBancario;
use App\Models\PartidaTransito;
use App\Services\AsientoService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConciliacionController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

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

        return redirect()->route('bancos.conciliaciones.show', $conciliacion->id)->with(
            abs($diferencia) > 0.01 ? 'warning' : 'success',
            $msg
        );
    }

    public function show(ConciliacionBancaria $conciliacion): Response
    {
        $conciliacion->load(['bancoCaja', 'partidas.movimiento']);

        $partidas = $conciliacion->partidas->map(fn($p) => [
            'id'          => $p->id,
            'tipo'        => $p->tipo,
            'fecha'       => $p->fecha instanceof \Carbon\Carbon
                                ? $p->fecha->format('d/m/Y')
                                : $p->fecha,
            'descripcion' => $p->descripcion,
            'monto'       => (float) $p->monto,
            'conciliada'  => (bool) $p->conciliada,
            'movimiento'  => $p->movimiento ? [
                'descripcion' => $p->movimiento->descripcion,
                'monto'       => (float) $p->movimiento->monto,
                'tipo'        => $p->movimiento->tipo,
            ] : null,
        ]);

        return Inertia::render('Bancos/Conciliaciones/Show', [
            'conciliacion' => [
                'id'            => $conciliacion->id,
                'banco_caja'    => [
                    'nombre'       => $conciliacion->bancoCaja?->nombre,
                    'saldo_actual' => (float) $conciliacion->bancoCaja?->saldo_actual,
                ],
                'banco_caja_id' => $conciliacion->banco_caja_id,
                'fecha_corte'   => $conciliacion->fecha_corte instanceof \Carbon\Carbon
                                    ? $conciliacion->fecha_corte->format('d/m/Y')
                                    : $conciliacion->fecha_corte,
                'saldo_banco'   => (float) $conciliacion->saldo_banco,
                'saldo_sistema' => (float) $conciliacion->saldo_sistema,
                'diferencia'    => (float) $conciliacion->diferencia,
                'descripcion'   => $conciliacion->descripcion,
                'estado'        => $conciliacion->estado,
                'partidas'      => $partidas,
            ],
        ]);
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

    // ──────────────────────────────────────────────────────────────────────
    // UPLOAD CSV / XLSX — auto-matching
    // ──────────────────────────────────────────────────────────────────────
    public function uploadCsv(Request $request, ConciliacionBancaria $conciliacion): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $file = $request->file('archivo');
        $ext  = strtolower($file->getClientOriginalExtension());

        $rows = in_array($ext, ['xlsx', 'xls'])
            ? $this->parseExcel($file->getPathname())
            : $this->parseCsv($file->getPathname());

        // Partidas sistema aún no conciliadas — load them for matching
        $partidasSistema = PartidaTransito::where('conciliacion_id', $conciliacion->id)
            ->where('tipo', 'sistema')
            ->where('conciliada', false)
            ->get()
            ->keyBy('id');

        $autoMatch     = 0;
        $probableMatch = 0;
        $sinMatch      = 0;

        DB::transaction(function () use ($rows, $conciliacion, $partidasSistema, &$autoMatch, &$probableMatch, &$sinMatch) {
            foreach ($rows as $row) {
                $fechaBanco  = $row['fecha']       ?? null;
                $montoBanco  = abs((float) ($row['monto'] ?? 0));
                $descripcion = $row['descripcion'] ?? '';

                if (!$fechaBanco || $montoBanco < 0.001) {
                    continue;
                }

                $matched = null;
                $isExact = false;

                foreach ($partidasSistema as $partida) {
                    if ($partida->conciliada) {
                        continue;
                    }

                    if (abs((float) $partida->monto - $montoBanco) > 0.02) {
                        continue;
                    }

                    try {
                        $fechaSistema = Carbon::parse($partida->fecha);
                        $fechaBancoC  = Carbon::parse($fechaBanco);
                        $daysDiff     = (int) abs($fechaSistema->diffInDays($fechaBancoC));
                    } catch (\Throwable) {
                        continue;
                    }

                    if ($daysDiff === 0) {
                        $matched = $partida;
                        $isExact = true;
                        break;
                    }

                    if ($daysDiff === 1 && !$matched) {
                        $matched = $partida;
                    }
                }

                $conciliadaBanco = $matched !== null;

                $partidaBanco = PartidaTransito::create([
                    'conciliacion_id' => $conciliacion->id,
                    'tipo'            => 'banco',
                    'fecha'           => $fechaBanco,
                    'descripcion'     => $descripcion,
                    'monto'           => $montoBanco,
                    'conciliada'      => $conciliadaBanco,
                ]);

                if ($matched) {
                    $matched->update(['conciliada' => true]);
                    $partidasSistema->forget($matched->id);

                    if ($matched->movimiento_id) {
                        MovimientoBancario::where('id', $matched->movimiento_id)
                            ->update(['conciliado' => true]);
                    }

                    $isExact ? $autoMatch++ : $probableMatch++;
                } else {
                    $sinMatch++;
                }
            }
        });

        return response()->json([
            'match_auto'     => $autoMatch,
            'match_probable' => $probableMatch,
            'sin_match'      => $sinMatch,
            'total'          => $autoMatch + $probableMatch + $sinMatch,
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // CRUCE MANUAL — une una partida sistema con una partida banco
    // ──────────────────────────────────────────────────────────────────────
    public function conciliarPartida(Request $request, ConciliacionBancaria $conciliacion): JsonResponse
    {
        $request->validate([
            'partida_sistema_id' => 'required|integer|exists:partidas_transito,id',
            'partida_banco_id'   => 'required|integer|exists:partidas_transito,id',
        ]);

        DB::transaction(function () use ($request) {
            $sistema = PartidaTransito::findOrFail($request->partida_sistema_id);
            $banco   = PartidaTransito::findOrFail($request->partida_banco_id);

            $sistema->update(['conciliada' => true]);
            $banco->update(['conciliada'   => true]);

            if ($sistema->movimiento_id) {
                MovimientoBancario::where('id', $sistema->movimiento_id)
                    ->update(['conciliado' => true]);
            }
        });

        return response()->json(['ok' => true]);
    }

    // ──────────────────────────────────────────────────────────────────────
    // GENERAR ASIENTO DE AJUSTE para diferencia de conciliación
    // ──────────────────────────────────────────────────────────────────────
    public function generarAsientoAjuste(Request $request, ConciliacionBancaria $conciliacion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($conciliacion->estaConciliada()) {
            return response()->json(['error' => 'La conciliación ya está cerrada.'], 422);
        }

        $diferencia = (float) $conciliacion->diferencia;
        if (abs($diferencia) <= 0.01) {
            return response()->json(['error' => 'No hay diferencia que ajustar.'], 422);
        }

        try {
            $asiento = $this->asientoService->ajusteConciliacion(
                empresaId:      $empresaId,
                conciliacionId: $conciliacion->id,
                diferencia:     $diferencia,
                bancoCajaId:    $conciliacion->banco_caja_id,
                fecha:          $request->string('fecha', now()->toDateString()),
            );

            return response()->json(['ok' => true, 'asiento_id' => $asiento->id]);

        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────
    private function parseCsv(string $path): array
    {
        $contenido = file_get_contents($path);
        // Remove UTF-8 BOM
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        // Normalize line endings
        $contenido = str_replace("\r\n", "\n", str_replace("\r", "\n", $contenido));

        $lines = array_filter(explode("\n", $contenido), fn($l) => trim($l) !== '');
        $lines = array_values($lines);

        if (!$lines) {
            return [];
        }

        $firstLine = $lines[0];
        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $headers = null;
        $rows    = [];

        foreach ($lines as $line) {
            $cols = str_getcsv($line, $sep);
            $cols = array_map('trim', $cols);

            if (!$headers) {
                $firstCell = strtolower(preg_replace('/[^a-zA-Z]/', '', $cols[0] ?? ''));
                if ($firstCell && !is_numeric(str_replace(['.', ',', '$', ' '], '', $cols[0]))) {
                    $headers = array_map(fn($h) => strtolower(trim($h)), $cols);
                } else {
                    $headers = array_keys($cols);
                }
                if (array_filter($headers, fn($h) => str_contains((string)$h, 'fecha') || str_contains((string)$h, 'date'))) {
                    continue; // header row, skip
                }
            }

            $row    = array_combine(
                $headers,
                array_pad($cols, count($headers), '')
            );
            $parsed = $this->extractRowFields($row);

            if ($parsed) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    private function parseExcel(string $path): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet       = $spreadsheet->getActiveSheet();
        $rows        = [];
        $headers     = null;

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $cells[] = trim((string) $cell->getFormattedValue());
            }

            if (!$headers) {
                $firstCell = strtolower(preg_replace('/[^a-zA-Z]/', '', $cells[0] ?? ''));
                if ($firstCell && !is_numeric(str_replace(['.', ',', '$'], '', $cells[0] ?? ''))) {
                    $headers = array_map(fn($h) => strtolower(trim($h)), $cells);
                } else {
                    $headers = array_keys($cells);
                }
                if (array_filter($headers, fn($h) => str_contains((string)$h, 'fecha') || str_contains((string)$h, 'date'))) {
                    continue;
                }
            }

            $row    = array_combine(
                $headers,
                array_pad($cells, count($headers), '')
            );
            $parsed = $this->extractRowFields($row);

            if ($parsed) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    private function extractRowFields(array $row): ?array
    {
        $fechaVal = $this->findCol($row, ['fecha', 'date', 'f.transaccion', 'fecha_transaccion', 'fec']);
        $montoVal = $this->findCol($row, ['monto', 'valor', 'amount', 'credito', 'debito', 'importe', 'debito/credito']);
        $descVal  = $this->findCol($row, ['descripcion', 'concepto', 'detalle', 'description', 'referencia', 'ref']);

        if (!$fechaVal || !$montoVal) {
            return null;
        }

        $montoNum = (float) preg_replace('/[^0-9.\-]/', '', str_replace(',', '.', $montoVal));

        try {
            $fechaParsed = Carbon::parse($fechaVal)->toDateString();
        } catch (\Throwable) {
            return null;
        }

        return [
            'fecha'       => $fechaParsed,
            'monto'       => abs($montoNum),
            'descripcion' => $descVal ?? $montoVal,
        ];
    }

    private function findCol(array $row, array $names): ?string
    {
        foreach ($names as $name) {
            foreach ($row as $key => $value) {
                if (str_contains(strtolower((string)$key), $name) && trim((string)$value) !== '') {
                    return $value;
                }
            }
        }
        return null;
    }
}
