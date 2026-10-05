<?php

namespace App\Domain\Notification\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GenericNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  string  $subjectLine
     * @param  string  $bodyContent
     * @param  string|null  $actionUrl
     * @param  string|null  $actionText
     * @param  string|null  $businessName
     */
    public function __construct(
        public string $subjectLine,
        public string $bodyContent,
        public ?string $actionUrl = null,
        public ?string $actionText = null,
        public ?string $businessName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.booking-notification',
            with: [
                'subjectLine' => $this->subjectLine,
                'bodyContent' => $this->bodyContent,
                'actionUrl' => $this->actionUrl,
                'actionText' => $this->actionText ?: 'Kelola Reservasi',
                'businessName' => $this->businessName ?: 'Aman Booking',
            ],
        );
    }
}
