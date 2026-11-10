<?php

namespace Omnibill\Model;

/**
 * One line of a journal entry: an amount on the debit or the credit side
 * of an account (minor units, the other side zero), maybe for a contact,
 * maybe a VAT line - as omnibase/ledger's EntryLine.
 */
final readonly class EntryLine
{
    /**
     * @param string      $account the account's number: "512000", "706", or an auxiliary one ("411U3")
     * @param int         $debit   minor units (cents)
     * @param int         $credit  minor units (cents)
     * @param string|null $contact the contact the line is for: its key (the application's) or its reference (the provider's)
     */
    public function __construct(
        public string $account,
        public int $debit = 0,
        public int $credit = 0,
        public ?string $label = null,
        public ?string $contact = null,
        public ?Vat $vat = null,
    ) {
    }

    /** Debit minus credit. */
    public function net(): int
    {
        return $this->debit - $this->credit;
    }
}
