<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);



// Get Orders

$sql = "SELECT
            orders.id,
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
            ON orders.waiter_id = users.id";


// Waiter Can Only See Their Own Orders

if ($_SESSION["role"] === "waiter") {

    $sql .= " WHERE orders.waiter_id = ?";

    $sql .= " ORDER BY orders.id DESC";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $_SESSION["user_id"]
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

} else {

    // Admin Can See All Orders
   

    $sql .= " ORDER BY orders.id DESC";

    $result = mysqli_query($conn, $sql);
}

$page_title = "Orders";
$active_menu = "orders";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Orders</h2>
        <p class="page-header-subtitle">
            Live dining, delivery, and pickup order tickets
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-circle"></i>
            <span>New Order</span>
        </a>
    </div>
</div>

<!-- Orders Table Card -->
<div class="pos-card">
    <div class="pos-card-header">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title"><i class="bi bi-receipt me-2 text-primary"></i>Orders Directory</span>
        </div>
        <div>
            <span class="badge bg-light text-muted border">
                <?php echo ($_SESSION["role"] === "waiter") ? "My Assigned Orders" : "All Orders"; ?>
            </span>
        </div>
    </div>
    <div class="pos-card-body p-0">
        <?php if (mysqli_num_rows($result) === 0) { ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-receipt display-5 d-block mb-3 text-secondary opacity-50"></i>
                <h5 class="fw-semibold text-dark">No orders found</h5>
                <p class="small text-muted mb-3">There are currently no orders registered in the system.</p>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Create First Order
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-pos table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Customer</th>
                            <th>Table / Service</th>
                            <th>Waiter</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Placed At</th>
                            <th class="text-end" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($order = mysqli_fetch_assoc($result)) { ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold text-dark">#<?php echo $order["id"]; ?></span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="bi bi-person text-muted"></i>
                                        <span class="fw-medium text-dark">
                                            <?php echo htmlspecialchars($order["customer_name"] ?? "Walk-in", ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($order["order_type"] === "dine_in") { ?>
                                        <span class="badge bg-light text-dark border">
                                            <i class="bi bi-grid-3x3-gap text-muted me-1"></i>Table <?php echo htmlspecialchars($order["table_number"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php } elseif ($order["order_type"] === "pickup") { ?>
                                        <span class="badge bg-light text-muted border">
                                            <i class="bi bi-bag me-1"></i>Pickup
                                        </span>
                                    <?php } elseif ($order["order_type"] === "delivery") { ?>
                                        <span class="badge bg-light text-muted border">
                                            <i class="bi bi-truck me-1"></i>Delivery
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge bg-light text-muted border text-capitalize">
                                            <?php echo htmlspecialchars($order["order_type"], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="text-secondary">
                                        <?php echo htmlspecialchars($order["waiter_name"] ?? "-", ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-bold text-dark">
                                        Rs. <?php echo number_format($order["total_amount"], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $status_class = match($order["status"]) {
                                        'completed' => 'badge-status-completed',
                                        'preparing' => 'badge-status-preparing',
                                        'ready' => 'badge-status-ready',
                                        'cancelled' => 'badge-status-cancelled',
                                        default => 'badge-status-pending',
                                    };
                                    ?>
                                    <span class="badge-subtle <?php echo $status_class; ?> text-capitalize">
                                        <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $pay_class = match($order["payment_status"]) {
                                        'paid' => 'badge-status-paid',
                                        'partial' => 'badge-status-partial',
                                        default => 'badge-status-unpaid',
                                    };
                                    ?>
                                    <span class="badge-subtle <?php echo $pay_class; ?> text-capitalize">
                                        <?php echo htmlspecialchars($order["payment_status"], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <?php echo !empty($order["created_at"]) ? date("M j, g:i A", strtotime($order["created_at"])) : "-"; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-1">
                                        <a href="view.php?id=<?php echo $order["id"]; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Order Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $order["id"]; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Order">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($_SESSION["role"] === "admin") { ?>
                                            <a href="delete.php?id=<?php echo $order["id"]; ?>" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Order" onclick="return confirm('Are you sure you want to permanently delete Order #<?php echo $order["id"]; ?>?');">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>