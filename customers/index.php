<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT * FROM customers ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Customers</title>
</head>
<body>

    <h1>Customers</h1>

    <a href="create.php">Add Customer</a>

    <br><br>

    <table border="1" cellpadding="10">
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Actions</th>
        </tr>

        <?php while ($customer = mysqli_fetch_assoc($result)) { ?>

            <tr>
                <td><?php echo htmlspecialchars($customer["id"]); ?></td>
                <td><?php echo htmlspecialchars($customer["name"]); ?></td>
                <td><?php echo htmlspecialchars($customer["phone"]); ?></td>
                <td><?php echo htmlspecialchars($customer["address"]); ?></td>

                <td>
                    <a href="edit.php?id=<?php echo htmlspecialchars($customer["id"]); ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo htmlspecialchars($customer["id"]); ?>">
                        Delete
                    </a>
                </td>
            </tr>

        <?php } ?>

    </table>

</body>
</html>