<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);



// Get Orders with linked invoice and payment totals

$sql = "SELECT
            orders.id,
            customers.name AS customer_name,
            restaurant_tables.table_number,
            users.name AS waiter_name,
            orders.order_type,
            orders.status,
            orders.payment_status,
            orders.total_amount,
            orders.created_at,
            bills.id AS bill_id,
            bills.invoice_number,
            bills.subtotal AS bill_subtotal,
            bills.discount AS bill_discount,
            bills.tax_type AS bill_tax_type,
            bills.tax_input AS bill_tax_input,
            bills.tax AS bill_tax,
            bills.total_amount AS bill_total_amount,
            bills.payment_status AS bill_payment_status,
            COALESCE(ip.payment_count, 0) AS invoice_payment_count,
            COALESCE(ip.total_paid, 0) AS invoice_total_paid

        FROM orders

        LEFT JOIN customers
            ON orders.customer_id = customers.id

        LEFT JOIN restaurant_tables
            ON orders.table_id = restaurant_tables.id

        LEFT JOIN users
            ON orders.waiter_id = users.id

        LEFT JOIN bills
            ON bills.order_id = orders.id

        LEFT JOIN (
            SELECT bill_id, COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS total_paid
            FROM invoice_payments
            GROUP BY bill_id
        ) ip
            ON ip.bill_id = bills.id";


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

// Fetch active payment methods for the shared Collect Payment modal
$active_methods = [];
$pm_res = mysqli_query($conn, "SELECT id, name, classification FROM payment_methods WHERE status = 'active' ORDER BY classification ASC, name ASC");
if ($pm_res) {
    while ($m = mysqli_fetch_assoc($pm_res)) {
        $active_methods[] = $m;
    }
}

require_once "../includes/header.php";

?>

<?php if (isset($_SESSION["flash_success"])) { ?>
    <div class="alert alert-success border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 text-success"></i>
        <div><?php echo htmlspecialchars($_SESSION["flash_success"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["flash_success"]); ?></div>
    </div>
<?php } ?>

<?php if (isset($_SESSION["error"])) { ?>
    <div class="alert alert-danger border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger"></i>
        <div><?php echo htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["error"]); ?></div>
    </div>
<?php } ?>

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
                            <th class="text-end">Actions</th>
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
                                    $has_invoice = !empty($order["bill_id"]);
                                    $inv_num = !empty($order["invoice_number"])
                                        ? $order["invoice_number"]
                                        : ($has_invoice ? ('INV-' . str_pad((int)$order["bill_id"], 5, '0', STR_PAD_LEFT)) : '');
                                    $bill_total = round((float) ($order["bill_total_amount"] ?? 0), 2);
                                    $paid_amt = round((float) ($order["invoice_total_paid"] ?? 0), 2);
                                    $balance = max(0.0, round($bill_total - $paid_amt, 2));
                                    $is_fully_paid = $has_invoice && ($order["bill_payment_status"] === "paid" || $balance <= 0);

                                    $effective_pay_status = $order["payment_status"];
                                    if ($has_invoice && !$is_fully_paid && (int)$order["invoice_payment_count"] > 0 && $paid_amt > 0) {
                                        $effective_pay_status = "partial";
                                    } elseif ($is_fully_paid) {
                                        $effective_pay_status = "paid";
                                    }

                                    $pay_class = match($effective_pay_status) {
                                        'paid' => 'badge-status-paid',
                                        'partial' => 'badge-status-partial',
                                        default => 'badge-status-unpaid',
                                    };
                                    ?>
                                    <span class="badge-subtle <?php echo $pay_class; ?> text-capitalize">
                                        <?php echo htmlspecialchars($effective_pay_status, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-muted small">
                                        <?php echo formatDateTime($order["created_at"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center justify-content-end gap-1 text-nowrap">
                                        <?php if ($order["status"] !== "cancelled" && $has_invoice) { ?>
                                            <?php if ($is_fully_paid) { ?>
                                                <span class="badge-subtle badge-status-paid py-1 px-2 small d-inline-flex align-items-center gap-1" title="Invoice <?php echo htmlspecialchars($inv_num, ENT_QUOTES, 'UTF-8'); ?> is fully paid">
                                                    <i class="bi bi-check2-circle"></i>
                                                    <span>Paid</span>
                                                </span>
                                            <?php } else { ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-success py-1 px-2"
                                                        title="Collect Payment &middot; <?php echo htmlspecialchars($inv_num, ENT_QUOTES, 'UTF-8'); ?>"
                                                        aria-label="Collect Payment for <?php echo htmlspecialchars($inv_num, ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="openPaymentCollectionModal(<?php echo htmlspecialchars(json_encode([
                                                            'id' => (int)$order['bill_id'],
                                                            'invoice_number' => $inv_num,
                                                            'order_id' => (int)$order['id'],
                                                            'subtotal' => (float)($order['bill_subtotal'] ?? $bill_total),
                                                            'discount' => (float)($order['bill_discount'] ?? 0),
                                                            'tax_type' => $order['bill_tax_type'] ?? 'percentage',
                                                            'tax_input' => (float)($order['bill_tax_input'] ?? 0),
                                                            'tax' => (float)($order['bill_tax'] ?? 0),
                                                            'total_amount' => $bill_total,
                                                            'total_paid' => $paid_amt,
                                                            'balance' => $balance,
                                                            'payment_count' => (int)$order['invoice_payment_count']
                                                        ]), ENT_QUOTES, 'UTF-8'); ?>)">
                                                    <i class="bi bi-wallet2"></i>
                                                </button>
                                            <?php } ?>
                                        <?php } ?>
                                        <a href="view.php?id=<?php echo $order["id"]; ?>" class="btn btn-sm btn-outline-secondary py-1 px-2" title="View Order Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="edit.php?id=<?php echo $order["id"]; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Order">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($_SESSION["role"] === "admin") { ?>
                                            <form method="POST" action="delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to permanently delete Order #<?php echo $order["id"]; ?>?');">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo $order["id"]; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Order">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
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

$payment_modal_action = "../invoices/pay.php";
$payment_modal_return_to = "orders";
require_once "../includes/payment-modal.php";

require_once "../includes/footer.php";

?>