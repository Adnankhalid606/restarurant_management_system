<?php

require_once "../includes/auth.php";

?>

<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
</head>

<body>

<h1>Restaurant Dashboard</h1>

<p>
    Welcome,
    <strong><?php echo $_SESSION["user_name"]; ?></strong>
</p>

<p>
    Role:
    <strong><?php echo $_SESSION["role"]; ?></strong>
</p>

<a href="../auth/logout.php">Logout</a>

</body>

</html>