<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

// Get suppliers
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM suppliers
    ORDER BY name ASC
";

$result = mysqli_query($conn, $sql);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Supplier Ledger</title>
</head>

<body>

    <h1>Supplier Ledger</h1>

    <a href="../index.php">Back to Dashboard</a>

    <hr>

    <table border="1" cellpadding="8">

        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Address</th>
            <th>Action</th>
        </tr>

        <?php while ($row = mysqli_fetch_assoc($result)) { ?>

            <tr>

                <td>
                    <?php echo $row["id"]; ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["name"]); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["phone"] ?? "N/A"); ?>
                </td>

                <td>
                    <?php echo htmlspecialchars($row["address"] ?? "N/A"); ?>
                </td>

                <td>
                    <a href="view.php?id=<?php echo $row["id"]; ?>">
                        View Ledger
                    </a>
                </td>

            </tr>

        <?php } ?>

    </table>

</body>

</html>