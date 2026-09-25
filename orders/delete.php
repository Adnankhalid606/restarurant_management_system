<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid order ID.");
}


// Get order

$sql = "SELECT id, table_id
        FROM orders
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Order not found.");
}


mysqli_begin_transaction($conn);

try {

    // Release table

    if (!empty($order["table_id"])) {

        $sql = "UPDATE restaurant_tables
                SET status = 'available'
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $order["table_id"]
        );

        mysqli_stmt_execute($stmt);
    }


    // Delete items

    $sql = "DELETE FROM order_items
            WHERE order_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);


    // Delete order

    $sql = "DELETE FROM orders
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);


    mysqli_commit($conn);

    header("Location: index.php");
    exit;

} catch (Exception $error) {

    mysqli_rollback($conn);

    die($error->getMessage());
}