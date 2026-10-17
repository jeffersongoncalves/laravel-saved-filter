<?php

namespace JeffersonGoncalves\SavedFilter\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SavedFilterReportNotification extends Notification
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
            ->line(trans('saved-filter::saved-filter.mail.body', ['name' => $name, 'count' => $this->count]))
            ->attachData($this->content, $this->fileName);

        if ($this->url !== null) {
            $mailMessage->action(trans('saved-filter::saved-filter.mail.action'), $this->url);
        }

        if ($this->schedule !== null) {
            $mailMessage->line($this->schedule.' '.trans('saved-filter::saved-filter.mail.stop'));
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
}
