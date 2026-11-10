# Changelog

## [Unreleased]

### Added

- Writes sent once: the bundle hands `http_client` to the platforms' factories with its retries
  off (omnisense/contracts' `Http\NoRetry`, which omnisense/contracts 1568f2c brings; with an older
  kernel the client is kept as given; `OmnibillBundle::sentOnce()`, internal), so Symfony's
  `RetryableHttpClient` (`framework.http_client`'s `retry_failed`) never submits an invoice or
  posts an entry twice. A `MockHttpClient` is kept as given. docs/symfony.md, docs/policies.md.

- The family on [omnisense/contracts](https://github.com/glitchr-omnisense/contracts), the kernel
  every omni family builds on, with usage policies. `Config`, `GatewayFactory`, `Registry` and
  `Http\Redaction` keep their names and API over the kernel's (prefix `omnibill`, the family's
  exceptions and messages); `GatewayInterface` and `GatewayFactoryInterface` extend its provider
  contracts; `OmnibillException` extends its `ExceptionInterface`.
- `Capabilities::policy()` (given as `policy:`, or made of `$terms` through `Terms::policy()`),
  `contracts()`; `$terms` read from a policy given alone (`Terms::fromPolicy()`); `toArray()` gives
  `policy`.
- Every request a gateway sends its provider passes its `PolicyGuard` (`Gateway::guard()`, the
  operation named after the typed method; webhooks not counted): `UsageLimitReachedException` with
  `retryAfter`. `Http\Answer::send()` and `ClientCredentials` take the provider's `Backoff`: a 429
  and its `Retry-After` hold the next calls. `Registry` takes `policy` and `usage` per gateway and a
  shared limiter as its third argument; `guard()`, `policy()`, `policies()`. The bundle takes
  `policy` and `usage` beside `options`. [Usage policies](docs/policies.md).
- The harness installs omnisense/contracts from `OMNISENSE_CONTRACTS` (or GitHub) and runs its
  tests; it names its entrypoint and runs on the default bridge.

### Changed

- PHPStan at level max with an empty baseline: the 91 findings of the code before 1.1 are fixed.
  `Gateway`'s e-invoice methods throw the gateway's `ProviderException` where an action answered
  no model, rather than returning null from a method typed not to.

- "E-invoices and accounting": two contracts beside the e-invoices', since French accounting
  software doubles as approved platforms. `JournalEntriesInterface` (`postEntry()`, `fetchEntry()`,
  `entries()`) and `ContactsInterface` (`saveContact()`, `fetchContact()`, `contacts()`); requests
  `Request\Accounting\*`. `Gateway` implements both; a gateway answers what its actions support,
  `NotSupportedException` otherwise.
- Models: `JournalEntry`, `EntryLine`, `EntryStatus`, `Vat`, `VatKind` (omnibase/ledger's
  `TaxKind` values), `EntryList`; `Contact`, `ContactKind`, `Address`, `ContactList`; `Amount`
  (minor units and decimal strings); `Terms` (a provider's own rules). They map from
  omnibase/ledger's `EntryDraft`, `Entry`, `Tax` and `Party` field by field.
- `Capabilities`: `$contracts` (`E_INVOICES`, `JOURNAL_ENTRIES`, `CONTACTS`; default the
  e-invoices only), `keeps()`, `$terms`; `toArray()` gives them.
- `Registry::journalEntries()` and `Registry::contacts()`. The bundle registers `omnibill/odoo` and
  `omnibill/pennylane` when installed, and aliases each gateway as `JournalEntriesInterface $name`
  and `ContactsInterface $name`.
- The harness: both providers, the bare script posts an entry, reads it back and saves a supplier
  through them; `phpstan` runs PHPStan at level max (its baseline, empty since: see Changed).
- Docs: [Accounting](docs/usage-accounting.md), with the mapping from omnibase/ledger.

### Fixed

- An `UnreachableException` no longer carries the request's secrets. `Answer::send()` put the HTTP
  client's message, the whole URL with a key in its query, in its message, wrapped the client's own
  exception as its previous, and every trace of the chain kept the arguments of the calls under it
  (the query, headers and body with the key, when `zend.exception_ignore_args` is off). The message
  and the previous - now a copy of the client's exception, the same class and code - have the key's
  parameters, the URL's user part and the request's secrets masked as `***` (`Http\Redaction`),
  and no trace of the chain keeps its arguments, a 5xx's or a 429's included.
- composer.json requires `ext-mbstring`, which the package calls (`mb_*` functions): a PHP built without it failed with an undefined function.
