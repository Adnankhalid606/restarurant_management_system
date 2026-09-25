<?php

require_once "../config/database.php";

$id = $_GET["id"];

mysqli_begin_transaction($conn);

try {

    /*
     * Get purchase items
     */

    $sql = "SELECT
                raw_material_id,
                quantity

            FROM purchase_items

            WHERE purchase_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    /*
     * Reverse inventory stock
     */

    while ($item = mysqli_fetch_assoc($result)) {

        $sql = "UPDATE raw_materials

                SET current_stock =
                    current_stock - ?

                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "di",
            $item["quantity"],
            $item["raw_material_id"]
        );

        mysqli_stmt_execute($stmt);
    }

    /*
     * Delete inventory transactions
     */

    $sql = "DELETE
            FROM inventory_transactions

            WHERE reference_type = 'purchase'

            AND reference_id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $id
    );

    mysqli_stmt_execute($stmt);

    /*
     * Delete purchase items
     */

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

    /*
     * Delete purchase
     */

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