<?php

namespace App\Traits;

trait PhoneNormalizer
{
    /**
     * Normalize phone number to format 62XXXXXXXXX
     */
    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        // Strip all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Replace leading 0 with 62
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        }

        // Handle cases where it might already start with 62 or +62 (already handled by preg_replace)
        // If it starts with 62 but was like +62, it's already correct.

        return $phone;
    }
}
