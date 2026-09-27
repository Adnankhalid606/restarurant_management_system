<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if (!isset($_GET["id"])) {
    die("Customer ID is required.");
}

$customer_id = (int) $_GET["id"];

// Get customer
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM customers
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    die("Customer not found.");
}

// Get customer orders
$sql = "
    SELECT
        orders.id,
        orders.created_at,
        orders.order_type,
        COALESCE(bills.total_amount, orders.total_amount) AS total_amount,
        orders.payment_status
    FROM orders
    LEFT JOIN bills
        ON orders.id = bills.order_id
    WHERE orders.customer_id = ?
    AND orders.status = 'completed'
    ORDER BY orders.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$orders = [];

$total_billed = 0;
$total_paid = 0;
$total_outstanding = 0;

// Calculate totals
while ($row = mysqli_fetch_assoc($result)) {

    $orders[] = $row;

    $total_billed += $row["total_amount"];

    if ($row["payment_status"] === "paid") {
        $total_paid += $row["total_amount"];
    } else {
        $total_outstanding += $row["total_amount"];
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Customer Ledger</title>
</head>

<body>

    <h1>Customer Ledger</h1>

    <a href="index.php">Back to Customer Ledger</a>

    <hr>

    <h2>
        <?php echo htmlspecialchars($customer["name"]); ?>
    </h2>

    <p>
        Phone:
        <?php echo htmlspecialchars($customer["phone"] ?? "N/A"); ?>
    </p>

    <p>
        Address:
        <?php echo htmlspecialchars($customer["address"] ?? "N/A"); ?>
    </p>

    <hr>

    <h2>Summary</h2>

    <p>
        Total Billed:
        <strong>
            Rs. <?php echo number_format($total_billed, 2); ?>
        </strong>
    </p>

    <p>
        Total Paid:
        <strong>
            Rs. <?php echo number_format($total_paid, 2); ?>
        </strong>
    </p>

    <p>
        Outstanding:
        <strong>
            Rs. <?php echo number_format($total_outstanding, 2); ?>
        </strong>
    </p>

    <hr>

    <h2>Transactions</h2>

    <?php if (empty($orders)) { ?>

        <p>No completed orders found.</p>

    <?php } else { ?>

        <table border="1" cellpadding="8">

            <tr>
                <th>Order ID</th>
                <th>Date</th>
                <th>Order Type</th>
                <th>Amount</th>
                <th>Payment Status</th>
            </tr>

            <?php foreach ($orders as $row) { ?>

                <tr>

                    <td>
                        #<?php echo $row["id"]; ?>
                    </td>

                    <td>
                        <?php echo $row["created_at"]; ?>
                    </td>

                    <td>
                        <?php echo ucfirst(str_replace("_", " ", $row["order_type"])); ?>
                    </td>

                    <td>
                        Rs. <?php echo number_format($row["total_amount"], 2); ?>
                    </td>

                    <td>
                        <?php echo ucfirst($row["payment_status"]); ?>
                    </td>

                </tr>

            <?php } ?>

        </table>

    <?php } ?>

</body>

</html>