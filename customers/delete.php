<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Check if customer exists
$cust_stmt = mysqli_prepare($conn, "SELECT id FROM customers WHERE id = ?");
mysqli_stmt_bind_param($cust_stmt, "i", $id);
mysqli_stmt_execute($cust_stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($cust_stmt)) === 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Check if customer has existing orders
$sql = "SELECT id
        FROM orders
        WHERE customer_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    $_SESSION["error"] = "Cannot delete customer: This customer has existing orders.";
    header("Location: index.php");
    exit;
}

$sql = "DELETE FROM customers WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

header("Location: index.php");
exit;