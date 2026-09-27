<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = $_GET["id"];


// Get bill
$sql = "SELECT id, order_id, payment_status
        FROM bills
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$bill = mysqli_fetch_assoc($result);

if (!$bill) {
    die("Bill not found.");
}


// Check if already paid
if ($bill["payment_status"] === "paid") {
    die("This bill is already paid.");
}


// Start transaction
mysqli_begin_transaction($conn);

try {

    // Mark bill as paid
    $sql = "UPDATE bills
            SET payment_status = 'paid',
                paid_at = NOW()
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);


    // Mark related order as paid
    $sql = "UPDATE orders
            SET payment_status = 'paid'
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $bill["order_id"]);
    mysqli_stmt_execute($stmt);


    // Save both changes
    mysqli_commit($conn);


    // Go back to bill
    header("Location: view.php?id=" . $id);
    exit;

} catch (Exception $error) {

    // Undo changes if something fails
    mysqli_rollback($conn);

    die("Payment failed: " . $error->getMessage());
}