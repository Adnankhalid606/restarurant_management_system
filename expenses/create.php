<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $expense_head = $_POST["expense_head"];
    $description = $_POST["description"];
    $amount = $_POST["amount"];
    $expense_date = $_POST["expense_date"];

    $sql = "INSERT INTO expenses
            (
                expense_head,
                description,
                amount,
                expense_date
            )
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssds",
        $expense_head,
        $description,
        $amount,
        $expense_date
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Expense</title>
</head>

<body>

    <h1>Add Expense</h1>

    <form method="POST">

        <label>Expense Head</label>
        <br>

        <input
            type="text"
            name="expense_head"
            placeholder="e.g. Electricity"
            required
        >

        <br><br>

        <label>Description</label>
        <br>

        <textarea
            name="description"
            placeholder="Enter expense details"
        ></textarea>

        <br><br>

        <label>Amount</label>
        <br>

        <input
            type="number"
            name="amount"
            step="0.01"
            required
        >

        <br><br>

        <label>Expense Date</label>
        <br>

        <input
            type="date"
            name="expense_date"
            required
        >

        <br><br>

        <button type="submit">
            Add Expense
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Expenses
    </a>

</body>

</html>