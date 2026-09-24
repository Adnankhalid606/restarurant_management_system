<?php

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];

    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "sss",
        $name,
        $phone,
        $address
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Customer</title>
</head>
<body>

    <h1>Add Customer</h1>

    <form method="POST">

        <label>Name</label>
        <br>
        <input type="text" name="name" required>

        <br><br>

        <label>Phone</label>
        <br>
        <input type="text" name="phone">

        <br><br>

        <label>Address</label>
        <br>
        <input type="text" name="address">

        <br><br>

        <button type="submit">Save Customer</button>

    </form>

    <br>

    <a href="index.php">Back to Customers</a>

</body>
</html>