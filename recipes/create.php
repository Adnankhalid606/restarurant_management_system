<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$menu_items = mysqli_query(
    $conn,
    "SELECT id, name
     FROM menu_items
     WHERE is_available = 1
     ORDER BY name ASC"
);


$materials = mysqli_query(
    $conn,
    "SELECT id, name, unit
     FROM raw_materials
     ORDER BY name ASC"
);

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $menu_item_id = isset($_POST["menu_item_id"]) ? (int) $_POST["menu_item_id"] : 0;
    $raw_material_ids = $_POST["raw_material_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if ($menu_item_id <= 0) {
        $error_message = "Please select a valid menu item.";
    } else {
        // Check if recipe already exists for this menu item
        $check_sql = "SELECT id FROM recipes WHERE menu_item_id = ? LIMIT 1";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $menu_item_id);
        mysqli_stmt_execute($check_stmt);
        $existing_recipe = mysqli_fetch_assoc(mysqli_stmt_get_result($check_stmt));

        if ($existing_recipe) {
            $error_message = "A recipe already exists for this menu item. Please edit the existing recipe instead.";
        } else {
            mysqli_begin_transaction($conn);

            try {
                // Create recipe
                $sql = "INSERT INTO recipes
                        (menu_item_id)
                        VALUES (?)";

                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $menu_item_id);

                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception("Failed to create recipe record.");
                }

                $recipe_id = mysqli_insert_id($conn);

                // Create recipe items
                for ($i = 0; $i < count($raw_material_ids); $i++) {
                    $raw_material_id = (int) $raw_material_ids[$i];
                    $quantity = (float) $quantities[$i];

                    if ($raw_material_id > 0 && $quantity > 0) {
                        $sql = "INSERT INTO recipe_items
                                (recipe_id, raw_material_id, quantity)
                                VALUES (?, ?, ?)";

                        $stmt = mysqli_prepare($conn, $sql);
                        mysqli_stmt_bind_param($stmt, "iid", $recipe_id, $raw_material_id, $quantity);

                        if (!mysqli_stmt_execute($stmt)) {
                            throw new Exception("Failed to add recipe ingredient.");
                        }
                    }
                }

                mysqli_commit($conn);

                header("Location: view.php?id=" . $recipe_id);
                exit;
            } catch (Exception $error) {
                mysqli_rollback($conn);
                $error_message = $error->getMessage();
            }
        }
    }
}

// Convert results to arrays for clean PHP rendering
$menu_items_list = [];
if ($menu_items) {
    while ($item = mysqli_fetch_assoc($menu_items)) {
        $menu_items_list[] = $item;
    }
}

$materials_list = [];
if ($materials) {
    while ($material = mysqli_fetch_assoc($materials)) {
        $materials_list[] = $material;
    }
}

$page_title = "Create Recipe";
$active_menu = "recipes";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Create Recipe Formulation</h2>
        <p class="page-header-subtitle">Standardize ingredient quantities and raw material deduction per serving</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Recipes</span>
        </a>
    </div>
</div>

<?php if (!empty($error_message)) { ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Recipe Creation Failed:</strong> <?php echo htmlspecialchars($error_message); ?>
        </div>
    </div>
<?php } ?>

<form method="POST" id="recipeForm">
    <div class="row g-4">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <!-- 1. Menu Item / Dish Selection Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <span class="recipe-flow-step-num">1</span>Target Menu Item
                    </span>
                    <span class="badge bg-light text-dark border">
                        Required Dish
                    </span>
                </div>
                <div class="pos-card-body p-4">
                    <label for="menuItemSelect" class="form-label pos-form-label">
                        Select Dish / Prepared Item <span class="text-danger">*</span>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-egg-fried text-muted"></i>
                        </span>
                        <select name="menu_item_id" id="menuItemSelect" class="form-select pos-form-control border-start-0" required>
                            <option value="">Select Menu Item...</option>
                            <?php foreach ($menu_items_list as $item) { ?>
                                <option value="<?php echo $item["id"]; ?>">
                                    <?php echo htmlspecialchars($item["name"]); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-text text-muted small mt-2">
                        <i class="bi bi-info-circle me-1"></i>
                        Only available items from the menu catalogue are shown. Each recipe defines ingredients for exactly 1 standard serving of this dish.
                    </div>
                </div>
            </div>

            <!-- 2. Ingredients / Raw Materials Builder Card -->
            <div class="pos-card">
                <div class="pos-card-header d-flex justify-content-between align-items-center">
                    <span class="pos-card-title">
                        <span class="recipe-flow-step-num">2</span>Recipe Ingredients (Bill of Materials)
                    </span>
                    <span class="badge bg-light text-primary border">
                        Multi-Ingredient Formulation
                    </span>
                </div>
                <div class="pos-card-body p-4">
                    <p class="text-muted small mb-3">
                        Specify each raw material and the exact portion needed. These quantities will be automatically deducted from inventory when this dish is completed in the Kitchen Display.
                    </p>

                    <!-- Ingredients List Container -->
                    <div id="ingredients" class="d-flex flex-column gap-3">
                        <!-- Initial Ingredient Row -->
                        <div class="ingredient-row p-3 rounded border bg-white recipe-ingredient-row">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-6">
                                    <label class="form-label pos-form-label mb-1">
                                        Raw Material <span class="text-danger">*</span>
                                    </label>
                                    <select name="raw_material_id[]" class="form-select pos-form-control raw-material-select" required onchange="handleMaterialChange(this)">
                                        <option value="">Select Raw Material</option>
                                        <?php foreach ($materials_list as $material) { ?>
                                            <option value="<?php echo $material["id"]; ?>" data-unit="<?php echo htmlspecialchars($material["unit"]); ?>">
                                                <?php echo htmlspecialchars($material["name"]); ?> (<?php echo htmlspecialchars($material["unit"]); ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-9 col-md-5">
                                    <label class="form-label pos-form-label mb-1">
                                        Quantity Required <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input
                                            type="number"
                                            name="quantity[]"
                                            step="0.001"
                                            min="0.001"
                                            class="form-control pos-form-control text-end quantity-input"
                                            placeholder="0.000"
                                            required
                                            oninput="updateIngredientCount()"
                                        >
                                        <span class="input-group-text bg-light text-muted unit-label" style="min-width: 68px; justify-content: center; font-size: 0.8rem; font-weight: 600;">
                                            unit
                                        </span>
                                    </div>
                                </div>
                                <div class="col-3 col-md-1 text-end">
                                    <label class="form-label pos-form-label mb-1 d-none d-md-block">&nbsp;</label>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-danger btn-sm w-100 d-flex align-items-center justify-content-center" 
                                        onclick="removeIngredient(this)" 
                                        title="Remove Ingredient"
                                        style="height: 38px;"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Add Row Action Button -->
                    <div class="mt-3">
                        <button
                            type="button"
                            class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                            onclick="addIngredient()"
                        >
                            <i class="bi bi-plus-circle"></i>
                            <span>Add Another Ingredient</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Configuration Summary Column -->
        <div class="col-12 col-lg-4">
            <!-- Formulation Summary Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-clipboard2-check text-primary me-2"></i>Formulation Summary
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small">Configured Ingredients</span>
                        <span id="ingredientCounter" class="fw-bold text-dark fs-5">1</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small">Standard Portion</span>
                        <span class="badge bg-light text-dark border">1 Serving</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small">Stock Deduction</span>
                        <span class="badge badge-subtle badge-status-ready">Automatic</span>
                    </div>

                    <div class="mt-4 d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Create Recipe</span>
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
                        <i class="bi bi-diagram-3 text-secondary me-2"></i>Deduction Mechanics
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="recipe-flow-card mb-3">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-primary text-white rounded-pill px-2">1</span>
                            <div>
                                <strong class="small d-block text-dark">Order Taken</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Waiter registers order with this menu dish.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-warning text-dark rounded-pill px-2">2</span>
                            <div>
                                <strong class="small d-block text-dark">Kitchen Prepares</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Kitchen team cooks the meal per standardized BOM.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-success text-white rounded-pill px-2">3</span>
                            <div>
                                <strong class="small d-block text-dark">Order Completed</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Status update triggers automatic real-time deduction of raw materials.</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Decimal support up to 3 places (0.001) allows precise gram, millilitre, or fractional portion control.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function addIngredient() {
    const ingredients = document.getElementById("ingredients");
    const firstRow = document.querySelector(".ingredient-row");
    if (!ingredients || !firstRow) return;

    const newRow = firstRow.cloneNode(true);

    const select = newRow.querySelector("select");
    const input = newRow.querySelector("input");
    const unitLabel = newRow.querySelector(".unit-label");

    if (select) select.value = "";
    if (input) input.value = "";
    if (unitLabel) unitLabel.textContent = "unit";

    ingredients.appendChild(newRow);
    updateIngredientCount();
}

function removeIngredient(btn) {
    const rows = document.querySelectorAll(".ingredient-row");
    const row = btn.closest(".ingredient-row");
    if (!row) return;

    if (rows.length > 1) {
        row.remove();
    } else {
        const select = row.querySelector("select");
        const input = row.querySelector("input");
        const unitLabel = row.querySelector(".unit-label");
        if (select) select.value = "";
        if (input) input.value = "";
        if (unitLabel) unitLabel.textContent = "unit";
    }
    updateIngredientCount();
}

function handleMaterialChange(select) {
    const row = select.closest(".ingredient-row");
    if (!row) return;
    const unitLabel = row.querySelector(".unit-label");
    const selectedOption = select.options[select.selectedIndex];
    const unit = selectedOption ? (selectedOption.getAttribute("data-unit") || "unit") : "unit";
    if (unitLabel) {
        unitLabel.textContent = unit;
    }
    updateIngredientCount();
}

function updateIngredientCount() {
    const rows = document.querySelectorAll(".ingredient-row");
    let count = 0;
    rows.forEach(function (r) {
        const s = r.querySelector("select");
        if (s && s.value) {
            count++;
        }
    });
    const counter = document.getElementById("ingredientCounter");
    if (counter) {
        counter.textContent = (count > 0 ? count : rows.length).toString();
    }
}
</script>

<?php

require_once "../includes/footer.php";

?>