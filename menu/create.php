<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $category = $_POST["category"];
    $price = $_POST["price"];
    $is_available = $_POST["is_available"];

    $sql = "INSERT INTO menu_items
            (name, category, price, is_available)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdi",
        $name,
        $category,
        $price,
        $is_available
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Menu Item</title>
</head>
<body>

    <h1>Add Menu Item</h1>

    <form method="POST">

        <label>Name</label>
        <br>
        <input type="text" name="name" required>

        <br><br>

        <label>Category</label>
        <br>
        <input type="text" name="category">

        <br><br>

        <label>Price</label>
        <br>
        <input type="number" name="price" step="0.01" required>

        <br><br>

        <label>Availability</label>
        <br>

        <select name="is_available">
            <option value="1">Available</option>
            <option value="0">Unavailable</option>
        </select>

        <br><br>

        <button type="submit">Save Menu Item</button>

    </form>

    <br>

    <a href="index.php">Back to Menu</a>

</body>
</html>