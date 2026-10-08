<?php

namespace Omnibill\Model;

/**
 * Where an invoice stands, in the words of the French reform: the
 * lifecycle statuses of the DGFiP's external specifications (general file
 * v3.2, 2026-04-30), each with its code - 200, 210, 212 and 213 are
 * mandatory, the others optional. SENT is the family's own: an invoice
 * handed to its recipient outside any platform (by e-mail).
 */
enum LifecycleStatus: string
{
    case SENT = 'sent';
    case DEPOSITED = 'deposited';
    case ISSUED = 'issued';
    case RECEIVED = 'received';
    case MADE_AVAILABLE = 'made_available';
    case TAKEN_OVER = 'taken_over';
    case APPROVED = 'approved';
    case PARTIALLY_APPROVED = 'partially_approved';
    case DISPUTED = 'disputed';
    case SUSPENDED = 'suspended';
    case COMPLETED = 'completed';
    case REFUSED = 'refused';
    case PAYMENT_SENT = 'payment_sent';
    case PAID = 'paid';
    case REJECTED = 'rejected';

    private const CODES = [
        'deposited' => 200, 'issued' => 201, 'received' => 202, 'made_available' => 203, 'taken_over' => 204,
        'approved' => 205, 'partially_approved' => 206, 'disputed' => 207, 'suspended' => 208, 'completed' => 209,
        'refused' => 210, 'payment_sent' => 211, 'paid' => 212, 'rejected' => 213,
    ];

    private const LABELS = [
        'sent' => 'Envoyée', 'deposited' => 'Déposée', 'issued' => 'Émise par la plateforme', 'received' => 'Reçue par la plateforme',
        'made_available' => 'Mise à disposition', 'taken_over' => 'Prise en charge', 'approved' => 'Approuvée', 'partially_approved' => 'Approuvée partiellement',
        'disputed' => 'En litige', 'suspended' => 'Suspendue', 'completed' => 'Complétée', 'refused' => 'Refusée',
        'payment_sent' => 'Paiement transmis', 'paid' => 'Encaissée', 'rejected' => 'Rejetée',
    ];

    /** The reform's code: 200 to 213; null for SENT. */
    public function code(): ?int
    {
        return self::CODES[$this->value] ?? null;
    }

    /** Its name in the DGFiP's specifications. */
    public function label(): string
    {
        return self::LABELS[$this->value];
    }

    /** Whether the reform makes it mandatory: Déposée, Refusée, Encaissée, Rejetée. */
    public function isMandatory(): bool
    {
        return \in_array($this, [self::DEPOSITED, self::REFUSED, self::PAID, self::REJECTED], true);
    }

    /** Whether nothing follows it: refused, rejected, paid. */
    public function isFinal(): bool
    {
        return \in_array($this, [self::REFUSED, self::REJECTED, self::PAID], true);
    }

    /** From a code, "212" or "fr:212" as some platforms write it; null when it is not one. */
    public static function fromCode(int|string $code): ?self
    {
        $code = (int) preg_replace('~^\D+~', '', (string) $code);
        $value = array_search($code, self::CODES, true);

        return false === $value ? null : self::from($value);
    }
}
