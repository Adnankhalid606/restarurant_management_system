<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT
            orders.id,
            orders.total_amount,
            orders.status,
            orders.payment_status

        FROM orders

        LEFT JOIN bills
            ON orders.id = bills.order_id

       WHERE bills.id IS NULL

        AND orders.status IN ('ready', 'completed')

        ORDER BY orders.id DESC";

$orders = mysqli_query($conn, $sql);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $order_id = isset($_POST["order_id"]) ? (int) $_POST["order_id"] : 0;
    $discount_raw = $_POST["discount"] ?? "0";
    $tax_type_raw = $_POST["tax_type"] ?? "manual";
    $tax_input_raw = $_POST["tax_input"] ?? "0";

    if ($order_id <= 0) {
        $error = "Please select a valid order.";
    } elseif ($discount_raw === null || is_array($discount_raw) || !is_scalar($discount_raw)) {
        $error = "Discount must be a valid numeric amount.";
    } elseif ($tax_type_raw === null || is_array($tax_type_raw) || !is_scalar($tax_type_raw)) {
        $error = "Tax calculation mode is invalid.";
    } elseif ($tax_input_raw === null || is_array($tax_input_raw) || !is_scalar($tax_input_raw)) {
        $error = "Tax input must be a valid numeric value.";
    } else {
        $tax_type = trim((string) $tax_type_raw);
        $discount_str = trim((string) $discount_raw);
        $tax_input_str = trim((string) $tax_input_raw);

        if (!in_array($tax_type, ["manual", "percentage"], true)) {
            $error = "Invalid tax calculation mode selected.";
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $discount_str)) {
            $error = "Discount must be a valid non-negative number with at most 2 decimal places.";
        } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $tax_input_str)) {
            $error = "Tax input must be a valid non-negative number with at most 2 decimal places.";
        } else {
            $discount = round((float) $discount_str, 2);
            $tax_input = round((float) $tax_input_str, 2);

            if ($discount > 99999999.99) {
                $error = "Discount exceeds the maximum permitted limit of 99,999,999.99.";
            } elseif ($tax_input > 99999999.99) {
                $error = "Tax input exceeds the maximum permitted limit of 99,999,999.99.";
            } else {
                // 1. Guard against duplicate bill generation for the same order
                $check_bill_sql = "SELECT id FROM bills WHERE order_id = ? LIMIT 1";
                $check_bill_stmt = mysqli_prepare($conn, $check_bill_sql);
                mysqli_stmt_bind_param($check_bill_stmt, "i", $order_id);
                mysqli_stmt_execute($check_bill_stmt);
                $existing_bill_res = mysqli_stmt_get_result($check_bill_stmt);

                if ($existing_bill = mysqli_fetch_assoc($existing_bill_res)) {
                    $error = "A bill has already been generated for Order #$order_id (Bill #{$existing_bill['id']}).";
                } else {
                    // 2. Fetch order and verify eligibility
                    $sql = "SELECT total_amount, status
                            FROM orders
                            WHERE id = ?";

                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $order_id);
                    mysqli_stmt_execute($stmt);

                    $result = mysqli_stmt_get_result($stmt);
                    $order = mysqli_fetch_assoc($result);

                    if (!$order) {
                        $error = "The selected order could not be found.";
                    } elseif (!in_array($order["status"], ["ready", "completed"], true)) {
                        $error = "Only orders with status 'Ready' or 'Completed' can be billed.";
                    } else {
                        $subtotal = round((float) $order["total_amount"], 2);

                        if ($discount < 0) {
                            $error = "Discount cannot be negative.";
                        } elseif ($discount > $subtotal) {
                            $error = "Discount cannot be greater than subtotal.";
                        } elseif ($tax_input < 0) {
                            $error = "Tax input cannot be negative.";
                        } else {
                            $max_monetary_paisa = 9999999999; // 99,999,999.99 in integer paisa
                            $subtotal_paisa = (int) round($subtotal * 100);
                            $discount_paisa = (int) round($discount * 100);

                            // Calculate monetary tax amount in integer paisa
                            if ($tax_type === "manual") {
                                $tax_paisa = (int) round($tax_input * 100);
                            } else {
                                // Decomposed integer arithmetic to mathematically guarantee no 64-bit integer overflow:
                                // subtotal in paisa: 0 <= subtotal_paisa <= 9,999,999,999
                                // tax_input in cents: 0 <= tax_input_cents <= 9,999,999,999
                                // Decompose: subtotal_paisa = S_q * 10,000 + S_r, where 0 <= S_r < 10,000
                                // Max S_q = 999,999; Max S_r = 9,999.
                                // Term 1: S_q * tax_input_cents <= 999,999 * 9,999,999,999 = ~9.999 x 10^14.
                                // Term 2: (S_r * tax_input_cents + 5000) <= (9,999 * 9,999,999,999 + 5000) = ~9.999 x 10^13.
                                // Both terms are strictly below PHP_INT_MAX (9.223 x 10^18), preventing any 64-bit overflow.
                                $tax_input_cents = (int) round($tax_input * 100);

                                $S_q = intdiv($subtotal_paisa, 10000);
                                $S_r = $subtotal_paisa % 10000;

                                $term1 = $S_q * $tax_input_cents;
                                $inner_remainder = $S_r * $tax_input_cents + 5000; // +5000 for half-up rounding
                                $term2 = intdiv($inner_remainder, 10000);

                                $tax_paisa = $term1 + $term2;
                            }

                            // Check calculated tax paisa against maximum permitted monetary limit
                            if ($tax_paisa > $max_monetary_paisa) {
                                $error = "Calculated tax exceeds the maximum permitted limit of 99,999,999.99.";
                            } else {
                                // Safe integer total calculation: 0 <= (subtotal_paisa - discount_paisa) + tax_paisa <= ~2 x 10^10
                                $total_paisa = ($subtotal_paisa - $discount_paisa) + $tax_paisa;
                                if ($total_paisa > $max_monetary_paisa) {
                                    $error = "Total bill amount exceeds the maximum permitted limit of 99,999,999.99.";
                                } else {
                                    // Convert to decimal monetary values at database binding boundary
                                    $tax = round($tax_paisa / 100, 2);
                                    $total_amount = round($total_paisa / 100, 2);
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($error === "") {

        /*
         * Create bill
         */
        try {
            $sql = "INSERT INTO bills
                    (
                        order_id,
                        subtotal,
                        discount,
                        tax_type,
                        tax_input,
                        tax,
                        total_amount
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "iddsddd",
                $order_id,
                $subtotal,
                $discount,
                $tax_type,
                $tax_input,
                $tax,
                $total_amount
            );

            mysqli_stmt_execute($stmt);

            $bill_id = mysqli_insert_id($conn);

            header("Location: view.php?id=" . $bill_id);
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = "Database rejected bill generation: a duplicate invoice exists for this order.";
        }
    }
}

$page_title = "Create Bill";
$active_menu = "bills";

require_once "../includes/header.php";

$order_count = mysqli_num_rows($orders);
$posted_order_id = $_POST["order_id"] ?? "";
$posted_discount = $_POST["discount"] ?? "0";
$posted_tax_type = $_POST["tax_type"] ?? "manual";
$posted_tax_input = $_POST["tax_input"] ?? "0";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Generate Guest Bill</h2>
        <p class="page-header-subtitle">Create a formalized invoice for completed kitchen orders</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Bills</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <?php if ($error !== "") { ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div>
                    <strong>Billing Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php } ?>

        <?php if ($order_count === 0) { ?>
            <!-- No billable orders prompt -->
            <div class="pos-card p-5 text-center shadow-sm">
                <div class="text-warning mb-3">
                    <i class="bi bi-info-circle-fill fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark mb-1">No Orders Available for Billing</h5>
                <p class="text-muted small mb-4" style="max-width: 480px; margin: 0 auto;">
                    Only orders with kitchen status <strong>Ready</strong> or <strong>Completed</strong> that have not already been billed can have a bill created.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="../orders/index.php" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-receipt me-1"></i>Check Orders
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm">
                        Return to Bills
                    </a>
                </div>
            </div>
        <?php } else { ?>
            <div class="pos-card shadow-sm">
                <div class="pos-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="pos-card-title">
                        <i class="bi bi-receipt-cutoff text-primary me-2"></i>Invoice Details
                    </span>
                    <span class="badge bg-white text-muted border"><?php echo $order_count; ?> Available Order(s)</span>
                </div>
                <div class="pos-card-body p-4">
                    <form method="POST" id="billForm">
                        <?php echo csrf_field(); ?>
                        <!-- Order Selection -->
                        <div class="mb-4">
                            <label for="orderSelect" class="form-label pos-form-label">
                                Target Order <span class="text-danger">*</span>
                            </label>
                            <select name="order_id" id="orderSelect" class="form-select pos-form-control py-2 fs-6" required>
                                <option value="" data-amount="0">-- Select Prepared / Completed Order --</option>
                                <?php
                                mysqli_data_seek($orders, 0);
                                while ($o = mysqli_fetch_assoc($orders)) {
                                    $selected = ($o["id"] == $posted_order_id) ? "selected" : "";
                                ?>
                                    <option value="<?php echo $o["id"]; ?>"
                                        data-amount="<?php echo $o["total_amount"]; ?>"
                                        <?php echo $selected; ?>>
                                        Order #<?php echo $o["id"]; ?> &bull; Rs. <?php echo number_format($o["total_amount"], 2); ?> (Status: <?php echo ucfirst($o["status"]); ?>)
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="form-text text-muted small">Only unbilled orders that are ready or completed appear here.</div>
                        </div>

                        <!-- Financial Adjustments: Discount & Tax -->
                        <div class="row g-3 mb-4">
                            <div class="col-12 col-md-6">
                                <label for="discountInput" class="form-label pos-form-label">
                                    Discount Amount (Rs.) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Rs.</span>
                                    <input
                                        type="number"
                                        class="form-control pos-form-control border-start-0 py-2"
                                        id="discountInput"
                                        name="discount"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo htmlspecialchars($posted_discount, ENT_QUOTES, 'UTF-8'); ?>"
                                        required>
                                </div>
                                <div class="form-text text-muted small">Deducted from order subtotal. Cannot exceed subtotal.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label class="form-label pos-form-label d-flex justify-content-between align-items-center mb-1">
                                    <span>Tax / Surcharge <span class="text-danger">*</span></span>
                                    <span class="text-muted small" id="taxTypeLabel">Mode: Fixed Amount</span>
                                </label>
                                
                                <!-- Mode Selector Pills -->
                                <div class="btn-group w-100 mb-2" role="group" aria-label="Tax Calculation Mode">
                                    <input type="radio" class="btn-check" name="tax_type" id="taxTypeManual" value="manual" <?php echo ($posted_tax_type !== 'percentage') ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-secondary btn-sm py-1" for="taxTypeManual">
                                        <i class="bi bi-cash me-1"></i>Fixed (Rs.)
                                    </label>

                                    <input type="radio" class="btn-check" name="tax_type" id="taxTypePercentage" value="percentage" <?php echo ($posted_tax_type === 'percentage') ? 'checked' : ''; ?>>
                                    <label class="btn btn-outline-secondary btn-sm py-1" for="taxTypePercentage">
                                        <i class="bi bi-percent me-1"></i>Percentage (%)
                                    </label>
                                </div>

                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0" id="taxAddon">
                                        <?php echo ($posted_tax_type === 'percentage') ? '%' : 'Rs.'; ?>
                                    </span>
                                    <input
                                        type="number"
                                        class="form-control pos-form-control border-start-0 py-2"
                                        id="taxInput"
                                        name="tax_input"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo htmlspecialchars($posted_tax_input, ENT_QUOTES, 'UTF-8'); ?>"
                                        required>
                                </div>
                                <div class="form-text text-muted small" id="taxHelpText">
                                    <?php echo ($posted_tax_type === 'percentage') ? 'Percentage calculated on pre-discount subtotal.' : 'Fixed monetary surcharge added to bill.'; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Live Calculation Preview Box -->
                        <div class="p-3 bg-light rounded border mb-4" id="calculationPreview">
                            <div class="d-flex justify-content-between py-1 text-muted small">
                                <span>Order Subtotal:</span>
                                <span id="previewSubtotal">Rs. 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 text-muted small">
                                <span>Discount:</span>
                                <span id="previewDiscount" class="text-success">- Rs. 0.00</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 text-muted small">
                                <span>Tax / Surcharge:</span>
                                <span id="previewTax">+ Rs. 0.00</span>
                            </div>
                            <div id="previewWarning" class="alert alert-warning py-1 px-2 my-2 small d-none" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                <span id="previewWarningText"></span>
                            </div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">Estimated Grand Total:</span>
                                <span class="fs-5 fw-bold text-primary" id="previewTotal">Rs. 0.00</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                            <a href="index.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" id="submitBillBtn" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="bi bi-receipt"></i>
                                <span>Generate Bill</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Client-side Calculation Preview Helper -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const orderSelect = document.getElementById('orderSelect');
                    const discountInput = document.getElementById('discountInput');
                    const taxInput = document.getElementById('taxInput');
                    const taxTypeManual = document.getElementById('taxTypeManual');
                    const taxTypePercentage = document.getElementById('taxTypePercentage');
                    const taxAddon = document.getElementById('taxAddon');
                    const taxHelpText = document.getElementById('taxHelpText');
                    const taxTypeLabel = document.getElementById('taxTypeLabel');

                    const previewSubtotal = document.getElementById('previewSubtotal');
                    const previewDiscount = document.getElementById('previewDiscount');
                    const previewTax = document.getElementById('previewTax');
                    const previewTotal = document.getElementById('previewTotal');
                    const previewWarning = document.getElementById('previewWarning');
                    const previewWarningText = document.getElementById('previewWarningText');
                    const submitBillBtn = document.getElementById('submitBillBtn');

                    function updateModeUI() {
                        const isPercentage = taxTypePercentage.checked;
                        if (isPercentage) {
                            taxAddon.textContent = '%';
                            taxHelpText.textContent = 'Percentage calculated on pre-discount subtotal.';
                            taxTypeLabel.textContent = 'Mode: Percentage Rate';
                        } else {
                            taxAddon.textContent = 'Rs.';
                            taxHelpText.textContent = 'Fixed monetary surcharge added to bill.';
                            taxTypeLabel.textContent = 'Mode: Fixed Amount';
                        }
                    }

                    function updatePreview() {
                        const selectedOption = orderSelect.options[orderSelect.selectedIndex];
                        const subtotal = selectedOption ? parseFloat(selectedOption.getAttribute('data-amount') || 0) : 0;
                        const discount = parseFloat(discountInput.value || 0);
                        const taxInputVal = parseFloat(taxInput.value || 0);
                        const isPercentage = taxTypePercentage.checked;

                        let taxAmount = 0;
                        let hasError = false;
                        let errorMessage = '';

                        if (isNaN(discount) || discount < 0) {
                            hasError = true;
                            errorMessage = 'Discount cannot be negative.';
                        } else if (discount > subtotal) {
                            hasError = true;
                            errorMessage = 'Discount exceeds order subtotal.';
                        }

                        if (isNaN(taxInputVal) || taxInputVal < 0) {
                            hasError = true;
                            errorMessage = 'Tax input cannot be negative.';
                        } else if (isPercentage) {
                            // Half-up rounding on cents: (subtotal * taxInputVal / 100)
                            taxAmount = Math.round((subtotal * taxInputVal / 100) * 100) / 100;
                            if (taxAmount > 99999999.99) {
                                hasError = true;
                                errorMessage = 'Calculated tax exceeds maximum permitted limit.';
                            }
                        } else {
                            taxAmount = taxInputVal;
                            if (taxAmount > 99999999.99) {
                                hasError = true;
                                errorMessage = 'Tax amount exceeds maximum permitted limit.';
                            }
                        }

                        const grandTotal = subtotal - discount + taxAmount;
                        if (grandTotal > 99999999.99) {
                            hasError = true;
                            errorMessage = 'Estimated grand total exceeds maximum permitted limit.';
                        }

                        previewSubtotal.textContent = 'Rs. ' + subtotal.toFixed(2);
                        previewDiscount.textContent = '- Rs. ' + (isNaN(discount) ? '0.00' : discount.toFixed(2));
                        
                        if (isPercentage) {
                            previewTax.textContent = '+ Rs. ' + taxAmount.toFixed(2) + ' (' + (isNaN(taxInputVal) ? '0' : taxInputVal.toFixed(2)) + '%)';
                        } else {
                            previewTax.textContent = '+ Rs. ' + taxAmount.toFixed(2);
                        }

                        previewTotal.textContent = 'Rs. ' + (isNaN(grandTotal) ? '0.00' : grandTotal.toFixed(2));

                        if (hasError) {
                            previewWarning.classList.remove('d-none');
                            previewWarningText.textContent = errorMessage;
                            submitBillBtn.disabled = true;
                        } else {
                            previewWarning.classList.add('d-none');
                            previewWarningText.textContent = '';
                            submitBillBtn.disabled = false;
                        }
                    }

                    orderSelect.addEventListener('change', updatePreview);
                    discountInput.addEventListener('input', updatePreview);
                    taxInput.addEventListener('input', updatePreview);
                    taxTypeManual.addEventListener('change', function() {
                        updateModeUI();
                        updatePreview();
                    });
                    taxTypePercentage.addEventListener('change', function() {
                        updateModeUI();
                        updatePreview();
                    });

                    updateModeUI();
                    updatePreview();
                });
            </script>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>