<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\Contact;
use Omnibill\Request\Request;

/** Create a customer or a supplier, or update it when it has its reference. Result: the Contact with its reference. */
final class SaveContact extends Request
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
