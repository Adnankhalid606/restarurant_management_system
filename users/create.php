<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $role = $_POST["role"];


    // Check if email already exists
    $sql = "SELECT id
            FROM users
            WHERE email = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {

        $error = "Email already exists.";

    } else {

       
        // Create user
        $sql = "INSERT INTO users (name, email, password, role)
                VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $name,
            $email,
            $password,
            $role
        );

        mysqli_stmt_execute($stmt);

        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create User</title>
</head>

<body>

    <h1>Create User</h1>

    <?php if ($error !== "") { ?>

        <p>
            <strong><?php echo $error; ?></strong>
        </p>

    <?php } ?>

    <form method="POST">

        <label>Name</label>
        <br>

        <input type="text" name="name" required>

        <br><br>


        <label>Email</label>
        <br>

        <input type="email" name="email" required>

        <br><br>


        <label>Password</label>
        <br>

        <input type="password" name="password" required>

        <br><br>


        <label>Role</label>
        <br>

        <select name="role" required>

            <option value="admin">Admin</option>
            <option value="waiter">Waiter</option>
            <option value="kitchen">Kitchen</option>

        </select>

        <br><br>

        <button type="submit">
            Create User
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Users
    </a>

</body>

</html>