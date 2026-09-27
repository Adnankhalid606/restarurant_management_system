<?php

require_once "../includes/role.php";

requireRole(["admin"]);

$page_title = "Reports & Analytics";
$page_subtitle = "Financial, Sales & Inventory Summaries";
$active_menu = "reports";

require_once "../includes/header.php";

?>

<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Reports Hub</h2>
        <p class="page-header-subtitle">Select a report module to view performance and accounting records.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Sales Report -->
    <div class="col-md-6 col-xl-4">
        <div class="pos-card h-100">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Sales Report</span>
            </div>
            <div class="pos-card-body d-flex flex-column justify-content-between">
                <p class="text-muted small mb-3">Daily sales summary, order items breakdown, and completed order revenue.</p>
                <a href="sales.php" class="btn btn-outline-primary btn-sm align-self-start">
                    <i class="bi bi-eye me-1"></i> View Sales Report
                </a>
            </div>
        </div>
    </div>

    <!-- Inventory Report -->
    <div class="col-md-6 col-xl-4">
        <div class="pos-card h-100">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-box-seam me-2 text-primary"></i>Inventory Report</span>
            </div>
            <div class="pos-card-body d-flex flex-column justify-content-between">
                <p class="text-muted small mb-3">Stock level audit, threshold warnings, and material consumption records.</p>
                <a href="inventory.php" class="btn btn-outline-primary btn-sm align-self-start">
                    <i class="bi bi-eye me-1"></i> View Inventory Report
                </a>
            </div>
        </div>
    </div>

    <!-- Purchase Report -->
    <div class="col-md-6 col-xl-4">
        <div class="pos-card h-100">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-cart-check me-2 text-primary"></i>Purchase Report</span>
            </div>
            <div class="pos-card-body d-flex flex-column justify-content-between">
                <p class="text-muted small mb-3">Procurement history, supplier expenditures, and purchasing trends.</p>
                <a href="purchases.php" class="btn btn-outline-primary btn-sm align-self-start">
                    <i class="bi bi-eye me-1"></i> View Purchase Report
                </a>
            </div>
        </div>
    </div>

    <!-- Expense Report -->
    <div class="col-md-6 col-xl-4">
        <div class="pos-card h-100">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-credit-card me-2 text-primary"></i>Expense Report</span>
            </div>
            <div class="pos-card-body d-flex flex-column justify-content-between">
                <p class="text-muted small mb-3">Operational expenditures, utilities, maintenance, and miscellaneous costs.</p>
                <a href="expenses.php" class="btn btn-outline-primary btn-sm align-self-start">
                    <i class="bi bi-eye me-1"></i> View Expense Report
                </a>
            </div>
        </div>
    </div>

    <!-- Profit & Loss -->
    <div class="col-md-6 col-xl-4">
        <div class="pos-card h-100 border-primary-subtle">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title text-primary"><i class="bi bi-cash-coin me-2"></i>Profit &amp; Loss Statement</span>
            </div>
            <div class="pos-card-body d-flex flex-column justify-content-between">
                <p class="text-muted small mb-3">Consolidated P&amp;L reconciling billed sales revenue, COGS, expenses, and staff salaries.</p>
                <a href="profit-loss.php" class="btn btn-primary btn-sm align-self-start">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> View Profit &amp; Loss
                </a>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>