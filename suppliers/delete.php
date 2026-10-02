<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($id <= 0) {
    header("Location: index.php");
    exit;
}

// 1. Verify supplier existence
$find_sql = "SELECT id, name FROM suppliers WHERE id = ?";
$find_stmt = mysqli_prepare($conn, $find_sql);
mysqli_stmt_bind_param($find_stmt, "i", $id);
mysqli_stmt_execute($find_stmt);
$supplier = mysqli_fetch_assoc(mysqli_stmt_get_result($find_stmt));

if (!$supplier) {
    $_SESSION["error"] = "Supplier record not found.";
    header("Location: index.php");
    exit;
}

// 2. Pre-check for linked purchase orders to prevent Foreign Key constraint crash
$check_sql = "SELECT COUNT(*) AS total FROM purchases WHERE supplier_id = ?";
$check_stmt = mysqli_prepare($conn, $check_sql);
mysqli_stmt_bind_param($check_stmt, "i", $id);
mysqli_stmt_execute($check_stmt);
$purchase_count = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt))["total"] ?? 0);

if ($purchase_count > 0) {
    $_SESSION["error"] = "Cannot delete supplier '" . $supplier["name"] . "' because there are " . $purchase_count . " purchase order(s) linked to this vendor in the ledger.";
    header("Location: index.php");
    exit;
}

// 3. Safe Deletion wrapped in try/catch
try {
    $sql = "DELETE FROM suppliers WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    $_SESSION["success"] = "Supplier '" . $supplier["name"] . "' was removed successfully.";
} catch (mysqli_sql_exception $e) {
    $_SESSION["error"] = "Database error: Cannot delete supplier due to linked records.";
}

header("Location: index.php");
exit;
