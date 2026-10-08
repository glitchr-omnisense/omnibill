# glitchr/omnibill

One contract for **transmitting and receiving electronic invoices** - whoever carries them: an
e-mail, an approved platform (plateforme agréée) through the standard API of XP Z12-013, Chorus
Pro for the public sector.

```php
$gateway = $registry->get('invoices');                       // or 'platform', 'public': the same calls

$flow = $gateway->submit($invoice);                          // a conformant file: Factur-X, UBL, CII
$flow->reference;                                            // keep it
$gateway->fetchStatus($flow)->status;                        // LifecycleStatus::DEPOSITED, APPROVED, PAID...

$inbox = $gateway->receive($cursor);                         // suppliers' invoices, statuses about yours
$gateway->setStatus(new StatusChange(LifecycleStatus::PAID), $flow);
$gateway->lookup('702042755');                               // where a company receives (the directory)
```

This package holds the contract (`GatewayInterface`, the requests `Submit`, `FetchStatus`,
`Receive`, `SetStatus`, `Lookup`, `Report`, `Notify`, `GatewayFactory`, `Registry`), the models
(`Invoice`, `Party`, `Flow`, `LifecycleStatus`, `StatusChange`, `Inbox`, `Addressing`, `EReport`,
`Notification`, `Capabilities`) and a bridge for Symfony. It needs no framework: PHP and
`symfony/http-client-contracts`. Each gateway is a package of its own:

| Package | Carries | Approved platform |
|---|---|---|
| [`omnibill/email`](https://github.com/glitchr-studio/omnibill-email) | the invoice attached to an e-mail to the buyer (symfony/mailer); statuses kept by hand. The default, and the tests' | no |
| [`omnibill/afnor`](https://github.com/glitchr-studio/omnibill-afnor) | any approved platform through XP Z12-013: flows (invoices, lifecycle messages, e-reporting), webhooks, the directory | yes |
| [`omnibill/chorus-pro`](https://github.com/glitchr-studio/omnibill-chorus-pro) | Chorus Pro through PISTE: invoices to public entities, deposited and followed | the public sector's portal |

## What a gateway is, and is not

**The format is not here.** A gateway carries a file already conformant - a Factur-X, a UBL or
CII invoice, a CDAR lifecycle message, an e-reporting file - that the application produced
(`horstoeko/zugferd` writes Factur-X). It reads nothing inside an invoice; of a lifecycle message
it reads the status, the invoice's number and the reason, to hand them over.

It is not invoicing software (the application issues the invoice), nor accounting, nor payment,
nor a bank. It keeps no books: the application keeps the flows' references and their statuses.

What a gateway cannot do is a `NotSupportedException`, and `supports()` says so beforehand: an
e-mail has no inbox nor directory, Chorus Pro gives a supplier no inbox.

## The reform, and the e-mail gateway

In France every company subject to VAT must be able to **receive** electronic invoices through an
approved platform since 1 September 2026; **issuing** is mandatory then for large and mid-sized
companies, on 1 September 2027 for SMEs and micro-companies (impots.gouv.fr, read on 2026-10-05).
Until then a small company may still send its invoices by e-mail: `omnibill/email`. On that date a
site changes gateway in its configuration - `factory: afnor` - not its code.

## Documentation

- [Installation and a first invoice](docs/installation.md)
- [Models](docs/models.md)
- [Gateways: the contract, what each does, writing one](docs/gateways.md)
- [Symfony](docs/symfony.md)
- [The Docker harness: bare PHP, the console](docs/harness.md)

## Plain PHP

```sh
composer require glitchr/omnibill omnibill/email
```

```php
use Omnibill\Email\EmailGatewayFactory;
use Omnibill\Registry;

$registry = new Registry([new EmailGatewayFactory($mailer)], [
    'invoices' => ['factory' => 'email', 'options' => ['from' => 'Atelier <factures@example.org>']],
]);
```

No bundle, no container: a factory per gateway package, the registry built by hand.
[docs/installation.md](docs/installation.md) opens on a whole script that runs as it is.

## Symfony

`Omnibill\Bridge\Symfony\OmnibillBundle` does that wiring in a Symfony application
([docs/symfony.md](docs/symfony.md)); its components are not required by this package.

```yaml
omnibill:
    gateways:
        invoices: { factory: email, options: { from: 'Atelier <factures@example.org>' } }
```

## Docker

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnibill gateways
docker compose run --rm omnibill bare          # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omnibill test
```

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
