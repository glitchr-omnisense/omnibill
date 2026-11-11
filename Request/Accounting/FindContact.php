<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\Contact;
use Omnibill\Request\Request;

/**
 * Find a customer or a supplier again by the application's own key: the
 * contact as it was handed to saveContact() - its kind and its $key (the
 * ledger party's reference), which the software is asked on. Asked before
 * saving again what ended in an OutcomeUnknownException. Result: the
 * Contact as the software has it, with its reference, or null when it has
 * none - an answer too.
 */
final class FindContact extends Request
{
    public function __construct(public readonly Contact $contact)
    {
    }

    public function getContact(): ?Contact
    {
        $result = $this->getResult();

        return $result instanceof Contact ? $result : null;
    }
}
