<?php

declare(strict_types=1);

namespace App\Services\Push;

/**
 * One notification, already localised.
 *
 * Localisation happens BEFORE this object exists, not inside a channel. A channel that
 * translates is a channel that can silently send English to a Telugu user when a
 * translation is missing, and that failure is invisible to us and obvious to them.
 */
final readonly class PushMessage
{
    public function __construct(
        public string $type,
        public string $title,
        public string $body,
        public string $url,
        public string $locale,
        public ?string $imageUrl = null,
        /** WhatsApp requires a pre-approved template name; web push does not. */
        public ?string $whatsappTemplate = null,
        /** @var array<int, string> */
        public array $templateParams = [],
    ) {}

    /**
     * Truncate for the notification tray.
     *
     * Telugu strings run 15-30% longer than their English equivalent, so a body written to
     * fit in English overflows in Telugu. Cutting on a character count is crude but it is
     * the only thing that behaves the same in both scripts.
     */
    public function trimmedBody(int $limit = 120): string
    {
        return mb_strlen($this->body) <= $limit
            ? $this->body
            : mb_substr($this->body, 0, $limit - 1).'…';
    }
}
