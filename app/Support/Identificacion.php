<?php

namespace App\Support;

class Identificacion
{
    /**
     * Valida una identificación según el tipo del SRI (04 RUC, 05 cédula,
     * 06 pasaporte, 07 consumidor final, 08 exterior). Devuelve null si es
     * válida, o un mensaje claro en español de qué está mal.
     */
    public static function error(string $tipo, ?string $valor): ?string
    {
        $valor = trim((string) $valor);

        if ($valor === '') {
            return 'La identificación está vacía.';
        }

        return match ($tipo) {
            '05' => self::errorCedula($valor),
            '04' => self::errorRuc($valor),
            '07' => $valor === '9999999999999' ? null : 'Para consumidor final la identificación debe ser 9999999999999.',
            default => preg_match('/^[A-Za-z0-9]{3,20}$/', $valor) ? null : "La identificación \"{$valor}\" debe tener entre 3 y 20 letras o números, sin espacios ni símbolos.",
        };
    }

    /** Tipo SRI según la longitud: 13 dígitos = RUC (04), el resto se trata como cédula (05). */
    public static function tipoPorLongitud(string $valor): string
    {
        return strlen(trim($valor)) === 13 ? '04' : '05';
    }

    /** Para estudiantes: acepta cédula (10 dígitos) o RUC (13 dígitos). */
    public static function errorCedulaORuc(string $valor): ?string
    {
        $valor = trim($valor);

        if (strlen($valor) === 13) {
            return self::errorRuc($valor);
        }

        if (ctype_digit($valor) && strlen($valor) !== 10) {
            $n = strlen($valor);
            $pista = $n === 9 ? ' Probablemente falta el 0 inicial.' : '';

            return "\"{$valor}\" tiene {$n} dígitos: una cédula debe tener 10 y un RUC 13.{$pista}";
        }

        return self::errorCedula($valor);
    }

    public static function errorCedula(string $cedula): ?string
    {
        $cedula = trim($cedula);

        if (! ctype_digit($cedula)) {
            return "La cédula \"{$cedula}\" contiene caracteres que no son números.";
        }

        if (strlen($cedula) !== 10) {
            $n = strlen($cedula);
            $pista = match ($n) {
                9 => ' Probablemente falta el 0 inicial.',
                13 => ' Si es un RUC, cambie el tipo de identificación a RUC.',
                default => '',
            };

            return "La cédula \"{$cedula}\" tiene {$n} dígitos y debe tener 10.{$pista}";
        }

        $provincia = (int) substr($cedula, 0, 2);
        if (($provincia < 1 || $provincia > 24) && $provincia !== 30) {
            return "La cédula \"{$cedula}\" no es válida: los dos primeros dígitos (provincia) deben estar entre 01 y 24.";
        }

        if ((int) $cedula[2] > 5) {
            return "La cédula \"{$cedula}\" no es válida: el tercer dígito debe ser menor a 6.";
        }

        $suma = 0;
        for ($i = 0; $i < 9; $i++) {
            $producto = (int) $cedula[$i] * ($i % 2 === 0 ? 2 : 1);
            $suma += $producto > 9 ? $producto - 9 : $producto;
        }
        $verificador = (10 - ($suma % 10)) % 10;

        if ($verificador !== (int) $cedula[9]) {
            return "La cédula \"{$cedula}\" no es válida: el dígito verificador no coincide (revise si hay un número mal digitado).";
        }

        return null;
    }

    public static function errorRuc(string $ruc): ?string
    {
        if (! ctype_digit($ruc) || strlen($ruc) !== 13) {
            return "El RUC \"{$ruc}\" debe tener exactamente 13 números.";
        }

        if (substr($ruc, 10) === '000') {
            return "El RUC \"{$ruc}\" no es válido: debe terminar en un establecimiento como 001.";
        }

        // Persona natural (tercer dígito < 6): los 10 primeros son una cédula.
        if ((int) $ruc[2] < 6 && self::errorCedula(substr($ruc, 0, 10))) {
            return "El RUC \"{$ruc}\" no es válido: su cédula base es incorrecta.";
        }

        return null;
    }
}
