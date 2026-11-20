<?php

require_once "../includes/role.php";

requireRole(["admin"]);

$page_title = "Reports & Analytics";
$page_subtitle = "Financial, Sales & Inventory Summaries";
$active_menu = "reports";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Executive Reports Hub</h2>
        <p class="page-header-subtitle">Consolidated operational statements, revenue audits, procurement tracking &amp; inventory health</p>
    </div>
</div>

<div class="row g-4">
    <!-- 1. Sales Report -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-primary-subtle text-primary">
                            <i class="bi bi-graph-up-arrow"></i>
                        </div>
                        <span class="badge bg-light text-primary border">Revenue &amp; Orders</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Sales Revenue Report</h5>
                    <p class="text-muted small mb-4">
                        Detailed audit of completed guest orders, dine-in vs. takeaway revenue, guest billing, and payment method settlements.
                    </p>
                </div>
                <div>
                    <a href="sales.php" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-file-earmark-bar-graph"></i>
                        <span>Open Sales Report</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Purchase Report -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-success-subtle text-success">
                            <i class="bi bi-cart-check"></i>
                        </div>
                        <span class="badge bg-light text-success border">Procurement</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Purchases Report</h5>
                    <p class="text-muted small mb-4">
                        Procurement history, supplier invoices, material restocking costs, and settlement status across vendor partners.
                    </p>
                </div>
                <div>
                    <a href="purchases.php" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Open Purchases Report</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Expense Report -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-danger-subtle text-danger">
                            <i class="bi bi-credit-card"></i>
                        </div>
                        <span class="badge bg-light text-danger border">Operating Costs</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Operating Expenses Report</h5>
                    <p class="text-muted small mb-4">
                        Operational overhead tracking including electricity, gas, facility rent, maintenance, and miscellaneous costs.
                    </p>
                </div>
                <div>
                    <a href="expenses.php" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-journal-text"></i>
                        <span>Open Expenses Report</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Inventory Report -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-info-subtle text-info">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <span class="badge bg-light text-secondary border">Stock Audit</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Inventory Stock Audit</h5>
                    <p class="text-muted small mb-4">
                        Current raw material reserves, unit measurements, reorder thresholds, and low-stock replenishment alerts.
                    </p>
                </div>
                <div>
                    <a href="inventory.php" class="btn btn-outline-primary btn-sm w-100 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-clipboard-data"></i>
                        <span>Open Inventory Report</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 5. Consolidated Operating Statement -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-secondary text-white">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </div>
                        <span class="badge bg-secondary text-white">Legacy Operating P&amp;L</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Operating Summary (Cash COGS)</h5>
                    <p class="text-muted small mb-4">
                        Original financial statement reconciling completed sales against raw material purchases, operating expenses &amp; paid salaries.
                    </p>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top">
                    <span class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Purchases as COGS model
                    </span>
                    <a href="profit-loss.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                        <i class="bi bi-arrow-right"></i>
                        <span>View Statement</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Operational Cash Flow Summary -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub border-success">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-success text-white">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <span class="badge bg-success text-white">Cash Basis</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Operational Cash Flow Summary</h5>
                    <p class="text-muted small mb-4">
                        Audited liquidity statement tracking verified cash inflows from settled guest bills against settled supplier disbursements, paid payroll &amp; expenses.
                    </p>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top">
                    <span class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Actual settlements only
                    </span>
                    <a href="cash-summary.php" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1">
                        <i class="bi bi-wallet2"></i>
                        <span>View Cash Summary</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 7. Accrual Profit & Loss Statement -->
    <div class="col-12 col-md-6 col-xl-4">
        <div class="pos-card h-100 report-card-hub border-primary">
            <div class="pos-card-body p-4 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div class="report-icon-box bg-primary text-white">
                            <i class="bi bi-file-earmark-ruled"></i>
                        </div>
                        <span class="badge bg-primary text-white">Accrual Financial P&amp;L</span>
                    </div>
                    <h5 class="fw-bold text-dark mb-2">Profit &amp; Loss (Accrual)</h5>
                    <p class="text-muted small mb-4">
                        Multi-step financial statement matching earned sales revenue against recipe-derived inventory consumption (COGS), inventory adjustments, and accrued payroll.
                    </p>
                </div>
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pt-3 border-top">
                    <span class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Moving Average COGS model
                    </span>
                    <a href="profit-loss-accrual.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                        <i class="bi bi-file-earmark-ruled"></i>
                        <span>View Accrual P&amp;L</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>