---
title: Installation
order: 1
---

# Installation and a first invoice

```sh
composer require omniscient/omnibill omnibill/email     # the invoice by e-mail: the default
composer require omnibill/afnor                      # an approved platform, XP Z12-013
composer require omnibill/chorus-pro                 # the public sector, through PISTE
```

PHP 8.2 or later.

Omnibill needs no framework. The core requires PHP and `symfony/http-client-contracts` (the
interfaces, no client); `omnibill/email` requires `symfony/mailer`, the platforms' packages
`symfony/http-client` - libraries, not a framework - and each takes the application's mailer or
client when it is given one. It runs the same in plain PHP, in a worker, in Laravel or Slim, and
in Symfony, where a bundle does the wiring ([Symfony](symfony.md)).

## Plain PHP

```php
<?php // bill.php

require __DIR__.'/vendor/autoload.php';

use Omnibill\Email\EmailGatewayFactory;
use Omnibill\Model\Invoice;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\Party;
use Omnibill\Model\StatusChange;
use Omnibill\Model\Syntax;
use Omnibill\Registry;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

$mailer = new Mailer(Transport::fromDsn(getenv('MAILER_DSN') ?: 'null://null'));
$registry = new Registry([new EmailGatewayFactory($mailer)], [
    'invoices' => ['factory' => 'email', 'options' => ['from' => 'Atelier Tilleul <factures@atelier.example>']],
]);

// The file is the application's, already conformant: here a Factur-X made by horstoeko/zugferd, say.
$invoice = new Invoice(
    content: "%PDF-1.7\n...",
    filename: 'F-2026-042.pdf',
    syntax: Syntax::FACTURX,
    number: 'F-2026-042',
    seller: new Party('Atelier Tilleul', siren: '123456789'),
    buyer: new Party('Maison Érable', siren: '702042755', email: 'compta@erable.example'),
    total: '1250.00',
);

$gateway = $registry->get('invoices');
$flow = $gateway->submit($invoice);                                       // keep $flow->reference
printf("%s: %s to %s, %s\n", $flow->invoiceNumber, $flow->status->label(), $flow->data['to'], $flow->reference);

// Weeks later, the payment arrives: the application says so.
$flow = $gateway->setStatus(new StatusChange(LifecycleStatus::PAID, amount: '1250.00'), $flow);
foreach ($gateway->fetchStatus($flow)->history as $change) {
    printf("  %s %s%s\n", $change->at->format('Y-m-d H:i'), $change->status->label(), null !== $change->status->code() ? ' ('.$change->status->code().')' : '');
}
```

```
$ php bill.php
F-2026-042: Envoyée to compta@erable.example, 08bf5ec24950b3d91b356c15f5a918d6@omnibill
  2026-10-08 00:54 Envoyée
  2026-10-08 00:54 Encaissée (212)
```

(2026-10-08; with `MAILER_DSN=smtp://...` the e-mail leaves for real - the harness sent one to a
local SMTP server that day, the PDF attached)

That is all there is to it:

- a **factory** per gateway package, which takes what its gateway works with - a mailer for the
  e-mail, an HTTP client for the platforms;
- the **registry**, built by hand from the factories and the gateways' options, by name;
- the **gateways** it gives, each answering the same questions ([gateways](gateways.md)).

No class of a framework is loaded on the way - a test of this package checks it in a process of
its own (`Tests/BareTest.php`), and so does `docker compose run --rm omnibill bare`
([harness](harness.md)).

## What the application does around a gateway

1. **Produce** the file: a Factur-X, a UBL or CII invoice - not Omnibill's job.
2. **Submit** it, and **keep the flow's reference** with the invoice, and the gateway's name.
3. **Follow** it: `fetchStatus()` now and then, or a platform's webhook (`notify()`), and
   `receive()` for what comes in - suppliers' invoices, statuses about your own - from the
   cursor kept since last time.
4. **Declare** what only the application knows - an invoice paid (`LifecycleStatus::PAID`, the
   reform's "Encaissée"), a supplier's invoice refused - with `setStatus()`. A platform carries it
   as a lifecycle message (CDAR) the application produced; the e-mail gateway records it.

## Errors

| Exception | When |
|---|---|
| `NotSupportedException` | the gateway does not do that: `supports()` says so beforehand |
| `InvalidConfigException` | a gateway not configured, a factory not installed, an option missing; an invoice without what the gateway needs (a buyer's e-mail, a number) |
| `InvalidKeyException` | the platform refused the application's credentials |
| `UnreachableException` | the platform did not answer: nothing was transmitted, try again |
| `InvalidNotificationException` | a callback whose signature does not hold, or too old |
| `ProviderException` | the platform refused the request (the three above extend it) |

All implement `Omnibill\Exception\OmnibillException`.
