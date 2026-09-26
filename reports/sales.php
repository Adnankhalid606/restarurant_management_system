<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sql = "
    SELECT
        orders.id,
        orders.created_at,
        customers.name AS customer_name,
        orders.order_type,
        orders.status,
        orders.payment_status,
        orders.total_amount
    FROM orders
    LEFT JOIN customers
        ON orders.customer_id = customers.id
    WHERE DATE(orders.created_at) BETWEEN ? AND ?
AND orders.status = 'completed'
ORDER BY orders.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "ss", $from, $to);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_sales = 0;
$total_orders = 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Sales Report</title>
</head>

<body>

    <h1>Sales Report</h1>

    <p>
        <a href="index.php">Back to Reports</a>
    </p>

    <hr>

    <form method="GET">

        <label for="from">From:</label>

        <input
            type="date"
            id="from"
            name="from"
            value="<?php echo htmlspecialchars($from); ?>"
            required>

        <label for="to">To:</label>

        <input
            type="date"
            id="to"
            name="to"
            value="<?php echo htmlspecialchars($to); ?>"
            required>

        <button type="submit">Generate Report</button>

    </form>

    <hr>

    <h2>Sales Summary</h2>

    <p>
        Total Orders:
        <strong><?php echo $total_orders; ?></strong>
    </p>

    <p>
        Total Sales:
        <strong>Rs. <?php echo number_format($total_sales, 2); ?></strong>
    </p>

    <hr>

    <h2>Sales Details</h2>

    <table border="1" cellpadding="8">

        <tr>
            <th>Order ID</th>
            <th>Date</th>
            <th>Customer</th>
            <th>Order Type</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Total</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)) { ?>

            <?php
            $total_orders++;
            $total_sales += $row["total_amount"];
            ?>

            <tr>

                <td>
                    <?php echo $row["id"]; ?>
                </td>

                <td>
                    <?php echo $row["created_at"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["customer_name"] ?? "Walk-in"); ?>
                </td>

                <td>
                    <?php echo $row["order_type"]; ?>
                </td>

                <td>
                    <?php echo $row["status"]; ?>
                </td>

                <td>
                    <?php echo $row["payment_status"]; ?>
                </td>

                <td>
                    Rs. <?php echo number_format($row["total_amount"], 2); ?>
                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>