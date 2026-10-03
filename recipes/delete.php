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

// Check if recipe exists
$check_stmt = mysqli_prepare($conn, "SELECT id FROM recipes WHERE id = ?");
mysqli_stmt_bind_param($check_stmt, "i", $id);
mysqli_stmt_execute($check_stmt);
if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) === 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

mysqli_begin_transaction($conn);

try {

    // Delete recipe items

    $sql = "DELETE FROM recipe_items
            WHERE recipe_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to delete recipe ingredients.");
    }


    // Delete recipe

    $sql = "DELETE FROM recipes
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to delete recipe.");
    }


    // Everything successful

    mysqli_commit($conn);


    header("Location: index.php");
    exit;

} catch (Exception $error) {

    mysqli_rollback($conn);

    die($error->getMessage());
}