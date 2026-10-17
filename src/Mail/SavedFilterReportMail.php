<?php

namespace JeffersonGoncalves\SavedFilter\Mail;

use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class SavedFilterReportMail extends Mailable
{
    /**
     * @param  string|null  $schedule  e.g. SavedFilterReport::describeSchedule(), null for a one-off send
     * @param  string|null  $url  where the view opens, shown as a button
     */
    public function __construct(
        public string $viewName,
        public int $count,
        public string $content,
        public string $fileName,
        public ?string $schedule = null,
        public ?string $url = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('saved-filter::saved-filter.mail.subject', ['name' => $this->viewName]));
    }

    public function content(): Content
    {
        // The view name comes from users and shared views reach other people's inboxes: no markdown links or formatting from it.
        $name = static::escapeMarkdown($this->viewName);

        return new Content(markdown: 'saved-filter::mail.report', with: [
            'title' => __('saved-filter::saved-filter.mail.subject', ['name' => $name]),
            'body' => __('saved-filter::saved-filter.mail.body', ['name' => $name, 'count' => $this->count]),
        ]);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->content, $this->fileName)];
    }

    /**
     * Backslash-escapes markdown punctuation. Leaves < > & alone: Blade's {{ }} already turns them into entities.
     */
    public static function escapeMarkdown(string $text): string
    {
        return (string) preg_replace('/[\\\\`*_{}\[\]()#+\-.!|~]/', '\\\\$0', $text);
    }
}
