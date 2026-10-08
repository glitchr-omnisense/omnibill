<?php

namespace Omnibill\Model;

/**
 * What a gateway carries and how, said before anything is sent. The
 * operations it answers are asked of the gateway itself (supports()).
 */
final readonly class Capabilities
{
    /**
     * @param list<Syntax> $syntaxes  the files it carries
     * @param bool         $approved  whether it is an approved platform's channel (plateforme agréée): what the reform asks of B2B invoices
     * @param bool         $publicSector whether it reaches public entities (B2G)
     * @param int|null     $maxSize   bytes a file may weigh
     */
    public function __construct(
        public array $syntaxes = [Syntax::FACTURX, Syntax::UBL, Syntax::CII],
        public bool $approved = false,
        public bool $publicSector = false,
        public ?int $maxSize = null,
    ) {
    }

    public function carries(Syntax $syntax): bool
    {
        return \in_array($syntax, $this->syntaxes, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['syntaxes' => array_map(static fn (Syntax $s) => $s->value, $this->syntaxes)] + get_object_vars($this);
    }
}
