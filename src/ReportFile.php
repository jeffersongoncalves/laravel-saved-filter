<?php

namespace JeffersonGoncalves\SavedFilter;

final readonly class ReportFile
{
    public function __construct(
        public string $content,
        public string $fileName,
        public int $count,
    ) {}
}
