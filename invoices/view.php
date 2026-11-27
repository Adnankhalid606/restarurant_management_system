<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Fetch invoice and associated order & customer details
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
            orders.order_type,
            orders.status AS order_status,
            orders.created_at AS order_created_at,
            tables.table_number,
            customers.name AS customer_name,
            customers.phone AS customer_phone,
            waiters.name AS waiter_name
        FROM bills
        JOIN orders ON bills.order_id = orders.id
        LEFT JOIN restaurant_tables tables ON orders.table_id = tables.id
        LEFT JOIN customers ON orders.customer_id = customers.id
        LEFT JOIN users waiters ON orders.waiter_id = waiters.id
        WHERE bills.id = ?";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    error_log("Invoice view SQL prepare failed: " . mysqli_error($conn));
    $_SESSION["error"] = "Unable to load invoice details due to a database error. Please contact the administrator.";
    header("Location: index.php");
    exit;
}
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$invoice = mysqli_fetch_assoc($result);

if (!$invoice) {
    $_SESSION["error"] = "Invoice not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

$display_invoice_number = !empty($invoice["invoice_number"]) 
    ? $invoice["invoice_number"] 
    : "INV-" . str_pad($invoice["id"], 5, "0", STR_PAD_LEFT);

// Fetch order items
$sql_items = "SELECT
                order_items.quantity,
                order_items.unit_price,
                order_items.subtotal,
                menu_items.name AS menu_item_name
            FROM order_items
            JOIN menu_items ON order_items.menu_item_id = menu_items.id
            WHERE order_items.order_id = ?";

$stmt_items = mysqli_prepare($conn, $sql_items);
if (!$stmt_items) {
    error_log("Invoice items SQL prepare failed: " . mysqli_error($conn));
    $_SESSION["error"] = "Unable to load invoice items due to a database error.";
    header("Location: index.php");
    exit;
}
mysqli_stmt_bind_param($stmt_items, "i", $invoice["order_id"]);
mysqli_stmt_execute($stmt_items);
$items = mysqli_stmt_get_result($stmt_items);
$order_items_list = [];
while ($item_row = mysqli_fetch_assoc($items)) {
    $order_items_list[] = $item_row;
}

// Fetch recorded payments for this invoice
$sql_payments = "SELECT
                    ip.id,
                    ip.payment_method_name,
                    ip.classification,
                    ip.amount,
                    ip.payment_date,
                    ip.transaction_ref,
                    ip.notes,
                    users.name AS received_by
                FROM invoice_payments ip
                LEFT JOIN users ON ip.created_by = users.id
                WHERE ip.bill_id = ?
                ORDER BY ip.payment_date ASC, ip.id ASC";

$stmt_payments = mysqli_prepare($conn, $sql_payments);
if (!$stmt_payments) {
    error_log("Invoice payments SQL prepare failed: " . mysqli_error($conn));
    $_SESSION["error"] = "Unable to load invoice payment ledger due to a database error.";
    header("Location: index.php");
    exit;
}
mysqli_stmt_bind_param($stmt_payments, "i", $id);
mysqli_stmt_execute($stmt_payments);
$payments_result = mysqli_stmt_get_result($stmt_payments);

$payments = [];
$total_paid = 0.0;
while ($p = mysqli_fetch_assoc($payments_result)) {
    $payments[] = $p;
    $total_paid += (float) $p["amount"];
}
$total_paid = round($total_paid, 2);
$grand_total = round((float) $invoice["total_amount"], 2);
$balance_due = round(max(0, $grand_total - $total_paid), 2);

$payment_count = count($payments);
$is_fully_paid = ($invoice["payment_status"] === "paid" || $balance_due <= 0);
$is_partial = (!$is_fully_paid && $total_paid > 0);

// Fetch active payment methods for payment modal
$methods_res = mysqli_query($conn, "SELECT id, name, classification FROM payment_methods WHERE status = 'active' ORDER BY name ASC");
$active_methods = [];
while ($m = mysqli_fetch_assoc($methods_res)) {
    $active_methods[] = $m;
}

$page_title = "Invoice " . $display_invoice_number;
$active_menu = "invoices";

require_once "../includes/header.php";
?>

<!-- Page Header Bar (Hidden on print) -->
<div class="page-header-bar d-print-none">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h2 class="page-header-title">Invoice <?php echo htmlspecialchars($display_invoice_number, ENT_QUOTES, 'UTF-8'); ?></h2>
            <?php if ($is_fully_paid) { ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-check-circle-fill me-1"></i>Paid in Full
                </span>
            <?php } elseif ($is_partial) { ?>
                <span class="badge bg-info-subtle text-primary border border-info-subtle px-2 py-1">
                    <i class="bi bi-pie-chart-fill me-1"></i>Partially Paid (Bal: Rs. <?php echo number_format($balance_due, 2); ?>)
                </span>
            <?php } else { ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                    <i class="bi bi-clock-history me-1"></i>Payment Pending (Bal: Rs. <?php echo number_format($balance_due, 2); ?>)
                </span>
            <?php } ?>
        </div>
        <p class="page-header-subtitle">
            Order #<?php echo $invoice["order_id"]; ?> &bull; Created on <?php echo formatDateTime($invoice["created_at"]); ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Invoices</span>
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-printer"></i>
            <span>Print Invoice</span>
        </button>
        <button type="button" onclick="triggerThermalPrint()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-printer"></i>
            <span>Thermal Print</span>
        </button>
        <?php if (!$is_fully_paid) { ?>
            <button type="button" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3" data-bs-toggle="modal" data-bs-target="#payModal">
                <i class="bi bi-cash-stack"></i>
                <span><?php echo $payment_count === 0 ? 'Collect Payment' : 'Collect Remaining Payment'; ?></span>
            </button>
        <?php } ?>
    </div>
</div>

<?php if (isset($_SESSION["flash_success"])) { ?>
    <div class="alert alert-success border py-2 px-3 mb-4 small d-flex align-items-center gap-2 d-print-none">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 text-success"></i>
        <div><?php echo htmlspecialchars($_SESSION["flash_success"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["flash_success"]); ?></div>
    </div>
<?php } ?>

<?php if (isset($_SESSION["error"])) { ?>
    <div class="alert alert-danger border py-2 px-3 mb-4 small d-flex align-items-center gap-2 d-print-none">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger"></i>
        <div><?php echo htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["error"]); ?></div>
    </div>
<?php } ?>

<div class="row justify-content-center d-print-none">
    <div class="col-12 col-lg-10 col-xl-9">
        <!-- Guest Invoice Document Card -->
        <div class="pos-card p-4 p-md-5 mb-4 shadow-sm" id="printableInvoice">
            <!-- Invoice Brand Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-start pb-4 border-bottom mb-4 gap-3">
                <div>
                    <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-shop text-primary"></i>
                        <span>RestoBar POS &amp; Restaurant</span>
                    </h3>
                    <p class="text-muted small mb-0">Official Dining Receipt &amp; Tax Invoice</p>
                </div>
                <div class="text-md-end">
                    <div class="fs-5 fw-bold text-dark font-monospace"><?php echo htmlspecialchars($display_invoice_number, ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="text-muted small">Date: <?php echo formatDateTime($invoice["created_at"]); ?></div>
                    <div class="text-muted small">Ref Order: #ORD-<?php echo $invoice["order_id"]; ?></div>
                </div>
            </div>

            <!-- Customer & Order Information Grid -->
            <div class="row g-4 mb-4 pb-4 border-bottom">
                <div class="col-12 col-sm-6">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-2">Billed To (Customer)</span>
                    <div class="fw-bold text-dark fs-6">
                        <?php echo htmlspecialchars($invoice["customer_name"] ?? "Walk-in Guest", ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if (!empty($invoice["customer_phone"])) { ?>
                        <div class="text-muted small mt-1">
                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($invoice["customer_phone"], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php } ?>
                </div>

                <div class="col-12 col-sm-6 text-sm-end">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-2">Order &amp; Settlement Details</span>
                    <div class="mb-2">
                        <span class="text-muted small me-2">Service Type:</span>
                        <span class="badge bg-light text-dark border text-capitalize">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $invoice["order_type"]), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <?php if (!empty($invoice["table_number"])) { ?>
                            <span class="badge bg-light text-dark border ms-1">
                                Table <?php echo htmlspecialchars($invoice["table_number"], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php } ?>
                    </div>
                    <div>
                        <?php if ($is_fully_paid) { ?>
                            <div class="paid-stamp">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>PAID IN FULL</span>
                            </div>
                            <?php if (!empty($invoice["paid_at"])) { ?>
                                <div class="text-muted small mt-1">
                                    Settled: <?php echo formatDateTime($invoice["paid_at"]); ?>
                                </div>
                            <?php } ?>
                        <?php } elseif ($is_partial) { ?>
                            <div class="unpaid-stamp text-info border-info">
                                <i class="bi bi-pie-chart"></i>
                                <span>PARTIAL PAYMENT</span>
                            </div>
                            <div class="text-danger small mt-1 fw-semibold font-monospace">
                                Remaining Due: Rs. <?php echo number_format($balance_due, 2); ?>
                            </div>
                        <?php } else { ?>
                            <div class="unpaid-stamp">
                                <i class="bi bi-hourglass-split"></i>
                                <span>UNPAID</span>
                            </div>
                            <div class="text-muted small mt-1">Payment collection pending</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Itemized Order Items Table -->
            <div class="table-responsive mb-4">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Item Description</th>
                            <th class="text-center" style="width: 100px;">Qty</th>
                            <th class="text-end" style="width: 140px;">Unit Price</th>
                            <th class="text-end" style="width: 150px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        if (!empty($order_items_list)) {
                            foreach ($order_items_list as $item) { 
                            ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $counter++; ?></td>
                                    <td>
                                        <span class="fw-semibold text-dark">
                                            <?php echo htmlspecialchars($item["menu_item_name"], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <?php echo $item["quantity"]; ?> &times;
                                        </span>
                                    </td>
                                    <td class="text-end text-muted font-monospace">
                                        Rs. <?php echo number_format($item["unit_price"], 2); ?>
                                    </td>
                                    <td class="text-end fw-semibold text-dark font-monospace">
                                        Rs. <?php echo number_format($item["subtotal"], 2); ?>
                                    </td>
                                </tr>
                            <?php } 
                        } else { ?>
                            <tr>
                                <td colspan="5" class="text-muted small text-center py-2">
                                    Direct order record (Subtotal: Rs. <?php echo number_format($invoice["subtotal"], 2); ?>)
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Settlement Totals Breakdown -->
            <div class="row justify-content-end mb-4">
                <div class="col-12 col-sm-8 col-md-6 col-lg-5">
                    <div class="bg-light p-3 rounded border">
                        <div class="d-flex justify-content-between py-1 text-muted">
                            <span>Order Subtotal:</span>
                            <span class="fw-semibold text-dark font-monospace">Rs. <?php echo number_format($invoice["subtotal"], 2); ?></span>
                        </div>
                        <?php if ($invoice["discount"] > 0) { ?>
                            <div class="d-flex justify-content-between py-1 text-success">
                                <span>Discount:</span>
                                <span class="font-monospace">&minus; Rs. <?php echo number_format($invoice["discount"], 2); ?></span>
                            </div>
                        <?php } ?>
                        <?php if ($invoice["tax"] > 0 || $invoice["tax_input"] > 0) { 
                            $tax_label_extra = "";
                            if (($invoice["tax_type"] ?? "") === "percentage" && isset($invoice["tax_input"])) {
                                $formatted_rate = rtrim(rtrim(number_format((float)$invoice["tax_input"], 2), '0'), '.');
                                $tax_label_extra = " (" . htmlspecialchars($formatted_rate, ENT_QUOTES, 'UTF-8') . "%)";
                            } else {
                                $tax_label_extra = " (Fixed)";
                            }
                        ?>
                            <div class="d-flex justify-content-between py-1 text-muted">
                                <span>Tax / Surcharge<?php echo $tax_label_extra; ?>:</span>
                                <span class="font-monospace">+ Rs. <?php echo number_format($invoice["tax"], 2); ?></span>
                            </div>
                        <?php } ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <span class="fw-bold text-dark fs-6">Grand Total:</span>
                            <span class="fs-5 fw-bold text-primary font-monospace">Rs. <?php echo number_format($grand_total, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center py-1 text-success">
                            <span class="fw-semibold">Total Paid:</span>
                            <span class="fw-bold font-monospace">Rs. <?php echo number_format($total_paid, 2); ?></span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <span class="fw-bold <?php echo $balance_due > 0 ? 'text-danger' : 'text-muted'; ?>">Balance Due:</span>
                            <span class="fs-5 fw-bold font-monospace <?php echo $balance_due > 0 ? 'text-danger' : 'text-success'; ?>">
                                Rs. <?php echo number_format($balance_due, 2); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment History Ledger -->
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-wallet2 text-secondary"></i>
                        <span>Payment History &amp; Settlement Ledger</span>
                    </h5>
                    <?php if (!$is_fully_paid) { ?>
                        <button type="button" class="btn btn-outline-success btn-sm d-print-none" data-bs-toggle="modal" data-bs-target="#payModal">
                            <i class="bi bi-plus-circle me-1"></i><?php echo $payment_count === 0 ? 'Collect Payment' : 'Collect Remaining Payment'; ?>
                        </button>
                    <?php } ?>
                </div>

                <?php if (empty($payments)) { ?>
                    <div class="alert alert-light border text-muted small p-3 text-center mb-0">
                        <i class="bi bi-info-circle me-1"></i>No payment transactions have been recorded for this invoice yet.
                    </div>
                <?php } else { ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle table-bordered mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Date &amp; Time</th>
                                    <th>Payment Method</th>
                                    <th>Classification</th>
                                    <th>Reference / Notes</th>
                                    <th>Recorded By</th>
                                    <th class="text-end" style="width: 140px;">Amount Paid</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $p_num = 1;
                                foreach ($payments as $pmt) {
                                    $c_class = 'bg-secondary-subtle text-secondary';
                                    if ($pmt['classification'] === 'Cash') $c_class = 'bg-success-subtle text-success';
                                    elseif ($pmt['classification'] === 'Bank Transfer') $c_class = 'bg-primary-subtle text-primary';
                                    elseif ($pmt['classification'] === 'Mobile Wallet') $c_class = 'bg-warning-subtle text-warning';
                                    elseif ($pmt['classification'] === 'Card') $c_class = 'bg-info-subtle text-info';
                                ?>
                                    <tr>
                                        <td class="text-muted small"><?php echo $p_num++; ?></td>
                                        <td class="small"><?php echo formatDateTime($pmt["payment_date"]); ?></td>
                                        <td>
                                            <span class="fw-semibold text-dark">
                                                <?php echo htmlspecialchars($pmt["payment_method_name"], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $c_class; ?> border px-2 py-1 small">
                                                <?php echo htmlspecialchars($pmt["classification"], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?php if (!empty($pmt["transaction_ref"])) { ?>
                                                <span class="d-block font-monospace">Ref: <?php echo htmlspecialchars($pmt["transaction_ref"], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php } ?>
                                            <?php if (!empty($pmt["notes"])) { ?>
                                                <span class="text-muted"><?php echo htmlspecialchars($pmt["notes"], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php } ?>
                                            <?php if (empty($pmt["transaction_ref"]) && empty($pmt["notes"])) { echo "-"; } ?>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo htmlspecialchars($pmt["received_by"] ?? "System", ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td class="text-end fw-bold text-success font-monospace">
                                            Rs. <?php echo number_format($pmt["amount"], 2); ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="6" class="text-end">Total Settled:</th>
                                    <th class="text-end text-success font-monospace">Rs. <?php echo number_format($total_paid, 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php } ?>
            </div>

            <!-- Receipt Footer Note -->
            <div class="text-center pt-4 border-top mt-4 text-muted small">
                <p class="mb-1">Thank you for dining with us! We appreciate your business.</p>
                <p class="mb-0 opacity-75">Official tax invoice generated by RestoBar POS System</p>
            </div>
        </div>
    </div>
</div>

<!-- Dedicated A4 Portrait Print Layout -->
<style>
@page {
    size: A4 portrait;
    margin: 12mm 14mm;
}

@media print {
    html,
    body,
    .app-wrapper,
    .app-main,
    .app-content {
        background: #ffffff !important;
        color: #111827 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        min-height: 0 !important;
        box-shadow: none !important;
    }

    .app-sidebar,
    .app-topbar,
    .app-footer,
    .sidebar-backdrop,
    .d-print-none,
    .modal,
    .modal-backdrop {
        display: none !important;
    }

    .print-invoice-sheet {
        display: block !important;
        width: 100%;
        background: #ffffff !important;
        color: #111827 !important;
        font-family: "Inter", "Segoe UI", Arial, sans-serif;
        font-size: 9.5pt;
        line-height: 1.38;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .print-invoice-header-table {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 2px solid #1d4ed8;
        margin-bottom: 12px;
        break-inside: avoid;
    }

    .print-invoice-header-table td {
        vertical-align: top;
        padding-bottom: 10px;
    }

    .print-brand-title {
        font-size: 15pt;
        font-weight: 700;
        color: #0f172a;
        margin: 0 0 2px 0;
        letter-spacing: -0.01em;
    }

    .print-brand-title i {
        color: #1d4ed8;
        margin-right: 5px;
    }

    .print-brand-sub {
        font-size: 8.5pt;
        color: #475569;
        margin: 0;
    }

    .print-inv-number {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 13pt;
        font-weight: 700;
        color: #1d4ed8;
        margin: 0 0 2px 0;
    }

    .print-meta-line {
        font-size: 8.5pt;
        color: #334155;
        margin: 0;
    }

    .print-details-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        border-bottom: 1px solid #cbd5e1;
        break-inside: avoid;
    }

    .print-details-table td {
        width: 50%;
        vertical-align: top;
        padding-bottom: 10px;
    }

    .print-section-label {
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #1d4ed8;
        margin-bottom: 4px;
    }

    .print-kv-line {
        font-size: 9pt;
        color: #1e293b;
        margin: 1px 0;
    }

    .print-kv-label {
        color: #64748b;
    }

    .print-items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
    }

    .print-items-table thead {
        display: table-header-group;
    }

    .print-items-table tr {
        break-inside: avoid;
    }

    .print-items-table th {
        background-color: #f1f5f9 !important;
        color: #0f172a;
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 6px 8px;
        border-top: 1px solid #cbd5e1;
        border-bottom: 1.5px solid #94a3b8;
    }

    .print-items-table td {
        padding: 5px 8px;
        border-bottom: 1px solid #e2e8f0;
        font-size: 9pt;
        color: #111827;
        vertical-align: top;
    }

    .print-summary-wrap {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 12px;
        break-inside: avoid;
    }

    .print-summary-table {
        width: 46%;
        margin-left: auto;
        border-collapse: collapse;
        border: 1px solid #cbd5e1;
    }

    .print-summary-table td {
        padding: 4px 8px;
        font-size: 9pt;
        border-bottom: 1px solid #e2e8f0;
    }

    .print-summary-grand td {
        background-color: #f8fafc !important;
        border-top: 1.5px solid #1d4ed8;
        border-bottom: 1.5px solid #1d4ed8;
        font-weight: 700;
        font-size: 10.5pt;
        color: #0f172a;
        padding: 6px 8px;
    }

    .print-summary-balance td {
        font-weight: 700;
        font-size: 9.5pt;
        border-bottom: none;
        padding-top: 5px;
    }

    .print-payments-block {
        margin-top: 10px;
        padding-top: 8px;
        border-top: 1px solid #cbd5e1;
        break-inside: avoid;
    }

    .print-payments-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4px;
    }

    .print-payments-table thead {
        display: table-header-group;
    }

    .print-payments-table tr {
        break-inside: avoid;
    }

    .print-payments-table th {
        background-color: #f1f5f9 !important;
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        color: #334155;
        padding: 4px 7px;
        border-top: 1px solid #cbd5e1;
        border-bottom: 1px solid #94a3b8;
    }

    .print-payments-table td {
        font-size: 8.5pt;
        padding: 4px 7px;
        border-bottom: 1px solid #e2e8f0;
        color: #1e293b;
    }

    .print-empty-payments {
        font-size: 8.5pt;
        color: #64748b;
        font-style: italic;
        margin: 4px 0 0 0;
    }

    .print-footer {
        margin-top: 14px;
        padding-top: 8px;
        border-top: 1px solid #cbd5e1;
        text-align: center;
        font-size: 8.5pt;
        color: #475569;
        break-inside: avoid;
    }

    .print-mono {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
}
</style>

<div class="d-none d-print-block print-invoice-sheet" id="printInvoiceSheet">
    <!-- 1. Header -->
    <table class="print-invoice-header-table">
        <tr>
            <td>
                <div class="print-brand-title">
                    <i class="bi bi-shop"></i>RestoBar POS &amp; Restaurant
                </div>
                <p class="print-brand-sub">Official Dining Receipt &amp; Tax Invoice</p>
            </td>
            <td style="text-align: right;">
                <div class="print-inv-number"><?php echo htmlspecialchars($display_invoice_number, ENT_QUOTES, 'UTF-8'); ?></div>
                <p class="print-meta-line">Invoice Date: <?php echo formatDateTime($invoice["created_at"]); ?></p>
                <p class="print-meta-line">Order Ref: <span class="print-mono">#ORD-<?php echo (int)$invoice["order_id"]; ?></span></p>
            </td>
        </tr>
    </table>

    <!-- 2. Customer & Order Details -->
    <table class="print-details-table">
        <tr>
            <td style="padding-right: 12px;">
                <div class="print-section-label">Customer Details</div>
                <div class="print-kv-line"><strong><?php echo htmlspecialchars($invoice["customer_name"] ?? "Walk-in Guest", ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <?php if (!empty($invoice["customer_phone"])) { ?>
                    <div class="print-kv-line"><span class="print-kv-label">Phone:</span> <?php echo htmlspecialchars($invoice["customer_phone"], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>
            </td>
            <td style="padding-left: 12px; text-align: right;">
                <div class="print-section-label">Order Details</div>
                <div class="print-kv-line"><span class="print-kv-label">Order Ref:</span> <span class="print-mono">#ORD-<?php echo (int)$invoice["order_id"]; ?></span></div>
                <div class="print-kv-line">
                    <span class="print-kv-label">Order Type:</span>
                    <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $invoice["order_type"])), ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($invoice["table_number"])) { ?>
                        &bull; Table <?php echo htmlspecialchars($invoice["table_number"], ENT_QUOTES, 'UTF-8'); ?>
                    <?php } ?>
                </div>
                <?php if (!empty($invoice["waiter_name"])) { ?>
                    <div class="print-kv-line"><span class="print-kv-label">Waiter:</span> <?php echo htmlspecialchars($invoice["waiter_name"], ENT_QUOTES, 'UTF-8'); ?></div>
                <?php } ?>
                <div class="print-kv-line"><span class="print-kv-label">Order Date:</span> <?php echo formatDateTime($invoice["order_created_at"]); ?></div>
                <div class="print-kv-line">
                    <span class="print-kv-label">Payment Status:</span>
                    <strong><?php echo $is_fully_paid ? 'Paid in Full' : ($is_partial ? 'Partially Paid' : 'Unpaid'); ?></strong>
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. Items Table -->
    <table class="print-items-table">
        <thead>
            <tr>
                <th style="width: 34px; text-align: left;">#</th>
                <th style="text-align: left;">Item Description</th>
                <th style="width: 60px; text-align: right;">Qty</th>
                <th style="width: 110px; text-align: right;">Unit Price</th>
                <th style="width: 115px; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($order_items_list)) {
                $p_idx = 1;
                foreach ($order_items_list as $item) { ?>
                    <tr>
                        <td><?php echo $p_idx++; ?></td>
                        <td><?php echo htmlspecialchars($item["menu_item_name"], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td class="print-mono" style="text-align: right;"><?php echo (int)$item["quantity"]; ?></td>
                        <td class="print-mono" style="text-align: right;">PKR <?php echo number_format((float)$item["unit_price"], 2); ?></td>
                        <td class="print-mono" style="text-align: right;">PKR <?php echo number_format((float)$item["subtotal"], 2); ?></td>
                    </tr>
                <?php }
            } else { ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b;">
                        Direct order record (Subtotal: PKR <?php echo number_format((float)$invoice["subtotal"], 2); ?>)
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <!-- 4. Financial Summary -->
    <table class="print-summary-wrap">
        <tr>
            <td>
                <table class="print-summary-table">
                    <tr>
                        <td>Order Subtotal</td>
                        <td class="print-mono" style="text-align: right;">PKR <?php echo number_format((float)$invoice["subtotal"], 2); ?></td>
                    </tr>
                    <?php if ((float)$invoice["discount"] > 0) { ?>
                        <tr>
                            <td>Promotional Discount</td>
                            <td class="print-mono" style="text-align: right;">&minus; PKR <?php echo number_format((float)$invoice["discount"], 2); ?></td>
                        </tr>
                    <?php } ?>
                    <?php if ((float)$invoice["tax"] > 0 || (float)$invoice["tax_input"] > 0) {
                        $print_tax_label = (($invoice["tax_type"] ?? "") === "percentage" && isset($invoice["tax_input"]))
                            ? " (" . rtrim(rtrim(number_format((float)$invoice["tax_input"], 2), '0'), '.') . "%)"
                            : " (Fixed)";
                    ?>
                        <tr>
                            <td>Tax / Surcharge<?php echo htmlspecialchars($print_tax_label, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="print-mono" style="text-align: right;">+ PKR <?php echo number_format((float)$invoice["tax"], 2); ?></td>
                        </tr>
                    <?php } ?>
                    <tr class="print-summary-grand">
                        <td>Grand Total</td>
                        <td class="print-mono" style="text-align: right;">PKR <?php echo number_format($grand_total, 2); ?></td>
                    </tr>
                    <tr>
                        <td>Total Paid</td>
                        <td class="print-mono" style="text-align: right;">PKR <?php echo number_format($total_paid, 2); ?></td>
                    </tr>
                    <tr class="print-summary-balance">
                        <td>Balance Due</td>
                        <td class="print-mono" style="text-align: right; color: <?php echo $balance_due > 0 ? '#b91c1c' : '#15803d'; ?>;">
                            PKR <?php echo number_format($balance_due, 2); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- 5. Payment History -->
    <div class="print-payments-block">
        <div class="print-section-label">Payment History</div>
        <?php if (empty($payments)) { ?>
            <p class="print-empty-payments">No payment transactions recorded yet.</p>
        <?php } else { ?>
            <table class="print-payments-table">
                <thead>
                    <tr>
                        <th style="text-align: left;">Date</th>
                        <th style="text-align: left;">Payment Method</th>
                        <th style="text-align: left;">Reference</th>
                        <th style="width: 115px; text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pmt) {
                        $ref_parts = [];
                        if (!empty($pmt["transaction_ref"])) $ref_parts[] = $pmt["transaction_ref"];
                        if (!empty($pmt["notes"])) $ref_parts[] = $pmt["notes"];
                        $ref_text = !empty($ref_parts) ? implode(" — ", $ref_parts) : "-";
                    ?>
                        <tr>
                            <td><?php echo formatDateTime($pmt["payment_date"]); ?></td>
                            <td><?php echo htmlspecialchars($pmt["payment_method_name"], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($ref_text, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="print-mono" style="text-align: right;">PKR <?php echo number_format((float)$pmt["amount"], 2); ?></td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        <?php } ?>
    </div>

    <!-- 6. Footer -->
    <div class="print-footer">
        <div style="font-weight: 600; color: #0f172a; margin-bottom: 2px;">Thank you for visiting!</div>
        <div>We appreciate your dining experience with us. &bull; RestoBar POS &amp; Restaurant</div>
    </div>
</div>

<!-- Modal: Collect Payment & Adjustments -->
<?php if (!$is_fully_paid) { ?>
<div class="modal fade d-print-none" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="pay.php" class="modal-content" id="viewPaymentForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $invoice["id"]; ?>">
            <input type="hidden" name="return_to" value="view">

            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="payModalLabel">
                    <i class="bi bi-cash-stack text-success me-2"></i>
                    <?php echo $payment_count === 0 ? 'Collect Payment - ' . htmlspecialchars($display_invoice_number) : 'Collect Remaining Payment - ' . htmlspecialchars($display_invoice_number); ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <!-- Summary bar -->
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row g-2 align-items-center">
                        <div class="col-6 col-sm-3">
                            <span class="text-muted small d-block">Invoice Number</span>
                            <span class="font-monospace fw-bold text-dark fs-6"><?php echo htmlspecialchars($display_invoice_number); ?></span>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted small d-block">Order Ref</span>
                            <span class="font-monospace fw-bold text-dark fs-6">#ORD-<?php echo $invoice["order_id"]; ?></span>
                        </div>
                        <div class="col-6 col-sm-3">
                            <span class="text-muted small d-block">Order Subtotal</span>
                            <span class="font-monospace fw-bold text-dark fs-6">Rs. <?php echo number_format($invoice["subtotal"], 2); ?></span>
                        </div>
                        <div class="col-6 col-sm-3 text-sm-end">
                            <span class="text-muted small d-block">Previously Paid</span>
                            <span class="font-monospace fw-bold text-success fs-6">Rs. <?php echo number_format($total_paid, 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Adjustments Section -->
                <div class="mb-4">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-sliders2 text-primary"></i>
                            <span>Discount &amp; Tax / Surcharge Adjustments</span>
                        </h6>
                        <?php if ($payment_count > 0) { ?>
                            <span class="badge bg-secondary-subtle text-secondary border small">Adjustments Locked</span>
                        <?php } else { ?>
                            <span class="badge bg-primary-subtle text-primary border small">First Payment Collection</span>
                        <?php } ?>
                    </div>

                    <?php if ($payment_count > 0) { ?>
                        <div class="alert alert-light border small text-muted py-2 px-3 mb-3">
                            <i class="bi bi-lock-fill me-1 text-secondary"></i>
                            <span>Adjustments are permanently locked because payment history already exists for this invoice.</span>
                        </div>
                    <?php } ?>

                    <div class="card p-3 bg-light border-0">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label for="v_pay_discount" class="form-label fw-semibold small mb-1">
                                    Promotional Discount (Rs.)
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">Rs.</span>
                                    <input 
                                        type="number" 
                                        id="v_pay_discount" 
                                        name="discount" 
                                        class="form-control font-monospace" 
                                        step="0.01" 
                                        min="0" 
                                        max="<?php echo $invoice["subtotal"]; ?>" 
                                        value="<?php echo number_format((float)$invoice["discount"], 2, '.', ''); ?>"
                                        <?php echo $payment_count > 0 ? 'disabled' : ''; ?>
                                    >
                                </div>
                                <div class="form-text small text-muted">Cannot exceed subtotal.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="v_pay_tax_type" class="form-label fw-semibold small mb-1">
                                    Tax / Surcharge Mode
                                </label>
                                <select id="v_pay_tax_type" name="tax_type" class="form-select form-select-sm" <?php echo $payment_count > 0 ? 'disabled' : ''; ?>>
                                    <option value="percentage" <?php echo ($invoice["tax_type"] ?? "") === "percentage" ? "selected" : ""; ?>>Percentage (%)</option>
                                    <option value="manual" <?php echo ($invoice["tax_type"] ?? "") === "manual" ? "selected" : ""; ?>>Manual Fixed Amount (Rs.)</option>
                                </select>
                                <div class="form-text small text-muted">Calculated on order subtotal.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label for="v_pay_tax_input" class="form-label fw-semibold small mb-1">
                                    Tax / Surcharge Input
                                </label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text" id="v_tax_symbol_addon"><?php echo ($invoice["tax_type"] ?? "") === "manual" ? "Rs." : "%"; ?></span>
                                    <input 
                                        type="number" 
                                        id="v_pay_tax_input" 
                                        name="tax_input" 
                                        class="form-control font-monospace" 
                                        step="0.01" 
                                        min="0" 
                                        value="<?php echo number_format((float)($invoice["tax_input"] ?? 0), 2, '.', ''); ?>"
                                        <?php echo $payment_count > 0 ? 'disabled' : ''; ?>
                                    >
                                </div>
                                <div class="form-text small text-muted" id="v_tax_help_text">Tax: Rs. <?php echo number_format($invoice["tax"], 2); ?></div>
                            </div>
                        </div>

                        <!-- Live Calculated Totals Breakdown -->
                        <div class="row g-2 mt-2 pt-2 border-top">
                            <div class="col-6 col-sm-4">
                                <span class="text-muted small d-block">Calculated Tax:</span>
                                <span class="font-monospace fw-semibold text-dark" id="v_live_calc_tax">Rs. <?php echo number_format($invoice["tax"], 2); ?></span>
                            </div>
                            <div class="col-6 col-sm-4">
                                <span class="text-muted small d-block">Final Invoice Total:</span>
                                <span class="font-monospace fw-bold text-primary fs-6" id="v_live_final_total">Rs. <?php echo number_format($grand_total, 2); ?></span>
                            </div>
                            <div class="col-12 col-sm-4 text-sm-end">
                                <span class="text-muted small d-block">Remaining Balance Due:</span>
                                <span class="font-monospace fw-bold text-danger fs-6" id="v_live_remaining_balance">Rs. <?php echo number_format($balance_due, 2); ?></span>
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
                            <label for="v_payment_method_id" class="form-label fw-semibold small mb-1">
                                Payment Method <span class="text-danger">*</span>
                            </label>
                            <select id="v_payment_method_id" name="payment_method_id" class="form-select" required>
                                <option value="">-- Choose Active Method --</option>
                                <?php foreach ($active_methods as $m) { ?>
                                    <option value="<?php echo $m['id']; ?>">
                                        <?php echo htmlspecialchars($m['name'] . ' (' . $m['classification'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="v_pay_amount" class="form-label fw-semibold small mb-1">
                                Amount Received (Rs.) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">Rs.</span>
                                <input 
                                    type="number" 
                                    id="v_pay_amount" 
                                    name="amount" 
                                    class="form-control font-monospace fw-bold text-success fs-6" 
                                    step="0.01" 
                                    min="0.01" 
                                    max="<?php echo $balance_due; ?>"
                                    value="<?php echo $balance_due; ?>"
                                    required
                                >
                            </div>
                            <div class="form-text small text-muted">Supports full payment or partial installment. Overpayment is rejected.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="v_transaction_ref" class="form-label fw-semibold small mb-1">
                                Transaction Reference <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="text" id="v_transaction_ref" name="transaction_ref" class="form-control" placeholder="e.g. Bank slip, Card approval #, Cheque ref">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="v_notes" class="form-label fw-semibold small mb-1">
                                Payment Notes <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <input type="text" id="v_notes" name="notes" class="form-control" placeholder="Optional cashier settlement remark">
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
document.addEventListener('DOMContentLoaded', function() {
    const isLocked = <?php echo $payment_count > 0 ? 'true' : 'false'; ?>;
    const subtotal = <?php echo (float)$invoice["subtotal"]; ?>;
    const previouslyPaid = <?php echo $total_paid; ?>;

    const discountEl = document.getElementById('v_pay_discount');
    const taxTypeEl = document.getElementById('v_pay_tax_type');
    const taxInputEl = document.getElementById('v_pay_tax_input');
    const taxSymbolAddon = document.getElementById('v_tax_symbol_addon');
    const taxHelpText = document.getElementById('v_tax_help_text');
    const amountInput = document.getElementById('v_pay_amount');

    function updateViewCalculations() {
        if (isLocked) return;

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

        let finalTotal = Math.max(0, Math.round((subtotal - discount + tax) * 100) / 100);
        let remainingBalance = Math.max(0, Math.round((finalTotal - previouslyPaid) * 100) / 100);

        document.getElementById('v_live_calc_tax').textContent = 'Rs. ' + tax.toFixed(2);
        document.getElementById('v_live_final_total').textContent = 'Rs. ' + finalTotal.toFixed(2);
        document.getElementById('v_live_remaining_balance').textContent = 'Rs. ' + remainingBalance.toFixed(2);

        amountInput.max = remainingBalance.toFixed(2);
        amountInput.value = remainingBalance.toFixed(2);
    }

    if (!isLocked) {
        discountEl.addEventListener('input', updateViewCalculations);
        taxTypeEl.addEventListener('change', updateViewCalculations);
        taxInputEl.addEventListener('input', updateViewCalculations);
    }
});
</script>
<?php } ?>

<?php
$thermal_items = [];
foreach ($order_items_list as $it) {
    $thermal_items[] = [
        'name' => $it['menu_item_name'],
        'quantity' => $it['quantity'],
        'unit_price' => $it['unit_price'],
        'subtotal' => $it['subtotal']
    ];
}
$thermal_tax_label = 'Tax / Surcharge';
if ((float)$invoice["tax"] > 0 || (float)$invoice["tax_input"] > 0) {
    $thermal_tax_label .= (($invoice["tax_type"] ?? "") === "percentage" && isset($invoice["tax_input"]))
        ? " (" . rtrim(rtrim(number_format((float)$invoice["tax_input"], 2), '0'), '.') . "%)"
        : " (Fixed)";
}
$thermal_receipt = [
    'mode' => 'invoice',
    'title' => 'Official Dining Receipt & Tax Invoice',
    'doc_number' => $display_invoice_number,
    'doc_date' => formatDateTime($invoice["created_at"]),
    'order_ref' => '#ORD-' . (int)$invoice["order_id"],
    'customer_name' => $invoice["customer_name"] ?? 'Walk-in Guest',
    'customer_phone' => $invoice["customer_phone"] ?? '',
    'order_type' => ucwords(str_replace('_', ' ', $invoice["order_type"])),
    'table_number' => $invoice["table_number"] ?? '',
    'waiter_name' => $invoice["waiter_name"] ?? '',
    'payment_status_label' => $is_fully_paid ? 'Paid in Full' : ($is_partial ? 'Partially Paid' : 'Unpaid'),
    'items' => $thermal_items,
    'subtotal' => (float)$invoice["subtotal"],
    'discount' => (float)$invoice["discount"],
    'tax' => (float)$invoice["tax"],
    'tax_label' => $thermal_tax_label,
    'grand_total' => $grand_total,
    'show_payments' => true,
    'total_paid' => $total_paid,
    'balance_due' => $balance_due,
    'payments' => $payments
];
require_once "../includes/thermal-receipt.php";
?>

<?php
require_once "../includes/footer.php";
?>
