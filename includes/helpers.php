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

if (!function_exists('csrf_token')) {
    /**
     * Generate or return existing cryptographically secure CSRF token.
     *
     * @return string 64-character hexadecimal token string
     */
    function csrf_token() {
        start_secure_session();
        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || strlen($_SESSION['_csrf_token']) !== 64) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Generate an HTML hidden input tag containing the CSRF token.
     *
     * @return string Escaped HTML input element
     */
    function csrf_field() {
        $token = csrf_token();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Validate the CSRF token from POST body or X-CSRF-Token request header using hash_equals().
     * Rejects missing, malformed, or query-string tokens.
     *
     * @return bool True if valid token, false otherwise
     */
    function verify_csrf_token() {
        start_secure_session();

        if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
            return false;
        }

        $session_token = $_SESSION['_csrf_token'];
        $submitted_token = '';

        // Check POST parameter
        if (isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token'])) {
            $submitted_token = trim($_POST['_csrf_token']);
        }

        // Check HTTP headers (X-CSRF-Token)
        if ($submitted_token === '') {
            $header_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (is_string($header_token)) {
                $submitted_token = trim($header_token);
            }
        }

        // Check JSON input if content type is JSON
        if ($submitted_token === '') {
            $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($content_type, 'application/json') !== false) {
                $raw_input = file_get_contents('php://input');
                if (!empty($raw_input)) {
                    $json_data = json_decode($raw_input, true);
                    if (is_array($json_data) && isset($json_data['_csrf_token']) && is_string($json_data['_csrf_token'])) {
                        $submitted_token = trim($json_data['_csrf_token']);
                    }
                }
            }
        }

        if ($submitted_token === '' || strlen($submitted_token) !== 64) {
            return false;
        }

        return hash_equals($session_token, $submitted_token);
    }
}

if (!function_exists('csrf_failure_response')) {
    /**
     * Terminate execution with HTTP 403 upon CSRF validation failure.
     * Returns JSON for AJAX requests or a user-friendly error screen for standard POST forms.
     */
    function csrf_failure_response() {
        http_response_code(403);

        $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
            || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
            || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

        if ($is_ajax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'CSRF token validation failed. Please refresh the page and try again.'
            ]);
            exit;
        }

        header('Content-Type: text/html; charset=UTF-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>403 - Request Verification Failed</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 1.5rem; }
                .card { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; max-width: 480px; width: 100%; padding: 2rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); text-align: center; }
                .icon { width: 56px; height: 56px; line-height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; font-size: 1.75rem; font-weight: bold; margin: 0 auto 1.25rem; display: flex; align-items: center; justify-content: center; }
                h1 { font-size: 1.25rem; margin-bottom: 0.75rem; color: #0f172a; }
                p { font-size: 0.925rem; color: #64748b; line-height: 1.5; margin-bottom: 1.5rem; }
                .btn { display: inline-block; background: #0284c7; color: #ffffff; text-decoration: none; padding: 0.625rem 1.25rem; border-radius: 0.5rem; font-weight: 500; font-size: 0.875rem; }
                .btn:hover { background: #0369a1; }
            </style>
        </head>
        <body>
            <div class="card">
                <div class="icon">!</div>
                <h1>Security Verification Failed</h1>
                <p>Your form submission could not be verified because the security token was missing, expired, or invalid. Please return to the previous page, refresh, and submit again.</p>
                <a href="javascript:history.back()" class="btn">Return to Previous Page</a>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}


