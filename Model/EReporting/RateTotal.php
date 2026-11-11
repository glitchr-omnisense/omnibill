<?php

namespace Omnibill\Model\EReporting;

/** The amounts at one VAT rate: the taxable base and the VAT, in minor units. */
final readonly class RateTotal
{
    /** @param int $rate basis points: 2000 = 20 %, 550 = 5.5 %, 0 for an exempt base */
    public function __construct(
        public int $rate,
        public int $base,
        public int $vat = 0,
    ) {
    }
}
