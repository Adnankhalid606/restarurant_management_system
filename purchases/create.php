<?php

require_once "../config/database.php";


$suppliers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM suppliers
     ORDER BY name ASC"
);


$materials = mysqli_query(
    $conn,
    "SELECT id, name, unit
     FROM raw_materials
     ORDER BY name ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $supplier_id = $_POST["supplier_id"];
    $raw_material_id = $_POST["raw_material_id"];
    $quantity = $_POST["quantity"];
    $unit_price = $_POST["unit_price"];
    $payment_status = $_POST["payment_status"];
    $purchase_date = $_POST["purchase_date"];


    $subtotal = $quantity * $unit_price;


    $sql = "INSERT INTO purchases
            (supplier_id, total_amount, payment_status, purchase_date)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "idss",
        $supplier_id,
        $subtotal,
        $payment_status,
        $purchase_date
    );

    mysqli_stmt_execute($stmt);


    $purchase_id = mysqli_insert_id($conn);


    $sql = "INSERT INTO purchase_items
            (purchase_id, raw_material_id, quantity, unit_price, subtotal)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iiddi",
        $purchase_id,
        $raw_material_id,
        $quantity,
        $unit_price,
        $subtotal
    );

    mysqli_stmt_execute($stmt);


    $sql = "INSERT INTO inventory_transactions
            (raw_material_id, type, quantity, reference_type, reference_id)
            VALUES (?, 'purchase', ?, 'purchase', ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "idi",
        $raw_material_id,
        $quantity,
        $purchase_id
    );

    mysqli_stmt_execute($stmt);


    $sql = "UPDATE raw_materials
            SET current_stock = current_stock + ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "di",
        $quantity,
        $raw_material_id
    );

    mysqli_stmt_execute($stmt);


    header("Location: index.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Purchase</title>
</head>

<body>

    <h1>Create Purchase</h1>

    <form method="POST">

        <label>Supplier</label>
        <br>

        <select name="supplier_id" required>

            <option value="">
                Select Supplier
            </option>

            <?php while ($supplier = mysqli_fetch_assoc($suppliers)) { ?>

                <option value="<?php echo $supplier["id"]; ?>">
                    <?php echo $supplier["name"]; ?>
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Raw Material</label>
        <br>

        <select name="raw_material_id" required>

            <option value="">
                Select Raw Material
            </option>

            <?php while ($material = mysqli_fetch_assoc($materials)) { ?>

                <option value="<?php echo $material["id"]; ?>">
                    <?php echo $material["name"]; ?>
                    (<?php echo $material["unit"]; ?>)
                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Quantity</label>
        <br>

        <input
            type="number"
            name="quantity"
            step="0.001"
            min="0.001"
            required
        >

        <br><br>


        <label>Unit Price</label>
        <br>

        <input
            type="number"
            name="unit_price"
            step="0.01"
            min="0"
            required
        >

        <br><br>


        <label>Payment Status</label>
        <br>

        <select name="payment_status" required>

            <option value="unpaid">
                Unpaid
            </option>

            <option value="partial">
                Partial
            </option>

            <option value="paid">
                Paid
            </option>

        </select>

        <br><br>


        <label>Purchase Date</label>
        <br>

        <input
            type="date"
            name="purchase_date"
            required
        >

        <br><br>


        <button type="submit">
            Create Purchase
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Purchases
    </a>

</body>

</html>