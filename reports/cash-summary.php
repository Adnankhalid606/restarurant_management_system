<?php
/**
 * Operational Cash Flow Summary Report
 * Identifiable Operating Cash Inflows & Outflows Based on Actual Settlements
 */

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

// 1. Customer Receipts (Cash Inflow from settled customer invoices based on payment_date)
$sales_sql = "
    SELECT 
        COALESCE(SUM(amount), 0) AS total_receipts,
        COUNT(DISTINCT bill_id) AS bill_count
    FROM invoice_payments
    WHERE DATE(payment_date) BETWEEN ? AND ?
";
$sales_stmt = mysqli_prepare($conn, $sales_sql);
mysqli_stmt_bind_param($sales_stmt, "ss", $from, $to);
mysqli_stmt_execute($sales_stmt);
$sales_res = mysqli_fetch_assoc(mysqli_stmt_get_result($sales_stmt));
$customer_receipts = (float) $sales_res["total_receipts"];
$settled_bill_count = (int) $sales_res["bill_count"];

// 2. Supplier Payments (Cash Outflow based on verified purchases.paid_at)
$purch_sql = "
    SELECT 
        COALESCE(SUM(total_amount), 0) AS total_supplier_payments,
        COUNT(id) AS purchase_count
    FROM purchases
    WHERE payment_status = 'paid'
    AND paid_at IS NOT NULL
    AND DATE(paid_at) BETWEEN ? AND ?
";
$purch_stmt = mysqli_prepare($conn, $purch_sql);
mysqli_stmt_bind_param($purch_stmt, "ss", $from, $to);
mysqli_stmt_execute($purch_stmt);
$purch_res = mysqli_fetch_assoc(mysqli_stmt_get_result($purch_stmt));
$supplier_payments = (float) $purch_res["total_supplier_payments"];
$settled_purchase_count = (int) $purch_res["purchase_count"];

// Count historical purchases marked paid without a paid_at timestamp (disclosure metric)
$legacy_purch_sql = "
    SELECT COUNT(*) AS legacy_count, COALESCE(SUM(total_amount), 0) AS legacy_total
    FROM purchases
    WHERE payment_status = 'paid' AND paid_at IS NULL
";
$legacy_purch_res = mysqli_fetch_assoc(mysqli_query($conn, $legacy_purch_sql));
$legacy_purch_count = (int) $legacy_purch_res["legacy_count"];
$legacy_purch_total = (float) $legacy_purch_res["legacy_total"];

// 3. Salary Disbursements (Cash Outflow based on verified salaries.disbursed_at)
$salary_sql = "
    SELECT 
        COALESCE(SUM(amount), 0) AS total_salaries_disbursed,
        COUNT(id) AS salary_count
    FROM salaries
    WHERE payment_status = 'paid'
    AND disbursed_at IS NOT NULL
    AND DATE(disbursed_at) BETWEEN ? AND ?
";
$salary_stmt = mysqli_prepare($conn, $salary_sql);
mysqli_stmt_bind_param($salary_stmt, "ss", $from, $to);
mysqli_stmt_execute($salary_stmt);
$salary_res = mysqli_fetch_assoc(mysqli_stmt_get_result($salary_stmt));
$salary_disbursements = (float) $salary_res["total_salaries_disbursed"];
$settled_salary_count = (int) $salary_res["salary_count"];

// Count legacy salaries marked paid without a disbursed_at timestamp
$legacy_sal_sql = "
    SELECT COUNT(*) AS legacy_count, COALESCE(SUM(amount), 0) AS legacy_total
    FROM salaries
    WHERE payment_status = 'paid' AND disbursed_at IS NULL
";
$legacy_sal_res = mysqli_fetch_assoc(mysqli_query($conn, $legacy_sal_sql));
$legacy_sal_count = (int) $legacy_sal_res["legacy_count"];
$legacy_sal_total = (float) $legacy_sal_res["legacy_total"];

// 4. Operating Expenses (Immediate cash outflows based on expense_date)
$expense_sql = "
    SELECT 
        COALESCE(SUM(amount), 0) AS total_expenses,
        COUNT(id) AS expense_count
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
";
$expense_stmt = mysqli_prepare($conn, $expense_sql);
mysqli_stmt_bind_param($expense_stmt, "ss", $from, $to);
mysqli_stmt_execute($expense_stmt);
$expense_res = mysqli_fetch_assoc(mysqli_stmt_get_result($expense_stmt));
$operating_expenses = (float) $expense_res["total_expenses"];
$expense_count = (int) $expense_res["expense_count"];

// Net Identified Operating Cash Movement
$total_cash_in = $customer_receipts;
$total_cash_out = round($supplier_payments + $salary_disbursements + $operating_expenses, 2);
$net_cash_movement = round($total_cash_in - $total_cash_out, 2);

$page_title = "Operational Cash Flow Summary";
$active_menu = "reports";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Operational Cash Flow Summary</h2>
        <p class="page-header-subtitle">Audited record of cash collections from guest bills, vendor settlements, payroll disbursements &amp; overheads</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reports Hub</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Summary</span>
        </button>
    </div>
</div>

<!-- Notice on Audit Semantics -->
<div class="alert alert-info border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
    <i class="bi bi-info-circle fs-5 flex-shrink-0"></i>
    <div>
        <strong>Audit Policy Notice:</strong> This statement tracks verified cash settlements only. Customer receipts are recognized on guest bill settlement (<code>paid_at</code>). Supplier purchases and staff salaries are recognized only when an audited payment timestamp exists.
    </div>
</div>

<?php if ($legacy_purch_count > 0 || $legacy_sal_count > 0) { ?>
    <div class="alert alert-warning border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-warning"></i>
        <div>
            <strong>Historical Pre-Migration Records:</strong>
            There are <?php echo $legacy_purch_count; ?> legacy paid purchase(s) (Rs. <?php echo number_format($legacy_purch_total, 2); ?>) and <?php echo $legacy_sal_count; ?> legacy salary record(s) (Rs. <?php echo number_format($legacy_sal_total, 2); ?>) without timestamp tracking. In accordance with data integrity guidelines, historical dates are not fabricated and are excluded from date-filtered cash flow calculations.
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
                    <span>Generate Summary</span>
                </button>
            </div>

            <div class="col-12 col-md text-md-end text-muted small">
                Reporting Period: <strong><?php echo formatDate($from); ?></strong> &mdash; <strong><?php echo formatDate($to); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- High-Level Cash Flow KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Cash Inflows</span>
                <span class="fs-4 fw-bold text-success font-monospace">Rs. <?php echo number_format($total_cash_in, 2); ?></span>
                <span class="text-muted small d-block"><?php echo $settled_bill_count; ?> Settled Bill(s)</span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-arrow-down-left-circle fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Supplier Payments</span>
                <span class="fs-4 fw-bold text-dark font-monospace">Rs. <?php echo number_format($supplier_payments, 2); ?></span>
                <span class="text-muted small d-block"><?php echo $settled_purchase_count; ?> Disbursed PO(s)</span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cart-check fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Payroll &amp; Overheads</span>
                <span class="fs-4 fw-bold text-danger font-monospace">Rs. <?php echo number_format($salary_disbursements + $operating_expenses, 2); ?></span>
                <span class="text-muted small d-block"><?php echo $settled_salary_count; ?> Salaries, <?php echo $expense_count; ?> Expenses</span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Net Cash Movement</span>
                <span class="fs-4 fw-bold font-monospace <?php echo $net_cash_movement >= 0 ? 'text-success' : 'text-danger'; ?>">
                    Rs. <?php echo number_format($net_cash_movement, 2); ?>
                </span>
                <span class="text-muted small d-block"><?php echo $net_cash_movement >= 0 ? 'Surplus Movement' : 'Deficit Movement'; ?></span>
            </div>
            <div class="badge-subtle <?php echo $net_cash_movement >= 0 ? 'badge-status-ready' : 'badge-status-cancelled'; ?> p-2 rounded">
                <i class="bi <?php echo $net_cash_movement >= 0 ? 'bi-graph-up-arrow text-success' : 'bi-graph-down-arrow text-danger'; ?> fs-4"></i>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Cash Movement Statement Table -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-10">
        <div class="pos-card">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-wallet2 text-primary me-2"></i>Cash Receipts &amp; Disbursements Statement
                </span>
                <span class="badge bg-light text-dark border font-monospace">
                    <?php echo htmlspecialchars($from); ?> &rarr; <?php echo htmlspecialchars($to); ?>
                </span>
            </div>
            <div class="pos-card-body p-0">
                <!-- 1. Cash Inflows -->
                <div class="pnl-row bg-light">
                    <div>
                        <strong class="text-dark">1. Operating Cash Receipts (Inflows)</strong>
                        <div class="text-muted small">Actual collections settled by dining guests and customers</div>
                    </div>
                    <div>
                        <span class="badge bg-success-subtle text-success border border-success-subtle">Receipt (+)</span>
                    </div>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-receipt text-muted"></i>
                        <span>Settled Guest Bills (Cash/Collected)</span>
                    </div>
                    <span class="font-monospace fw-semibold text-success fs-6">
                        + Rs. <?php echo number_format($customer_receipts, 2); ?>
                    </span>
                </div>

                <!-- 2. Cash Outflows -->
                <div class="pnl-row bg-light border-top">
                    <div>
                        <strong class="text-dark">2. Operating Cash Disbursements (Outflows)</strong>
                        <div class="text-muted small">Settled disbursements for raw materials, supplier invoices, payroll &amp; overheads</div>
                    </div>
                    <div>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Payment (&minus;)</span>
                    </div>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-cart-check text-muted"></i>
                        <span>Supplier Purchase Order Payments</span>
                    </div>
                    <span class="font-monospace fw-semibold text-dark fs-6">
                        &minus; Rs. <?php echo number_format($supplier_payments, 2); ?>
                    </span>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-people text-muted"></i>
                        <span>Disbursed Staff Salaries</span>
                    </div>
                    <span class="font-monospace fw-semibold text-danger fs-6">
                        &minus; Rs. <?php echo number_format($salary_disbursements, 2); ?>
                    </span>
                </div>
                <div class="pnl-row ps-4">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-credit-card text-muted"></i>
                        <span>Operational Expenses (Utilities, Rent, Cleaning, Fuel)</span>
                    </div>
                    <span class="font-monospace fw-semibold text-danger fs-6">
                        &minus; Rs. <?php echo number_format($operating_expenses, 2); ?>
                    </span>
                </div>

                <!-- Consolidated Result -->
                <div class="pnl-row-total">
                    <div>
                        <h5 class="fw-bold text-dark mb-1">
                            Net Operating Cash Movement
                        </h5>
                        <div class="text-muted small">
                            Formula: Customer Receipts &minus; Supplier Payments &minus; Salary Payments &minus; Operating Expenses
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="fs-3 fw-bold font-monospace <?php echo $net_cash_movement >= 0 ? 'text-success' : 'text-danger'; ?>">
                            Rs. <?php echo number_format($net_cash_movement, 2); ?>
                        </div>
                        <div class="text-muted small">
                            Identified Period Cash Variance
                        </div>
                    </div>
                </div>
            </div>
            <div class="pos-card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    Tracks identifiable operating liquidity based strictly on completed payment settlements.
                </span>
                <span class="text-muted small">
                    Cash Summary Audit
                </span>
            </div>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
