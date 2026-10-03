<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

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

    $supplier_id = (int) ($_POST["supplier_id"] ?? 0);
    $payment_status = trim($_POST["payment_status"] ?? "unpaid");
    $purchase_date = trim($_POST["purchase_date"] ?? date("Y-m-d"));

    $raw_material_ids = $_POST["raw_material_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];
    $unit_prices = $_POST["unit_price"] ?? [];

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

            // Calculate purchase items
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

                // Check raw material exists
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

            // Create purchase
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

            // Insert purchase items
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

                // Increase stock
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

                // Create inventory transaction
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

// Convert suppliers and materials to arrays
$suppliers_list = [];
if ($suppliers) {
    while ($s = mysqli_fetch_assoc($suppliers)) {
        $suppliers_list[] = $s;
    }
}

$materials_list = [];
if ($raw_materials) {
    while ($m = mysqli_fetch_assoc($raw_materials)) {
        $materials_list[] = $m;
    }
}

$page_title = "Create Purchase Order";
$active_menu = "purchases";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Create Purchase Order</h2>
        <p class="page-header-subtitle">Intake bulk raw materials, record supplier liability &amp; update active inventory</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Purchases</span>
        </a>
    </div>
</div>

<?php if ($error !== "") { ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Purchase Creation Error:</strong> <?php echo htmlspecialchars($error); ?>
        </div>
    </div>
<?php } ?>

<form method="POST" id="purchaseForm">
    <div class="row g-4">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <!-- 1. Order Header Information Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <span class="recipe-flow-step-num">1</span>Vendor &amp; Invoice Details
                    </span>
                    <span class="badge bg-light text-dark border">
                        Purchase Metadata
                    </span>
                </div>
                <div class="pos-card-body p-4">
                    <div class="row g-3">
                        <!-- Supplier Select -->
                        <div class="col-12 col-md-6">
                            <label class="form-label pos-form-label">
                                Supplier / Vendor <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-truck text-muted"></i>
                                </span>
                                <select name="supplier_id" class="form-select pos-form-control border-start-0" required>
                                    <option value="">Select Supplier...</option>
                                    <?php foreach ($suppliers_list as $sup) { ?>
                                        <option value="<?php echo $sup["id"]; ?>" <?php echo (isset($_POST["supplier_id"]) && $_POST["supplier_id"] == $sup["id"]) ? "selected" : ""; ?>>
                                            <?php echo htmlspecialchars($sup["name"]); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="form-text text-muted small">Registered vendor supplying the materials.</div>
                        </div>

                        <!-- Purchase Date -->
                        <div class="col-12 col-md-3">
                            <label class="form-label pos-form-label">
                                Purchase Date <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-calendar-event text-muted"></i>
                                </span>
                                <input
                                    type="date"
                                    name="purchase_date"
                                    class="form-control pos-form-control border-start-0"
                                    value="<?php echo htmlspecialchars($_POST["purchase_date"] ?? date('Y-m-d')); ?>"
                                    required
                                >
                            </div>
                        </div>

                        <!-- Payment Status -->
                        <div class="col-12 col-md-3">
                            <label class="form-label pos-form-label">
                                Payment Status <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-wallet2 text-muted"></i>
                                </span>
                                <select name="payment_status" class="form-select pos-form-control border-start-0" required>
                                    <option value="unpaid" <?php echo (isset($_POST["payment_status"]) && $_POST["payment_status"] === "unpaid") ? "selected" : ""; ?>>Unpaid</option>
                                    <option value="partial" <?php echo (isset($_POST["payment_status"]) && $_POST["payment_status"] === "partial") ? "selected" : ""; ?>>Partial</option>
                                    <option value="paid" <?php echo (isset($_POST["payment_status"]) && $_POST["payment_status"] === "paid") ? "selected" : ""; ?>>Paid</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Purchased Materials Table Builder -->
            <div class="pos-card">
                <div class="pos-card-header d-flex justify-content-between align-items-center">
                    <span class="pos-card-title">
                        <span class="recipe-flow-step-num">2</span>Purchased Materials &amp; Line Items
                    </span>
                    <span class="badge bg-light text-primary border">
                        Stock Increment Items
                    </span>
                </div>
                <div class="pos-card-body p-4">
                    <p class="text-muted small mb-3">
                        Add raw materials included in this delivery. Stock quantities will be automatically credited to inventory upon saving.
                    </p>

                    <!-- Items Container -->
                    <div id="purchaseItems" class="d-flex flex-column gap-3">
                        <!-- Initial Row -->
                        <div class="purchase-item p-3 rounded border bg-white">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-5">
                                    <label class="form-label pos-form-label mb-1">
                                        Raw Material <span class="text-danger">*</span>
                                    </label>
                                    <select name="raw_material_id[]" class="form-select pos-form-control" required onchange="handleMaterialChange(this)">
                                        <option value="">Select Raw Material</option>
                                        <?php foreach ($materials_list as $mat) { ?>
                                            <option value="<?php echo $mat["id"]; ?>" data-unit="<?php echo htmlspecialchars($mat["unit"]); ?>">
                                                <?php echo htmlspecialchars($mat["name"]); ?> (<?php echo htmlspecialchars($mat["unit"]); ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div class="col-6 col-md-3">
                                    <label class="form-label pos-form-label mb-1">
                                        Quantity <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            name="quantity[]"
                                            step="0.001"
                                            min="0.001"
                                            class="form-control pos-form-control text-end"
                                            placeholder="0.000"
                                            required
                                            oninput="recalcRow(this.closest('.purchase-item'))"
                                        >
                                        <span class="input-group-text bg-light text-muted unit-label" style="min-width: 58px; font-size: 0.75rem; justify-content: center;">
                                            unit
                                        </span>
                                    </div>
                                </div>

                                <div class="col-6 col-md-3">
                                    <label class="form-label pos-form-label mb-1">
                                        Unit Price (Rs.) <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light text-muted" style="font-size: 0.8rem;">Rs.</span>
                                        <input
                                            type="number"
                                            name="unit_price[]"
                                            step="0.01"
                                            min="0"
                                            class="form-control pos-form-control text-end"
                                            placeholder="0.00"
                                            required
                                            oninput="recalcRow(this.closest('.purchase-item'))"
                                        >
                                    </div>
                                </div>

                                <div class="col-12 col-md-1 text-end">
                                    <label class="form-label pos-form-label mb-1 d-none d-md-block">&nbsp;</label>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center" 
                                        onclick="removePurchaseItem(this)" 
                                        title="Remove Line Item"
                                        style="height: 38px;"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end align-items-center gap-2 mt-2 pt-2 border-top text-muted small">
                                <span>Estimated Line Subtotal:</span>
                                <span class="line-subtotal fw-bold text-dark font-monospace">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>

                    <!-- Add Row Button -->
                    <div class="mt-3">
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                            onclick="addPurchaseItem()"
                        >
                            <i class="bi bi-plus-circle"></i>
                            <span>Add Another Item</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Summary Column -->
        <div class="col-12 col-lg-4">
            <!-- Purchase Summary Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-calculator text-primary me-2"></i>Order Financial Summary
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="purchase-summary-box mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Item Lines</span>
                            <span id="previewItemCount" class="fw-bold text-dark">1</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Stock Adjustment</span>
                            <span class="badge badge-subtle badge-status-ready">Immediate Credit</span>
                        </div>
                        <hr class="my-2 border-secondary-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">Total Amount</span>
                            <span id="previewGrandTotal" class="fs-4 fw-bold text-primary font-monospace">Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Create Purchase</span>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Inventory Mechanics Guidance Card -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-box-arrow-in-down text-secondary me-2"></i>Stock Intake Mechanics
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="recipe-flow-card mb-3">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-primary text-white rounded-pill px-2">1</span>
                            <div>
                                <strong class="small d-block text-dark">Stock Increment</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Each material's current_stock is credited in real-time.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-warning text-dark rounded-pill px-2">2</span>
                            <div>
                                <strong class="small d-block text-dark">Audit Transaction</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Records permanent inventory_transactions entry.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-success text-white rounded-pill px-2">3</span>
                            <div>
                                <strong class="small d-block text-dark">Supplier Account</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Invoice enters Supplier Ledger for payment tracking.</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Decimal support up to 3 places (0.001) for precise weight/volume intake.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function addPurchaseItem() {
    const purchaseItems = document.getElementById("purchaseItems");
    const firstItem = document.querySelector(".purchase-item");
    if (!purchaseItems || !firstItem) return;

    const newItem = firstItem.cloneNode(true);

    const select = newItem.querySelector("select");
    const inputs = newItem.querySelectorAll("input");
    const unitLabel = newItem.querySelector(".unit-label");
    const subtotalDisplay = newItem.querySelector(".line-subtotal");

    if (select) select.value = "";
    inputs.forEach(function (input) {
        input.value = "";
    });
    if (unitLabel) unitLabel.textContent = "unit";
    if (subtotalDisplay) subtotalDisplay.textContent = "Rs. 0.00";

    purchaseItems.appendChild(newItem);
    recalcPurchaseTotal();
}

function removePurchaseItem(btn) {
    const rows = document.querySelectorAll(".purchase-item");
    const row = btn.closest(".purchase-item");
    if (!row) return;

    if (rows.length > 1) {
        row.remove();
    } else {
        const select = row.querySelector("select");
        const inputs = row.querySelectorAll("input");
        const unitLabel = row.querySelector(".unit-label");
        const subtotalDisplay = row.querySelector(".line-subtotal");
        if (select) select.value = "";
        inputs.forEach(function (input) {
            input.value = "";
        });
        if (unitLabel) unitLabel.textContent = "unit";
        if (subtotalDisplay) subtotalDisplay.textContent = "Rs. 0.00";
    }
    recalcPurchaseTotal();
}

function handleMaterialChange(select) {
    const row = select.closest(".purchase-item");
    if (!row) return;
    const unitLabel = row.querySelector(".unit-label");
    const selectedOption = select.options[select.selectedIndex];
    const unit = selectedOption ? (selectedOption.getAttribute("data-unit") || "unit") : "unit";
    if (unitLabel) {
        unitLabel.textContent = unit;
    }
    recalcRow(row);
}

function recalcRow(row) {
    if (!row) return;
    const qtyInput = row.querySelector('input[name="quantity[]"]');
    const priceInput = row.querySelector('input[name="unit_price[]"]');
    const subtotalDisplay = row.querySelector('.line-subtotal');

    const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
    const price = parseFloat(priceInput ? priceInput.value : 0) || 0;
    const subtotal = qty * price;

    if (subtotalDisplay) {
        subtotalDisplay.textContent = 'Rs. ' + subtotal.toFixed(2);
    }
    recalcPurchaseTotal();
}

function recalcPurchaseTotal() {
    let grandTotal = 0;
    let validCount = 0;
    const rows = document.querySelectorAll('.purchase-item');
    rows.forEach(function (row) {
        const qtyInput = row.querySelector('input[name="quantity[]"]');
        const priceInput = row.querySelector('input[name="unit_price[]"]');
        const select = row.querySelector('select[name="raw_material_id[]"]');

        const qty = parseFloat(qtyInput ? qtyInput.value : 0) || 0;
        const price = parseFloat(priceInput ? priceInput.value : 0) || 0;

        if (select && select.value && qty > 0) {
            validCount++;
            grandTotal += (qty * price);
        }
    });

    const totalDisplay = document.getElementById('previewGrandTotal');
    const countDisplay = document.getElementById('previewItemCount');

    if (totalDisplay) totalDisplay.textContent = 'Rs. ' + grandTotal.toFixed(2);
    if (countDisplay) countDisplay.textContent = (validCount > 0 ? validCount : rows.length).toString();
}
</script>

<?php

require_once "../includes/footer.php";

?>