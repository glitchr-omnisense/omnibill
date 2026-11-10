<?php

namespace Omnibill\Model;

/** A postal address, as accounting software asks it of a contact. */
final readonly class Address
{
    /** @param string $country ISO 3166-1 alpha-2 */
    public function __construct(
        public string $line,
        public string $postalCode,
        public string $city,
        public string $country = 'FR',
    ) {
    }
}
