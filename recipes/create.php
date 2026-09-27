<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$menu_items = mysqli_query(
    $conn,
    "SELECT id, name
     FROM menu_items
     WHERE is_available = 1
     ORDER BY name ASC"
);


$materials = mysqli_query(
    $conn,
    "SELECT id, name, unit
     FROM raw_materials
     ORDER BY name ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $menu_item_id = $_POST["menu_item_id"];
    $raw_material_ids = $_POST["raw_material_id"];
    $quantities = $_POST["quantity"];


    mysqli_begin_transaction($conn);

    try {

        // Create recipe

        $sql = "INSERT INTO recipes
                (menu_item_id)
                VALUES (?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $menu_item_id
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Failed to create recipe.");
        }

        $recipe_id = mysqli_insert_id($conn);


        // Create recipe items

        for ($i = 0; $i < count($raw_material_ids); $i++) {

            $raw_material_id = $raw_material_ids[$i];
            $quantity = $quantities[$i];


            $sql = "INSERT INTO recipe_items
                    (recipe_id, raw_material_id, quantity)
                    VALUES (?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "iid",
                $recipe_id,
                $raw_material_id,
                $quantity
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to add recipe ingredient.");
            }
        }


        // Everything successful

        mysqli_commit($conn);


        header("Location: view.php?id=" . $recipe_id);
        exit;
    } catch (Exception $error) {

        mysqli_rollback($conn);

        die($error->getMessage());
    }
}

?>

<!DOCTYPE html>
<html>

<head>

    <title>Create Recipe</title>

</head>

<body>

    <h1>Create Recipe</h1>


    <form method="POST">

        <label>Menu Item</label>
        <br>

        <select name="menu_item_id" required>

            <option value="">
                Select Menu Item
            </option>

            <?php while ($item = mysqli_fetch_assoc($menu_items)) { ?>

                <option value="<?php echo $item["id"]; ?>">
                    <?php echo $item["name"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <h2>Ingredients</h2>


        <div id="ingredients">

            <div class="ingredient-row">

                <select name="raw_material_id[]" required>

                    <option value="">
                        Select Raw Material
                    </option>

                    <?php

                    mysqli_data_seek($materials, 0);

                    while ($material = mysqli_fetch_assoc($materials)) {

                    ?>

                        <option value="<?php echo $material["id"]; ?>">

                            <?php echo $material["name"]; ?>

                            (<?php echo $material["unit"]; ?>)

                        </option>

                    <?php } ?>

                </select>


                <input
                    type="number"
                    name="quantity[]"
                    step="0.001"
                    min="0.001"
                    placeholder="Quantity"
                    required>

            </div>

        </div>


        <br>

        <button
            type="button"
            onclick="addIngredient()">
            Add Ingredient
        </button>


        <br><br>


        <button type="submit">
            Create Recipe
        </button>

    </form>


    <br>

    <a href="index.php">
        Back to Recipes
    </a>


    <script>
        function addIngredient() {

            const ingredients = document.getElementById("ingredients");

            const firstRow = document.querySelector(".ingredient-row");

            const newRow = firstRow.cloneNode(true);


            newRow.querySelector("select").value = "";

            newRow.querySelector("input").value = "";


            ingredients.appendChild(newRow);
        }
    </script>

</body>

</html>