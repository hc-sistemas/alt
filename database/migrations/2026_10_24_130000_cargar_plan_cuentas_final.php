<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga del plan de cuentas FINAL (docs/Oct2026/Plan de cuentas Final
 * actualizado.xlsx): una sola lista para Altamira UIO y Altamira Import.
 * Los datos viven en database/data/plan_cuentas_final.php.
 *
 * Los parámetros contables apuntan a la cuenta por id, no por código. Como 15
 * códigos cambian de significado (p. ej. 4.01.06 pasa de «Venta de Mercaderías»
 * a «Venta de Repuestos e Insumos de Taller»), primero se reapuntan los
 * parámetros a la cuenta correcta por significado y recién después se renombran
 * las filas. Si alguna cuenta que cambia de significado o deja de aceptar
 * asientos ya tiene movimientos, la migración se detiene con un error antes de
 * tocar nada (no se reinterpretan asientos históricos en silencio).
 *
 * Idempotente. Debe correr después de parametros_contables_compartidos.
 */
return new class extends Migration
{
    /** codigo de la cuenta que pasa a ser grupo => codigo de su única hija de detalle (se mueven sus asientos). */
    private array $remapeos = [];

    /**
     * Parámetro => [cuenta actual esperada, cuenta nueva]. Solo se reapunta si
     * el parámetro apunta hoy a la cuenta esperada.
     */
    private const REAPUNTES = [
        // Obligatorios por el cambio de plan
        'cta_inventario_transito'        => ['1.01.04.04', '1.01.04.07'],
        'cta_ajuste_inventario'          => ['5.01.05',    '5.01.09'],
        'cta_ingresos_servicios'         => ['4.01.05',    '4.01.08'],
        'cta_costo_servicios'            => ['5.01.04.01', '5.01.07.01'],
        'cta_bancos_exterior'            => ['1.01.02.01', '1.01.02.03'],
        'cta_decimo_tercero_pagar'       => ['2.01.03.03', '2.01.03.03.01'],
        'cta_decimo_cuarto_pagar'        => ['2.01.03.03', '2.01.03.03.02'],
        'cta_vacaciones_pagar'           => ['2.01.03.03', '2.01.03.03.03'],
        'cta_participacion_trabajadores' => ['2.01.03.03', '2.01.03.05'],
        // Cuentas genéricas: respaldo en «sin clasificar» hasta que Ventas e
        // Inventario asignen la cuenta por categoría (DEC-030).
        'cta_inventario_mercaderia'      => ['1.01.04.05', '1.01.04.09'],
        'cta_costo_ventas'               => ['5.01.05',    '5.01.08'],
        'cta_costo_ventas_importadas'    => ['5.01.05',    '5.01.08'],
        'cta_ventas_locales'             => ['4.01.06',    '4.01.09'],
        'cta_ventas_exterior'            => ['4.01.06',    '4.01.09'],
        // Provisional hasta definir los bancos (DEC-038)
        'cta_bancos_locales'             => ['1.01.02.01', '1.01.02.01.01'],
        // Correcciones que el documento 01 ya exigía
        'cta_anticipos_clientes'         => ['2.01.01.01', '2.01.04'],
        'cta_anticipos_proveedores'      => ['1.01.03.01', '1.01.06.01'],
        'cta_anticipos_empleados'        => ['1.01.03.01', '1.01.06.02'],
        'cta_clientes_exterior'          => ['1.01.03.01', '1.01.03.03'],
        'cta_vouchers'                   => ['1.01.03.01', '1.01.03.04'],
        'cta_ajuste_conciliacion'        => ['6.02.01',    '6.01.14'],
        'cta_impuesto_renta_pagar'       => ['2.01.02.02', '2.01.07'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('plan_cuentas')) {
            return;
        }

        $plan = require database_path('data/plan_cuentas_final.php');

        DB::transaction(function () use ($plan) {
            $porCodigo = DB::table('plan_cuentas')->pluck('id', 'codigo')->all();
            $existentes = DB::table('plan_cuentas')->get()->keyBy('codigo');
            $finales = array_column($plan, null, 0);

            $this->verificarSinMovimientos($existentes, $finales);

            // 0. Cuentas que pasan a ser grupo y solo tienen asientos: sus líneas se mueven a la hija de detalle
            //    (ya creadas las cuentas nuevas, en el paso 1b).

            // 1. Cuentas nuevas (padres antes que hijas)
            $nuevas = array_filter($plan, fn ($c) => !isset($porCodigo[$c[0]]));
            usort($nuevas, fn ($a, $b) => [substr_count($a[0], '.'), $a[0]] <=> [substr_count($b[0], '.'), $b[0]]);
            foreach ($nuevas as [$codigo, $nombre, $tipo, $permite]) {
                $porCodigo[$codigo] = DB::table('plan_cuentas')->insertGetId([
                    'empresa_id'       => null,
                    'codigo'           => $codigo,
                    'nombre'           => $nombre,
                    'tipo'             => $tipo,
                    'nivel'            => substr_count($codigo, '.') + 1,
                    'permite_asientos' => $permite,
                    'estado'           => true,
                    'total_asientos'   => 0,
                ]);
            }

            // 1b. Mover los asientos de las cuentas que dejan de aceptar movimientos a su única hija de detalle
            //     (p. ej. 1.01.02.01 «Banco Local - Cuenta Corriente» pasa a ser grupo y su línea pasa a
            //     1.01.02.01.01 «Banco Pichincha»). Solo si el nombre no cambia y no hay otro tipo de movimiento.
            foreach ($this->remapeos as $codigoViejo => $codigoHija) {
                if (isset($existentes[$codigoViejo], $porCodigo[$codigoHija])) {
                    $movidas = DB::table('asiento_detalles')->where('cuenta_id', $existentes[$codigoViejo]->id)
                        ->update(['cuenta_id' => $porCodigo[$codigoHija]]);
                    \Log::info("Plan final: {$movidas} línea(s) de asiento pasan de {$codigoViejo} a {$codigoHija}.");
                }
            }

            // 2. Reapuntar parámetros por significado, antes de renombrar nada
            $this->reapuntarParametros($existentes, $porCodigo);

            // 3. Actualizar nombre, tipo, nivel, padre y Permite_Asientos del resto
            foreach ($plan as [$codigo, $nombre, $tipo, $permite]) {
                $partes = explode('.', $codigo);
                $padreCodigo = count($partes) > 1 ? implode('.', array_slice($partes, 0, -1)) : null;
                DB::table('plan_cuentas')->where('id', $porCodigo[$codigo])->update([
                    'nombre'           => $nombre,
                    'tipo'             => $tipo,
                    'nivel'            => count($partes),
                    'padre_id'         => $padreCodigo ? ($porCodigo[$padreCodigo] ?? null) : null,
                    'permite_asientos' => $permite,
                    'estado'           => true,
                ]);
            }

            // 4. Cuentas que ya no están en el plan final
            foreach ($existentes as $codigo => $fila) {
                if (isset($finales[$codigo]) || str_starts_with($codigo, 'OBS-')) {
                    continue;
                }
                if ($this->tieneReferencias((int) $fila->id)) {
                    DB::table('plan_cuentas')->where('id', $fila->id)->update([
                        'codigo' => 'OBS-' . $codigo, 'estado' => false, 'permite_asientos' => false,
                    ]);
                } else {
                    DB::table('plan_cuentas')->where('id', $fila->id)->delete();
                }
            }
        });
    }

    /** Código de la única cuenta de detalle directamente bajo $codigo en el plan final, o null. */
    private function unicaHijaDeDetalle(string $codigo, array $finales): ?string
    {
        $hijas = [];
        foreach ($finales as $c => [, , , $permite]) {
            $partes = explode('.', $c);
            if ($permite && count($partes) > 1 && implode('.', array_slice($partes, 0, -1)) === $codigo) {
                $hijas[] = $c;
            }
        }

        return count($hijas) === 1 ? $hijas[0] : null;
    }

    /** ¿Los únicos movimientos de la cuenta son líneas de asiento (nada en compras, bancos ni movimientos)? */
    private function soloTieneAsientos(int $cuentaId): bool
    {
        foreach ([['compra_detalles', 'cuenta_id'], ['bancos_cajas', 'cuenta_id'], ['movimientos_bancarios', 'cuenta_contrapartida_id']] as [$t, $col]) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, $col) && DB::table($t)->where($col, $cuentaId)->exists()) {
                return false;
            }
        }

        return true;
    }

    /** Falla con un mensaje claro si cambiar una cuenta alteraría movimientos ya registrados. */
    private function verificarSinMovimientos($existentes, array $finales): void
    {
        $norm = fn (string $s) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($s)));
        $afectadas = [];
        foreach ($finales as $codigo => [, $nombre, , $permite]) {
            $fila = $existentes[$codigo] ?? null;
            if (!$fila) {
                continue;
            }
            $cambiaNombre = $norm($fila->nombre) !== $norm($nombre);
            $dejaDePermitir = (bool) $fila->permite_asientos && !$permite;
            if (($cambiaNombre || $dejaDePermitir) && $this->tieneMovimientos((int) $fila->id)) {
                // Caso resoluble sin ambigüedad: la cuenta solo cambia a «grupo», solo tiene asientos y el plan final
                // le da exactamente una hija de detalle -> los asientos pasan a esa hija.
                $hija = $this->unicaHijaDeDetalle($codigo, $finales);
                if (!$cambiaNombre && $dejaDePermitir && $hija && $this->soloTieneAsientos((int) $fila->id)) {
                    $this->remapeos[$codigo] = $hija;
                    continue;
                }
                $afectadas[] = "{$codigo} ({$fila->nombre})";
            }
        }
        if ($afectadas) {
            throw new RuntimeException(
                'No se carga el plan final: estas cuentas ya tienen asientos, compras o bancos y cambiarían de '
                . 'significado: ' . implode('; ', $afectadas) . '. Hay que migrar esos movimientos antes.'
            );
        }
    }

    private function reapuntarParametros($existentes, array $porCodigo): void
    {
        foreach (self::REAPUNTES as $parametro => [$codigoActual, $codigoNuevo]) {
            $idActual = $existentes[$codigoActual]->id ?? null;
            $idNuevo  = $porCodigo[$codigoNuevo] ?? null;
            if (!$idActual || !$idNuevo) {
                continue;
            }
            DB::table('parametros_contables')
                ->where('codigo', $parametro)
                ->where('cuenta_id', $idActual)
                ->update(['cuenta_id' => $idNuevo]);
        }
    }

    /** Referencias de movimientos reales (no parámetros). */
    private function tieneMovimientos(int $cuentaId): bool
    {
        $refs = [
            ['asiento_detalles', 'cuenta_id'],
            ['compra_detalles', 'cuenta_id'],
            ['bancos_cajas', 'cuenta_id'],
            ['movimientos_bancarios', 'cuenta_contrapartida_id'],
        ];
        foreach ($refs as [$tabla, $col]) {
            if (Schema::hasTable($tabla) && Schema::hasColumn($tabla, $col)
                && DB::table($tabla)->where($col, $cuentaId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function tieneReferencias(int $cuentaId): bool
    {
        return $this->tieneMovimientos($cuentaId)
            || (Schema::hasTable('parametros_contables')
                && DB::table('parametros_contables')->where('cuenta_id', $cuentaId)->exists())
            || DB::table('plan_cuentas')->where('padre_id', $cuentaId)->exists();
    }

    public function down(): void
    {
        // Sin reversión: el plan anterior queda solo en el respaldo.
    }
};
