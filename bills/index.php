<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT
            bills.id,
            bills.order_id,
            bills.subtotal,
            bills.discount,
            bills.tax,
            bills.total_amount,
            bills.payment_status,
            bills.paid_at,
            bills.created_at

        FROM bills

        ORDER BY bills.id DESC";

$result = mysqli_query($conn, $sql);

$bills = [];
$stats = [
    'total_count' => 0,
    'total_amount' => 0.0,
    'paid_count' => 0,
    'paid_amount' => 0.0,
    'unpaid_count' => 0,
    'unpaid_amount' => 0.0,
];

while ($b = mysqli_fetch_assoc($result)) {
    $bills[] = $b;
    $stats['total_count']++;
    $stats['total_amount'] += (float)$b['total_amount'];
    if (($b['payment_status'] ?? '') === 'paid') {
        $stats['paid_count']++;
        $stats['paid_amount'] += (float)$b['total_amount'];
    } else {
        $stats['unpaid_count']++;
        $stats['unpaid_amount'] += (float)$b['total_amount'];
    }
}

$page_title = "Bills & Payments";
$active_menu = "bills";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Bills &amp; Payments</h2>
        <p class="page-header-subtitle">Guest checks, billing records &amp; revenue settlements</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Create Bill</span>
        </a>
    </div>
</div>

<!-- Financial Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Billed</span>
                <span class="fs-5 fw-bold text-dark">Rs. <?php echo number_format($stats['total_amount'], 2); ?></span>
                <span class="text-muted small d-block mt-1"><?php echo $stats['total_count']; ?> Invoices</span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-receipt fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Collected (Paid)</span>
                <span class="fs-5 fw-bold text-success">Rs. <?php echo number_format($stats['paid_amount'], 2); ?></span>
                <span class="text-muted small d-block mt-1"><?php echo $stats['paid_count']; ?> Settled</span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Outstanding (Unpaid)</span>
                <span class="fs-5 fw-bold text-danger">Rs. <?php echo number_format($stats['unpaid_amount'], 2); ?></span>
                <span class="text-muted small d-block mt-1"><?php echo $stats['unpaid_count']; ?> Pending</span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-exclamation-circle-fill fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Settlement Rate</span>
                <?php 
                    $rate = ($stats['total_count'] > 0) ? round(($stats['paid_count'] / $stats['total_count']) * 100) : 0;
                ?>
                <span class="fs-5 fw-bold text-primary"><?php echo $rate; ?>%</span>
                <span class="text-muted small d-block mt-1">Paid / Total</span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-pie-chart-fill fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Pills Bar -->
<div class="pos-card mb-4">
    <div class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="btn-group btn-group-sm" role="group" id="billFilterGroup">
            <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                All Bills (<?php echo $stats['total_count']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="paid">
                Paid (<?php echo $stats['paid_count']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="unpaid">
                Unpaid (<?php echo $stats['unpaid_count']; ?>)
            </button>
        </div>

        <div class="small text-muted">
            <i class="bi bi-shield-check me-1"></i>Verified system ledger records
        </div>
    </div>
</div>

<?php if (empty($bills)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center">
        <div class="text-muted mb-3">
            <i class="bi bi-cash-stack fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Billing Records Found</h5>
        <p class="text-muted small mb-4">No customer invoices have been generated yet.</p>
        <a href="create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Create First Bill
        </a>
    </div>
<?php } else { ?>
    <!-- Bills Table -->
    <div class="pos-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="billsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 80px;">Bill #</th>
                        <th style="width: 100px;">Order #</th>
                        <th>Created Date</th>
                        <th class="text-end">Subtotal</th>
                        <th class="text-end">Disc. / Tax</th>
                        <th class="text-end">Total Amount</th>
                        <th style="width: 120px;">Payment Status</th>
                        <th>Settled Date</th>
                        <th style="width: 150px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bills as $bill) {
                        $is_paid = ($bill["payment_status"] === "paid");
                        $status_badge = $is_paid 
                            ? 'bg-success-subtle text-success border-success-subtle' 
                            : 'bg-warning-subtle text-warning border-warning-subtle';
                        $status_icon = $is_paid ? 'bi-check-circle-fill' : 'bi-clock-history';
                    ?>
                        <tr data-status="<?php echo htmlspecialchars($bill['payment_status'], ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="fw-bold text-dark">
                                #<?php echo $bill["id"]; ?>
                            </td>
                            <td>
                                <a href="../orders/view.php?id=<?php echo $bill["order_id"]; ?>" class="badge bg-light text-primary border text-decoration-none">
                                    <i class="bi bi-receipt me-1"></i>Order #<?php echo $bill["order_id"]; ?>
                                </a>
                            </td>
                            <td class="text-muted small">
                                <?php echo formatDateTime($bill["created_at"]); ?>
                            </td>
                            <td class="text-end text-muted">
                                Rs. <?php echo number_format($bill["subtotal"], 2); ?>
                            </td>
                            <td class="text-end text-muted small">
                                <?php if ($bill["discount"] > 0) { ?>
                                    <span class="text-success">-Rs. <?php echo number_format($bill["discount"], 2); ?></span><br>
                                <?php } ?>
                                <?php if ($bill["tax"] > 0) { ?>
                                    <span class="text-muted">+Rs. <?php echo number_format($bill["tax"], 2); ?></span>
                                <?php } ?>
                                <?php if ($bill["discount"] == 0 && $bill["tax"] == 0) { ?>
                                    <span>&ndash;</span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold text-dark fs-6">
                                    Rs. <?php echo number_format($bill["total_amount"], 2); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $status_badge; ?> border text-capitalize px-2 py-1">
                                    <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo htmlspecialchars($bill["payment_status"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="text-muted small">
                                <?php if (!empty($bill["paid_at"])) { ?>
                                    <i class="bi bi-calendar-check text-success me-1"></i><?php echo formatDateTime($bill["paid_at"]); ?>
                                <?php } else { ?>
                                    <span class="text-muted opacity-50">&ndash;</span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="view.php?id=<?php echo $bill["id"]; ?>" class="btn btn-outline-secondary btn-sm" title="View Guest Invoice">
                                        <i class="bi bi-eye"></i>
                                    </a>

                                    <?php if ($bill["payment_status"] === "unpaid") { ?>
                                        <a href="pay.php?id=<?php echo $bill["id"]; ?>" 
                                           class="btn btn-success btn-sm px-2 d-inline-flex align-items-center gap-1 shadow-sm" 
                                           title="Mark Bill as Paid"
                                           onclick="return confirm('Confirm payment collection of Rs. <?php echo number_format($bill['total_amount'], 2); ?> for Bill #<?php echo $bill['id']; ?>?');">
                                            <i class="bi bi-cash"></i>
                                            <span class="small fw-semibold">Pay</span>
                                        </a>
                                    <?php } ?>

                                    <?php if (($_SESSION['role'] ?? '') === 'admin' && $bill["payment_status"] === "unpaid") { ?>
                                        <a href="delete.php?id=<?php echo $bill["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           title="Delete Unpaid Bill"
                                           onclick="return confirm('Are you sure you want to delete unpaid Bill #<?php echo $bill['id']; ?>?');">
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
    </div>

    <!-- Client-side filter script for Bills -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('#billFilterGroup button');
        const rows = document.querySelectorAll('#billsTable tbody tr');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                rows.forEach(row => {
                    if (filter === 'all' || row.getAttribute('data-status') === filter) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>