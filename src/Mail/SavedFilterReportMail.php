<?php

namespace JeffersonGoncalves\SavedFilter\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SavedFilterReportMail extends Mailable
{
    public function __construct(
        public string $viewName,
        public int $count,
        public string $content,
        public string $fileName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('saved-filter::saved-filter.mail.subject', ['name' => $this->viewName]));
    }

    public function content(): Content
    {
        return new Content(htmlString: e(__('saved-filter::saved-filter.mail.body', ['name' => $this->viewName, 'count' => $this->count])));
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->content, $this->fileName)];
    }
}
