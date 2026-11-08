<?php

require_once __DIR__ . '/helpers.php';
start_secure_session();

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
    || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
    || (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);

// 1. Authentication Check
if (!isset($_SESSION["user_id"])) {
    if ($is_ajax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Your session has expired or you are not authenticated. Please log in again.'
        ]);
        exit;
    }

    header("Location: " . get_login_url());
    exit;
}

// 2. Inactivity Timeout Check (30 minutes = 1800 seconds)
$timeout_duration = 1800;
$now = time();

if (isset($_SESSION["last_activity"]) && ($now - (int)$_SESSION["last_activity"]) > $timeout_duration) {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            "",
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    if ($is_ajax) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Session timed out due to 30 minutes of inactivity. Please sign in again.'
        ]);
        exit;
    }

    header("Location: " . get_login_url(["error" => "expired"]));
    exit;
}

// Update activity timestamp for active session
$_SESSION["last_activity"] = $now;

// 3. User Active Status Check in Database
require_once __DIR__ . "/../config/database.php";

$user_id = (int) $_SESSION["user_id"];
$auth_stmt = mysqli_prepare($conn, "SELECT is_active FROM users WHERE id = ? LIMIT 1");

if ($auth_stmt) {
    mysqli_stmt_bind_param($auth_stmt, "i", $user_id);
    mysqli_stmt_execute($auth_stmt);
    $auth_res = mysqli_stmt_get_result($auth_stmt);
    $auth_user = mysqli_fetch_assoc($auth_res);

    if (!$auth_user || (int) $auth_user["is_active"] !== 1) {
        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                "",
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        session_destroy();

        if ($is_ajax) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'success' => false,
                'message' => 'Your staff account has been deactivated. Please contact an administrator.'
            ]);
            exit;
        }

        header("Location: " . get_login_url(["error" => "deactivated"]));
        exit;
    }
}

