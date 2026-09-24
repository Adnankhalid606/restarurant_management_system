<?php

require_once "../config/database.php";

$id = $_GET["id"];

$sql = "SELECT * FROM restaurant_tables WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$table = mysqli_fetch_assoc($result);

if (!$table) {
    die("Table not found.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $table_number = $_POST["table_number"];
    $capacity = $_POST["capacity"];
    $status = $_POST["status"];

    $sql = "UPDATE restaurant_tables
            SET table_number = ?, capacity = ?, status = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iisi",
        $table_number,
        $capacity,
        $status,
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
    <title>Edit Restaurant Table</title>
</head>
<body>

    <h1>Edit Restaurant Table</h1>

    <form method="POST">

        <label>Table Number</label>
        <br>

        <input
            type="number"
            name="table_number"
            value="<?php echo $table["table_number"]; ?>"
            min="1"
            required
        >

        <br><br>

        <label>Capacity</label>
        <br>

        <input
            type="number"
            name="capacity"
            value="<?php echo $table["capacity"]; ?>"
            min="1"
            required
        >

        <br><br>

        <label>Status</label>
        <br>

        <select name="status">

            <option
                value="available"
                <?php if ($table["status"] === "available") echo "selected"; ?>
            >
                Available
            </option>

            <option
                value="occupied"
                <?php if ($table["status"] === "occupied") echo "selected"; ?>
            >
                Occupied
            </option>

            <option
                value="reserved"
                <?php if ($table["status"] === "reserved") echo "selected"; ?>
            >
                Reserved
            </option>

        </select>

        <br><br>

        <button type="submit">
            Update Table
        </button>

    </form>

    <br>

    <a href="index.php">Back to Tables</a>

</body>
</html>