<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Firma XAdES-BES del XML del comprobante usando sri_firma_xml.jar (Java 8). */
class SriFirmadorService
{
    public function firmar(string $xmlSinFirmar, string $rutaCertificado, string $claveCertificado): string
    {
        $jar = config('sri.firmador_jar');
        if (! is_file($jar)) {
            throw new RuntimeException('No se encontró el firmador del SRI (sri_firma_xml.jar) en el servidor.');
        }

        $dirTemporal = storage_path('app/tmp-firma');
        if (! is_dir($dirTemporal)) {
            mkdir($dirTemporal, 0755, true);
        }

        $nombreBase       = uniqid('comprobante_', true);
        $rutaXmlEntrada   = $dirTemporal . DIRECTORY_SEPARATOR . $nombreBase . '.xml';
        $nombreSalida     = $nombreBase . '-firmado.xml';
        $rutaXmlSalida    = $dirTemporal . DIRECTORY_SEPARATOR . $nombreSalida;

        file_put_contents($rutaXmlEntrada, $xmlSinFirmar);

        try {
            $proceso = new Process([
                config('sri.java_path'), '-jar', $jar,
                $rutaCertificado, $claveCertificado, $rutaXmlEntrada, $dirTemporal, $nombreSalida,
            ]);
            $proceso->setTimeout((int) config('sri.timeout_firma', 60));
            $proceso->run();

            if (! $proceso->isSuccessful() || ! file_exists($rutaXmlSalida)) {
                $salida = trim($proceso->getOutput() . PHP_EOL . $proceso->getErrorOutput());
                Log::warning('Firmador SRI: no pudo firmar el XML', ['salida_java' => $salida]);

                $detalle = $salida !== '' ? mb_substr($salida, 0, 200) : null;

                throw new RuntimeException(
                    'No se pudo firmar el comprobante. Revise que el certificado digital no esté vencido y que la clave sea correcta (Configuración → Empresa).'
                    . ($detalle ? " (Detalle técnico: {$detalle})" : '')
                );
            }

            return file_get_contents($rutaXmlSalida);
        } finally {
            @unlink($rutaXmlEntrada);
            @unlink($rutaXmlSalida);
        }
    }
}
