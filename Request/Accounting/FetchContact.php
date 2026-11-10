<?php

namespace Omnibill\Request\Accounting;

use Omnibill\Model\Contact;
use Omnibill\Request\Request;

/** A contact as the software has it now. Result: the Contact. */
final class FetchContact extends Request
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
