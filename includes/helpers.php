<?php


if (!function_exists('formatDate')) {
    /**
     * Format a date value to standard presentation format: "Sep 28, 2026"
     *
     * @param string|int|null $datetime Date string, timestamp, or null
     * @param string $fallback Fallback string if date is empty or invalid
     * @return string
     */
    function formatDate($datetime, $fallback = '-') {
        if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
            return $fallback;
        }
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$ts || $ts <= 0) {
            return $fallback;
        }
        return date('M d, Y', $ts);
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Format a timestamp to standard presentation format: "Sep 28, 2026 at 12:33 AM"
     * Concatenates the literal word "at" in PHP to prevent token collision in date().
     *
     * @param string|int|null $datetime Date/time string, timestamp, or null
     * @param string $fallback Fallback string if timestamp is empty or invalid
     * @return string
     */
    function formatDateTime($datetime, $fallback = '-') {
        if (empty($datetime) || $datetime === '0000-00-00' || $datetime === '0000-00-00 00:00:00') {
            return $fallback;
        }
        $ts = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        if (!$ts || $ts <= 0) {
            return $fallback;
        }
        return date('M d, Y', $ts) . ' at ' . date('h:i A', $ts);
    }
}

if (!function_exists('formatTime')) {
    /**
     * Format a time value to standard presentation format: "12:33 AM"
     *
     * @param string|int|null $time Time or datetime string, timestamp, or null
     * @param string $fallback Fallback string if time is empty or invalid
     * @return string
     */
    function formatTime($time, $fallback = '-') {
        if (empty($time) || $time === '00:00:00') {
            // Note: 00:00:00 could be midnight or unset, but in POS context time fields default empty
            if ($time === '00:00:00') {
                return date('h:i A', strtotime('00:00:00'));
            }
            return $fallback;
        }
        $ts = is_numeric($time) ? (int)$time : strtotime($time);
        if (!$ts) {
            return $fallback;
        }
        return date('h:i A', $ts);
    }
}
