<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    $_SESSION["flash_error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// 1. Prevent deleting your own account
if (isset($_SESSION["user_id"]) && (int) $_SESSION["user_id"] === $id) {
    $_SESSION["flash_error"] = "Security Notice: You cannot delete or deactivate your own active session account.";
    header("Location: index.php");
    exit;
}

// 2. Verify user existence
$find_sql = "SELECT id, name, username FROM users WHERE id = ?";
$find_stmt = mysqli_prepare($conn, $find_sql);
mysqli_stmt_bind_param($find_stmt, "i", $id);
mysqli_stmt_execute($find_stmt);
$user = mysqli_fetch_assoc(mysqli_stmt_get_result($find_stmt));

if (!$user) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    $_SESSION["flash_error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// 3. Check for relational dependencies (orders.waiter_id and salaries.user_id)
$ref_sql = "SELECT 
            (SELECT COUNT(*) FROM orders WHERE waiter_id = ?) AS order_count,
            (SELECT COUNT(*) FROM salaries WHERE user_id = ?) AS salary_count";

$ref_stmt = mysqli_prepare($conn, $ref_sql);
mysqli_stmt_bind_param($ref_stmt, "ii", $id, $id);
mysqli_stmt_execute($ref_stmt);
$ref_res = mysqli_fetch_assoc(mysqli_stmt_get_result($ref_stmt));

$order_count = (int) ($ref_res["order_count"] ?? 0);
$salary_count = (int) ($ref_res["salary_count"] ?? 0);
$total_refs = $order_count + $salary_count;

if ($total_refs > 0) {
    // Soft-deactivate to maintain relational integrity and audit history
    try {
        $deact_sql = "UPDATE users SET is_active = 0 WHERE id = ?";
        $deact_stmt = mysqli_prepare($conn, $deact_sql);
        mysqli_stmt_bind_param($deact_stmt, "i", $id);
        mysqli_stmt_execute($deact_stmt);

        $_SESSION["flash_warning"] = "Staff account '" . $user["name"] . "' has $total_refs historical record(s) ($order_count orders, $salary_count payrolls) and cannot be deleted. The account has been deactivated (suspended) instead.";
    } catch (mysqli_sql_exception $e) {
        $_SESSION["flash_error"] = "Database error while deactivating account.";
    }
} else {
    // Safe hard delete since no foreign references exist
    try {
        $sql = "DELETE FROM users WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);

        $_SESSION["flash_success"] = "Staff account '" . $user["name"] . "' (@" . $user["username"] . ") has been permanently removed.";
    } catch (mysqli_sql_exception $e) {
        $_SESSION["flash_error"] = "Database rejected deletion due to linked records.";
    }
}

header("Location: index.php");
exit;