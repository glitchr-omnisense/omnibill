<?php

namespace Omnibill\Model;

/**
 * A provider's own rules, read in its terms and declared by its package:
 * who may use it, what it costs, how long what it gives may be kept, what
 * must never be kept. The family enforces none of them; a site follows
 * them, and its privacy notice says so.
 */
final readonly class Terms
{
    /**
     * @param string|null  $retention   how long what it gives may be kept, as an ISO 8601 duration ("P3M"); null: as long as the purpose needs, no limit of the provider's
     * @param bool         $partner     only under an agreement with the provider (a charter, a contract, a partnership)
     * @param bool|null    $commercial  whether a business may use it; null not stated
     * @param string|null  $price       what a call costs, in words
     * @param string|null  $attribution what the page must show (a button, a mention)
     * @param list<string> $mustNotKeep what a site must not store of it
     * @param list<string> $rules       the other rules, in words
     * @param string|null  $source      where they are written
     */
    public function __construct(
        public ?string $retention = null,
        public bool $partner = false,
        public ?bool $commercial = true,
        public ?string $price = null,
        public ?string $attribution = null,
        public array $mustNotKeep = [],
        public array $rules = [],
        public ?string $source = null,
    ) {
    }

    /** The last moment something obtained at $at may be kept, when the provider sets a limit. */
    public function keepUntil(\DateTimeImmutable $at): ?\DateTimeImmutable
    {
        return null === $this->retention ? null : $at->add(new \DateInterval($this->retention));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'retention' => $this->retention,
            'partner' => $this->partner,
            'commercial' => $this->commercial,
            'price' => $this->price,
            'attribution' => $this->attribution,
            'mustNotKeep' => $this->mustNotKeep,
            'rules' => $this->rules,
            'source' => $this->source,
        ];
    }
}
