<?php

require_once "../config/database.php";

$id = $_GET["id"];


$sql = "SELECT *
        FROM suppliers
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$supplier = mysqli_fetch_assoc($result);


if (!$supplier) {
    die("Supplier not found.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];


    $sql = "UPDATE suppliers

            SET name = ?,
                phone = ?,
                address = ?

            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $name,
        $phone,
        $address,
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
    <title>Edit Supplier</title>
</head>

<body>

    <h1>Edit Supplier</h1>

    <form method="POST">

        <label>Name</label>
        <br>

        <input
            type="text"
            name="name"
            value="<?php echo $supplier["name"]; ?>"
            required
        >

        <br><br>


        <label>Phone</label>
        <br>

        <input
            type="text"
            name="phone"
            value="<?php echo $supplier["phone"]; ?>"
            required
        >

        <br><br>


        <label>Address</label>
        <br>

        <textarea
            name="address"
            required
        ><?php echo $supplier["address"]; ?></textarea>

        <br><br>


        <button type="submit">
            Update Supplier
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Suppliers
    </a>

</body>

</html>