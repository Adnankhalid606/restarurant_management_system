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

// Start atomic transaction
mysqli_begin_transaction($conn);

try {
    // 1. Lock and fetch order details with table and financial status
    $sql = "SELECT id, table_id, status, payment_status
            FROM orders
            WHERE id = ?
            FOR UPDATE";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);

    if (!$order) {
        throw new Exception("Record not found or invalid identifier.");
    }

    // 2. Financial History Safeguard: Check if any bill exists for this order
    $check_bill_sql = "SELECT id, payment_status, total_amount FROM bills WHERE order_id = ? LIMIT 1 FOR UPDATE";
    $check_bill_stmt = mysqli_prepare($conn, $check_bill_sql);
    mysqli_stmt_bind_param($check_bill_stmt, "i", $id);
    mysqli_stmt_execute($check_bill_stmt);
    $bill_res = mysqli_stmt_get_result($check_bill_stmt);
    $linked_bill = mysqli_fetch_assoc($bill_res);

    if ($linked_bill) {
        if ($linked_bill["payment_status"] === "paid" || $order["payment_status"] === "paid") {
            throw new Exception("Cannot delete Order #$id: A paid invoice (#Bill-" . $linked_bill["id"] . ") exists. Financial audit records cannot be deleted.");
        }
        throw new Exception("Cannot delete Order #$id: An existing invoice (#Bill-" . $linked_bill["id"] . ") is linked to this order. Please cancel or remove the bill first.");
    }

    // Direct check on order payment status
    if ($order["payment_status"] === "paid") {
        throw new Exception("Cannot delete Order #$id: This order has already been recorded as paid.");
    }

    // 3. Operational & Inventory Safeguards
    // Check if order has completed kitchen fulfillment
    if ($order["status"] === "completed") {
        throw new Exception("Cannot delete Order #$id: This order has completed kitchen fulfillment and inventory stock reductions have already been recorded.");
    }

    // Direct check for associated inventory transactions
    $check_inv_sql = "SELECT id FROM inventory_transactions WHERE reference_type = 'order' AND reference_id = ? LIMIT 1";
    $check_inv_stmt = mysqli_prepare($conn, $check_inv_sql);
    mysqli_stmt_bind_param($check_inv_stmt, "i", $id);
    mysqli_stmt_execute($check_inv_stmt);
    $inv_res = mysqli_stmt_get_result($check_inv_stmt);
    if (mysqli_num_rows($inv_res) > 0) {
        throw new Exception("Cannot delete Order #$id: Associated inventory deduction records exist. Hard deletion is forbidden to preserve stock accounting.");
    }

    // 4. Release table if occupied and no other active order is using it
    if (!empty($order["table_id"])) {
        $check_other_orders_sql = "SELECT id FROM orders 
                                   WHERE table_id = ? 
                                   AND id != ? 
                                   AND status IN ('pending', 'preparing', 'ready') 
                                   LIMIT 1 FOR UPDATE";
        $check_other_stmt = mysqli_prepare($conn, $check_other_orders_sql);
        mysqli_stmt_bind_param($check_other_stmt, "ii", $order["table_id"], $id);
        mysqli_stmt_execute($check_other_stmt);
        $other_res = mysqli_stmt_get_result($check_other_stmt);

        if (mysqli_num_rows($other_res) === 0) {
            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ? AND status = 'occupied'";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $order["table_id"]);
            mysqli_stmt_execute($stmt);
        }
    }

    // 5. Delete order line items
    $sql = "DELETE FROM order_items WHERE order_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    // 6. Delete order record
    $sql = "DELETE FROM orders WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    mysqli_commit($conn);

    $_SESSION["flash_success"] = "Order #$id was successfully deleted.";
    header("Location: index.php");
    exit;

} catch (Exception $error) {
    mysqli_rollback($conn);
    $_SESSION["error"] = $error->getMessage();
    header("Location: index.php");
    exit;
}
