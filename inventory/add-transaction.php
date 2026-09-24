<?php

require_once "../config/database.php";


$materials = mysqli_query(
    $conn,
    "SELECT id, name, unit, current_stock
     FROM raw_materials
     ORDER BY name ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $raw_material_id = $_POST["raw_material_id"];
    $type = $_POST["type"];
    $quantity = $_POST["quantity"];


    $sql = "INSERT INTO inventory_transactions
            (raw_material_id, type, quantity)
            VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "isd",
        $raw_material_id,
        $type,
        $quantity
    );

    mysqli_stmt_execute($stmt);


    if ($type === "purchase") {

        $sql = "UPDATE raw_materials
                SET current_stock = current_stock + ?
                WHERE id = ?";

    } else {

        $sql = "UPDATE raw_materials
                SET current_stock = current_stock - ?
                WHERE id = ?";
    }


    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "di",
        $quantity,
        $raw_material_id
    );

    mysqli_stmt_execute($stmt);


    header("Location: materials.php");
    exit;
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Add Stock Transaction</title>
</head>

<body>

    <h1>Add Stock Transaction</h1>

    <form method="POST">

        <label>Raw Material</label>
        <br>

        <select name="raw_material_id" required>

            <option value="">
                Select Raw Material
            </option>

            <?php while ($material = mysqli_fetch_assoc($materials)) { ?>

                <option value="<?php echo $material["id"]; ?>">

                    <?php echo $material["name"]; ?>

                    (Current:
                    <?php echo $material["current_stock"]; ?>
                    <?php echo $material["unit"]; ?>)

                </option>

            <?php } ?>

        </select>

        <br><br>


        <label>Transaction Type</label>
        <br>

        <select name="type" required>

            <option value="purchase">
                Purchase
            </option>

            <option value="consumption">
                Consumption
            </option>

            <option value="adjustment">
                Adjustment
            </option>

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


        <button type="submit">
            Add Transaction
        </button>

    </form>

    <br>

    <a href="materials.php">
        Back to Raw Materials
    </a>

</body>

</html>