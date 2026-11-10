<?php

namespace Omnibill\Model;

/**
 * Minor units and the decimal strings accounting APIs read: 12050 is
 * "120.50". Two decimals - the euro's, and every currency the family's
 * accounting providers book in by default.
 */
final class Amount
{
    public static function decimal(int $minor): string
    {
        $sign = $minor < 0 ? '-' : '';
        $minor = abs($minor);

        return \sprintf('%s%d.%02d', $sign, intdiv($minor, 100), $minor % 100);
    }

    /** "120.5", "120.50", 120.5 -> 12050; rounded half up to the cent. */
    public static function minor(string|int|float $decimal): int
    {
        if (\is_int($decimal)) {
            return $decimal * 100;
        }
        $text = trim((string) $decimal);
        if (!preg_match('/^(-?)(\d*)(?:\.(\d*))?$/', $text, $m) || ('' === $m[2] && '' === ($m[3] ?? ''))) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not an amount.', $text));
        }
        $fraction = str_pad($m[3] ?? '', 3, '0');
        $cents = (int) $m[2] * 100 + (int) substr($fraction, 0, 2) + ((int) $fraction[2] >= 5 ? 1 : 0);

        return '-' === $m[1] ? -$cents : $cents;
    }
}
