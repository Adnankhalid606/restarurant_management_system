<?php

require_once "../config/database.php";

$sql = "SELECT *
        FROM suppliers
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Suppliers</title>
</head>

<body>

    <h1>Suppliers</h1>

    <a href="create.php">
        Add Supplier
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Actions</th>
        </tr>

        <?php while ($supplier = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $supplier["id"]; ?>
                </td>

                <td>
                    <?php echo $supplier["name"]; ?>
                </td>

                <td>
                    <?php echo $supplier["phone"]; ?>
                </td>

                <td>
                    <?php echo $supplier["address"]; ?>
                </td>

                <td>

                    <a href="edit.php?id=<?php echo $supplier["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $supplier["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>