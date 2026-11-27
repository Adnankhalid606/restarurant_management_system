<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once "../includes/pagination.php";

requireRole(["admin"]);

$pg = get_pagination_params(25, [25, 50, 100]);
$q = trim($_GET["q"] ?? "");
$head_filter = trim($_GET["head"] ?? "");

// 1. Summary KPIs across all expenses
$current_month = date('Y-m');
$kpi_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS total_expenses, COALESCE(SUM(amount), 0) AS total_amount, COALESCE(SUM(CASE WHEN DATE_FORMAT(expense_date, '%Y-%m') = ? THEN amount ELSE 0 END), 0) AS current_month_total FROM expenses");
mysqli_stmt_bind_param($kpi_stmt, "s", $current_month);
mysqli_stmt_execute($kpi_stmt);
$kpi_row = mysqli_fetch_assoc(mysqli_stmt_get_result($kpi_stmt));
mysqli_stmt_close($kpi_stmt);

$total_expenses = (int) ($kpi_row['total_expenses'] ?? 0);
$total_amount = (float) ($kpi_row['total_amount'] ?? 0);
$current_month_total = (float) ($kpi_row['current_month_total'] ?? 0);

// Distinct expense heads for dropdown
$heads_count = [];
$heads_res = mysqli_query($conn, "SELECT expense_head, COUNT(*) AS cnt FROM expenses WHERE expense_head IS NOT NULL AND TRIM(expense_head) <> '' GROUP BY expense_head ORDER BY expense_head ASC");
if ($heads_res) {
    while ($hr = mysqli_fetch_assoc($heads_res)) {
        $heads_count[$hr['expense_head']] = (int) $hr['cnt'];
    }
}

// 2. Filtered WHERE clause
$where_clauses = [];
$bind_params = [];
$bind_types = "";

if ($q !== "") {
    $where_clauses[] = "(expense_head LIKE ? OR description LIKE ? OR CAST(amount AS CHAR) LIKE ?)";
    $like_q = "%" . $q . "%";
    $bind_params[] = $like_q;
    $bind_params[] = $like_q;
    $bind_params[] = $like_q;
    $bind_types .= "sss";
}

if ($head_filter !== "") {
    $where_clauses[] = "expense_head = ?";
    $bind_params[] = $head_filter;
    $bind_types .= "s";
}

$where_sql = !empty($where_clauses) ? ("WHERE " . implode(" AND ", $where_clauses)) : "";

// 3. Filtered COUNT query
$count_sql = "SELECT COUNT(*) AS total FROM expenses $where_sql";
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
$sql = "SELECT *
        FROM expenses
        $where_sql
        ORDER BY expense_date DESC, id DESC
        LIMIT ? OFFSET ?";
$d_stmt = mysqli_prepare($conn, $sql);
$data_types = $bind_types . "ii";
$data_params = array_merge($bind_params, [$limit, $offset]);
mysqli_stmt_bind_param($d_stmt, $data_types, ...$data_params);
mysqli_stmt_execute($d_stmt);
$result = mysqli_stmt_get_result($d_stmt);

$expenses = [];
while ($expense = mysqli_fetch_assoc($result)) {
    $expenses[] = $expense;
}
mysqli_stmt_close($d_stmt);

$page_title = "Expenses Register";
$active_menu = "expenses";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Operating Expenses</h2>
        <p class="page-header-subtitle">General operational overheads, utility payments, maintenance &amp; recurring outlays</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Add Expense</span>
        </a>
    </div>
</div>

<!-- Expense Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Recorded Entries</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_expenses; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-receipt fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Outlay</span>
                <span class="fs-4 fw-bold text-dark font-monospace">Rs. <?php echo number_format($total_amount, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">This Month (<?php echo date('M Y'); ?>)</span>
                <span class="fs-4 fw-bold text-primary font-monospace">Rs. <?php echo number_format($current_month_total, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-calendar-month fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Active Heads</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo count($heads_count); ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-tags fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Controls Bar -->
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
                        id="expenseSearch" 
                        value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                        placeholder="Search by expense head, particulars, or amount..."
                    >
                    <button type="submit" class="btn btn-outline-primary">Search</button>
                    <?php if ($q !== "" || $head_filter !== "") { ?>
                        <a href="?per_page=<?php echo (int)$pagination['per_page']; ?>" class="btn btn-outline-secondary">Reset</a>
                    <?php } ?>
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                <select name="head" id="headFilter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                    <option value="">All Expense Heads</option>
                    <?php foreach (array_keys($heads_count) as $h) { ?>
                        <option value="<?php echo htmlspecialchars($h, ENT_QUOTES, 'UTF-8'); ?>" <?php if ($head_filter === $h) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($h, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Expenses Directory Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-journal-text text-primary me-2"></i>Operating Expense Register
        </span>
        <span class="badge bg-light text-dark border">
            Reconciles in P&amp;L Statements
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if (empty($expenses)) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-wallet2 fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Expenses Found</h5>
                <p class="text-muted small mb-3">
                    <?php echo ($q !== "" || $head_filter !== "") ? "Try adjusting your search keywords or category filter." : "Log utility payments, kitchen fuel, maintenance, packaging, or petty cash expenses to monitor operational overheads."; ?>
                </p>
                <?php if ($q === "" && $head_filter === "") { ?>
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> Record First Expense
                    </a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="expensesTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Voucher #</th>
                            <th style="width: 130px;">Expense Date</th>
                            <th>Expense Head / Category</th>
                            <th>Description / Particulars</th>
                            <th class="text-end" style="width: 160px;">Amount</th>
                            <th class="text-end pe-3" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp) { 
                            $amt_display = (float)($exp["amount"] ?? 0);
                            $head_val = trim($exp["expense_head"] ?? "General");
                        ?>
                            <tr class="expense-table-row" data-head="<?php echo htmlspecialchars($head_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #EXP-<?php echo str_pad($exp["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold small d-block">
                                        <?php echo formatDate($exp["expense_date"], '&mdash;'); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="expense-avatar">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                        <div>
                                            <span class="expense-head-badge">
                                                <?php echo htmlspecialchars($head_val, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty(trim($exp["description"] ?? ""))) { ?>
                                        <span class="text-dark small">
                                            <?php echo htmlspecialchars($exp["description"], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small">&mdash; No details entered &mdash;</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-dark font-monospace">
                                        Rs. <?php echo number_format($amt_display, 2); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit.php?id=<?php echo $exp["id"]; ?>" 
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                                           title="Edit Expense">
                                            <i class="bi bi-pencil"></i>
                                            <span>Edit</span>
                                        </a>

                                        <form method="POST" action="delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete Expense #EXP-<?php echo str_pad($exp["id"], 3, '0', STR_PAD_LEFT); ?> (Rs. <?php echo number_format($amt_display, 2); ?>)?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $exp["id"]; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1" title="Delete Expense">
                                                <i class="bi bi-trash"></i>
                                                <span>Delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="4" class="text-end fw-bold py-3">Total Operational Expenses:</td>
                            <td class="text-end fw-bold text-danger font-monospace fs-5 py-3">
                                Rs. <?php echo number_format($total_amount, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <?php render_pagination_bar($pagination); ?>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>