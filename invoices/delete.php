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
    // 1. Lock and fetch bill/invoice
    $sql = "SELECT id, order_id, invoice_number, payment_status, total_amount
            FROM bills
            WHERE id = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $bill = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$bill) {
        throw new Exception("Invoice record not found or invalid identifier.");
    }

    // 2. Reject deletion if bill is paid
    if ($bill["payment_status"] === "paid") {
        throw new Exception("Cannot delete Invoice #" . $bill["id"] . ": Paid invoices and settled financial transactions cannot be deleted.");
    }

    // 3. Reject deletion if payments have been recorded
    $pm_check = mysqli_prepare($conn, "SELECT COUNT(*) AS c FROM invoice_payments WHERE bill_id = ?");
    mysqli_stmt_bind_param($pm_check, "i", $id);
    mysqli_stmt_execute($pm_check);
    $pay_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($pm_check))["c"];

    if ($pay_count > 0) {
        throw new Exception("Cannot delete Invoice #" . $bill["id"] . ": Recorded payments exist for this invoice. Financial audit records cannot be deleted.");
    }

    // 4. Verify order payment status is not paid
    $check_order_sql = "SELECT payment_status FROM orders WHERE id = ? FOR UPDATE";
    $check_order_stmt = mysqli_prepare($conn, $check_order_sql);
    mysqli_stmt_bind_param($check_order_stmt, "i", $bill["order_id"]);
    mysqli_stmt_execute($check_order_stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($check_order_stmt));

    if ($order && $order["payment_status"] === "paid") {
        throw new Exception("Cannot delete Invoice #" . $bill["id"] . ": The associated order #{$bill['order_id']} has already been marked as paid.");
    }

    // 5. Delete invoice record
    $sql = "DELETE FROM bills WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to delete invoice record: " . mysqli_error($conn));
    }

    mysqli_commit($conn);

    $inv_num = !empty($bill["invoice_number"]) ? $bill["invoice_number"] : "#" . $id;
    $_SESSION["flash_success"] = "Unpaid Invoice " . htmlspecialchars($inv_num) . " was successfully cancelled and deleted.";
    header("Location: index.php");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION["error"] = $e->getMessage();
    header("Location: index.php");
    exit;
}
