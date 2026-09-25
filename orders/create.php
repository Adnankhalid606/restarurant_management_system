<?php

require_once "../config/database.php";

$customers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM customers
     ORDER BY name ASC"
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

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = $_POST["customer_id"];
    $order_type = $_POST["order_type"];

    $table_id = null;

    if ($order_type === "dine_in") {

        $table_id = $_POST["table_id"];

        if (empty($table_id)) {

            $error = "Please select a table.";

        }

    }

    $menu_item_ids = $_POST["menu_item_id"];
    $quantities = $_POST["quantity"];

    if ($error === "") {

        if (
            empty($menu_item_ids)
            ||
            empty($quantities)
        ) {

            $error = "Please add at least one menu item.";

        } elseif (count($menu_item_ids) !== count($quantities)) {

            $error = "Invalid order items.";

        }

    }

    /*
     * Check reservation for dine-in order
     */

    if ($error === "" && $order_type === "dine_in") {

        $current_date = date("Y-m-d");
        $current_time = date("H:i:s");

        $sql = "SELECT id
                FROM reservations

                WHERE table_id = ?

                AND reservation_date = ?

                AND status IN ('pending', 'confirmed')

                AND reservation_time <= ?

                AND reservation_end_time > ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "isss",
            $table_id,
            $current_date,
            $current_time,
            $current_time
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {

            $error = "This table is currently reserved.";

        }

    }

    /*
     * Create order
     */

    if ($error === "") {

        mysqli_begin_transaction($conn);

        try {

            $total_amount = 0;

            $items = [];

            /*
             * Get official prices from database
             */

            foreach ($menu_item_ids as $index => $menu_item_id) {

                $quantity = $quantities[$index];

                if ($quantity < 1) {

                    throw new Exception(
                        "Quantity must be at least 1."
                    );
                }

                $sql = "SELECT
                            id,
                            name,
                            price

                        FROM menu_items

                        WHERE id = ?

                        AND is_available = 1";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $menu_item_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                $menu_item = mysqli_fetch_assoc($result);

                if (!$menu_item) {

                    throw new Exception(
                        "Menu item not found or unavailable."
                    );
                }

                $unit_price = $menu_item["price"];

                $subtotal = $unit_price * $quantity;

                $total_amount += $subtotal;

                $items[] = [
                    "menu_item_id" => $menu_item_id,
                    "quantity" => $quantity,
                    "unit_price" => $unit_price,
                    "subtotal" => $subtotal
                ];
            }

            /*
             * Create order
             */

            if ($order_type === "dine_in") {

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            table_id,
                            order_type,
                            status,
                            payment_status,
                            total_amount
                        )

                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            'pending',
                            'unpaid',
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisd",
                    $customer_id,
                    $table_id,
                    $order_type,
                    $total_amount
                );

            } else {

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            table_id,
                            order_type,
                            status,
                            payment_status,
                            total_amount
                        )

                        VALUES
                        (
                            ?,
                            NULL,
                            ?,
                            'pending',
                            'unpaid',
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "isd",
                    $customer_id,
                    $order_type,
                    $total_amount
                );
            }

            mysqli_stmt_execute($stmt);

            $order_id = mysqli_insert_id($conn);

            /*
             * Insert all order items
             */

            foreach ($items as $item) {

                $sql = "INSERT INTO order_items
                        (
                            order_id,
                            menu_item_id,
                            quantity,
                            unit_price,
                            subtotal
                        )

                        VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiidd",
                    $order_id,
                    $item["menu_item_id"],
                    $item["quantity"],
                    $item["unit_price"],
                    $item["subtotal"]
                );

                mysqli_stmt_execute($stmt);
            }

            /*
             * Occupy table for dine-in
             */

            if ($order_type === "dine_in") {

                $sql = "UPDATE restaurant_tables
                        SET status = 'occupied'
                        WHERE id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $table_id
                );

                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);

            header("Location: view.php?id=" . $order_id);
            exit;

        } catch (Exception $error) {

            mysqli_rollback($conn);

            $error = $error->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Order</title>
</head>

<body>

    <h1>Create Order</h1>

    <?php if ($error !== "") { ?>

        <p>
            <strong>
                <?php echo $error; ?>
            </strong>
        </p>

    <?php } ?>

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

        <label>Order Type</label>
        <br>

        <select
            name="order_type"
            id="orderType"
        >

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

        <div id="tableField">

            <label>Table</label>
            <br>

            <select name="table_id" id="tableId">

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

        </div>

        <h2>Order Items</h2>

        <div id="orderItems">

            <div class="order-item">

                <label>Menu Item</label>
                <br>

                <select name="menu_item_id[]" required>

                    <option value="">
                        Select Menu Item
                    </option>

                    <?php
                    mysqli_data_seek($menu_items, 0);
                    ?>

                    <?php while ($menu_item = mysqli_fetch_assoc($menu_items)) { ?>

                        <option value="<?php echo $menu_item["id"]; ?>">
                            <?php echo $menu_item["name"]; ?>
                            - <?php echo $menu_item["price"]; ?>
                        </option>

                    <?php } ?>

                </select>

                <br>

                <label>Quantity</label>
                <br>

                <input
                    type="number"
                    name="quantity[]"
                    min="1"
                    value="1"
                    required
                >

                <br><br>

            </div>

        </div>

        <button
            type="button"
            onclick="addItem()"
        >
            Add Another Item
        </button>

        <br><br>

        <button type="submit">
            Create Order
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Orders
    </a>

    <script>

        const orderType = document.getElementById("orderType");

        const tableField = document.getElementById("tableField");

        const tableId = document.getElementById("tableId");

        function updateTableField() {

            if (orderType.value === "dine_in") {

                tableField.style.display = "block";

                tableId.required = true;

            } else {

                tableField.style.display = "none";

                tableId.required = false;

                tableId.value = "";
            }
        }

        orderType.addEventListener(
            "change",
            updateTableField
        );

        updateTableField();


        function addItem() {

            const orderItems =
                document.getElementById("orderItems");

            const firstItem =
                document.querySelector(".order-item");

            const newItem =
                firstItem.cloneNode(true);

            newItem
                .querySelector("select")
                .value = "";

            newItem
                .querySelector("input")
                .value = 1;

            orderItems.appendChild(newItem);
        }

    </script>

</body>

</html>