<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\ContactKind;
use Omnibill\Model\ContactList;
use Omnibill\Request\Request;

/** The customers, or the suppliers, maybe those whose name starts with $search: a page of them. Result: the ContactList. */
final class ListContacts extends Request
{
    public function __construct(public readonly ContactKind $kind, public readonly ?string $search = null, public readonly ?string $cursor = null, public readonly int $limit = 25)
    {
    }

    public function getContacts(): ?ContactList
    {
        $result = $this->getResult();

        return $result instanceof ContactList ? $result : null;
    }
}
