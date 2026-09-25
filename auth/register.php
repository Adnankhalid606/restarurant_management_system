<?php

require_once "../config/database.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];

    // Check if email already exists
    $sql = "SELECT id
            FROM users
            WHERE email = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "s",
        $email
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);


    if (mysqli_num_rows($result) > 0) {

        $error = "Email already exists.";

    } else {

        // Hash password
        $hashed_password = password_hash(
            $password,
            PASSWORD_DEFAULT
        );


        // Create admin account
        $role = "admin";


        $sql = "INSERT INTO users
                (
                    name,
                    email,
                    password,
                    role
                )
                VALUES (?, ?, ?, ?)";


        $stmt = mysqli_prepare($conn, $sql);


        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $name,
            $email,
            $hashed_password,
            $role
        );


        mysqli_stmt_execute($stmt);


        // Redirect to login
        header("Location: login.php?registered=1");
        exit;
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Admin Account</title>
</head>

<body>

<h1>Create Admin Account</h1>


<?php if ($error !== "") { ?>

    <p>
        <strong>
            <?php echo $error; ?>
        </strong>
    </p>

<?php } ?>


<form method="POST">

    <label>Name</label>
    <br>

    <input
        type="text"
        name="name"
        required
    >

    <br><br>


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
        Create Admin Account
    </button>

</form>


<br>

<a href="login.php">
    Back to Login
</a>

</body>

</html>