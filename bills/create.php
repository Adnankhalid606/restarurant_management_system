<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT
            orders.id,
            orders.total_amount,
            orders.status,
            orders.payment_status

        FROM orders

        LEFT JOIN bills
            ON orders.id = bills.order_id

       WHERE bills.id IS NULL

        AND orders.status IN ('ready', 'completed')

        ORDER BY orders.id DESC";

$orders = mysqli_query($conn, $sql);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $order_id = $_POST["order_id"];
    $discount = $_POST["discount"];
    $tax = $_POST["tax"];

    /*
     * Get order
     */

    $sql = "SELECT total_amount
            FROM orders
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $order_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $order = mysqli_fetch_assoc($result);

    if (!$order) {

        $error = "Order not found.";
    } else {

        $subtotal = $order["total_amount"];

        /*
         * Validate discount
         */

        if ($discount < 0) {

            $error = "Discount cannot be negative.";
        } elseif ($discount > $subtotal) {

            $error = "Discount cannot be greater than subtotal.";
        } elseif ($tax < 0) {

            $error = "Tax cannot be negative.";
        }
    }

    if ($error === "") {

        $total_amount =
            $subtotal
            -
            $discount
            +
            $tax;

        /*
         * Create bill
         */

        $sql = "INSERT INTO bills
                (
                    order_id,
                    subtotal,
                    discount,
                    tax,
                    total_amount
                )

                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "idddd",
            $order_id,
            $subtotal,
            $discount,
            $tax,
            $total_amount
        );

        mysqli_stmt_execute($stmt);

        $bill_id = mysqli_insert_id($conn);

        header("Location: view.php?id=" . $bill_id);
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Bill</title>
</head>

<body>

    <h1>Create Bill</h1>

    <?php if ($error !== "") { ?>

        <p>
            <strong>
                <?php echo $error; ?>
            </strong>
        </p>

    <?php } ?>

    <form method="POST">

        <label>Order</label>
        <br>

        <select name="order_id" required>

            <option value="">
                Select Order
            </option>

            <?php while ($order = mysqli_fetch_assoc($orders)) { ?>

                <option value="<?php echo $order["id"]; ?>">

                    Order #<?php echo $order["id"]; ?>

                    -
                    Total:
                    <?php echo $order["total_amount"]; ?>

                    -
                    Status:
                    <?php echo $order["status"]; ?>

                </option>

            <?php } ?>

        </select>

        <br><br>

        <label>Discount</label>
        <br>

        <input
            type="number"
            name="discount"
            step="0.01"
            min="0"
            value="0"
            required>

        <br><br>

        <label>Tax</label>
        <br>

        <input
            type="number"
            name="tax"
            step="0.01"
            min="0"
            value="0"
            required>

        <br><br>

        <button type="submit">
            Create Bill
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Bills
    </a>

</body>

</html>