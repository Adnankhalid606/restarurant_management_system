
<?php

require_once "../config/database.php";

$sql = "SELECT
            orders.id,
            customers.name AS customer_name,
            restaurant_tables.table_number,
            users.name AS waiter_name,
            orders.order_type,
            orders.status,
            orders.payment_status,
            orders.total_amount,
            orders.created_at

        FROM orders

        LEFT JOIN customers
            ON orders.customer_id = customers.id

        LEFT JOIN restaurant_tables
            ON orders.table_id = restaurant_tables.id

        LEFT JOIN users
            ON orders.waiter_id = users.id

        ORDER BY orders.id DESC";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Orders</title>
</head>
<body>

    <h1>Orders</h1>

    <a href="create.php">Create Order</a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Customer</th>
            <th>Table</th>
            <th>Waiter</th>
            <th>Order Type</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Total</th>
            <th>Actions</th>
        </tr>

        <?php while ($order = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $order["id"]; ?>
                </td>

                <td>
                    <?php echo $order["customer_name"] ?? "Walk-in"; ?>
                </td>

                <td>
                    <?php echo $order["table_number"] ?? "-"; ?>
                </td>

                <td>
                    <?php echo $order["waiter_name"] ?? "-"; ?>
                </td>

                <td>
                    <?php echo $order["order_type"]; ?>
                </td>

                <td>
                    <?php echo $order["status"]; ?>
                </td>

                <td>
                    <?php echo $order["payment_status"]; ?>
                </td>

                <td>
                    <?php echo $order["total_amount"]; ?>
                </td>

                <td>

                    <a href="view.php?id=<?php echo $order["id"]; ?>">
                        View
                    </a>

                    |

                    <a href="edit.php?id=<?php echo $order["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $order["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>
</html>