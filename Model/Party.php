<?php

namespace Omnibill\Model;

/**
 * A company on an invoice: who issues it, who receives it. Identifiers
 * only - a name for the people who read it, an e-mail for the gateway that
 * sends by e-mail.
 */
final readonly class Party
{
    /**
     * @param string|null $siren             the legal unit (9 digits), France
     * @param string|null $siret             the establishment (14 digits), France
     * @param string|null $electronicAddress the electronic invoicing address the directory gives (addressing identifier)
     * @param string|null $routingCode       a service, a cost centre: the routing code under the SIRET
     * @param string|null $email             where an e-mail gateway sends
     * @param string|null $country           ISO 3166-1 alpha-2
     */
    public function __construct(
        public ?string $name = null,
        public ?string $siren = null,
        public ?string $siret = null,
        public ?string $electronicAddress = null,
        public ?string $routingCode = null,
        public ?string $email = null,
        public ?string $country = 'FR',
    ) {
    }
}
