<?php

namespace App\Helpers;

use Carbon\Carbon;

class TimeHelper
{
    /**
     * Convert datetime to human-readable "time ago" format
     * 
     * @param string|null $datetime Datetime string (Y-m-d H:i:s)
     * @return string Human-readable format (e.g., "1 min ago", "2 hours ago")
     */
    public static function formatTimeAgo(?string $datetime): string
    {
        if (!$datetime) {
            return 'Never';
        }

        try {
            $date = Carbon::parse($datetime);
            $diff = $date->diffForHumans([
                'parts' => 1,           
                'short' => true,        
                'syntax' => Carbon::DIFF_ABSOLUTE,
            ]);

            return self::customizeFormat($diff);
            
        } catch (\Exception $e) {
            return $datetime; 
        }
    }

    /**
     * Customize Carbon's diffForHumans output to match dashboard style
     */
    private static function customizeFormat(string $diff): string
    {
        $replacements = [
            'second' => 'sec',
            'seconds' => 'sec',
            'minute' => 'min',
            'minutes' => 'min',
            'hour' => 'hr',
            'hours' => 'hr',
            'day' => 'day',
            'days' => 'day',
            'week' => 'wk',
            'weeks' => 'wk',
            'month' => 'mo',
            'months' => 'mo',
            'year' => 'yr',
            'years' => 'yr',
        ];

        $formatted = str_replace(array_keys($replacements), array_values($replacements), $diff);
        
        // Agregar "ago" si no está presente
        if (!str_ends_with($formatted, 'ago')) {
            $formatted .= ' ago';
        }

        return $formatted;
    }

    /**
     * Get both raw datetime and formatted "time ago"
     * 
     * @param string|null $datetime
     * @return array{raw: string|null, formatted: string}
     */
    public static function formatTimeAgoWithRaw(?string $datetime): array
    {
        return [
            'raw' => $datetime,
            'formatted' => self::formatTimeAgo($datetime),
        ];
    }

    /**
     * Convert database datetime to ISO 8601 UTC format
     * 
     * @param string|null $datetime Database format: "Y-m-d H:i:s"
     * @return string|null ISO 8601 format: "Y-m-d\TH:i:s\Z" or null
     */
    public static function toIso8601Utc(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }
        
        try {
            return (new \DateTimeImmutable($datetime))
                ->setTimezone(new \DateTimeZone('UTC'))
                ->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            // Fallback to original value if parsing fails
            return $datetime;
        }
    }
}