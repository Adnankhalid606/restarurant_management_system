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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $unit = trim($_POST["unit"] ?? "");
    $current_stock = (float) ($_POST["current_stock"] ?? 0);
    $minimum_stock = (float) ($_POST["minimum_stock"] ?? 0);

    $sql = "UPDATE raw_materials
            SET name = ?,
                unit = ?,
                current_stock = ?,
                minimum_stock = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssddi",
        $name,
        $unit,
        $current_stock,
        $minimum_stock,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: materials.php");
    exit;
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
                        <select name="unit" id="materialUnit" class="form-select pos-form-control py-2" required>
                            <option value="kg" <?php if ($material["unit"] === "kg") echo "selected"; ?>>Kilogram (kg)</option>
                            <option value="liter" <?php if ($material["unit"] === "liter") echo "selected"; ?>>Liter (l)</option>
                            <option value="gram" <?php if ($material["unit"] === "gram") echo "selected"; ?>>Gram (g)</option>
                            <option value="piece" <?php if ($material["unit"] === "piece") echo "selected"; ?>>Piece (pcs)</option>
                        </select>
                        <div class="form-text text-muted small">Unit applies to recipe portions and stock tracking.</div>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- Current Stock -->
                        <div class="col-12 col-sm-6">
                            <label for="currentStock" class="form-label pos-form-label">
                                Current Stock On Hand <span class="text-danger">*</span>
                            </label>
                            <input
                                type="number"
                                class="form-control pos-form-control py-2"
                                id="currentStock"
                                name="current_stock"
                                step="0.001"
                                min="0"
                                value="<?php echo htmlspecialchars($material["current_stock"], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                            <div class="form-text text-muted small">Physical inventory quantity.</div>
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
                        <a href="delete-material.php?id=<?php echo $id; ?>" 
                           class="btn btn-outline-danger px-3 d-inline-flex align-items-center gap-1"
                           onclick="return confirm('Are you sure you want to delete raw material <?php echo htmlspecialchars(addslashes($material['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Materials used in recipes, purchases, or recorded transactions cannot be deleted.');">
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </a>

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
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>