<?php

namespace Omnibill\Request;

use Omnibill\Model\EReport;
use Omnibill\Model\Flow;

/** Transmit an e-reporting file. Result: its Flow. */
final class Report extends Request
{
    public function __construct(public readonly EReport $report, public readonly ?string $trackingId = null)
    {
    }

    public function getFlow(): ?Flow
    {
        return $this->getResult();
    }
}
