<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

// Check if raw material is used in recipes
$sql = "SELECT id
        FROM recipe_items
        WHERE raw_material_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    die("Cannot delete raw material: This material is used in existing recipes.");
}

// Check if raw material is used in purchase items
$sql = "SELECT id
        FROM purchase_items
        WHERE raw_material_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    die("Cannot delete raw material: This material is referenced in purchase records.");
}

// Check if raw material has inventory transactions
$sql = "SELECT id
        FROM inventory_transactions
        WHERE raw_material_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    die("Cannot delete raw material: This material has recorded inventory transactions.");
}

$sql = "DELETE FROM raw_materials
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);


header("Location: materials.php");
exit;