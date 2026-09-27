<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$sql = "SELECT
            recipes.id,
            menu_items.name AS menu_item_name,
            recipes.created_at

        FROM recipes

        JOIN menu_items
            ON recipes.menu_item_id = menu_items.id

        ORDER BY recipes.id DESC";

$result = mysqli_query($conn, $sql);

$recipes = [];
$total_recipes = 0;
while ($row = mysqli_fetch_assoc($result)) {
    $recipes[] = $row;
    $total_recipes++;
}

$page_title = "Recipes Directory";
$active_menu = "recipes";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Recipe Formulations</h2>
        <p class="page-header-subtitle">Standardized Bill of Materials (BOM) &amp; Kitchen Inventory Deductions</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Create Recipe</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Active Recipes</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_recipes; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-book-half fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Inventory Linked</span>
                <span class="fs-4 fw-bold text-success"><?php echo $total_recipes; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-link-45deg fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Deduction Method</span>
                <span class="fs-4 fw-bold text-primary">Auto BOM</span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-box-arrow-down fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Role Access</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo htmlspecialchars(ucfirst($_SESSION['role'] ?? 'Staff')); ?></span>
            </div>
            <div class="badge-subtle badge-status-pending p-2 rounded">
                <i class="bi bi-shield-check fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Controls Bar -->
<div class="pos-card mb-4">
    <div class="pos-card-body p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control pos-form-control border-start-0" 
                        id="recipeSearch" 
                        placeholder="Search menu item or formula ID..."
                    >
                </div>
            </div>
            <div class="col-12 col-md-auto text-md-end text-muted small">
                Showing <strong id="visibleCount"><?php echo $total_recipes; ?></strong> of <?php echo $total_recipes; ?> recipes
            </div>
        </div>
    </div>
</div>

<!-- Recipes List Table -->
<div class="pos-card">
    <div class="pos-card-header">
        <span class="pos-card-title">
            <i class="bi bi-journal-text text-primary me-2"></i>Configured Menu Item Recipes
        </span>
        <span class="badge bg-light text-dark border">
            Auto-Sync on Order Complete
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if ($total_recipes === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-journal-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Recipe Formulations Found</h5>
                <p class="text-muted small mb-3">
                    Recipes link menu dishes to inventory raw materials for automatic stock deduction upon kitchen order completion.
                </p>
                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Create First Recipe
                    </a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="recipesTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Recipe ID</th>
                            <th>Menu Item / Dish</th>
                            <th>Stock Deduction Link</th>
                            <th>Created On</th>
                            <th class="text-end pe-3" style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recipes as $recipe) { ?>
                            <tr class="recipe-table-row">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #REC-<?php echo str_pad($recipe["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-dish p-2 rounded bg-light text-primary border">
                                            <i class="bi bi-egg-fried fs-5"></i>
                                        </div>
                                        <div>
                                            <a href="view.php?id=<?php echo $recipe["id"]; ?>" class="fw-semibold text-dark text-decoration-none recipe-name-text">
                                                <?php echo htmlspecialchars($recipe["menu_item_name"]); ?>
                                            </a>
                                            <div class="text-muted small">Standard Serving Specification</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Auto-Deducts Materials</span>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small d-block">
                                        <?php echo formatDate($recipe["created_at"]); ?>
                                    </span>
                                    <span class="text-muted small" style="font-size: 0.75rem;">
                                        <?php echo formatTime($recipe["created_at"]); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="view.php?id=<?php echo $recipe["id"]; ?>" 
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                                           title="View Bill of Materials">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>

                                        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                            <a href="delete.php?id=<?php echo $recipe["id"]; ?>" 
                                               class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                                               title="Delete Recipe"
                                               onclick="return confirm('Are you sure you want to delete the recipe formulation for <?php echo htmlspecialchars(addslashes($recipe['menu_item_name'])); ?>? This will stop automatic inventory deduction for this item.');">
                                                <i class="bi bi-trash"></i>
                                                <span>Delete</span>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Client-side No Results State -->
            <div id="noSearchResults" class="text-center py-5 d-none">
                <i class="bi bi-search text-muted fs-2 mb-2 d-block"></i>
                <div class="fw-semibold text-dark">No matching recipes found</div>
                <div class="text-muted small">Try searching for a different dish name or formula ID</div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('recipeSearch');
    const tableRows = document.querySelectorAll('.recipe-table-row');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noSearchResults');
    const recipesTable = document.getElementById('recipesTable');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let matches = 0;

            tableRows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount) {
                visibleCount.textContent = matches.toString();
            }

            if (noResults && recipesTable) {
                if (matches === 0 && tableRows.length > 0) {
                    noResults.classList.remove('d-none');
                    recipesTable.classList.add('d-none');
                } else {
                    noResults.classList.add('d-none');
                    recipesTable.classList.remove('d-none');
                }
            }
        });
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>