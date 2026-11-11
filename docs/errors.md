---
title: Errors
order: 50
---

# Errors

Every exception implements `Omnibill\Exception\OmnibillException` (an interface that extends
`\Throwable`), except the `\InvalidArgumentException` of `Invoice::fromFile()` for a file it cannot
read.

| Exception | Parent | Thrown when |
|---|---|---|
| `InvalidConfigException` | `\LogicException` | a gateway not configured, a factory not installed, a required option missing; a factory without its mailer or its capabilities; an invoice or a status without what the gateway needs (a buyer's e-mail, a number, a CDAR file, a flow) |
| `NotSupportedException` | `\LogicException` | the gateway does not do that request: `supports()` says so beforehand |
| `ProviderException` | `\RuntimeException` | the platform refused the request or answered with an error; an action left a request unanswered |
| `InvalidKeyException` | `ProviderException` | the platform refused the application's credentials (the token endpoint's 400, 401, 403; an API's 401 after one token renewal, or 403) |
| `UnreachableException` | `ProviderException` | the platform did not answer: no connection, a timeout, a 5xx, a 429; the mailer could not send. Nothing was transmitted: try again |
| `InvalidNotificationException` | `ProviderException` | a webhook's call that does not hold: no key configured, a header missing, too old, a wrong signature, not JSON |

`ProviderException` and its children carry `provider` (`string`: `afnor`, `chorus-pro`, `email`) and
`providerCode` (`?string`: the platform's own code - XP Z12-013's `errorCode`, Chorus Pro's
`codeRetour`, the HTTP status - or null); the message is `[<provider>] <message>`. There is no
HTTP status property: it is in the message, and in `providerCode` when the platform gave no code.

## By gateway

| | `afnor` | `chorus-pro` | `email` |
|---|---|---|---|
| token endpoint 400, 401, 403 | `InvalidKeyException` | `InvalidKeyException` | - |
| 401 | the token renewed once, then `InvalidKeyException` | the same | - |
| 403 | `InvalidKeyException` | `InvalidKeyException` | - |
| other 4xx | `ProviderException` (`HTTP 422: <errorCode> <errorMessage> - <details>`, `providerCode` the `errorCode`) | `ProviderException` | - |
| `codeRetour` not 0 | - | `ProviderException` (`/v1/deposer/flux answered 20001: GDP_MSG_11.022`, `providerCode` the code) | - |
| 5xx, 429, no answer | `UnreachableException` | `UnreachableException` | the mailer's transport error: `UnreachableException` |
| a body that is not JSON | `ProviderException` | `ProviderException` | - |

Error messages carry at most 200 characters of a body that is not the platform's JSON error.

## What to catch

```php
use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\InvalidKeyException;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Exception\OutcomeUnknownException;
use Omnibill\Exception\ProviderException;
use Omnibill\Exception\UnreachableException;

try {
    $flow = $gateway->submit($invoice);
} catch (OutcomeUnknownException) {
    // since 1.2: the request left, the answer failed (a timeout, a 5xx): the platform may have
    // taken it - never resend blindly; findFlow($trackingId) first (below)
} catch (UnreachableException) {
    // nothing transmitted: retry later (a delayed message)
} catch (InvalidKeyException) {
    // the credentials are refused: an administrator's problem, not the invoice's
} catch (ProviderException $e) {
    // the platform refused this invoice: $e->providerCode, $e->getMessage()
} catch (InvalidConfigException|NotSupportedException $e) {
    // a configuration error, or the invoice lacks what this gateway needs
}
```

## A write whose outcome is unknown (since 1.2)

`OutcomeUnknownException` extends `UnreachableException` (the catches written before 1.2 still
match it). `Http\Answer::send()` throws it for a **write** - a POST, PUT, PATCH or DELETE, or a call
the gateway says writes (Odoo's `create`, `action_post`; Chorus Pro's `deposer/flux`) - when the
answer failed after the request may have left: a timeout, a connection lost, a 5xx. A write that
never left (the host did not resolve, the connection was refused, no TLS session), a read (a GET, a
search sent as a POST, a token request) and a 429 stay plain `UnreachableException`s: nothing was
taken, try again.

After an `OutcomeUnknownException`, look the write up by the application's own key before sending
it again - nothing in the family sends a write twice:

| | Look-up | Gateways |
|---|---|---|
| `submit($invoice, $trackingId)`, `report()`, `setStatus($change, $flow, $trackingId)` (since 1.3) | `findFlow($trackingId)` (`FlowLookupInterface`, `Request\FindFlow`): the flow, or `null` | `afnor` (`POST /v1/flows/search`, `where.trackingId`, `flowDirection: [Out]`; a status is its lifecycle message's flow) |
| `postEntry($entry)` | `findEntry($entry)` (`EntryLookupInterface`, `Request\Accounting\FindEntry`): the entry as the software has it, or `null` | `odoo` (the key in `ref` or the narration), `pennylane` (date, journal, label and piece: it keeps no key) |
| `saveContact($contact)` (since 1.3) | `findContact($contact)` (`ContactLookupInterface`, `Request\Accounting\FindContact`): the contact as the software has it, with its reference, or `null` | `odoo` (`res.partner`'s `ref`), `pennylane` (`external_reference eq` on `/suppliers` or `/customers`) |

```php
try {
    $flow = $gateway->submit($invoice, $invoice->number);
} catch (OutcomeUnknownException) {
    $flow = $gateway->findFlow($invoice->number);   // null: not taken - submit again; a Flow: keep it
}
```

`supports(FindFlow::class)` / `supports(FindEntry::class)` / `supports(FindContact::class)` say
beforehand whether a gateway can. Where none can (`email`, `chorus-pro`), the application asks a
person to check. A status declared by e-mail is recorded in the application's own store: nothing
leaves, nothing to look up. A flow just
deposited may take a moment to show in a platform's search: ask again later before concluding
`null`.

A flow `REJECTED` by the platform's checks is not an exception: it is the answer of a later
`fetchStatus()` (or a webhook), with its `errors`.

## No secret in a failure

`Answer::send()` keeps the request's secrets out of what it throws. The HTTP client's message
quotes the URL called: its key parameters, a password in its user part and every secret of the
request's options are masked (`api_key=***`, `Http\Redaction::text()`). The previous exception is
a copy of the client's (`Redaction::copy()`: the same class and code, that message, its own
previous copied the same way), and no trace of the chain keeps its arguments, where the query,
headers and body stood: a log that writes the whole chain, arguments included
(`zend.exception_ignore_args` off), holds no key.

omnibill/email's `UnreachableException` wraps a copy of the mailer's exception the same way: an
SMTP transport's trace held the credentials it sent (`AUTH PLAIN`).
