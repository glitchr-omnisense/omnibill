<?php

namespace Omnibill\Exception;

/**
 * The platform did not answer: no connection, a timeout, a server error, a
 * quota exceeded. Nothing was transmitted, nothing was decided: try again.
 *
 * Since 1.2, a write (submit, setStatus, report, postEntry...) whose
 * request left before the answer failed - a timeout, a 5xx - is its
 * subclass OutcomeUnknownException: the provider may have taken it, so it is
 * not tried again blindly. An UnreachableException that is not one is a
 * write that never left (no connection, a name that did not resolve), or a
 * read, or a 429.
 */
class UnreachableException extends ProviderException
{
}
