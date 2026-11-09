<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Method Not Allowed");
}

if (!verify_csrf_token()) {
    http_response_code(403);
    exit("CSRF token validation failed");
}

$id = (int) ($_POST["id"] ?? 0);

if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Start transaction with pessimistic lock
mysqli_begin_transaction($conn);

try {
    // 1. Lock and fetch purchase
    $check_stmt = mysqli_prepare($conn, "SELECT id, payment_status, total_amount FROM purchases WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($check_stmt, "i", $id);
    mysqli_stmt_execute($check_stmt);
    $res = mysqli_stmt_get_result($check_stmt);
    $purchase = mysqli_fetch_assoc($res);

    if (!$purchase) {
        throw new Exception("Record not found or invalid identifier.");
    }

    // 2. Financial settlement check: Cannot delete paid or partially settled purchases
    if ($purchase["payment_status"] === "paid" || $purchase["payment_status"] === "partial") {
        throw new Exception("Cannot delete Purchase #PO-" . str_pad($id, 3, '0', STR_PAD_LEFT) . ": Settled or paid vendor purchases cannot be deleted to preserve financial audit integrity.");
    }

    // 3. Inventory movements check: Cannot delete purchase if stock was credited
    $sql = "SELECT id
            FROM inventory_transactions
            WHERE reference_type = 'purchase'
            AND reference_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {
        throw new Exception("Cannot delete Purchase #PO-" . str_pad($id, 3, '0', STR_PAD_LEFT) . ": Inventory movements and stock records are associated with this purchase. Stock cannot be silently removed.");
    }

    // 4. Safe delete items & purchase
    $sql = "DELETE FROM purchase_items WHERE purchase_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $sql = "DELETE FROM purchases WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);
    $_SESSION["flash_success"] = "Purchase #PO-" . str_pad($id, 3, '0', STR_PAD_LEFT) . " was successfully deleted.";
    header("Location: index.php");
    exit;

} catch (Exception $error) {
    mysqli_rollback($conn);
    $_SESSION["error"] = $error->getMessage();
    header("Location: " . ($id > 0 ? "view.php?id=" . $id : "index.php"));
    exit;
}
