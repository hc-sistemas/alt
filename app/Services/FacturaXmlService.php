<?php

namespace App\Services;

use App\Models\Factura;
use XMLWriter;

/**
 * XML de factura electrónica del SRI (esquema 1.1.0) y clave de acceso.
 *
 * El XML que genera es SIN FIRMAR: sirve para revisarlo/descargarlo. La firma
 * XAdES-BES y el envío al webservice pertenecen al ciclo SRI (pendiente). Si la
 * factura ya tiene `xml_doc` (comprobante autorizado guardado), se devuelve ese.
 */
class FacturaXmlService
{
    /** Código de porcentaje de IVA según tabla 17 del SRI. */
    private const COD_IVA_0      = '0';
    private const COD_IVA_15     = '4';
    private const COD_IVA_EXENTO = '7';

    /** Forma de pago interna → código SRI (tabla 24). */
    private const FORMAS_PAGO = [
        'efectivo'      => '01', // Sin utilización del sistema financiero
        'transferencia' => '20', // Otros con utilización del sistema financiero
        'cheque'        => '20',
        'tarjeta'       => '19', // Tarjeta de crédito
        'credito'       => '01',
    ];

    /**
     * Clave de acceso de 49 dígitos. Se genera una sola vez y se guarda: el
     * código numérico aleatorio debe mantenerse estable durante toda la vida
     * del comprobante.
     */
    public function claveAcceso(Factura $factura): string
    {
        if ($factura->clave_acceso) {
            return $factura->clave_acceso;
        }

        $empresa  = $factura->empresa;
        $base = $factura->fecha_emision->format('dmY')
            . '01'                                                   // tipo de comprobante: factura
            . str_pad(preg_replace('/\D/', '', (string) $empresa->ruc), 13, '0', STR_PAD_LEFT)
            . ((int) $empresa->ambiente_sri === 2 ? '2' : '1')       // ambiente
            . str_pad($factura->establecimiento, 3, '0', STR_PAD_LEFT)
            . str_pad($factura->punto_emision, 3, '0', STR_PAD_LEFT)
            . str_pad((string) $factura->secuencial, 9, '0', STR_PAD_LEFT)
            . str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT)
            . '1';                                                   // tipo de emisión: normal

        $clave = $base . $this->digitoVerificador($base);
        $factura->update(['clave_acceso' => $clave]);

        return $clave;
    }

    /** Módulo 11 con pesos 2..7 de derecha a izquierda. */
    private function digitoVerificador(string $cadena): int
    {
        $suma = 0;
        $peso = 2;
        for ($i = strlen($cadena) - 1; $i >= 0; $i--) {
            $suma += ((int) $cadena[$i]) * $peso;
            $peso = $peso === 7 ? 2 : $peso + 1;
        }
        $dv = 11 - ($suma % 11);

        return $dv === 11 ? 0 : ($dv === 10 ? 1 : $dv);
    }

    public function xml(Factura $factura): string
    {
        if ($factura->xml_doc) {
            return $factura->xml_doc;
        }

        $factura->loadMissing(['empresa', 'detalles', 'pagos']);
        $empresa = $factura->empresa;
        $clave   = $this->claveAcceso($factura);

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');

        $w->startElement('factura');
        $w->writeAttribute('id', 'comprobante');
        $w->writeAttribute('version', '1.1.0');

        // ── infoTributaria ──
        $w->startElement('infoTributaria');
        $w->writeElement('ambiente', (int) $empresa->ambiente_sri === 2 ? '2' : '1');
        $w->writeElement('tipoEmision', '1');
        $w->writeElement('razonSocial', $empresa->razon_social);
        if ($empresa->nombre_comercial) {
            $w->writeElement('nombreComercial', $empresa->nombre_comercial);
        }
        $w->writeElement('ruc', $empresa->ruc);
        $w->writeElement('claveAcceso', $clave);
        $w->writeElement('codDoc', '01');
        $w->writeElement('estab', str_pad($factura->establecimiento, 3, '0', STR_PAD_LEFT));
        $w->writeElement('ptoEmi', str_pad($factura->punto_emision, 3, '0', STR_PAD_LEFT));
        $w->writeElement('secuencial', str_pad((string) $factura->secuencial, 9, '0', STR_PAD_LEFT));
        $w->writeElement('dirMatriz', $empresa->direccion_matriz ?: 'S/N');
        $w->endElement();

        // ── infoFactura ──
        $sinImpuestos = (float) $factura->subtotal_0 + (float) $factura->subtotal_15 + (float) $factura->subtotal_exento;

        $w->startElement('infoFactura');
        $w->writeElement('fechaEmision', $factura->fecha_emision->format('d/m/Y'));
        $w->writeElement('dirEstablecimiento', $empresa->direccion_establecimiento ?: ($empresa->direccion_matriz ?: 'S/N'));
        $w->writeElement('obligadoContabilidad', $empresa->obligado_contabilidad ? 'SI' : 'NO');
        $w->writeElement('tipoIdentificacionComprador', $factura->tipo_identificacion ?: '05');
        $w->writeElement('razonSocialComprador', $factura->razon_social);
        $w->writeElement('identificacionComprador', $factura->identificacion);
        if ($factura->direccion_cliente) {
            $w->writeElement('direccionComprador', $factura->direccion_cliente);
        }
        $w->writeElement('totalSinImpuestos', $this->n($sinImpuestos));
        $w->writeElement('totalDescuento', $this->n($factura->descuento_total));

        $w->startElement('totalConImpuestos');
        foreach ([
            [self::COD_IVA_15, $factura->subtotal_15, $factura->total_iva],
            [self::COD_IVA_0, $factura->subtotal_0, 0],
            [self::COD_IVA_EXENTO, $factura->subtotal_exento, 0],
        ] as [$codigoPorcentaje, $base, $valor]) {
            if ((float) $base <= 0) {
                continue;
            }
            $w->startElement('totalImpuesto');
            $w->writeElement('codigo', '2');
            $w->writeElement('codigoPorcentaje', $codigoPorcentaje);
            $w->writeElement('baseImponible', $this->n($base));
            $w->writeElement('valor', $this->n($valor));
            $w->endElement();
        }
        $w->endElement();

        $w->writeElement('propina', '0.00');
        $w->writeElement('importeTotal', $this->n($factura->total));
        $w->writeElement('moneda', 'DOLAR');

        $w->startElement('pagos');
        foreach ($factura->pagos as $pago) {
            $w->startElement('pago');
            $w->writeElement('formaPago', self::FORMAS_PAGO[$pago->forma_pago] ?? '01');
            $w->writeElement('total', $this->n($pago->valor));
            if ($pago->forma_pago === 'credito' && (int) $pago->plazo > 0) {
                $w->writeElement('plazo', (string) (int) $pago->plazo);
                $w->writeElement('unidadTiempo', 'dias');
            }
            $w->endElement();
        }
        $w->endElement();
        $w->endElement(); // infoFactura

        // ── detalles ──
        $w->startElement('detalles');
        foreach ($factura->detalles as $d) {
            $grava = (float) $d->porcentaje_iva > 0;

            $w->startElement('detalle');
            $w->writeElement('codigoPrincipal', $d->codigo_producto ?: (string) $d->producto_id);
            $w->writeElement('descripcion', $d->descripcion ?: 'S/D');
            $w->writeElement('cantidad', $this->n($d->cantidad, 6));
            $w->writeElement('precioUnitario', $this->n($d->precio_unitario, 6));
            $w->writeElement('descuento', $this->n($d->descuento_valor));
            $w->writeElement('precioTotalSinImpuesto', $this->n($d->subtotal));
            $w->startElement('impuestos');
            $w->startElement('impuesto');
            $w->writeElement('codigo', '2');
            $w->writeElement('codigoPorcentaje', $grava ? self::COD_IVA_15 : self::COD_IVA_0);
            $w->writeElement('tarifa', $grava ? '15' : '0');
            $w->writeElement('baseImponible', $this->n($d->subtotal));
            $w->writeElement('valor', $this->n($d->valor_iva));
            $w->endElement();
            $w->endElement();
            $w->endElement(); // detalle
        }
        $w->endElement();

        // ── infoAdicional ──
        $adicional = array_filter([
            'Email'     => $factura->email_cliente,
            'Telefono'  => $factura->telefono_cliente,
            'Direccion' => $factura->direccion_cliente,
        ]);
        if ($adicional) {
            $w->startElement('infoAdicional');
            foreach ($adicional as $nombre => $valor) {
                $w->startElement('campoAdicional');
                $w->writeAttribute('nombre', $nombre);
                $w->text((string) $valor);
                $w->endElement();
            }
            $w->endElement();
        }

        $w->endElement(); // factura
        $w->endDocument();

        return $w->outputMemory();
    }

    private function n(mixed $valor, int $decimales = 2): string
    {
        return number_format((float) $valor, $decimales, '.', '');
    }
}
