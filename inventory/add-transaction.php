<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$materials = mysqli_query(
    $conn,
    "SELECT id, name, unit, current_stock
     FROM raw_materials
     ORDER BY name ASC"
);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $raw_material_id = isset($_POST["raw_material_id"]) ? (int) $_POST["raw_material_id"] : 0;
    $type = trim($_POST["type"] ?? "");
    $quantity_raw = trim($_POST["quantity"] ?? "");
    $valid_types = ["purchase", "adjustment_in", "consumption", "adjustment_out"];

    if ($raw_material_id <= 0) {
        $error = "Please select a valid raw material.";
    } elseif (!in_array($type, $valid_types, true)) {
        $error = "Invalid transaction type selected.";
    } elseif (!is_numeric($quantity_raw) || (float) $quantity_raw <= 0) {
        $error = "Transaction quantity must be strictly greater than zero.";
    } else {
        $quantity = (float) $quantity_raw;

        mysqli_begin_transaction($conn);

        try {
            // 1. Fetch and lock raw material to check stock
            $mat_stmt = mysqli_prepare($conn, "SELECT id, name, unit, current_stock FROM raw_materials WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($mat_stmt, "i", $raw_material_id);
            mysqli_stmt_execute($mat_stmt);
            $material = mysqli_fetch_assoc(mysqli_stmt_get_result($mat_stmt));

            if (!$material) {
                throw new Exception("Selected raw material does not exist.");
            }

            $current_stock = (float) $material["current_stock"];

            // 2. For deductions, verify sufficient stock is available
            if ($type === "consumption" || $type === "adjustment_out") {
                if ($quantity > $current_stock) {
                    throw new Exception("Insufficient stock available. Current stock of " . $material["name"] . " is " . number_format($current_stock, 3) . " " . $material["unit"] . ".");
                }
            }

            // 3. Create inventory transaction
            $sql = "INSERT INTO inventory_transactions
                    (raw_material_id, type, quantity)
                    VALUES (?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "isd", $raw_material_id, $type, $quantity);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to create inventory transaction.");
            }

            // 4. Update stock balance
            if ($type === "purchase" || $type === "adjustment_in") {
                $sql = "UPDATE raw_materials
                        SET current_stock = current_stock + ?
                        WHERE id = ?";
            } else {
                $sql = "UPDATE raw_materials
                        SET current_stock = current_stock - ?
                        WHERE id = ?";
            }

            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "di", $quantity, $raw_material_id);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update inventory stock balance.");
            }

            mysqli_commit($conn);

            header("Location: materials.php");
            exit;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    }
}

$page_title = "Record Stock Transaction";
$active_menu = "inventory";

require_once "../includes/header.php";

$posted_mat = $_POST["raw_material_id"] ?? "";
$posted_type = $_POST["type"] ?? "purchase";
$posted_qty = $_POST["quantity"] ?? "";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Record Stock Transaction</h2>
        <p class="page-header-subtitle">Post a physical inventory movement, supplier intake, or manual adjustment</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="materials.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Materials</span>
        </a>
        <a href="transactions.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-clock-history"></i>
            <span>Audit Log</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <?php if ($error !== "") { ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div>
                    <strong>Inventory Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php } ?>

        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-arrow-left-right text-primary me-2"></i>Stock Movement Record
                </span>
                <span class="badge bg-white text-muted border">Inventory Mutation</span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST">
                    <!-- Raw Material Selection -->
                    <div class="mb-3">
                        <label for="materialSelect" class="form-label pos-form-label">
                            Target Raw Material <span class="text-danger">*</span>
                        </label>
                        <select name="raw_material_id" id="materialSelect" class="form-select pos-form-control py-2 fs-6" required>
                            <option value="">-- Choose Raw Material --</option>
                            <?php 
                            mysqli_data_seek($materials, 0);
                            while ($material = mysqli_fetch_assoc($materials)) { 
                                $selected = ($material["id"] == $posted_mat) ? "selected" : "";
                            ?>
                                <option value="<?php echo $material["id"]; ?>" 
                                        data-unit="<?php echo htmlspecialchars($material["unit"], ENT_QUOTES, 'UTF-8'); ?>"
                                        data-stock="<?php echo $material["current_stock"]; ?>"
                                        <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($material["name"], ENT_QUOTES, 'UTF-8'); ?> &bull; Current: <?php echo $material["current_stock"]; ?> <?php echo htmlspecialchars($material["unit"], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <div class="form-text text-muted small">Select the storeroom item being adjusted.</div>
                    </div>

                    <!-- Transaction Type -->
                    <div class="mb-3">
                        <label for="transactionType" class="form-label pos-form-label">
                            Transaction Direction &amp; Type <span class="text-danger">*</span>
                        </label>
                        <select name="type" id="transactionType" class="form-select pos-form-control py-2" required>
                            <optgroup label="Stock Inflows (+ Increase Stock)">
                                <option value="purchase" <?php if ($posted_type === "purchase") echo "selected"; ?>>
                                    Purchase (+ Stock Received from Supplier)
                                </option>
                                <option value="adjustment_in" <?php if ($posted_type === "adjustment_in") echo "selected"; ?>>
                                    Adjustment In (+ Physical Inventory Surplus)
                                </option>
                            </optgroup>
                            <optgroup label="Stock Outflows (&minus; Decrease Stock)">
                                <option value="consumption" <?php if ($posted_type === "consumption") echo "selected"; ?>>
                                    Consumption (&minus; Manual Kitchen Preparation)
                                </option>
                                <option value="adjustment_out" <?php if ($posted_type === "adjustment_out") echo "selected"; ?>>
                                    Adjustment Out (&minus; Spoilage, Damage, Waste)
                                </option>
                            </optgroup>
                        </select>
                        <div class="form-text text-muted small">Inflows increase current on-hand stock; outflows decrease it.</div>
                    </div>

                    <!-- Quantity Input -->
                    <div class="mb-4">
                        <label for="quantityInput" class="form-label pos-form-label">
                            Transaction Quantity <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <input
                                type="number"
                                class="form-control pos-form-control py-2 fw-semibold fs-6"
                                id="quantityInput"
                                name="quantity"
                                step="0.001"
                                min="0.001"
                                placeholder="0.000"
                                value="<?php echo htmlspecialchars($posted_qty, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                            <span class="input-group-text bg-light text-muted" id="unitLabel">Units</span>
                        </div>
                        <div class="form-text text-muted small">Amount to add or subtract based on chosen transaction type.</div>
                    </div>

                    <!-- Live Effect Explanatory Notice -->
                    <div class="alert alert-light border py-2 px-3 small text-muted mb-4 d-flex align-items-center gap-2" id="impactNotice">
                        <i class="bi bi-info-circle text-primary fs-5 flex-shrink-0"></i>
                        <span id="noticeText">
                            This transaction will immediately mutate on-hand inventory levels and create a permanent audit log entry.
                        </span>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="materials.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-check-lg"></i>
                            <span>Add Transaction</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const matSelect = document.getElementById('materialSelect');
    const typeSelect = document.getElementById('transactionType');
    const unitLabel = document.getElementById('unitLabel');
    const noticeText = document.getElementById('noticeText');

    function updateDetails() {
        const opt = matSelect.options[matSelect.selectedIndex];
        const unit = opt ? opt.getAttribute('data-unit') : '';
        if (unit) {
            unitLabel.textContent = unit;
        } else {
            unitLabel.textContent = 'Units';
        }

        const type = typeSelect.value;
        if (type === 'purchase' || type === 'adjustment_in') {
            noticeText.innerHTML = '<strong>Stock Inflow:</strong> Entered quantity will be <strong>added (+)</strong> to current stock.';
        } else {
            noticeText.innerHTML = '<strong>Stock Outflow:</strong> Entered quantity will be <strong>subtracted (&minus;)</strong> from current stock.';
        }
    }

    matSelect.addEventListener('change', updateDetails);
    typeSelect.addEventListener('change', updateDetails);
    updateDetails();
});
</script>

<?php

require_once "../includes/footer.php";

?>