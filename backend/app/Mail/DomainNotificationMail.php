<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DomainNotificationMail extends Mailable
{
    public function __construct(public string $kind, public array $payload) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'QSL Nexus · '.match ($this->kind) {
            'candidate' => 'Nueva coincidencia candidata', 'confirmed' => 'Contacto confirmado', 'qsl' => 'Tarjeta QSL emitida', 'disputed' => 'Contacto en disputa', default => 'Actualización de tu cuenta'
        });
    }

    public function content(): Content
    {
        return new Content(view: 'mail.domain-notification');
    }
}
