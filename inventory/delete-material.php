<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: materials.php");
    exit;
}

// Check if material exists
$check_stmt = mysqli_prepare($conn, "SELECT id FROM raw_materials WHERE id = ?");
mysqli_stmt_bind_param($check_stmt, "i", $id);
mysqli_stmt_execute($check_stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) === 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: materials.php");
    exit;
}

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
    $_SESSION["error"] = "Cannot delete raw material: This material is used in existing recipes.";
    header("Location: materials.php");
    exit;
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
    $_SESSION["error"] = "Cannot delete raw material: This material is referenced in purchase records.";
    header("Location: materials.php");
    exit;
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
    $_SESSION["error"] = "Cannot delete raw material: This material has recorded inventory transactions.";
    header("Location: materials.php");
    exit;
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