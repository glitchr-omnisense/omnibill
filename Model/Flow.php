<?php

namespace Omnibill\Model;

/**
 * One transmission on a gateway: an invoice, a status, a report, sent or
 * received. The platform's reference is what to keep, to ask about it
 * again.
 */
final readonly class Flow
{
    /**
     * @param string                 $reference     the gateway's name for it: a flow id, a deposit number, a message id
     * @param string|null            $trackingId    the application's own, where the platform keeps one
     * @param string|null            $invoiceNumber the invoice it is about
     * @param LifecycleStatus|null   $status        the invoice's latest status, as far as the gateway knows
     * @param list<StatusChange>     $history       the statuses known, oldest first
     * @param list<string>           $errors        why the platform rejected it
     * @param array<string, mixed>   $data          what else the platform answered
     */
    public function __construct(
        public string $reference,
        public FlowKind $kind = FlowKind::INVOICE,
        public Direction $direction = Direction::OUT,
        public FlowState $state = FlowState::PENDING,
        public ?string $trackingId = null,
        public ?string $invoiceNumber = null,
        public ?\DateTimeImmutable $submittedAt = null,
        public ?\DateTimeImmutable $updatedAt = null,
        public ?LifecycleStatus $status = null,
        public array $history = [],
        public array $errors = [],
        public ?string $name = null,
        public array $data = [],
    ) {
    }

    /** @param list<StatusChange>|null $history */
    public function with(?FlowState $state = null, ?LifecycleStatus $status = null, ?array $history = null, ?array $errors = null, ?\DateTimeImmutable $updatedAt = null, ?array $data = null): self
    {
        return new self($this->reference, $this->kind, $this->direction, $state ?? $this->state, $this->trackingId, $this->invoiceNumber, $this->submittedAt, $updatedAt ?? $this->updatedAt, $status ?? $this->status, $history ?? $this->history, $errors ?? $this->errors, $this->name, $data ?? $this->data);
    }
}
