<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sql = "
    SELECT
        id,
        expense_head,
        description,
        amount,
        expense_date
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    ORDER BY expense_date DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "ss", $from, $to);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_expenses = 0;

$rows = [];

while ($row = mysqli_fetch_assoc($result)) {

    $rows[] = $row;

    $total_expenses += $row["amount"];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Expense Report</title>
</head>

<body>

    <h1>Expense Report</h1>

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

    <h2>Expense Summary</h2>

    <p>
        Total Expenses:
        <strong>Rs. <?php echo number_format($total_expenses, 2); ?></strong>
    </p>

    <hr>

    <h2>Expense Details</h2>

    <table border="1" cellpadding="8">

        <tr>
            <th>Expense ID</th>
            <th>Date</th>
            <th>Expense Head</th>
            <th>Description</th>
            <th>Amount</th>
        </tr>

        <?php foreach ($rows as $row) { ?>

            <tr>

                <td>
                    <?php echo $row["id"]; ?>
                </td>

                <td>
                    <?php echo $row["expense_date"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["expense_head"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["description"]); ?>
                </td>

                <td>
                    Rs. <?php echo number_format($row["amount"], 2); ?>
                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>