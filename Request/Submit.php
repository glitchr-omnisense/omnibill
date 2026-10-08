<?php

namespace Omnibill\Request;

use Omnibill\Model\Flow;
use Omnibill\Model\Invoice;

/** Transmit an invoice. Result: its Flow, PENDING until the platform checked it. */
final class Submit extends Request
{
    public function __construct(public readonly Invoice $invoice, public readonly ?string $trackingId = null)
    {
    }

    public function getFlow(): ?Flow
    {
        return $this->getResult();
    }
}
