<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$sql = "SELECT *
        FROM raw_materials
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Raw Materials</title>
</head>

<body>

    <h1>Raw Materials</h1>

    <a href="create-material.php">
        Add Raw Material
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>

            <th>ID</th>
            <th>Name</th>
            <th>Unit</th>
            <th>Current Stock</th>
            <th>Minimum Stock</th>
            <th>Status</th>
            <th>Actions</th>

        </tr>

        <?php while ($material = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $material["id"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($material["name"], ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($material["unit"], ENT_QUOTES, 'UTF-8'); ?>
                </td>

                <td>
                    <?php echo $material["current_stock"]; ?>
                </td>

                <td>
                    <?php echo $material["minimum_stock"]; ?>
                </td>
                <td>
                    <?php

                    if ($material["current_stock"] <= $material["minimum_stock"]) {
                        echo "Low Stock";
                    } else {
                        echo "Stock OK";
                    }

                    ?>
                </td>

                <td>

                    <a href="edit-material.php?id=<?php echo $material["id"]; ?>">
                        Edit
                    </a>

                    |

                    <a href="delete-material.php?id=<?php echo $material["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>