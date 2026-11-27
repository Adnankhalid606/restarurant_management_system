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

<!-- Modal: Collect Payment & Adjustments -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="pay.php" class="modal-content" id="paymentCollectionForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" id="pay_bill_id" name="id" value="">
            <input type="hidden" id="pay_return_to" name="return_to" value="index">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="paymentModalLabel">
                    <i class="bi bi-cash-stack text-success me-2"></i><span id="modal_title_text">Collect Payment</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Invoice Header Summary Bar -->
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-sm-3">
                            <span class="text-muted small d-block">Invoice Number</span>
                            <span class="font-monospace fw-bold text-dark fs-6" id="summary_inv_num">-</span>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted small d-block">Order Ref</span>
                            <span class="font-monospace fw-bold text-dark fs-6" id="summary_order_ref">-</span>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted small d-block">Order Subtotal</span>
                            <span class="font-monospace fw-bold text-dark fs-6" id="summary_subtotal">Rs. 0.00</span>
                        </div>
                        <div class="col-6 col-sm-3 text-sm-end">
                            <span class="text-muted small d-block">Previously Paid</span>
                            <span class="font-monospace fw-bold text-success fs-6" id="summary_paid">Rs. 0.00</span>
                        </div>
                    </div>
                </div>

                <!-- First Payment Adjustments Section (Editable for first payment, locked for subsequent) -->
                <div id="adjustments_container" class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-sliders2 text-primary"></i>
                            <span>Discount &amp; Tax / Surcharge Adjustments</span>
                        </h6>
                        <span class="badge bg-primary-subtle text-primary border small" id="adjustments_status_badge">
                            First Payment Collection
                        </span>
                    </div>

                    <!-- Lock Notice (Shown only if payments already exist) -->
                    <div id="locked_adjustments_notice" class="alert alert-light border small text-muted py-2 px-3 mb-3 d-none">
                        <i class="bi bi-lock-fill me-1 text-secondary"></i>
                        <span>Adjustments are permanently locked because payment history already exists for this invoice.</span>
                    </div>

                    <div class="card p-3 bg-light border-0">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="pay_discount" class="form-label fw-semibold small mb-1">
                                    Promotional Discount (Rs.)
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rs.</span>
                                    <input 
                                        type="number" 
                                        id="pay_discount" 
                                        name="discount" 
                                        class="form-control font-monospace" 
                                        step="0.01" 
                                        min="0" 
                                        value="0.00"
                                    >
                                </div>
                                <div class="form-text small text-muted">Cannot exceed subtotal.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="pay_tax_type" class="form-label fw-semibold small mb-1">
                                    Tax / Surcharge Mode
                                </label>
                                <select id="pay_tax_type" name="tax_type" class="form-select form-select-sm">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="manual">Manual Fixed Amount (Rs.)</option>
                                </select>
                                <div class="form-text small text-muted">Calculated on order subtotal.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="pay_tax_input" class="form-label fw-semibold small mb-1">
                                    Tax / Surcharge Input
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" id="tax_symbol_addon">%</span>
                                    <input 
                                        type="number" 
                                        id="pay_tax_input" 
                                        name="tax_input" 
                                        class="form-control font-monospace" 
                                        step="0.01" 
                                        min="0" 
                                        value="0.00"
                                    >
                                </div>
                                <div class="form-text small text-muted" id="tax_help_text">Tax: Rs. 0.00</div>
                            </div>
                        </div>

                        <!-- Live Calculated Totals Breakdown -->
                        <div class="row g-2 mt-2 pt-2 border-top">
                            <div class="col-6 col-sm-4">
                                <span class="text-muted small d-block">Calculated Tax:</span>
                                <span class="font-monospace fw-semibold text-dark" id="live_calc_tax">Rs. 0.00</span>
                            </div>
                            <div class="col-6 col-sm-4">
                                <span class="text-muted small d-block">Final Invoice Total:</span>
                                <span class="font-monospace fw-bold text-primary fs-6" id="live_final_total">Rs. 0.00</span>
                            </div>
                            <div class="col-12 col-sm-4 text-sm-end">
                                <span class="text-muted small d-block">Remaining Balance Due:</span>
                                <span class="font-monospace fw-bold text-danger fs-6" id="live_remaining_balance">Rs. 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details Section -->
                <div class="mb-3">
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                        <i class="bi bi-credit-card text-success"></i>
                        <span>Payment Collection Details</span>
                    </h6>

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="payment_method_id" class="form-label fw-semibold small mb-1">
                                Payment Method <span class="text-danger">*</span>
                            </label>
                            <select id="payment_method_id" name="payment_method_id" class="form-select" required>
                                <option value="">-- Choose Active Method --</option>
                                <?php foreach ($active_methods as $m) { ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo htmlspecialchars($m['name'] . ' (' . $m['classification'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="pay_amount" class="form-label fw-semibold small mb-1">
                                Amount Received (Rs.) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input 
                                    type="number" 
                                    id="pay_amount" 
                                    name="amount" 
                                    class="form-control font-monospace fw-bold text-success fs-6" 
                                    step="0.01" 
                                    min="0.01" 
                                    required
                                >
                            </div>
                            <div class="form-text small text-muted">Supports full payment or partial installment. Overpayment is rejected.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="transaction_ref" class="form-label fw-semibold small mb-1">
                                Transaction Reference <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="text" id="transaction_ref" name="transaction_ref" class="form-control" placeholder="e.g. Bank slip, Card approval #, Cheque ref">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="pay_notes" class="form-label fw-semibold small mb-1">
                                Payment Notes <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="text" id="pay_notes" name="notes" class="form-control" placeholder="Optional cashier settlement remark">
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3">
                    <i class="bi bi-check2-circle"></i>
                    <span>Confirm &amp; Collect Payment</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentInvoiceData = null;

function openPaymentCollectionModal(data) {
    currentInvoiceData = data;

    document.getElementById('pay_bill_id').value = data.id;
    document.getElementById('summary_inv_num').textContent = data.invoice_number;
    document.getElementById('summary_order_ref').textContent = '#ORD-' + data.order_id;
    document.getElementById('summary_subtotal').textContent = 'Rs. ' + parseFloat(data.subtotal).toFixed(2);
    document.getElementById('summary_paid').textContent = 'Rs. ' + parseFloat(data.total_paid).toFixed(2);

    const discountInput = document.getElementById('pay_discount');
    const taxTypeSelect = document.getElementById('pay_tax_type');
    const taxInput = document.getElementById('pay_tax_input');
    const lockNotice = document.getElementById('locked_adjustments_notice');
    const badge = document.getElementById('adjustments_status_badge');
    const modalTitle = document.getElementById('modal_title_text');

    discountInput.max = parseFloat(data.subtotal).toFixed(2);

    if (data.payment_count > 0) {
        // Subsequent payment: adjustments are locked
        modalTitle.textContent = 'Collect Remaining Payment - ' + data.invoice_number;
        badge.textContent = 'Adjustments Locked';
        badge.className = 'badge bg-secondary-subtle text-secondary border small';
        lockNotice.classList.remove('d-none');

        discountInput.value = parseFloat(data.discount).toFixed(2);
        discountInput.disabled = true;
        taxTypeSelect.value = data.tax_type;
        taxTypeSelect.disabled = true;
        taxInput.value = parseFloat(data.tax_input).toFixed(2);
        taxInput.disabled = true;

        updateCalculations(true);
    } else {
        // First payment: adjustments can be specified
        modalTitle.textContent = 'Collect Payment - ' + data.invoice_number;
        badge.textContent = 'First Payment Collection';
        badge.className = 'badge bg-primary-subtle text-primary border small';
        lockNotice.classList.add('d-none');

        discountInput.disabled = false;
        taxTypeSelect.disabled = false;
        taxInput.disabled = false;

        discountInput.value = parseFloat(data.discount || 0).toFixed(2);
        taxTypeSelect.value = data.tax_type || 'percentage';
        taxInput.value = parseFloat(data.tax_input || 0).toFixed(2);

        updateCalculations(false);
    }

    const modal = new bootstrap.Modal(document.getElementById('paymentModal'));
    modal.show();
}

function updateCalculations(isLocked) {
    if (!currentInvoiceData) return;

    const subtotal = parseFloat(currentInvoiceData.subtotal) || 0;
    const previouslyPaid = parseFloat(currentInvoiceData.total_paid) || 0;

    const discountEl = document.getElementById('pay_discount');
    const taxTypeEl = document.getElementById('pay_tax_type');
    const taxInputEl = document.getElementById('pay_tax_input');
    const taxSymbolAddon = document.getElementById('tax_symbol_addon');
    const taxHelpText = document.getElementById('tax_help_text');

    let discount = parseFloat(discountEl.value) || 0;
    if (discount < 0) discount = 0;
    if (discount > subtotal) discount = subtotal;

    const taxType = taxTypeEl.value;
    let taxInputVal = parseFloat(taxInputEl.value) || 0;
    if (taxInputVal < 0) taxInputVal = 0;

    let tax = 0;
    if (taxType === 'manual') {
        taxSymbolAddon.textContent = 'Rs.';
        tax = Math.round(taxInputVal * 100) / 100;
        taxHelpText.textContent = 'Fixed surcharge of Rs. ' + tax.toFixed(2);
    } else {
        taxSymbolAddon.textContent = '%';
        tax = Math.round((subtotal * taxInputVal / 100) * 100) / 100;
        taxHelpText.textContent = taxInputVal.toFixed(2) + '% of Rs. ' + subtotal.toFixed(2) + ' = Rs. ' + tax.toFixed(2);
    }

    let finalTotal = isLocked ? parseFloat(currentInvoiceData.total_amount) : Math.max(0, Math.round((subtotal - discount + tax) * 100) / 100);
    let remainingBalance = Math.max(0, Math.round((finalTotal - previouslyPaid) * 100) / 100);

    document.getElementById('live_calc_tax').textContent = 'Rs. ' + tax.toFixed(2);
    document.getElementById('live_final_total').textContent = 'Rs. ' + finalTotal.toFixed(2);
    document.getElementById('live_remaining_balance').textContent = 'Rs. ' + remainingBalance.toFixed(2);

    const amountInput = document.getElementById('pay_amount');
    amountInput.max = remainingBalance.toFixed(2);
    amountInput.value = remainingBalance.toFixed(2);
}

// Live calculation event listeners
document.getElementById('pay_discount').addEventListener('input', () => updateCalculations(false));
document.getElementById('pay_tax_type').addEventListener('change', () => updateCalculations(false));
document.getElementById('pay_tax_input').addEventListener('input', () => updateCalculations(false));

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
