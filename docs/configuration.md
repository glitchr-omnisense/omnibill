---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry

```php
new Registry(iterable $factories, array $config);
```

| Key | Type | Required | |
|---|---|---|---|
| `<name>` | string | - | the gateway's name: `get('<name>')`, and in Symfony the argument's name |
| `<name>.factory` | string | yes | a factory's `getName()`: `email`, `afnor`, `chorus-pro`, or an application's own |
| `<name>.options` | map | no, default `[]` | handed to the factory's `create()`; `create($name, $overrides)` puts other values over them |

The same tree is the Symfony bundle's `omnibill.gateways` ([Symfony](symfony.md#configuration)).
An option is read as given: `null` and `''` count as missing for a required option, and as "not set"
for most optional ones (each factory's `?:` or its own check, below).

## E-mail (`email`)

`new EmailGatewayFactory(?MailerInterface $mailer = null, ?StatusStoreInterface $store = null)`:
the mailer is required to build a gateway (`InvalidConfigException`: `The "email" gateway needs a
mailer: give the factory a Symfony\Component\Mailer\MailerInterface.`); without a store, the
statuses live in this process's memory.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `from` | string | - | yes | the sender: an address, or `Name <address>` |
| `reply_to` | string | none | no | where the buyer's answers go |
| `bcc` | list or string | `[]` | no | a copy of each invoice sent: a list, or one comma-separated string (`Config::list()`) |
| `subject` | string | `Facture {number}` | no | the subject; whitespace runs become one space |
| `body` | string | `Bonjour,\n\nVeuillez trouver ci-joint la facture {number}.\n\nCordialement,\n{seller}` | no | the text part |

Placeholders, in the subject and the body: `{number}`, `{seller}` (the seller's name), `{buyer}`
(the buyer's name), `{total}`, `{currency}`; an unknown value is empty. Capabilities: Factur-X, UBL,
CII; not approved; not public sector.

## Approved platform (`afnor`)

`new AfnorGatewayFactory(?HttpClientInterface $http = null)`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `flow_url` | string | - | yes | the Flow Service's root, before `/v1` (a trailing slash is dropped) |
| `token_url` | string | - | yes | the OAuth 2.0 token endpoint (client credentials) |
| `client_id` | string | - | yes | |
| `client_secret` | string | - | yes | |
| `directory_url` | string | none | no | the Directory Service's root; without it, `lookup()` is not supported |
| `scope` | string | none | no | sent with the token request when set |
| `organization_id` | string | none | no | the `Organization-Id` header: the company acted for, under a delegation |
| `webhook_key` | string | none | no | the webhook's signing key (base64); without it, `notify()` is not supported |
| `webhook_tolerance` | int | `300` | no | seconds a callback may be old; `0`: any age |
| `profile` | string | none | no | the invoices' `flowProfile` when the `Invoice` gives none (Basic, CIUS, Extended-CTC-FR); not sent for CDAR and FRR |
| `rule` | string | none | no | their `processingRule` (B2B, B2BInt, B2C, B2G...); not sent for CDAR; a report's own `rule` wins |

Capabilities: Factur-X, UBL, CII, CDAR, FRR; approved; public sector; 100 MB.

## Chorus Pro (`chorus-pro`)

`new ChorusProGatewayFactory(?HttpClientInterface $http = null)`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id` | string | - | yes | the PISTE application's (OAuth 2.0 client credentials, scope `openid`) |
| `client_secret` | string | - | yes | |
| `login` | string | - | yes | the Chorus Pro technical account (`TECH_1_...@cpro.fr`) |
| `password` | string | - | yes | sent with the login as `cpro-account: base64(login:password)` |
| `sandbox` | bool | `false` | no | PISTE's sandbox and Chorus Pro's qualification; read with `Config::bool()` (`"1"`, `"true"`, `"yes"`, `"on"`) |
| `user_id` | int | none | no | `idUtilisateurCourant`, when the account asks for it |
| `syntaxes` | map | `[]` | no | `syntaxeFlux` per syntax, over the defaults `{Factur-X: IN_DP_E2_CII_FACTURX, UBL: IN_DP_E1_UBL_INVOICE, CII: IN_DP_E1_CII_16B}` |
| `api_url` | string | `https://api.piste.gouv.fr/cpro/factures` (sandbox: `https://sandbox-api.piste.gouv.fr/cpro/factures`) | no | |
| `oauth_url` | string | `https://oauth.piste.gouv.fr/api/oauth/token` (sandbox: `https://sandbox-oauth.piste.gouv.fr/api/oauth/token`) | no | |

Capabilities: Factur-X, UBL, CII; not approved; public sector; 10 MB.

## Environment variables

The packages read no environment variable themselves. The names in these pages and in the harness
are conventions: `PA_FLOW_URL`, `PA_DIRECTORY_URL`, `PA_TOKEN_URL`, `PA_CLIENT_ID`,
`PA_CLIENT_SECRET`, `PA_WEBHOOK_KEY`; `PISTE_CLIENT_ID`, `PISTE_CLIENT_SECRET`, `CPRO_LOGIN`,
`CPRO_PASSWORD`, `CPRO_SANDBOX`; `INVOICE_FROM`, `INVOICE_TO`, `MAILER_DSN`; the harness's
`OMNIBILL_PLUGINS` and `OMNIBILL_AUTOLOAD`.
