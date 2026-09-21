<?php

namespace App\Support;

/**
 * Reglas de negocio ligadas a la forma de pago.
 *
 * Regla vigente: si se paga con tarjeta de crédito no hay descuento de ningún
 * tipo (ni por línea ni descuento especial) en factura, prefactura o proforma.
 * "datafast" es el cobro con tarjeta por POS, así que se trata igual.
 */
class ReglasPago
{
    public const TARJETA = ['tarjeta', 'datafast'];

    public const MENSAJE_SIN_DESCUENTO = 'Las ventas con tarjeta de crédito no admiten descuentos de ningún tipo.';

    public static function esTarjeta(?string $forma): bool
    {
        return in_array($forma, self::TARJETA, true);
    }

    /** @param iterable<string|null> $formas */
    public static function algunaTarjeta(iterable $formas): bool
    {
        foreach ($formas as $forma) {
            if (self::esTarjeta($forma)) {
                return true;
            }
        }

        return false;
    }
}
