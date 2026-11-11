<?php

namespace Omnibill;

use Omnibill\Model\Contact;

/**
 * Since 1.3: a customer or a supplier found again by the application's own
 * key (Contact::$key, the ledger party's reference) - what to ask after an
 * OutcomeUnknownException on saveContact(), before creating it again: a
 * contact created twice is a duplicate third party in the books. A gateway
 * whose software cannot be searched for it throws NotSupportedException:
 * supports(Request\Accounting\FindContact::class) says so beforehand. Each
 * gateway says in its documentation what it matches on.
 */
interface ContactLookupInterface
{
    /**
     * The contact as the software has it, with its reference, looked up
     * from the one handed to saveContact() (its kind and key); null when it
     * has none.
     */
    public function findContact(Contact $contact): ?Contact;
}
