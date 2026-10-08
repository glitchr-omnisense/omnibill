<?php

namespace Omnibill\Exception;

use Omnibill\Request\Request;

/**
 * The gateway does not do that: no directory behind an e-mail, no inbox for
 * a supplier on Chorus Pro. supports() says so beforehand.
 */
final class NotSupportedException extends \LogicException implements OmnibillException
{
    public static function for(Request $request, string $gateway): self
    {
        return new self(\sprintf('The "%s" gateway does not support %s.', $gateway, (new \ReflectionClass($request))->getShortName()));
    }
}
