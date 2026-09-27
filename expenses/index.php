<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$sql = "SELECT *
        FROM expenses
        ORDER BY expense_date DESC, id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Expenses</title>
</head>

<body>

    <h1>Expenses</h1>

    <a href="create.php">Add Expense</a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Expense Head</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Expense Date</th>
            <th>Actions</th>
        </tr>

        <?php while ($expense = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $expense["id"]; ?>
                </td>

                <td>
                    <?php echo $expense["expense_head"]; ?>
                </td>

                <td>
                    <?php echo $expense["description"]; ?>
                </td>

                <td>
                    <?php echo $expense["amount"]; ?>
                </td>

                <td>
                    <?php echo $expense["expense_date"]; ?>
                </td>

                <td>

                    <a href="edit.php?id=<?php echo $expense["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $expense["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>