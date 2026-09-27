<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter", "kitchen"]);

$sql = "SELECT * FROM menu_items ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$items = [];
$total_items = 0;
$available_count = 0;
$unavailable_count = 0;
$categories = [];

while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
    $total_items++;
    if (!empty($row['is_available'])) {
        $available_count++;
    } else {
        $unavailable_count++;
    }
    $cat = trim($row['category'] ?? '');
    if ($cat !== '') {
        $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    }
}

$page_title = "Menu Items";
$active_menu = "menu";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Menu Directory</h2>
        <p class="page-header-subtitle">Food &amp; beverage catalogue, prices &amp; kitchen availability</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Add Menu Item</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Menu Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Dishes</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_items; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-card-list fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Available</span>
                <span class="fs-4 fw-bold text-success"><?php echo $available_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Unavailable</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $unavailable_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-slash-circle fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Categories</span>
                <span class="fs-4 fw-bold text-primary"><?php echo count($categories); ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-tags-fill fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="pos-card mb-4">
    <div class="p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <!-- Search Input -->
            <div class="col-12 col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control pos-form-control border-start-0" 
                        id="menuSearch" 
                        placeholder="Search dishes or categories..."
                    >
                </div>
            </div>

            <!-- Category Filter Dropdown -->
            <div class="col-6 col-md-3 col-lg-3">
                <select class="form-select pos-form-control" id="categoryFilter">
                    <option value="all">All Categories (<?php echo count($categories); ?>)</option>
                    <?php foreach ($categories as $catName => $catCount) { ?>
                        <option value="<?php echo htmlspecialchars(mb_strtolower($catName), ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?> (<?php echo $catCount; ?>)
                        </option>
                    <?php } ?>
                </select>
            </div>

            <!-- Availability Filter Buttons -->
            <div class="col-6 col-md-auto">
                <div class="btn-group btn-group-sm w-100" role="group" id="availabilityFilterGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                        All
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="available">
                        Available
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="unavailable">
                        Unavailable
                    </button>
                </div>
            </div>

            <!-- Result Count -->
            <div class="col-12 col-md-auto text-md-end text-muted small">
                <span id="filteredMenuCount"><?php echo $total_items; ?></span> of <?php echo $total_items; ?> items
            </div>
        </div>
    </div>
</div>

<?php if (empty($items)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center shadow-sm">
        <div class="text-muted mb-3">
            <i class="bi bi-card-list fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Menu Items Configured</h5>
        <p class="text-muted small mb-4">There are currently no food or beverage items registered in the menu.</p>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add First Menu Item
            </a>
        <?php } ?>
    </div>
<?php } else { ?>
    <!-- Menu Items Table -->
    <div class="pos-card overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="menuTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-end">Selling Price</th>
                        <th style="width: 140px;">Availability</th>
                        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                            <th style="width: 120px;" class="text-end">Actions</th>
                        <?php } else { ?>
                            <th style="width: 100px;" class="text-end">Status</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) {
                        $is_avail = !empty($item["is_available"]);
                        $avail_key = $is_avail ? 'available' : 'unavailable';
                        $cat_clean = mb_strtolower(trim($item['category'] ?? ''));
                    ?>
                        <tr class="menu-row" 
                            data-name="<?php echo htmlspecialchars(mb_strtolower($item['name']), ENT_QUOTES, 'UTF-8'); ?>"
                            data-category="<?php echo htmlspecialchars($cat_clean, ENT_QUOTES, 'UTF-8'); ?>"
                            data-availability="<?php echo $avail_key; ?>">
                            <td class="text-muted fw-semibold">#<?php echo $item["id"]; ?></td>
                            <td>
                                <div class="fw-semibold text-dark d-flex align-items-center">
                                    <i class="bi bi-egg-fried text-primary me-2"></i>
                                    <span><?php echo htmlspecialchars($item["name"], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($item["category"])) { ?>
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <i class="bi bi-tag text-muted me-1"></i><?php echo htmlspecialchars($item["category"], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="text-muted fst-italic small">Uncategorized</span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-dark fs-6">
                                    Rs. <?php echo number_format($item["price"], 2); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_avail) { ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle-fill me-1"></i>Available
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border px-2 py-1">
                                        <i class="bi bi-slash-circle me-1"></i>Unavailable
                                    </span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit.php?id=<?php echo $item["id"]; ?>" 
                                           class="btn btn-outline-secondary btn-sm" 
                                           title="Edit Menu Item">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete.php?id=<?php echo $item["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           title="Delete Menu Item"
                                           onclick="return confirm('Are you sure you want to delete menu item <?php echo htmlspecialchars(addslashes($item['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Items with existing orders or recipes cannot be deleted.');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                <?php } else { ?>
                                    <span class="text-muted small">View Only</span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- No Search Results Found Row (hidden by default) -->
        <div id="noMenuResults" class="p-4 text-center text-muted d-none">
            <i class="bi bi-search fs-3 d-block mb-2 text-secondary opacity-50"></i>
            No matching menu items found
        </div>
    </div>

    <!-- Client-side Search and Filter Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('menuSearch');
        const categoryFilter = document.getElementById('categoryFilter');
        const availButtons = document.querySelectorAll('#availabilityFilterGroup button');
        const rows = document.querySelectorAll('.menu-row');
        const countLabel = document.getElementById('filteredMenuCount');
        const noResults = document.getElementById('noMenuResults');

        let currentAvail = 'all';

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            const selectedCat = categoryFilter.value;
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const cat = row.getAttribute('data-category') || '';
                const avail = row.getAttribute('data-availability') || '';

                const matchesQuery = !query || name.includes(query) || cat.includes(query);
                const matchesCat = (selectedCat === 'all') || (cat === selectedCat);
                const matchesAvail = (currentAvail === 'all') || (avail === currentAvail);

                if (matchesQuery && matchesCat && matchesAvail) {
                    row.classList.remove('d-none');
                    visibleCount++;
                } else {
                    row.classList.add('d-none');
                }
            });

            countLabel.textContent = visibleCount;

            if (visibleCount === 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        }

        searchInput.addEventListener('input', applyFilters);
        categoryFilter.addEventListener('change', applyFilters);

        availButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                availButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentAvail = this.getAttribute('data-filter');
                applyFilters();
            });
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>