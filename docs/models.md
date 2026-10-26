---
title: Models
order: 21
---

# Models

All models are `readonly`; times are `DateTimeImmutable`; amounts are decimal strings
(`"1250.00"`). They carry what a gateway needs: a file and identifiers, never an entity of the
application.

## Invoice

| Property | |
|---|---|
| `content`, `filename` | the file's bytes, its name with the extension |
| `syntax` | `Syntax::FACTURX`, `UBL`, `CII` (also `CDAR` for a lifecycle message, `FRR` for e-reporting) |
| `number` | the invoice's number: what its statuses are followed by |
| `seller`, `buyer` | `Party`: name, SIREN, SIRET, electronic address, routing code, e-mail, country |
| `issuedAt`, `total`, `currency` | what the application knows; not read from the file |
| `profile`, `rule` | the platform's flowProfile (Basic, CIUS, Extended-CTC-FR) and processingRule (B2B, B2BInt, B2C, B2G...), where it asks |

`new Invoice(string $content, string $filename, Syntax $syntax, ?string $number = null, ?Party
$seller = null, ?Party $buyer = null, ?\DateTimeImmutable $issuedAt = null, ?string $total = null,
?string $currency = 'EUR', ?string $profile = null, ?string $rule = null)`.

`Invoice::fromFile($path, $syntax, $number, $buyer)` reads a file (an `\InvalidArgumentException`
when it cannot: `Cannot read <path>.`), its name the file's base name, its syntax the one given, else
Factur-X for a `.pdf` and CII for anything else - a UBL file must say `Syntax::UBL`. `sha256()` is
the content's hash, `size()` its length in bytes.

## Party

`new Party(?string $name = null, ?string $siren = null, ?string $siret = null, ?string
$electronicAddress = null, ?string $routingCode = null, ?string $email = null, ?string $country =
'FR')`: identifiers only. The e-mail gateway sends to the buyer's `email`; the platforms read no
party (the file says who is who).

## Syntax

| Case | Value | `mimeType()` | `extension()` | |
|---|---|---|---|---|
| `FACTURX` | `Factur-X` | `application/pdf` | `pdf` | a PDF/A-3 with its CII XML inside (Factur-X, ZUGFeRD) |
| `UBL` | `UBL` | `application/xml` | `xml` | UBL 2.1 |
| `CII` | `CII` | `application/xml` | `xml` | UN/CEFACT Cross Industry Invoice |
| `CDAR` | `CDAR` | `application/xml` | `xml` | a lifecycle status message |
| `FRR` | `FRR` | `application/xml` | `xml` | an e-reporting file |

The values are XP Z12-013's `flowSyntax` names.

## Flow

One transmission on a gateway: an invoice, a status, a report, sent or received.

| Property | |
|---|---|
| `reference` | the gateway's name for it: a flow id, a deposit number, a message id. Keep it |
| `kind` | `FlowKind::INVOICE`, `LIFECYCLE`, `REPORT`, `OTHER` |
| `direction` | `Direction::OUT` (sent), `IN` (received) |
| `state` | the platform's acknowledgement: `FlowState::PENDING`, `ACCEPTED`, `REJECTED` |
| `status`, `history` | the invoice's latest `LifecycleStatus`, and every `StatusChange` known, oldest first |
| `errors` | why the platform rejected it |
| `trackingId`, `invoiceNumber`, `submittedAt`, `updatedAt`, `name`, `data` | |

`with(state, status, history, errors, updatedAt, data)` gives the same flow updated (each argument
null keeps the flow's). The enums:

| Enum | Cases (value) |
|---|---|
| `FlowKind` | `INVOICE` (`invoice`), `LIFECYCLE` (`lifecycle`), `REPORT` (`report`), `OTHER` (`other`) |
| `Direction` | `OUT` (`out`: sent by the application), `IN` (`in`: received) |
| `FlowState` | `PENDING` (`pending`: received, not checked), `ACCEPTED` (`accepted`: checked and taken), `REJECTED` (`rejected`: refused by the platform's checks, see `errors`) |

The flow's state is the platform's technical acknowledgement of the file; what the recipient did with
the invoice is its `status`.

## LifecycleStatus

The reform's statuses, as the DGFiP's external specifications list them (general file v3.2,
2026-04-30):

| Case | Code | Label | |
|---|---|---|---|
| `DEPOSITED` | 200 | Déposée | mandatory: the issuing platform checked the invoice |
| `ISSUED` | 201 | Émise par la plateforme | |
| `RECEIVED` | 202 | Reçue par la plateforme | |
| `MADE_AVAILABLE` | 203 | Mise à disposition | |
| `TAKEN_OVER` | 204 | Prise en charge | |
| `APPROVED` | 205 | Approuvée | |
| `PARTIALLY_APPROVED` | 206 | Approuvée partiellement | |
| `DISPUTED` | 207 | En litige | |
| `SUSPENDED` | 208 | Suspendue | |
| `COMPLETED` | 209 | Complétée | |
| `REFUSED` | 210 | Refusée | mandatory: the recipient refuses it |
| `PAYMENT_SENT` | 211 | Paiement transmis | |
| `PAID` | 212 | Encaissée | mandatory: the supplier received the payment |
| `REJECTED` | 213 | Rejetée | mandatory: a platform's checks found an anomaly |
| `SENT` | - | Envoyée | the family's own: sent outside any platform (e-mail) |

Values are lower-case snake case (`deposited`, `made_available`, `payment_sent`...). `code()` is the
reform's code (null for `SENT`), `label()` the French name above, `isMandatory()` true for 200, 210,
212 and 213, `isFinal()` true for `REFUSED`, `REJECTED` and `PAID` (nothing follows them).
`LifecycleStatus::fromCode(int|string $code)` reads `212`, `"212"` or `"fr:212"` (leading non-digits
are dropped); a code that is not one of 200 to 213 is null.

## StatusChange

One step: `status`, `invoiceNumber`, `at`, `reason`, `amount` (for a payment), `code` (the
platform's own), and `file`/`filename` - the lifecycle message itself (CDAR), where the platform
carries one.

## Inbox and Received

`receive($cursor)` answers an `Inbox`: `items` (each a `Received`: the `flow`, and an `invoice` or a
`status`), `cursor` (give it to the next `receive()`), `more`. `invoices()` and `statuses()` sort
them.

## Addressing

A line of the national directory: `siren`, `siret`, `routingCode`, `electronicAddress` (the
addressing identifier), `name`, `platform` (`WK`: an approved platform, `DFH`: the public portal),
`status` (`Enabled`, `Disabled`, `Upcoming`), `data`. `isEnabled()`.

## EReport

An e-reporting file: `content`, `filename`, `syntax` (`FRR`), `rule` (B2C, B2BInt...).

## Notification

A platform's callback, its signature checked: `event` (`Event::FLOW_UPDATED` `flow_updated`: a flow
was taken or rejected; `RECEIVED` `received`: something arrived for the application; `OTHER`
`other`), the `flow`, `at`, `id` (what a callback sent twice is recognised by), `data` (the
platform's payload).

## Capabilities

`syntaxes` (what it carries), `approved` (an approved platform's channel), `publicSector`,
`maxSize` (bytes). Defaults: Factur-X, UBL and CII, not approved, not public sector, no size.
`carries(Syntax)`, `toArray()` (the syntaxes as their values, and the other fields). The operations
are asked of the gateway: `supports(Receive::class)`.

`maxSize` is said, not enforced: no gateway refuses a file for its size before sending it; the
platform does (Chorus Pro: codeRetour `20001`).
