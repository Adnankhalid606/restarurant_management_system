<?php

require_once "./includes/auth.php";

$role = $_SESSION["role"] ?? "";
$user_name = $_SESSION["user_name"] ?? "Staff";
$user_email = $_SESSION["user_email"] ?? "";

$page_title = "Restaurant Dashboard";
$page_subtitle = "Operational Directory & Navigation";
$active_menu = "dashboard";

require_once "./includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Welcome back, <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="page-header-subtitle">
            Role: <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-capitalize fw-semibold"><?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></span>
            &bull; RestoBar POS System
        </p>
    </div>
    <div class="d-flex align-items-center gap-2 text-muted small">
        <i class="bi bi-calendar3"></i>
        <span><?php echo date("l, F j, Y"); ?></span>
    </div>
</div>

<!-- Operational Quick Actions Bar -->
<div class="dashboard-actions-bar">
    <div class="d-flex align-items-center gap-2 me-auto">
        <span class="text-muted small fw-semibold text-uppercase" style="letter-spacing: 0.05em;">
            <i class="bi bi-lightning-charge text-primary me-1"></i>Quick Actions
        </span>
    </div>
    <div class="d-flex align-items-center flex-wrap gap-2">
        <?php if ($role === "admin") { ?>
            <a href="./orders/create.php" class="btn btn-primary btn-sm dashboard-action-btn">
                <i class="bi bi-plus-circle"></i>
                <span>New Order</span>
            </a>
            <a href="./expenses/create.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-wallet2"></i>
                <span>Add Expense</span>
            </a>
            <a href="./purchases/create.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-cart-plus"></i>
                <span>Add Purchase</span>
            </a>
            <a href="./reports/index.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-bar-chart-line"></i>
                <span>View Reports</span>
            </a>
        <?php } elseif ($role === "waiter") { ?>
            <a href="./orders/create.php" class="btn btn-primary btn-sm dashboard-action-btn">
                <i class="bi bi-plus-circle"></i>
                <span>New Order</span>
            </a>
            <a href="./reservations/create.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-calendar-plus"></i>
                <span>New Reservation</span>
            </a>
            <a href="./table/index.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-grid-3x3-gap"></i>
                <span>Dining Tables</span>
            </a>
            <a href="./bills/index.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-cash-stack"></i>
                <span>View Bills</span>
            </a>
        <?php } elseif ($role === "kitchen") { ?>
            <a href="./kitchen/index.php" class="btn btn-primary btn-sm dashboard-action-btn">
                <i class="bi bi-fire"></i>
                <span>Kitchen Display</span>
            </a>
            <a href="./inventory/materials.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-box-seam"></i>
                <span>Inventory Stock</span>
            </a>
            <a href="./recipes/index.php" class="btn btn-outline-secondary btn-sm dashboard-action-btn">
                <i class="bi bi-book"></i>
                <span>Recipes Catalog</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Role-Tailored Module Navigation -->
<?php if ($role === "admin") { ?>
    <!-- Admin View: 4 Domain Cards -->
    <div class="row g-4">
        <!-- Operations -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-play-circle me-2 text-primary"></i>Operations</span>
                    <span class="badge bg-light text-muted border">4 Modules</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./orders/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-receipt"></i><span>Orders</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./kitchen/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-fire"></i><span>Kitchen Display</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./table/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-grid-3x3-gap"></i><span>Dining Tables</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./reservations/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-calendar-check"></i><span>Reservations</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Management -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-sliders me-2 text-primary"></i>Management</span>
                    <span class="badge bg-light text-muted border">7 Modules</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./menu/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-card-list"></i><span>Menu Items</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./customers/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-people"></i><span>Customers</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./inventory/materials.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-box-seam"></i><span>Inventory Stock</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./recipes/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-book"></i><span>Recipes</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./inventory/transactions.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-arrow-left-right"></i><span>Stock Adjustments</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./suppliers/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-truck"></i><span>Suppliers</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./purchases/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-cart-check"></i><span>Purchases</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Finance & Ledgers -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-cash-coin me-2 text-primary"></i>Finance &amp; Ledgers</span>
                    <span class="badge bg-light text-muted border">5 Modules</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./bills/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-cash-stack"></i><span>Bills &amp; Payments</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./expenses/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-wallet2"></i><span>Expenses</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./customer-ledger/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-journal-text"></i><span>Customer Ledger</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./supplier-ledger/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-journal-bookmark"></i><span>Supplier Ledger</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./salary/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-credit-card-2-front"></i><span>Staff Salaries</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Reports & Administration -->
        <div class="col-12 col-md-6 col-xl-3">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-graph-up me-2 text-primary"></i>Reports &amp; Admin</span>
                    <span class="badge bg-light text-muted border">2 Modules</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./reports/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-bar-chart-line"></i><span>Reports &amp; Analytics</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./users/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-shield-lock"></i><span>User Accounts</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php } elseif ($role === "waiter") { ?>
    <!-- Waiter View: 2 Focused Cards -->
    <div class="row g-4">
        <!-- Floor & Tables -->
        <div class="col-12 col-md-6">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-grid-3x3-gap me-2 text-primary"></i>Floor &amp; Service Operations</span>
                    <span class="badge bg-light text-muted border">Active Floor</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./orders/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-receipt"></i><span>Orders &amp; POS</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./table/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-grid-3x3-gap"></i><span>Dining Tables</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./reservations/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-calendar-check"></i><span>Reservations</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Guests & Billing -->
        <div class="col-12 col-md-6">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-people me-2 text-primary"></i>Guests &amp; Billing</span>
                    <span class="badge bg-light text-muted border">Guest Care</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./customers/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-people"></i><span>Customers</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./bills/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-cash-stack"></i><span>Bills &amp; Invoices</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./menu/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-card-list"></i><span>Menu Item Reference</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

<?php } elseif ($role === "kitchen") { ?>
    <!-- Kitchen View: 2 Focused Cards -->
    <div class="row g-4">
        <!-- Live Kitchen Display -->
        <div class="col-12 col-md-6">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-fire me-2 text-danger"></i>Kitchen Operations</span>
                    <span class="badge bg-light text-muted border">Live Display</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./kitchen/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-fire text-danger"></i><span>Kitchen Display System (KDS)</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./table/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-grid-3x3-gap"></i><span>Dining Tables Reference</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Recipes & Stock Reference -->
        <div class="col-12 col-md-6">
            <div class="pos-card h-100">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-book me-2 text-primary"></i>Recipes &amp; Stock Reference</span>
                    <span class="badge bg-light text-muted border">Kitchen Reference</span>
                </div>
                <div class="pos-card-body p-2">
                    <ul class="module-nav-list">
                        <li class="module-nav-item">
                            <a href="./recipes/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-book"></i><span>Recipe Ingredients &amp; Instructions</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./inventory/materials.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-box-seam"></i><span>Raw Materials &amp; Stock</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                        <li class="module-nav-item">
                            <a href="./menu/index.php" class="module-nav-link">
                                <span class="nav-item-left"><i class="bi bi-card-list"></i><span>Menu Item Reference</span></span>
                                <span class="nav-item-right"><i class="bi bi-chevron-right"></i></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<!-- Terminal System Information Note -->
<div class="card bg-white border mt-4">
    <div class="card-body py-2 px-3 d-flex align-items-center justify-content-between flex-wrap gap-2 text-muted small">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">
                <i class="bi bi-check-circle-fill me-1"></i>System Operational
            </span>
            <span>Terminal: <strong>RestoBar Local Server</strong></span>
        </div>
        <div>
            <span>Active Session: <strong><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>)</span>
        </div>
    </div>
</div>

<?php

require_once "./includes/footer.php";

?>