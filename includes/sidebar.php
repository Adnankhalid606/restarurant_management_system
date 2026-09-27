<?php
/**
 * Shared Application Sidebar Component
 * Respects existing session role: admin, waiter, kitchen
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION["role"] ?? "";
$user_name = $_SESSION["user_name"] ?? "Staff";

if (!isset($base_path)) {
    $base_path = file_exists('./config/database.php') ? './' : '../';
}

$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$active_menu = $active_menu ?? '';

function isItemActive($key, $script_name, $active_menu) {
    if (!empty($active_menu) && $active_menu === $key) {
        return true;
    }
    if ($key === 'dashboard') {
        return basename($script_name) === 'index.php' && strpos($script_name, '/', 1) === false;
    }
    return strpos($script_name, '/' . $key . '/') !== false;
}
?>

<aside class="app-sidebar" id="appSidebar">
    <a href="<?php echo $base_path; ?>index.php" class="sidebar-brand">
        <i class="bi bi-shop"></i>
        <span>RestoBar POS</span>
    </a>

    <div class="sidebar-nav">
        <!-- Operations Group -->
        <div class="nav-group-title">Operations</div>
        
        <a href="<?php echo $base_path; ?>index.php" 
           class="sidebar-link <?php echo isItemActive('dashboard', $script_name, $active_menu) ? 'active' : ''; ?>">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>

        <?php if ($role === "admin" || $role === "waiter") { ?>
            <a href="<?php echo $base_path; ?>orders/index.php" 
               class="sidebar-link <?php echo isItemActive('orders', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-receipt"></i>
                <span>Orders</span>
            </a>
        <?php } ?>

        <?php if ($role === "admin" || $role === "kitchen") { ?>
            <a href="<?php echo $base_path; ?>kitchen/index.php" 
               class="sidebar-link <?php echo isItemActive('kitchen', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-fire"></i>
                <span>Kitchen Display</span>
            </a>
        <?php } ?>

        <a href="<?php echo $base_path; ?>table/index.php" 
           class="sidebar-link <?php echo isItemActive('table', $script_name, $active_menu) ? 'active' : ''; ?>">
            <i class="bi bi-grid-3x3-gap"></i>
            <span>Dining Tables</span>
        </a>

        <?php if ($role === "admin" || $role === "waiter") { ?>
            <a href="<?php echo $base_path; ?>reservations/index.php" 
               class="sidebar-link <?php echo isItemActive('reservations', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Reservations</span>
            </a>
        <?php } ?>

        <!-- Management Group -->
        <div class="nav-group-title">Management</div>

        <a href="<?php echo $base_path; ?>menu/index.php" 
           class="sidebar-link <?php echo isItemActive('menu', $script_name, $active_menu) ? 'active' : ''; ?>">
            <i class="bi bi-card-list"></i>
            <span>Menu Items</span>
        </a>

        <?php if ($role === "admin" || $role === "waiter") { ?>
            <a href="<?php echo $base_path; ?>customers/index.php" 
               class="sidebar-link <?php echo isItemActive('customers', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-people"></i>
                <span>Customers</span>
            </a>
        <?php } ?>

        <?php if ($role === "admin" || $role === "kitchen") { ?>
            <a href="<?php echo $base_path; ?>inventory/materials.php" 
               class="sidebar-link <?php echo isItemActive('inventory', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-box-seam"></i>
                <span>Inventory Stock</span>
            </a>

            <a href="<?php echo $base_path; ?>recipes/index.php" 
               class="sidebar-link <?php echo isItemActive('recipes', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-book"></i>
                <span>Recipes</span>
            </a>
        <?php } ?>

        <?php if ($role === "admin") { ?>
            <a href="<?php echo $base_path; ?>inventory/transactions.php" 
               class="sidebar-link <?php echo (strpos($script_name, 'transactions.php') !== false) ? 'active' : ''; ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span>Stock Adjustments</span>
            </a>

            <a href="<?php echo $base_path; ?>suppliers/index.php" 
               class="sidebar-link <?php echo isItemActive('suppliers', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-truck"></i>
                <span>Suppliers</span>
            </a>

            <a href="<?php echo $base_path; ?>purchases/index.php" 
               class="sidebar-link <?php echo isItemActive('purchases', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-cart-check"></i>
                <span>Purchases</span>
            </a>
        <?php } ?>

        <!-- Finance & Accounting Group (Admin & Waiter for Bills) -->
        <?php if ($role === "admin" || $role === "waiter") { ?>
            <div class="nav-group-title">Finance &amp; Ledgers</div>

            <a href="<?php echo $base_path; ?>bills/index.php" 
               class="sidebar-link <?php echo isItemActive('bills', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-cash-stack"></i>
                <span>Bills &amp; Payments</span>
            </a>
        <?php } ?>

        <?php if ($role === "admin") { ?>
            <a href="<?php echo $base_path; ?>expenses/index.php" 
               class="sidebar-link <?php echo isItemActive('expenses', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-wallet2"></i>
                <span>Expenses</span>
            </a>

            <a href="<?php echo $base_path; ?>customer-ledger/index.php" 
               class="sidebar-link <?php echo isItemActive('customer-ledger', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-journal-text"></i>
                <span>Customer Ledger</span>
            </a>

            <a href="<?php echo $base_path; ?>supplier-ledger/index.php" 
               class="sidebar-link <?php echo isItemActive('supplier-ledger', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-journal-bookmark"></i>
                <span>Supplier Ledger</span>
            </a>

            <a href="<?php echo $base_path; ?>salary/index.php" 
               class="sidebar-link <?php echo isItemActive('salary', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-credit-card-2-front"></i>
                <span>Salaries</span>
            </a>
        <?php } ?>

        <!-- Reports Hub (Admin Only) -->
        <?php if ($role === "admin") { ?>
            <div class="nav-group-title">Reports</div>
            <a href="<?php echo $base_path; ?>reports/index.php" 
               class="sidebar-link <?php echo isItemActive('reports', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-bar-chart-line"></i>
                <span>Reports &amp; Analytics</span>
            </a>
        <?php } ?>

        <!-- Administration (Admin Only) -->
        <?php if ($role === "admin") { ?>
            <div class="nav-group-title">Administration</div>
            <a href="<?php echo $base_path; ?>users/index.php" 
               class="sidebar-link <?php echo isItemActive('users', $script_name, $active_menu) ? 'active' : ''; ?>">
                <i class="bi bi-shield-lock"></i>
                <span>User Accounts</span>
            </a>
        <?php } ?>
    </div>

    <!-- User Profile Footer -->
    <div class="sidebar-user">
        <div class="d-flex align-items-center">
            <i class="bi bi-person-circle fs-4 me-2 text-info"></i>
            <div>
                <div class="fw-semibold text-white text-truncate" style="max-width: 120px;">
                    <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <small class="text-secondary text-capitalize">
                    <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                </small>
            </div>
        </div>
        <a href="<?php echo $base_path; ?>auth/logout.php" class="btn btn-sm btn-outline-light border-0 p-1" title="Sign Out">
            <i class="bi bi-box-arrow-right fs-5"></i>
        </a>
    </div>
</aside>

<!-- Backdrop overlay for mobile viewport -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>
