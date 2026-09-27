<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$sql = "SELECT *
        FROM raw_materials
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$materials = [];
$total_count = 0;
$low_stock_count = 0;
$out_of_stock_count = 0;
$stock_ok_count = 0;

while ($m = mysqli_fetch_assoc($result)) {
    $materials[] = $m;
    $total_count++;
    $current = (float)$m['current_stock'];
    $min = (float)$m['minimum_stock'];

    if ($current <= 0) {
        $out_of_stock_count++;
    } elseif ($current <= $min) {
        $low_stock_count++;
    } else {
        $stock_ok_count++;
    }
}

$page_title = "Raw Materials & Inventory";
$active_menu = "inventory";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Raw Materials &amp; Stock</h2>
        <p class="page-header-subtitle">Kitchen ingredients inventory, stock quantities &amp; reorder thresholds</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="transactions.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left-right"></i>
            <span>Transactions</span>
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="add-transaction.php" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-slash-minus"></i>
                <span>Stock Adjustment</span>
            </a>
            <a href="create-material.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Add Material</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Inventory Status Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Items</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-box-seam fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Stock OK</span>
                <span class="fs-4 fw-bold text-success"><?php echo $stock_ok_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Low Stock</span>
                <span class="fs-4 fw-bold text-warning"><?php echo $low_stock_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-pending p-2 rounded">
                <i class="bi bi-exclamation-triangle-fill fs-4 text-warning"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Out of Stock</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $out_of_stock_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-x-circle-fill fs-4 text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Controls Bar -->
<div class="pos-card mb-4">
    <div class="p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control pos-form-control border-start-0" 
                        id="materialSearch" 
                        placeholder="Search materials by name or unit..."
                    >
                </div>
            </div>

            <div class="col-12 col-md-auto d-flex align-items-center gap-3">
                <div class="btn-group btn-group-sm" role="group" id="stockFilterGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                        All (<?php echo $total_count; ?>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="ok">
                        OK (<?php echo $stock_ok_count; ?>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="low">
                        Low (<?php echo $low_stock_count; ?>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="out">
                        Out (<?php echo $out_of_stock_count; ?>)
                    </button>
                </div>

                <div class="text-muted small d-none d-md-block">
                    <span id="filteredMaterialCount"><?php echo $total_count; ?></span> of <?php echo $total_count; ?> items
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($materials)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center shadow-sm">
        <div class="text-muted mb-3">
            <i class="bi bi-box-seam fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Raw Materials Found</h5>
        <p class="text-muted small mb-4">There are currently no raw material items recorded in inventory.</p>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="create-material.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add First Material
            </a>
        <?php } ?>
    </div>
<?php } else { ?>
    <!-- Materials Table -->
    <div class="pos-card overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="materialsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Material Name</th>
                        <th>Unit</th>
                        <th class="text-end">Current Stock</th>
                        <th class="text-end">Minimum Level</th>
                        <th style="width: 140px;">Stock Status</th>
                        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                            <th style="width: 130px;" class="text-end">Actions</th>
                        <?php } else { ?>
                            <th style="width: 100px;" class="text-end">Access</th>
                        <?php } ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $m) {
                        $current = (float)$m['current_stock'];
                        $min = (float)$m['minimum_stock'];
                        
                        if ($current <= 0) {
                            $filter_tag = 'out';
                            $badge_class = 'bg-danger-subtle text-danger border-danger-subtle';
                            $status_text = 'Out of Stock';
                            $status_icon = 'bi-x-circle-fill';
                        } elseif ($current <= $min) {
                            $filter_tag = 'low';
                            $badge_class = 'bg-warning-subtle text-warning border-warning-subtle';
                            $status_text = 'Low Stock';
                            $status_icon = 'bi-exclamation-triangle-fill';
                        } else {
                            $filter_tag = 'ok';
                            $badge_class = 'bg-success-subtle text-success border-success-subtle';
                            $status_text = 'Stock OK';
                            $status_icon = 'bi-check-circle-fill';
                        }
                    ?>
                        <tr class="material-row" 
                            data-name="<?php echo htmlspecialchars(mb_strtolower($m['name'] . ' ' . $m['unit']), ENT_QUOTES, 'UTF-8'); ?>"
                            data-status="<?php echo $filter_tag; ?>">
                            <td class="text-muted fw-semibold">#<?php echo $m["id"]; ?></td>
                            <td>
                                <div class="fw-semibold text-dark d-flex align-items-center">
                                    <i class="bi bi-box-seam text-primary me-2"></i>
                                    <span><?php echo htmlspecialchars($m["name"], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border px-2 py-1 text-uppercase">
                                    <?php echo htmlspecialchars($m["unit"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold fs-6 <?php echo ($current <= $min) ? 'text-danger' : 'text-dark'; ?>">
                                    <?php echo htmlspecialchars($m["current_stock"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="text-muted small"><?php echo htmlspecialchars($m["unit"], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="text-end text-muted small">
                                <?php echo htmlspecialchars($m["minimum_stock"], ENT_QUOTES, 'UTF-8'); ?>
                                <?php echo htmlspecialchars($m["unit"], ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?> border text-capitalize px-2 py-1">
                                    <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit-material.php?id=<?php echo $m["id"]; ?>" 
                                           class="btn btn-outline-secondary btn-sm" 
                                           title="Edit Material">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="delete-material.php?id=<?php echo $m["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           title="Delete Material"
                                           onclick="return confirm('Are you sure you want to delete raw material <?php echo htmlspecialchars(addslashes($m['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Materials used in recipes, purchases, or recorded transactions cannot be deleted.');">
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
        <div id="noMaterialResults" class="p-4 text-center text-muted d-none">
            <i class="bi bi-search fs-3 d-block mb-2 text-secondary opacity-50"></i>
            No matching raw materials found
        </div>
    </div>

    <!-- Client-side Search and Filter Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('materialSearch');
        const filterButtons = document.querySelectorAll('#stockFilterGroup button');
        const rows = document.querySelectorAll('.material-row');
        const countLabel = document.getElementById('filteredMaterialCount');
        const noResults = document.getElementById('noMaterialResults');

        let currentFilter = 'all';

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const status = row.getAttribute('data-status') || '';

                const matchesQuery = !query || name.includes(query);
                const matchesFilter = (currentFilter === 'all') || (status === currentFilter);

                if (matchesQuery && matchesFilter) {
                    row.classList.remove('d-none');
                    visibleCount++;
                } else {
                    row.classList.add('d-none');
                }
            });

            if (countLabel) {
                countLabel.textContent = visibleCount;
            }

            if (visibleCount === 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        }

        searchInput.addEventListener('input', applyFilters);

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter');
                applyFilters();
            });
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>