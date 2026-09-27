<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$sql = "
    SELECT
        id,
        name,
        unit,
        current_stock,
        minimum_stock
    FROM raw_materials
    ORDER BY name ASC
";

$result = mysqli_query($conn, $sql);

$rows = [];
$total_materials = 0;
$low_stock_count = 0;
$normal_stock_count = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $rows[] = $row;
    $total_materials++;
    if ($row["current_stock"] <= $row["minimum_stock"]) {
        $low_stock_count++;
    } else {
        $normal_stock_count++;
    }
}

$page_title = "Inventory Report";
$active_menu = "reports";

require_once "../includes/header.php";
?>

<!-- Print-Only Report Header -->
<div class="d-none d-print-block mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
        <div>
            <h3 class="mb-0 fw-bold">RestoBar POS &amp; Management</h3>
            <p class="text-muted small mb-0">Raw Materials Inventory Audit &amp; Reorder Level Report</p>
        </div>
        <div class="text-end">
            <div class="fw-semibold">Audit Date: <?php echo formatDate('now'); ?></div>
            <small class="text-muted">Printed on <?php echo formatTime('now'); ?></small>
        </div>
    </div>
</div>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Inventory Stock Audit Report</h2>
        <p class="page-header-subtitle">Current raw material reserves, reorder thresholds, inventory balances &amp; stock alerts</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reports Hub</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Report</span>
        </button>
    </div>
</div>

<!-- Inventory Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Tracked Materials</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_materials; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-box-seam fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Healthy Reserves</span>
                <span class="fs-4 fw-bold text-success"><?php echo $normal_stock_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Low Stock Warnings</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $low_stock_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-exclamation-triangle-fill fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Inventory Health</span>
                <span class="fs-4 fw-bold text-primary">
                    <?php 
                        $health_pct = $total_materials > 0 ? round(($normal_stock_count / $total_materials) * 100) : 100;
                        echo $health_pct . '%';
                    ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-heart-pulse fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Details Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-table text-primary me-2"></i>Raw Material Stock Levels
        </span>
        <span class="badge bg-light text-dark border">
            Auto-Sync with Kitchen &amp; Purchases
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if ($total_materials === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-inbox fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Raw Materials Found</h5>
                <p class="text-muted small mb-0">
                    No items exist in the inventory catalogue.
                </p>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Item #</th>
                            <th>Raw Material</th>
                            <th>Unit</th>
                            <th class="text-end">Current Stock</th>
                            <th class="text-end">Minimum Stock</th>
                            <th class="text-center pe-3" style="width: 160px;">Inventory Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) { 
                            $status = "Normal";
                            if ($row["current_stock"] <= $row["minimum_stock"]) {
                                $status = "Low Stock";
                            }
                            $is_low = ($status === "Low Stock");
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #RM-<?php echo str_pad($row["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($row["name"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border">
                                        <?php echo htmlspecialchars($row["unit"]); ?>
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    <strong class="<?php echo $is_low ? 'text-danger' : 'text-dark'; ?>">
                                        <?php echo rtrim(rtrim(number_format((float)$row["current_stock"], 3), '0'), '.'); ?>
                                    </strong>
                                </td>
                                <td class="text-end font-monospace text-muted">
                                    <?php echo rtrim(rtrim(number_format((float)$row["minimum_stock"], 3), '0'), '.'); ?>
                                </td>
                                <td class="text-center pe-3">
                                    <?php if ($is_low) { ?>
                                        <span class="badge-subtle badge-status-cancelled d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            <span>Low Stock</span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Normal</span>
                                        </span>
                                    <?php } ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>