<?php


if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION["role"] ?? "staff";
$user_name = $_SESSION["user_name"] ?? "Staff";

if (!isset($base_path)) {
    $base_path = file_exists('./config/database.php') ? './' : '../';
}

$page_title = $page_title ?? 'Dashboard';

$role_badge_class = 'bg-secondary-subtle text-secondary';
if ($role === 'admin') {
    $role_badge_class = 'bg-danger-subtle text-danger border-danger-subtle';
} elseif ($role === 'waiter') {
    $role_badge_class = 'bg-primary-subtle text-primary border-primary-subtle';
} elseif ($role === 'kitchen') {
    $role_badge_class = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
}
?>

<header class="app-topbar">
    <div class="topbar-left">
        <button class="topbar-toggle" id="sidebarToggle" type="button" aria-label="Toggle Sidebar">
            <i class="bi bi-list fs-5"></i>
        </button>

        <div>
            <h1 class="topbar-title">
                <?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?>
            </h1>
            <?php if (!empty($page_subtitle)) { ?>
                <div class="topbar-breadcrumb">
                    <?php echo htmlspecialchars($page_subtitle, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="topbar-right">
        <!-- Live Shift Date Badge -->
        <span class="badge bg-light text-secondary border d-none d-md-inline-flex align-items-center gap-1 py-2 px-3">
            <i class="bi bi-calendar3"></i>
            <span><?php echo formatDate('now'); ?></span>
        </span>

        <!-- Staff Identity & Role -->
        <div class="d-flex align-items-center gap-2">
            <span class="fw-semibold text-dark d-none d-sm-inline">
                <?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="badge border <?php echo $role_badge_class; ?> text-capitalize px-2 py-1">
                <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>

        <!-- Quick Logout Action -->
        <a href="<?php echo $base_path; ?>auth/logout.php" 
           class="btn btn-sm btn-outline-secondary ms-1 d-flex align-items-center gap-1" 
           title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-lg-inline">Logout</span>
        </a>
    </div>
</header>
