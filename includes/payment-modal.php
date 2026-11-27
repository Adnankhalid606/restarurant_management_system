<?php
/**
 * Shared Collect Payment & Adjustments Modal
 * Used by invoices/index.php and orders/index.php.
 * Expects $active_methods (array of active payment methods),
 * $payment_modal_action (form action URL), and $payment_modal_return_to ('index' | 'orders').
 */
$payment_modal_action = $payment_modal_action ?? 'pay.php';
$payment_modal_return_to = $payment_modal_return_to ?? 'index';
$active_methods = $active_methods ?? [];
?>
<!-- Modal: Collect Payment & Adjustments -->
<div class="modal fade" id="paymentModal" tabindex="-1" aria-labelledby="paymentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="<?php echo htmlspecialchars($payment_modal_action, ENT_QUOTES, 'UTF-8'); ?>" class="modal-content" id="paymentCollectionForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" id="pay_bill_id" name="id" value="">
            <input type="hidden" id="pay_return_to" name="return_to" value="<?php echo htmlspecialchars($payment_modal_return_to, ENT_QUOTES, 'UTF-8'); ?>">

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
                                    <option value="<?php echo (int)$m['id']; ?>">
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
</script>
