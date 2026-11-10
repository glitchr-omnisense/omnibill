# Changelog

## [Unreleased]

### Added

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
  through them; `phpstan` runs PHPStan at level max (the baseline holds the 91 findings of the code
  before 1.1, none of the new code).
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
