<?php

namespace Omnibill\Model;

/**
 * One thing a gateway received for the application: an invoice from a
 * supplier, or a status about an invoice - the flow it came in, and its
 * file.
 */
final readonly class Received
{
    public function __construct(
        public Flow $flow,
        public ?Invoice $invoice = null,
        public ?StatusChange $status = null,
    ) {
    }

    public function isInvoice(): bool
    {
        return null !== $this->invoice;
    }
}
