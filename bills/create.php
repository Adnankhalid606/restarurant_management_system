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

    $order_id = $_POST["order_id"];
    $discount = $_POST["discount"];
    $tax = $_POST["tax"];

    /*
     * Get order
     */

    $sql = "SELECT total_amount
            FROM orders
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $order_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $order = mysqli_fetch_assoc($result);

    if (!$order) {

        $error = "Order not found.";
    } else {

        $subtotal = $order["total_amount"];

        /*
         * Validate discount
         */

        if ($discount < 0) {

            $error = "Discount cannot be negative.";
        } elseif ($discount > $subtotal) {

            $error = "Discount cannot be greater than subtotal.";
        } elseif ($tax < 0) {

            $error = "Tax cannot be negative.";
        }
    }

    if ($error === "") {

        $total_amount =
            $subtotal
            -
            $discount
            +
            $tax;

        /*
         * Create bill
         */

        $sql = "INSERT INTO bills
                (
                    order_id,
                    subtotal,
                    discount,
                    tax,
                    total_amount
                )

                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "idddd",
            $order_id,
            $subtotal,
            $discount,
            $tax,
            $total_amount
        );

        mysqli_stmt_execute($stmt);

        $bill_id = mysqli_insert_id($conn);

        header("Location: view.php?id=" . $bill_id);
        exit;
    }
}

$page_title = "Create Bill";
$active_menu = "bills";

require_once "../includes/header.php";

$order_count = mysqli_num_rows($orders);
$posted_order_id = $_POST["order_id"] ?? "";
$posted_discount = $_POST["discount"] ?? "0";
$posted_tax = $_POST["tax"] ?? "0";
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
                                        required
                                    >
                                </div>
                                <div class="form-text text-muted small">Deducted from order subtotal. Cannot exceed subtotal.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="taxInput" class="form-label pos-form-label">
                                    Tax / Surcharge (Rs.) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">Rs.</span>
                                    <input
                                        type="number"
                                        class="form-control pos-form-control border-start-0 py-2"
                                        id="taxInput"
                                        name="tax"
                                        step="0.01"
                                        min="0"
                                        value="<?php echo htmlspecialchars($posted_tax, ENT_QUOTES, 'UTF-8'); ?>"
                                        required
                                    >
                                </div>
                                <div class="form-text text-muted small">Added to order subtotal.</div>
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
                            <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
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

                const previewSubtotal = document.getElementById('previewSubtotal');
                const previewDiscount = document.getElementById('previewDiscount');
                const previewTax = document.getElementById('previewTax');
                const previewTotal = document.getElementById('previewTotal');

                function updatePreview() {
                    const selectedOption = orderSelect.options[orderSelect.selectedIndex];
                    const subtotal = selectedOption ? parseFloat(selectedOption.getAttribute('data-amount') || 0) : 0;
                    const discount = parseFloat(discountInput.value || 0);
                    const tax = parseFloat(taxInput.value || 0);

                    const total = Math.max(0, subtotal - discount + tax);

                    previewSubtotal.textContent = 'Rs. ' + subtotal.toFixed(2);
                    previewDiscount.textContent = '- Rs. ' + discount.toFixed(2);
                    previewTax.textContent = '+ Rs. ' + tax.toFixed(2);
                    previewTotal.textContent = 'Rs. ' + total.toFixed(2);
                }

                orderSelect.addEventListener('change', updatePreview);
                discountInput.addEventListener('input', updatePreview);
                taxInput.addEventListener('input', updatePreview);

                updatePreview();
            });
            </script>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>