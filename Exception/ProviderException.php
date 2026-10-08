<?php

namespace Omnibill\Exception;

/** The platform refused the request, or answered with an error. */
class ProviderException extends \RuntimeException implements OmnibillException
{
    public function __construct(
        public readonly string $provider,
        string $message,
        /** The provider's own error code, when it gave one */
        public readonly ?string $providerCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(\sprintf('[%s] %s', $provider, $message), 0, $previous);
    }
}
