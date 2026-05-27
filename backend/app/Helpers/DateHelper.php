<?php

namespace App\Helpers;

use Carbon\Carbon;

class DateHelper
{
    /**
     * Parse a date string and append the current time if the time component is exactly 00:00:00.
     * This helps maintain chronological sorting when users only select a date in the frontend picker.
     *
     * @param string|null $dateString
     * @return Carbon
     */
    public static function parseDateWithCurrentTime($dateString): Carbon
    {
        if (empty($dateString)) {
            return now();
        }

        $date = Carbon::parse($dateString);

        // If the parsed time is exactly 00:00:00 or the string is just Y-m-d (10 characters)
        if ($date->format('H:i:s') === '00:00:00') {
            $now = now();
            $date->setTime($now->hour, $now->minute, $now->second);
        }

        return $date;
    }
}
