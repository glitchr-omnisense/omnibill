<?php

namespace Omnibill\Request;

use Omnibill\Model\Flow;
use Omnibill\Model\StatusChange;

/**
 * Declare a status: a supplier's invoice refused or approved, one's own
 * invoice paid. Result: the Flow that carried it, or the invoice's, updated.
 *
 * Since 1.3, $trackingId: the application's own key for the message that
 * carries the status, where a platform sends it as a flow of its own (afnor:
 * the lifecycle message's flow) - findFlow($trackingId) finds that flow
 * again after an OutcomeUnknownException, as it finds an invoice.
 */
final class SetStatus extends Request
{
    public function __construct(public readonly StatusChange $change, public readonly ?Flow $flow = null, public readonly ?string $trackingId = null)
    {
    }

    public function getFlow(): ?Flow
    {
        $result = $this->getResult();

        return $result instanceof Flow ? $result : null;
    }
}
