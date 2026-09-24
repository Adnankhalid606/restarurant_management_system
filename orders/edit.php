<?php

require_once "../config/database.php";

$id = $_GET["id"];


/*
 * Get existing order.
 */
$sql = "SELECT * FROM orders WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Order not found.");
}


/*
 * Get customers.
 */
$customers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM customers
     ORDER BY name ASC"
);


/*
 * Get tables.
 */
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

    header("Location: view.php?id=" . $id);
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Order</title>
</head>

<body>

    <h1>Edit Order #<?php echo $order["id"]; ?></h1>

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
                <?php if ($order["order_type"] === "dine_in") echo "selected"; ?>
            >
                Dine In
            </option>

            <option
                value="delivery"
                <?php if ($order["order_type"] === "delivery") echo "selected"; ?>
            >
                Delivery
            </option>

            <option
                value="pickup"
                <?php if ($order["order_type"] === "pickup") echo "selected"; ?>
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
                <?php if ($order["payment_status"] === "unpaid") echo "selected"; ?>
            >
                Unpaid
            </option>

            <option
                value="paid"
                <?php if ($order["payment_status"] === "paid") echo "selected"; ?>
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