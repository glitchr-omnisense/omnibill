---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-26 on the four packages' checkouts (the workspace's autoloader), PHP 8.4.27, PHPUnit
11.5.57, no network: 32 tests, 170 assertions, green.

- The core (11 tests): an invoice submitted, followed and paid through a gateway; what a gateway does
  not do said beforehand and thrown; misconfiguration shown when the gateway is built; the
  lifecycle statuses with the reform's codes; invoices, flows updated not changed, inboxes; the
  token asked once and again a minute before it lapses; refused credentials and silence
  (unreachable); the Symfony bundle (gateways built and injectable by name, the packages installed
  registered with the application's client); the plain-PHP run with no framework class loaded and an
  invoice through each gateway (`Tests/BareTest.php`).
- `omnibill/afnor` (9 tests): the upload with its flowInfo, the acknowledgement as the first status,
  receiving from the cursor, a status as the application's CDAR, the directory, e-reporting, the
  webhook's signature, errors and the token renewed once, a CDAR without a reform status.
- `omnibill/chorus-pro` (5 tests): the deposit with the technical account, each syntax's
  `syntaxeFlux` and the sandbox's addresses, the statuses read as the reform's, errors and refusals,
  what Chorus Pro does not give a supplier.
- `omnibill/email` (4 tests): the invoice attached and sent, its statuses kept by hand, what an
  e-mail cannot do, the mailer needed and the templates.

The examples of these pages were run on 2026-10-26 on the same checkouts, the platforms answering
from the packages' fixtures through the harness's `recorded.php`.

## What was run live, and what was not

- **The e-mail**: sent for real on 2026-10-08 - the harness's `bare --live` through a local SMTP
  server (Mailpit, in a throwaway container): received from the sender, to the buyer, subject
  "Facture F-2026-042", the PDF attached, the Message-ID the flow's reference. Delivery to a real
  mailbox (SPF, DKIM, filters) is the application's mailer's.
- **An approved platform (afnor)**: not run against one - no account. Every answer in its fixtures
  is written from XP Z12-013's OpenAPI 1.3.0 and the DGFiP's Annex 2. Which routes a given platform
  implements is unknown until tried.
- **Chorus Pro**: not run against PISTE or Chorus Pro - no PISTE application, no technical account.
  The fixtures are written from Chorus Pro's OpenAPI and the API services annex.
- The [Laravel](laravel.md) page was written from the registry's API; it was not run in a Laravel
  application.
- The known defects found while writing these pages: `ext-mbstring` not required though
  `mb_substr()` is called ([Installation](installation.md#requirements)); a Chorus Pro date PHP cannot
  read throwing PHP's `\Exception` ([Submitting](usage-submitting.md#following)).

## Responsibility

`omnisense/omnibill` and its `omnibill/*` gateways are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the platforms or administrations it reaches. Each keeps
its own terms (accounts, approvals, credentials, the reform's obligations), named with a link in each
gateway's README. The MIT licence covers this code only; whoever runs these packages complies with
those terms and with the invoicing obligations that apply to them.
