<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\EntryList;
use Omnibill\Request\Request;

/** The entries between two dates, in a journal: a page of them, lines left out (fetch one for its lines). Result: the EntryList. */
final class ListEntries extends Request
{
    public function __construct(public readonly ?\DateTimeImmutable $from = null, public readonly ?\DateTimeImmutable $to = null, public readonly ?string $journal = null, public readonly ?string $cursor = null, public readonly int $limit = 25)
    {
    }

    public function getEntries(): ?EntryList
    {
        $result = $this->getResult();

        return $result instanceof EntryList ? $result : null;
    }
}
