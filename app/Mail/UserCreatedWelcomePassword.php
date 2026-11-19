<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class UserCreatedWelcomePassword extends Mailable {

    public function __construct(
        public readonly string $name,
        public readonly string $username,
        public readonly string $secretLink,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            // TODO: i18n?
            // TODO see TODO in config/aula.php re: templates
            from: new Address(
                config('aula.email.from.address'),
                config('aula.email.from.name'),
            ),
            replyTo: [new Address(
                config('aula.email.reply_to.address')
            )],
            subject: config('aula.email.template.creation.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            // TODO: see TODO in config/aula.php re: templates
            view: 'mail.user-created-welcome-password',
        );
    }

}
