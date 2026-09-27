<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sales_sql = "
    SELECT COALESCE(SUM(COALESCE(bills.total_amount, orders.total_amount)), 0) AS total_sales
    FROM orders
    LEFT JOIN bills
        ON orders.id = bills.order_id
    WHERE DATE(orders.created_at) BETWEEN ? AND ?
    AND orders.status = 'completed'
";

$sales_stmt = mysqli_prepare($conn, $sales_sql);

mysqli_stmt_bind_param($sales_stmt, "ss", $from, $to);

mysqli_stmt_execute($sales_stmt);

$sales_result = mysqli_stmt_get_result($sales_stmt);

$sales_row = mysqli_fetch_assoc($sales_result);

$total_sales = $sales_row["total_sales"];


$purchase_sql = "
    SELECT COALESCE(SUM(total_amount), 0) AS total_purchases
    FROM purchases
    WHERE purchase_date BETWEEN ? AND ?
";

$purchase_stmt = mysqli_prepare($conn, $purchase_sql);

mysqli_stmt_bind_param($purchase_stmt, "ss", $from, $to);

mysqli_stmt_execute($purchase_stmt);

$purchase_result = mysqli_stmt_get_result($purchase_stmt);

$purchase_row = mysqli_fetch_assoc($purchase_result);

$total_purchases = $purchase_row["total_purchases"];


$expense_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";

$expense_stmt = mysqli_prepare($conn, $expense_sql);

mysqli_stmt_bind_param($expense_stmt, "ss", $from, $to);

mysqli_stmt_execute($expense_stmt);

$expense_result = mysqli_stmt_get_result($expense_stmt);

$expense_row = mysqli_fetch_assoc($expense_result);

$total_expenses = $expense_row["total_expenses"];

$salary_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_salaries
    FROM salaries
    WHERE payment_status = 'paid'
    AND salary_date BETWEEN ? AND ?
";

$salary_stmt = mysqli_prepare($conn, $salary_sql);

mysqli_stmt_bind_param(
    $salary_stmt,
    "ss",
    $from,
    $to
);

mysqli_stmt_execute($salary_stmt);

$salary_result = mysqli_stmt_get_result($salary_stmt);

$salary_row = mysqli_fetch_assoc($salary_result);

$total_salaries = $salary_row["total_salaries"];

$net_profit = $total_sales - $total_purchases - $total_expenses - $total_salaries;

$is_profit = ($net_profit >= 0);

$page_title = "Profit & Loss Statement";
$active_menu = "reports";

require_once "../includes/header.php";
?>

<!-- Print-Only Report Header -->
<div class="d-none d-print-block mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
        <div>
            <h3 class="mb-0 fw-bold">RestoBar POS &amp; Management</h3>
            <p class="text-muted small mb-0">Consolidated Profit &amp; Loss (P&amp;L) Financial Statement</p>
        </div>
        <div class="text-end">
            <div class="fw-semibold">Period: <?php echo formatDate($from); ?> to <?php echo formatDate($to); ?></div>
            <small class="text-muted">Generated on <?php echo formatDateTime('now'); ?></small>
        </div>
    </div>
</div>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Profit &amp; Loss Statement</h2>
        <p class="page-header-subtitle">Executive financial reconciliation of sales revenue, raw material purchases, operating expenses &amp; payroll</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reports Hub</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Statement</span>
        </button>
    </div>
</div>

<!-- Date Filter Form Card -->
<div class="pos-card mb-4 d-print-none">
    <div class="pos-card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-sm-4 col-md-3">
                <label for="from" class="form-label pos-form-label mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>From Date:
                </label>
                <input
                    type="date"
                    id="from"
                    name="from"
                    class="form-control pos-form-control"
                    value="<?php echo htmlspecialchars($from); ?>"
                    required>
            </div>

            <div class="col-12 col-sm-4 col-md-3">
                <label for="to" class="form-label pos-form-label mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>To Date:
                </label>
                <input
                    type="date"
                    id="to"
                    name="to"
                    class="form-control pos-form-control"
                    value="<?php echo htmlspecialchars($to); ?>"
                    required>
            </div>

            <div class="col-12 col-sm-4 col-md-auto">
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-filter"></i>
                    <span>Generate Statement</span>
                </button>
            </div>

            <div class="col-12 col-md text-md-end text-muted small">
                Statement Period: <strong><?php echo formatDate($from); ?></strong> &mdash; <strong><?php echo formatDate($to); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- High-Level Financial KPI Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Gross Sales Revenue</span>
                <span class="fs-4 fw-bold text-success font-monospace">Rs. <?php echo number_format($total_sales, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-graph-up-arrow fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Material Purchases</span>
                <span class="fs-4 fw-bold text-dark font-monospace">Rs. <?php echo number_format($total_purchases, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cart-check fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Overheads &amp; Payroll</span>
                <span class="fs-4 fw-bold text-danger font-monospace">
                    Rs. <?php echo number_format($total_expenses + $total_salaries, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Net <?php echo $is_profit ? 'Profit' : 'Loss'; ?></span>
                <span class="fs-4 fw-bold font-monospace <?php echo $is_profit ? 'text-success' : 'text-danger'; ?>">
                    Rs. <?php echo number_format($net_profit, 2); ?>
                </span>
            </div>
            <div class="badge-subtle <?php echo $is_profit ? 'badge-status-ready' : 'badge-status-cancelled'; ?> p-2 rounded">
                <i class="bi <?php echo $is_profit ? 'bi-trophy-fill text-success' : 'bi-exclamation-triangle-fill text-danger'; ?> fs-4"></i>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Master Financial Statement Card -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
        <div class="pos-card">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>Statement of Financial Performance
                </span>
                <span class="badge bg-light text-dark border font-monospace">
                    <?php echo htmlspecialchars($from); ?> &rarr; <?php echo htmlspecialchars($to); ?>
                </span>
            </div>
            <div class="pos-card-body p-0">
                <!-- 1. Operating Revenue -->
                <div class="pnl-row bg-light">
                    <div>
                        <strong class="text-dark">1. Operating Revenue</strong>
                        <div class="text-muted small">Income earned from completed customer dining and food sales</div>
                    </div>
                    <div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Credit (+)</span>
                    </div>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-receipt text-muted"></i>
                        <span>Completed Orders &amp; Billed Sales</span>
                    </div>
                    <span class="font-monospace fw-semibold text-success fs-6">
                        + Rs. <?php echo number_format($total_sales, 2); ?>
                    </span>
                </div>

                <!-- 2. Cost of Goods Sold (Purchases) -->
                <div class="pnl-row bg-light border-top">
                    <div>
                        <strong class="text-dark">2. Cost of Materials &amp; Purchases (COGS)</strong>
                        <div class="text-muted small">Direct procurement expenditure for inventory ingredients &amp; raw materials</div>
                    </div>
                    <div>
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Debit (&minus;)</span>
                    </div>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cart-check text-muted"></i>
                        <span>Raw Material Supplier Purchases</span>
                    </div>
                    <span class="font-monospace fw-semibold text-dark fs-6">
                        &minus; Rs. <?php echo number_format($total_purchases, 2); ?>
                    </span>
                </div>

                <!-- 3. Operating Overheads & Expenses -->
                <div class="pnl-row bg-light border-top">
                    <div>
                        <strong class="text-dark">3. Operational Overheads (OPEX)</strong>
                        <div class="text-muted small">Indirect expenses incurred for restaurant operations and facility upkeep</div>
                    </div>
                    <div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Debit (&minus;)</span>
                    </div>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-credit-card text-muted"></i>
                        <span>Operating Expenses (Utilities, Rent, Fuel, Maintenance)</span>
                    </div>
                    <span class="font-monospace fw-semibold text-danger fs-6">
                        &minus; Rs. <?php echo number_format($total_expenses, 2); ?>
                    </span>
                </div>

                <!-- 4. Payroll & Staff Salaries -->
                <div class="pnl-row bg-light border-top">
                    <div>
                        <strong class="text-dark">4. Staff Compensation (Payroll)</strong>
                        <div class="text-muted small">Direct labor outlays paid out to restaurant staff and employees</div>
                    </div>
                    <div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Debit (&minus;)</span>
                    </div>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people text-muted"></i>
                        <span>Settled Staff Salaries</span>
                    </div>
                    <span class="font-monospace fw-semibold text-danger fs-6">
                        &minus; Rs. <?php echo number_format($total_salaries, 2); ?>
                    </span>
                </div>

                <!-- Consolidated Result -->
                <div class="pnl-row-total">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            Net Operating <?php echo $is_profit ? 'Profit' : 'Loss'; ?>
                        </h5>
                        <div class="text-muted small">
                            Calculation formula: Revenue (Sales) &minus; Purchases &minus; Expenses &minus; Salaries
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold font-monospace <?php echo $is_profit ? 'text-success' : 'text-danger'; ?>">
                            Rs. <?php echo number_format($net_profit, 2); ?>
                        </div>
                        <div>
                            <?php if ($is_profit) { ?>
                                <span class="badge badge-subtle badge-status-ready">
                                    <i class="bi bi-check-circle-fill me-1"></i>Profitable Operating Period
                                </span>
                            <?php } else { ?>
                                <span class="badge badge-subtle badge-status-cancelled">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Operating Deficit
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pos-card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    All financial figures adhere strictly to completed transaction records in the POS database.
                </span>
                <span class="text-muted small">
                    Admin Executive Report
                </span>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>