<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo "Method Not Allowed. Payment settlement requires a POST request.";
    exit;
}

if (!verify_csrf_token()) {
    csrf_failure_response();
}

$id = (int) ($_POST["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Start transaction immediately with pessimistic lock
mysqli_begin_transaction($conn);

try {
    // 1. Lock and fetch bill
    $sql = "SELECT id, order_id, payment_status
            FROM bills
            WHERE id = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $bill = mysqli_fetch_assoc($result);

    if (!$bill) {
        throw new Exception("Record not found or invalid identifier.");
    }

    // 2. Guard against duplicate settlement
    if ($bill["payment_status"] === "paid") {
        throw new Exception("This bill has already been paid.");
    }

    // 3. Mark bill as paid
    $sql = "UPDATE bills
            SET payment_status = 'paid',
                paid_at = NOW()
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    // 4. Mark related order as paid
    $sql = "UPDATE orders
            SET payment_status = 'paid'
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $bill["order_id"]);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);

    $_SESSION["flash_success"] = "Bill #$id was successfully marked as paid.";
    header("Location: view.php?id=" . $id);
    exit;

} catch (Exception $error) {
    mysqli_rollback($conn);
    $_SESSION["error"] = $error->getMessage();
    header("Location: " . ($id > 0 ? "view.php?id=" . $id : "index.php"));
    exit;
}