<?php

namespace Omnibill\Model;

/**
 * A customer or a supplier in accounting software. It maps from
 * omnibase/ledger's Party: $kind from its kind (a tenant, a customer: CUSTOMER;
 * a supplier: SUPPLIER), $name, $key its reference (the application's
 * handle), $accountNumber its auxiliary account (411U3), $iban.
 *
 * Saved, it comes back with the software's $reference.
 */
final readonly class Contact
{
    /**
     * @param string|null          $key           the application's handle (the ledger party's reference): the software's external reference
     * @param string|null          $accountNumber the auxiliary account (411U3, 401EDF)
     * @param string|null          $siren         the legal unit, 9 digits (France)
     * @param string|null          $siret         the establishment, 14 digits (France)
     * @param string|null          $vatNumber     the intra-EU VAT number
     * @param bool                 $company       a company, or a person
     * @param string|null          $reference     the software's id, once saved
     * @param array<string, mixed> $data          the software's answer, as it came
     */
    public function __construct(
        public ContactKind $kind,
        public string $name,
        public ?string $key = null,
        public ?string $accountNumber = null,
        public ?string $email = null,
        public ?string $siren = null,
        public ?string $siret = null,
        public ?string $vatNumber = null,
        public ?string $iban = null,
        public ?Address $address = null,
        public bool $company = true,
        public ?string $reference = null,
        public array $data = [],
    ) {
    }

    /** The software's id; a LogicException before it was saved. */
    public function reference(): string
    {
        return $this->reference ?? throw new \LogicException('This contact was not saved: no reference yet.');
    }

    /** @param array<string, mixed>|null $data */
    public function with(?string $reference = null, ?string $accountNumber = null, ?array $data = null): self
    {
        return new self($this->kind, $this->name, $this->key, $accountNumber ?? $this->accountNumber, $this->email, $this->siren, $this->siret, $this->vatNumber, $this->iban, $this->address, $this->company,
            $reference ?? $this->reference, $data ?? $this->data);
    }
}
