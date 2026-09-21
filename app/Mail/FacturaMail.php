<?php

namespace App\Mail;

use App\Models\Factura;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class FacturaMail extends Mailable
{
    public function __construct(
        public Factura $factura,
        private string $pdf,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Factura {$this->factura->numero_completo} — {$this->factura->empresa->razon_social}",
        );
    }

    public function content(): Content
    {
        return new Content(htmlString:
            '<p>Estimado(a) ' . e($this->factura->razon_social) . ',</p>' .
            '<p>Adjuntamos su factura <strong>' . e($this->factura->numero_completo) . '</strong> ' .
            'por un total de <strong>$' . number_format((float) $this->factura->total, 2) . '</strong>.</p>' .
            '<p>Gracias por su compra.<br>' . e($this->factura->empresa->razon_social) . '</p>'
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdf, "Factura-{$this->factura->numero_completo}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
