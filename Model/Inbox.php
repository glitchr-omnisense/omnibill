<?php

namespace Omnibill\Model;

/**
 * What receive() found, and where to start next time: keep the cursor and
 * give it back to the next receive().
 */
final readonly class Inbox
{
    /** @param list<Received> $items */
    public function __construct(
        public array $items = [],
        public ?string $cursor = null,
        public bool $more = false,
    ) {
    }

    /**
     * The same inbox with only the flows of these kinds, its cursor kept.
     *
     * @param list<FlowKind> $kinds
     */
    public function only(array $kinds): self
    {
        return new self(array_values(array_filter($this->items, static fn (Received $r) => \in_array($r->flow->kind, $kinds, true))), $this->cursor, $this->more);
    }

    /** @return list<Invoice> */
    public function invoices(): array
    {
        return array_values(array_filter(array_map(static fn (Received $r) => $r->invoice, $this->items)));
    }

    /** @return list<StatusChange> */
    public function statuses(): array
    {
        return array_values(array_filter(array_map(static fn (Received $r) => $r->status, $this->items)));
    }
}
