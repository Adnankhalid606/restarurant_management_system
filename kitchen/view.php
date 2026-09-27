<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid order ID.");
}


// Get order

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

        WHERE orders.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die("Order not found.");
}


// Get order items

$sql = "SELECT
            order_items.quantity,
            menu_items.name AS menu_item_name

        FROM order_items

        JOIN menu_items
            ON order_items.menu_item_id = menu_items.id

        WHERE order_items.order_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

$page_title = "Kitchen Ticket #" . $id;
$active_menu = "kitchen";

require_once "../includes/header.php";

$status_class = match($order["status"]) {
    'completed' => 'badge-status-completed',
    'preparing' => 'badge-status-preparing',
    'ready' => 'badge-status-ready',
    'cancelled' => 'badge-status-cancelled',
    default => 'badge-status-pending',
};

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h2 class="page-header-title">Kitchen Ticket #<?php echo $id; ?></h2>
            <span class="badge-subtle <?php echo $status_class; ?> text-capitalize">
                <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($order["order_type"] === "dine_in") { ?>
                <span class="badge bg-light text-dark border"><i class="bi bi-grid-3x3-gap text-muted me-1"></i>Table <?php echo htmlspecialchars($order["table_number"] ?? "-", ENT_QUOTES, 'UTF-8'); ?></span>
            <?php } elseif ($order["order_type"] === "pickup") { ?>
                <span class="badge bg-light text-muted border"><i class="bi bi-bag me-1"></i>Pickup</span>
            <?php } elseif ($order["order_type"] === "delivery") { ?>
                <span class="badge bg-light text-muted border"><i class="bi bi-truck me-1"></i>Delivery</span>
            <?php } ?>
        </div>
        <p class="page-header-subtitle">
            Placed on <?php echo formatDateTime($order["created_at"]); ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Kitchen Queue</span>
        </a>
        <?php if ($order["status"] !== "completed") { ?>
            <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-arrow-repeat"></i>
                <span>Update Status</span>
            </a>
        <?php } ?>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Food Items to Prepare (Large Legibility) -->
    <div class="col-12 col-lg-8">
        <div class="pos-card mb-4">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title fs-6"><i class="bi bi-fire text-danger me-2"></i>Items to Prepare</span>
                <span class="badge bg-white text-muted border">Order #<?php echo $id; ?></span>
            </div>
            <div class="pos-card-body p-3">
                <?php if (mysqli_num_rows($items) === 0) { ?>
                    <div class="text-center py-4 text-muted">
                        No food items found for this ticket.
                    </div>
                <?php } else { ?>
                    <?php while ($item = mysqli_fetch_assoc($items)) { ?>
                        <div class="kds-item-row shadow-sm">
                            <div class="d-flex align-items-center gap-3">
                                <span class="kds-qty-badge shadow-sm">
                                    <?php echo (int) $item["quantity"]; ?> &times;
                                </span>
                                <div>
                                    <div class="kds-food-title">
                                        <?php echo htmlspecialchars($item["menu_item_name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <small class="text-muted">Quantity: <?php echo (int) $item["quantity"]; ?> portion<?php echo ((int)$item["quantity"] > 1) ? 's' : ''; ?></small>
                                </div>
                            </div>
                            <span class="badge bg-light text-secondary border text-uppercase" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                                Kitchen Item
                            </span>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Right: Order Metadata & Status Action -->
    <div class="col-12 col-lg-4">
        <div class="pos-card mb-4">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-info-circle me-2 text-primary"></i>Service Details</span>
            </div>
            <div class="pos-card-body">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Guest / Customer:</span>
                    <span class="fw-medium text-dark">
                        <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Service Type:</span>
                    <span class="fw-medium text-dark text-capitalize">
                        <?php echo htmlspecialchars(str_replace('_', ' ', $order["order_type"]), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Dining Table:</span>
                    <span class="fw-medium text-dark">
                        <?php echo !empty($order["table_number"]) ? "Table " . htmlspecialchars($order["table_number"], ENT_QUOTES, 'UTF-8') : "<span class=\"text-muted\">N/A</span>"; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Server / Waiter:</span>
                    <span class="text-dark">
                        <?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Current Status:</span>
                    <span class="badge-subtle <?php echo $status_class; ?> text-capitalize">
                        <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 mb-3">
                    <span class="text-muted small">Created At:</span>
                    <span class="text-dark small fw-medium">
                        <?php echo formatDateTime($order["created_at"]); ?>
                    </span>
                </div>

                <!-- Contextual Action Button to update-status.php -->
                <?php if ($order["status"] === "pending") { ?>
                    <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-warning w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm text-dark">
                        <i class="bi bi-fire"></i>
                        <span>Advance to Preparing</span>
                    </a>
                <?php } elseif ($order["status"] === "preparing") { ?>
                    <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-check-circle"></i>
                        <span>Advance to Ready</span>
                    </a>
                <?php } elseif ($order["status"] === "ready") { ?>
                    <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-success w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-check2-all"></i>
                        <span>Complete Order &amp; Deduct Stock</span>
                    </a>
                <?php } elseif ($order["status"] === "completed") { ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-0 border-success-subtle">
                        <i class="bi bi-check2-circle text-success fs-5"></i>
                        <span class="small text-secondary">This order has completed the kitchen workflow.</span>
                    </div>
                <?php } else { ?>
                    <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-outline-secondary w-100 py-2">
                        Update Status
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>