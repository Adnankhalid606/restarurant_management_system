<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

// Check if menu item is referenced in order items
$sql = "SELECT id
        FROM order_items
        WHERE menu_item_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    die("Cannot delete menu item: This item is referenced in existing orders.");
}

// Check if menu item has a recipe
$sql = "SELECT id
        FROM recipes
        WHERE menu_item_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    die("Cannot delete menu item: This item has an associated recipe.");
}

// Fetch image filename for cleanup if delete succeeds
$sql = "SELECT image FROM menu_items WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
$image_filename = $row ? ($row["image"] ?? null) : null;

$sql = "DELETE FROM menu_items WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {
    if (mysqli_stmt_affected_rows($stmt) > 0 && !empty($image_filename)) {
        $file_path = dirname(__DIR__) . "/assets/uploads/menu/" . $image_filename;
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
    }
}

header("Location: index.php");
exit;