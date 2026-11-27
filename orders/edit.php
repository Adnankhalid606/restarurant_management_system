<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once "../includes/helpers.php";

requireRole(["admin", "waiter"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


// Get order

$sql = "SELECT *
        FROM orders
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


// Check ownership

if ($_SESSION["role"] === "waiter") {

    if ((int) $order["waiter_id"] !== (int) $_SESSION["user_id"]) {
        $_SESSION["error"] = "Access denied to requested order.";
        header("Location: index.php");
        exit;
    }
}


// Get customers

$customers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM customers
     ORDER BY name ASC"
);


// Get tables

$tables = mysqli_query(
    $conn,
    "SELECT id, table_number
     FROM restaurant_tables
     ORDER BY table_number ASC"
);


// Get waiters

$waiters = mysqli_query(
    $conn,
    "SELECT id, name
     FROM users
     WHERE role = 'waiter'
     AND is_active = 1
     ORDER BY name ASC"
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $customer_id = !empty($_POST["customer_id"])
        ? (int) $_POST["customer_id"]
        : null;

    $table_id = !empty($_POST["table_id"])
        ? (int) $_POST["table_id"]
        : null;

    $order_type = $_POST["order_type"] ?? "";
    $status = $_POST["status"] ?? "";
    


    // Set waiter

    if ($_SESSION["role"] === "waiter") {

        $waiter_id = (int) $_SESSION["user_id"];

    } else {

        $waiter_id = !empty($_POST["waiter_id"])
            ? (int) $_POST["waiter_id"]
            : 0;

        if ($waiter_id <= 0) {
            $_SESSION["error"] = "Please select a waiter.";
            header("Location: edit.php?id=" . $id);
            exit;
        }
    }


    // Check waiter

    $sql = "SELECT id
            FROM users
            WHERE id = ?
            AND role = 'waiter'
            AND is_active = 1";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("orders/edit.php prepare waiter check failed: " . mysqli_error($conn));
        $_SESSION["error"] = "A database error occurred while validating the waiter.";
        header("Location: edit.php?id=" . $id);
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $waiter_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        error_log("orders/edit.php execute waiter check failed: " . mysqli_stmt_error($stmt));
        $_SESSION["error"] = "A database error occurred while validating the waiter.";
        header("Location: edit.php?id=" . $id);
        exit;
    }

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        $_SESSION["error"] = "Invalid waiter.";
        header("Location: edit.php?id=" . $id);
        exit;
    }


    // Validate order type

    $allowed_order_types = [
        "dine_in",
        "delivery",
        "pickup"
    ];

    if (!in_array($order_type, $allowed_order_types, true)) {
        $_SESSION["error"] = "Invalid order type.";
        header("Location: edit.php?id=" . $id);
        exit;
    }


    // Validate status
    $allowed_statuses = [
        "pending",
        "preparing",
        "ready",
        "completed",
        "cancelled"
    ];

    if (!in_array($status, $allowed_statuses, true)) {
        $_SESSION["error"] = "Invalid status.";
        header("Location: edit.php?id=" . $id);
        exit;
    }

    // Remove table if needed
    if ($order_type !== "dine_in") {
        $table_id = null;
    }

    mysqli_begin_transaction($conn);

try {
    // 1. Lock and re-fetch authoritative order state inside transaction
    $ord_lock_stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id = ? FOR UPDATE");
    if (!$ord_lock_stmt) {
        error_log("orders/edit.php prepare order lock failed: " . mysqli_error($conn));
        throw new Exception("A database error occurred while loading the order.");
    }
    mysqli_stmt_bind_param($ord_lock_stmt, "i", $id);
    if (!mysqli_stmt_execute($ord_lock_stmt)) {
        error_log("orders/edit.php execute order lock failed: " . mysqli_stmt_error($ord_lock_stmt));
        throw new Exception("A database error occurred while loading the order.");
    }
    $locked_order = mysqli_fetch_assoc(mysqli_stmt_get_result($ord_lock_stmt));
    mysqli_stmt_close($ord_lock_stmt);

    if (!$locked_order) {
        throw new Exception("Record not found or invalid identifier.");
    }

    if ($_SESSION["role"] === "waiter" && (int) $locked_order["waiter_id"] !== (int) $_SESSION["user_id"]) {
        throw new Exception("Access denied to requested order.");
    }

    // 2. Terminal state safeguards against locked row (completed/cancelled orders are strictly read-only)
    if ($locked_order["status"] === "completed") {
        throw new Exception("Security Notice: Completed orders are finalized and read-only. No fields can be modified.");
    }

    if ($locked_order["status"] === "cancelled") {
        throw new Exception("Security Notice: Cancelled orders are closed and read-only. No fields can be modified.");
    }

    // 3. Lock associated bill and check invoice_payments while lock is held
    $linked_bill = null;
    $payment_count = 0;
    $total_paid = 0.0;

    $bill_stmt = mysqli_prepare($conn, "SELECT id, invoice_number, payment_status, total_amount FROM bills WHERE order_id = ? FOR UPDATE");
    if (!$bill_stmt) {
        error_log("orders/edit.php prepare bill lock failed: " . mysqli_error($conn));
        throw new Exception("A database error occurred while checking the associated invoice.");
    }
    mysqli_stmt_bind_param($bill_stmt, "i", $id);
    if (!mysqli_stmt_execute($bill_stmt)) {
        error_log("orders/edit.php execute bill lock failed: " . mysqli_stmt_error($bill_stmt));
        throw new Exception("A database error occurred while checking the associated invoice.");
    }
    $bill_res = mysqli_stmt_get_result($bill_stmt);
    if ($bill_res && $b_row = mysqli_fetch_assoc($bill_res)) {
        $linked_bill = $b_row;
        $pay_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS total_paid FROM invoice_payments WHERE bill_id = ?");
        if (!$pay_stmt) {
            error_log("orders/edit.php prepare payment check failed: " . mysqli_error($conn));
            throw new Exception("A database error occurred while checking invoice payments.");
        }
        mysqli_stmt_bind_param($pay_stmt, "i", $linked_bill['id']);
        if (!mysqli_stmt_execute($pay_stmt)) {
            error_log("orders/edit.php execute payment check failed: " . mysqli_stmt_error($pay_stmt));
            throw new Exception("A database error occurred while checking invoice payments.");
        }
        $pay_res = mysqli_stmt_get_result($pay_stmt);
        if ($pay_res && $pay_row = mysqli_fetch_assoc($pay_res)) {
            $payment_count = (int) ($pay_row['payment_count'] ?? 0);
            $total_paid = (float) ($pay_row['total_paid'] ?? 0);
        }
        mysqli_stmt_close($pay_stmt);
    }
    mysqli_stmt_close($bill_stmt);

    $inv_label = ($linked_bill && !empty($linked_bill['invoice_number'])) ? $linked_bill['invoice_number'] : ($linked_bill ? ('Bill #' . $linked_bill['id']) : 'Invoice');

    // 4. Integrity Safeguard: An order with recorded payments cannot be cancelled
    if ($status === "cancelled") {
        if ($payment_count > 0 || $total_paid > 0 || ($linked_bill && $linked_bill["payment_status"] === "paid") || $locked_order["payment_status"] === "paid") {
            throw new Exception("Security Notice: Cannot cancel Order #$id because recorded payments exist on $inv_label. A payment reversal or refund must be processed through an authorized accounting workflow before cancellation.");
        }
    }

    // 5. Integrity Safeguard: An order in ready status with recorded payments cannot be demoted to pending or preparing
    if ($locked_order["status"] === "ready" && in_array($status, ["pending", "preparing"], true)) {
        if ($payment_count > 0 || $total_paid > 0 || ($linked_bill && $linked_bill["payment_status"] === "paid")) {
            throw new Exception("Security Notice: Cannot revert Order #$id to " . ucfirst($status) . " because recorded payments exist on $inv_label. A payment reversal or refund must be processed through an authorized accounting workflow before modifying status.");
        }
    }

    $old_table_id = !empty($locked_order["table_id"])
        ? (int) $locked_order["table_id"]
        : null;
    $new_table_id = $table_id;

    if ($status === "cancelled") {

        // Release old table if assigned
        if ($old_table_id !== null) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("orders/edit.php prepare release table failed: " . mysqli_error($conn));
                throw new Exception("A database error occurred while updating table availability.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $old_table_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                error_log("orders/edit.php execute release table failed: " . mysqli_stmt_error($stmt));
                throw new Exception("A database error occurred while updating table availability.");
            }
        }

        // If an unpaid draft bill exists with 0 payments, safely delete it
        if ($linked_bill && $payment_count === 0) {
            $del_stmt = mysqli_prepare($conn, "DELETE FROM bills WHERE id = ?");
            if (!$del_stmt) {
                error_log("orders/edit.php prepare delete bill failed: " . mysqli_error($conn));
                throw new Exception("A database error occurred while removing the uncollected draft invoice.");
            }
            mysqli_stmt_bind_param($del_stmt, "i", $linked_bill['id']);
            if (!mysqli_stmt_execute($del_stmt)) {
                error_log("orders/edit.php execute delete bill failed: " . mysqli_stmt_error($del_stmt));
                throw new Exception("A database error occurred while removing the uncollected draft invoice.");
            }
            mysqli_stmt_close($del_stmt);
        }

    } else {

        // If demoting from ready to pending/preparing with 0 payments, delete draft bill
        if ($locked_order["status"] === "ready" && in_array($status, ["pending", "preparing"], true) && $linked_bill && $payment_count === 0) {
            $del_stmt = mysqli_prepare($conn, "DELETE FROM bills WHERE id = ?");
            if (!$del_stmt) {
                error_log("orders/edit.php prepare delete draft bill failed: " . mysqli_error($conn));
                throw new Exception("A database error occurred while removing the uncollected draft invoice.");
            }
            mysqli_stmt_bind_param($del_stmt, "i", $linked_bill['id']);
            if (!mysqli_stmt_execute($del_stmt)) {
                error_log("orders/edit.php execute delete draft bill failed: " . mysqli_stmt_error($del_stmt));
                throw new Exception("A database error occurred while removing the uncollected draft invoice.");
            }
            mysqli_stmt_close($del_stmt);
        }

        // Check new table

        if (
            $new_table_id !== null &&
            $new_table_id !== $old_table_id
        ) {

            $sql = "SELECT status
                    FROM restaurant_tables
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("orders/edit.php prepare table check failed: " . mysqli_error($conn));
                throw new Exception("A database error occurred while checking table availability.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $new_table_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                error_log("orders/edit.php execute table check failed: " . mysqli_stmt_error($stmt));
                throw new Exception("A database error occurred while checking table availability.");
            }

            $result = mysqli_stmt_get_result($stmt);

            $new_table = mysqli_fetch_assoc($result);

            if (!$new_table) {
                throw new Exception("Table not found.");
            }

            if ($new_table["status"] !== "available") {
                throw new Exception("Selected table is not available.");
            }
        }


        // Release old table

        if (
            $old_table_id !== null &&
            $old_table_id !== $new_table_id
        ) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("orders/edit.php prepare release old table failed: " . mysqli_error($conn));
                throw new Exception("A database error occurred while releasing the previous table.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $old_table_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                error_log("orders/edit.php execute release old table failed: " . mysqli_stmt_error($stmt));
                throw new Exception("A database error occurred while releasing the previous table.");
            }
        }


        // Occupy new table

        if (
            $new_table_id !== null &&
            $new_table_id !== $old_table_id
        ) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'occupied'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                error_log("orders/edit.php prepare occupy new table failed: " . mysqli_error($conn));
                throw new Exception("A database error occurred while occupying the selected table.");
            }

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $new_table_id
            );

            if (!mysqli_stmt_execute($stmt)) {
                error_log("orders/edit.php execute occupy new table failed: " . mysqli_stmt_error($stmt));
                throw new Exception("A database error occurred while occupying the selected table.");
            }
        }
    }


    // Update order

    $sql = "UPDATE orders
            SET customer_id = ?,
                table_id = ?,
                waiter_id = ?,
                order_type = ?,
                status = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log("orders/edit.php prepare update order failed: " . mysqli_error($conn));
        throw new Exception("A database error occurred while updating the order.");
    }

    mysqli_stmt_bind_param(
        $stmt,
        "iiissi",
        $customer_id,
        $table_id,
        $waiter_id,
        $order_type,
        $status,
        $id
    );

    if (!mysqli_stmt_execute($stmt)) {
        error_log("orders/edit.php execute update order failed: " . mysqli_stmt_error($stmt));
        throw new Exception("A database error occurred while updating the order.");
    }

    // Automatically generate customer invoice if order transitioned to ready or completed
    if ($status === 'ready' || $status === 'completed') {
        ensureOrderInvoice($conn, $id);
    }

    mysqli_commit($conn);

    header("Location: view.php?id=" . $id);
    exit;

} catch (Exception $error) {

    mysqli_rollback($conn);
    $_SESSION["error"] = $error->getMessage();
    header("Location: edit.php?id=" . $id);
    exit;
}
}

$page_title = "Edit Order #" . $id;
$active_menu = "orders";
$is_terminal_readonly = in_array($order["status"], ["completed", "cancelled"], true);

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Edit Order #<?php echo $id; ?></h2>
        <p class="page-header-subtitle">
            Update customer, service type, dining table, or assigned waiter
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Order Ticket</span>
        </a>
    </div>
</div>

<div class="alert alert-light border py-2 px-3 mb-4 small text-muted d-flex align-items-center gap-2">
    <i class="bi bi-shield-check text-primary fs-5"></i>
    <div>
        Order items and total pricing (Rs. <?php echo number_format($order["total_amount"], 2); ?>) are locked for audit integrity. Use this screen to update seating, service type, or workflow status.
    </div>
</div>

<?php if (isset($_SESSION["error"])) { ?>
    <div class="alert alert-danger border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger"></i>
        <div><?php echo htmlspecialchars($_SESSION["error"], ENT_QUOTES, 'UTF-8'); unset($_SESSION["error"]); ?></div>
    </div>
<?php } ?>

<?php if ($is_terminal_readonly) { ?>
    <div class="alert alert-warning border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-lock-fill fs-5 flex-shrink-0 text-warning"></i>
        <div>
            <strong>Read-Only Order:</strong> This order is <strong><?php echo htmlspecialchars(ucfirst($order["status"]), ENT_QUOTES, 'UTF-8'); ?></strong> and cannot be edited. Customer, service type, dining table, and waiter details are locked.
        </div>
    </div>
<?php } ?>

<form method="POST">
    <?php echo csrf_field(); ?>
    <div class="row g-4">
        <!-- Left: Service & Seating -->
        <div class="col-12 col-lg-7">
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-gear me-2 text-primary"></i>Service &amp; Seating Details</span>
                </div>
                <div class="pos-card-body">
                    <!-- Customer -->
                    <div class="mb-3">
                        <label for="customerId" class="form-label pos-form-label">Customer</label>
                        <select name="customer_id" id="customerId" class="form-select pos-form-control" <?php if ($is_terminal_readonly) echo "disabled"; ?>>
                            <option value="">Walk-in Customer</option>
                            <?php mysqli_data_seek($customers, 0); ?>
                            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>
                                <option value="<?php echo $customer["id"]; ?>" <?php if ($customer["id"] == $order["customer_id"]) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($customer["name"], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Order Type -->
                    <div class="mb-3">
                        <label for="orderType" class="form-label pos-form-label">Order Type <span class="text-danger">*</span></label>
                        <select name="order_type" id="orderType" class="form-select pos-form-control" <?php echo $is_terminal_readonly ? "disabled" : "required"; ?>>
                            <option value="dine_in" <?php if ($order["order_type"] === "dine_in") echo "selected"; ?>>
                                Dine In
                            </option>
                            <option value="delivery" <?php if ($order["order_type"] === "delivery") echo "selected"; ?>>
                                Delivery
                            </option>
                            <option value="pickup" <?php if ($order["order_type"] === "pickup") echo "selected"; ?>>
                                Pickup
                            </option>
                        </select>
                    </div>

                    <!-- Dining Table -->
                    <div id="tableSection" class="mb-3" style="<?php echo ($order["order_type"] === "dine_in") ? "" : "display: none;"; ?>">
                        <label for="tableId" class="form-label pos-form-label">Dining Table</label>
                        <select name="table_id" id="tableId" class="form-select pos-form-control" <?php if ($is_terminal_readonly) echo "disabled"; ?>>
                            <option value="">No Table Assigned</option>
                            <?php mysqli_data_seek($tables, 0); ?>
                            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>
                                <option value="<?php echo $table["id"]; ?>" <?php if ($table["id"] == $order["table_id"]) echo "selected"; ?>>
                                    Table <?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <small class="text-muted d-block mt-1">Changing table automatically releases the previous table.</small>
                    </div>

                    <!-- Waiter Assignment -->
                    <div class="mb-2">
                        <?php if ($_SESSION["role"] === "admin") { ?>
                            <label for="waiterId" class="form-label pos-form-label">Assigned Waiter <span class="text-danger">*</span></label>
                            <select name="waiter_id" id="waiterId" class="form-select pos-form-control" <?php echo $is_terminal_readonly ? "disabled" : "required"; ?>>
                                <option value="">Select Waiter</option>
                                <?php mysqli_data_seek($waiters, 0); ?>
                                <?php while ($waiter = mysqli_fetch_assoc($waiters)) { ?>
                                    <option value="<?php echo $waiter["id"]; ?>" <?php if ($waiter["id"] == $order["waiter_id"]) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($waiter["name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        <?php } else { ?>
                            <label class="form-label pos-form-label">Assigned Waiter</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control pos-form-control bg-light border-start-0" value="<?php echo htmlspecialchars($_SESSION["user_name"], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Read-Only Order Summary & Actions -->
        <div class="col-12 col-lg-5">
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-receipt me-2 text-primary"></i>Order Summary</span>
                </div>
                <div class="pos-card-body">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Readonly Order Summary Card -->
                    <div class="order-summary-box mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Order Status:</span>
                            <span class="badge-subtle badge-status-<?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?> text-capitalize">
                                <?php echo htmlspecialchars($order["status"], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Payment Status:</span>
                            <span class="badge-subtle badge-status-<?php echo htmlspecialchars($order['payment_status'], ENT_QUOTES, 'UTF-8'); ?> text-capitalize">
                                <?php echo htmlspecialchars($order["payment_status"], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Placed Date:</span>
                            <span class="text-dark small fw-medium">
                                <?php echo formatDateTime($order["created_at"]); ?>
                            </span>
                        </div>
                        <hr class="my-2 border-secondary-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">Order Total:</span>
                            <span class="fs-5 fw-bold text-primary">Rs. <?php echo number_format($order["total_amount"], 2); ?></span>
                        </div>
                    </div>

                    <small class="text-muted d-block mb-3">
                        <i class="bi bi-info-circle me-1"></i>Workflow status progression and cancellations are managed from Kitchen Display.
                    </small>

                    <?php if (!$is_terminal_readonly) { ?>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm mb-2">
                            <i class="bi bi-save"></i>
                            <span>Update Order</span>
                        </button>
                    <?php } ?>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary w-100 py-2">
                        <?php echo $is_terminal_readonly ? "Back to Order Ticket" : "Cancel"; ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const orderType = document.getElementById("orderType");
    const tableSection = document.getElementById("tableSection");
    const tableSelect = document.getElementById("tableId");

    if (orderType && tableSection) {
        orderType.addEventListener("change", function () {
            if (this.value === "dine_in") {
                tableSection.style.display = "block";
            } else {
                tableSection.style.display = "none";
                if (tableSelect) tableSelect.value = "";
            }
        });
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>