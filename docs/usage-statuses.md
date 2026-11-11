---
title: Declaring statuses
order: 33
---

# Declaring statuses

Some statuses only the application knows: its invoice was paid (`PAID`, the reform's "Encaissée",
212, mandatory), a supplier's invoice is refused (`REFUSED`, 210) or approved. `setStatus()`
declares them.

```php
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\StatusChange;

$flow = $gateway->setStatus(new StatusChange(
    LifecycleStatus::PAID,
    invoiceNumber: 'F-2026-042',
    at: new \DateTimeImmutable('2026-11-15'),
    amount: '1250.00',
    file: $cdar,                         // the lifecycle message, for a platform: the application's to produce
    filename: 'CDV-212-F-2026-042.xml',
), $flow);
```

`StatusChange`: `status`, `invoiceNumber`, `at`, `reason`, `amount` (for `PAID` or
`PARTIALLY_APPROVED`, a decimal string), `code` (the platform's own), `file` and `filename` (the
lifecycle message, CDAR).

## On an approved platform (afnor)

A platform carries a status as a lifecycle message (CDAR) of its own flow. The family does not write
one - and will not: the message's content is set by XP Z12-012/013 and the DGFiP's external
specifications' annexes (Annex 2, flux 6), whose notice forbids their processing by AI. **A person
writes the writer**, or validates it against Annex 2: the application implements
`Omnibill\Writer\LifecycleMessageWriterInterface` (`write(StatusChange $change, ?Flow $flow):
StatusChange`, the change `withFile($cdar, $filename)`), finding the parties and the invoice's date
from the invoice's number it keeps, and hands `setStatus()` the change it returns. What it must
carry, for each status a buyer or a seller declares (204 taken over, 205 approved, 206 partially
approved, 207 disputed, 210 refused with its reason, 212 paid): the invoice's number and date, the
sender and the recipient, the status code, the reason where there is one, and for 212 the amounts
received by VAT rate - `StatusChange::$amounts` since 1.2 (`[2000 => '1200.00', 550 => '50.00']`,
the rate in basis points; `$amount` stays the total), the payment data the DGFiP lists for
"Encaissée" (impots.gouv.fr).

`StatusChange::$file` is required (`InvalidConfigException`: `An approved platform carries a
status as a lifecycle message: give the StatusChange its CDAR file - the family does not write
one.`). The file is submitted as a flow (`POST /v1/flows`, `flowSyntax` CDAR, no processing rule or
profile), named `filename`, else `CDV-<code>-<invoice number>.xml`. The answer is **the CDAR's
flow**, `PENDING`, its status the one declared and its history the given flow's history plus the
change.

```
setStatus
  > POST https://api.flow.pa.example/flow-service/v1/flows
  lifecycle refused pending
```

The `$flow` argument is optional here: it only carries the history over. Keep the returned flow's
reference to follow the CDAR's own acknowledgement (`fetchStatus()`).

Since 1.3, `setStatus($change, $flow, $trackingId)` (`Request\SetStatus::$trackingId`) gives the
CDAR's flow the application's own key: it goes as the flow's `trackingId` in its `flowInfo`, beside
its name and hash - the file itself is the application's, as given. After an
`OutcomeUnknownException`, `findFlow($trackingId)` finds that flow (direction `Out`) as it finds an
invoice, before the status is sent again ([errors](errors.md)). A key per declaration, unique among
the application's flows: `status:<invoice id>:<index>`. Without one, the flow carries none, as
before 1.3, and cannot be looked up. `GatewayInterface::setStatus()` keeps its two arguments:
through the interface, `$gateway->execute(new SetStatus($change, $flow, $trackingId))->getFlow()`.

```php
try {
    $flow = $gateway->setStatus($change, $invoiceFlow, 'status:F-2026-042:1');
} catch (OutcomeUnknownException) {
    $flow = $gateway->findFlow('status:F-2026-042:1');   // null: not taken - declare it again
}
```

## By e-mail

Nobody tells an e-mail's statuses: the application records them, and `setStatus()` records one in
the gateway's status store under the flow's reference - nothing is sent. The flow is required
(`InvalidConfigException` without: the store needs its reference). The change takes the flow's
invoice number and the current time when it has none. `fetchStatus()` then reads the recorded
history back.

The store is `Omnibill\Email\StatusStoreInterface`, given to the factory:

```php
use Omnibill\Email\StatusStoreInterface;
use Omnibill\Model\StatusChange;

final class InvoiceStatusStore implements StatusStoreInterface
{
    public function __construct(private readonly \PDO $db)
    {
    }

    public function record(string $reference, StatusChange $change): void
    {
        $this->db->prepare('INSERT INTO invoice_status (reference, status, at, amount, reason) VALUES (?, ?, ?, ?, ?)')
            ->execute([$reference, $change->status->value, $change->at?->format(DATE_ATOM), $change->amount, $change->reason]);
    }

    public function history(string $reference): array
    {
        $rows = $this->db->prepare('SELECT * FROM invoice_status WHERE reference = ? ORDER BY id');
        $rows->execute([$reference]);

        return array_map(static fn (array $r) => new StatusChange(
            \Omnibill\Model\LifecycleStatus::from($r['status']), at: $r['at'] ? new \DateTimeImmutable($r['at']) : null, reason: $r['reason'], amount: $r['amount'],
        ), $rows->fetchAll(\PDO::FETCH_ASSOC));
    }
}
```

(run on 2026-10-26 with an SQLite database in memory behind the e-mail gateway: `submit()` then
`setStatus(PAID, amount: '10.00')` read back as `sent`, then `paid 10.00`.)

Without one, `InMemoryStatusStore` keeps the statuses in the process's memory: right for a test or a
script, lost at the end of a web request.

## On Chorus Pro

Not supported: a supplier does not declare statuses on Chorus Pro through this API; they are read
(`fetchStatus()`).

## The statuses

The 15 cases of `LifecycleStatus`, their codes and the DGFiP's names: [Models](models.md#lifecyclestatus).
