<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once __DIR__ . "/service.php";

requireRole(["admin", "kitchen"]);

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
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
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
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

// Handle inline action trigger from view page if submitted
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_status'])) {
    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $target_status = trim($_POST['action_status']);
    $res = transitionKitchenOrderStatus($conn, $id, $target_status);
    if ($res['success']) {
        $_SESSION['flash_success'] = $res['message'];
        header("Location: view.php?id=" . $id);
        exit;
    } else {
        $flash_error = $res['message'];
    }
}

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
                <span class="badge bg-light text-secondary border"><i class="bi bi-grid-3x3-gap text-primary me-1"></i>Table <?php echo htmlspecialchars($order["table_number"] ?? "-", ENT_QUOTES, 'UTF-8'); ?></span>
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
        <?php if (!in_array($order["status"], ['completed', 'cancelled'], true)) { ?>
            <a href="update-status.php?id=<?php echo $order["id"]; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1" title="Advanced workflow options">
                <i class="bi bi-sliders"></i>
                <span class="d-none d-sm-inline">Workflow Options</span>
            </a>
        <?php } ?>
    </div>
</div>

<?php if (!empty($flash_success)) { ?>
    <div class="alert alert-success d-flex align-items-center py-2 px-3 mb-4 border-success-subtle shadow-sm">
        <i class="bi bi-check-circle-fill text-success me-2 fs-5"></i>
        <div class="small fw-medium"><?php echo htmlspecialchars($flash_success, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

<?php if (!empty($flash_error)) { ?>
    <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4 border-danger-subtle shadow-sm">
        <i class="bi bi-exclamation-triangle-fill text-danger me-2 fs-5"></i>
        <div class="small fw-medium"><?php echo htmlspecialchars($flash_error, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

<div class="row g-4">
    <!-- Left: Food Items to Prepare (Large Legibility) -->
    <div class="col-12 col-lg-8">
        <div class="pos-card mb-4">
            <div class="pos-card-header">
                <span class="pos-card-title fs-6"><i class="bi bi-fire text-danger me-2"></i>Items to Prepare</span>
                <span class="badge bg-light text-muted border">Order #<?php echo $id; ?></span>
            </div>
            <div class="pos-card-body p-3">
                <?php if (mysqli_num_rows($items) === 0) { ?>
                    <div class="text-center py-4 text-muted">
                        No food items found for this ticket.
                    </div>
                <?php } else { ?>
                    <?php while ($item = mysqli_fetch_assoc($items)) { ?>
                        <div class="kds-item-row shadow-sm">
                            <div class="d-flex align-items-center gap-3 min-w-0">
                                <span class="kds-qty-badge shadow-sm flex-shrink-0">
                                    <?php echo (int) $item["quantity"]; ?> &times;
                                </span>
                                <div class="min-w-0">
                                    <div class="kds-food-title text-break">
                                        <?php echo htmlspecialchars($item["menu_item_name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <small class="text-muted">Quantity: <?php echo (int) $item["quantity"]; ?> portion<?php echo ((int)$item["quantity"] > 1) ? 's' : ''; ?></small>
                                </div>
                            </div>
                            <span class="badge bg-light text-secondary border text-uppercase flex-shrink-0 ms-2" style="font-size: 0.75rem; letter-spacing: 0.05em;">
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
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                    <span class="text-muted small flex-shrink-0">Guest / Customer:</span>
                    <span class="fw-medium text-dark text-end text-break">
                        <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                    <span class="text-muted small flex-shrink-0">Service Type:</span>
                    <span class="fw-medium text-dark text-capitalize text-end">
                        <?php echo htmlspecialchars(str_replace('_', ' ', $order["order_type"]), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                    <span class="text-muted small flex-shrink-0">Dining Table:</span>
                    <span class="fw-medium text-dark text-end">
                        <?php echo !empty($order["table_number"]) ? "Table " . htmlspecialchars($order["table_number"], ENT_QUOTES, 'UTF-8') : "<span class=\"text-muted\">N/A</span>"; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                    <span class="text-muted small flex-shrink-0">Server / Waiter:</span>
                    <span class="text-dark text-end text-break">
                        <?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom gap-2">
                    <span class="text-muted small flex-shrink-0">Current Status:</span>
                    <span class="badge-subtle <?php echo $status_class; ?> text-capitalize flex-shrink-0">
                        <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 mb-3 gap-2">
                    <span class="text-muted small flex-shrink-0">Created At:</span>
                    <span class="text-dark small fw-medium text-end">
                        <?php echo formatDateTime($order["created_at"]); ?>
                    </span>
                </div>

                <!-- Contextual Action Buttons -->
                <?php if ($order["status"] === "pending") { ?>
                    <form method="POST" class="mb-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action_status" value="preparing">
                        <button type="submit" class="btn btn-warning w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm text-dark">
                            <i class="bi bi-fire"></i>
                            <span>Advance to Preparing</span>
                        </button>
                    </form>
                <?php } elseif ($order["status"] === "preparing") { ?>
                    <form method="POST" class="mb-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action_status" value="ready">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check-circle"></i>
                            <span>Advance to Ready</span>
                        </button>
                    </form>
                <?php } elseif ($order["status"] === "ready") { ?>
                    <form method="POST" class="mb-2">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action_status" value="completed">
                        <button type="submit" class="btn btn-success w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-all"></i>
                            <span>Complete Order &amp; Deduct Stock</span>
                        </button>
                    </form>
                <?php } elseif ($order["status"] === "completed") { ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2 px-3 mb-0 border-success-subtle">
                        <i class="bi bi-check2-circle text-success fs-5"></i>
                        <span class="small text-secondary">This order has completed the kitchen workflow.</span>
                    </div>
                <?php } elseif ($order["status"] === "cancelled") { ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2 px-3 mb-0 border-danger-subtle">
                        <i class="bi bi-x-circle text-danger fs-5"></i>
                        <span class="small text-secondary">This order was cancelled.</span>
                    </div>
                <?php } ?>

                <?php if (in_array($order["status"], ['pending', 'preparing', 'ready'], true)) { ?>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel Order #<?php echo $id; ?>? This will release the table and remove the order from the kitchen queue.');">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action_status" value="cancelled">
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100 py-1 d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-x-circle"></i>
                            <span>Cancel Order</span>
                        </button>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>