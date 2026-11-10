<?php

namespace Omnibill\Model;

/** A page of journal entries; $cursor asks for the next one, null when none. */
final readonly class EntryList
{
    /** @param list<JournalEntry> $entries */
    public function __construct(public array $entries = [], public ?string $cursor = null)
    {
    }
}
