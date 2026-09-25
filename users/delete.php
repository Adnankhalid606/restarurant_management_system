<?php

require_once "../includes/role.php";

require_once "../config/database.php";
requireRole(["admin"]);

$id = $_GET["id"];


// Prevent deleting your own account
if (isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $id) {
    die("You cannot delete your own account.");
}


// Check if user exists
$sql = "SELECT id
        FROM users
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User not found.");
}


// Delete user
$sql = "DELETE FROM users
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);


// Go back to users
header("Location: index.php");
exit;