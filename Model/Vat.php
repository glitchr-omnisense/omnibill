<?php

namespace Omnibill\Model;

/**
 * The VAT of an entry line: its rate in basis points (2000 = 20 %), what it
 * is, the base it was computed on (minor units) and the book's code for it.
 * On a VAT line (44571, 44566...) it says which tax and on what base; on an
 * exempt operation's base line, that the base is exempt - as
 * omnibase/ledger's EntryLine::$tax and $taxBase.
 */
final readonly class Vat
{
    /** @param int|null $base the amount the VAT was computed on, in minor units */
    public function __construct(
        public int $rate,
        public VatKind $kind = VatKind::COLLECTED,
        public ?int $base = null,
        public ?string $code = null,
    ) {
    }

    /** The rate as a decimal percentage: "20", "5.5", "2.1". */
    public function percent(): string
    {
        return rtrim(rtrim(number_format($this->rate / 100, 2, '.', ''), '0'), '.');
    }
}
