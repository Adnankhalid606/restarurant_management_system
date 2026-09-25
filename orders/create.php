<?php

require_once "../includes/role.php";
require_once "../config/database.php";

requireRole(["admin", "waiter"]);

$error = "";


//Fetch all customers for dropdown
$customers = mysqli_query(
    $conn,
    "SELECT id, name, phone
     FROM customers
     ORDER BY name ASC"
);


//fetch tables that are currently available
$tables = mysqli_query(
    $conn,
    "SELECT id, table_number, capacity
     FROM restaurant_tables
     WHERE status = 'available'
     ORDER BY table_number ASC"
);

//All available menu items
$menu_items = mysqli_query(
    $conn,
    "SELECT id, name, price
     FROM menu_items
     WHERE is_available = 1
     ORDER BY name ASC"
);


//All active waiters
$waiters = mysqli_query(
    $conn,
    "SELECT id, name
     FROM users
     WHERE role = 'waiter'
     AND is_active = 1
     ORDER BY name ASC"
);




if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = !empty($_POST["customer_id"])
        ? (int) $_POST["customer_id"]
        : null;

    $order_type = $_POST["order_type"] ?? "";

    $table_id = !empty($_POST["table_id"])
        ? (int) $_POST["table_id"]
        : null;


    if ($_SESSION["role"] === "waiter") {

        // Waiter automatically gets assigned to their own order.
        $waiter_id = (int) $_SESSION["user_id"];

    } else {

        // Admin must select a waiter.
        $waiter_id = !empty($_POST["waiter_id"])
            ? (int) $_POST["waiter_id"]
            : null;

        if (!$waiter_id) {
            $error = "Please select a waiter.";
        }
    }


    if ($error === "") {

        $allowed_order_types = [
            "dine_in",
            "delivery",
            "pickup"
        ];

        if (!in_array($order_type, $allowed_order_types)) {
            $error = "Invalid order type.";
        }
    }



    if ($error === "" && $order_type === "dine_in") {

        if (!$table_id) {
            $error = "Please select a table.";
        }
    }



    if ($order_type !== "dine_in") {
        $table_id = null;
    }



    $menu_item_ids = $_POST["menu_item_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if ($error === "" && empty($menu_item_ids)) {
        $error = "Please add at least one menu item.";
    }


    if ($error === "") {

        $sql = "SELECT id
                FROM users
                WHERE id = ?
                AND role = 'waiter'
                AND is_active = 1";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $waiter_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) === 0) {
            $error = "Selected waiter is invalid or inactive.";
        }
    }



    if ($error === "" && $order_type === "dine_in") {

        $today = date("Y-m-d");
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
            $today,
            $current_time,
            $current_time
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $error = "This table is currently reserved.";
        }
    }


    if ($error === "") {

        mysqli_begin_transaction($conn);

        try {

            $total_amount = 0;



            $verified_items = [];

            for ($i = 0; $i < count($menu_item_ids); $i++) {

                $menu_item_id = (int) $menu_item_ids[$i];
                $quantity = (int) $quantities[$i];

                if ($menu_item_id <= 0 || $quantity <= 0) {
                    throw new Exception("Invalid menu item or quantity.");
                }

                $sql = "SELECT id, price
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
                    throw new Exception("Menu item is unavailable.");
                }

                $unit_price = (float) $menu_item["price"];

                $subtotal = $unit_price * $quantity;

                $total_amount += $subtotal;

                $verified_items[] = [
                    "menu_item_id" => $menu_item_id,
                    "quantity" => $quantity,
                    "unit_price" => $unit_price,
                    "subtotal" => $subtotal
                ];
            }



            if ($order_type === "dine_in") {

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            table_id,
                            waiter_id,
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
                            ?,
                            'pending',
                            'unpaid',
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiisd",
                    $customer_id,
                    $table_id,
                    $waiter_id,
                    $order_type,
                    $total_amount
                );

            } else {

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            table_id,
                            waiter_id,
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
                    $waiter_id,
                    $order_type,
                    $total_amount
                );
            }

            mysqli_stmt_execute($stmt);

            $order_id = mysqli_insert_id($conn);



            foreach ($verified_items as $item) {

                $sql = "INSERT INTO order_items
                        (
                            order_id,
                            menu_item_id,
                            quantity,
                            unit_price,
                            subtotal
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )";

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


            // Occupy Table

            if ($order_type === "dine_in") {

                $sql = "UPDATE restaurant_tables
                        SET status = 'occupied'
                        WHERE id = ?
                        AND status = 'available'";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $table_id
                );

                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) === 0) {
                    throw new Exception(
                        "The selected table is no longer available."
                    );
                }
            }


            mysqli_commit($conn);

            header(
                "Location: view.php?id=" . $order_id
            );

            exit;

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
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

        <p style="color: red;">
            <strong>
                <?php echo htmlspecialchars($error); ?>
            </strong>
        </p>

    <?php } ?>

    <form method="POST">

        <label>
            Customer:
        </label>

        <select name="customer_id">

            <option value="">
                Walk-in Customer
            </option>

            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>

                <option value="<?php echo $customer["id"]; ?>">

                    <?php
                    echo htmlspecialchars($customer["name"]);

                    if (!empty($customer["phone"])) {
                        echo " - " . htmlspecialchars($customer["phone"]);
                    }
                    ?>

                </option>

            <?php } ?>

        </select>

        <br><br>


        <?php if ($_SESSION["role"] === "admin") { ?>

            <label>
                Waiter:
            </label>

            <select name="waiter_id" required>

                <option value="">
                    Select Waiter
                </option>

                <?php while ($waiter = mysqli_fetch_assoc($waiters)) { ?>

                    <option value="<?php echo $waiter["id"]; ?>">

                        <?php echo htmlspecialchars($waiter["name"]); ?>

                    </option>

                <?php } ?>

            </select>

            <br><br>

        <?php } else { ?>

            <p>
                <strong>
                    Waiter:
                </strong>

                <?php echo htmlspecialchars($_SESSION["user_name"]); ?>
            </p>

        <?php } ?>




        <label>
            Order Type:
        </label>

        <select name="order_type" id="orderType" required>

            <option value="">
                Select Order Type
            </option>

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




        <div id="tableSection" style="display: none;">

            <label>
                Table:
            </label>

            <select name="table_id">

                <option value="">
                    Select Table
                </option>

                <?php while ($table = mysqli_fetch_assoc($tables)) { ?>

                    <option value="<?php echo $table["id"]; ?>">

                        Table
                        <?php echo htmlspecialchars($table["table_number"]); ?>

                        |
                        Capacity:
                        <?php echo htmlspecialchars($table["capacity"]); ?>

                    </option>

                <?php } ?>

            </select>

            <br><br>

        </div>




        <h3>Order Items</h3>

        <div id="itemsContainer">

            <div class="order-item">

                <select name="menu_item_id[]" required>

                    <option value="">
                        Select Menu Item
                    </option>

                    <?php
                    mysqli_data_seek($menu_items, 0);
                    ?>

                    <?php while ($item = mysqli_fetch_assoc($menu_items)) { ?>

                        <option value="<?php echo $item["id"]; ?>">

                            <?php echo htmlspecialchars($item["name"]); ?>

                            -
                            <?php echo number_format(
                                $item["price"],
                                2
                            ); ?>

                        </option>

                    <?php } ?>

                </select>

                <input type="number" name="quantity[]" min="1" value="1" required>

            </div>

        </div>

        <br>

        <button type="button" onclick="addItem()">
            Add Another Item
        </button>

        <br><br>

        <button type="submit">
            Create Order
        </button>

    </form>


    <script>



        const orderType = document.getElementById("orderType");

        const tableSection = document.getElementById("tableSection");

        orderType.addEventListener("change", function () {

            if (this.value === "dine_in") {

                tableSection.style.display = "block";

            } else {

                tableSection.style.display = "none";
            }

        });


        function addItem() {

            const container =
                document.getElementById("itemsContainer");

            const firstItem =
                document.querySelector(".order-item");

            const newItem =
                firstItem.cloneNode(true);

            newItem.querySelector(
                'select[name="menu_item_id[]"]'
            ).value = "";

            newItem.querySelector(
                'input[name="quantity[]"]'
            ).value = 1;

            container.appendChild(newItem);
        }

    </script>

</body>

</html>