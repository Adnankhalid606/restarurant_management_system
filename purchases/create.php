<?php

require_once "../config/database.php";

$suppliers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM suppliers
     ORDER BY name ASC"
);

$raw_materials = mysqli_query(
    $conn,
    "SELECT id, name, unit
     FROM raw_materials
     ORDER BY name ASC"
);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $supplier_id = $_POST["supplier_id"];
    $payment_status = $_POST["payment_status"];
    $purchase_date = $_POST["purchase_date"];

    $raw_material_ids = $_POST["raw_material_id"];
    $quantities = $_POST["quantity"];
    $unit_prices = $_POST["unit_price"];

    if (
        empty($raw_material_ids)
        ||
        empty($quantities)
        ||
        empty($unit_prices)
    ) {

        $error = "Please add at least one raw material.";

    } elseif (
        count($raw_material_ids) !== count($quantities)
        ||
        count($raw_material_ids) !== count($unit_prices)
    ) {

        $error = "Invalid purchase items.";

    }

    if ($error === "") {

        mysqli_begin_transaction($conn);

        try {

            $total_amount = 0;

            $items = [];

            /*
             * Calculate purchase items
             */

            foreach ($raw_material_ids as $index => $raw_material_id) {

                $quantity = $quantities[$index];
                $unit_price = $unit_prices[$index];

                if ($quantity <= 0) {

                    throw new Exception(
                        "Quantity must be greater than 0."
                    );
                }

                if ($unit_price < 0) {

                    throw new Exception(
                        "Unit price cannot be negative."
                    );
                }

                /*
                 * Check raw material exists
                 */

                $sql = "SELECT id
                        FROM raw_materials
                        WHERE id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $raw_material_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                $material = mysqli_fetch_assoc($result);

                if (!$material) {

                    throw new Exception(
                        "Raw material not found."
                    );
                }

                $subtotal = $quantity * $unit_price;

                $total_amount += $subtotal;

                $items[] = [
                    "raw_material_id" => $raw_material_id,
                    "quantity" => $quantity,
                    "unit_price" => $unit_price,
                    "subtotal" => $subtotal
                ];
            }

            /*
             * Create purchase
             */

            $sql = "INSERT INTO purchases
                    (
                        supplier_id,
                        total_amount,
                        payment_status,
                        purchase_date
                    )

                    VALUES (?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "idss",
                $supplier_id,
                $total_amount,
                $payment_status,
                $purchase_date
            );

            mysqli_stmt_execute($stmt);

            $purchase_id = mysqli_insert_id($conn);

            /*
             * Insert purchase items
             */

            foreach ($items as $item) {

                $sql = "INSERT INTO purchase_items
                        (
                            purchase_id,
                            raw_material_id,
                            quantity,
                            unit_price,
                            subtotal
                        )

                        VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiddd",
                    $purchase_id,
                    $item["raw_material_id"],
                    $item["quantity"],
                    $item["unit_price"],
                    $item["subtotal"]
                );

                mysqli_stmt_execute($stmt);

                /*
                 * Increase stock
                 */

                $sql = "UPDATE raw_materials

                        SET current_stock =
                            current_stock + ?

                        WHERE id = ?";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "di",
                    $item["quantity"],
                    $item["raw_material_id"]
                );

                mysqli_stmt_execute($stmt);

                /*
                 * Create inventory transaction
                 */

                $sql = "INSERT INTO inventory_transactions
                        (
                            raw_material_id,
                            type,
                            quantity,
                            reference_type,
                            reference_id
                        )

                        VALUES
                        (
                            ?,
                            'purchase',
                            ?,
                            'purchase',
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "idi",
                    $item["raw_material_id"],
                    $item["quantity"],
                    $purchase_id
                );

                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);

            header("Location: view.php?id=" . $purchase_id);
            exit;

        } catch (Exception $exception) {

            mysqli_rollback($conn);

            $error = $exception->getMessage();
        }
    }
}

?>

<!DOCTYPE html>
<html>

<head>
    <title>Create Purchase</title>
</head>

<body>

    <h1>Create Purchase</h1>

    <?php if ($error !== "") { ?>

        <p>
            <strong>
                <?php echo $error; ?>
            </strong>
        </p>

    <?php } ?>

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

        <label>Payment Status</label>
        <br>

        <select name="payment_status">

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

        <h2>Purchase Items</h2>

        <div id="purchaseItems">

            <div class="purchase-item">

                <label>Raw Material</label>
                <br>

                <select name="raw_material_id[]" required>

                    <option value="">
                        Select Raw Material
                    </option>

                    <?php
                    mysqli_data_seek($raw_materials, 0);
                    ?>

                    <?php while ($material = mysqli_fetch_assoc($raw_materials)) { ?>

                        <option value="<?php echo $material["id"]; ?>">
                            <?php echo $material["name"]; ?>
                            (<?php echo $material["unit"]; ?>)
                        </option>

                    <?php } ?>

                </select>

                <br>

                <label>Quantity</label>
                <br>

                <input
                    type="number"
                    name="quantity[]"
                    step="0.001"
                    min="0.001"
                    required
                >

                <br>

                <label>Unit Price</label>
                <br>

                <input
                    type="number"
                    name="unit_price[]"
                    step="0.01"
                    min="0"
                    required
                >

                <br><br>

            </div>

        </div>

        <button
            type="button"
            onclick="addPurchaseItem()"
        >
            Add Another Item
        </button>

        <br><br>

        <button type="submit">
            Create Purchase
        </button>

    </form>

    <br>

    <a href="index.php">
        Back to Purchases
    </a>

    <script>

        function addPurchaseItem() {

            const purchaseItems =
                document.getElementById("purchaseItems");

            const firstItem =
                document.querySelector(".purchase-item");

            const newItem =
                firstItem.cloneNode(true);

            newItem
                .querySelector("select")
                .value = "";

            newItem
                .querySelectorAll("input")
                .forEach(function (input) {
                    input.value = "";
                });

            purchaseItems.appendChild(newItem);
        }

    </script>

</body>

</html>