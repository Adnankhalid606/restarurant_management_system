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

// Get bill
$sql = "SELECT id, payment_status
        FROM bills
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$bill = mysqli_fetch_assoc($result);

if (!$bill) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Do not allow deleting paid bills
if ($bill["payment_status"] === "paid") {
    $_SESSION["error"] = "Paid bill cannot be deleted.";
    header("Location: index.php");
    exit;
}


// Delete bill
$sql = "DELETE FROM bills
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);


// Go back to bills
header("Location: index.php");
exit;