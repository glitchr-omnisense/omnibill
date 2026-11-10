<?php

namespace Omnibill;

use Omnibill\Model\EntryList;
use Omnibill\Model\JournalEntry;

/**
 * The journal-entries contract (Capabilities::JOURNAL_ENTRIES): entries
 * handed to accounting software - the journal, the accounts, the amounts
 * and the VAT - and read back. Each method is a shortcut for execute() with
 * its request (Request\Accounting\*); a gateway that keeps no books throws
 * NotSupportedException.
 *
 * The application keeps its own books (omnibase/ledger); the gateway hands
 * them on, to the accountant's software, and keeps nothing.
 */
interface JournalEntriesInterface
{
    /** Post the entry: it comes back with its reference on the software, to keep. */
    public function postEntry(JournalEntry $entry): JournalEntry;

    /** A posted entry as the software has it now. */
    public function fetchEntry(JournalEntry $entry): JournalEntry;

    /** A page of entries, lines left out. */
    public function entries(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $journal = null, ?string $cursor = null, int $limit = 25): EntryList;
}
