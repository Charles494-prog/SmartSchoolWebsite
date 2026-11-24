<?php

if (! function_exists('ampm')) {
    /** "13:05:00" → "1:05 PM". Empty input gives "". */
    function ampm(?string $time): string
    {
        if (! $time) {
            return '';
        }
        [$h, $m] = array_map('intval', explode(':', $time) + [0, 0]);

        return ($h % 12 ?: 12).':'.str_pad((string) $m, 2, '0', STR_PAD_LEFT).' '.($h < 12 ? 'AM' : 'PM');
    }
}
