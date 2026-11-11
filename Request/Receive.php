<?php

namespace Omnibill\Request;

use Omnibill\Model\FlowKind;
use Omnibill\Model\Inbox;

/**
 * What arrived for the application since the cursor given: invoices from
 * suppliers, statuses about its own invoices. Result: an Inbox, with the
 * cursor to give next time.
 *
 * Since 1.2, $kinds narrows it: [FlowKind::LIFECYCLE] for the statuses
 * only, [FlowKind::INVOICE] for the invoices only - so that two readers of
 * the same inbox (the suppliers' invoices, the statuses of one's own) each
 * take their part, with a cursor each. A platform that filters its search
 * (afnor: flowType) downloads only what matches; otherwise the gateway
 * leaves the rest out of the Inbox. A cursor belongs to the kinds it was
 * given with: keep one cursor per set of kinds.
 */
final class Receive extends Request
{
    /** @param list<FlowKind> $kinds the kinds wanted; none: every flow */
    public function __construct(public readonly ?string $cursor = null, public readonly int $limit = 25, public readonly array $kinds = [])
    {
    }

    /** Whether a flow of this kind is wanted. */
    public function wants(FlowKind $kind): bool
    {
        return [] === $this->kinds || \in_array($kind, $this->kinds, true);
    }

    public function getInbox(): ?Inbox
    {
        $result = $this->getResult();

        return $result instanceof Inbox ? $result : null;
    }
}
