<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SimpleXMLElement;
use Throwable;

class SriWebServiceClient
{
    /**
     * Envía el XML firmado al web service de Recepción. Retorna 'RECIBIDA' o 'DEVUELTA'.
     */
    public function enviarRecepcion(string $xmlFirmado, int $ambiente): array
    {
        $envelope = $this->envolverSoap('ec.gob.sri.ws.recepcion', 'validarComprobante', [
            'xml' => base64_encode($xmlFirmado),
        ]);

        $nodo = $this->buscarNodo(
            $this->llamar($this->endpoint($ambiente, 'recepcion'), $envelope),
            'RespuestaRecepcionComprobante'
        );

        return [
            'estado' => (string) ($nodo->estado ?? ''),
            'mensajes' => $this->extraerMensajes($nodo),
        ];
    }

    /**
     * Consulta el resultado de autorización para una clave de acceso.
     *
     * Si el SRI no tiene ningún comprobante registrado con esa clave (nunca se recibió,
     * o la respuesta de un envío anterior se perdió antes de guardarse), devuelve
     * 'estado' => '' en vez de lanzar una excepción -- es una respuesta válida del SRI,
     * no un error de conexión, y le permite al llamador decidir si corresponde enviar.
     */
    public function consultarAutorizacion(string $claveAcceso, int $ambiente): array
    {
        $envelope = $this->envolverSoap('ec.gob.sri.ws.autorizacion', 'autorizacionComprobante', [
            'claveAccesoComprobante' => $claveAcceso,
        ]);

        $raiz = $this->buscarNodo(
            $this->llamar($this->endpoint($ambiente, 'autorizacion'), $envelope),
            'RespuestaAutorizacionComprobante'
        );

        $autorizacion = $raiz->autorizaciones->autorizacion ?? null;

        if (! $autorizacion) {
            return [
                'estado' => '',
                'numero_autorizacion' => null,
                'fecha_autorizacion' => null,
                'xml_autorizado' => null,
                'mensajes' => [],
            ];
        }

        return [
            'estado' => (string) ($autorizacion->estado ?? ''),
            'numero_autorizacion' => $this->valorONull($autorizacion->numeroAutorizacion ?? null),
            'fecha_autorizacion' => $this->valorONull($autorizacion->fechaAutorizacion ?? null),
            'xml_autorizado' => $this->valorONull($autorizacion->comprobante ?? null),
            'mensajes' => $this->extraerMensajes($autorizacion),
        ];
    }

    private function endpoint(int $ambiente, string $tipo): string
    {
        $url = config("sri.endpoints.{$ambiente}.{$tipo}");

        if (! $url) {
            throw new RuntimeException("No hay endpoint del SRI configurado para el ambiente {$ambiente} ({$tipo}).");
        }

        return $url;
    }

    private function envolverSoap(string $namespace, string $operacion, array $parametros): string
    {
        $params = '';
        foreach ($parametros as $nombre => $valor) {
            $params .= "<{$nombre}>".htmlspecialchars((string) $valor, ENT_XML1 | ENT_QUOTES, 'UTF-8')."</{$nombre}>";
        }

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ec="http://{$namespace}">
   <soapenv:Header/>
   <soapenv:Body>
      <ec:{$operacion}>
         {$params}
      </ec:{$operacion}>
   </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    private function llamar(string $endpoint, string $envelope): string
    {
        try {
            $respuesta = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '""',
            ])
                ->withBody($envelope, 'text/xml')
                ->timeout((int) config('sri.timeout_ws', 30))
                ->post($endpoint);
        } catch (Throwable $e) {
            Log::warning('SRI: no se pudo conectar', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            throw new RuntimeException(
                'No se pudo conectar con el servicio del SRI. Puede ser un corte de conexión temporal -- intente reintentar en unos minutos.'
                .' (Detalle técnico: '.$this->extractoCorto($e->getMessage()).')'
            );
        }

        if ($respuesta->failed()) {
            $motivo = $this->extraerFault($respuesta);
            Log::warning('SRI: respondió con error HTTP', [
                'endpoint' => $endpoint,
                'status' => $respuesta->status(),
                'motivo' => $motivo,
                'body' => $this->extracto($respuesta->body()),
            ]);

            throw new RuntimeException(
                "El servicio del SRI respondió con un error (código HTTP {$respuesta->status()})."
                .' Puede ser un problema temporal del lado del SRI (mantenimiento, sobrecarga, etc.) -- intente reintentar más tarde.'
                .($motivo ? ' (Detalle técnico: '.$this->extractoCorto($motivo).')' : '')
            );
        }

        return $this->normalizarUtf8($respuesta->body());
    }

    /**
     * El SRI a veces responde con bytes que no son UTF-8 válido (típicamente en
     * tildes/Ñ de textos como "PRODUCCIÓN"), aunque el envelope declare esa
     * codificación -- eso hace que el parseo XML estricto de PHP falle
     * ("no es un XML válido") aunque el mismo contenido se vea bien en un
     * cliente SOAP más permisivo. Se detecta y corrige antes de parsear.
     */
    private function normalizarUtf8(string $contenido): string
    {
        if ($contenido === '' || mb_check_encoding($contenido, 'UTF-8')) {
            return $contenido;
        }

        return mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
    }

    private function extraerFault(Response $respuesta): ?string
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($this->normalizarUtf8($respuesta->body()), 'SimpleXMLElement', LIBXML_PARSEHUGE);
        libxml_clear_errors();

        if ($doc === false) {
            return null;
        }

        $nodos = $doc->xpath("//*[local-name()='Text' or local-name()='faultstring']") ?: [];

        return isset($nodos[0]) ? trim((string) $nodos[0]) : null;
    }

    private function buscarNodo(string $xmlRespuesta, string $nombreNodo): SimpleXMLElement
    {
        libxml_use_internal_errors(true);
        // LIBXML_PARSEHUGE: la respuesta de autorización trae el comprobante firmado
        // completo (con cadena de certificado) embebido en un CDATA -- sin este flag,
        // libxml aplica un límite de tamaño interno y simplexml_load_string() devuelve
        // false en respuestas grandes aunque el XML sea válido (se puede confirmar el
        // mismo XML con un cliente SOAP externo). Ese fue el causante real detrás de
        // "La respuesta del SRI no es un XML válido" en facturas que sí estaban
        // autorizadas.
        $doc = simplexml_load_string($xmlRespuesta, 'SimpleXMLElement', LIBXML_PARSEHUGE);
        $erroresLibxml = libxml_get_errors();
        libxml_clear_errors();

        if ($doc === false) {
            $detalleErrores = implode(' | ', array_map(
                fn ($e) => trim($e->message).' (línea '.$e->line.')',
                $erroresLibxml
            ));

            Log::warning('SRI: respuesta no es XML válido', [
                'nodo_esperado' => $nombreNodo,
                'detalle_libxml' => $detalleErrores,
                'contenido' => $this->extracto($xmlRespuesta),
            ]);

            throw new RuntimeException(
                'El SRI devolvió una respuesta que no se pudo interpretar. Puede ser un problema temporal del servicio -- intente reintentar; si vuelve a pasar, avise a soporte técnico.'
                .($detalleErrores ? ' (Detalle técnico: '.$this->extractoCorto($detalleErrores).')' : '')
            );
        }

        $nodos = $doc->xpath("//*[local-name()='{$nombreNodo}']") ?: [];

        if (empty($nodos)) {
            Log::warning('SRI: respuesta sin el nodo esperado', [
                'nodo_esperado' => $nombreNodo,
                'contenido' => $this->extracto($xmlRespuesta),
            ]);

            throw new RuntimeException(
                "La respuesta del SRI no tuvo el formato esperado (faltó la sección '{$nombreNodo}')."
                .' Puede ser un problema temporal del servicio -- intente reintentar; si vuelve a pasar, avise a soporte técnico.'
            );
        }

        return $nodos[0];
    }

    /**
     * Recorte legible de una respuesta cruda del SRI -- se manda al log completo
     * (hasta 8000 caracteres) para poder diagnosticar a fondo cuando haga falta,
     * sin depender de acceso directo al servidor.
     */
    private function extracto(string $contenido): string
    {
        $contenido = trim($contenido);

        if ($contenido === '') {
            return '(respuesta vacía)';
        }

        return mb_strlen($contenido) > 8000 ? mb_substr($contenido, 0, 8000).'…' : $contenido;
    }

    /**
     * Versión corta del detalle técnico para el mensaje que ve el usuario en la
     * factura -- lo suficiente para reconocer el tipo de problema (timeout,
     * error de esquema, etc.) sin volcarle un bloque de XML o cURL ilegible.
     * El contenido completo siempre queda en el log (ver Log::warning arriba).
     */
    private function extractoCorto(string $contenido): string
    {
        $contenido = trim($contenido);

        return mb_strlen($contenido) > 200 ? mb_substr($contenido, 0, 200).'…' : $contenido;
    }

    private function extraerMensajes(SimpleXMLElement $nodo): array
    {
        $mensajes = [];

        foreach ($nodo->xpath(".//*[local-name()='mensajes']/*[local-name()='mensaje']") ?: [] as $mensaje) {
            $mensajes[] = [
                'identificador' => (string) ($mensaje->identificador ?? ''),
                'mensaje' => (string) ($mensaje->mensaje ?? ''),
                'informacionAdicional' => (string) ($mensaje->informacionAdicional ?? ''),
                'tipo' => (string) ($mensaje->tipo ?? ''),
            ];
        }

        return $mensajes;
    }

    private function valorONull(?SimpleXMLElement $nodo): ?string
    {
        if ($nodo === null) {
            return null;
        }

        $valor = trim((string) $nodo);

        return $valor !== '' ? $valor : null;
    }
}
