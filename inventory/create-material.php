<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $current_stock = (float) ($_POST["current_stock"] ?? 0);
    $minimum_stock = (float) ($_POST["minimum_stock"] ?? 0);

    $sql = "INSERT INTO raw_materials
            (name, unit, current_stock, minimum_stock)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdd",
        $name,
        $unit,
        $current_stock,
        $minimum_stock
    );

    mysqli_stmt_execute($stmt);

    header("Location: materials.php");
    exit;
}

$page_title = "Add Raw Material";
$active_menu = "inventory";

require_once "../includes/header.php";

$posted_name = $_POST["name"] ?? "";
$posted_unit = $_POST["unit"] ?? "";
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
                        <select name="unit" id="materialUnit" class="form-select pos-form-control py-2" required>
                            <option value="">-- Select Unit of Measure --</option>
                            <option value="kg" <?php if ($posted_unit === "kg") echo "selected"; ?>>Kilogram (kg)</option>
                            <option value="liter" <?php if ($posted_unit === "liter") echo "selected"; ?>>Liter (l)</option>
                            <option value="gram" <?php if ($posted_unit === "gram") echo "selected"; ?>>Gram (g)</option>
                            <option value="piece" <?php if ($posted_unit === "piece") echo "selected"; ?>>Piece (pcs)</option>
                        </select>
                        <div class="form-text text-muted small">Unit applies to recipe portions and stock tracking.</div>
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