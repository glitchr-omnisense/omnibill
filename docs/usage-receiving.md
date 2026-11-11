---
title: Receiving and webhooks
order: 32
---

# Receiving and webhooks

Only `omnibill/afnor` receives: an approved platform delivers the application's suppliers'
invoices and the statuses of its own invoices as flows coming in. `email` and `chorus-pro` throw
`NotSupportedException` (`supports(Receive::class)` is false).

## Receiving, from a cursor

```php
$inbox = $gateway->receive($row->cursor, limit: 25);
foreach ($inbox->items as $received) {
    if ($received->isInvoice()) {
        $received->invoice;              // a supplier's invoice: its file, to read with the application's tools
    } elseif ($received->status) {
        $received->status;               // a status about one of the application's invoices
    }
    $received->flow;                     // the flow it came in
}
$row->cursor = $inbox->cursor;           // the next receive() starts there
$inbox->more;                            // true: there is more, call again
```

### Two readers of one inbox (since 1.2)

`receive()` takes the kinds of flows wanted - `Omnibill\Model\FlowKind` - so that the suppliers'
invoices (omnibase/ledger) and the statuses of the application's own invoices (omnibase/marketplace)
are read by two processes without either downloading the other's files:

```php
use Omnibill\Model\FlowKind;
use Omnibill\Request\Receive;

$inbox = $gateway->execute(new Receive($row->cursor, 25, [FlowKind::LIFECYCLE]))->getInbox();   // the statuses only
$inbox = $gateway->execute(new Receive($row->cursor, 25, [FlowKind::INVOICE]))->getInbox();     // the invoices only
```

(`Gateway::receive($cursor, $limit, $kinds)` too; `GatewayInterface::receive()` keeps its two
arguments.) `afnor` puts them in the search's `where.flowType` - `INVOICE`: `SupplierInvoice`,
`CustomerInvoice`; `LIFECYCLE`: `CustomerInvoiceLC`, `SupplierInvoiceLC` (the Flow Service's
OpenAPI 1.3.0 and 1.4.0) - and downloads only what matches. A kind with no flowType (`REPORT`,
`OTHER`) is not filtered by the platform: the gateway leaves the rest out of the `Inbox`. **A cursor
belongs to its kinds**: keep one per reader.

`receive()` sends `POST /v1/flows/search` with `where.flowDirection: [In]`, `limit` (1 to 100) and
the cursor, then downloads each flow's file (`GET /v1/flows/{flowId}?docType=Original`): one call
per flow. Each flow becomes a `Received`:

- a lifecycle flow (`flowSyntax` CDAR, or a `flowType` ending in `LC`): its CDAR file read for the
  invoice's number, the status code (200 to 213, `fr:212` taken too), the reason and the date - a
  `StatusChange` with the file kept in `file`; a message without a reform status gives no status;
- an invoice flow (CII, UBL, Factur-X, or a `flowType` with `Invoice`): an `Invoice` of the file,
  its syntax the flow's (CII when unknown), its `processingRule` and `flowProfile` as `rule` and
  `profile`;
- anything else: the flow alone.

The inbox's cursor is the platform's `nextCursor`, or the cursor given when there is none; `more`
is whether the platform gave one.

```
receive
  > POST https://api.flow.pa.example/flow-service/v1/flows/search
  > GET https://api.flow.pa.example/flow-service/v1/flows/c1a2b3c4-d5e6-4f70-8a9b-0c1d2e3f4a5b?docType=Original
  > GET https://api.flow.pa.example/flow-service/v1/flows/d2b3c4d5-e6f7-4a80-9b0c-1d2e3f4a5b6c?docType=Original
  invoice in FA-77.xml -
  lifecycle in - F-2026-042 paid 212
  cursor 'eyJjcmVhdGVkX2F0IjoiMjAyNi0xMC0wOFQwOTowMDowMFoiLCJpZCI6MTIzNH0=', more true
```

The CDAR message is parsed with `LIBXML_NONET`: no entity is fetched, nothing leaves the machine.
`Inbox::invoices()` and `Inbox::statuses()` give the invoices and the statuses alone.

## Webhooks

An approved platform can call the application when a flow changes or arrives. The webhook is
created on the platform (`POST /v1/webhooks`, not done by this package), which answers its signing
key: the `webhook_key` option. With it, `notify()` is supported:

```php
$notification = $gateway->notify($request->getContent(), $request->headers->all());

$notification->event;    // Event::RECEIVED (a flow coming in) or Event::FLOW_UPDATED
$notification->flow;     // the flow the callback carries
$notification->id;       // "<flowId>@<updatedAt>": the same for a callback sent twice
```

The check: `Afnor-Signature-Timestamp` is epoch seconds, within `webhook_tolerance` (300 s; 0: any
age) of now; `Afnor-Signature` is the base64 of an HMAC-SHA-256, under the key (base64-decoded when
it is base64), of the SHA-256 fingerprint of `<body>@<timestamp>` - over the fingerprint's 32 bytes
or its 64 hexadecimal characters, both accepted since the OpenAPI does not say which. The body must
be a JSON flow. Each failure is an `InvalidNotificationException`: no key configured, a header
missing, too old, the signature not holding, not JSON.

Run with a signature computed as a platform would, then a forged one:

```
notify
  received c1a2b3c4-d5e6-4f70-8a9b-0c1d2e3f4a5b c1a2b3c4-d5e6-4f70-8a9b-0c1d2e3f4a5b@2026-10-26T09:00:00Z
  [afnor] The signature does not hold.
```

Answer the platform fast (200, or 400 on an `InvalidNotificationException`) and do the work out of
the request: a callback announces; `receive()` from the kept cursor, or `fetchStatus()` of the flow,
gets what it announces. Deduplicate on `Notification::$id`. The Symfony controller:
[Symfony](symfony.md#a-platforms-webhook).
