<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

$error = "";


// Get user
$sql = "SELECT id, name, email, role, is_active
        FROM users
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User not found.");
}


// Update user
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $role = $_POST["role"];
    $is_active = $_POST["is_active"];
    $password = $_POST["password"];


    // Check if another user already uses this email
    $sql = "SELECT id
            FROM users
            WHERE email = ?
            AND id != ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "si", $email, $id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {

        $error = "Email already exists.";

    } else {

        // Update password only if a new password was entered
        if ($password !== "") {

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $sql = "UPDATE users
                    SET name = ?,
                        email = ?,
                        password = ?,
                        role = ?,
                        is_active = ?
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "ssssii",
                $name,
                $email,
                $hashed_password,
                $role,
                $is_active,
                $id
            );

        } else {

            $sql = "UPDATE users
                    SET name = ?,
                        email = ?,
                        role = ?,
                        is_active = ?
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "sssii",
                $name,
                $email,
                $role,
                $is_active,
                $id
            );
        }

        mysqli_stmt_execute($stmt);

        header("Location: index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Edit User</title>
</head>

<body>

    <h1>Edit User</h1>

    <?php if ($error !== "") { ?>

        <p>
            <strong><?php echo $error; ?></strong>
        </p>

    <?php } ?>

    <form method="POST">

        <label>Name</label>
        <br>

        <input type="text" name="name" value="<?php echo htmlspecialchars($user["name"]); ?>" required>

        <br><br>


        <label>Email</label>
        <br>

        <input type="email" name="email" value="<?php echo htmlspecialchars($user["email"]); ?>" required>

        <br><br>


        <label>New Password</label>
        <br>

        <input type="password" name="password">

        <p>
            Leave empty if you don't want to change the password.
        </p>


        <label>Role</label>
        <br>

        <select name="role" required>

            <option value="admin" <?php if ($user["role"] === "admin") {
                echo "selected";
            } ?>>
                Admin
            </option>

            <option value="waiter" <?php if ($user["role"] === "waiter") {
                echo "selected";
            } ?>>
                Waiter
            </option>

            <option value="kitchen" <?php if ($user["role"] === "kitchen") {
                echo "selected";
            } ?>>
                Kitchen
            </option>

        </select>

        <br><br>


        <label>Status</label>
        <br>

        <select name="is_active" required>

            <option value="1" <?php if ($user["is_active"] == 1) {
                echo "selected";
            } ?>>
                Active
            </option>

            <option value="0" <?php if ($user["is_active"] == 0) {
                echo "selected";
            } ?>>
                Inactive
            </option>

        </select>

        <br><br>

        <button type="submit">
            Update User
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Users
    </a>

</body>

</html>