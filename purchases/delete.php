<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid purchase ID.");
}

// Check inventory transaction

$sql = "SELECT id
        FROM inventory_transactions
        WHERE reference_type = 'purchase'
        AND reference_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {

    die(
        "This purchase cannot be deleted because it has inventory movements."
    );
}


// Start transaction

mysqli_begin_transaction($conn);

try {

    // Delete purchase items

    $sql = "DELETE
            FROM purchase_items
            WHERE purchase_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);


    // Delete purchase

    $sql = "DELETE
            FROM purchases
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);


    mysqli_commit($conn);

    header("Location: index.php");
    exit;

} catch (Exception $error) {

    mysqli_rollback($conn);

    die($error->getMessage());
}