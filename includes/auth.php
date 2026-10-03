<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Cache-Control: no-store");
header("Pragma: no-cache");
header("Expires: 0");

if (!isset($_SESSION["user_id"])) {
    header("Location: /restarurant_management_system/auth/login.php");
    exit;
}

require_once __DIR__ . "/../config/database.php";

$user_id = (int) $_SESSION["user_id"];
$auth_stmt = mysqli_prepare($conn, "SELECT is_active FROM users WHERE id = ? LIMIT 1");

if ($auth_stmt) {
    mysqli_stmt_bind_param($auth_stmt, "i", $user_id);
    mysqli_stmt_execute($auth_stmt);
    $auth_res = mysqli_stmt_get_result($auth_stmt);
    $auth_user = mysqli_fetch_assoc($auth_res);

    if (!$auth_user || (int) $auth_user["is_active"] !== 1) {
        session_unset();
        session_destroy();
        header("Location: /restarurant_management_system/auth/login.php?error=deactivated");
        exit;
    }
}
