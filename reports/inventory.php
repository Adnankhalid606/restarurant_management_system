<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

$sql = "
    SELECT
        id,
        name,
        unit,
        current_stock,
        minimum_stock
    FROM raw_materials
    ORDER BY name ASC
";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Inventory Report</title>
</head>

<body>

    <h1>Inventory Report</h1>

    <p>
        <a href="index.php">Back to Reports</a>
    </p>

    <hr>

    <table border="1" cellpadding="8">

        <tr>
            <th>Raw Material</th>
            <th>Unit</th>
            <th>Current Stock</th>
            <th>Minimum Stock</th>
            <th>Status</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)) { ?>

            <?php
            $status = "Normal";

            if ($row["current_stock"] <= $row["minimum_stock"]) {
                $status = "Low Stock";
            }
            ?>

            <tr>

                <td>
                    <?php echo htmlspecialchars($row["name"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["unit"]); ?>
                </td>

                <td>
                    <?php echo $row["current_stock"]; ?>
                </td>

                <td>
                    <?php echo $row["minimum_stock"]; ?>
                </td>

                <td>
                    <?php echo $status; ?>
                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>