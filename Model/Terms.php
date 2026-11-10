<?php

namespace Omnibill\Model;

use Omnisense\Contracts\Policy\Attribution;
use Omnisense\Contracts\Policy\Caching;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\Provenance;
use Omnisense\Contracts\Policy\Rights;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A provider's own rules, read in its terms and declared by its package:
 * who may use it, what it costs, how long what it gives may be kept, what
 * must never be kept. The family enforces none of them; a site follows
 * them, and its privacy notice says so.
 *
 * policy() gives them as omnisense/contracts' UsagePolicy, the part the
 * schema can say: who may use it (rights.commercial), the retention as the
 * longest a site may keep an answer (rights.caching.maxTtl), the attribution,
 * where they are written (provenance.terms); the price, the retention, what
 * must not be kept and the other rules in words (provenance.notes). The
 * rate limits stay unknown: Terms never said them.
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

    /**
     * As a usage policy: commercial use forbidden when $commercial is false,
     * under a contract when $partner, allowed when true, unknown when null;
     * storage under a contract when $partner, unknown otherwise; the
     * retention as caching's longest time to live, the shortest span the
     * duration can mean (P3M: 89 days); the attribution required on display;
     * the rest in the notes. Named "(unnamed)" until a registry names it.
     */
    public function policy(): UsagePolicy
    {
        $commercial = match (true) {
            false === $this->commercial => Permission::Forbidden,
            $this->partner => Permission::Contract,
            true === $this->commercial => Permission::Allowed,
            default => Permission::Unknown,
        };
        $ttl = null === $this->retention ? null : self::shortest(new \DateInterval($this->retention));
        $notes = array_values(array_filter([
            null === $this->price ? null : 'Price: '.$this->price,
            null === $this->retention ? null : 'Retention: '.$this->retention,
            [] === $this->mustNotKeep ? null : 'Must not keep: '.implode('; ', $this->mustNotKeep),
            [] === $this->rules ? null : implode('. ', array_map(static fn (string $rule): string => rtrim($rule, '.'), $this->rules)),
        ]));

        return new UsagePolicy(
            UsagePolicy::UNNAMED,
            null,
            new Rights(
                commercial: $commercial,
                caching: new Caching(maxTtl: $ttl),
                storage: $this->partner ? Permission::Contract : Permission::Unknown,
                attribution: null === $this->attribution ? new Attribution() : new Attribution(true, $this->attribution, null, ['display']),
            ),
            provenance: new Provenance(terms: $this->source, notes: [] === $notes ? null : implode('. ', $notes).'.'),
        );
    }

    /**
     * The rules a usage policy says, in words: commercial use (a contract is
     * a partnership), the attribution's text, where the terms are written.
     * Retention, price and the rest are the policy's notes, not read back.
     */
    public static function fromPolicy(UsagePolicy $policy): self
    {
        return new self(
            partner: Permission::Contract === $policy->rights->commercial,
            commercial: match ($policy->rights->commercial) {
                Permission::Allowed, Permission::Contract => true,
                Permission::Forbidden => false,
                Permission::Unknown => null,
            },
            attribution: $policy->rights->attribution->text,
            source: $policy->provenance->terms ?? $policy->provenance->policy,
        );
    }

    /** The fewest seconds $interval spans, whichever day it starts on (a month is 28 days at the least). */
    private static function shortest(\DateInterval $interval): int
    {
        $shortest = \PHP_INT_MAX;
        $start = new \DateTimeImmutable('2001-01-01 00:00:00', new \DateTimeZone('UTC'));
        // Four years hold every month's length and a leap day: each start in them, the shortest span.
        for ($day = 0; $day < 1461; ++$day) {
            $from = $start->modify('+'.$day.' days');
            $shortest = min($shortest, $from->add($interval)->getTimestamp() - $from->getTimestamp());
        }

        return max(0, $shortest);
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
