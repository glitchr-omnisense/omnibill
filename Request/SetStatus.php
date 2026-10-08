<?php

namespace Omnibill\Request;

use Omnibill\Model\Flow;
use Omnibill\Model\StatusChange;

/**
 * Declare a status: a supplier's invoice refused or approved, one's own
 * invoice paid. Result: the Flow that carried it, or the invoice's, updated.
 */
final class SetStatus extends Request
{
    public function __construct(public readonly StatusChange $change, public readonly ?Flow $flow = null)
    {
    }

    public function getFlow(): ?Flow
    {
        return $this->getResult();
    }
}
