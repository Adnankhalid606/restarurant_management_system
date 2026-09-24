<?php

require_once "../config/database.php";

$sql = "SELECT * FROM menu_items ORDER BY id DESC";

$result = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Menu Items</title>
</head>
<body>

    <h1>Menu Items</h1>

    <a href="create.php">Add Menu Item</a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Category</th>
            <th>Price</th>
            <th>Availability</th>
            <th>Actions</th>
        </tr>

        <?php while ($item = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td><?php echo $item["id"]; ?></td>

                <td><?php echo $item["name"]; ?></td>

                <td><?php echo $item["category"]; ?></td>

                <td><?php echo $item["price"]; ?></td>

                <td>
                    <?php
                    if ($item["is_available"]) {
                        echo "Available";
                    } else {
                        echo "Unavailable";
                    }
                    ?>
                </td>

                <td>

                    <a href="edit.php?id=<?php echo $item["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $item["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>
</html>