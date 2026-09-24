<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $table_number = $_POST["table_number"];
    $capacity = $_POST["capacity"];
    $status = $_POST["status"];

    $sql = "INSERT INTO restaurant_tables
            (table_number, capacity, status)
            VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iis",
        $table_number,
        $capacity,
        $status
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Restaurant Table</title>
</head>
<body>

    <h1>Add Restaurant Table</h1>

    <form method="POST">

        <label>Table Number</label>
        <br>

        <input
            type="number"
            name="table_number"
            min="1"
            required
        >

        <br><br>

        <label>Capacity</label>
        <br>

        <input
            type="number"
            name="capacity"
            min="1"
            required
        >

        <br><br>

        <label>Status</label>
        <br>

        <select name="status">

            <option value="available">
                Available
            </option>

            <option value="occupied">
                Occupied
            </option>

            <option value="reserved">
                Reserved
            </option>

        </select>

        <br><br>

        <button type="submit">
            Save Table
        </button>

    </form>

    <br>

    <a href="index.php">Back to Tables</a>

</body>
</html>