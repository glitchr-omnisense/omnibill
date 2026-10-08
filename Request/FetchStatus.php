<?php

namespace Omnibill\Request;

use Omnibill\Model\Flow;

/** Where a flow stands now: the platform's acknowledgement, the invoice's status. Result: the Flow, updated. */
final class FetchStatus extends Request
{
    public function __construct(public readonly Flow $flow)
    {
    }

    public function getFlow(): ?Flow
    {
        return $this->getResult();
    }
}
