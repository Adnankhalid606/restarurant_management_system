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
        purchases.id,
        purchases.purchase_date,
        suppliers.name AS supplier_name,
        purchases.total_amount,
        purchases.payment_status
    FROM purchases
    LEFT JOIN suppliers
        ON purchases.supplier_id = suppliers.id
    WHERE purchases.purchase_date BETWEEN ? AND ?
    ORDER BY purchases.purchase_date DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "ss", $from, $to);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_purchases = 0;
$total_amount = 0;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Purchase Report</title>
</head>

<body>

    <h1>Purchase Report</h1>

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
            required
        >

        <label for="to">To:</label>

        <input
            type="date"
            id="to"
            name="to"
            value="<?php echo htmlspecialchars($to); ?>"
            required
        >

        <button type="submit">Generate Report</button>

    </form>

    <hr>

    <h2>Purchase Summary</h2>

    <p>
        Total Purchases:
        <strong><?php echo $total_purchases; ?></strong>
    </p>

    <p>
        Total Amount:
        <strong>Rs. <?php echo number_format($total_amount, 2); ?></strong>
    </p>

    <hr>

    <h2>Purchase Details</h2>

    <table border="1" cellpadding="8">

        <tr>
            <th>Purchase ID</th>
            <th>Date</th>
            <th>Supplier</th>
            <th>Payment Status</th>
            <th>Total Amount</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)) { ?>

            <?php
            $total_purchases++;
            $total_amount += $row["total_amount"];
            ?>

            <tr>

                <td>
                    <?php echo $row["id"]; ?>
                </td>

                <td>
                    <?php echo $row["purchase_date"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["supplier_name"] ?? "Unknown"); ?>
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