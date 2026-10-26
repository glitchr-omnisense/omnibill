---
title: The directory and e-reporting
order: 34
---

# The directory and e-reporting

Both are `omnibill/afnor`'s alone; `email` and `chorus-pro` throw `NotSupportedException`.

## Where a company receives (lookup)

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
