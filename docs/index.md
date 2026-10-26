# omnisense/omnibill

Omnibill is one PHP contract for transmitting and receiving electronic invoices. An application
asks every channel the same questions - send this invoice, where does it stand, what arrived for
me, this invoice is paid, where does this company receive its invoices, here is my e-reporting -
and gets the same answers back: a `Flow` to keep, an `Inbox`, a list of `Addressing`, a
`Notification`. Each channel is a package of its own: `omnibill/email` (the invoice attached to an
e-mail), `omnibill/afnor` (any approved platform that implements the standard API of XP Z12-013),
`omnibill/chorus-pro` (the public sector's portal, through PISTE).

```php
$gateway = $registry->get('invoices');

$flow = $gateway->submit($invoice);                                      // keep $flow->reference
$flow = $gateway->fetchStatus($flow);                                    // ACCEPTED, DEPOSITED (200)...
$inbox = $gateway->receive($cursor);                                     // suppliers' invoices, statuses about yours
$gateway->setStatus(new StatusChange(LifecycleStatus::PAID, amount: '1250.00', file: $cdar), $flow);
$gateway->lookup('702042755');                                           // where a company receives
$gateway->notify($request->getContent(), $request->headers->all());      // a platform's webhook, its signature checked
```

It is built around the French reform of electronic invoicing - its lifecycle statuses and their
codes (200 to 213), its approved platforms (plateformes agréées), its national directory - and keeps
the reform's words where they matter. A gateway carries files already conformant: Omnibill
produces no Factur-X, UBL, CII or CDAR file and reads none beyond what it must to route one (a
lifecycle message's status); producing them is the application's job (horstoeko/zugferd, for
instance). It keeps no books either: the application stores the flows' references and statuses.

The design is Payum's, as in Omnibus: a gateway's **factory** fills a configuration with its name,
title, capabilities, options, its client (`omnibill.api`) and one **action** per request it answers;
the **gateway** runs the action that supports each request; a **registry** holds the application's
gateways by name. The core needs no framework: it requires `symfony/http-client-contracts` and
nothing else. A Symfony bundle is included ([Symfony](symfony.md)).

Published as `omnisense/omnibill`, formerly `glitchr/omnibill` and `omniscient/omnibill`: the package
`replace`s both names, so a `composer.json` that still requires one of them resolves to this package
and never installs two. The namespace is `Omnibill\`; the gateways keep the `omnibill/` vendor and the
`Omnibill\<Gateway>\` namespace.

## When to use it

- A French company's site or back office that issues invoices and must, as the reform's dates
  come, move from e-mail to an approved platform without rewriting the code that sends them: the
  gateway's factory changes, the calls stay.
- A supplier of public entities depositing its invoices on Chorus Pro and following them.
- An application that receives its suppliers' invoices and the statuses of its own through an
  approved platform, by polling (`receive()`) or by webhook (`notify()`).

It is not an approved platform, and not an invoicing tool: it does not number, compute, sign,
archive, or produce invoices.

## Gateways

Three packages. The operations are what each gateway's `supports()` answers with its required
options set (the harness's `bare` lists them: [harness](harness.md)).

| Package | Factory name | Carries | Submit | Fetch status | Receive | Set status | Lookup | Report | Notify | Approved platform | Public sector |
|---|---|---|---|---|---|---|---|---|---|---|---|
| [`omnibill/email`](https://github.com/glitchr-omnisense/omnibill-email) | `email` | Factur-X, UBL, CII | yes (an e-mail) | yes (recorded) | - | yes (recorded) | - | - | - | no | no |
| [`omnibill/afnor`](https://github.com/glitchr-omnisense/omnibill-afnor) | `afnor` | Factur-X, UBL, CII, CDAR, FRR; 100 MB | yes | yes | yes | yes (a CDAR file) | with `directory_url` | yes | with `webhook_key` | yes | yes |
| [`omnibill/chorus-pro`](https://github.com/glitchr-omnisense/omnibill-chorus-pro) | `chorus-pro` | Factur-X, UBL, CII; 10 MB | yes | yes | - | - | - | - | - | no | yes |

None of the three was run against its real service except the e-mail, sent through a local SMTP
server ([Verified, and not](verified.md)).

## How this manual is organised

- [Installation](installation.md): requirements, a first invoice in plain PHP.
- [Symfony](symfony.md) and [Laravel](laravel.md): wiring the registry in a framework.
- [Concepts](concepts.md): gateways, requests and actions, factories, the registry, the HTTP helpers.
- [Models](models.md): invoices, flows, statuses, inbox, addressing, every field.
- [Gateways](gateways.md): the contract and what each gateway does with it.
- Usage: [submitting and following](usage-submitting.md), [receiving and webhooks](usage-receiving.md),
  [declaring statuses](usage-statuses.md), [the directory and e-reporting](usage-directory.md).
- [Configuration reference](configuration.md): every option of every factory.
- [Errors](errors.md): the exceptions and when each is thrown.
- [Testing](testing.md): testing an application that uses Omnibill.
- [The Docker harness](harness.md): every gateway installed, the bare script.
- [Writing a gateway](providers.md).
- [Verified, and not](verified.md).
