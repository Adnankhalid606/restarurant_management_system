<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];


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