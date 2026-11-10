<?php

namespace Omnibill\Model;

/** A page of contacts; $cursor asks for the next one, null when none. */
final readonly class ContactList
{
    /** @param list<Contact> $contacts */
    public function __construct(public array $contacts = [], public ?string $cursor = null)
    {
    }
}
