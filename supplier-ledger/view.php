<?php

require_once "../includes/auth.php";
require_once "../config/database.php";

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

if (!isset($_GET["id"])) {
    die("Supplier ID is required.");
}

$supplier_id = (int) $_GET["id"];

// Get supplier
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM suppliers
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $supplier_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$supplier = mysqli_fetch_assoc($result);

if (!$supplier) {
    die("Supplier not found.");
}

// Get supplier purchases
$sql = "
    SELECT
        id,
        purchase_date,
        total_amount,
        payment_status
    FROM purchases
    WHERE supplier_id = ?
    ORDER BY purchase_date DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $supplier_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$purchases = [];

$total_purchases = 0;
$total_paid = 0;
$total_outstanding = 0;

// Calculate totals
while ($row = mysqli_fetch_assoc($result)) {

    $purchases[] = $row;

    $total_purchases += $row["total_amount"];

    if ($row["payment_status"] === "paid") {
        $total_paid += $row["total_amount"];
    } else {
        $total_outstanding += $row["total_amount"];
    }
}

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

    <a href="index.php">Back to Supplier Ledger</a>

    <hr>

    <h2>
        <?php echo htmlspecialchars($supplier["name"]); ?>
    </h2>

    <p>
        Phone:
        <?php echo htmlspecialchars($supplier["phone"] ?? "N/A"); ?>
    </p>

    <p>
        Address:
        <?php echo htmlspecialchars($supplier["address"] ?? "N/A"); ?>
    </p>

    <hr>

    <h2>Summary</h2>

    <p>
        Total Purchases:
        <strong>
            Rs. <?php echo number_format($total_purchases, 2); ?>
        </strong>
    </p>

    <p>
        Total Paid:
        <strong>
            Rs. <?php echo number_format($total_paid, 2); ?>
        </strong>
    </p>

    <p>
        Outstanding:
        <strong>
            Rs. <?php echo number_format($total_outstanding, 2); ?>
        </strong>
    </p>

    <hr>

    <h2>Transactions</h2>

    <?php if (empty($purchases)) { ?>

        <p>No purchases found.</p>

    <?php } else { ?>

        <table border="1" cellpadding="8">

            <tr>
                <th>Purchase ID</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Payment Status</th>
            </tr>

            <?php foreach ($purchases as $row) { ?>

                <tr>

                    <td>
                        #<?php echo $row["id"]; ?>
                    </td>

                    <td>
                        <?php echo $row["purchase_date"]; ?>
                    </td>

                    <td>
                        Rs. <?php echo number_format($row["total_amount"], 2); ?>
                    </td>

                    <td>
                        <?php echo ucfirst($row["payment_status"]); ?>
                    </td>

                </tr>

            <?php } ?>

        </table>

    <?php } ?>

</body>

</html>