<?php

require_once "../config/database.php";

$id = $_GET["id"];

$sql = "SELECT *
        FROM expenses
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$expense = mysqli_fetch_assoc($result);

if (!$expense) {
    die("Expense not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $expense_head = $_POST["expense_head"];
    $description = $_POST["description"];
    $amount = $_POST["amount"];
    $expense_date = $_POST["expense_date"];

    $sql = "UPDATE expenses

            SET expense_head = ?,
                description = ?,
                amount = ?,
                expense_date = ?

            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdsi",
        $expense_head,
        $description,
        $amount,
        $expense_date,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Expense</title>
</head>

<body>

    <h1>Edit Expense</h1>

    <form method="POST">

        <label>Expense Head</label>
        <br>

        <input
            type="text"
            name="expense_head"
            value="<?php echo $expense["expense_head"]; ?>"
            required
        >

        <br><br>

        <label>Description</label>
        <br>

        <textarea name="description"><?php echo $expense["description"]; ?></textarea>

        <br><br>

        <label>Amount</label>
        <br>

        <input
            type="number"
            name="amount"
            step="0.01"
            value="<?php echo $expense["amount"]; ?>"
            required
        >

        <br><br>

        <label>Expense Date</label>
        <br>

        <input
            type="date"
            name="expense_date"
            value="<?php echo $expense["expense_date"]; ?>"
            required
        >

        <br><br>

        <button type="submit">
            Update Expense
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Expenses
    </a>

</body>

</html>