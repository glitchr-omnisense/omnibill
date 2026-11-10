<?php

namespace Omnibill\Model;

/**
 * A journal entry handed to accounting software: balanced lines on one
 * date, in one journal. It maps from omnibase/ledger's EntryDraft (or a
 * validated Entry) field by field: $journal the journal's code (VT, AC,
 * BQ, OD), $date, $label, $piece the supporting document's number, $key the
 * ledger's source ("notice:2026-10-U3"), each line's account number,
 * amounts in minor units, contact and VAT.
 *
 * Posted, it comes back with the software's $reference (to fetch it again)
 * and, once validated there, its $number and status.
 */
final readonly class JournalEntry
{
    /**
     * @param list<EntryLine>      $lines
     * @param string|null          $key       the application's handle on what the entry records (the ledger's source)
     * @param string|null          $reference the software's id, once posted
     * @param string|null          $number    the software's number for it, once validated
     * @param array<string, mixed> $data      the software's answer, as it came
     */
    public function __construct(
        public string $journal,
        public \DateTimeImmutable $date,
        public string $label,
        public array $lines = [],
        public ?string $piece = null,
        public ?string $key = null,
        public string $currency = 'EUR',
        public ?string $reference = null,
        public ?string $number = null,
        public EntryStatus $status = EntryStatus::DRAFT,
        public array $data = [],
    ) {
    }

    public function totalDebit(): int
    {
        return array_sum(array_map(static fn (EntryLine $l) => $l->debit, $this->lines));
    }

    public function totalCredit(): int
    {
        return array_sum(array_map(static fn (EntryLine $l) => $l->credit, $this->lines));
    }

    public function isBalanced(): bool
    {
        return [] !== $this->lines && $this->totalDebit() === $this->totalCredit();
    }

    /** The software's id; a LogicException before it was posted. */
    public function reference(): string
    {
        return $this->reference ?? throw new \LogicException('This entry was not posted: no reference yet.');
    }

    /**
     * A copy with what the software answered.
     *
     * @param list<EntryLine>|null      $lines
     * @param array<string, mixed>|null $data
     */
    public function with(?string $reference = null, ?string $number = null, ?EntryStatus $status = null, ?array $lines = null, ?string $piece = null, ?\DateTimeImmutable $date = null, ?string $journal = null, ?string $label = null, ?array $data = null): self
    {
        return new self($journal ?? $this->journal, $date ?? $this->date, $label ?? $this->label, $lines ?? $this->lines, $piece ?? $this->piece, $this->key, $this->currency,
            $reference ?? $this->reference, $number ?? $this->number, $status ?? $this->status, $data ?? $this->data);
    }
}
