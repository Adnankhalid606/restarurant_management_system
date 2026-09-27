<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

$sql = "SELECT * FROM menu_items WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$item = mysqli_fetch_assoc($result);

if (!$item) {
    die("Menu item not found.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $category = $_POST["category"];
    $price = $_POST["price"];
    $is_available = $_POST["is_available"];

    $sql = "UPDATE menu_items
            SET name = ?, category = ?, price = ?, is_available = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdii",
        $name,
        $category,
        $price,
        $is_available,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Menu Item</title>
</head>
<body>

    <h1>Edit Menu Item</h1>

    <form method="POST">

        <label>Name</label>
        <br>
        <input
            type="text"
            name="name"
            value="<?php echo $item["name"]; ?>"
            required
        >

        <br><br>

        <label>Category</label>
        <br>
        <input
            type="text"
            name="category"
            value="<?php echo $item["category"]; ?>"
        >

        <br><br>

        <label>Price</label>
        <br>
        <input
            type="number"
            name="price"
            value="<?php echo $item["price"]; ?>"
            step="0.01"
            required
        >

        <br><br>

        <label>Availability</label>
        <br>

        <select name="is_available">

            <option
                value="1"
                <?php if ($item["is_available"] == 1) echo "selected"; ?>
            >
                Available
            </option>

            <option
                value="0"
                <?php if ($item["is_available"] == 0) echo "selected"; ?>
            >
                Unavailable
            </option>

        </select>

        <br><br>

        <button type="submit">Update Menu Item</button>

    </form>

    <br>

    <a href="index.php">Back to Menu</a>

</body>
</html>