<?php

namespace Omnibill;

use Omnibill\Model\Contact;
use Omnibill\Model\ContactKind;
use Omnibill\Model\ContactList;

/**
 * The contacts contract (Capabilities::CONTACTS): the customers and the
 * suppliers of the accounting software. Each method is a shortcut for
 * execute() with its request (Request\Accounting\*).
 */
interface ContactsInterface
{
    /** Create it, or update it when it has its reference: it comes back with it. */
    public function saveContact(Contact $contact): Contact;

    /** A contact as the software has it now. */
    public function fetchContact(Contact $contact): Contact;

    /** A page of customers or of suppliers, maybe those whose name starts with $search. */
    public function contacts(ContactKind $kind, ?string $search = null, ?string $cursor = null, int $limit = 25): ContactList;
}
