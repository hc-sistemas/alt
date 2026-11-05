<?php

namespace App\Support;

/** Importe en letras para documentos (rol de pagos): 436.45 → «CUATROCIENTOS TREINTA Y SEIS CON 45/100 DÓLARES». */
class NumeroALetras
{
    private const UNIDADES = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ', 'ONCE', 'DOCE', 'TRECE',
        'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE', 'VEINTIUNO', 'VEINTIDÓS', 'VEINTITRÉS',
        'VEINTICUATRO', 'VEINTICINCO', 'VEINTISÉIS', 'VEINTISIETE', 'VEINTIOCHO', 'VEINTINUEVE'];
    private const DECENAS = [3 => 'TREINTA', 4 => 'CUARENTA', 5 => 'CINCUENTA', 6 => 'SESENTA', 7 => 'SETENTA', 8 => 'OCHENTA', 9 => 'NOVENTA'];
    private const CENTENAS = [1 => 'CIENTO', 2 => 'DOSCIENTOS', 3 => 'TRESCIENTOS', 4 => 'CUATROCIENTOS', 5 => 'QUINIENTOS',
        6 => 'SEISCIENTOS', 7 => 'SETECIENTOS', 8 => 'OCHOCIENTOS', 9 => 'NOVECIENTOS'];

    public static function dolares(float $monto): string
    {
        $monto   = round(abs($monto), 2);
        $enteros = (int) floor($monto);
        $centavos = (int) round(($monto - $enteros) * 100);

        return self::entero($enteros) . ' CON ' . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . '/100 DÓLARES';
    }

    private static function entero(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }
        if ($n < 1000) {
            return self::centenas($n);
        }
        if ($n < 1000000) {
            $miles = intdiv($n, 1000);
            $resto = $n % 1000;
            $texto = $miles === 1 ? 'MIL' : self::centenas($miles) . ' MIL';

            return $texto . ($resto ? ' ' . self::centenas($resto) : '');
        }
        $millones = intdiv($n, 1000000);
        $resto = $n % 1000000;

        return ($millones === 1 ? 'UN MILLÓN' : self::entero($millones) . ' MILLONES') . ($resto ? ' ' . self::entero($resto) : '');
    }

    private static function centenas(int $n): string
    {
        if ($n === 100) {
            return 'CIEN';
        }
        $c = intdiv($n, 100);
        $r = $n % 100;
        $texto = $c ? self::CENTENAS[$c] : '';
        if ($r === 0) {
            return $texto;
        }
        $resto = $r < 30 ? self::UNIDADES[$r] : self::DECENAS[intdiv($r, 10)] . ($r % 10 ? ' Y ' . self::UNIDADES[$r % 10] : '');

        return trim($texto . ' ' . $resto);
    }
}
