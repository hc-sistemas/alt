<?php

namespace App\Services;

use App\Models\Factura;
use App\Support\Identificacion;
use DOMDocument;
use DOMXPath;

/**
 * Filtro previo al envío al SRI. Revisa (1) los datos de la empresa y de la factura y
 * (2) el XML ya generado: estructura, formatos y que todos los valores cuadren entre sí.
 * Cada método devuelve una lista de mensajes claros en español; vacía = todo en orden.
 */
class FacturaSriValidador
{
    /** Tolerancia de cant × precio por línea (el precio unitario admite 6 decimales; el SRI acepta ±0.01). */
    private const TOL = 0.01;
    /** Sumas y totales: deben cuadrar exacto al centavo. */
    private const TOL_SUMA = 0.0;
    private const FORMAS_PAGO = ['efectivo', 'transferencia', 'cheque', 'tarjeta', 'credito'];

    /** @return list<string> */
    public function validarDatos(Factura $f): array
    {
        $e = [];
        $emp = $f->empresa;

        // ── Empresa ──
        if ($msg = Identificacion::errorRuc(trim((string) $emp->ruc))) {
            $e[] = 'Empresa: ' . $msg;
        }
        if (trim((string) $emp->razon_social) === '') {
            $e[] = 'Empresa: falta la razón social (Configuración → Empresa).';
        }
        if (trim((string) $emp->direccion_matriz) === '') {
            $e[] = 'Empresa: falta la dirección de la matriz (Configuración → Empresa). El SRI la exige.';
        }
        if (! $emp->firma_electronica_path || ! $emp->firma_electronica_pass) {
            $e[] = 'Empresa: no tiene firma electrónica cargada (Configuración → Empresa → Firma electrónica).';
        } elseif (! is_file(storage_path('app/private/' . $emp->firma_electronica_path))) {
            $e[] = 'Empresa: el archivo de la firma electrónica no está en el servidor; vuelva a cargarlo (Configuración → Empresa).';
        }
        if (! is_file(config('sri.firmador_jar'))) {
            $e[] = 'Servidor: falta el firmador sri_firma_xml.jar en el servidor (resources/tools). Avise a soporte técnico.';
        }

        // ── Numeración y fecha ──
        if (! preg_match('/^\d{1,3}$/', (string) $f->establecimiento) || (int) $f->establecimiento < 1) {
            $e[] = "Factura: el establecimiento «{$f->establecimiento}» no es válido (debe ser numérico, ej. 001).";
        }
        if (! preg_match('/^\d{1,3}$/', (string) $f->punto_emision) || (int) $f->punto_emision < 1) {
            $e[] = "Factura: el punto de emisión «{$f->punto_emision}» no es válido (debe ser numérico, ej. 001).";
        }
        if (! preg_match('/^\d{1,9}$/', (string) $f->secuencial) || (int) $f->secuencial < 1) {
            $e[] = "Factura: el secuencial «{$f->secuencial}» no es válido (1 a 9 dígitos).";
        }
        if (! $f->fecha_emision) {
            $e[] = 'Factura: no tiene fecha de emisión.';
        } elseif ($f->fecha_emision->isFuture()) {
            $e[] = 'Factura: la fecha de emisión es posterior a hoy; el SRI no la aceptará.';
        }

        // ── Cliente ──
        $tipo = (string) ($f->tipo_identificacion ?: '05');
        if (! in_array($tipo, ['04', '05', '06', '07', '08'], true)) {
            $e[] = "Cliente: el tipo de identificación «{$tipo}» no es válido (04 RUC, 05 cédula, 06 pasaporte, 07 consumidor final, 08 exterior).";
        } elseif ($msg = Identificacion::error($tipo, $f->identificacion)) {
            $e[] = 'Cliente: ' . $msg . ' Corríjala en la ficha del cliente antes de enviar.';
        }
        if (trim((string) $f->razon_social) === '') {
            $e[] = 'Cliente: falta el nombre o razón social del comprador.';
        } elseif (mb_strlen($f->razon_social) > 300) {
            $e[] = 'Cliente: el nombre del comprador supera los 300 caracteres.';
        }
        if ($tipo === '07') {
            if (mb_strtoupper(trim((string) $f->razon_social)) !== 'CONSUMIDOR FINAL') {
                $e[] = 'Cliente: para consumidor final el nombre debe ser «CONSUMIDOR FINAL».';
            }
            if ((float) $f->total > 50.0 + 1e-9) {
                $e[] = 'Cliente: una factura a consumidor final no puede superar $50.00; el SRI exige identificar al comprador.';
            }
        }

        // ── Detalles ──
        if ($f->detalles->isEmpty()) {
            $e[] = 'Factura: no tiene ningún producto en el detalle.';
        }
        foreach ($f->detalles as $i => $d) {
            $n = $i + 1;
            if (trim((string) $d->descripcion) === '') {
                $e[] = "Línea {$n}: falta la descripción del producto.";
            } elseif (mb_strlen($d->descripcion) > 300) {
                $e[] = "Línea {$n}: la descripción supera los 300 caracteres.";
            }
            if (trim((string) ($d->codigo_producto ?: $d->producto_id)) === '') {
                $e[] = "Línea {$n}: el producto no tiene código.";
            }
            if ((float) $d->precio_unitario < 0) {
                $e[] = "Línea {$n}: el precio unitario no puede ser negativo.";
            }
            if ((float) $d->porcentaje_iva !== 15.0) {
                $e[] = "Línea {$n} (" . ($d->codigo_producto ?: $d->descripcion) . "): el producto no tiene IVA 15% (tiene {$d->porcentaje_iva}%). Esta empresa factura todo con IVA 15%; corrija el producto antes de enviar.";
            }
        }

        // ── Pagos ──
        if ($f->pagos->isEmpty()) {
            $e[] = 'Factura: no tiene formas de pago registradas.';
        }
        foreach ($f->pagos as $p) {
            if (! in_array($p->forma_pago, self::FORMAS_PAGO, true)) {
                $e[] = "Pago: la forma de pago «{$p->forma_pago}» no tiene equivalente en el SRI.";
            }
            if ((float) $p->valor <= 0) {
                $e[] = "Pago «{$p->forma_pago}»: el valor debe ser mayor a 0.";
            }
        }

        return $e;
    }

    /** @return list<string> */
    public function validarXml(string $xml, Factura $f): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (! $dom->loadXML($xml)) {
            $m = collect(libxml_get_errors())->map(fn ($x) => trim($x->message))->unique()->implode(' | ');
            libxml_clear_errors();

            return ["El XML generado no está bien formado: {$m}"];
        }
        libxml_clear_errors();

        $x = new DOMXPath($dom);
        $v = fn (string $path) => trim((string) $x->evaluate("string({$path})"));
        $e = [];

        // ── Campos obligatorios y formato ──
        $obligatorios = [
            'infoTributaria/ambiente' => '/^[12]$/',
            'infoTributaria/tipoEmision' => '/^1$/',
            'infoTributaria/razonSocial' => '/^.{1,300}$/su',
            'infoTributaria/ruc' => '/^\d{13}$/',
            'infoTributaria/claveAcceso' => '/^\d{49}$/',
            'infoTributaria/codDoc' => '/^01$/',
            'infoTributaria/estab' => '/^\d{3}$/',
            'infoTributaria/ptoEmi' => '/^\d{3}$/',
            'infoTributaria/secuencial' => '/^\d{9}$/',
            'infoTributaria/dirMatriz' => '/^.{1,300}$/su',
            'infoFactura/fechaEmision' => '/^\d{2}\/\d{2}\/\d{4}$/',
            'infoFactura/dirEstablecimiento' => '/^.{1,300}$/su',
            'infoFactura/obligadoContabilidad' => '/^(SI|NO)$/',
            'infoFactura/tipoIdentificacionComprador' => '/^0[4-8]$/',
            'infoFactura/razonSocialComprador' => '/^.{1,300}$/su',
            'infoFactura/identificacionComprador' => '/^.{1,20}$/su',
            'infoFactura/totalSinImpuestos' => '/^\d+\.\d{2}$/',
            'infoFactura/totalDescuento' => '/^\d+\.\d{2}$/',
            'infoFactura/propina' => '/^\d+\.\d{2}$/',
            'infoFactura/importeTotal' => '/^\d+\.\d{2}$/',
            'infoFactura/moneda' => '/^.+$/',
        ];
        foreach ($obligatorios as $path => $regex) {
            $valor = $v("/factura/{$path}");
            if ($valor === '') {
                $e[] = 'XML: falta el campo «' . basename($path) . '».';
            } elseif (! preg_match($regex, $valor)) {
                $e[] = 'XML: el campo «' . basename($path) . "» tiene un formato inválido («{$valor}»).";
            }
        }

        if ($x->query('/factura/infoFactura/totalConImpuestos/totalImpuesto')->length === 0) {
            $e[] = 'XML: la factura no tiene ningún impuesto en el total (totalConImpuestos); revise que tenga valores en los subtotales.';
        }
        if ($x->query('/factura/infoFactura/pagos/pago')->length === 0) {
            $e[] = 'XML: la factura no tiene formas de pago.';
        }
        if ($x->query('/factura/detalles/detalle')->length === 0) {
            $e[] = 'XML: la factura no tiene detalle de productos.';
        }

        // ── Clave de acceso coherente con los datos ──
        $clave = $v('/factura/infoTributaria/claveAcceso');
        if (preg_match('/^\d{49}$/', $clave)) {
            $esperado = [
                'fecha de emisión' => [substr($clave, 0, 8), str_replace('/', '', $v('/factura/infoFactura/fechaEmision'))],
                'tipo de comprobante' => [substr($clave, 8, 2), $v('/factura/infoTributaria/codDoc')],
                'RUC' => [substr($clave, 10, 13), $v('/factura/infoTributaria/ruc')],
                'ambiente' => [substr($clave, 23, 1), $v('/factura/infoTributaria/ambiente')],
                'establecimiento y punto de emisión' => [substr($clave, 24, 6), $v('/factura/infoTributaria/estab') . $v('/factura/infoTributaria/ptoEmi')],
                'secuencial' => [substr($clave, 30, 9), $v('/factura/infoTributaria/secuencial')],
            ];
            foreach ($esperado as $campo => [$enClave, $enXml]) {
                if ($enClave !== $enXml) {
                    $e[] = "La clave de acceso no coincide con el XML en {$campo} (clave: {$enClave}, XML: {$enXml}).";
                }
            }
            if ((int) $clave[48] !== $this->modulo11(substr($clave, 0, 48))) {
                $e[] = 'La clave de acceso tiene un dígito verificador incorrecto.';
            }
        }

        // ── Cuadre de valores ──
        $num = fn (string $path) => (float) $v($path);
        $sinImp = $num('/factura/infoFactura/totalSinImpuestos');
        $total = $num('/factura/infoFactura/importeTotal');

        $baseImp = $ivaImp = 0.0;
        foreach ($x->query('/factura/infoFactura/totalConImpuestos/totalImpuesto') as $t) {
            $base = (float) $x->evaluate('string(baseImponible)', $t);
            $val = (float) $x->evaluate('string(valor)', $t);
            $cod = $x->evaluate('string(codigoPorcentaje)', $t);
            $baseImp += $base;
            $ivaImp += $val;
            $tarifa = ['0' => 0, '4' => 15, '7' => 0][$cod] ?? null;
            if ($tarifa === null) {
                $e[] = "Totales: código de porcentaje de IVA «{$cod}» desconocido.";
            } elseif (abs(round($base * $tarifa / 100, 2) - $val) > 1e-9) {
                $e[] = sprintf('Totales: el IVA del grupo %d%% es %.2f pero la base %.2f × %d%% da %.2f.', $tarifa, $val, $base, $tarifa, $base * $tarifa / 100);
            }
        }
        $this->cuadra($e, $baseImp, $sinImp, 'Totales: la suma de bases de los impuestos (%.2f) no coincide con el total sin impuestos (%.2f).');
        $this->cuadra($e, $sinImp + $ivaImp, $total, 'Totales: total sin impuestos + IVA (%.2f) no coincide con el importe total (%.2f).');

        $sumPagos = 0.0;
        foreach ($x->query('/factura/infoFactura/pagos/pago/total') as $t) {
            $sumPagos += (float) $t->textContent;
        }
        $this->cuadra($e, $sumPagos, $total, 'Pagos: la suma de las formas de pago (%.2f) no coincide con el total de la factura (%.2f).');

        $sumSub = $sumDesc = $sumIvaDet = 0.0;
        foreach ($x->query('/factura/detalles/detalle') as $k => $d) {
            $n = $k + 1;
            $cant = (float) $x->evaluate('string(cantidad)', $d);
            $pu = (float) $x->evaluate('string(precioUnitario)', $d);
            $desc = (float) $x->evaluate('string(descuento)', $d);
            $sub = (float) $x->evaluate('string(precioTotalSinImpuesto)', $d);
            $sumSub += $sub;
            $sumDesc += $desc;

            if (trim($x->evaluate('string(codigoPrincipal)', $d)) === '') {
                $e[] = "Línea {$n}: falta el código principal del producto.";
            }
            if ($cant <= 0) {
                $e[] = "Línea {$n}: la cantidad debe ser mayor a 0.";
            }
            if ($desc < 0 || $desc > $cant * $pu + self::TOL) {
                $e[] = sprintf('Línea %d: el descuento (%.2f) no puede ser negativo ni mayor al valor de la línea (%.2f).', $n, $desc, $cant * $pu);
            }
            $this->cuadra($e, $cant * $pu - $desc, $sub, "Línea {$n}: cantidad × precio − descuento (%.2f) no coincide con el total de la línea sin IVA (%.2f).", self::TOL);

            foreach ($x->query('impuestos/impuesto', $d) as $imp) {
                $base = (float) $x->evaluate('string(baseImponible)', $imp);
                $tarifa = (float) $x->evaluate('string(tarifa)', $imp);
                $val = (float) $x->evaluate('string(valor)', $imp);
                $sumIvaDet += $val;
                $this->cuadra($e, $base, $sub, "Línea {$n}: la base imponible (%.2f) no coincide con el total de la línea (%.2f).");
                $this->cuadra($e, $base * $tarifa / 100, $val, "Línea {$n}: el IVA calculado (%.2f) no coincide con el IVA de la línea (%.2f).");
            }
        }
        $this->cuadra($e, $sumSub, $sinImp, 'Totales: la suma de las líneas (%.2f) no coincide con el total sin impuestos (%.2f).');
        $this->cuadra($e, $sumDesc, $num('/factura/infoFactura/totalDescuento'), 'Totales: la suma de descuentos de las líneas (%.2f) no coincide con el descuento total (%.2f).');
        $this->cuadra($e, $sumIvaDet, $ivaImp, 'Totales: la suma del IVA de las líneas (%.2f) no coincide con el IVA total (%.2f); suele ser un descuadre de redondeo por centavos.');

        // ── Contra los valores guardados en la factura ──
        $this->cuadra($e, (float) $f->total, $total, 'La factura guardada tiene total %.2f pero el XML calcula %.2f.');

        return array_values(array_unique($e));
    }

    /** Agrega el mensaje (con los dos valores) si $a y $b difieren más que la tolerancia. */
    private function cuadra(array &$errores, float $a, float $b, string $formato, float $tol = self::TOL_SUMA): void
    {
        if (abs(round($a, 2) - round($b, 2)) > $tol + 1e-9) {
            $errores[] = sprintf($formato, $a, $b);
        }
    }

    private function modulo11(string $cadena): int
    {
        $suma = 0;
        $peso = 2;
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += (int) $cadena[$i] * $peso;
            $peso = $peso === 7 ? 2 : $peso + 1;
        }
        $dv = 11 - ($suma % 11);

        return $dv === 11 ? 0 : ($dv === 10 ? 1 : $dv);
    }
}
