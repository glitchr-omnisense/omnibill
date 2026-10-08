<?php

namespace Omnibill\Model;

/**
 * An invoice to transmit, or one received: its file, already conformant
 * (Factur-X, UBL, CII - the application's to produce: horstoeko/zugferd),
 * and what the application knows of it. The family reads nothing inside the
 * file; what a gateway needs beyond it - a number to follow it by, a
 * recipient's e-mail - is given here.
 */
final readonly class Invoice
{
    /**
     * @param string                  $content  the file's bytes
     * @param string                  $filename with its extension: what the platform and the recipient see
     * @param string|null             $number   the invoice's number: what its statuses are followed by
     * @param string|null             $total    the amount due, as a decimal string: "1234.50"
     * @param string|null             $profile  Basic, CIUS, Extended-CTC-FR, where the platform asks
     * @param string|null             $rule     the processing rule, where the platform asks: B2B, B2BInt, B2C, B2G...
     */
    public function __construct(
        public string $content,
        public string $filename,
        public Syntax $syntax,
        public ?string $number = null,
        public ?Party $seller = null,
        public ?Party $buyer = null,
        public ?\DateTimeImmutable $issuedAt = null,
        public ?string $total = null,
        public ?string $currency = 'EUR',
        public ?string $profile = null,
        public ?string $rule = null,
    ) {
    }

    /** An invoice from a file on disk, its syntax told by the extension unless given. */
    public static function fromFile(string $path, ?Syntax $syntax = null, ?string $number = null, ?Party $buyer = null): self
    {
        $content = @file_get_contents($path);
        if (false === $content) {
            throw new \InvalidArgumentException(\sprintf('Cannot read %s.', $path));
        }

        return new self($content, basename($path), $syntax ?? (str_ends_with(strtolower($path), '.pdf') ? Syntax::FACTURX : Syntax::CII), $number, buyer: $buyer);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->content);
    }

    public function size(): int
    {
        return \strlen($this->content);
    }
}
