<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Factura;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;
use Throwable;

/**
 * Ciclo SRI de una factura:
 *   1. Consulta por clave de acceso: si el SRI ya la tiene, solo se recuperan los datos.
 *   2. Valida datos y XML (nada se envía si algo no cuadra).
 *   3. Firma → recepción → autorización.
 *
 * Estados de `facturas.estado_sri`: pendiente | recibida | autorizada | rechazada.
 * Un fallo de validación, firma o conexión NO cambia el estado (sigue reintentable);
 * el motivo queda en `observacion_sri`. Nunca pisa una factura ya autorizada.
 *
 * Resultado: ['ok' => bool, 'estado' => string, 'mensaje' => string, 'errores' => string[]]
 */
class FacturaSriService
{
    /** Errores frecuentes del SRI → qué hacer. */
    private const AYUDA_SRI = [
        '35' => 'El XML no cumple la estructura del SRI. Revise los datos de la factura y de la empresa.',
        '39' => 'La firma electrónica no es válida: verifique que el certificado no esté vencido y que corresponda al RUC de la empresa.',
        '45' => 'Ese secuencial ya fue registrado en el SRI con otro comprobante.',
        '46' => 'El RUC del emisor no está activo en el SRI.',
        '52' => 'El SRI detectó diferencias entre los valores del comprobante (totales, impuestos o pagos).',
        '56' => 'El establecimiento del emisor figura como cerrado en el SRI.',
    ];

    public function __construct(
        private FacturaXmlService $xml,
        private FacturaSriValidador $validador,
        private SriFirmadorService $firmador,
        private SriWebServiceClient $sri,
    ) {}

    public function enviar(Factura $factura): array
    {
        if ($factura->estado_sri === 'autorizada') {
            return $this->resultado(true, 'autorizada', 'La factura ya está autorizada por el SRI.');
        }

        $factura->loadMissing(['empresa', 'detalles', 'pagos']);
        $empresa  = $factura->empresa;
        $ambiente = (int) $empresa->ambiente_sri === 2 ? 2 : 1;

        // Una clave generada en otro ambiente no sirve: se descarta y se genera una nueva.
        if ($factura->clave_acceso && (int) substr($factura->clave_acceso, 23, 1) !== $ambiente) {
            $factura->update(['clave_acceso' => null]);
        }

        // ── 1. ¿El SRI ya tiene esta clave de acceso? ──
        if ($factura->clave_acceso) {
            try {
                $previa = $this->sri->consultarAutorizacion($factura->clave_acceso, $ambiente);
            } catch (RuntimeException $e) {
                return $this->fallo($factura, 'No se pudo consultar al SRI si la factura ya fue enviada', $e->getMessage());
            }

            switch ($previa['estado']) {
                case 'AUTORIZADO':
                    $this->guardarAutorizada($factura, $previa, $ambiente);

                    return $this->resultado(true, 'autorizada', 'La factura ya estaba autorizada en el SRI; se recuperaron sus datos (número y fecha de autorización).');

                case 'EN PROCESO':
                case 'EN PROCESAMIENTO':
                    $factura->update(['estado_sri' => 'recibida', 'observacion_sri' => 'El SRI la recibió y la está procesando.']);

                    return $this->resultado(false, 'recibida', 'El SRI ya recibió esta factura y aún la está procesando. Vuelva a intentar en unos minutos.');

                case '':
                    break; // nunca llegó al SRI: se envía normalmente

                default:
                    // NO AUTORIZADO u otro: esa clave quedó usada; se envía de nuevo con una clave nueva.
                    $factura->update(['clave_acceso' => null]);
            }
        }

        // ── 2. Validación previa ──
        $errores = $this->validador->validarDatos($factura);
        if ($errores) {
            return $this->bloqueo($factura, 'No se envió al SRI: hay datos por corregir.', $errores);
        }

        $xmlSinFirmar = $this->xml->xmlSinFirmar($factura);
        $errores = $this->validador->validarXml($xmlSinFirmar, $factura);
        if ($errores) {
            return $this->bloqueo($factura, 'No se envió al SRI: los valores de la factura no cuadran.', $errores);
        }

        // ── 3. Firma, recepción y autorización ──
        try {
            $xmlFirmado = $this->firmar($empresa, $xmlSinFirmar);
        } catch (RuntimeException $e) {
            return $this->fallo($factura, 'No se pudo firmar la factura', $e->getMessage());
        }

        $clave = $factura->clave_acceso;

        try {
            $recepcion = $this->sri->enviarRecepcion($xmlFirmado, $ambiente);
        } catch (RuntimeException $e) {
            return $this->fallo($factura, 'No se pudo enviar la factura al SRI', $e->getMessage());
        }

        $yaRegistrada = collect($recepcion['mensajes'])->contains(fn ($m) => $m['identificador'] === '43');
        if ($recepcion['estado'] !== 'RECIBIDA' && ! $yaRegistrada) {
            return $this->rechazo($factura, 'El SRI devolvió la factura sin recibirla.', $recepcion['mensajes']);
        }

        try {
            $aut = $this->sri->consultarAutorizacion($clave, $ambiente);
        } catch (RuntimeException $e) {
            $factura->update(['estado_sri' => 'recibida', 'observacion_sri' => mb_substr($e->getMessage(), 0, 1000)]);

            return $this->resultado(false, 'recibida', 'El SRI recibió la factura pero no se pudo consultar su autorización: ' . $e->getMessage() . ' Use "Enviar SRI" otra vez para recuperarla.');
        }

        if ($aut['estado'] === 'AUTORIZADO') {
            $this->guardarAutorizada($factura, $aut, $ambiente, $xmlFirmado);

            return $this->resultado(true, 'autorizada', 'Factura autorizada por el SRI.');
        }

        if ($aut['estado'] === '' || str_starts_with($aut['estado'], 'EN PROCES')) {
            $factura->update(['estado_sri' => 'recibida', 'observacion_sri' => 'Recibida por el SRI; la autorización aún no está disponible.']);

            return $this->resultado(false, 'recibida', 'El SRI recibió la factura pero todavía no la autoriza. Use "Enviar SRI" otra vez en unos minutos para recuperar la autorización.');
        }

        return $this->rechazo($factura, 'El SRI no autorizó la factura.', $aut['mensajes']);
    }

    private function firmar(Empresa $empresa, string $xml): string
    {
        $ruta = storage_path('app/private/' . $empresa->firma_electronica_path);

        try {
            $clave = Crypt::decryptString($empresa->firma_electronica_pass);
        } catch (Throwable) {
            throw new RuntimeException('La clave de la firma electrónica guardada no se puede leer; vuelva a cargar la firma (Configuración → Empresa).');
        }

        return $this->firmador->firmar($xml, $ruta, $clave);
    }

    private function guardarAutorizada(Factura $factura, array $aut, int $ambiente, ?string $xmlFirmado = null): void
    {
        $comprobante = $aut['xml_autorizado'] ?: $xmlFirmado;
        $esc = fn ($v) => htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = $comprobante
            ? '<?xml version="1.0" encoding="UTF-8"?><autorizacion><estado>AUTORIZADO</estado>'
                . '<numeroAutorizacion>' . $esc($aut['numero_autorizacion']) . '</numeroAutorizacion>'
                . '<fechaAutorizacion>' . $esc($aut['fecha_autorizacion']) . '</fechaAutorizacion>'
                . '<ambiente>' . ($ambiente === 2 ? 'PRODUCCIÓN' : 'PRUEBAS') . '</ambiente>'
                . '<comprobante><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $comprobante) . ']]></comprobante></autorizacion>'
            : $factura->xml_doc;

        $factura->update([
            'estado_sri'      => 'autorizada',
            'autorizacion'    => $aut['numero_autorizacion'] ?? $factura->clave_acceso,
            'fecha_hora_aut'  => $aut['fecha_autorizacion'],
            'observacion_sri' => null,
            'xml_doc'         => $xml,
        ]);
    }

    private function resultado(bool $ok, string $estado, string $mensaje, array $errores = []): array
    {
        return compact('ok', 'estado', 'mensaje', 'errores');
    }

    /** Falla antes de enviar (validación): no cambia el estado; guarda el motivo. */
    private function bloqueo(Factura $factura, string $mensaje, array $errores): array
    {
        $factura->update(['observacion_sri' => mb_substr($mensaje . ' ' . implode(' | ', $errores), 0, 2000)]);

        return $this->resultado(false, $factura->estado_sri, $mensaje, $errores);
    }

    /** Falla de firma o de conexión: no cambia el estado; reintentable. */
    private function fallo(Factura $factura, string $titulo, string $detalle): array
    {
        $factura->update(['observacion_sri' => mb_substr("{$titulo}: {$detalle}", 0, 2000)]);

        return $this->resultado(false, $factura->estado_sri, $titulo . '.', [$detalle]);
    }

    /** El SRI respondió con mensajes de rechazo/devolución. */
    private function rechazo(Factura $factura, string $titulo, array $mensajes): array
    {
        $errores = [];
        foreach ($mensajes as $m) {
            $linea = ($m['identificador'] !== '' ? "[{$m['identificador']}] " : '') . trim($m['mensaje']);
            if ($m['informacionAdicional'] !== '') {
                $linea .= ' — ' . trim($m['informacionAdicional']);
            }
            if ($ayuda = self::AYUDA_SRI[$m['identificador']] ?? null) {
                $linea .= '. ' . $ayuda;
            }
            $errores[] = $linea;
        }
        $errores = $errores ?: ['El SRI no indicó el motivo.'];

        $factura->update(['estado_sri' => 'rechazada', 'observacion_sri' => mb_substr($titulo . ' ' . implode(' | ', $errores), 0, 2000)]);

        return $this->resultado(false, 'rechazada', $titulo, $errores);
    }
}
