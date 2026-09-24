<?php

require_once "../config/database.php";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $unit = $_POST["unit"];
    $current_stock = $_POST["current_stock"];
    $minimum_stock = $_POST["minimum_stock"];


    $sql = "INSERT INTO raw_materials
            (name, unit, current_stock, minimum_stock)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdd",
        $name,
        $unit,
        $current_stock,
        $minimum_stock
    );

    mysqli_stmt_execute($stmt);


    header("Location: materials.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Raw Material</title>
</head>

<body>

    <h1>Add Raw Material</h1>

    <form method="POST">

        <label>Name</label>
        <br>

        <input
            type="text"
            name="name"
            required
        >

        <br><br>


        <label>Unit</label>
        <br>

        <select name="unit" required>

            <option value="">
                Select Unit
            </option>

            <option value="kg">
                Kilogram (kg)
            </option>

            <option value="liter">
                Liter
            </option>

            <option value="gram">
                Gram (g)
            </option>

            <option value="piece">
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
            value="0"
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
            value="0"
            required
        >

        <br><br>


        <button type="submit">
            Add Raw Material
        </button>

    </form>

    <br>

    <a href="materials.php">
        Back to Raw Materials
    </a>

</body>

</html>