<?php

namespace App\Services;

use App\Models\Empresa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SecuencialService
{
    /**
     * Genera y reserva el siguiente número de documento electrónico.
     * Usa lock pesimista para evitar duplicados bajo concurrencia.
     *
     * @param  int    $empresaId
     * @param  string $tipo  FAC | NC | ND | RET | GR | PRF | PRE
     * @return string  Formato '001-001-000000001'
     */
    public function siguiente(int $empresaId, string $tipo): string
    {
        return DB::transaction(function () use ($empresaId, $tipo) {
            $empresa = Empresa::lockForUpdate()->findOrFail($empresaId);
            $est     = $empresa->cod_establecimiento ?? '001';
            $pe      = $empresa->cod_punto_emision   ?? '001';

            $sec = DB::table('secuenciales')
                ->where('empresa_id',   $empresaId)
                ->where('tipo_documento', $tipo)
                ->where('establecimiento', $est)
                ->where('punto_emision',   $pe)
                ->lockForUpdate()
                ->first();

            if (!$sec) {
                $inicial = $this->determinarInicial($empresaId, $tipo, $est, $pe);

                DB::table('secuenciales')->insert([
                    'empresa_id'                   => $empresaId,
                    'tipo_documento'               => $tipo,
                    'establecimiento'              => $est,
                    'punto_emision'                => $pe,
                    'siguiente'                    => $inicial + 1,
                    'inicializado_desde_migracion' => true,
                ]);

                return sprintf('%s-%s-%09d', $est, $pe, $inicial);
            }

            // Registro existe y ya fue inicializado — camino normal
            if ($sec->inicializado_desde_migracion ?? false) {
                $numero = (int) ($sec->siguiente ?? 1);

                DB::table('secuenciales')
                    ->where('id', $sec->id)
                    ->update(['siguiente' => $numero + 1]);

                return sprintf('%s-%s-%09d', $est, $pe, $numero);
            }

            // Registro existe pero aún no fue reconciliado con datos legacy
            $inicial = $this->determinarInicial($empresaId, $tipo, $est, $pe);
            $stored  = (int) ($sec->siguiente ?? 1);
            $numero  = max($stored, $inicial);

            DB::table('secuenciales')
                ->where('id', $sec->id)
                ->update([
                    'siguiente'                    => $numero + 1,
                    'inicializado_desde_migracion' => true,
                ]);

            return sprintf('%s-%s-%09d', $est, $pe, $numero);
        });
    }

    /**
     * Genera y RESERVA (incrementa) el siguiente número de control INTERNO
     * — no fiscal — para un tipo de documento propio de Altamira (ej.
     * 'COMPRA' para Facturas de Compra). Reutiliza la misma tabla
     * `secuenciales` y el mismo bloqueo pesimista que siguiente(), pero con
     * un formato simple ("FC-00001") en vez del formato SRI
     * (establecimiento-puntoEmision-000000001): esto NO es un documento
     * electrónico autorizado por el SRI, es solo un correlativo interno de
     * seguimiento, así que no necesita establecimiento/punto de emisión.
     */
    public function siguienteInterno(int $empresaId, string $tipo, string $prefijo, int $digitos = 5): string
    {
        return DB::transaction(function () use ($empresaId, $tipo, $prefijo, $digitos) {
            $sec = DB::table('secuenciales')
                ->where('empresa_id',     $empresaId)
                ->where('tipo_documento', $tipo)
                ->where('establecimiento', '001')
                ->where('punto_emision',   '001')
                ->lockForUpdate()
                ->first();

            if (!$sec) {
                DB::table('secuenciales')->insert([
                    'empresa_id'      => $empresaId,
                    'tipo_documento'  => $tipo,
                    'establecimiento' => '001',
                    'punto_emision'   => '001',
                    'siguiente'       => 2,
                ]);
                $numero = 1;
            } else {
                $numero = (int) ($sec->siguiente ?? 1);
                DB::table('secuenciales')->where('id', $sec->id)->update(['siguiente' => $numero + 1]);
            }

            return sprintf("%s-%0{$digitos}d", $prefijo, $numero);
        });
    }

    /**
     * Solo CONSULTA (sin reservar ni incrementar) cuál sería el próximo
     * número interno — para mostrarlo como vista previa de solo lectura en
     * el formulario, antes de que el usuario guarde el documento real.
     */
    public function proximoInterno(int $empresaId, string $tipo, string $prefijo, int $digitos = 5): string
    {
        $siguiente = DB::table('secuenciales')
            ->where('empresa_id',     $empresaId)
            ->where('tipo_documento', $tipo)
            ->where('establecimiento', '001')
            ->where('punto_emision',   '001')
            ->value('siguiente') ?? 1;

        return sprintf("%s-%0{$digitos}d", $prefijo, (int) $siguiente);
    }

    /**
     * Calcula el número inicial mirando el MAX de la tabla correspondiente.
     * Evita duplicados con documentos migrados desde el sistema legacy.
     */
    private function determinarInicial(int $empresaId, string $tipo, string $est, string $pe): int
    {
        $tabla = $this->tablaParaTipo($tipo);

        if ($tabla && Schema::hasTable($tabla) && Schema::hasColumn($tabla, 'secuencial')) {
            $max = DB::table($tabla)
                ->where('empresa_id',    $empresaId)
                ->where('establecimiento', $est)
                ->where('punto_emision',   $pe)
                ->pluck('secuencial')
                ->map(fn ($v) => (int) $v)
                ->max();

            if ($max) {
                return $max + 1;
            }
        }

        return 1;
    }

    private function tablaParaTipo(string $tipo): ?string
    {
        return match ($tipo) {
            'FAC' => 'facturas',
            'NC'  => 'notas_credito',
            'RET' => 'retenciones',
            'GR'  => 'guias_remision',
            default => null,  // ND, PRF, PRE no tienen secuencial propio en schema
        };
    }
}
