<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once "../includes/pagination.php";

requireRole(["admin"]);

$pg = get_pagination_params(25, [25, 50, 100]);
$q = trim($_GET["q"] ?? "");
$status_filter = strtolower(trim($_GET["status"] ?? ""));
if (!in_array($status_filter, ["", "paid", "partial", "unpaid"], true)) {
    $status_filter = "";
}

// 1. Summary KPIs across all purchases
$kpi_sql = "SELECT
                COUNT(*) AS total_purchases,
                COALESCE(SUM(total_amount), 0) AS total_spend,
                SUM(CASE WHEN LOWER(TRIM(payment_status)) = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                SUM(CASE WHEN LOWER(TRIM(payment_status)) <> 'paid' THEN 1 ELSE 0 END) AS pending_count
            FROM purchases";
$kpi_row = mysqli_fetch_assoc(mysqli_query($conn, $kpi_sql));
$total_purchases = (int) ($kpi_row['total_purchases'] ?? 0);
$total_spend = (float) ($kpi_row['total_spend'] ?? 0);
$paid_count = (int) ($kpi_row['paid_count'] ?? 0);
$pending_count = (int) ($kpi_row['pending_count'] ?? 0);

// 2. Filtered WHERE clause
$where_clauses = [];
$bind_params = [];
$bind_types = "";

if ($q !== "") {
    $where_clauses[] = "(COALESCE(suppliers.name, '') LIKE ? OR CAST(purchases.id AS CHAR) LIKE ? OR CAST(purchases.total_amount AS CHAR) LIKE ?)";
    $like_q = "%" . $q . "%";
    $bind_params[] = $like_q;
    $bind_params[] = $like_q;
    $bind_params[] = $like_q;
    $bind_types .= "sss";
}

if ($status_filter !== "") {
    $where_clauses[] = "LOWER(TRIM(purchases.payment_status)) = ?";
    $bind_params[] = $status_filter;
    $bind_types .= "s";
}

$where_sql = !empty($where_clauses) ? ("WHERE " . implode(" AND ", $where_clauses)) : "";

// 3. Filtered COUNT query
$count_sql = "SELECT COUNT(*) AS total
              FROM purchases
              LEFT JOIN suppliers ON purchases.supplier_id = suppliers.id
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
            purchases.id,
            suppliers.name AS supplier_name,
            purchases.total_amount,
            purchases.payment_status,
            purchases.purchase_date,
            purchases.created_at
        FROM purchases
        LEFT JOIN suppliers
            ON purchases.supplier_id = suppliers.id
        $where_sql
        ORDER BY purchases.id DESC
        LIMIT ? OFFSET ?";

$d_stmt = mysqli_prepare($conn, $sql);
$data_types = $bind_types . "ii";
$data_params = array_merge($bind_params, [$limit, $offset]);
mysqli_stmt_bind_param($d_stmt, $data_types, ...$data_params);
mysqli_stmt_execute($d_stmt);
$result = mysqli_stmt_get_result($d_stmt);

$purchases = [];
while ($purchase = mysqli_fetch_assoc($result)) {
    $purchases[] = $purchase;
}
mysqli_stmt_close($d_stmt);

$page_title = "Purchases Directory";
$active_menu = "purchases";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Procurement &amp; Purchases</h2>
        <p class="page-header-subtitle">Raw material purchase orders, vendor invoices &amp; inventory intake records</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>New Purchase</span>
        </a>
    </div>
</div>

<!-- Purchase Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Purchases</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_purchases; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cart-check fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Procurement Spend</span>
                <span class="fs-4 fw-bold text-dark">Rs. <?php echo number_format($total_spend, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Paid Invoices</span>
                <span class="fs-4 fw-bold text-success"><?php echo $paid_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Pending / Partial</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $pending_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-clock-history fs-4 text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="pos-card mb-4">
    <div class="pos-card-body p-3">
        <form method="GET" class="row g-2 align-items-center justify-content-between">
            <input type="hidden" name="per_page" value="<?php echo (int)$pagination['per_page']; ?>">
            <input type="hidden" name="page" value="1">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        name="q"
                        class="form-control pos-form-control border-start-0" 
                        id="purchaseSearch" 
                        value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Search by supplier, reference #, or amount..."
                    >
                    <button type="submit" class="btn btn-outline-primary">Search</button>
                    <?php if ($q !== "" || $status_filter !== "") { ?>
                        <a href="?per_page=<?php echo (int)$pagination['per_page']; ?>" class="btn btn-outline-secondary">Reset</a>
                    <?php } ?>
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                <select name="status" id="statusFilter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Payment Statuses</option>
                    <option value="paid" <?php if ($status_filter === 'paid') echo 'selected'; ?>>Paid</option>
                    <option value="partial" <?php if ($status_filter === 'partial') echo 'selected'; ?>>Partial</option>
                    <option value="unpaid" <?php if ($status_filter === 'unpaid') echo 'selected'; ?>>Unpaid</option>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Purchases Directory Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-receipt text-primary me-2"></i>Purchase Orders &amp; Receipts
        </span>
        <span class="badge bg-light text-dark border">
            Auto-Updates Stock on Creation
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if (empty($purchases)) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-cart-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Purchase Orders Found</h5>
                <p class="text-muted small mb-3">
                    <?php echo ($q !== "" || $status_filter !== "") ? "Try adjusting your search keywords or payment status filter." : "Record raw material intake from suppliers to increase inventory stock and track vendor payables."; ?>
                </p>
                <?php if ($q === "" && $status_filter === "") { ?>
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Create First Purchase
                    </a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="purchasesTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">PO #</th>
                            <th>Supplier / Vendor</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center" style="width: 130px;">Payment Status</th>
                            <th class="text-end pe-3" style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $p) { 
                            $status_clean = strtolower(trim($p["payment_status"] ?? "unpaid"));
                            $amount_val = (float)($p["total_amount"] ?? 0);
                        ?>
                            <tr class="purchase-table-row" data-status="<?php echo htmlspecialchars($status_clean); ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #PO-<?php echo str_pad($p["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="supplier-avatar" style="width: 32px; height: 32px; font-size: 0.95rem;">
                                            <i class="bi bi-truck"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark purchase-supplier-name">
                                                <?php echo htmlspecialchars($p["supplier_name"] ?? "Standard Vendor"); ?>
                                            </span>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                Logged <?php echo formatDate($p["created_at"]); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small d-block">
                                        <?php echo formatDate($p["purchase_date"], '&mdash;'); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-dark font-monospace">
                                        Rs. <?php echo number_format($amount_val, 2); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($status_clean === "paid") { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Paid</span>
                                        </span>
                                    <?php } elseif ($status_clean === "partial") { ?>
                                        <span class="badge-subtle badge-status-preparing d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-hourglass-split"></i>
                                            <span>Partial</span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge-subtle badge-status-cancelled d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-exclamation-circle-fill"></i>
                                            <span>Unpaid</span>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="view.php?id=<?php echo $p["id"]; ?>" 
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                                           title="View Purchase Details">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>

                                        <?php if ($status_clean !== "paid") { ?>
                                            <a href="pay.php?id=<?php echo $p["id"]; ?>" 
                                               class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1"
                                               title="Mark Purchase as Paid">
                                                <i class="bi bi-credit-card"></i>
                                                <span>Pay</span>
                                            </a>
                                        <?php } ?>

                                        <form method="POST" action="delete.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete Purchase #PO-<?php echo str_pad($p["id"], 3, '0', STR_PAD_LEFT); ?>? Note: Purchases with recorded inventory movements cannot be deleted.');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $p["id"]; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" title="Delete Purchase">
                                                <i class="bi bi-trash"></i>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php render_pagination_bar($pagination); ?>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>