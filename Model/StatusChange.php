<?php

namespace Omnibill\Model;

/**
 * One step of an invoice's lifecycle: told by a platform, or declared by
 * the application (setStatus: "paid", "refused"). Where a platform carries
 * statuses as files (CDAR), the file is given: the family does not write
 * one.
 */
final readonly class StatusChange
{
    /**
     * @param string|null $code     the platform's own code, as it gave it
     * @param string|null $amount   for PAID or PARTIALLY_APPROVED, the amount, as a decimal string
     * @param string|null $file     the lifecycle message itself (CDAR), where the platform carries one
     */
    public function __construct(
        public LifecycleStatus $status,
        public ?string $invoiceNumber = null,
        public ?\DateTimeImmutable $at = null,
        public ?string $reason = null,
        public ?string $amount = null,
        public ?string $code = null,
        public ?string $file = null,
        public ?string $filename = null,
    ) {
    }
}
