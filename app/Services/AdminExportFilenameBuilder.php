<?php

namespace App\Services;

use Carbon\Carbon;

class AdminExportFilenameBuilder
{
    public static function buildPdfFilename($purpose, $statusCode, $timeCode = null)
    {
        $parts = array_filter([
            self::sanitizeSegment($purpose),
            $timeCode ? self::sanitizeSegment($timeCode) : null,
            self::sanitizeSegment($statusCode),
            date('Ymd_Hi'),
        ], function ($value) {
            return $value !== null && $value !== '';
        });

        return implode('_', $parts).'.pdf';
    }

    public static function timeCodeForScope($scope)
    {
        $today = Carbon::today();

        switch ($scope) {
            case 'today':
            case 'paid_today':
                return '日'.$today->format('Ymd');
            case 'week':
            case 'paid_week':
                $start = $today->copy()->subDays(6);

                return '周'.$start->format('Ymd').'-'.$today->format('Ymd');
            default:
                return null;
        }
    }

    protected static function sanitizeSegment($value)
    {
        $safe = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9_-]+/u', '', (string) $value);

        return $safe !== '' ? $safe : 'export';
    }
}
