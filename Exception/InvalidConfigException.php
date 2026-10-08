<?php

namespace Omnibill\Exception;

/** A gateway misconfigured: an option missing, an unknown factory, an invoice or a status without what the gateway needs. */
final class InvalidConfigException extends \LogicException implements OmnibillException
{
}
