<?php

require_once "../config/database.php";

$customers = mysqli_query(
    $conn,
    "SELECT id, name FROM customers ORDER BY name ASC"
);

$tables = mysqli_query(
    $conn,
    "SELECT id, table_number
     FROM restaurant_tables
     WHERE status = 'available'
     ORDER BY table_number ASC"
);

$menu_items = mysqli_query(
    $conn,
    "SELECT id, name, price
     FROM menu_items
     WHERE is_available = 1
     ORDER BY name ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = $_POST["customer_id"];
    $table_id = $_POST["table_id"];
    $order_type = $_POST["order_type"];

    $menu_item_id = $_POST["menu_item_id"];
    $quantity = $_POST["quantity"];

    /*
     * Get the price of the selected menu item.
     */
    $stmt = mysqli_prepare(
        $conn,
        "SELECT price
         FROM menu_items
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $menu_item_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $menu_item = mysqli_fetch_assoc($result);

    if (!$menu_item) {
        die("Menu item not found.");
    }

    $unit_price = $menu_item["price"];

    $subtotal = $unit_price * $quantity;


    /*
     * Create the main order.
     */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO orders
        (customer_id, table_id, order_type, total_amount)
        VALUES (?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iisd",
        $customer_id,
        $table_id,
        $order_type,
        $subtotal
    );

    mysqli_stmt_execute($stmt);


    /*
     * Get the ID of the newly created order.
     */
    $order_id = mysqli_insert_id($conn);


    /*
     * Create the order item.
     */
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO order_items
        (order_id, menu_item_id, quantity, unit_price, subtotal)
        VALUES (?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iiidd",
        $order_id,
        $menu_item_id,
        $quantity,
        $unit_price,
        $subtotal
    );

    mysqli_stmt_execute($stmt);


    /*
     * Mark the table as occupied.
     */
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE restaurant_tables
         SET status = 'occupied'
         WHERE id = ?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $table_id
    );

    mysqli_stmt_execute($stmt);


    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Order</title>
</head>

<body>

    <h1>Create Order</h1>

    <form method="POST">

        <label>Customer</label>
        <br>

        <select name="customer_id" required>

            <option value="">
                Select Customer
            </option>

            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>

                <option value="<?php echo $customer["id"]; ?>">
                    <?php echo $customer["name"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Table</label>
        <br>

        <select name="table_id" required>

            <option value="">
                Select Table
            </option>

            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>

                <option value="<?php echo $table["id"]; ?>">
                    Table <?php echo $table["table_number"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Order Type</label>
        <br>

        <select name="order_type" required>

            <option value="dine_in">
                Dine In
            </option>

            <option value="delivery">
                Delivery
            </option>

            <option value="pickup">
                Pickup
            </option>

        </select>

        <br><br>


        <label>Menu Item</label>
        <br>

        <select name="menu_item_id" required>

            <option value="">
                Select Menu Item
            </option>

            <?php while ($item = mysqli_fetch_assoc($menu_items)) { ?>

                <option value="<?php echo $item["id"]; ?>">
                    <?php echo $item["name"]; ?>
                    - Rs. <?php echo $item["price"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Quantity</label>
        <br>

        <input
            type="number"
            name="quantity"
            min="1"
            value="1"
            required
        >

        <br><br>


        <button type="submit">
            Create Order
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Orders
    </a>

</body>

</html>