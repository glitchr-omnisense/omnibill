<?php

namespace Omnibill\Model;

/**
 * What a gateway carries and how, said before anything is sent. The
 * operations it answers are asked of the gateway itself (supports()).
 *
 * Since 1.1 the family is "e-invoices and accounting": $contracts says which
 * of its contracts a gateway keeps (e-invoices, journal-entries, contacts),
 * $terms the provider's own rules. A gateway built before 1.1 keeps the
 * e-invoices only.
 */
final readonly class Capabilities
{
    /** Transmitting and receiving e-invoices (GatewayInterface). */
    public const E_INVOICES = 'e-invoices';
    /** Posting journal entries and reading them back (JournalEntriesInterface). */
    public const JOURNAL_ENTRIES = 'journal-entries';
    /** Customers and suppliers (ContactsInterface). */
    public const CONTACTS = 'contacts';

    /**
     * @param list<Syntax> $syntaxes  the files it carries
     * @param bool         $approved  whether it is an approved platform's channel (plateforme agréée): what the reform asks of B2B invoices
     * @param bool         $publicSector whether it reaches public entities (B2G)
     * @param int|null     $maxSize   bytes a file may weigh
     * @param list<string> $contracts the family's contracts it keeps: E_INVOICES, JOURNAL_ENTRIES, CONTACTS
     */
    public function __construct(
        public array $syntaxes = [Syntax::FACTURX, Syntax::UBL, Syntax::CII],
        public bool $approved = false,
        public bool $publicSector = false,
        public ?int $maxSize = null,
        public array $contracts = [self::E_INVOICES],
        public ?Terms $terms = null,
    ) {
    }

    /** Whether it keeps one of the family's contracts: E_INVOICES, JOURNAL_ENTRIES, CONTACTS. */
    public function keeps(string $contract): bool
    {
        return \in_array($contract, $this->contracts, true);
    }

    public function carries(Syntax $syntax): bool
    {
        return \in_array($syntax, $this->syntaxes, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'syntaxes' => array_map(static fn (Syntax $s) => $s->value, $this->syntaxes),
            'terms' => $this->terms?->toArray(),
            'approved' => $this->approved,
            'publicSector' => $this->publicSector,
            'maxSize' => $this->maxSize,
            'contracts' => $this->contracts,
        ];
    }
}
