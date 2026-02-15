<?php

namespace App\Mail;

use App\Models\SearchRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SearchRequestSharedMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public SearchRequest $searchRequest,
        public User $recipient,
        public User $sender
    ) {
    }

    public function envelope(): Envelope
    {
        $officeName = $this->sender->organization?->name ?: 'onbekend kantoor';

        return new Envelope(
            subject: 'Nieuwe zoekvraag: '.$this->searchRequest->title,
            from: new Address(
                config('mail.from.address'),
                trim($this->sender->name.', '.$officeName, ' ,')
            )
        );
    }

    public function content(): Content
    {
        $officeName = $this->sender->organization?->name ?: '-';
        $senderName = $this->sender->name ?: '-';
        $logoUrl = $this->sender->organization?->logo_url;
        $senderAvatarUrl = $this->sender->avatar_url;
        $senderPhone = $this->sender->phone ?: ($this->sender->organization?->phone ?: '-');
        $senderEmail = $this->sender->email ?: ($this->sender->organization?->email ?: '-');

        if ($logoUrl && ! str_starts_with($logoUrl, 'http://') && ! str_starts_with($logoUrl, 'https://')) {
            $logoUrl = url($logoUrl);
        }
        if ($senderAvatarUrl && ! str_starts_with($senderAvatarUrl, 'http://') && ! str_starts_with($senderAvatarUrl, 'https://')) {
            $senderAvatarUrl = url($senderAvatarUrl);
        }

        return new Content(
            markdown: 'emails.search-request-shared',
            with: [
                'officeName' => $officeName,
                'senderName' => $senderName,
                'mailHeaderLogoUrl' => $logoUrl,
                'mailHeaderLogoAlt' => $officeName,
                'senderAvatarUrl' => $senderAvatarUrl,
                'senderPhone' => $senderPhone,
                'senderEmail' => $senderEmail,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
