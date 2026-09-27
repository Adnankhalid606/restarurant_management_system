<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];


    $sql = "INSERT INTO suppliers
            (name, phone, address)
            VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

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
    <title>Add Supplier</title>
</head>

<body>

    <h1>Add Supplier</h1>

    <form method="POST">

        <label>Name</label>
        <br>

        <input
            type="text"
            name="name"
            required
        >

        <br><br>


        <label>Phone</label>
        <br>

        <input
            type="text"
            name="phone"
            required
        >

        <br><br>


        <label>Address</label>
        <br>

        <textarea
            name="address"
            required
        ></textarea>

        <br><br>


        <button type="submit">
            Add Supplier
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Suppliers
    </a>

</body>

</html>