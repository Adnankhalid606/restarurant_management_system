<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if (!isset($_GET["id"])) {
    die("Customer ID is required.");
}

$customer_id = (int) $_GET["id"];

// Get customer
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM customers
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    die("Customer not found.");
}

// Get customer orders
$sql = "
    SELECT
        orders.id,
        orders.created_at,
        orders.order_type,
        COALESCE(bills.total_amount, orders.total_amount) AS total_amount,
        orders.payment_status
    FROM orders
    LEFT JOIN bills
        ON orders.id = bills.order_id
    WHERE orders.customer_id = ?
    AND orders.status = 'completed'
    ORDER BY orders.created_at DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $customer_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$orders = [];

$total_billed = 0;
$total_paid = 0;
$total_outstanding = 0;

// Calculate totals
while ($row = mysqli_fetch_assoc($result)) {

    $orders[] = $row;

    $total_billed += $row["total_amount"];

    if ($row["payment_status"] === "paid") {
        $total_paid += $row["total_amount"];
    } else {
        $total_outstanding += $row["total_amount"];
    }
}

$page_title = "Customer Ledger — " . htmlspecialchars($customer["name"]);
$active_menu = "customer-ledger";

require_once "../includes/header.php";

?>

<!-- Print-Only Header -->
<div class="d-none d-print-block mb-4 pb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h2 class="fw-bold mb-1">RestoBar POS</h2>
            <div class="text-secondary small">Customer Account Statement &amp; Receivables Ledger</div>
            <div class="text-muted small mt-1">Generated: <?php echo formatDateTime('now'); ?></div>
        </div>
        <div class="text-end">
            <h4 class="mb-0 text-dark"><?php echo htmlspecialchars($customer["name"]); ?></h4>
            <div class="text-muted small">Account: #CUS-<?php echo str_pad($customer["id"], 3, '0', STR_PAD_LEFT); ?></div>
            <?php if (!empty($customer["phone"])) { ?>
                <div class="text-muted small">Phone: <?php echo htmlspecialchars($customer["phone"]); ?></div>
            <?php } ?>
            <?php if (!empty($customer["address"])) { ?>
                <div class="text-muted small">Address: <?php echo htmlspecialchars($customer["address"]); ?></div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Screen Page Header Bar -->
<div class="page-header-bar d-print-none">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="index.php" class="text-muted small text-decoration-none">
                <i class="bi bi-journal-text me-1"></i>Customer Ledger
            </a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold">Account Statement</span>
        </div>
        <h2 class="page-header-title">
            <?php echo htmlspecialchars($customer["name"]); ?>
            <span class="badge bg-light text-dark border font-monospace fs-6 fw-normal ms-2">
                #CUS-<?php echo str_pad($customer["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
        </h2>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i>
            <span>Ledger Register</span>
        </a>
        <a href="../customers/edit.php?id=<?php echo $customer["id"]; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-pencil"></i>
            <span>Edit Profile</span>
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-printer"></i>
            <span>Print Statement</span>
        </button>
    </div>
</div>

<!-- Customer Profile Summary Info Box -->
<div class="ledger-info-box mb-4">
    <div class="row align-items-center g-3">
        <div class="col-12 col-md-6">
            <div class="d-flex align-items-center gap-3">
                <div class="ledger-avatar" style="width: 48px; height: 48px; font-size: 1.5rem;">
                    <i class="bi bi-person"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($customer["name"]); ?></h5>
                    <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                        <span>
                            <i class="bi bi-telephone text-secondary me-1"></i>
                            <?php echo !empty($customer["phone"]) ? htmlspecialchars($customer["phone"]) : "No phone provided"; ?>
                        </span>
                        <span>
                            <i class="bi bi-geo-alt text-secondary me-1"></i>
                            <?php echo !empty($customer["address"]) ? htmlspecialchars($customer["address"]) : "No address recorded"; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <div>
                <span class="text-muted small d-block mb-1">Account Standing</span>
                <?php if ($total_outstanding > 0) { ?>
                    <span class="badge-subtle badge-status-cancelled p-2 rounded d-inline-flex align-items-center gap-1">
                        <i class="bi bi-exclamation-circle-fill"></i>
                        <span>Outstanding Balance Due</span>
                    </span>
                <?php } else { ?>
                    <span class="badge-subtle badge-status-ready p-2 rounded d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Account Fully Settled</span>
                    </span>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Total Billed</span>
                <span class="fs-4 fw-bold text-dark font-monospace">
                    Rs. <?php echo number_format($total_billed, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-receipt fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-paid">
            <div>
                <span class="text-muted small d-block">Total Paid</span>
                <span class="fs-4 fw-bold text-success font-monospace">
                    Rs. <?php echo number_format($total_paid, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-cash-coin fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-outstanding">
            <div>
                <span class="text-muted small d-block">Outstanding Balance</span>
                <span class="fs-4 fw-bold <?php echo $total_outstanding > 0 ? 'text-danger' : 'text-success'; ?> font-monospace">
                    Rs. <?php echo number_format($total_outstanding, 2); ?>
                </span>
            </div>
            <div class="badge-subtle <?php echo $total_outstanding > 0 ? 'badge-status-cancelled' : 'badge-status-ready'; ?> p-2 rounded">
                <i class="bi <?php echo $total_outstanding > 0 ? 'bi-exclamation-triangle text-danger' : 'bi-check2-circle text-success'; ?> fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Completed Orders</span>
                <span class="fs-4 fw-bold text-secondary font-monospace">
                    <?php echo count($orders); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-bag-check fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Ledger Card -->
<div class="pos-card">
    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title">
                <i class="bi bi-clock-history text-primary me-2"></i>Completed Order Transactions
            </span>
            <span class="badge bg-light text-dark border font-monospace">
                <?php echo count($orders); ?> Records
            </span>
        </div>
        <div class="text-muted small d-print-none">
            Sorted by most recent transaction
        </div>
    </div>

    <div class="pos-card-body p-0">
        <?php if (empty($orders)) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-journal-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Completed Orders Found</h5>
                <p class="text-muted small mb-0">
                    This customer account does not have any completed dining, takeaway, or delivery orders recorded.
                </p>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 120px;">Order #</th>
                            <th>Date &amp; Time</th>
                            <th>Order Type</th>
                            <th>Payment Status</th>
                            <th class="text-end pe-3" style="width: 160px;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $row) { 
                            $order_type_clean = ucfirst(str_replace("_", " ", $row["order_type"] ?? "dine_in"));
                            $pay_status = strtolower(trim($row["payment_status"] ?? "unpaid"));
                            $amount = (float) ($row["total_amount"] ?? 0);
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
                                    <span class="badge bg-light text-secondary border">
                                        <?php echo htmlspecialchars($order_type_clean); ?>
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
                            <td colspan="4" class="text-end fw-bold py-2">Total Billed:</td>
                            <td class="text-end pe-3 py-2 fw-bold text-dark font-monospace">
                                Rs. <?php echo number_format($total_billed, 2); ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-end fw-bold py-2 text-success">Total Paid:</td>
                            <td class="text-end pe-3 py-2 fw-bold text-success font-monospace">
                                Rs. <?php echo number_format($total_paid, 2); ?>
                            </td>
                        </tr>
                        <tr class="<?php echo $total_outstanding > 0 ? 'table-danger' : 'table-light'; ?>">
                            <td colspan="4" class="text-end fw-bold py-3 <?php echo $total_outstanding > 0 ? 'text-danger' : 'text-dark'; ?>">
                                Net Outstanding Receivable:
                            </td>
                            <td class="text-end pe-3 py-3 fw-bold <?php echo $total_outstanding > 0 ? 'text-danger' : 'text-success'; ?> font-monospace fs-5">
                                Rs. <?php echo number_format($total_outstanding, 2); ?>
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