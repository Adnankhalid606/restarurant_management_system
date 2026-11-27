<?php
/**
 * Shared 80mm Thermal Receipt Partial
 * Used by invoices/view.php and orders/view.php.
 * Expects $thermal_receipt associative array.
 */
$tr = $thermal_receipt ?? [];
$tr_mode = $tr['mode'] ?? 'invoice';
$tr_title = $tr['title'] ?? 'Official Dining Receipt';
$tr_doc_number = $tr['doc_number'] ?? '-';
$tr_doc_date = $tr['doc_date'] ?? '-';
$tr_order_ref = $tr['order_ref'] ?? '-';
$tr_customer_name = $tr['customer_name'] ?? 'Walk-in Guest';
$tr_customer_phone = $tr['customer_phone'] ?? '';
$tr_order_type = $tr['order_type'] ?? '-';
$tr_table_number = $tr['table_number'] ?? '';
$tr_waiter_name = $tr['waiter_name'] ?? '';
$tr_order_status = $tr['order_status'] ?? '';
$tr_payment_status_label = $tr['payment_status_label'] ?? '';
$tr_items = $tr['items'] ?? [];
$tr_subtotal = (float) ($tr['subtotal'] ?? 0);
$tr_discount = (float) ($tr['discount'] ?? 0);
$tr_tax = (float) ($tr['tax'] ?? 0);
$tr_tax_label = $tr['tax_label'] ?? 'Tax / Surcharge';
$tr_grand_total = (float) ($tr['grand_total'] ?? 0);
$tr_show_payments = !empty($tr['show_payments']);
$tr_total_paid = (float) ($tr['total_paid'] ?? 0);
$tr_balance_due = (float) ($tr['balance_due'] ?? 0);
$tr_payments = $tr['payments'] ?? [];
?>

<style>
#thermalReceiptSheet {
    display: none;
}

@media print {
    body.thermal-print-mode,
    body.thermal-print-mode .app-wrapper,
    body.thermal-print-mode .app-main,
    body.thermal-print-mode .app-content {
        background: #ffffff !important;
        color: #000000 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        min-height: 0 !important;
        box-shadow: none !important;
    }

    body.thermal-print-mode .app-sidebar,
    body.thermal-print-mode .app-topbar,
    body.thermal-print-mode .app-footer,
    body.thermal-print-mode .sidebar-backdrop,
    body.thermal-print-mode .d-print-none,
    body.thermal-print-mode .print-invoice-sheet,
    body.thermal-print-mode .modal,
    body.thermal-print-mode .modal-backdrop {
        display: none !important;
    }

    body.thermal-print-mode #thermalReceiptSheet {
        display: block !important;
        width: 74mm;
        max-width: 100%;
        margin: 0 auto;
        padding: 2mm 1mm;
        background: #ffffff !important;
        color: #000000 !important;
        font-family: "Courier New", Consolas, "Liberation Mono", monospace;
        font-size: 8.5pt;
        line-height: 1.32;
        box-sizing: border-box;
    }

    .thermal-header {
        text-align: center;
        padding-bottom: 6px;
        border-bottom: 1px dashed #000000;
        margin-bottom: 6px;
        break-inside: avoid;
    }

    .thermal-brand {
        font-size: 11pt;
        font-weight: 700;
        color: #000000;
        margin: 0 0 2px 0;
    }

    .thermal-subtitle {
        font-size: 8pt;
        color: #000000;
        margin: 0 0 3px 0;
    }

    .thermal-doc-id {
        font-size: 9.5pt;
        font-weight: 700;
        color: #000000;
        margin: 0;
    }

    .thermal-meta {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px dashed #000000;
        break-inside: avoid;
    }

    .thermal-meta td {
        font-size: 8pt;
        padding: 1px 0;
        color: #000000;
        vertical-align: top;
    }

    .thermal-meta-label {
        font-weight: 700;
        white-space: nowrap;
        padding-right: 6px;
    }

    .thermal-meta-val {
        text-align: right;
        word-break: break-word;
    }

    .thermal-items {
        width: 100%;
        border-collapse: collapse;
        table-layout: fixed;
        margin-bottom: 6px;
    }

    .thermal-items thead {
        display: table-header-group;
    }

    .thermal-items tr {
        break-inside: avoid;
    }

    .thermal-items th {
        font-size: 7.5pt;
        font-weight: 700;
        text-transform: uppercase;
        color: #000000;
        border-top: 1px solid #000000;
        border-bottom: 1px solid #000000;
        padding: 3px 2px;
    }

    .thermal-items td {
        font-size: 8pt;
        color: #000000;
        padding: 3px 2px;
        vertical-align: top;
        border-bottom: 1px dotted #000000;
    }

    .thermal-item-name {
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
    }

    .thermal-totals {
        width: 100%;
        border-collapse: collapse;
        border-top: 1px solid #000000;
        margin-bottom: 6px;
        break-inside: avoid;
    }

    .thermal-totals td {
        font-size: 8.5pt;
        padding: 2px 0;
        color: #000000;
    }

    .thermal-totals-grand td {
        font-size: 10pt;
        font-weight: 700;
        border-top: 1px dashed #000000;
        border-bottom: 1px dashed #000000;
        padding: 4px 0;
    }

    .thermal-payments {
        border-top: 1px dashed #000000;
        padding-top: 5px;
        margin-bottom: 6px;
        break-inside: avoid;
    }

    .thermal-section-title {
        font-size: 8pt;
        font-weight: 700;
        text-transform: uppercase;
        margin: 0 0 3px 0;
        color: #000000;
    }

    .thermal-payment-row {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 3px;
    }

    .thermal-payment-row td {
        font-size: 7.5pt;
        color: #000000;
        padding: 1px 0;
    }

    .thermal-footer {
        border-top: 1px dashed #000000;
        padding-top: 6px;
        text-align: center;
        font-size: 8pt;
        color: #000000;
        break-inside: avoid;
    }
}
</style>

<div id="thermalReceiptSheet" aria-hidden="true">
    <div class="thermal-header">
        <div class="thermal-brand">RestoBar POS &amp; Restaurant</div>
        <div class="thermal-subtitle"><?php echo htmlspecialchars($tr_title, ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="thermal-doc-id"><?php echo htmlspecialchars($tr_doc_number, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <table class="thermal-meta">
        <tr>
            <td class="thermal-meta-label">Date:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars($tr_doc_date, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php if ($tr_mode === 'invoice') { ?>
        <tr>
            <td class="thermal-meta-label">Order Ref:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars($tr_order_ref, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php } ?>
        <tr>
            <td class="thermal-meta-label">Customer:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars($tr_customer_name, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php if (!empty($tr_customer_phone)) { ?>
        <tr>
            <td class="thermal-meta-label">Phone:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars($tr_customer_phone, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php } ?>
        <tr>
            <td class="thermal-meta-label">Order Type:</td>
            <td class="thermal-meta-val">
                <?php echo htmlspecialchars($tr_order_type, ENT_QUOTES, 'UTF-8'); ?>
                <?php if (!empty($tr_table_number)) { ?>
                    (Table <?php echo htmlspecialchars($tr_table_number, ENT_QUOTES, 'UTF-8'); ?>)
                <?php } ?>
            </td>
        </tr>
        <?php if (!empty($tr_waiter_name)) { ?>
        <tr>
            <td class="thermal-meta-label">Waiter:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars($tr_waiter_name, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php } ?>
        <?php if (!empty($tr_order_status)) { ?>
        <tr>
            <td class="thermal-meta-label">Order Status:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars(ucfirst($tr_order_status), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php } ?>
        <?php if (!empty($tr_payment_status_label)) { ?>
        <tr>
            <td class="thermal-meta-label">Payment:</td>
            <td class="thermal-meta-val"><?php echo htmlspecialchars($tr_payment_status_label, ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php } ?>
    </table>

    <table class="thermal-items">
        <thead>
            <tr>
                <th style="width: 44%; text-align: left;">Item</th>
                <th style="width: 12%; text-align: right;">Qty</th>
                <th style="width: 21%; text-align: right;">Rate</th>
                <th style="width: 23%; text-align: right;">Amt</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($tr_items)) {
                foreach ($tr_items as $ti) { ?>
                <tr>
                    <td class="thermal-item-name"><?php echo htmlspecialchars($ti['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td style="text-align: right;"><?php echo (int)$ti['quantity']; ?></td>
                    <td style="text-align: right;"><?php echo number_format((float)$ti['unit_price'], 2); ?></td>
                    <td style="text-align: right;"><?php echo number_format((float)$ti['subtotal'], 2); ?></td>
                </tr>
            <?php }
            } else { ?>
                <tr>
                    <td colspan="4" style="text-align: center;">Direct order (Subtotal: Rs. <?php echo number_format($tr_subtotal, 2); ?>)</td>
                </tr>
            <?php } ?>
        </tbody>
    </table>

    <table class="thermal-totals">
        <tr>
            <td>Subtotal</td>
            <td style="text-align: right;">Rs. <?php echo number_format($tr_subtotal, 2); ?></td>
        </tr>
        <?php if ($tr_discount > 0) { ?>
        <tr>
            <td>Discount</td>
            <td style="text-align: right;">- Rs. <?php echo number_format($tr_discount, 2); ?></td>
        </tr>
        <?php } ?>
        <?php if ($tr_tax > 0) { ?>
        <tr>
            <td><?php echo htmlspecialchars($tr_tax_label, ENT_QUOTES, 'UTF-8'); ?></td>
            <td style="text-align: right;">+ Rs. <?php echo number_format($tr_tax, 2); ?></td>
        </tr>
        <?php } ?>
        <tr class="thermal-totals-grand">
            <td>TOTAL</td>
            <td style="text-align: right;">Rs. <?php echo number_format($tr_grand_total, 2); ?></td>
        </tr>
        <?php if ($tr_show_payments) { ?>
        <tr>
            <td>Total Paid</td>
            <td style="text-align: right;">Rs. <?php echo number_format($tr_total_paid, 2); ?></td>
        </tr>
        <tr>
            <td><strong>Balance Due</strong></td>
            <td style="text-align: right;"><strong>Rs. <?php echo number_format($tr_balance_due, 2); ?></strong></td>
        </tr>
        <?php } ?>
    </table>

    <?php if ($tr_show_payments && !empty($tr_payments)) { ?>
    <div class="thermal-payments">
        <div class="thermal-section-title">Payment History</div>
        <?php foreach ($tr_payments as $tp) { ?>
        <table class="thermal-payment-row">
            <tr>
                <td><?php echo htmlspecialchars(formatDateTime($tp['payment_date']), ENT_QUOTES, 'UTF-8'); ?></td>
                <td style="text-align: right; font-weight: 700;">Rs. <?php echo number_format((float)$tp['amount'], 2); ?></td>
            </tr>
            <tr>
                <td colspan="2">
                    <?php echo htmlspecialchars($tp['payment_method_name'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($tp['transaction_ref'])) { ?>
                        (Ref: <?php echo htmlspecialchars($tp['transaction_ref'], ENT_QUOTES, 'UTF-8'); ?>)
                    <?php } ?>
                </td>
            </tr>
        </table>
        <?php } ?>
    </div>
    <?php } ?>

    <div class="thermal-footer">
        <div style="font-weight: 700;">Thank you for visiting!</div>
        <div>RestoBar POS &amp; Restaurant</div>
    </div>
</div>

<script>
function triggerThermalPrint() {
    const existingRule = document.getElementById('thermalPageRule');
    if (existingRule) existingRule.remove();

    const styleEl = document.createElement('style');
    styleEl.id = 'thermalPageRule';
    styleEl.textContent = '@page { size: 80mm auto; margin: 2mm 3mm; }';
    document.head.appendChild(styleEl);

    document.body.classList.add('thermal-print-mode');

    function cleanupThermalPrint() {
        document.body.classList.remove('thermal-print-mode');
        const rule = document.getElementById('thermalPageRule');
        if (rule) rule.remove();
        window.removeEventListener('afterprint', cleanupThermalPrint);
    }

    window.addEventListener('afterprint', cleanupThermalPrint);
    window.print();
}
</script>
