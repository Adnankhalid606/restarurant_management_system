<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter", "kitchen"]);

$sql = "SELECT * FROM restaurant_tables ORDER BY table_number ASC";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Restaurant Tables</title>
</head>
<body>

    <h1>Restaurant Tables</h1>

    <a href="create.php">Add Table</a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Table Number</th>
            <th>Capacity</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>

        <?php while ($table = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $table["id"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($table["capacity"], ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($table["status"], ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>

                    <a href="edit.php?id=<?php echo $table["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $table["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>
</html>