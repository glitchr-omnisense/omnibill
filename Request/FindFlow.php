<?php

namespace Omnibill\Request;

use Omnibill\Model\Direction;
use Omnibill\Model\Flow;

/**
 * Find a flow again by the application's own key: the trackingId given to
 * submit() or report(). Asked before sending again what ended in an
 * OutcomeUnknownException. Result: the Flow, or null when the platform has
 * none with that key - an answer too (isAnswered() is true).
 */
final class FindFlow extends Request
{
    public function __construct(public readonly string $trackingId, public readonly Direction $direction = Direction::OUT)
    {
    }

    public function getFlow(): ?Flow
    {
        $result = $this->getResult();

        return $result instanceof Flow ? $result : null;
    }
}
