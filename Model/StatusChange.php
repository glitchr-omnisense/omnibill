<?php

namespace Omnibill\Model;

/**
 * One step of an invoice's lifecycle: told by a platform, or declared by
 * the application (setStatus: "paid", "refused"). Where a platform carries
 * statuses as files (CDAR), the file is given: the family does not write
 * one (an application's Writer\LifecycleMessageWriterInterface does).
 *
 * Since 1.2, $amounts: for PAID (Encaissée, 212), the amount received by VAT
 * rate, the payment data the DGFiP lists for that status (impots.gouv.fr);
 * $amount stays the total.
 */
final readonly class StatusChange
{
    /**
     * @param string|null $code     the platform's own code, as it gave it
     * @param string|null $amount   for PAID or PARTIALLY_APPROVED, the amount, as a decimal string
     * @param string|null $file     the lifecycle message itself (CDAR), where the platform carries one
     * @param array<int, string> $amounts for PAID, the amount received at each VAT rate: the rate in basis points (2000 = 20 %, 550 = 5.5 %) => a decimal string
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
        public array $amounts = [],
    ) {
    }

    /** The same status with its lifecycle message, written by the application. */
    public function withFile(string $file, ?string $filename = null): self
    {
        return new self($this->status, $this->invoiceNumber, $this->at, $this->reason, $this->amount, $this->code, $file, $filename ?? $this->filename, $this->amounts);
    }
}
