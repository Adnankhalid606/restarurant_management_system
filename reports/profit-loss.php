<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sales_sql = "
    SELECT COALESCE(SUM(total_amount), 0) AS total_sales
    FROM orders
    WHERE DATE(created_at) BETWEEN ? AND ?
    AND status = 'completed'
";

$sales_stmt = mysqli_prepare($conn, $sales_sql);

mysqli_stmt_bind_param($sales_stmt, "ss", $from, $to);

mysqli_stmt_execute($sales_stmt);

$sales_result = mysqli_stmt_get_result($sales_stmt);

$sales_row = mysqli_fetch_assoc($sales_result);

$total_sales = $sales_row["total_sales"];


$purchase_sql = "
    SELECT COALESCE(SUM(total_amount), 0) AS total_purchases
    FROM purchases
    WHERE purchase_date BETWEEN ? AND ?
";

$purchase_stmt = mysqli_prepare($conn, $purchase_sql);

mysqli_stmt_bind_param($purchase_stmt, "ss", $from, $to);

mysqli_stmt_execute($purchase_stmt);

$purchase_result = mysqli_stmt_get_result($purchase_stmt);

$purchase_row = mysqli_fetch_assoc($purchase_result);

$total_purchases = $purchase_row["total_purchases"];


$expense_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$expense_stmt = mysqli_prepare($conn, $expense_sql);

mysqli_stmt_bind_param($expense_stmt, "ss", $from, $to);

mysqli_stmt_execute($expense_stmt);

$expense_result = mysqli_stmt_get_result($expense_stmt);

$expense_row = mysqli_fetch_assoc($expense_result);

$total_expenses = $expense_row["total_expenses"];

$salary_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_salaries
    FROM salaries
    WHERE payment_status = 'paid'
    AND salary_date BETWEEN ? AND ?
";

$salary_stmt = mysqli_prepare($conn, $salary_sql);

mysqli_stmt_bind_param(
    $salary_stmt,
    "ss",
    $from,
    $to
);

mysqli_stmt_execute($salary_stmt);

$salary_result = mysqli_stmt_get_result($salary_stmt);

$salary_row = mysqli_fetch_assoc($salary_result);

$total_salaries = $salary_row["total_salaries"];

$net_profit = $total_sales - $total_purchases - $total_expenses - $total_salaries;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Profit & Loss</title>
</head>

<body>

    <h1>Profit & Loss</h1>

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

    <h2>Financial Summary</h2>

    <p>
        Total Sales:
        <strong>Rs. <?php echo number_format($total_sales, 2); ?></strong>
    </p>

    <p>
        Total Purchases:
        <strong>Rs. <?php echo number_format($total_purchases, 2); ?></strong>
    </p>

    <p>
        Total Expenses:
        <strong>Rs. <?php echo number_format($total_expenses, 2); ?></strong>
    </p>

    <hr>
    
    <p>
        Total Salaries:
        <strong>Rs. <?php echo number_format($total_salaries, 2); ?></strong>
    </p>

    <hr>

    <p>
        Net Profit / Loss:
        <strong>Rs. <?php echo number_format($net_profit, 2); ?></strong>
    </p>

</body>

</html>