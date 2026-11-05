<?php

namespace App\Support;

use App\Models\BancoCaja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Saldo negativo en Bancos (02-06 §2.8, DEC-041 pendiente): no se permite dejar un banco o caja en negativo sin que el
 * usuario lo confirme. La primera petición se detiene con una advertencia (flash `saldo_negativo`); la pantalla pregunta y
 * reenvía la misma petición con `confirmar_saldo_negativo=1`, que queda registrada en `log_cambios_criticos`.
 */
class SaldoBancario
{
    /** Devuelve una redirección con la advertencia si el egreso deja el saldo negativo y no fue confirmado; null si puede seguir. */
    public static function advertir(BancoCaja $banco, float $monto, Request $request): ?RedirectResponse
    {
        $saldo = (float) $banco->saldo_actual;
        if ($saldo + 0.001 >= $monto) {
            return null;
        }

        $resultante = round($saldo - $monto, 2);

        // La caja (efectivo físico) no admite saldo final negativo: se bloquea y se avisa, sin opción de confirmar
        // (02-05 §2.3, DEC-041 pendiente). Solo los bancos permiten continuar con confirmación.
        if (in_array($banco->tipo, ['caja', 'caja_chica'], true)) {
            return back()->withInput()->with('error',
                "{$banco->nombre} solo tiene \$" . number_format($saldo, 2) . ' en efectivo: no se puede registrar un egreso de \$'
                . number_format($monto, 2) . ' porque la caja quedaría en negativo.');
        }

        if (!$request->boolean('confirmar_saldo_negativo')) {
            $mensaje = "{$banco->nombre} tiene \$" . number_format($saldo, 2) . " y este egreso es de \$" . number_format($monto, 2)
                . ": el saldo quedaría en -\$" . number_format(abs($resultante), 2) . '.';

            return back()->withInput()->with('error', $mensaje)->with('saldo_negativo', $mensaje);
        }

        try {
            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'empresa_id'     => $banco->empresa_id,
                'tabla'          => 'bancos_cajas',
                'registro_id'    => $banco->id,
                'campo'          => 'saldo_negativo_confirmado',
                'valor_anterior' => number_format($saldo, 2, '.', ''),
                'valor_nuevo'    => number_format($resultante, 2, '.', ''),
                'ip_address'     => $request->ip(),
            ]);
        } catch (\Throwable) {
            // La confirmación del usuario no debe fallar por el registro de auditoría
        }

        return null;
    }
}
