<?php

namespace Omnibill\Model;

/**
 * Where a company receives its invoices, as a directory answers: a line of
 * the national directory - a SIREN, maybe a SIRET and a routing code, the
 * electronic address to send to, the kind of platform behind it.
 *
 * Since 1.2, $scheme: the identification scheme of the electronic address
 * (its schemeID, BT-49's and BT-34's), as the directory gives it or 0225 -
 * "FRCTC ELECTRONIC ADDRESS" in the EAS code list (docs.peppol.eu, read
 * 2026-11-11) - when it gives none, as XP Z12-013's Directory Service 1.3.0
 * does not.
 */
final readonly class Addressing
{
    /** The scheme of the French directory's electronic addresses (EAS 0225). */
    public const FRENCH_DIRECTORY = '0225';

    /**
     * @param string|null $platform WK: an approved platform (PA); DFH: the public portal (PPF), as the directory codes them
     * @param string|null $status   Enabled, Disabled, Upcoming
     * @param array<string, mixed> $data
     * @param string      $scheme   the electronic address's identification scheme (EAS): 0225 for the French directory's
     */
    public function __construct(
        public string $siren,
        public ?string $siret = null,
        public ?string $routingCode = null,
        public ?string $electronicAddress = null,
        public ?string $name = null,
        public ?string $platform = null,
        public ?string $status = null,
        public array $data = [],
        public string $scheme = self::FRENCH_DIRECTORY,
    ) {
    }

    public function isEnabled(): bool
    {
        return null === $this->status || 'Enabled' === $this->status;
    }
}
