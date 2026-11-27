<?php

namespace App\Libraries;

class PhoneNormalizer
{
    /**
     * Normalize Indonesian/international phone number to standard 62... digits.
     */
    public static function normalize(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', trim($phone)) ?? '';
        if ($clean === '') {
            return '';
        }

        if (str_starts_with($clean, '0062')) {
            $clean = substr($clean, 2);
        } elseif (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '8')) {
            $clean = '62' . $clean;
        }

        return $clean;
    }

    /**
     * Validate normalized phone number (9-15 digits E.164 without '+', e.g. 6281234567890).
     */
    public static function isValid(string $normalizedPhone): bool
    {
        return (bool) preg_match('/^[1-9][0-9]{8,14}$/', $normalizedPhone);
    }
}
