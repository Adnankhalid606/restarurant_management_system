<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);
$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sql = "
    SELECT
        orders.id,
        orders.created_at,
        customers.name AS customer_name,
        orders.order_type,
        orders.status,
        orders.payment_status,
        COALESCE(bills.total_amount, orders.total_amount) AS total_amount
    FROM orders
    LEFT JOIN customers
        ON orders.customer_id = customers.id
    LEFT JOIN bills
        ON orders.id = bills.order_id
    WHERE DATE(orders.created_at) BETWEEN ? AND ?
    AND orders.status = 'completed'
    ORDER BY orders.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "ss", $from, $to);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_sales = 0;
$total_orders = 0;

$rows = [];

while ($row = mysqli_fetch_assoc($result)) {

    $rows[] = $row;

    $total_orders++;

    $total_sales += $row["total_amount"];
}

$page_title = "Sales Revenue Report";
$active_menu = "reports";

require_once "../includes/header.php";
?>

<!-- Print-Only Report Header -->
<div class="d-none d-print-block mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
        <div>
            <h3 class="mb-0 fw-bold">RestoBar POS &amp; Management</h3>
            <p class="text-muted small mb-0">Sales Revenue Report &bull; Completed Orders Audit</p>
        </div>
        <div class="text-end">
            <div class="fw-semibold">Period: <?php echo formatDate($from); ?> to <?php echo formatDate($to); ?></div>
            <small class="text-muted">Printed on <?php echo formatDateTime('now'); ?></small>
        </div>
    </div>
</div>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Sales Revenue Report</h2>
        <p class="page-header-subtitle">Reconciled sales, guest checks, payment settlements &amp; completed order performance</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reports Hub</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Report</span>
        </button>
    </div>
</div>

<!-- Date Filter Form Card -->
<div class="pos-card mb-4 d-print-none">
    <div class="pos-card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-sm-4 col-md-3">
                <label for="from" class="form-label pos-form-label mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>From Date:
                </label>
                <input
                    type="date"
                    id="from"
                    name="from"
                    class="form-control pos-form-control"
                    value="<?php echo htmlspecialchars($from); ?>"
                    required>
            </div>

            <div class="col-12 col-sm-4 col-md-3">
                <label for="to" class="form-label pos-form-label mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>To Date:
                </label>
                <input
                    type="date"
                    id="to"
                    name="to"
                    class="form-control pos-form-control"
                    value="<?php echo htmlspecialchars($to); ?>"
                    required>
            </div>

            <div class="col-12 col-sm-4 col-md-auto">
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-filter"></i>
                    <span>Generate Report</span>
                </button>
            </div>

            <div class="col-12 col-md text-md-end text-muted small">
                Active Period: <strong><?php echo formatDate($from); ?></strong> &mdash; <strong><?php echo formatDate($to); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Sales Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Completed Orders</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_orders; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-receipt fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Gross Sales Revenue</span>
                <span class="fs-4 fw-bold text-success font-monospace">Rs. <?php echo number_format($total_sales, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-cash-coin fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Average Ticket</span>
                <span class="fs-4 fw-bold text-primary font-monospace">
                    Rs. <?php echo $total_orders > 0 ? number_format($total_sales / $total_orders, 2) : "0.00"; ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-graph-up fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Period Days</span>
                <span class="fs-4 fw-bold text-secondary">
                    <?php 
                        $days = (strtotime($to) - strtotime($from)) / (60 * 60 * 24) + 1;
                        echo max(1, (int)$days);
                    ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-pending p-2 rounded">
                <i class="bi bi-calendar3 fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Sales Details Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-table text-primary me-2"></i>Completed Order Transactions
        </span>
        <span class="badge bg-light text-dark border">
            Status: Completed
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if ($total_orders === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-calendar-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Completed Orders in Range</h5>
                <p class="text-muted small mb-0">
                    No sales were registered between <?php echo htmlspecialchars($from); ?> and <?php echo htmlspecialchars($to); ?>. Try adjusting the date filter.
                </p>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Order #</th>
                            <th>Date &amp; Time</th>
                            <th>Customer</th>
                            <th>Order Type</th>
                            <th>Kitchen Status</th>
                            <th>Payment Status</th>
                            <th class="text-end pe-3" style="width: 160px;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) { 
                            $order_type_clean = str_replace('_', ' ', $row["order_type"] ?? "dine_in");
                            $pay_status = strtolower(trim($row["payment_status"] ?? "unpaid"));
                            $amount = (float)($row["total_amount"] ?? 0);
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #ORD-<?php echo str_pad($row["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small d-block">
                                        <?php echo formatDate($row["created_at"]); ?>
                                    </span>
                                    <span class="text-muted small" style="font-size: 0.75rem;">
                                        <?php echo formatTime($row["created_at"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($row["customer_name"] ?? "Walk-in"); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border text-capitalize">
                                        <?php echo htmlspecialchars($order_type_clean); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-subtle badge-status-completed d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Completed</span>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pay_status === "paid") { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Paid</span>
                                        </span>
                                    <?php } elseif ($pay_status === "partial") { ?>
                                        <span class="badge-subtle badge-status-preparing d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-hourglass-split"></i>
                                            <span>Partial</span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge-subtle badge-status-cancelled d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-exclamation-circle-fill"></i>
                                            <span>Unpaid</span>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                    Rs. <?php echo number_format($amount, 2); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="6" class="text-end fw-bold py-3">Total Reconciled Sales:</td>
                            <td class="text-end pe-3 py-3 fw-bold text-success font-monospace fs-5">
                                Rs. <?php echo number_format($total_sales, 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>