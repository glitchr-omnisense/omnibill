<?php

namespace Omnibill\Model;

/**
 * Where a company receives its invoices, as a directory answers: a line of
 * the national directory - a SIREN, maybe a SIRET and a routing code, the
 * electronic address to send to, the kind of platform behind it.
 */
final readonly class Addressing
{
    /**
     * @param string|null $platform WK: an approved platform (PA); DFH: the public portal (PPF), as the directory codes them
     * @param string|null $status   Enabled, Disabled, Upcoming
     * @param array<string, mixed> $data
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
    ) {
    }

    public function isEnabled(): bool
    {
        return null === $this->status || 'Enabled' === $this->status;
    }
}
