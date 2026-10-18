<?php

namespace JeffersonGoncalves\SavedFilter;

use Carbon\CarbonInterface;

final readonly class ReportLink
{
    public function __construct(
        public string $url,
        // Null = the link doesn't expire.
        public ?CarbonInterface $expiresAt = null,
    ) {}
}
