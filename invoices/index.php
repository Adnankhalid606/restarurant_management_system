<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT
            bills.id,
            bills.order_id,
            bills.invoice_number,
            bills.subtotal,
            bills.discount,
            bills.tax_type,
            bills.tax_input,
            bills.tax,
            bills.total_amount,
            bills.payment_status,
            bills.paid_at,
            bills.created_at,
            COUNT(ip.id) AS payment_count,
            COALESCE(SUM(ip.amount), 0) AS total_paid
        FROM bills
        LEFT JOIN invoice_payments ip ON bills.id = ip.bill_id
        GROUP BY bills.id
        ORDER BY bills.id DESC";

$result = mysqli_query($conn, $sql);

$invoices = [];
$total_invoices_count = 0;
$paid_invoices_count = 0;
$partial_invoices_count = 0;
$pending_invoices_count = 0;
$total_billed_amount = 0.0;
$total_collected_amount = 0.0;
$total_unpaid_balance = 0.0;

while ($b = mysqli_fetch_assoc($result)) {
    $invoices[] = $b;
    $total_invoices_count++;
    $billed = (float) $b['total_amount'];
    $paid = (float) $b['total_paid'];
    $bal = max(0.0, round($billed - $paid, 2));

    $total_billed_amount += $billed;
    $total_collected_amount += $paid;
    $total_unpaid_balance += $bal;

    $is_fully_paid = ($b['payment_status'] === 'paid' || $bal <= 0);
    $has_partial = (!$is_fully_paid && (int)$b['payment_count'] > 0 && $paid > 0);

    if ($is_fully_paid) {
        $paid_invoices_count++;
    } elseif ($has_partial) {
        $partial_invoices_count++;
    } else {
        $pending_invoices_count++;
    }
}

// Fetch active payment methods for quick payment collection modal
$methods_res = mysqli_query($conn, "SELECT id, name, classification FROM payment_methods WHERE status = 'active' ORDER BY name ASC");
$active_methods = [];
while ($m = mysqli_fetch_assoc($methods_res)) {
    $active_methods[] = $m;
}

$page_title = "Customer Invoices";
$active_menu = "invoices";

require_once "../includes/header.php";
?>

<!-- Page Header Bar (No manual create invoice button - automatic generation) -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Customer Invoices</h2>
        <p class="page-header-subtitle">Auto-generated invoices, payment collection, discount &amp; tax adjustments</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge bg-light text-secondary border px-3 py-2 small d-none d-sm-inline-flex align-items-center gap-1">
            <i class="bi bi-magic text-primary"></i>
            <span>Auto-generated from Ready &amp; Completed orders</span>
        </span>
    </div>
</div>

<?php if (isset($_SESSION["flash_success"])) { ?>
    <div class="alert alert-success border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 text-success"></i>
        <div><?php echo htmlspecialchars($_SESSION["flash_success"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["flash_success"]); ?></div>
    </div>
<?php } ?>

<?php if (isset($_SESSION["info"])) { ?>
    <div class="alert alert-info border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 text-primary"></i>
        <div><?php echo htmlspecialchars($_SESSION["info"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["info"]); ?></div>
    </div>
<?php } ?>

<?php if (isset($_SESSION["error"])) { ?>
    <div class="alert alert-danger border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger"></i>
        <div><?php echo htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["error"]); ?></div>
    </div>
<?php } ?>

<!-- Metric Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Invoices</span>
                <span class="fs-4 fw-bold text-dark font-monospace"><?php echo $total_invoices_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-receipt-cutoff fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Payment Pending</span>
                <span class="fs-4 fw-bold text-warning font-monospace"><?php echo $pending_invoices_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-pending p-2 rounded">
                <i class="bi bi-clock-history fs-4 text-warning"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Partially Paid</span>
                <span class="fs-4 fw-bold text-info font-monospace"><?php echo $partial_invoices_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-pie-chart fs-4 text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Paid in Full</span>
                <span class="fs-4 fw-bold text-success font-monospace"><?php echo $paid_invoices_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check2-circle fs-4 text-success"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="pos-card mb-4">
    <div class="pos-card-body p-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        id="invoiceSearchInput" 
                        class="form-control pos-form-control border-start-0" 
                        placeholder="Search by invoice #, order #, or date..."
                    >
                </div>
            </div>
            <div class="col-12 col-md-7 text-md-end">
                <div class="btn-group btn-group-sm flex-wrap" role="group" id="statusFilterGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">All (<?php echo $total_invoices_count; ?>)</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="pending">Pending (<?php echo $pending_invoices_count; ?>)</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="partial">Partial (<?php echo $partial_invoices_count; ?>)</button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="paid">Paid (<?php echo $paid_invoices_count; ?>)</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invoices Data Table -->
<div class="pos-card mb-4">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-receipt text-primary me-2"></i>Invoice Register
        </span>
        <span class="badge bg-light text-primary border font-monospace">
            Showing <?php echo count($invoices); ?> Invoices
        </span>
    </div>
    <?php if (empty($invoices)) { ?>
        <div class="pos-card-body text-center py-5">
            <div class="mb-3 text-muted">
                <i class="bi bi-inbox fs-1"></i>
            </div>
            <h5 class="fw-semibold text-dark">No Invoices Generated Yet</h5>
            <p class="text-muted small mb-0">Invoices are automatically created when kitchen orders transition to Ready or Completed.</p>
        </div>
    <?php } else { ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="invoicesTable">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 140px;">Invoice #</th>
                        <th style="width: 110px;">Order #</th>
                        <th>Created Date</th>
                        <th class="text-end" style="width: 120px;">Subtotal</th>
                        <th class="text-end" style="width: 110px;">Discount</th>
                        <th class="text-end" style="width: 120px;">Tax / Surcharge</th>
                        <th class="text-end" style="width: 130px;">Total</th>
                        <th class="text-end" style="width: 120px;">Amount Paid</th>
                        <th class="text-end" style="width: 130px;">Balance Due</th>
                        <th class="text-center" style="width: 140px;">Status</th>
                        <th class="text-end pe-3" style="width: 170px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv) { 
                        $total_amt = round((float) $inv["total_amount"], 2);
                        $paid_amt = round((float) $inv["total_paid"], 2);
                        $balance = max(0.0, round($total_amt - $paid_amt, 2));
                        $is_fully_paid = ($inv["payment_status"] === "paid" || $balance <= 0);
                        $has_partial = (!$is_fully_paid && (int)$inv["payment_count"] > 0 && $paid_amt > 0);

                        $inv_num = !empty($inv["invoice_number"]) ? $inv["invoice_number"] : 'INV-' . str_pad($inv["id"], 5, '0', STR_PAD_LEFT);

                        if ($is_fully_paid) {
                            $filter_status = "paid";
                            $badge_class = "badge-status-ready text-success";
                            $status_label = "Paid in Full";
                            $status_icon = "bi-check-circle-fill";
                        } elseif ($has_partial) {
                            $filter_status = "partial";
                            $badge_class = "bg-info-subtle text-primary border border-info-subtle";
                            $status_label = "Partially Paid";
                            $status_icon = "bi-pie-chart-fill";
                        } else {
                            $filter_status = "pending";
                            $badge_class = "badge-status-pending text-warning";
                            $status_label = "Payment Pending";
                            $status_icon = "bi-clock-history";
                        }
                    ?>
                        <tr data-status="<?php echo $filter_status; ?>">
                            <td class="ps-3">
                                <a href="view.php?id=<?php echo $inv['id']; ?>" class="fw-bold text-primary font-monospace text-decoration-none">
                                    <?php echo htmlspecialchars($inv_num, ENT_QUOTES, 'UTF-8'); ?>
                                </a>
                            </td>
                            <td>
                                <a href="../orders/view.php?id=<?php echo $inv['order_id']; ?>" class="text-dark font-monospace text-decoration-none">
                                    #ORD-<?php echo $inv["order_id"]; ?>
                                </a>
                            </td>
                            <td class="text-muted small">
                                <?php echo formatDateTime($inv["created_at"]); ?>
                            </td>
                            <td class="text-end font-monospace">
                                Rs. <?php echo number_format((float)$inv["subtotal"], 2); ?>
                            </td>
                            <td class="text-end font-monospace text-danger">
                                <?php echo (float)$inv["discount"] > 0 ? '&minus; Rs. ' . number_format((float)$inv["discount"], 2) : '&mdash;'; ?>
                            </td>
                            <td class="text-end font-monospace text-muted">
                                <?php echo (float)$inv["tax"] > 0 ? '+ Rs. ' . number_format((float)$inv["tax"], 2) : '&mdash;'; ?>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                Rs. <?php echo number_format($total_amt, 2); ?>
                            </td>
                            <td class="text-end font-monospace text-success fw-semibold">
                                Rs. <?php echo number_format($paid_amt, 2); ?>
                            </td>
                            <td class="text-end font-monospace fw-bold <?php echo $balance > 0 ? 'text-danger' : 'text-success'; ?>">
                                Rs. <?php echo number_format($balance, 2); ?>
                            </td>
                            <td class="text-center">
                                <span class="badge <?php echo $badge_class; ?> px-2 py-1 small d-inline-flex align-items-center">
                                    <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo $status_label; ?>
                                </span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <a href="view.php?id=<?php echo $inv['id']; ?>" class="btn btn-outline-secondary btn-sm" title="View &amp; Print Invoice">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if (!$is_fully_paid && $balance > 0) { ?>
                                        <button 
                                            type="button" 
                                            class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 px-2"
                                            title="Collect Payment"
                                            onclick="openPaymentCollectionModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => (int)$inv['id'],
                                                'invoice_number' => $inv_num,
                                                'order_id' => (int)$inv['order_id'],
                                                'subtotal' => (float)$inv['subtotal'],
                                                'discount' => (float)$inv['discount'],
                                                'tax_type' => $inv['tax_type'] ?? 'percentage',
                                                'tax_input' => (float)($inv['tax_input'] ?? 0),
                                                'tax' => (float)$inv['tax'],
                                                'total_amount' => $total_amt,
                                                'total_paid' => $paid_amt,
                                                'balance' => $balance,
                                                'payment_count' => (int)$inv['payment_count']
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)"
                                        >
                                            <i class="bi bi-cash-stack"></i>
                                            <span>Pay</span>
                                        </button>
                                    <?php } ?>
                                    <?php if ($paid_amt == 0 && (int)$inv["payment_count"] == 0) { ?>
                                        <form method="POST" action="delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Cancel and delete uncollected Invoice <?php echo htmlspecialchars(addslashes($inv_num)); ?>?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $inv['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Unpaid Invoice">
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

<?php
$payment_modal_action = 'pay.php';
$payment_modal_return_to = 'index';
require_once "../includes/payment-modal.php";
?>

<script>
// Client-side quick filter
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('invoiceSearchInput');
    const filterButtons = document.querySelectorAll('#statusFilterGroup button');
    let currentFilter = 'all';

    function filterTable() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
        const rows = document.querySelectorAll('#invoicesTable tbody tr');

        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status') || '';
            const rowText = row.innerText.toLowerCase();

            const matchesStatus = (currentFilter === 'all') || (rowStatus === currentFilter);
            const matchesQuery = !query || rowText.includes(query);

            row.style.display = (matchesStatus && matchesQuery) ? '' : 'none';
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterTable);
    }

    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.getAttribute('data-filter');
            filterTable();
        });
    });
});
</script>

<?php
require_once "../includes/footer.php";
?>
