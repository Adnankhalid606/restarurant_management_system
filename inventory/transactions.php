<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once "../includes/pagination.php";

requireRole(["admin", "kitchen"]);

$pg = get_pagination_params(25, [25, 50, 100]);
$q = trim($_GET["q"] ?? "");
$flow = trim($_GET["flow"] ?? "all");
if (!in_array($flow, ["all", "inflow", "outflow"], true)) {
    $flow = "all";
}

// 1. Summary KPI counts across all transactions
$kpi_sql = "SELECT
                COUNT(*) AS total_count,
                SUM(CASE WHEN type IN ('purchase', 'adjustment_in') THEN 1 ELSE 0 END) AS inflow_count,
                SUM(CASE WHEN type NOT IN ('purchase', 'adjustment_in') THEN 1 ELSE 0 END) AS outflow_count
            FROM inventory_transactions";
$kpi_row = mysqli_fetch_assoc(mysqli_query($conn, $kpi_sql));
$total_count = (int) ($kpi_row['total_count'] ?? 0);
$inflow_count = (int) ($kpi_row['inflow_count'] ?? 0);
$outflow_count = (int) ($kpi_row['outflow_count'] ?? 0);

// 2. Filtered WHERE clause
$where_clauses = [];
$bind_params = [];
$bind_types = "";

if ($q !== "") {
    $where_clauses[] = "raw_materials.name LIKE ?";
    $bind_params[] = "%" . $q . "%";
    $bind_types .= "s";
}

if ($flow === "inflow") {
    $where_clauses[] = "inventory_transactions.type IN ('purchase', 'adjustment_in')";
} elseif ($flow === "outflow") {
    $where_clauses[] = "inventory_transactions.type NOT IN ('purchase', 'adjustment_in')";
}

$where_sql = !empty($where_clauses) ? ("WHERE " . implode(" AND ", $where_clauses)) : "";

// 3. Filtered COUNT query
$count_sql = "SELECT COUNT(*) AS total
              FROM inventory_transactions
              JOIN raw_materials ON inventory_transactions.raw_material_id = raw_materials.id
              $where_sql";
$c_stmt = mysqli_prepare($conn, $count_sql);
if ($bind_types !== "") {
    mysqli_stmt_bind_param($c_stmt, $bind_types, ...$bind_params);
}
mysqli_stmt_execute($c_stmt);
$filtered_total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($c_stmt))['total'] ?? 0);
mysqli_stmt_close($c_stmt);

$pagination = finalize_pagination($filtered_total, $pg['page'], $pg['per_page']);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

// 4. Paginated data query
$sql = "SELECT
            inventory_transactions.id,
            raw_materials.name AS material_name,
            raw_materials.unit,
            inventory_transactions.type,
            inventory_transactions.quantity,
            inventory_transactions.created_at
        FROM inventory_transactions
        JOIN raw_materials
            ON inventory_transactions.raw_material_id = raw_materials.id
        $where_sql
        ORDER BY inventory_transactions.id DESC
        LIMIT ? OFFSET ?";

$d_stmt = mysqli_prepare($conn, $sql);
$data_types = $bind_types . "ii";
$data_params = array_merge($bind_params, [$limit, $offset]);
mysqli_stmt_bind_param($d_stmt, $data_types, ...$data_params);
mysqli_stmt_execute($d_stmt);
$result = mysqli_stmt_get_result($d_stmt);

$transactions = [];
while ($t = mysqli_fetch_assoc($result)) {
    $transactions[] = $t;
}
mysqli_stmt_close($d_stmt);

$page_title = "Inventory Transactions Log";
$active_menu = "inventory";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Inventory Transactions</h2>
        <p class="page-header-subtitle">Stock movements audit ledger &amp; kitchen consumption log</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="materials.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-box-seam"></i>
            <span>Materials Directory</span>
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="add-transaction.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Add Transaction</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Transactions Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Movements</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-arrow-left-right fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Stock Inflows (+)</span>
                <span class="fs-4 fw-bold text-success"><?php echo $inflow_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-box-arrow-in-down fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Stock Outflows (&minus;)</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo $outflow_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-box-arrow-up fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search Controls Bar -->
<div class="pos-card mb-4">
    <div class="p-3">
        <form method="GET" class="row g-2 align-items-center justify-content-between">
            <input type="hidden" name="flow" value="<?php echo htmlspecialchars($flow, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="per_page" value="<?php echo (int)$pagination['per_page']; ?>">
            <input type="hidden" name="page" value="1">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        name="q"
                        class="form-control pos-form-control border-start-0" 
                        id="txSearch" 
                        value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Search by material name..."
                    >
                    <button type="submit" class="btn btn-outline-primary">Search</button>
                    <?php if ($q !== "" || $flow !== "all") { ?>
                        <a href="?per_page=<?php echo (int)$pagination['per_page']; ?>" class="btn btn-outline-secondary">Reset</a>
                    <?php } ?>
                </div>
            </div>

            <div class="col-12 col-md-auto d-flex align-items-center gap-3">
                <div class="btn-group btn-group-sm" role="group" id="txFilterGroup">
                    <a href="<?php echo htmlspecialchars(build_pagination_url(['flow' => 'all', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($flow === 'all') ? 'active' : ''; ?>">
                        All (<?php echo $total_count; ?>)
                    </a>
                    <a href="<?php echo htmlspecialchars(build_pagination_url(['flow' => 'inflow', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($flow === 'inflow') ? 'active' : ''; ?>">
                        Inflows (<?php echo $inflow_count; ?>)
                    </a>
                    <a href="<?php echo htmlspecialchars(build_pagination_url(['flow' => 'outflow', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($flow === 'outflow') ? 'active' : ''; ?>">
                        Outflows (<?php echo $outflow_count; ?>)
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($transactions)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center shadow-sm">
        <div class="text-muted mb-3">
            <i class="bi bi-clock-history fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Transactions Recorded</h5>
        <p class="text-muted small mb-4">There are currently no recorded stock adjustments or movements.</p>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="add-transaction.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Record First Transaction
            </a>
        <?php } ?>
    </div>
<?php } else { ?>
    <!-- Transactions Table -->
    <div class="pos-card overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="txTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Raw Material</th>
                        <th style="width: 180px;">Transaction Type</th>
                        <th class="text-end" style="width: 160px;">Quantity Moved</th>
                        <th style="width: 200px;">Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx) {
                        $is_inflow = ($tx['type'] === 'purchase' || $tx['type'] === 'adjustment_in');
                        $flow_tag = $is_inflow ? 'inflow' : 'outflow';

                        $badge_class = match($tx['type']) {
                            'purchase' => 'bg-success-subtle text-success border-success-subtle',
                            'adjustment_in' => 'bg-info-subtle text-info border-info-subtle',
                            'consumption' => 'bg-secondary-subtle text-secondary border',
                            'adjustment_out' => 'bg-warning-subtle text-warning border-warning-subtle',
                            default => 'bg-light text-muted border',
                        };

                        $type_icon = match($tx['type']) {
                            'purchase' => 'bi-box-arrow-in-down',
                            'adjustment_in' => 'bi-plus-circle',
                            'consumption' => 'bi-fire',
                            'adjustment_out' => 'bi-dash-circle',
                            default => 'bi-circle',
                        };

                        $type_label = match($tx['type']) {
                            'purchase' => 'Purchase (+)',
                            'adjustment_in' => 'Adjustment In (+)',
                            'consumption' => 'Consumption (&minus;)',
                            'adjustment_out' => 'Adjustment Out (&minus;)',
                            default => htmlspecialchars($tx['type'], ENT_QUOTES, 'UTF-8'),
                        };
                    ?>
                        <tr class="tx-row" 
                            data-name="<?php echo htmlspecialchars(mb_strtolower($tx['material_name']), ENT_QUOTES, 'UTF-8'); ?>"
                            data-flow="<?php echo $flow_tag; ?>">
                            <td class="text-muted fw-semibold">#<?php echo $tx["id"]; ?></td>
                            <td>
                                <div class="fw-semibold text-dark d-flex align-items-center">
                                    <i class="bi bi-box-seam text-secondary me-2"></i>
                                    <span><?php echo htmlspecialchars($tx["material_name"], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?> border px-2 py-1">
                                    <i class="bi <?php echo $type_icon; ?> me-1"></i><?php echo $type_label; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold fs-6 <?php echo $is_inflow ? 'text-success' : 'text-dark'; ?>">
                                    <?php echo $is_inflow ? '+' : '&minus;'; ?> <?php echo htmlspecialchars($tx["quantity"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="text-muted small"><?php echo htmlspecialchars($tx["unit"], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="text-muted small">
                                <i class="bi bi-clock me-1"></i><?php echo formatDateTime($tx["created_at"]); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination_bar($pagination); ?>
    </div>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>