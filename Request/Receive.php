<?php

namespace Omnibill\Request;

use Omnibill\Model\Inbox;

/**
 * What arrived for the application since the cursor given: invoices from
 * suppliers, statuses about its own invoices. Result: an Inbox, with the
 * cursor to give next time.
 */
final class Receive extends Request
{
    public function __construct(public readonly ?string $cursor = null, public readonly int $limit = 25)
    {
    }

    public function getInbox(): ?Inbox
    {
        return $this->getResult();
    }
}
