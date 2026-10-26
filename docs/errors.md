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
use Omnibill\Exception\ProviderException;
use Omnibill\Exception\UnreachableException;

try {
    $flow = $gateway->submit($invoice);
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

A flow `REJECTED` by the platform's checks is not an exception: it is the answer of a later
`fetchStatus()` (or a webhook), with its `errors`.
