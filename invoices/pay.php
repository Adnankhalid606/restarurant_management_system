<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    header("Allow: POST");
    echo "Method Not Allowed. Payment settlement requires a POST request.";
    exit;
}

if (!verify_csrf_token()) {
    csrf_failure_response();
}

$id = (int) ($_POST["id"] ?? 0);
$method_id = (int) ($_POST["payment_method_id"] ?? 0);
$amount_raw = trim($_POST["amount"] ?? "0");
$transaction_ref = trim($_POST["transaction_ref"] ?? "");
$notes = trim($_POST["notes"] ?? "");
$return_to = trim($_POST["return_to"] ?? "");
$acting_user_id = (int) ($_SESSION["user_id"] ?? 0);

$redirect_target = ($return_to === "view" && $id > 0) ? "view.php?id=" . $id : "index.php";

if ($id <= 0) {
    $_SESSION["error"] = "Invalid invoice identifier.";
    header("Location: index.php");
    exit;
}

if ($method_id <= 0) {
    $_SESSION["error"] = "Please select a valid payment method.";
    header("Location: " . $redirect_target);
    exit;
}

if (!is_numeric($amount_raw) || (float)$amount_raw <= 0) {
    $_SESSION["error"] = "Payment amount received must be strictly greater than zero.";
    header("Location: " . $redirect_target);
    exit;
}

$payment_amount = round((float) $amount_raw, 2);

// Start database transaction with pessimistic row locking
mysqli_begin_transaction($conn);

try {
    // 1. Lock and fetch invoice/bill record
    $sql = "SELECT id, order_id, invoice_number, subtotal, discount, tax_type, tax_input, tax, total_amount, payment_status
            FROM bills
            WHERE id = ?
            FOR UPDATE";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $bill = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$bill) {
        throw new Exception("Invoice record not found.");
    }

    if ($bill["payment_status"] === "paid") {
        throw new Exception("This invoice is already fully paid and settled.");
    }

    // 2. Lock and check existing payments
    $calc_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS total_paid FROM invoice_payments WHERE bill_id = ? FOR UPDATE");
    mysqli_stmt_bind_param($calc_stmt, "i", $id);
    mysqli_stmt_execute($calc_stmt);
    $pay_stats = mysqli_fetch_assoc(mysqli_stmt_get_result($calc_stmt));

    $payment_count = (int) $pay_stats["payment_count"];
    $current_paid = round((float) $pay_stats["total_paid"], 2);
    $subtotal = round((float) $bill["subtotal"], 2);

    $final_invoice_total = round((float) $bill["total_amount"], 2);

    // 3. First Payment Collection: Apply Discount and Tax/Surcharge adjustments
    if ($payment_count === 0) {
        $discount_raw = trim($_POST["discount"] ?? "0");
        $tax_type = trim($_POST["tax_type"] ?? "percentage");
        $tax_input_raw = trim($_POST["tax_input"] ?? "0");

        if (!is_numeric($discount_raw) || (float)$discount_raw < 0) {
            throw new Exception("Discount amount must be a non-negative number.");
        }
        $discount = round((float)$discount_raw, 2);

        if ($discount > $subtotal) {
            throw new Exception("Discount (Rs. " . number_format($discount, 2) . ") cannot exceed the order subtotal amount of Rs. " . number_format($subtotal, 2) . ".");
        }

        if (!in_array($tax_type, ["manual", "percentage"], true)) {
            $tax_type = "percentage";
        }

        if (!is_numeric($tax_input_raw) || (float)$tax_input_raw < 0) {
            throw new Exception("Tax / Surcharge input must be a non-negative number.");
        }
        $tax_input = round((float)$tax_input_raw, 2);

        // Precise integer arithmetic (in paisa) to prevent floating point inaccuracies
        $max_monetary_paisa = 9999999999;
        $subtotal_paisa = (int) round($subtotal * 100);
        $discount_paisa = (int) round($discount * 100);

        if ($tax_type === "manual") {
            $tax_paisa = (int) round($tax_input * 100);
        } else {
            // Percentage tax calculated on original subtotal before discount
            $tax_input_cents = (int) round($tax_input * 100);
            $S_q = intdiv($subtotal_paisa, 10000);
            $S_r = $subtotal_paisa % 10000;
            $term1 = $S_q * $tax_input_cents;
            $inner_remainder = $S_r * $tax_input_cents + 5000;
            $term2 = intdiv($inner_remainder, 10000);
            $tax_paisa = $term1 + $term2;
        }

        if ($tax_paisa > $max_monetary_paisa) {
            throw new Exception("Calculated Tax / Surcharge exceeds the maximum permitted limit of 99,999,999.99.");
        }

        $total_paisa = ($subtotal_paisa - $discount_paisa) + $tax_paisa;
        if ($total_paisa > $max_monetary_paisa) {
            throw new Exception("Total invoice amount exceeds the maximum permitted limit of 99,999,999.99.");
        }
        if ($total_paisa < 0) {
            throw new Exception("Final invoice total cannot be negative.");
        }

        $tax = round($tax_paisa / 100, 2);
        $final_invoice_total = round($total_paisa / 100, 2);

        // Atomically update bills record with the finalized adjustments and total
        $upd_bill = mysqli_prepare($conn, "UPDATE bills SET discount = ?, tax_type = ?, tax_input = ?, tax = ?, total_amount = ? WHERE id = ?");
        mysqli_stmt_bind_param($upd_bill, "dsdddi", $discount, $tax_type, $tax_input, $tax, $final_invoice_total, $id);
        if (!mysqli_stmt_execute($upd_bill)) {
            throw new Exception("Failed to update invoice adjustments: " . mysqli_error($conn));
        }
    }

    // 4. Validate remaining balance and payment amount
    $remaining_balance = round(max(0, $final_invoice_total - $current_paid), 2);

    if ($payment_amount > $remaining_balance) {
        throw new Exception("Payment amount (Rs. " . number_format($payment_amount, 2) . ") exceeds remaining invoice balance (Rs. " . number_format($remaining_balance, 2) . "). Overpayments are strictly prohibited.");
    }

    // 5. Fetch and validate selected payment method (must be active)
    $pm_stmt = mysqli_prepare($conn, "SELECT id, name, classification, status FROM payment_methods WHERE id = ?");
    mysqli_stmt_bind_param($pm_stmt, "i", $method_id);
    mysqli_stmt_execute($pm_stmt);
    $method = mysqli_fetch_assoc(mysqli_stmt_get_result($pm_stmt));

    if (!$method || $method["status"] !== "active") {
        throw new Exception("Selected payment method is inactive or invalid. Please select an active method.");
    }

    // 6. Record payment in invoice_payments with method name & classification snapshots
    $ins_sql = "INSERT INTO invoice_payments
                (bill_id, payment_method_id, payment_method_name, classification, amount, payment_date, transaction_ref, notes, created_by)
                VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?)";
    $ins_stmt = mysqli_prepare($conn, $ins_sql);
    mysqli_stmt_bind_param(
        $ins_stmt, 
        "iissdssi", 
        $id, 
        $method_id, 
        $method["name"], 
        $method["classification"], 
        $payment_amount, 
        $transaction_ref, 
        $notes, 
        $acting_user_id
    );

    if (!mysqli_stmt_execute($ins_stmt)) {
        throw new Exception("Failed to record invoice payment receipt: " . mysqli_error($conn));
    }

    // 7. Check if invoice is now fully settled
    $new_total_paid = round($current_paid + $payment_amount, 2);
    $is_fully_paid = ($new_total_paid >= $final_invoice_total);

    if ($is_fully_paid) {
        // Mark bill as paid
        $upd_status_bill = mysqli_prepare($conn, "UPDATE bills SET payment_status = 'paid', paid_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd_status_bill, "i", $id);
        mysqli_stmt_execute($upd_status_bill);

        // Mark related order as paid
        $upd_ord = mysqli_prepare($conn, "UPDATE orders SET payment_status = 'paid' WHERE id = ?");
        mysqli_stmt_bind_param($upd_ord, "i", $bill["order_id"]);
        mysqli_stmt_execute($upd_ord);
    }

    mysqli_commit($conn);

    $inv_ref = !empty($bill["invoice_number"]) ? $bill["invoice_number"] : "#INV-" . $id;
    $remaining_after = round(max(0, $final_invoice_total - $new_total_paid), 2);
    
    $_SESSION["flash_success"] = "Payment of Rs. " . number_format($payment_amount, 2) . " via " . htmlspecialchars($method["name"]) . " recorded successfully for " . $inv_ref . ($is_fully_paid ? " (Fully Settled)." : " (Remaining Balance: Rs. " . number_format($remaining_after, 2) . ").");
    header("Location: " . $redirect_target);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION["error"] = $e->getMessage();
    header("Location: " . $redirect_target);
    exit;
}
