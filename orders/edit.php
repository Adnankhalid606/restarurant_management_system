<?php

require_once "../config/database.php";

$id = $_GET["id"];


$sql = "SELECT *
        FROM orders
        WHERE id = ?";

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
     ORDER BY table_number ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = $_POST["customer_id"];
    $table_id = $_POST["table_id"];
    $order_type = $_POST["order_type"];
    $status = $_POST["status"];
    $payment_status = $_POST["payment_status"];


    /*
     * Inventory should be consumed only when
     * the order changes to completed for the first time.
     */

    $should_consume_inventory = (
        $order["status"] !== "completed"
        &&
        $status === "completed"
    );


    /*
     * Table should become available when
     * the order is completed or cancelled.
     */

    $should_release_table = (
        $order["status"] !== "completed"
        &&
        $order["status"] !== "cancelled"
        &&
        (
            $status === "completed"
            ||
            $status === "cancelled"
        )
    );


    mysqli_begin_transaction($conn);


    try {

        /*
         * Consume inventory
         */

        if ($should_consume_inventory) {

            $sql = "SELECT
                        order_items.menu_item_id,
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

            $order_items = mysqli_stmt_get_result($stmt);


            while ($order_item = mysqli_fetch_assoc($order_items)) {

                /*
                 * Find recipe ingredients
                 */

                $sql = "SELECT
                            recipe_items.raw_material_id,
                            recipe_items.quantity,
                            raw_materials.name AS material_name,
                            raw_materials.current_stock

                        FROM recipes

                        JOIN recipe_items
                            ON recipes.id = recipe_items.recipe_id

                        JOIN raw_materials
                            ON recipe_items.raw_material_id = raw_materials.id

                        WHERE recipes.menu_item_id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $order_item["menu_item_id"]
                );

                mysqli_stmt_execute($stmt);

                $ingredients = mysqli_stmt_get_result($stmt);


                if (mysqli_num_rows($ingredients) === 0) {

                    throw new Exception(
                        "Recipe not found for "
                        . $order_item["menu_item_name"]
                    );
                }


                while ($ingredient = mysqli_fetch_assoc($ingredients)) {

                    $required_quantity =
                        $ingredient["quantity"]
                        *
                        $order_item["quantity"];


                    /*
                     * Check available stock
                     */

                    if (
                        $ingredient["current_stock"]
                        <
                        $required_quantity
                    ) {

                        throw new Exception(
                            "Not enough "
                            . $ingredient["material_name"]
                            . " in stock."
                        );
                    }


                    /*
                     * Decrease stock
                     */

                    $sql = "UPDATE raw_materials

                            SET current_stock =
                                current_stock - ?

                            WHERE id = ?";

                    $stmt = mysqli_prepare($conn, $sql);

                    mysqli_stmt_bind_param(
                        $stmt,
                        "di",
                        $required_quantity,
                        $ingredient["raw_material_id"]
                    );

                    mysqli_stmt_execute($stmt);


                    /*
                     * Record consumption
                     */

                    $sql = "INSERT INTO inventory_transactions
                            (
                                raw_material_id,
                                type,
                                quantity,
                                reference_type,
                                reference_id
                            )

                            VALUES
                            (
                                ?,
                                'consumption',
                                ?,
                                'order',
                                ?
                            )";

                    $stmt = mysqli_prepare($conn, $sql);

                    mysqli_stmt_bind_param(
                        $stmt,
                        "idi",
                        $ingredient["raw_material_id"],
                        $required_quantity,
                        $id
                    );

                    mysqli_stmt_execute($stmt);
                }
            }
        }


        /*
         * Update order
         */

        $sql = "UPDATE orders

                SET customer_id = ?,
                    table_id = ?,
                    order_type = ?,
                    status = ?,
                    payment_status = ?

                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "iisssi",
            $customer_id,
            $table_id,
            $order_type,
            $status,
            $payment_status,
            $id
        );

        mysqli_stmt_execute($stmt);


        /*
         * Release table
         */

        if ($should_release_table && $order["table_id"]) {

            $sql = "UPDATE restaurant_tables

                    SET status = 'available'

                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $order["table_id"]
            );

            mysqli_stmt_execute($stmt);
        }


        mysqli_commit($conn);


        header("Location: view.php?id=" . $id);
        exit;


    } catch (Exception $error) {

        mysqli_rollback($conn);

        die($error->getMessage());
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Order</title>
</head>

<body>

    <h1>
        Edit Order #<?php echo $order["id"]; ?>
    </h1>


    <form method="POST">

        <label>Customer</label>
        <br>

        <select name="customer_id" required>

            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>

                <option
                    value="<?php echo $customer["id"]; ?>"
                    <?php
                    if ($customer["id"] == $order["customer_id"]) {
                        echo "selected";
                    }
                    ?>
                >
                    <?php echo $customer["name"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Table</label>
        <br>

        <select name="table_id" required>

            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>

                <option
                    value="<?php echo $table["id"]; ?>"
                    <?php
                    if ($table["id"] == $order["table_id"]) {
                        echo "selected";
                    }
                    ?>
                >
                    Table <?php echo $table["table_number"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Order Type</label>
        <br>

        <select name="order_type">

            <option
                value="dine_in"
                <?php
                if ($order["order_type"] === "dine_in") {
                    echo "selected";
                }
                ?>
            >
                Dine In
            </option>

            <option
                value="delivery"
                <?php
                if ($order["order_type"] === "delivery") {
                    echo "selected";
                }
                ?>
            >
                Delivery
            </option>

            <option
                value="pickup"
                <?php
                if ($order["order_type"] === "pickup") {
                    echo "selected";
                }
                ?>
            >
                Pickup
            </option>

        </select>

        <br><br>


        <label>Status</label>
        <br>

        <select name="status">

            <option
                value="pending"
                <?php if ($order["status"] === "pending") echo "selected"; ?>
            >
                Pending
            </option>

            <option
                value="preparing"
                <?php if ($order["status"] === "preparing") echo "selected"; ?>
            >
                Preparing
            </option>

            <option
                value="ready"
                <?php if ($order["status"] === "ready") echo "selected"; ?>
            >
                Ready
            </option>

            <option
                value="completed"
                <?php if ($order["status"] === "completed") echo "selected"; ?>
            >
                Completed
            </option>

            <option
                value="cancelled"
                <?php if ($order["status"] === "cancelled") echo "selected"; ?>
            >
                Cancelled
            </option>

        </select>

        <br><br>


        <label>Payment Status</label>
        <br>

        <select name="payment_status">

            <option
                value="unpaid"
                <?php
                if ($order["payment_status"] === "unpaid") {
                    echo "selected";
                }
                ?>
            >
                Unpaid
            </option>

            <option
                value="paid"
                <?php
                if ($order["payment_status"] === "paid") {
                    echo "selected";
                }
                ?>
            >
                Paid
            </option>

        </select>

        <br><br>


        <button type="submit">
            Update Order
        </button>

    </form>


    <br>

    <a href="view.php?id=<?php echo $id; ?>">
        Back to Order
    </a>

</body>

</html>