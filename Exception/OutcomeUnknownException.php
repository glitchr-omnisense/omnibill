<?php

namespace Omnibill\Exception;

/**
 * A write left and its outcome is unknown: the request was sent (a POST,
 * a PUT, a PATCH, a DELETE, or a provider call the gateway knows writes)
 * and then the answer failed - a timeout, a connection lost, a 5xx. The
 * provider may have taken it: an invoice deposited, an entry posted.
 *
 * Not to be sent again as it is: look it up first, by the application's own
 * key - FlowLookupInterface::findFlow() with the trackingId given to
 * submit(), EntryLookupInterface::findEntry() with the entry and its key -
 * where the gateway supports it (supports(FindFlow::class),
 * supports(FindEntry::class)); otherwise ask a person to check on the
 * provider's side. Nothing in the family sends a write twice.
 *
 * It extends UnreachableException, so the catches written before 1.2 still
 * match it.
 */
final class OutcomeUnknownException extends UnreachableException
{
}
