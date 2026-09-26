<?php

require_once "../includes/auth.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Reports</title>
</head>

<body>

    <h1>Reports</h1>

    <p>
        <a href="../dashboard/index.php">Back to Dashboard</a>
    </p>

    <hr>

    <h2>Sales Report</h2>

    <p>
        <a href="sales.php">View Sales Report</a>
    </p>

    <h2>Inventory Report</h2>

    <p>
        <a href="inventory.php">View Inventory Report</a>
    </p>

    <h2>Purchase Report</h2>

    <p>
        <a href="purchases.php">View Purchase Report</a>
    </p>

    <h2>Expense Report</h2>

    <p>
        <a href="expenses.php">View Expense Report</a>
    </p>

    <h2>Profit & Loss</h2>

    <p>
        <a href="profit-loss.php">View Profit & Loss</a>
    </p>

</body>

</html>