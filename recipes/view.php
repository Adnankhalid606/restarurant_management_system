<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$id = $_GET["id"];


$sql = "SELECT
            recipes.id,
            menu_items.name AS menu_item_name,
            menu_items.price,
            recipes.created_at

        FROM recipes

        JOIN menu_items
            ON recipes.menu_item_id = menu_items.id

        WHERE recipes.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$recipe = mysqli_fetch_assoc($result);


if (!$recipe) {
    die("Recipe not found.");
}


$sql = "SELECT
            recipe_items.quantity,
            raw_materials.name AS material_name,
            raw_materials.unit

        FROM recipe_items

        JOIN raw_materials
            ON recipe_items.raw_material_id = raw_materials.id

        WHERE recipe_items.recipe_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html>

<head>
    <title>View Recipe</title>
</head>

<body>

    <h1>
        Recipe #<?php echo $recipe["id"]; ?>
    </h1>


    <p>
        <strong>Menu Item:</strong>
        <?php echo $recipe["menu_item_name"]; ?>
    </p>

    <p>
        <strong>Selling Price:</strong>
        Rs. <?php echo $recipe["price"]; ?>
    </p>


    <h2>Ingredients</h2>

    <table border="1" cellpadding="10">

        <tr>
            <th>Raw Material</th>
            <th>Quantity</th>
        </tr>

        <?php while ($item = mysqli_fetch_assoc($items)) { ?>

            <tr>

                <td>
                    <?php echo $item["material_name"]; ?>
                </td>

                <td>
                    <?php echo $item["quantity"]; ?>
                    <?php echo $item["unit"]; ?>
                </td>

            </tr>

        <?php } ?>

    </table>

    <br>

    <a href="index.php">
        Back to Recipes
    </a>

</body>

</html>