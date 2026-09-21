<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Prefactura;
use App\Models\Proforma;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF de Proforma y Prefactura con el mismo diseño del RIDE de factura
 * (sin datos del SRI, porque no son comprobantes tributarios).
 */
class DocumentoVentaPdf
{
    public function proforma(Proforma $p)
    {
        $p->loadMissing(['empresa', 'cliente', 'usuario', 'detalles.producto']);

        $detalles = $p->detalles->map(fn ($d) => $this->linea(
            $d->producto?->codigo ?? '', $d->descripcion, (float) $d->cantidad,
            (float) $d->precio_unitario, (float) $d->descuento_pct, (float) $d->subtotal, (float) $d->total,
        ));

        $subtotal = (float) $p->subtotal;
        $iva      = (float) $p->total_iva;
        // Base gravada = IVA / 15%; el resto es base 0%.
        $base15   = $iva > 0 ? round($iva / 0.15, 2) : 0.0;

        return $this->render([
            'titulo'        => 'PROFORMA',
            'numero'        => $p->numero,
            'fecha'         => $p->fecha_emision?->format('d/m/Y'),
            'vencimiento'   => $p->fecha_vencimiento?->format('d/m/Y'),
            'estado'        => $p->estado,
            'empresa'       => $p->empresa,
            'cliente'       => $p->cliente,
            'vendedor'      => $p->usuario?->nombre,
            'detalles'      => $detalles,
            'subtotal15'    => $base15,
            'subtotal0'     => max(0, round($subtotal - $base15, 2)),
            'descuento'     => (float) $p->descuento_total,
            'iva'           => $iva,
            'total'         => (float) $p->total,
            'observaciones' => $p->observaciones,
            'abonos'        => [],
        ]);
    }

    public function prefactura(Prefactura $p)
    {
        $p->loadMissing(['empresa', 'cliente', 'usuario', 'detalles.producto', 'abonos']);

        $subtotal15 = 0.0;
        $subtotal0  = 0.0;
        $descuento  = 0.0;
        $iva        = 0.0;

        $detalles = $p->detalles->map(function ($d) use (&$subtotal15, &$subtotal0, &$descuento, &$iva) {
            $base  = (float) $d->cantidad * (float) $d->precio_unitario;
            $desc  = round($base * ((float) $d->descuento_pct / 100), 2);
            $neto  = round($base - $desc, 2);
            $grava = (float) $d->total > $neto + 0.001;

            $descuento += $desc;
            if ($grava) {
                $subtotal15 += $neto;
            } else {
                $subtotal0 += $neto;
            }

            return $this->linea(
                $d->producto?->codigo ?? '', $d->descripcion, (float) $d->cantidad,
                (float) $d->precio_unitario, (float) $d->descuento_pct, $neto, (float) $d->total,
            );
        });
        $iva = round($subtotal15 * 0.15, 2);

        return $this->render([
            'titulo'        => 'PREFACTURA',
            'numero'        => $p->numero,
            'fecha'         => $p->fecha_emision?->format('d/m/Y'),
            'vencimiento'   => null,
            'estado'        => $p->estado,
            'empresa'       => $p->empresa,
            'cliente'       => $p->cliente,
            'vendedor'      => $p->usuario?->nombre,
            'detalles'      => $detalles,
            'subtotal15'    => round($subtotal15, 2),
            'subtotal0'     => round($subtotal0, 2),
            'descuento'     => round($descuento, 2),
            'iva'           => $iva,
            'total'         => (float) $p->total,
            'observaciones' => $p->observaciones,
            'abonos'        => $p->abonos->map(fn ($a) => [
                'fecha' => $a->fecha instanceof \DateTimeInterface ? $a->fecha->format('d/m/Y') : (string) $a->fecha,
                'forma' => $a->forma_pago,
                'valor' => (float) $a->valor,
            ])->all(),
            'abonado'       => (float) $p->total_abonado,
            'saldo'         => (float) $p->saldo_pendiente,
        ]);
    }

    private function linea(string $codigo, ?string $descripcion, float $cant, float $precio, float $pct, float $neto, float $total): array
    {
        return compact('codigo', 'descripcion', 'cant', 'precio', 'pct', 'neto', 'total');
    }

    private function render(array $doc)
    {
        $doc['logo'] = $this->logo($doc['empresa']);

        return Pdf::loadView('pdf.documento-venta', ['doc' => $doc])->setPaper('a4');
    }

    /** Logo como data URI: el de la empresa, el de Import si aplica, o el de Altamira. */
    public function logo(Empresa $empresa): ?string
    {
        $candidatos = [];
        if ($empresa->logo) {
            $candidatos[] = storage_path('app/public/' . ltrim($empresa->logo, '/'));
            $candidatos[] = public_path(ltrim($empresa->logo, '/'));
        }
        if (stripos($empresa->nombre_comercial . ' ' . $empresa->razon_social, 'import') !== false) {
            $candidatos[] = public_path('images/logo-import.png');
        }
        $candidatos[] = public_path('images/logo-altamira.png');

        foreach ($candidatos as $ruta) {
            if (is_file($ruta)) {
                $mime = mime_content_type($ruta) ?: 'image/png';
                return "data:{$mime};base64," . base64_encode(file_get_contents($ruta));
            }
        }

        return null;
    }
}
