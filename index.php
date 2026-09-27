<?php

require_once "./includes/auth.php";

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Restaurant Dashboard</title>
</head>

<body>

    <h1>Restaurant Dashboard</h1>

    <p>
        Welcome, <strong><?php echo htmlspecialchars($_SESSION["user_name"], ENT_QUOTES, 'UTF-8'); ?></strong>
    </p>

    <p>
        Role: <strong><?php echo htmlspecialchars($_SESSION["role"], ENT_QUOTES, 'UTF-8'); ?></strong>
    </p>

    <hr>

    <h2>Management</h2>

    <ul>
        <?php if ($_SESSION["role"] === "admin") { ?>
            <li><a href="./users/index.php">Users</a></li>
        <?php } ?>

        <li><a href="./customers/index.php">Customers</a></li>
        <li><a href="./menu/index.php">Menu Items</a></li>
        <li><a href="./table/index.php">Restaurant Tables</a></li>
        <li><a href="./reservations/index.php">Reservations</a></li>
    </ul>

    <h2>Sales & Orders</h2>

    <ul>
        <?php if (
            $_SESSION["role"] === "admin" ||
            $_SESSION["role"] === "waiter"
        ) { ?>
            <li><a href="./orders/index.php">Orders</a></li>
        <?php } ?>

        <li><a href="./bills/index.php">Bills</a></li>
    </ul>

    <h2>Kitchen & Inventory</h2>

    <ul>
        <?php if (
            $_SESSION["role"] === "admin" ||
            $_SESSION["role"] === "kitchen"
        ) { ?>
            <li><a href="./kitchen/index.php">Kitchen</a></li>
        <?php } ?>

        <li><a href="./recipes/index.php">Recipes</a></li>
        <li><a href="./inventory/materials.php">Inventory</a></li>
    </ul>

    <h2>Purchasing & Expenses</h2>

    <ul>
        <li><a href="./suppliers/index.php">Suppliers</a></li>
        <li><a href="./purchases/index.php">Purchases</a></li>
        <li><a href="./expenses/index.php">Expenses</a></li>
        <li><a href="./reports/index.php">Reports</a></li>
        <li><a href="./inventory/transactions.php">Inventory Transactions</a></li>
    </ul>

    <hr>

    <p>
        <a href="./auth/logout.php">Logout</a>
    </p>

</body>

</html>