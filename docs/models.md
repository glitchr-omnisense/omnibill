---
title: Models
order: 2
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

`Invoice::fromFile($path, $syntax, $number, $buyer)`, `sha256()`, `size()`.

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

`with(state, status, history, errors, updatedAt, data)` gives the same flow updated.

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

`code()`, `label()`, `isMandatory()`, `isFinal()`, `LifecycleStatus::fromCode('fr:212')`.

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

A platform's callback, its signature checked: `event` (`FLOW_UPDATED`, `RECEIVED`, `OTHER`), the
`flow`, `at`, `id` (what a callback sent twice is recognised by), `data`.

## Capabilities

`syntaxes` (what it carries), `approved` (an approved platform's channel), `publicSector`,
`maxSize` (bytes). `carries(Syntax)`, `toArray()`. The operations are asked of the gateway:
`supports(Receive::class)`.
