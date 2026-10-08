<?php

namespace Omnibill\Request;

use Omnibill\Model\Addressing;

/**
 * Where a company receives its invoices: the directory's lines for a SIREN
 * (9 digits) or a SIRET (14). Result: a list of Addressing, empty when the
 * company is not in the directory.
 */
final class Lookup extends Request
{
    public function __construct(public readonly string $identifier)
    {
    }

    /** @return list<Addressing>|null */
    public function getAddressings(): ?array
    {
        return $this->getResult();
    }
}
