<?php

namespace App\Support;

final class QuantityInputNormalizer
{
    /**
     * Parse quantity from request/UI (supports decimals and common Indonesian number shapes).
     */
    public static function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $string = trim((string) $value);

        if ($string === '') {
            return null;
        }

        if (str_contains($string, ',') && str_contains($string, '.')) {
            $lastCommaPos = strrpos($string, ',');
            $lastDotPos = strrpos($string, '.');

            if ($lastCommaPos > $lastDotPos) {
                $string = str_replace('.', '', $string);
                $string = str_replace(',', '.', $string);
            } else {
                $string = str_replace(',', '', $string);
            }
        } elseif (str_contains($string, ',')) {
            $string = str_replace(',', '.', $string);
        }

        if (! is_numeric($string)) {
            return null;
        }

        return round((float) $string, 2);
    }
}
