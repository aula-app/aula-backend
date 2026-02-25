<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantStatisticsReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private readonly string $csvContent,
        private readonly string $filename,
        private readonly int $tenantCount,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tenant Statistics Report — '.now()->format('Y-m-d'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.tenant-statistics-report',
            with: [
                'tenantCount' => $this->tenantCount,
                'filename' => $this->filename,
            ],
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->csvContent, $this->filename)
                ->withMime('text/csv'),
        ];
    }
}
