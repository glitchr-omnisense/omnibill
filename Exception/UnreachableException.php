<?php

namespace Omnibill\Exception;

/**
 * The platform did not answer: no connection, a timeout, a server error, a
 * quota exceeded. Nothing was transmitted, nothing was decided: try again.
 */
final class UnreachableException extends ProviderException
{
}
