<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: materials.php");
    exit;
}

$sql = "SELECT *
        FROM raw_materials
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$material = mysqli_fetch_assoc($result);

if (!$material) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: materials.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $name = trim($_POST["name"] ?? "");
    $unit_id = (int) ($_POST["unit_id"] ?? 0);
    $minimum_stock_raw = trim($_POST["minimum_stock"] ?? "0");

    if ($name === "") {
        $error = "Material name is required.";
    } elseif ($unit_id <= 0) {
        $error = "Please select a valid unit of measure.";
    } else {
        // Fetch new unit of measure
        $uom_stmt = mysqli_prepare($conn, "SELECT id, name, symbol, allow_decimal, status FROM units_of_measure WHERE id = ?");
        mysqli_stmt_bind_param($uom_stmt, "i", $unit_id);
        mysqli_stmt_execute($uom_stmt);
        $new_uom = mysqli_fetch_assoc(mysqli_stmt_get_result($uom_stmt));

        if (!$new_uom || ($new_uom["status"] !== "active" && (int)$new_uom["id"] !== (int)$material["unit_id"])) {
            $error = "Selected unit of measure is inactive or invalid.";
        } else {
            $allow_decimal = (int) $new_uom["allow_decimal"];
            $unit_symbol = $new_uom["symbol"];
            $current_stock_val = (float) $material["current_stock"];

            // If switching to a whole-number UOM, ensure current physical stock has no fractional part
            if ($allow_decimal === 0 && (floor($current_stock_val) != $current_stock_val)) {
                $error = "Cannot change unit to " . htmlspecialchars($new_uom["name"], ENT_QUOTES, 'UTF-8') . " ($unit_symbol): Current stock on hand (" . number_format($current_stock_val, 3) . ") contains a fractional amount. Reconcile stock to a whole number before changing unit policy.";
            } elseif (!is_numeric($minimum_stock_raw) || (float)$minimum_stock_raw < 0) {
                $error = "Minimum stock threshold must be a non-negative number.";
            } elseif ($allow_decimal === 0 && (strpos($minimum_stock_raw, '.') !== false && rtrim(explode('.', $minimum_stock_raw)[1], '0') !== '')) {
                $error = "Fractional quantities are not allowed for minimum stock threshold for " . htmlspecialchars($new_uom["name"], ENT_QUOTES, 'UTF-8') . " ($unit_symbol).";
            } else {
                $minimum_stock = (float) $minimum_stock_raw;

                // Physical current_stock cannot be silently overwritten here.
                // Stock mutations must go through purchase receipts or traceable manual adjustments.
                $sql = "UPDATE raw_materials
                        SET name = ?,
                            unit = ?,
                            unit_id = ?,
                            minimum_stock = ?
                        WHERE id = ?";

                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssidi",
                    $name,
                    $unit_symbol,
                    $unit_id,
                    $minimum_stock,
                    $id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION["flash_success"] = "Raw material details updated successfully.";
                    header("Location: materials.php");
                    exit;
                } else {
                    $error = "Failed to update material: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Fetch all units of measure (active plus the currently assigned unit even if inactive)
$uom_list_stmt = mysqli_prepare($conn, "
    SELECT id, name, symbol, allow_decimal, status 
    FROM units_of_measure 
    WHERE status = 'active' OR id = ? 
    ORDER BY name ASC
");
mysqli_stmt_bind_param($uom_list_stmt, "i", $material["unit_id"]);
mysqli_stmt_execute($uom_list_stmt);
$uom_list_res = mysqli_stmt_get_result($uom_list_stmt);
$available_uoms = [];
while ($u_row = mysqli_fetch_assoc($uom_list_res)) {
    $available_uoms[] = $u_row;
}

$page_title = "Edit Raw Material #" . $id;
$active_menu = "inventory";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Edit Raw Material #<?php echo $id; ?></h2>
        <p class="page-header-subtitle">Update ingredient specifications, units, or stock thresholds</p>
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
            <div class="pos-card-header bg-light d-flex align-items-center justify-content-between">
                <span class="pos-card-title">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Modify Material Record
                </span>
                <span class="badge bg-white text-muted border">ID #<?php echo $id; ?></span>
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
                                value="<?php echo htmlspecialchars($material["name"], ENT_QUOTES, 'UTF-8'); ?>"
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
                            <?php foreach ($available_uoms as $u) { 
                                $is_selected = ((int)$material["unit_id"] === (int)$u["id"]);
                                $is_inactive = ($u["status"] === "inactive");
                                $dec_label = (int)$u["allow_decimal"] === 1 ? 'fractional' : 'whole';
                            ?>
                                <option value="<?php echo $u['id']; ?>" <?php if ($is_selected) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($u["name"] . " (" . $u["symbol"] . ") - " . $dec_label . ($is_inactive ? ' [Inactive]' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <div class="form-text text-muted small">Unit of Measure loaded from database. Applies to recipe portions and stock tracking.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- Current Stock (Read-Only Audit Display) -->
                        <div class="col-12 col-sm-6">
                            <label class="form-label pos-form-label">
                                Current Stock On Hand
                            </label>
                            <input
                                type="text"
                                class="form-control pos-form-control py-2 bg-light text-muted"
                                value="<?php echo number_format((float)$material["current_stock"], 3) . ' ' . htmlspecialchars($material["unit"], ENT_QUOTES, 'UTF-8'); ?>"
                                readonly
                            >
                            <div class="form-text text-muted small">
                                Managed via <a href="add-transaction.php?material_id=<?php echo $id; ?>">traceable adjustments</a> &amp; purchases.
                            </div>
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
                                value="<?php echo htmlspecialchars($material["minimum_stock"], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                            <div class="form-text text-muted small">Triggers "Low Stock" alert when reached.</div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <button type="submit" form="deleteMaterialForm" class="btn btn-outline-danger px-3 d-inline-flex align-items-center gap-1">
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </button>

                        <div class="d-flex align-items-center gap-2">
                            <a href="materials.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="bi bi-check-lg"></i>
                                <span>Update Material</span>
                            </button>
                        </div>
                    </div>
                </form>

                <form id="deleteMaterialForm" method="POST" action="delete-material.php" class="d-none" onsubmit="return confirm('Are you sure you want to delete raw material <?php echo htmlspecialchars(addslashes($material['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Materials used in recipes, purchases, or recorded transactions cannot be deleted.');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $id; ?>">
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>