<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);



// Get Order ID

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid order ID.");
}



// Get Order

$sql = "SELECT
            orders.id,
            orders.waiter_id,
            customers.name AS customer_name,
            restaurant_tables.table_number,
            users.name AS waiter_name,
            orders.order_type,
            orders.status,
            orders.payment_status,
            orders.total_amount,
            orders.created_at

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



//  Check Order Ownership


if ($_SESSION["role"] === "waiter") {

    if ((int) $order["waiter_id"] !== (int) $_SESSION["user_id"]) {

        die("Access denied.");
    }
}



// Get Order Items


$sql = "SELECT
            order_items.quantity,
            order_items.unit_price,
            order_items.subtotal,
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

$page_title = "Order #" . $id;
$active_menu = "orders";

require_once "../includes/header.php";

$status_class = match($order["status"]) {
    'completed' => 'badge-status-completed',
    'preparing' => 'badge-status-preparing',
    'ready' => 'badge-status-ready',
    'cancelled' => 'badge-status-cancelled',
    default => 'badge-status-pending',
};

$pay_class = match($order["payment_status"]) {
    'paid' => 'badge-status-paid',
    'partial' => 'badge-status-partial',
    default => 'badge-status-unpaid',
};

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h2 class="page-header-title">Order #<?php echo $id; ?></h2>
            <span class="badge-subtle <?php echo $status_class; ?> text-capitalize">
                <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="badge-subtle <?php echo $pay_class; ?> text-capitalize">
                <?php echo htmlspecialchars($order["payment_status"], ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
        <p class="page-header-subtitle">
            Placed on <?php echo formatDateTime($order["created_at"]); ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Orders</span>
        </a>
        <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-pencil"></i>
            <span>Edit Order</span>
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-printer"></i>
            <span>Print Ticket</span>
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Left: Order Items Ticket -->
    <div class="col-12 col-lg-8">
        <div class="pos-card mb-4">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-receipt me-2 text-primary"></i>Ordered Items</span>
            </div>
            <div class="pos-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-pos table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center" style="width: 100px;">Qty</th>
                                <th class="text-end" style="width: 140px;">Unit Price</th>
                                <th class="text-end" style="width: 140px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($items)) { ?>
                                <tr>
                                    <td>
                                        <div class="fw-medium text-dark">
                                            <?php echo htmlspecialchars($item["menu_item_name"], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <?php echo htmlspecialchars($item["quantity"], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="text-end text-muted">
                                        Rs. <?php echo number_format($item["unit_price"], 2); ?>
                                    </td>
                                    <td class="text-end fw-semibold text-dark">
                                        Rs. <?php echo number_format($item["subtotal"], 2); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>

                <!-- Order Ticket Totals Footer -->
                <div class="p-3 border-top bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-dark fs-6">Total Amount:</span>
                        <span class="fs-4 fw-bold text-primary">Rs. <?php echo number_format($order["total_amount"], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Service & Seating Metadata -->
    <div class="col-12 col-lg-4">
        <div class="pos-card">
            <div class="pos-card-header">
                <span class="pos-card-title"><i class="bi bi-info-circle me-2 text-primary"></i>Order Overview</span>
            </div>
            <div class="pos-card-body">
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Customer:</span>
                    <span class="fw-medium text-dark">
                        <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Service Type:</span>
                    <?php if ($order["order_type"] === "dine_in") { ?>
                        <span class="badge bg-light text-dark border"><i class="bi bi-grid-3x3-gap text-muted me-1"></i>Dine In</span>
                    <?php } elseif ($order["order_type"] === "pickup") { ?>
                        <span class="badge bg-light text-muted border"><i class="bi bi-bag me-1"></i>Pickup</span>
                    <?php } elseif ($order["order_type"] === "delivery") { ?>
                        <span class="badge bg-light text-muted border"><i class="bi bi-truck me-1"></i>Delivery</span>
                    <?php } else { ?>
                        <span class="badge bg-light text-muted border text-capitalize"><?php echo htmlspecialchars($order["order_type"], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Dining Table:</span>
                    <span class="fw-medium text-dark">
                        <?php echo !empty($order["table_number"]) ? "Table " . htmlspecialchars($order["table_number"], ENT_QUOTES, 'UTF-8') : "<span class=\"text-muted\">N/A</span>"; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Assigned Waiter:</span>
                    <span class="text-dark">
                        <?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Order Status:</span>
                    <span class="badge-subtle <?php echo $status_class; ?> text-capitalize">
                        <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Payment Status:</span>
                    <span class="badge-subtle <?php echo $pay_class; ?> text-capitalize">
                        <?php echo htmlspecialchars($order["payment_status"], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted small">Placed Date:</span>
                    <span class="text-dark small fw-medium">
                        <?php echo formatDateTime($order["created_at"]); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>