<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\JournalEntry;
use Omnibill\Request\Request;

/** Post a journal entry to the accounting software: its journal, date, label, lines (accounts, amounts, contacts, VAT). Result: the JournalEntry with its reference - and its number, posted, where the software validates at once. */
final class PostEntry extends Request
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
