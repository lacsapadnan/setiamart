<?php

namespace App\Support;

class SalePaymentMethodResolver
{
    /**
     * Resolve payment method for sale cashflow: honor whitelisted request value, else derive from cash/transfer amounts.
     */
    public static function resolve(?string $fromRequest, int|float|string $cash, int|float|string $transfer): ?string
    {
        $cashAmount = self::toNonNegativeInt($cash);
        $transferAmount = self::toNonNegativeInt($transfer);

        $raw = $fromRequest !== null ? trim($fromRequest) : '';
        if ($raw !== '') {
            $normalized = strtolower($raw);
            if (in_array($normalized, ['cash', 'transfer', 'split'], true)) {
                return $normalized;
            }
        }

        if ($cashAmount > 0 && $transferAmount > 0) {
            return 'split';
        }
        if ($cashAmount > 0) {
            return 'cash';
        }
        if ($transferAmount > 0) {
            return 'transfer';
        }

        return null;
    }

    /**
     * Parse amount like form inputs (digits only; strips thousands separators).
     */
    private static function toNonNegativeInt(int|float|string $value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        if (is_float($value)) {
            return max(0, (int) $value);
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        return $digits === '' ? 0 : max(0, (int) $digits);
    }
}
