<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid order ID.");
}


// Get order

$sql = "SELECT
            orders.id,
            orders.order_type,
            orders.status,
            orders.created_at,
            customers.name AS customer_name,
            restaurant_tables.table_number,
            users.name AS waiter_name

        FROM orders

        LEFT JOIN customers
            ON orders.customer_id = customers.id

        LEFT JOIN restaurant_tables
            ON orders.table_id = restaurant_tables.id

        LEFT JOIN users
            ON orders.waiter_id = users.id

        WHERE orders.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Order not found.");
}


// Get order items

$sql = "SELECT
            order_items.quantity,
            menu_items.name AS menu_item_name

        FROM order_items

        JOIN menu_items
            ON order_items.menu_item_id = menu_items.id

        WHERE order_items.order_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Kitchen Order</title>
</head>

<body>

    <h1>
        Kitchen Order #<?php echo $order["id"]; ?>
    </h1>


    <p>

        <strong>
            Customer:
        </strong>

        <?php
        echo $order["customer_name"] ?? "Walk-in";
        ?>

    </p>


    <p>

        <strong>
            Table:
        </strong>

        <?php
        echo $order["table_number"] ?? "-";
        ?>

    </p>


    <p>

        <strong>
            Waiter:
        </strong>

        <?php
        echo $order["waiter_name"] ?? "-";
        ?>

    </p>


    <p>

        <strong>
            Order Type:
        </strong>

        <?php
        echo $order["order_type"];
        ?>

    </p>


    <p>

        <strong>
            Status:
        </strong>

        <?php
        echo $order["status"];
        ?>

    </p>


    <a href="update-status.php?id=<?php echo $order["id"]; ?>">
        Update Status
    </a>


    <p>

        <strong>
            Created:
        </strong>

        <?php
        echo $order["created_at"];
        ?>

    </p>


    <h2>
        Items to Prepare
    </h2>


    <table border="1" cellpadding="10">

        <tr>

            <th>
                Food Item
            </th>

            <th>
                Quantity
            </th>

        </tr>


        <?php while ($item = mysqli_fetch_assoc($items)) { ?>

            <tr>

                <td>
                    <?php
                    echo $item["menu_item_name"];
                    ?>
                </td>

                <td>
                    <?php
                    echo $item["quantity"];
                    ?>
                </td>

            </tr>

        <?php } ?>

    </table>


    <br>


    <a href="index.php">
        Back to Kitchen
    </a>

</body>

</html>