<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

// Get salaries
$sql = "
    SELECT
        salaries.id,
        users.name AS employee_name,
        salaries.amount,
        salaries.salary_type,
        salaries.salary_date,
        salaries.payment_status,
        salaries.description
    FROM salaries
    INNER JOIN users
        ON salaries.user_id = users.id
    ORDER BY salaries.salary_date DESC
";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Salary Management</title>
</head>

<body>

    <h1>Salary Management</h1>

    <a href="../dashboard/index.php">Back to Dashboard</a>

    |

    <a href="create.php">Add Salary</a>

    <hr>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Employee</th>
            <th>Amount</th>
            <th>Type</th>
            <th>Date</th>
            <th>Payment Status</th>
            <th>Description</th>
            <th>Actions</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $row["id"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["employee_name"]); ?>
                </td>

                <td>
                    Rs. <?php echo number_format($row["amount"], 2); ?>
                </td>

                <td>
                    <?php echo ucfirst($row["salary_type"]); ?>
                </td>

                <td>
                    <?php echo $row["salary_date"]; ?>
                </td>

                <td>
                    <?php echo ucfirst($row["payment_status"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["description"] ?? "N/A"); ?>
                </td>

                <td>

                    <a href="edit.php?id=<?php echo $row["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a
                        href="delete.php?id=<?php echo $row["id"]; ?>"
                        onclick="return confirm('Are you sure you want to delete this salary record?');"
                    >
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>