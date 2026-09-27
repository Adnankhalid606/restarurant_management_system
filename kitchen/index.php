<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);


// Get kitchen orders

$sql = "SELECT
            orders.id,
            orders.order_type,
            orders.status,
            orders.created_at,
            customers.name AS customer_name,
            restaurant_tables.table_number,
            users.name AS waiter_name

        FROM orders

        LEFT JOIN customers
            ON orders.customer_id = customers.id

        LEFT JOIN restaurant_tables
            ON orders.table_id = restaurant_tables.id

        LEFT JOIN users
            ON orders.waiter_id = users.id

        WHERE orders.status IN ('pending', 'preparing', 'ready')

        ORDER BY orders.id ASC";

$result = mysqli_query($conn, $sql);

$page_title = "Kitchen Display";
$active_menu = "kitchen";

require_once "../includes/header.php";

// Helper for human-readable order age
function getKitchenOrderAge($datetime_str) {
    if (empty($datetime_str)) return '-';
    $time = strtotime($datetime_str);
    $diff = max(0, time() - $time);
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } else {
        $hrs = floor($diff / 3600);
        return $hrs . ' hr' . ($hrs > 1 ? 's' : '') . ' ago';
    }
}

// Group active orders by status in memory (strictly zero SQL changes)
$active_orders = [];
$status_counts = [
    'pending' => 0,
    'preparing' => 0,
    'ready' => 0
];

while ($row = mysqli_fetch_assoc($result)) {
    $active_orders[] = $row;
    if (isset($status_counts[$row['status']])) {
        $status_counts[$row['status']]++;
    }
}
$total_active = count($active_orders);

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">
            <i class="bi bi-fire text-danger me-2"></i>Kitchen Display System (KDS)
        </h2>
        <p class="page-header-subtitle">
            Active Kitchen Queue &bull; <?php echo $total_active; ?> Tickets In Progress
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" onclick="window.location.reload()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Refresh Queue</span>
        </button>
    </div>
</div>

<?php if ($total_active === 0) { ?>
    <div class="pos-card text-center py-5">
        <i class="bi bi-check2-circle display-4 text-success opacity-75 d-block mb-3"></i>
        <h4 class="fw-bold text-dark">Kitchen Queue Clear</h4>
        <p class="text-muted small mb-0">There are no pending, preparing, or ready orders right now.</p>
    </div>
<?php } else { ?>
    <!-- 3-Stage Kitchen Kanban Pipeline -->
    <div class="row g-3">
        <!-- Stage 1: PENDING -->
        <div class="col-12 col-md-4">
            <div class="kds-column">
                <div class="kds-column-header">
                    <span class="fw-bold text-dark fs-6">
                        <i class="bi bi-clock text-warning me-1"></i>New Orders
                    </span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-semibold">
                        <?php echo $status_counts['pending']; ?> Pending
                    </span>
                </div>

                <?php
                $pending_found = false;
                foreach ($active_orders as $order) {
                    if ($order['status'] !== 'pending') continue;
                    $pending_found = true;
                    $age = getKitchenOrderAge($order['created_at']);
                ?>
                    <div class="kds-ticket">
                        <div class="kds-ticket-header bg-light">
                            <span class="fw-bold fs-6 text-dark">#<?php echo $order["id"]; ?></span>
                            <span class="kds-age-pill bg-white text-muted border">
                                <i class="bi bi-stopwatch me-1"></i><?php echo $age; ?>
                            </span>
                        </div>
                        <div class="kds-ticket-body">
                            <div class="mb-2">
                                <?php if ($order["order_type"] === "dine_in") { ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-grid-3x3-gap me-1"></i>Table <?php echo htmlspecialchars($order["table_number"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php } elseif ($order["order_type"] === "pickup") { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-bag me-1"></i>Pickup
                                    </span>
                                <?php } elseif ($order["order_type"] === "delivery") { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-truck me-1"></i>Delivery
                                    </span>
                                <?php } ?>
                            </div>

                            <div class="small text-muted mb-1">
                                <i class="bi bi-person-badge me-1"></i>Server: <strong><?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="small text-muted mb-3">
                                <i class="bi bi-person me-1"></i>Guest: <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <a href="view.php?id=<?php echo $order["id"]; ?>" class="btn btn-primary btn-sm w-100 mb-1 fw-medium">
                                <i class="bi bi-receipt me-1"></i>View Items to Prepare
                            </a>
                            <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-outline-warning btn-sm w-100 text-dark">
                                <i class="bi bi-fire me-1"></i>Start Preparing
                            </a>
                        </div>
                    </div>
                <?php } ?>

                <?php if (!$pending_found) { ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-check2-circle fs-3 d-block mb-1 text-secondary opacity-50"></i>
                        No pending tickets
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Stage 2: PREPARING -->
        <div class="col-12 col-md-4">
            <div class="kds-column">
                <div class="kds-column-header">
                    <span class="fw-bold text-dark fs-6">
                        <i class="bi bi-fire text-primary me-1"></i>In Preparation
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold">
                        <?php echo $status_counts['preparing']; ?> Cooking
                    </span>
                </div>

                <?php
                $preparing_found = false;
                foreach ($active_orders as $order) {
                    if ($order['status'] !== 'preparing') continue;
                    $preparing_found = true;
                    $age = getKitchenOrderAge($order['created_at']);
                ?>
                    <div class="kds-ticket border-primary-subtle">
                        <div class="kds-ticket-header bg-primary-subtle text-primary">
                            <span class="fw-bold fs-6">#<?php echo $order["id"]; ?></span>
                            <span class="kds-age-pill bg-white text-muted border">
                                <i class="bi bi-stopwatch me-1"></i><?php echo $age; ?>
                            </span>
                        </div>
                        <div class="kds-ticket-body">
                            <div class="mb-2">
                                <?php if ($order["order_type"] === "dine_in") { ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-grid-3x3-gap me-1"></i>Table <?php echo htmlspecialchars($order["table_number"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php } elseif ($order["order_type"] === "pickup") { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-bag me-1"></i>Pickup
                                    </span>
                                <?php } elseif ($order["order_type"] === "delivery") { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-truck me-1"></i>Delivery
                                    </span>
                                <?php } ?>
                            </div>

                            <div class="small text-muted mb-1">
                                <i class="bi bi-person-badge me-1"></i>Server: <strong><?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="small text-muted mb-3">
                                <i class="bi bi-person me-1"></i>Guest: <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <a href="view.php?id=<?php echo $order["id"]; ?>" class="btn btn-primary btn-sm w-100 mb-1 fw-medium">
                                <i class="bi bi-receipt me-1"></i>View Items to Prepare
                            </a>
                            <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-check-circle me-1"></i>Mark as Ready
                            </a>
                        </div>
                    </div>
                <?php } ?>

                <?php if (!$preparing_found) { ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-slash-circle fs-3 d-block mb-1 text-secondary opacity-50"></i>
                        No orders currently cooking
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- Stage 3: READY FOR SERVING -->
        <div class="col-12 col-md-4">
            <div class="kds-column">
                <div class="kds-column-header">
                    <span class="fw-bold text-dark fs-6">
                        <i class="bi bi-check2-all text-success me-1"></i>Ready for Serving
                    </span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold">
                        <?php echo $status_counts['ready']; ?> Plated
                    </span>
                </div>

                <?php
                $ready_found = false;
                foreach ($active_orders as $order) {
                    if ($order['status'] !== 'ready') continue;
                    $ready_found = true;
                    $age = getKitchenOrderAge($order['created_at']);
                ?>
                    <div class="kds-ticket border-success-subtle">
                        <div class="kds-ticket-header bg-success-subtle text-success">
                            <span class="fw-bold fs-6">#<?php echo $order["id"]; ?></span>
                            <span class="kds-age-pill bg-white text-muted border">
                                <i class="bi bi-stopwatch me-1"></i><?php echo $age; ?>
                            </span>
                        </div>
                        <div class="kds-ticket-body">
                            <div class="mb-2">
                                <?php if ($order["order_type"] === "dine_in") { ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        <i class="bi bi-grid-3x3-gap me-1"></i>Table <?php echo htmlspecialchars($order["table_number"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php } elseif ($order["order_type"] === "pickup") { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-bag me-1"></i>Pickup
                                    </span>
                                <?php } elseif ($order["order_type"] === "delivery") { ?>
                                    <span class="badge bg-secondary-subtle text-secondary border">
                                        <i class="bi bi-truck me-1"></i>Delivery
                                    </span>
                                <?php } ?>
                            </div>

                            <div class="small text-muted mb-1">
                                <i class="bi bi-person-badge me-1"></i>Server: <strong><?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?></strong>
                            </div>
                            <div class="small text-muted mb-3">
                                <i class="bi bi-person me-1"></i>Guest: <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <a href="view.php?id=<?php echo $order["id"]; ?>" class="btn btn-outline-secondary btn-sm w-100 mb-1">
                                <i class="bi bi-receipt me-1"></i>View Items
                            </a>
                            <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-outline-success btn-sm w-100">
                                <i class="bi bi-check-lg me-1"></i>Complete Order
                            </a>
                        </div>
                    </div>
                <?php } ?>

                <?php if (!$ready_found) { ?>
                    <div class="text-center py-4 text-muted small">
                        <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary opacity-50"></i>
                        No plated orders waiting
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>