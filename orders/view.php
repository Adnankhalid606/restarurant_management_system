<?php

require_once "../config/database.php";

$id = $_GET["id"];

$sql = "SELECT
            orders.id,
            customers.name AS customer_name,
            restaurant_tables.table_number,
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

        WHERE orders.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Order not found.");
}


/*
 * Get items belonging to this order.
 */
$sql = "SELECT
            order_items.quantity,
            order_items.unit_price,
            order_items.subtotal,
            menu_items.name AS menu_item_name

        FROM order_items

        JOIN menu_items
            ON order_items.menu_item_id = menu_items.id

        WHERE order_items.order_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html>

<head>
    <title>View Order</title>
</head>

<body>

    <h1>Order #<?php echo $order["id"]; ?></h1>

    <p>
        <strong>Customer:</strong>
        <?php echo $order["customer_name"] ?? "Walk-in"; ?>
    </p>

    <p>
        <strong>Table:</strong>
        <?php echo $order["table_number"] ?? "-"; ?>
    </p>

    <p>
        <strong>Order Type:</strong>
        <?php echo $order["order_type"]; ?>
    </p>

    <p>
        <strong>Status:</strong>
        <?php echo $order["status"]; ?>
    </p>

    <p>
        <strong>Payment:</strong>
        <?php echo $order["payment_status"]; ?>
    </p>

    <p>
        <strong>Created:</strong>
        <?php echo $order["created_at"]; ?>
    </p>


    <h2>Order Items</h2>

    <table border="1" cellpadding="10">

        <tr>
            <th>Item</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Subtotal</th>
        </tr>

        <?php while ($item = mysqli_fetch_assoc($items)) { ?>

            <tr>

                <td>
                    <?php echo $item["menu_item_name"]; ?>
                </td>

                <td>
                    <?php echo $item["quantity"]; ?>
                </td>

                <td>
                    <?php echo $item["unit_price"]; ?>
                </td>

                <td>
                    <?php echo $item["subtotal"]; ?>
                </td>

            </tr>

        <?php } ?>

    </table>


    <h2>
        Total:
        Rs. <?php echo $order["total_amount"]; ?>
    </h2>

    <a href="index.php">
        Back to Orders
    </a>

</body>

</html>