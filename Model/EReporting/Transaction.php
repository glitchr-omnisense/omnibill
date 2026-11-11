<?php

namespace Omnibill\Model\EReporting;

use Omnibill\Model\Party;

/**
 * One row of transaction data: a day's sales to private persons
 * (aggregated: no invoice number, no counterparty) or one invoice with a
 * company abroad - its date, its amounts by VAT rate, the application's
 * category of the operation (goods, services... as the application names
 * it; the writer maps it to the format's code).
 */
final readonly class Transaction
{
    /**
     * @param list<RateTotal>      $totals
     * @param array<string, mixed> $data   whatever else the writer needs, the application's
     */
    public function __construct(
        public TransactionKind $kind,
        public \DateTimeImmutable $date,
        public array $totals,
        public ?string $category = null,
        public ?string $invoiceNumber = null,
        public ?Party $counterparty = null,
        public string $currency = 'EUR',
        public array $data = [],
    ) {
    }

    public function base(): int
    {
        return array_sum(array_map(static fn (RateTotal $t) => $t->base, $this->totals));
    }

    public function vat(): int
    {
        return array_sum(array_map(static fn (RateTotal $t) => $t->vat, $this->totals));
    }
}
