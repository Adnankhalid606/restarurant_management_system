<?php

require_once "../config/database.php";

$id = $_GET["id"];


$sql = "SELECT
            purchases.id,
            suppliers.name AS supplier_name,
            purchases.total_amount,
            purchases.payment_status,
            purchases.purchase_date,
            purchases.created_at

        FROM purchases

        LEFT JOIN suppliers
            ON purchases.supplier_id = suppliers.id

        WHERE purchases.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$purchase = mysqli_fetch_assoc($result);


if (!$purchase) {
    die("Purchase not found.");
}


$sql = "SELECT
            purchase_items.quantity,
            purchase_items.unit_price,
            purchase_items.subtotal,
            raw_materials.name AS material_name,
            raw_materials.unit

        FROM purchase_items

        JOIN raw_materials
            ON purchase_items.raw_material_id = raw_materials.id

        WHERE purchase_items.purchase_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

?>

<!DOCTYPE html>
<html>

<head>
    <title>View Purchase</title>
</head>

<body>

    <h1>
        Purchase #<?php echo $purchase["id"]; ?>
    </h1>


    <p>
        <strong>Supplier:</strong>
        <?php echo $purchase["supplier_name"] ?? "-"; ?>
    </p>

    <p>
        <strong>Payment Status:</strong>
        <?php echo $purchase["payment_status"]; ?>
    </p>

    <p>
        <strong>Purchase Date:</strong>
        <?php echo $purchase["purchase_date"]; ?>
    </p>

    <p>
        <strong>Created:</strong>
        <?php echo $purchase["created_at"]; ?>
    </p>


    <h2>Purchase Items</h2>

    <table border="1" cellpadding="10">

        <tr>
            <th>Raw Material</th>
            <th>Quantity</th>
            <th>Unit Price</th>
            <th>Subtotal</th>
        </tr>

        <?php while ($item = mysqli_fetch_assoc($items)) { ?>

            <tr>

                <td>
                    <?php echo $item["material_name"]; ?>
                </td>

                <td>
                    <?php echo $item["quantity"]; ?>
                    <?php echo $item["unit"]; ?>
                </td>

                <td>
                    Rs. <?php echo $item["unit_price"]; ?>
                </td>

                <td>
                    Rs. <?php echo $item["subtotal"]; ?>
                </td>

            </tr>

        <?php } ?>

    </table>


    <h2>
        Total:
        Rs. <?php echo $purchase["total_amount"]; ?>
    </h2>


    <a href="index.php">
        Back to Purchases
    </a>

</body>

</html>