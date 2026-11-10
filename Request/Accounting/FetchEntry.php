<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\JournalEntry;
use Omnibill\Request\Request;

/** A posted entry as the software has it now, lines included. Result: the JournalEntry. */
final class FetchEntry extends Request
{
    public function __construct(public readonly JournalEntry $entry)
    {
    }

    public function getEntry(): ?JournalEntry
    {
        $result = $this->getResult();

        return $result instanceof JournalEntry ? $result : null;
    }
}
