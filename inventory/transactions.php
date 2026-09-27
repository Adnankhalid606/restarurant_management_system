<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$sql = "SELECT
            inventory_transactions.id,
            raw_materials.name AS material_name,
            raw_materials.unit,
            inventory_transactions.type,
            inventory_transactions.quantity,
            inventory_transactions.created_at

        FROM inventory_transactions

        JOIN raw_materials
            ON inventory_transactions.raw_material_id = raw_materials.id

        ORDER BY inventory_transactions.id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Inventory Transactions</title>
</head>

<body>

    <h1>Inventory Transactions</h1>

    <a href="add-transaction.php">
        Add Transaction
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Raw Material</th>
            <th>Type</th>
            <th>Quantity</th>
            <th>Date</th>
        </tr>

        <?php while ($transaction = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $transaction["id"]; ?>
                </td>

                <td>
                    <?php echo $transaction["material_name"]; ?>
                </td>

                <td>
                    <?php echo $transaction["type"]; ?>
                </td>

                <td>
                    <?php echo $transaction["quantity"]; ?>
                    <?php echo $transaction["unit"]; ?>
                </td>

                <td>
                    <?php echo $transaction["created_at"]; ?>
                </td>

            </tr>

        <?php } ?>

    </table>

    <br>

    <a href="materials.php">
        Back to Raw Materials
    </a>

</body>

</html>