<?php

namespace Omnibill\Model\EReporting;

/**
 * One row of payment data: money received for services (where VAT is due
 * on receipt), on a date, by VAT rate - an invoice's, or a day's sales to
 * private persons together.
 */
final readonly class Payment
{
    /**
     * @param array<int, int>      $amounts the amount received at each VAT rate: basis points => minor units
     * @param array<string, mixed> $data    whatever else the writer needs, the application's
     */
    public function __construct(
        public TransactionKind $kind,
        public \DateTimeImmutable $date,
        public array $amounts,
        public ?string $invoiceNumber = null,
        public string $currency = 'EUR',
        public array $data = [],
    ) {
    }

    public function total(): int
    {
        return array_sum($this->amounts);
    }
}
