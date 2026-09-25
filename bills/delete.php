<?php

require_once "../config/database.php";

$id = $_GET["id"];


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
    die("Bill not found.");
}


// Do not allow deleting paid bills
if ($bill["payment_status"] === "paid") {
    die("Paid bill cannot be deleted.");
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