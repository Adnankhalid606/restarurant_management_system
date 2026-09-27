<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter", "kitchen"]);

$sql = "SELECT * FROM restaurant_tables ORDER BY table_number ASC";

$result = mysqli_query($conn, $sql);

$tables = [];
$status_counts = [
    'all' => 0,
    'available' => 0,
    'occupied' => 0,
    'reserved' => 0
];

while ($row = mysqli_fetch_assoc($result)) {
    $tables[] = $row;
    $status_counts['all']++;
    $s = $row['status'] ?? 'available';
    if (isset($status_counts[$s])) {
        $status_counts[$s]++;
    }
}

$page_title = "Restaurant Tables";
$active_menu = "table";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Dining Tables</h2>
        <p class="page-header-subtitle">Floor plan overview &amp; real-time seating availability</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-plus-lg"></i>
                <span>Add Table</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Table Status Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Tables</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $status_counts['all']; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-grid-3x3-gap fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Available</span>
                <span class="fs-4 fw-bold text-success"><?php echo $status_counts['available']; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Occupied</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $status_counts['occupied']; ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-fire fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Reserved</span>
                <span class="fs-4 fw-bold text-primary"><?php echo $status_counts['reserved']; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-bookmark-fill fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Controls & Layout Toggle -->
<div class="pos-card mb-4">
    <div class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <!-- Filter Pills -->
        <div class="btn-group btn-group-sm" role="group" id="tableFilterGroup">
            <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                All (<?php echo $status_counts['all']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="available">
                Available (<?php echo $status_counts['available']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="occupied">
                Occupied (<?php echo $status_counts['occupied']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="reserved">
                Reserved (<?php echo $status_counts['reserved']; ?>)
            </button>
        </div>

        <!-- View Switcher Tabs -->
        <ul class="nav nav-pills nav-pills-sm" id="viewTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active py-1 px-3" id="floor-tab" data-bs-toggle="tab" data-bs-target="#floorView" type="button" role="tab">
                    <i class="bi bi-grid-fill me-1"></i>Floor Grid
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link py-1 px-3" id="list-tab" data-bs-toggle="tab" data-bs-target="#listView" type="button" role="tab">
                    <i class="bi bi-list-ul me-1"></i>List View
                </button>
            </li>
        </ul>
    </div>
</div>

<?php if (empty($tables)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center">
        <div class="text-muted mb-3">
            <i class="bi bi-grid-3x3-gap fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Tables Configured</h5>
        <p class="text-muted small mb-4">There are currently no dining tables in the database.</p>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="create.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Add First Table
            </a>
        <?php } ?>
    </div>
<?php } else { ?>

    <div class="tab-content" id="tableTabContent">
        <!-- Visual Floor Grid View -->
        <div class="tab-pane fade show active" id="floorView" role="tabpanel">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-3" id="tableGridContainer">
                <?php foreach ($tables as $t) {
                    $st = $t['status'] ?? 'available';
                    $status_class = match($st) {
                        'occupied' => 'table-status-occupied',
                        'reserved' => 'table-status-reserved',
                        default => 'table-status-available',
                    };
                    $badge_class = match($st) {
                        'occupied' => 'bg-danger-subtle text-danger border-danger-subtle',
                        'reserved' => 'bg-info-subtle text-info border-info-subtle',
                        default => 'bg-success-subtle text-success border-success-subtle',
                    };
                    $status_icon = match($st) {
                        'occupied' => 'bi-fire',
                        'reserved' => 'bi-bookmark-fill',
                        default => 'bi-check-circle-fill',
                    };
                ?>
                    <div class="col table-item-col" data-status="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="table-card <?php echo $status_class; ?> h-100 d-flex flex-column justify-content-between">
                            <div>
                                <div class="d-flex align-items-start justify-content-between mb-3">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="rounded-2 p-2 bg-light border text-primary">
                                            <i class="bi bi-grid-3x3-gap fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="table-card-number">Table <?php echo htmlspecialchars($t['table_number'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <span class="text-muted small">ID: #<?php echo $t['id']; ?></span>
                                        </div>
                                    </div>
                                    <span class="badge <?php echo $badge_class; ?> border text-capitalize px-2 py-1">
                                        <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>

                                <div class="mb-3">
                                    <span class="table-capacity-chip">
                                        <i class="bi bi-people-fill text-secondary"></i>
                                        <strong><?php echo htmlspecialchars($t['capacity'], ENT_QUOTES, 'UTF-8'); ?></strong> Seats Capacity
                                    </span>
                                </div>
                            </div>

                            <!-- Actions -->
                            <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                <div class="pt-2 border-top d-flex align-items-center justify-content-end gap-2">
                                    <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit Table">
                                        <i class="bi bi-pencil me-1"></i>Edit
                                    </a>
                                    <a href="delete.php?id=<?php echo $t['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm" 
                                       title="Delete Table"
                                       onclick="return confirm('Are you sure you want to delete Table <?php echo htmlspecialchars($t['table_number'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </div>
                            <?php } else { ?>
                                <div class="pt-2 border-top text-muted small text-end">
                                    <i class="bi bi-info-circle me-1"></i>Status View
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Structured List Table View -->
        <div class="tab-pane fade" id="listView" role="tabpanel">
            <div class="pos-card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="tablesListTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Table Number</th>
                                <th>Capacity</th>
                                <th>Current Status</th>
                                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                    <th style="width: 140px;" class="text-end">Actions</th>
                                <?php } ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tables as $t) {
                                $st = $t['status'] ?? 'available';
                                $badge_class = match($st) {
                                    'occupied' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    'reserved' => 'bg-info-subtle text-info border-info-subtle',
                                    default => 'bg-success-subtle text-success border-success-subtle',
                                };
                            ?>
                                <tr data-status="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="text-muted fw-semibold">#<?php echo $t['id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                            <i class="bi bi-grid-3x3-gap text-secondary"></i>
                                            Table <?php echo htmlspecialchars($t['table_number'], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="table-capacity-chip">
                                            <i class="bi bi-people-fill text-secondary"></i>
                                            <?php echo htmlspecialchars($t['capacity'], ENT_QUOTES, 'UTF-8'); ?> Seats
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?> border text-capitalize px-2 py-1">
                                            <?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                        <td class="text-end">
                                            <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit Table">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="delete.php?id=<?php echo $t['id']; ?>" 
                                               class="btn btn-outline-danger btn-sm" 
                                               title="Delete Table"
                                               onclick="return confirm('Are you sure you want to delete Table <?php echo htmlspecialchars($t['table_number'], ENT_QUOTES, 'UTF-8'); ?>?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </td>
                                    <?php } ?>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Client-side filter script for Table display -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('#tableFilterGroup button');
        const gridItems = document.querySelectorAll('.table-item-col');
        const tableRows = document.querySelectorAll('#tablesListTable tbody tr');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                gridItems.forEach(item => {
                    if (filter === 'all' || item.getAttribute('data-status') === filter) {
                        item.classList.remove('d-none');
                    } else {
                        item.classList.add('d-none');
                    }
                });

                tableRows.forEach(row => {
                    if (filter === 'all' || row.getAttribute('data-status') === filter) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>