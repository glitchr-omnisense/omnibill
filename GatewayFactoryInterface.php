<?php

namespace Omnibill;

use Omnisense\Contracts\Provider\ProviderFactoryInterface;

/** Builds a gateway from its options (a platform's address, a client and its secret, a mailer's sender...): omnisense/contracts' provider factory. */
interface GatewayFactoryInterface extends ProviderFactoryInterface
{
    /** The name gateways are configured with: "email", "afnor", "chorus-pro"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
