<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo "Method Not Allowed. Deletion requires a POST request.";
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

mysqli_begin_transaction($conn);

try {
    // 1. Lock and fetch bill details
    $sql = "SELECT id, order_id, payment_status, total_amount
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

    // 2. Reject deletion of paid bills or bills with settlement records
    if ($bill["payment_status"] === "paid") {
        throw new Exception("Cannot delete Bill #$id: Paid bills and settled financial transactions cannot be deleted.");
    }

    // 3. Verify order payment status is not paid
    $check_order_sql = "SELECT payment_status FROM orders WHERE id = ? FOR UPDATE";
    $check_order_stmt = mysqli_prepare($conn, $check_order_sql);
    mysqli_stmt_bind_param($check_order_stmt, "i", $bill["order_id"]);
    mysqli_stmt_execute($check_order_stmt);
    $order_res = mysqli_stmt_get_result($check_order_stmt);
    $order = mysqli_fetch_assoc($order_res);

    if ($order && $order["payment_status"] === "paid") {
        throw new Exception("Cannot delete Bill #$id: The associated order #{$bill['order_id']} has already been marked as paid.");
    }

    // 4. Safe delete unpaid bill
    $sql = "DELETE FROM bills WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);

    $_SESSION["flash_success"] = "Bill #$id was successfully deleted.";
    header("Location: index.php");
    exit;

} catch (Exception $error) {
    mysqli_rollback($conn);
    $_SESSION["error"] = $error->getMessage();
    header("Location: index.php");
    exit;
}
