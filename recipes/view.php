<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


$sql = "SELECT
            recipes.id,
            menu_items.name AS menu_item_name,
            menu_items.price,
            recipes.created_at

        FROM recipes

        JOIN menu_items
            ON recipes.menu_item_id = menu_items.id

        WHERE recipes.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$recipe = mysqli_fetch_assoc($result);


if (!$recipe) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


$sql = "SELECT
            recipe_items.quantity,
            raw_materials.name AS material_name,
            raw_materials.unit

        FROM recipe_items

        JOIN raw_materials
            ON recipe_items.raw_material_id = raw_materials.id

        WHERE recipe_items.recipe_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

$ingredient_list = [];
$total_ingredients = 0;
while ($row = mysqli_fetch_assoc($items)) {
    $ingredient_list[] = $row;
    $total_ingredients++;
}

$page_title = "Recipe #" . $recipe["id"] . " - " . $recipe["menu_item_name"];
$active_menu = "recipes";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-light text-dark border font-monospace">
                #REC-<?php echo str_pad($recipe["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
            <span class="badge badge-subtle badge-status-ready">
                <i class="bi bi-check-circle-fill me-1"></i>Active Formulation
            </span>
        </div>
        <h2 class="page-header-title"><?php echo htmlspecialchars($recipe["menu_item_name"]); ?></h2>
        <p class="page-header-subtitle">Standard Bill of Materials (BOM) &amp; Kitchen Inventory Deduction Specification</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Recipes</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print BOM</span>
        </button>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="delete.php?id=<?php echo $recipe["id"]; ?>" 
               class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
               onclick="return confirm('Are you sure you want to delete this recipe for <?php echo htmlspecialchars(addslashes($recipe['menu_item_name'])); ?>? Automatic inventory deduction will be stopped for this dish.');">
                <i class="bi bi-trash"></i>
                <span>Delete</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Specification Highlights -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Dish Price</span>
                <span class="fs-4 fw-bold text-dark">Rs. <?php echo number_format($recipe["price"], 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-tag-fill fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Ingredients</span>
                <span class="fs-4 fw-bold text-primary"><?php echo $total_ingredients; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-basket3 fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Standard Portion</span>
                <span class="fs-4 fw-bold text-dark">1 Serving</span>
            </div>
            <div class="badge-subtle badge-status-pending p-2 rounded">
                <i class="bi bi-pie-chart fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Created On</span>
                <span class="fs-6 fw-bold text-dark d-block">
                    <?php echo formatDate($recipe["created_at"]); ?>
                </span>
                <span class="text-muted small" style="font-size: 0.75rem;">
                    <?php echo formatTime($recipe["created_at"]); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-calendar-event fs-4 text-success"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Column: Bill of Materials Table -->
    <div class="col-12 col-lg-8">
        <div class="pos-card">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-list-check text-primary me-2"></i>Ingredient Bill of Materials (BOM)
                </span>
                <span class="badge bg-light text-dark border">
                    Per 1 Prepared Portion
                </span>
            </div>
            <div class="pos-card-body p-0">
                <?php if ($total_ingredients === 0) { ?>
                    <div class="text-center py-5">
                        <div class="mb-3 text-muted">
                            <i class="bi bi-slash-circle fs-1 text-warning"></i>
                        </div>
                        <h5 class="fw-semibold text-dark">No Ingredients Configured</h5>
                        <p class="text-muted small mb-0">
                            This recipe currently contains no raw materials. When orders for this dish are completed, no stock deduction will occur.
                        </p>
                    </div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3" style="width: 50px;">#</th>
                                    <th>Raw Material Ingredient</th>
                                    <th class="text-end" style="width: 140px;">Quantity</th>
                                    <th style="width: 100px;">Unit</th>
                                    <th class="pe-3">Inventory Deduction Impact</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($ingredient_list as $item) { 
                                    $qty_display = rtrim(rtrim(number_format((float)$item["quantity"], 3), '0'), '.');
                                ?>
                                    <tr>
                                        <td class="ps-3 text-muted fw-semibold"><?php echo $idx++; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="p-2 rounded bg-light text-secondary border">
                                                    <i class="bi bi-box-seam"></i>
                                                </div>
                                                <div>
                                                    <span class="fw-semibold text-dark">
                                                        <?php echo htmlspecialchars($item["material_name"]); ?>
                                                    </span>
                                                    <div class="text-muted small">Standard Kitchen Stock Item</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-end font-monospace fw-bold text-dark">
                                            <?php echo htmlspecialchars($qty_display); ?>
                                        </td>
                                        <td>
                                            <span class="recipe-unit-pill">
                                                <?php echo htmlspecialchars($item["unit"]); ?>
                                            </span>
                                        </td>
                                        <td class="pe-3">
                                            <span class="text-muted small d-inline-flex align-items-center gap-1">
                                                <i class="bi bi-arrow-down-circle text-primary"></i>
                                                Deducts <strong><?php echo htmlspecialchars($qty_display); ?> <?php echo htmlspecialchars($item["unit"]); ?></strong> / order
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
            <div class="pos-card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    Quantities adhere to standardized kitchen batch formula
                </span>
                <span class="text-muted small">
                    Showing <strong><?php echo $total_ingredients; ?></strong> raw material <?php echo $total_ingredients === 1 ? 'input' : 'inputs'; ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Side Column: Technical Reference & Production Flow -->
    <div class="col-12 col-lg-4">
        <!-- Deduction Mechanics Card -->
        <div class="pos-card mb-4">
            <div class="pos-card-header">
                <span class="pos-card-title">
                    <i class="bi bi-diagram-3 text-secondary me-2"></i>Inventory Auto-Deduction
                </span>
            </div>
            <div class="pos-card-body p-3">
                <div class="recipe-flow-card mb-3">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="badge bg-primary text-white rounded-pill px-2">1</span>
                        <div>
                            <strong class="small d-block text-dark">Order Entry</strong>
                            <span class="text-muted" style="font-size: 0.78rem;">
                                Order containing <?php echo htmlspecialchars($recipe["menu_item_name"]); ?> is placed.
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <span class="badge bg-warning text-dark rounded-pill px-2">2</span>
                        <div>
                            <strong class="small d-block text-dark">Kitchen Display</strong>
                            <span class="text-muted" style="font-size: 0.78rem;">
                                Ticket displays preparation requirement to kitchen crew.
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        <span class="badge bg-success text-white rounded-pill px-2">3</span>
                        <div>
                            <strong class="small d-block text-dark">Status &rarr; Completed</strong>
                            <span class="text-muted" style="font-size: 0.78rem;">
                                POS reads this BOM and subtracts <?php echo $total_ingredients; ?> raw materials from current inventory.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2 p-2 bg-light rounded border text-muted small">
                    <i class="bi bi-info-circle text-primary fs-5"></i>
                    <span>
                        If order quantity is multiple servings (e.g. 3x), deduction scales proportionally (3 &times; BOM).
                    </span>
                </div>
            </div>
        </div>

        <!-- Recipe Information Card -->
        <div class="pos-card">
            <div class="pos-card-header">
                <span class="pos-card-title">
                    <i class="bi bi-info-circle text-primary me-2"></i>Formulation Dossier
                </span>
            </div>
            <div class="pos-card-body p-3">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Formula ID</span>
                    <span class="font-monospace fw-semibold text-dark">#REC-<?php echo $recipe["id"]; ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Dish Name</span>
                    <span class="fw-semibold text-dark"><?php echo htmlspecialchars($recipe["menu_item_name"]); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Catalog Price</span>
                    <span class="fw-semibold text-dark">Rs. <?php echo number_format($recipe["price"], 2); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted small">Access Privileges</span>
                    <span class="badge bg-light text-secondary border">Admin &bull; Kitchen</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>