<?php

session_start();

require_once "../config/database.php";

$error = "";
if (isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"];
    $password = $_POST["password"];

    $sql = "SELECT id, name, email, password, role, is_active
            FROM users
            WHERE email = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $user = mysqli_fetch_assoc($result);

    if (!$user) {

        $error = "Invalid email or password.";

    } elseif (!$user["is_active"]) {

        $error = "Your account is inactive.";

    } elseif ($password !== $user["password"]) {

        $error = "Invalid email or password.";

    } else {

        session_regenerate_id(true);

        $_SESSION["user_id"] = $user["id"];
        $_SESSION["user_name"] = $user["name"];
        $_SESSION["user_email"] = $user["email"];
        $_SESSION["role"] = $user["role"];

        header("Location: ../index.php");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Login</title>
</head>

<body>

<h1>Login</h1>

<?php if ($error !== "") { ?>

<p>
    <strong><?php echo $error; ?></strong>
</p>

<?php } ?>


<form method="POST">

    <label>Email</label>
    <br>

    <input
        type="email"
        name="email"
        required
    >

    <br><br>

    <label>Password</label>
    <br>

    <input
        type="password"
        name="password"
        required
    >

    <br><br>

    <button type="submit">
        Login
    </button>

</form>

</body>

</html>