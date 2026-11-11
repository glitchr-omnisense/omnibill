<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\JournalEntry;
use Omnibill\Request\Request;

/**
 * Find a posted entry again by the application's own key: the entry as it
 * was handed to postEntry() - its $key (the ledger's source), its journal,
 * date and piece, which the software is asked on. Asked before posting
 * again what ended in an OutcomeUnknownException. Result: the JournalEntry
 * as the software has it, or null when it has none - an answer too.
 */
final class FindEntry extends Request
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
