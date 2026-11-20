<?php
/**
 * Accrual-Based Profit & Loss Financial Statement
 * Reconciles Earned Revenue, Cost of Goods Sold (Recipe Consumption),
 * Operating Overheads, and Accrued Payroll.
 */

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

// 1. REVENUE RECOGNITION (Completed orders within period)
// Accrual Revenue Principles:
// - Only recognized for completed guest orders (orders.status = 'completed').
// - For billed orders: Gross Sales = bills.subtotal, Discount = bills.discount, Billed Net = bills.subtotal - bills.discount.
// - For unbilled completed orders: Gross Sales = Net Revenue = orders.total_amount, Discount = 0.
// This strictly prevents double-counting discounts and transparently accounts for orders completed without generated invoices.
$rev_sql = "
    SELECT 
        COUNT(orders.id) AS completed_orders_count,
        COALESCE(SUM(CASE WHEN bills.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS billed_orders_count,
        COALESCE(SUM(CASE WHEN bills.id IS NULL THEN 1 ELSE 0 END), 0) AS unbilled_orders_count,
        COALESCE(SUM(CASE WHEN bills.id IS NOT NULL THEN bills.subtotal ELSE 0 END), 0) AS billed_gross_sales,
        COALESCE(SUM(CASE WHEN bills.id IS NOT NULL THEN bills.discount ELSE 0 END), 0) AS billed_discounts,
        COALESCE(SUM(CASE WHEN bills.id IS NULL THEN orders.total_amount ELSE 0 END), 0) AS unbilled_revenue
    FROM orders
    LEFT JOIN bills ON orders.id = bills.order_id
    WHERE orders.status = 'completed'
    AND DATE(orders.created_at) BETWEEN ? AND ?
";
$rev_stmt = mysqli_prepare($conn, $rev_sql);
mysqli_stmt_bind_param($rev_stmt, "ss", $from, $to);
mysqli_stmt_execute($rev_stmt);
$rev_res = mysqli_fetch_assoc(mysqli_stmt_get_result($rev_stmt));

$completed_orders_count = (int) $rev_res["completed_orders_count"];
$billed_orders_count    = (int) $rev_res["billed_orders_count"];
$unbilled_orders_count  = (int) $rev_res["unbilled_orders_count"];

$billed_gross_sales     = (float) $rev_res["billed_gross_sales"];
$billed_discounts       = (float) $rev_res["billed_discounts"];
$unbilled_revenue       = (float) $rev_res["unbilled_revenue"];

// Mathematical Aggregation
$billed_net_revenue     = round($billed_gross_sales - $billed_discounts, 2);
$gross_sales            = round($billed_gross_sales + $unbilled_revenue, 2);
$total_discounts        = round($billed_discounts, 2);
$net_revenue            = round($billed_net_revenue + $unbilled_revenue, 2);

// 2. COST OF GOODS SOLD (COGS)
// Measured by inventory consumption transactions for orders created during the period.
$cogs_sql = "
    SELECT 
        COALESCE(SUM(it.total_cost), 0) AS total_cogs,
        COUNT(it.id) AS consumption_lines_count,
        SUM(CASE WHEN it.total_cost = 0 THEN 1 ELSE 0 END) AS zero_cost_lines_count
    FROM inventory_transactions it
    WHERE it.type = 'consumption'
    AND it.reference_type = 'order'
    AND DATE(it.created_at) BETWEEN ? AND ?
";
$cogs_stmt = mysqli_prepare($conn, $cogs_sql);
mysqli_stmt_bind_param($cogs_stmt, "ss", $from, $to);
mysqli_stmt_execute($cogs_stmt);
$cogs_res = mysqli_fetch_assoc(mysqli_stmt_get_result($cogs_stmt));

$cogs = (float) $cogs_res["total_cogs"];
$consumption_lines_count = (int) $cogs_res["consumption_lines_count"];
$zero_cost_lines_count = (int) $cogs_res["zero_cost_lines_count"];

// 3. INVENTORY ADJUSTMENTS / WASTAGE
$waste_sql = "
    SELECT 
        COALESCE(SUM(total_cost), 0) AS total_wastage,
        COUNT(id) AS waste_lines_count
    FROM inventory_transactions
    WHERE type = 'adjustment'
    AND DATE(created_at) BETWEEN ? AND ?
";
$waste_stmt = mysqli_prepare($conn, $waste_sql);
mysqli_stmt_bind_param($waste_stmt, "ss", $from, $to);
mysqli_stmt_execute($waste_stmt);
$waste_res = mysqli_fetch_assoc(mysqli_stmt_get_result($waste_stmt));
$inventory_adjustments = (float) $waste_res["total_wastage"];

// Gross Profit = Net Revenue - COGS
$gross_profit = round($net_revenue - $cogs, 2);

// 4. OPERATIONAL OVERHEADS (OPEX)
$expense_sql = "
    SELECT COALESCE(SUM(amount), 0) AS total_expenses
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";
$expense_stmt = mysqli_prepare($conn, $expense_sql);
mysqli_stmt_bind_param($expense_stmt, "ss", $from, $to);
mysqli_stmt_execute($expense_stmt);
$expense_res = mysqli_fetch_assoc(mysqli_stmt_get_result($expense_stmt));
$total_expenses = (float) $expense_res["total_expenses"];

// 5. ACCRUED STAFF COMPENSATION (Labor Expense)
// In Accrual accounting, salaries incurred for the period are recognized regardless of payment_status.
$salary_sql = "
    SELECT 
        COALESCE(SUM(amount), 0) AS total_salaries,
        SUM(CASE WHEN payment_status = 'unpaid' THEN amount ELSE 0 END) AS unpaid_salaries_accrued
    FROM salaries
    WHERE salary_date BETWEEN ? AND ?
";
$salary_stmt = mysqli_prepare($conn, $salary_sql);
mysqli_stmt_bind_param($salary_stmt, "ss", $from, $to);
mysqli_stmt_execute($salary_stmt);
$salary_res = mysqli_fetch_assoc(mysqli_stmt_get_result($salary_stmt));
$total_salaries = (float) $salary_res["total_salaries"];
$unpaid_salaries_accrued = (float) $salary_res["unpaid_salaries_accrued"];

// Total Operating Expenses = OPEX + Salaries + Wastage
$total_opex = round($total_expenses + $total_salaries + $inventory_adjustments, 2);

// Net Operating Profit / Loss
$net_operating_profit = round($gross_profit - $total_opex, 2);

if ($net_operating_profit > 0) {
    $pnl_status = 'profit';
    $pnl_label = 'Operating Profit';
    $pnl_text_class = 'text-success';
    $pnl_badge_class = 'badge-status-ready';
    $pnl_icon = 'bi-trophy-fill text-success';
    $pnl_badge_icon = 'bi-check-circle-fill me-1';
    $pnl_badge_text = 'Profitable Operating Period';
} elseif ($net_operating_profit < 0) {
    $pnl_status = 'loss';
    $pnl_label = 'Operating Deficit (Loss)';
    $pnl_text_class = 'text-danger';
    $pnl_badge_class = 'badge-status-cancelled';
    $pnl_icon = 'bi-exclamation-triangle-fill text-danger';
    $pnl_badge_icon = 'bi-exclamation-triangle-fill me-1';
    $pnl_badge_text = 'Operating Loss';
} else {
    $pnl_status = 'breakeven';
    $pnl_label = 'Break-even';
    $pnl_text_class = 'text-secondary';
    $pnl_badge_class = 'badge-status-general';
    $pnl_icon = 'bi-dash-circle-fill text-secondary';
    $pnl_badge_icon = 'bi-dash-circle-fill me-1';
    $pnl_badge_text = 'Break-Even Operating Period';
}

$page_title = "Accrual Profit & Loss Statement";
$active_menu = "reports";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Profit &amp; Loss Statement (Accrual Basis)</h2>
        <p class="page-header-subtitle">Multi-step income statement matching earned revenues against recipe COGS, inventory write-offs, accrued labor &amp; overheads</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reports Hub</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print P&amp;L</span>
        </button>
    </div>
</div>

<!-- Historical Data Limitation Disclosures -->
<?php if ($zero_cost_lines_count > 0) { ?>
    <div class="alert alert-warning border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-warning"></i>
        <div>
            <strong>Unvalued Pre-Migration Inventory Consumption:</strong>
            This period contains <?php echo $zero_cost_lines_count; ?> kitchen consumption record(s) logged before Moving Weighted Average Costing (MAC) was activated. In accordance with strict accounting integrity, historical costs are not retroactively fabricated, resulting in an unvalued historical COGS component.
        </div>
    </div>
<?php } ?>

<?php if ($unbilled_orders_count > 0) { ?>
    <div class="alert alert-light border py-2 px-3 mb-4 small text-muted d-flex align-items-center gap-2">
        <i class="bi bi-info-circle text-primary fs-5 flex-shrink-0"></i>
        <div>
            <strong>Completed Orders Without Invoices:</strong>
            Includes <?php echo $unbilled_orders_count; ?> completed order(s) fulfilled in the kitchen without a formal generated bill. Revenue is recognized from verified order subtotals.
        </div>
    </div>
<?php } ?>

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
                Operating Period: <strong><?php echo formatDate($from); ?></strong> &mdash; <strong><?php echo formatDate($to); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- High-Level Financial Performance KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Net Sales Revenue</span>
                <span class="fs-4 fw-bold text-success font-monospace">Rs. <?php echo number_format($net_revenue, 2); ?></span>
                <span class="text-muted small d-block"><?php echo $completed_orders_count; ?> Completed Orders</span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-graph-up-arrow fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Cost of Goods Sold</span>
                <span class="fs-4 fw-bold text-dark font-monospace">Rs. <?php echo number_format($cogs, 2); ?></span>
                <span class="text-muted small d-block"><?php echo $consumption_lines_count; ?> Ingredient Usages</span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-egg-fried fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Gross Profit</span>
                <span class="fs-4 fw-bold text-primary font-monospace">Rs. <?php echo number_format($gross_profit, 2); ?></span>
                <span class="text-muted small d-block">Margin: <?php echo $net_revenue > 0 ? number_format(($gross_profit / $net_revenue) * 100, 1) . '%' : '0.0%'; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-pie-chart-fill fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Net <?php echo $pnl_label; ?></span>
                <span class="fs-4 fw-bold font-monospace <?php echo $pnl_text_class; ?>">
                    Rs. <?php echo number_format($net_operating_profit, 2); ?>
                </span>
                <span class="text-muted small d-block"><?php echo $pnl_badge_text; ?></span>
            </div>
            <div class="badge-subtle <?php echo $pnl_badge_class; ?> p-2 rounded">
                <i class="bi <?php echo $pnl_icon; ?> fs-4"></i>
            </div>
        </div>
    </div>
</div>

<!-- Multi-Step Income Statement Breakdown -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
        <div class="pos-card">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>Multi-Step Statement of Financial Performance
                </span>
                <span class="badge bg-light text-dark border font-monospace">
                    <?php echo htmlspecialchars($from); ?> &rarr; <?php echo htmlspecialchars($to); ?>
                </span>
            </div>
            <div class="pos-card-body p-0">
                <!-- Section 1: Revenue -->
                <div class="pnl-row bg-light">
                    <div>
                        <strong class="text-dark">1. Operating Revenue</strong>
                        <div class="text-muted small">Earned dining sales from completed guest orders</div>
                    </div>
                    <div><span class="badge bg-success-subtle text-success border border-success-subtle">Credit (+)</span></div>
                </div>
                <div class="pnl-row ps-4">
                    <span>Billed Gross Sales (<?php echo $billed_orders_count; ?> invoiced orders)</span>
                    <span class="font-monospace text-dark">+ Rs. <?php echo number_format($billed_gross_sales, 2); ?></span>
                </div>
                <div class="pnl-row ps-4">
                    <span>Less: Promotional Discounts &amp; Allowances</span>
                    <span class="font-monospace text-danger">&minus; Rs. <?php echo number_format($billed_discounts, 2); ?></span>
                </div>
                <div class="pnl-row ps-4 border-bottom text-muted">
                    <span class="ps-2">&rdquor; Net Billed Revenue</span>
                    <span class="font-monospace text-dark">Rs. <?php echo number_format($billed_net_revenue, 2); ?></span>
                </div>
                <?php if ($unbilled_orders_count > 0) { ?>
                    <div class="pnl-row ps-4">
                        <span>Unbilled Completed Order Revenue (<?php echo $unbilled_orders_count; ?> orders)</span>
                        <span class="font-monospace text-dark">+ Rs. <?php echo number_format($unbilled_revenue, 2); ?></span>
                    </div>
                <?php } ?>
                <div class="pnl-row ps-4 bg-light fw-bold border-top">
                    <span>Total Net Operating Revenue</span>
                    <span class="font-monospace text-success">+ Rs. <?php echo number_format($net_revenue, 2); ?></span>
                </div>

                <!-- Section 2: Cost of Goods Sold -->
                <div class="pnl-row bg-light border-top">
                    <div>
                        <strong class="text-dark">2. Cost of Goods Sold (COGS)</strong>
                        <div class="text-muted small">Recipe-derived raw material consumption valued at moving weighted average cost</div>
                    </div>
                    <div><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Debit (&minus;)</span></div>
                </div>
                <div class="pnl-row ps-4">
                    <span>Kitchen Recipe Ingredient Consumption</span>
                    <span class="font-monospace text-dark">&minus; Rs. <?php echo number_format($cogs, 2); ?></span>
                </div>
                <div class="pnl-row ps-4 bg-light fw-bold border-top">
                    <span>Gross Profit</span>
                    <span class="font-monospace text-primary">Rs. <?php echo number_format($gross_profit, 2); ?></span>
                </div>

                <!-- Section 3: Operating Expenses -->
                <div class="pnl-row bg-light border-top">
                    <div>
                        <strong class="text-dark">3. Operating Expenses (OPEX)</strong>
                        <div class="text-muted small">Facilities, staff compensation, and inventory adjustments</div>
                    </div>
                    <div><span class="badge bg-danger-subtle text-danger border border-danger-subtle">Debit (&minus;)</span></div>
                </div>
                <div class="pnl-row ps-4">
                    <span>Overhead Expenses (Rent, Utilities, Maintenance)</span>
                    <span class="font-monospace text-danger">&minus; Rs. <?php echo number_format($total_expenses, 2); ?></span>
                </div>
                <div class="pnl-row ps-4">
                    <span>Staff Compensation &amp; Payroll (Accrued)</span>
                    <span class="font-monospace text-danger">&minus; Rs. <?php echo number_format($total_salaries, 2); ?></span>
                </div>
                <?php if ($inventory_adjustments > 0) { ?>
                    <div class="pnl-row ps-4">
                        <span>Inventory Spoilage / Damage Adjustments</span>
                        <span class="font-monospace text-danger">&minus; Rs. <?php echo number_format($inventory_adjustments, 2); ?></span>
                    </div>
                <?php } ?>

                <!-- Section 4: Consolidated Net Income -->
                <div class="pnl-row-total">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            Net Operating <?php echo $pnl_label; ?>
                        </h5>
                        <div class="text-muted small">
                            Calculation: Net Revenue &minus; Recipe COGS &minus; Overheads &minus; Accrued Payroll
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold font-monospace <?php echo $pnl_text_class; ?>">
                            Rs. <?php echo number_format($net_operating_profit, 2); ?>
                        </div>
                        <div>
                            <span class="badge badge-subtle <?php echo $pnl_badge_class; ?>">
                                <i class="bi <?php echo $pnl_badge_icon; ?>"></i><?php echo $pnl_badge_text; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pos-card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    Complies with accrual financial accounting: matches kitchen inventory consumption against completed dining revenues.
                </span>
                <span class="text-muted small">
                    Accrual Financial Performance
                </span>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
