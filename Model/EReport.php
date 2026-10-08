<?php

namespace Omnibill\Model;

/**
 * An e-reporting file: the transactions with private persons or abroad, and
 * their payments, as the tax administration asks them - already produced,
 * like an invoice.
 */
final readonly class EReport
{
    /** @param string|null $rule B2C, B2BInt... where the platform asks */
    public function __construct(
        public string $content,
        public string $filename,
        public Syntax $syntax = Syntax::FRR,
        public ?string $rule = null,
    ) {
    }
}
