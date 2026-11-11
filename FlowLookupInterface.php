<?php

namespace Omnibill;

use Omnibill\Model\Flow;

/**
 * Since 1.2: a flow found again by the application's own key, the
 * trackingId it gave submit() or report() - what to ask after an
 * OutcomeUnknownException, before sending again. A gateway whose platform
 * cannot search by it throws NotSupportedException: supports(Request\FindFlow::class)
 * says so beforehand.
 */
interface FlowLookupInterface
{
    /** The flow the application sent with that trackingId; null when the platform has none. */
    public function findFlow(string $trackingId): ?Flow;
}
