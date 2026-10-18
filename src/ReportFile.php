<?php

namespace JeffersonGoncalves\SavedFilter;

final readonly class ReportFile
{
    public function __construct(
        public string $content,
        public string $fileName,
        public int $count,
        // Where the view opens, shown as a button in the email.
        public ?string $url = null,
        // Turns the report off in one click (e.g. a signed route), shown as a link in the email.
        public ?string $unsubscribeUrl = null,
    ) {}
}
