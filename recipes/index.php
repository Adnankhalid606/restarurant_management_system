<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$sql = "SELECT
            recipes.id,
            menu_items.name AS menu_item_name,
            recipes.created_at

        FROM recipes

        JOIN menu_items
            ON recipes.menu_item_id = menu_items.id

        ORDER BY recipes.id DESC";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html>

<head>
    <title>Recipes</title>
</head>

<body>

    <h1>Recipes</h1>

    <a href="create.php">
        Create Recipe
    </a>

    <br><br>

    <table border="1" cellpadding="10">

        <tr>
            <th>ID</th>
            <th>Menu Item</th>
            <th>Created At</th>
            <th>Actions</th>
        </tr>

        <?php while ($recipe = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $recipe["id"]; ?>
                </td>

                <td>
                    <?php echo $recipe["menu_item_name"]; ?>
                </td>

                <td>
                    <?php echo $recipe["created_at"]; ?>
                </td>

                <td>

                    <a href="view.php?id=<?php echo $recipe["id"]; ?>">
                        View
                    </a>

                    |

                    <a href="delete.php?id=<?php echo $recipe["id"]; ?>">
                        Delete
                    </a>

                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>