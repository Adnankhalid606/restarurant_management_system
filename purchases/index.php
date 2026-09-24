<?php

require_once "../config/database.php";

$sql = "SELECT
            purchases.id,
            suppliers.name AS supplier_name,
            purchases.total_amount,
            purchases.payment_status,
            purchases.purchase_date,
            purchases.created_at

        FROM purchases

        LEFT JOIN suppliers
            ON purchases.supplier_id = suppliers.id

        ORDER BY purchases.id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Purchases</title>
</head>

<body>

    <h1>Purchases</h1>

    <a href="create.php">
        Create Purchase
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Supplier</th>
            <th>Total</th>
            <th>Payment Status</th>
            <th>Purchase Date</th>
            <th>Actions</th>
        </tr>

        <?php while ($purchase = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $purchase["id"]; ?>
                </td>

                <td>
                    <?php echo $purchase["supplier_name"] ?? "-"; ?>
                </td>

                <td>
                    Rs. <?php echo $purchase["total_amount"]; ?>
                </td>

                <td>
                    <?php echo $purchase["payment_status"]; ?>
                </td>

                <td>
                    <?php echo $purchase["purchase_date"]; ?>
                </td>

                <td>

                    <a href="view.php?id=<?php echo $purchase["id"]; ?>">
                        View
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $purchase["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>