<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = $_GET["id"];

$sql = "SELECT * FROM customers WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    die("Customer not found.");
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];

    $sql = "UPDATE customers
            SET name = ?, phone = ?, address = ?
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
    <title>Edit Customer</title>
</head>

<body>

    <h1>Edit Customer</h1>

    <form method="POST">

        <label>Name</label>
        <br>
        <input
            type="text"
            name="name"
            value="<?php echo htmlspecialchars($customer["name"]); ?>"
            required
        >

        <br><br>

        <label>Phone</label>
        <br>
        <input
            type="text"
            name="phone"
            value="<?php echo htmlspecialchars($customer["phone"]); ?>"
        >

        <br><br>

        <label>Address</label>
        <br>
        <input
            type="text"
            name="address"
            value="<?php echo htmlspecialchars($customer["address"]); ?>"
        >

        <br><br>

        <button type="submit">Update Customer</button>

    </form>

    <br>

    <a href="index.php">Back to Customers</a>

</body>
</html>