<?php

namespace Omnibill;

use Omnibill\Model\JournalEntry;

/**
 * Since 1.2: a posted entry found again by the application's own key
 * (JournalEntry::$key, the ledger's source) - what to ask after an
 * OutcomeUnknownException, before posting again. A gateway whose software
 * cannot be searched for it throws NotSupportedException:
 * supports(Request\Accounting\FindEntry::class) says so beforehand. Each
 * gateway says in its documentation what it matches on.
 */
interface EntryLookupInterface
{
    /**
     * The entry as the software has it, looked up from the one handed to
     * postEntry() (its key, journal, date, piece); null when it has none.
     */
    public function findEntry(JournalEntry $entry): ?JournalEntry;
}
