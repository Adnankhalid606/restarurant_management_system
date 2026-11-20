<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $name = trim($_POST["name"] ?? "");
    $unit_id = (int) ($_POST["unit_id"] ?? 0);
    $current_stock_raw = trim($_POST["current_stock"] ?? "0");
    $minimum_stock_raw = trim($_POST["minimum_stock"] ?? "0");

    if ($name === "") {
        $error = "Material name is required.";
    } elseif ($unit_id <= 0) {
        $error = "Please select a valid unit of measure.";
    } else {
        // Validate unit_id exists and is active
        $uom_stmt = mysqli_prepare($conn, "SELECT id, name, symbol, allow_decimal, status FROM units_of_measure WHERE id = ?");
        mysqli_stmt_bind_param($uom_stmt, "i", $unit_id);
        mysqli_stmt_execute($uom_stmt);
        $uom = mysqli_fetch_assoc(mysqli_stmt_get_result($uom_stmt));

        if (!$uom || $uom["status"] !== "active") {
            $error = "Selected unit of measure is inactive or invalid.";
        } else {
            $allow_decimal = (int) $uom["allow_decimal"];
            $unit_symbol = $uom["symbol"];

            // Quantity validation for current_stock
            if (!is_numeric($current_stock_raw) || (float)$current_stock_raw < 0) {
                $error = "Opening stock must be a non-negative number.";
            } elseif ($allow_decimal === 0 && (strpos($current_stock_raw, '.') !== false && rtrim(explode('.', $current_stock_raw)[1], '0') !== '')) {
                $error = "Fractional quantities are not allowed for " . htmlspecialchars($uom["name"], ENT_QUOTES, 'UTF-8') . " ($unit_symbol). Please enter a whole number.";
            } elseif (!is_numeric($minimum_stock_raw) || (float)$minimum_stock_raw < 0) {
                $error = "Minimum stock threshold must be a non-negative number.";
            } elseif ($allow_decimal === 0 && (strpos($minimum_stock_raw, '.') !== false && rtrim(explode('.', $minimum_stock_raw)[1], '0') !== '')) {
                $error = "Fractional quantities are not allowed for minimum stock threshold for " . htmlspecialchars($uom["name"], ENT_QUOTES, 'UTF-8') . " ($unit_symbol).";
            } else {
                $current_stock = (float) $current_stock_raw;
                $minimum_stock = (float) $minimum_stock_raw;

                $sql = "INSERT INTO raw_materials
                        (name, unit, unit_id, current_stock, minimum_stock)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssidd",
                    $name,
                    $unit_symbol,
                    $unit_id,
                    $current_stock,
                    $minimum_stock
                );

                if (mysqli_stmt_execute($stmt)) {
                    header("Location: materials.php");
                    exit;
                } else {
                    $error = "Failed to create raw material: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Fetch active units of measure from database
$uom_list_res = mysqli_query($conn, "SELECT id, name, symbol, allow_decimal FROM units_of_measure WHERE status = 'active' ORDER BY name ASC");
$active_uoms = [];
while ($u_row = mysqli_fetch_assoc($uom_list_res)) {
    $active_uoms[] = $u_row;
}

$page_title = "Add Raw Material";
$active_menu = "inventory";

require_once "../includes/header.php";

$posted_name = $_POST["name"] ?? "";
$posted_unit_id = (int) ($_POST["unit_id"] ?? 0);
$posted_current = $_POST["current_stock"] ?? "0";
$posted_minimum = $_POST["minimum_stock"] ?? "0";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Add Raw Material</h2>
        <p class="page-header-subtitle">Register a new kitchen cooking ingredient or bar supply item</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="materials.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Materials</span>
        </a>
    </div>
</div>

<?php if (!empty($error)) { ?>
    <div class="alert alert-danger border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger"></i>
        <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Material Specification
                </span>
                <span class="badge bg-white text-muted border">New Inventory Record</span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST">
                    <?php echo csrf_field(); ?>
                    <!-- Material Name -->
                    <div class="mb-3">
                        <label for="materialName" class="form-label pos-form-label">
                            Material / Ingredient Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-box-seam text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0 py-2"
                                id="materialName"
                                name="name"
                                placeholder="e.g. Basmati Rice, Cooking Oil, Salt"
                                value="<?php echo htmlspecialchars($posted_name, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Standard name used in kitchen recipes and purchase orders.</div>
                    </div>

                    <!-- Measurement Unit -->
                    <div class="mb-3">
                        <label for="materialUnit" class="form-label pos-form-label">
                            Standard Measurement Unit <span class="text-danger">*</span>
                        </label>
                        <select name="unit_id" id="materialUnit" class="form-select pos-form-control py-2" required>
                            <option value="">-- Select Unit of Measure --</option>
                            <?php foreach ($active_uoms as $u) { 
                                $is_sel = ($posted_unit_id === (int)$u["id"]);
                                $dec_label = (int)$u["allow_decimal"] === 1 ? 'fractional' : 'whole';
                            ?>
                                <option value="<?php echo $u['id']; ?>" <?php if ($is_sel) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($u["name"] . " (" . $u["symbol"] . ") - " . $dec_label, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <div class="form-text text-muted small">Standardized UOM from database. Applies to recipe portions and stock tracking.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- Current Stock -->
                        <div class="col-12 col-sm-6">
                            <label for="currentStock" class="form-label pos-form-label">
                                Opening Stock <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number"
                                class="form-control pos-form-control py-2"
                                id="currentStock"
                                name="current_stock"
                                step="0.001"
                                min="0"
                                value="<?php echo htmlspecialchars($posted_current, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                            <div class="form-text text-muted small">Initial quantity in storeroom.</div>
                        </div>

                        <!-- Minimum Stock -->
                        <div class="col-12 col-sm-6">
                            <label for="minimumStock" class="form-label pos-form-label">
                                Minimum Reorder Level <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number"
                                class="form-control pos-form-control py-2"
                                id="minimumStock"
                                name="minimum_stock"
                                step="0.001"
                                min="0"
                                value="<?php echo htmlspecialchars($posted_minimum, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                            <div class="form-text text-muted small">Triggers "Low Stock" alert when reached.</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="materials.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-check-lg"></i>
                            <span>Add Raw Material</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>