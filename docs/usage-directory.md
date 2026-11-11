---
title: The directory and e-reporting
order: 34
---

# The directory and e-reporting

Both are `omnibill/afnor`'s alone; `email` and `chorus-pro` throw `NotSupportedException`.

## Where a company receives (lookup)

Since 1.2 an `Addressing` carries its electronic address's scheme, `$scheme` (BT-49's schemeID):
what the directory gives, else `0225` (`Addressing::FRENCH_DIRECTORY`, "FRCTC ELECTRONIC ADDRESS" in
the EAS code list, docs.peppol.eu) - XP Z12-013's Directory Service 1.3.0 gives none.

```php
foreach ($gateway->lookup('702 042 755') as $line) {
    $line->siren;                // 702042755
    $line->siret;                // null for the legal unit's line
    $line->routingCode;
    $line->electronicAddress;    // where to address its invoices
    $line->platform;             // WK: an approved platform, DFH: the public portal
    $line->isEnabled();          // status Enabled (or none)
}
```

Supported when the gateway has a `directory_url` (the platform's Directory Service). The
identifier, spaces removed, must be a SIREN (9 digits) or a SIRET (14) - otherwise an
`InvalidConfigException` before any call. The call is `POST /v1/directory-line/search` with
`filters.siren` or `filters.siret` (`op: strict`) and `include: [siren, siret, routingCode]`; a 204
or an empty body is no line. Each line is an `Addressing`: the SIREN, SIRET and routing identifier
(read flat or nested), the addressing identifier as `electronicAddress`, the legal unit's business
name, `platformType`, `directoryLineStatus` (`Enabled`, `Disabled`, `Upcoming`) and the whole line
in `data`.

```
lookup
  > POST https://api.directory.pa.example/directory-service/v1/directory-line/search
  702042755 - 702042755 WK Enabled
  702042755 70204275500240 702042755_70204275500240_ACHATS WK Upcoming
```

## E-reporting (report)

Since 1.2 the core has a **data model** for what an e-reporting declares -
`Omnibill\Model\EReporting\EReportData` (the company's SIREN, the period, its VAT regime in the
application's words), its `Transaction`s (`TransactionKind::B2C` a day's sales to private persons,
`INTERNATIONAL_SALE` and `INTERNATIONAL_PURCHASE` invoice by invoice; amounts by VAT rate as
`RateTotal`s, base and VAT in minor units; the application's category) and its `Payment`s (by rate)
- so that the bundles that collect it (omnibase/marketplace, forge) hand one model to the file's
writer instead of each its own array. **No file format**: the e-reporting file (flux 10) is set by
the DGFiP's external specifications' annexes, whose notice forbids their processing by AI. The
application implements `Omnibill\Writer\EReportWriterInterface` (`write(EReportData): EReport`),
written or validated by a person against those annexes, and hands its `EReport` to `report()`:

```php
$flow = $gateway->report($writer->write($data), $trackingId);
```

```php
use Omnibill\Model\EReport;

$flow = $gateway->report(new EReport(file_get_contents('ER-2026-10.xml'), 'ER-2026-10.xml', rule: 'B2C'));
```

The e-reporting file (transactions with private persons or abroad, and their payments), already
produced, is submitted as a flow: `POST /v1/flows`, `flowSyntax` FRR (the report's syntax), the
report's `rule` (else the gateway's `rule` option) as `processingRule`, no profile. The answer is its
flow, `PENDING`, kind `REPORT`.

```
report
  > POST https://api.flow.pa.example/flow-service/v1/flows
  report pending
```
