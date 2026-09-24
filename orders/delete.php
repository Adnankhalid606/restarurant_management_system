<?php

require_once "../config/database.php";

$id = $_GET["id"];


/*
 * Delete order items first.
 */
$sql = "DELETE FROM order_items WHERE order_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);


/*
 * Delete the order.
 */
$sql = "DELETE FROM orders WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);


header("Location: index.php");
exit;