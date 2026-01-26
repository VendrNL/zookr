<?php

namespace App\Services\Funda;

final class FundaBusinessNormalizer
{
    public static function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/i', '_', $value) ?? '';

        return trim($value, '_');
    }

    public static function parseNlInt(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (! preg_match('/\d{1,3}(?:\.\d{3})*|\d+/', $value, $matches)) {
            return null;
        }

        $numeric = str_replace('.', '', $matches[0]);

        return (int) $numeric;
    }

    public static function parseMoney(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (! preg_match('/\d{1,3}(?:\.\d{3})*(?:,\d{1,2})?|\d+(?:,\d{1,2})?/', $value, $matches)) {
            return null;
        }

        $numeric = str_replace('.', '', $matches[0]);
        $numeric = str_replace(',', '.', $numeric);

        return (float) $numeric;
    }

    public static function parseServiceCosts(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower($value);
        if (! str_contains($normalized, 'per vierkante meter per jaar') && ! str_contains($normalized, 'per m2 per jaar')) {
            return null;
        }

        return self::parseMoney($value);
    }

    public static function digitsOnly(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return $digits !== '' ? $digits : null;
    }
}
