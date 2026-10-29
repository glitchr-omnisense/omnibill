---
title: Submitting and following
order: 31
---

# Submitting and following

The examples of these usage pages come from one script per gateway, run on 2026-10-26 on the
packages' checkouts (PHP 8.4, no network) with a `MockHttpClient` serving the packages' recorded
answers (`docker/harness/config/recorded.php`, the gateways' `Tests/Fixtures`); the `>` lines are the
mock's log of each request. The platforms' answers are fixtures written from their published
schemas: the ids and dates are theirs, not the call's.

## Submitting

```php
use Omnibill\Model\Invoice;
use Omnibill\Model\Party;
use Omnibill\Model\Syntax;

$invoice = new Invoice(
    content: file_get_contents('F-2026-042.pdf'),     // the application's Factur-X
    filename: 'F-2026-042.pdf',
    syntax: Syntax::FACTURX,
    number: 'F-2026-042',                              // what the statuses are followed by
    buyer: new Party('Maison Érable', siren: '702042755', email: 'compta@erable.example'),
    profile: 'Basic',                                  // flowProfile, where the platform asks
);
$flow = $gateway->submit($invoice, trackingId: 'order-1234');

$row->gateway = 'platform';
$row->reference = $flow->reference;                    // keep it, with the gateway's name
```

What each gateway does:

| Gateway | Call | The flow answered |
|---|---|---|
| `email` | an e-mail to the buyer's `email`, the file attached | `ACCEPTED`, status `SENT`, reference the e-mail's `Message-ID` (`<32 hex>@omnibill`), `data['to']` the address |
| `afnor` | `POST /v1/flows`, multipart: `flowInfo` (JSON) and `file` | `PENDING`, reference the platform's `flowId` |
| `chorus-pro` | `POST /v1/deposer/flux`: the file in base64, its name, its `syntaxeFlux` | `PENDING`, reference `numeroFluxDepot` |

**afnor**'s `flowInfo` is `trackingId`, `name` (the file name), `processingRule` (the invoice's
`rule`, else the gateway's `rule` option), `flowSyntax`, `flowProfile` (the invoice's `profile`,
else the `profile` option), and the file's `sha256`; empty fields are left out. The flow's state is
`PENDING` whatever the answer says; its other fields are the platform's answer (`trackingId` as the
platform echoes it).

```
submit
  > POST https://auth.pa.example/auth-service/acme/token
  > POST https://api.flow.pa.example/flow-service/v1/flows
  8f1d2c3b-4a5e-4f60-9a7b-1c2d3e4f5a6b pending invoice order-42
```

(the recorded answer's `trackingId` is `order-42`.) The token is asked once, before the first call,
and kept.

**chorus-pro** maps the syntax to Chorus Pro's `syntaxeFlux` (the `syntaxes` option: Factur-X
`IN_DP_E2_CII_FACTURX`, UBL `IN_DP_E1_UBL_INVOICE`, CII `IN_DP_E1_CII_16B`); a syntax it has no name
for is an `InvalidConfigException` (`Chorus Pro takes no CDAR file through this gateway.`). It sends
`avecSignature: false`. The flow's `submittedAt` is the answer's `dateDepot`.

```
  > POST https://sandbox-oauth.piste.gouv.fr/api/oauth/token
  > POST https://sandbox-api.piste.gouv.fr/cpro/factures/v1/deposer/flux  {"fichierFlux":"JVBERi0xLjcKLi4u","nomFichier":"F-2026-042.pdf","syntaxeFlux":"IN_DP_E2_CII_FACTURX","avecSignature":fal
CPP0021100000000000000023 pending 2026-10-08 00:00
```

**email** needs the invoice's buyer and the buyer's e-mail (`InvalidConfigException` without); the
subject and the body are the gateway's templates with `{number}`, `{seller}`, `{buyer}`, `{total}`,
`{currency}` filled; a mailer that fails is an `UnreachableException` and nothing is recorded.
The first run of [Installation](installation.md#plain-php) shows it.

No gateway checks the file's size or content before sending: a file the platform refuses comes back
as a `ProviderException` (Chorus Pro: `codeRetour` `20001`, `GDP_MSG_11.022`, too large) or as a
`REJECTED` flow once checked.

## Following

```php
use Omnibill\Model\Flow;
use Omnibill\Model\FlowState;

$flow = $gateway->fetchStatus(new Flow($row->reference, invoiceNumber: $row->number));
if (FlowState::REJECTED === $flow->state) {
    $flow->errors;                       // what the platform's checks found
}
$flow->status;                           // the invoice's latest LifecycleStatus
$flow->history;                          // every StatusChange known, oldest first
```

`fetchStatus()` takes the flow kept - rebuilt from the stored reference and number is enough - and
answers it updated:

| Gateway | Asks | Answers |
|---|---|---|
| `email` | the status store, by the reference | the recorded statuses as `history`, the latest as `status` |
| `afnor` | `GET /v1/flows/{flowId}?docType=Metadata` | the acknowledgement: `Ok` is `ACCEPTED` and, for an invoice sent, `DEPOSITED` (200); `Error` is `REJECTED` and `REJECTED` (213), with the details as `errors` (`<reasonCode>: <reasonMessage> (<item>)`); anything else `PENDING`. The status is added to the history given when it is not there yet |
| `chorus-pro` | `POST /v1/rechercher/fournisseur` with the invoice's number | the invoice Chorus Pro integrated - the one deposited in this flow when several bear the number, else the first found: `ACCEPTED`, its `statut` read as a reform status (the code kept), added to the history when it changed. None found yet: the flow as it was |

```
fetchStatus
  > GET https://api.flow.pa.example/flow-service/v1/flows/8f1d2c3b-4a5e-4f60-9a7b-1c2d3e4f5a6b?docType=Metadata
  accepted deposited, history: deposited (200)
```

```
  > POST https://sandbox-api.piste.gouv.fr/cpro/factures/v1/rechercher/fournisseur  {"numeroFacture":"F-2026-042"}
accepted made_available, history: made_available (MISE_A_DISPOSITION)
```

Chorus Pro finds an invoice by its number: a flow without `invoiceNumber` is an
`InvalidConfigException`. On an approved platform, the acknowledgement is all `fetchStatus()`
tells: the recipient's statuses arrive as lifecycle flows, through [receive()](usage-receiving.md).
Chorus Pro's statuses, and the reading of each: the
[chorus-pro manual](https://github.com/glitchr-omnisense/omnibill-chorus-pro/blob/1.x/docs/index.md).

A `dateDepot` or `dateHeureEtatCourant` that PHP cannot read as a date is no date in
`omnibill/chorus-pro`: `submittedAt` the time of the deposit, the status change's `at` null.
