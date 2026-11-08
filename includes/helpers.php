<?php

date_default_timezone_set('Asia/Karachi');
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

if (!function_exists('start_secure_session')) {
    /**
     * Start or resume a session with secure cookie parameters.
     * Prevents duplicate session initialization and sets HttpOnly, SameSite, and Secure flags.
     */
    function start_secure_session() {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
                    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

                $current_params = session_get_cookie_params();

                session_set_cookie_params([
                    'lifetime' => $current_params['lifetime'] ?? 0,
                    'path' => $current_params['path'] ?? '/',
                    'domain' => $current_params['domain'] ?? '',
                    'secure' => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            }
            session_start();
        }
    }
}

if (!function_exists('get_app_base_path')) {
    /**
     * Determine the web application root path (e.g. "/restaurant_pos/") dynamically.
     * Works seamlessly regardless of host subdirectory, virtual host, or nested request URL.
     *
     * @return string Normalized root URI with trailing slash (e.g. "/restaurant_pos/")
     */
    function get_app_base_path() {
        $project_root = str_replace('\\', '/', dirname(__DIR__));
        $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');

        if (!empty($doc_root) && strpos($project_root, $doc_root) === 0) {
            $relative = substr($project_root, strlen($doc_root));
            return '/' . trim($relative, '/') . '/';
        }

        // Fallback using script path deduction
        $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $app_dir = preg_replace('#/(auth|bills|customers|customer-ledger|expenses|inventory|kitchen|menu|orders|purchases|recipes|reports|reservations|salary|supplier-ledger|suppliers|table|users)(/.*)?$#i', '', $script_dir);
        return '/' . trim($app_dir, '/') . '/';
    }
}

if (!function_exists('get_login_url')) {
    /**
     * Return absolute path to login page with optional query parameters.
     *
     * @param array $params Optional query key-value pairs
     * @return string
     */
    function get_login_url(array $params = []) {
        $base = get_app_base_path();
        $url = rtrim($base, '/') . '/auth/login.php';
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }
}

