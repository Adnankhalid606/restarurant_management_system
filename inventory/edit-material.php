<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];


$sql = "SELECT *
        FROM raw_materials
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$material = mysqli_fetch_assoc($result);


if (!$material) {
    die("Raw material not found.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $unit = $_POST["unit"];
    $current_stock = $_POST["current_stock"];
    $minimum_stock = $_POST["minimum_stock"];


    $sql = "UPDATE raw_materials

            SET name = ?,
                unit = ?,
                current_stock = ?,
                minimum_stock = ?

            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssddi",
        $name,
        $unit,
        $current_stock,
        $minimum_stock,
        $id
    );

    mysqli_stmt_execute($stmt);


    header("Location: materials.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit Raw Material</title>
</head>

<body>

    <h1>
        Edit Raw Material
    </h1>

    <form method="POST">

        <label>Name</label>
        <br>

        <input
            type="text"
            name="name"
            value="<?php echo $material["name"]; ?>"
            required
        >

        <br><br>


        <label>Unit</label>
        <br>

        <select name="unit" required>

            <option
                value="kg"
                <?php
                if ($material["unit"] === "kg") {
                    echo "selected";
                }
                ?>
            >
                Kilogram (kg)
            </option>

            <option
                value="liter"
                <?php
                if ($material["unit"] === "liter") {
                    echo "selected";
                }
                ?>
            >
                Liter
            </option>

            <option
                value="gram"
                <?php
                if ($material["unit"] === "gram") {
                    echo "selected";
                }
                ?>
            >
                Gram (g)
            </option>

            <option
                value="piece"
                <?php
                if ($material["unit"] === "piece") {
                    echo "selected";
                }
                ?>
            >
                Piece
            </option>

        </select>

        <br><br>


        <label>Current Stock</label>
        <br>

        <input
            type="number"
            name="current_stock"
            step="0.001"
            min="0"
            value="<?php echo $material["current_stock"]; ?>"
            required
        >

        <br><br>


        <label>Minimum Stock</label>
        <br>

        <input
            type="number"
            name="minimum_stock"
            step="0.001"
            min="0"
            value="<?php echo $material["minimum_stock"]; ?>"
            required
        >

        <br><br>


        <button type="submit">
            Update Raw Material
        </button>

    </form>

    <br>

    <a href="materials.php">
        Back to Raw Materials
    </a>

</body>

</html>