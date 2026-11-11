---
title: Accounting
order: 35
---

# Accounting: journal entries and contacts

Since 1.1 Omnibill is "e-invoices and accounting". French accounting software doubles as approved
e-invoicing platforms, so the same providers and the same accounts serve both; the family carries
two more contracts beside the e-invoices':

- `JournalEntriesInterface` (`Capabilities::JOURNAL_ENTRIES`): post entries - the journal, the
  accounts, the amounts, the VAT - and read them back;
- `ContactsInterface` (`Capabilities::CONTACTS`): customers and suppliers.

| Package | Factory name | Entries | Contacts | Terms |
|---|---|---|---|---|
| [`omnibill/odoo`](https://github.com/glitchr-omnisense/omnibill-odoo) | `odoo` | posted (or drafts), read, listed | created, updated, read, listed | a user's API key, rotated; JSON-2 only (Odoo 19+) |
| [`omnibill/pennylane`](https://github.com/glitchr-omnisense/omnibill-pennylane) | `pennylane` | posted, read, listed | company and individual customers, suppliers | internal use unless API partner |
| `omnibill/xero`, `omnibill/quickbooks` | reserved | | | |

A gateway may keep books and carry no invoice (`odoo`, `pennylane` today: their `syntaxes` are
empty), and a gateway built before 1.1 keeps the e-invoices only.

```php
$books = $registry->journalEntries('accountant');   // NotSupportedException when the gateway keeps no books
$contacts = $registry->contacts('accountant');

$edf = $contacts->saveContact(new Contact(ContactKind::SUPPLIER, 'EDF', key: 'supplier:edf', accountNumber: '401EDF', siren: '552081317'));
// keep $edf->reference; saveContact() again with it updates
// since 1.3, after an OutcomeUnknownException: $contacts->findContact($contact) (ContactLookupInterface),
// the contact the software has under that key with its reference, or null - create it again only then

$entry = $books->postEntry(new JournalEntry('AC', new DateTimeImmutable('2026-11-10'), 'Électricité novembre', [
    new EntryLine('615', 10000, 0, vat: new Vat(2000, VatKind::DEDUCTIBLE)),
    new EntryLine('44566', 2000, 0, 'TVA 20 %', vat: new Vat(2000, VatKind::DEDUCTIBLE, base: 10000)),
    new EntryLine('401EDF', 0, 12000, contact: 'supplier:edf'),
], piece: 'EDF-2026-11', key: 'bill:edf-2026-11'));
// keep $entry->reference; $entry->number and ->status once the software validated it

$books->fetchEntry($entry);                                   // as the software has it, lines included
$books->entries(new DateTimeImmutable('2026-11-01'), new DateTimeImmutable('2026-11-30'), 'AC');   // EntryList, ->cursor for the next page
$contacts->contacts(ContactKind::CUSTOMER, 'Cam');            // ContactList
```

Amounts are integers in minor units (cents), as in `omnibase/ledger`; `Amount::decimal()` and
`Amount::minor()` convert to and from the decimal strings the APIs read. An entry must balance:
`isBalanced()`, checked before any call.

## From omnibase/ledger

The models map from `omnibase/ledger` (used by meyers) field by field; the family does not depend on
it, the application maps:

| omnibase/ledger | Omnibill |
|---|---|
| `EntryDraft::$journal` (`Journal::SALES` VT, `PURCHASES` AC, `BANK` BQ, `MISCELLANEOUS` OD, `OPENING` AN) | `JournalEntry::$journal`, the same code |
| `EntryDraft::$date`, `$label`, `$piece` | `$date`, `$label`, `$piece` |
| `EntryDraft::$source` ("notice:2026-10-U3", unique per book) | `$key` |
| `Entry::getNumber()` (once validated) | `$number`, the software's own |
| each line: `line($account, $debit, $credit, $party, $label, tax: $tax, taxBase: $base)` | `new EntryLine($account, $debit, $credit, $label, $party?->getReference(), new Vat(...))` |
| `Tax::getRate()` (basis points), `getKind()` (`TaxKind`), `getCode()`; `EntryLine::getTaxBase()` | `Vat::$rate`, `$kind` (`VatKind`, the same values), `$code`, `$base` |
| line dimensions (`unit`, `owner`) | not carried: analytics stay in the books |
| `Party::getKind()`: `TENANT`, `CUSTOMER` / `SUPPLIER` | `Contact::$kind`: `CUSTOMER` / `SUPPLIER` |
| `Party::getName()`, `getReference()`, `getAccountNumber()` (411U3), `getIban()` | `$name`, `$key`, `$accountNumber`, `$iban` |

```php
// An application's mapper, from a validated ledger Entry:
$journalEntry = new JournalEntry(
    $entry->getJournal()->getCode(), $entry->getDate(), $entry->getLabel(),
    array_map(static fn (LedgerLine $l) => new EntryLine(
        $l->getAccount()->getNumber(), $l->getDebit(), $l->getCredit(), $l->getLabel(), $l->getParty()?->getReference(),
        $l->getTax() ? new Vat($l->getTax()->getRate(), VatKind::from($l->getTax()->getKind()->value), $l->getTaxBase(), $l->getTax()->getCode()) : null,
    ), $entry->getLines()->toArray()),
    $entry->getPiece(), $entry->getSource(),
);
```

The account numbers go as the books write them; the accounting software must have the same chart
(Odoo's codes, Pennylane's numbers). Each provider says what it does with the VAT lines and the
contacts on lines (Odoo: the partner on the line; Pennylane: the auxiliary account is the contact).

## Terms

Each provider's own rules are in `capabilities()->terms` (`Terms`): Pennylane's API contract terms
allow a company's own integration only (internal use) unless one is an API partner with an OAuth
app; Odoo's external API is open on Odoo Online's Custom plan only. The site's operator reads them
before connecting a customer's books.

## Writing a gateway that keeps books

Its factory declares `contracts: [Capabilities::JOURNAL_ENTRIES, Capabilities::CONTACTS]` (with
`E_INVOICES` too if it carries invoices), its `terms`, and one action per request of
`Omnibill\Request\Accounting`: `PostEntry`, `FetchEntry`, `ListEntries`, `SaveContact`,
`FetchContact`, `ListContacts` ([Writing a gateway](providers.md)).
