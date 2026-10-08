<?php

namespace Omnibill\Model;

/**
 * What a platform told the application's webhook, its signature checked:
 * an event about one flow. Fetch or receive what it announces.
 */
final readonly class Notification
{
    /**
     * @param string|null          $id   the same for a callback sent twice: what to deduplicate on
     * @param array<string, mixed> $data the platform's payload
     */
    public function __construct(
        public Event $event,
        public ?Flow $flow = null,
        public ?\DateTimeImmutable $at = null,
        public ?string $id = null,
        public array $data = [],
    ) {
    }
}
