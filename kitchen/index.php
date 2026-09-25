<?php

require_once "../config/database.php";


$sql = "SELECT
            orders.id,
            orders.order_type,
            orders.status,
            orders.created_at,
            customers.name AS customer_name

        FROM orders

        LEFT JOIN customers
            ON orders.customer_id = customers.id

        WHERE orders.status IN ('pending', 'preparing', 'ready')

        ORDER BY orders.id ASC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Kitchen Orders</title>
</head>

<body>

    <h1>Kitchen Orders</h1>

    <table border="1" cellpadding="10">

        <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Order Type</th>
            <th>Status</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>

        <?php while ($order = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    #<?php echo $order["id"]; ?>
                </td>

                <td>
                    <?php echo $order["customer_name"] ?? "Walk-in"; ?>
                </td>

                <td>
                    <?php echo $order["order_type"]; ?>
                </td>

                <td>
                    <?php echo $order["status"]; ?>
                </td>

                <td>
                    <?php echo $order["created_at"]; ?>
                </td>

                <td>

                    <a href="view.php?id=<?php echo $order["id"]; ?>">
                        View
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>