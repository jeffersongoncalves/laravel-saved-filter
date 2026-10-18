<?php

namespace JeffersonGoncalves\SavedFilter\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use JeffersonGoncalves\SavedFilter\ReportLink;

class SavedFilterReportNotification extends Notification
{
    /**
     * @param  string|null  $schedule  e.g. SavedFilterReport::describeSchedule(), null for a one-off send
     * @param  string|null  $url  where the view opens, shown as a button
     * @param  string|null  $unsubscribeUrl  turns the report off in one click
     * @param  bool  $tooLarge  the file is not attached: $download links to it, or the email only gives the count
     */
    public function __construct(
        public string $viewName,
        public int $count,
        public string $content,
        public string $fileName,
        public ?string $schedule = null,
        public ?string $url = null,
        public ?string $unsubscribeUrl = null,
        public bool $tooLarge = false,
        public ?ReportLink $download = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // The view name comes from users and shared views reach other people's inboxes: no markdown links or formatting from it.
        $name = static::escapeMarkdown($this->viewName);

        $mailMessage = (new MailMessage)
            ->subject(trans('saved-filter::saved-filter.mail.subject', ['name' => $this->viewName]))
            ->line(trans('saved-filter::saved-filter.mail.body', ['name' => $name, 'count' => $this->count]));

        if (! $this->tooLarge) {
            $mailMessage->attachData($this->content, $this->fileName);
        } else {
            $mailMessage->line(trans('saved-filter::saved-filter.mail.too_large', ['size' => static::formatSize(strlen($this->content))]));
        }

        if ($this->download !== null) {
            // One button per MailMessage: the download takes it, the view becomes a plain link.
            $mailMessage->action(trans('saved-filter::saved-filter.mail.download'), $this->download->url);

            if ($this->download->expiresAt !== null) {
                $mailMessage->line(trans('saved-filter::saved-filter.mail.expires', [
                    'date' => $this->download->expiresAt->locale(app()->getLocale())->isoFormat('LLL'),
                ]));
            }

            if ($this->url !== null) {
                $mailMessage->line(static::link(trans('saved-filter::saved-filter.mail.action'), $this->url));
            }
        } elseif ($this->url !== null) {
            $mailMessage->action(trans('saved-filter::saved-filter.mail.action'), $this->url);
        }

        if ($this->schedule !== null) {
            $mailMessage->line($this->schedule.' '.($this->unsubscribeUrl === null
                ? trans('saved-filter::saved-filter.mail.stop')
                : static::link(trans('saved-filter::saved-filter.mail.unsubscribe'), $this->unsubscribeUrl)));
        }

        return $mailMessage;
    }

    /**
     * Backslash-escapes markdown punctuation. Leaves < > & alone: the mail template already turns them into entities.
     */
    public static function escapeMarkdown(string $text): string
    {
        return (string) preg_replace('/[\\\\`*_{}\[\]()#+\-.!|~]/', '\\\\$0', $text);
    }

    /**
     * A markdown link. Parentheses and spaces are percent-encoded so the URL stays in one piece
     * (<url> doesn't work: the template turns < > into entities before the markdown runs).
     */
    protected static function link(string $text, string $url): string
    {
        return '['.static::escapeMarkdown($text).']('.strtr($url, ['(' => '%28', ')' => '%29', ' ' => '%20']).')';
    }

    protected static function formatSize(int $bytes): string
    {
        return $bytes >= 1024 * 1024 ? round($bytes / 1024 / 1024, 1).' MB' : max(1, (int) round($bytes / 1024)).' KB';
    }
}
