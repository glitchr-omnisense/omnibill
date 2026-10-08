<?php

namespace Omnibill;

/** Builds a gateway from its options (a platform's address, a client and its secret, a mailer's sender...). */
interface GatewayFactoryInterface
{
    /** The name gateways are configured with: "email", "afnor", "chorus-pro"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
