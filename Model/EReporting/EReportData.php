<?php

namespace Omnibill\Model\EReporting;

/**
 * Since 1.2: what an e-reporting declares for one company and one period -
 * its transactions and its payments - as data, not as a file. The family
 * writes no e-reporting file: the format (the DGFiP's external
 * specifications, flux 10) is in annexes whose notice forbids their
 * processing by AI, so the file is written by the application's
 * Writer\EReportWriterInterface, written or validated by a person against
 * those annexes, and handed to report() as an EReport.
 *
 * The bundles that collect the data (omnibase/marketplace, forge) hand this
 * model to that writer instead of each its own array.
 */
final readonly class EReportData
{
    /**
     * @param string               $siren        the declaring company
     * @param list<Transaction>    $transactions
     * @param list<Payment>        $payments
     * @param string|null          $regime       the company's VAT regime, in the application's words (the writer maps it)
     * @param array<string, mixed> $data         whatever else the writer needs: the header's other fields, warnings
     */
    public function __construct(
        public string $siren,
        public \DateTimeImmutable $from,
        public \DateTimeImmutable $to,
        public array $transactions = [],
        public array $payments = [],
        public ?string $regime = null,
        public array $data = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return [] === $this->transactions && [] === $this->payments;
    }

    /** @return list<Transaction> */
    public function transactionsOf(TransactionKind $kind): array
    {
        return array_values(array_filter($this->transactions, static fn (Transaction $t) => $t->kind === $kind));
    }

    /** @return list<Payment> */
    public function paymentsOf(TransactionKind $kind): array
    {
        return array_values(array_filter($this->payments, static fn (Payment $p) => $p->kind === $kind));
    }
}
