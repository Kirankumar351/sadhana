<?php

declare(strict_types=1);

namespace App\Services\Ingestion;

/**
 * What one scraper run produced.
 *
 * `errors` is a list rather than a flag because the useful signal is usually partial: a
 * run that found 12 items and failed on 2 is healthy, and one that found 12 and failed on
 * 11 is a layout change. Collapsing both to "failed" loses the difference.
 */
final readonly class ScrapeResult
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(
        public int $found = 0,
        public int $created = 0,
        public array $errors = [],
        public bool $failed = false,
    ) {}

    public function summary(): string
    {
        if ($this->failed) {
            return 'failed: '.($this->errors[0] ?? 'unknown error');
        }

        $line = "found {$this->found}, queued {$this->created} for review";

        return $this->errors === []
            ? $line
            : $line.', '.count($this->errors).' items could not be parsed';
    }
}
