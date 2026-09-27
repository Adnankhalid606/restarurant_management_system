<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;

if ($id <= 0) {
    die("Invalid order ID.");
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
    die("Order not found.");
}


// Check ownership

if ($_SESSION["role"] === "waiter") {

    if ((int) $order["waiter_id"] !== (int) $_SESSION["user_id"]) {
        die("Access denied.");
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
            die("Please select a waiter.");
        }
    }


    // Check waiter

    $sql = "SELECT id
            FROM users
            WHERE id = ?
            AND role = 'waiter'
            AND is_active = 1";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $waiter_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) === 0) {
        die("Invalid waiter.");
    }


    // Validate order type

    $allowed_order_types = [
        "dine_in",
        "delivery",
        "pickup"
    ];

    if (!in_array($order_type, $allowed_order_types)) {
        die("Invalid order type.");
    }


    // Validate status

    $allowed_statuses = [
        "pending",
        "preparing",
        "ready",
        "cancelled"
    ];

    if (!in_array($status, $allowed_statuses)) {
        die("Invalid status.");
    }



    // Remove table if needed

    if ($order_type !== "dine_in") {
        $table_id = null;
    }

    // Get old table

$old_table_id = !empty($order["table_id"])
    ? (int) $order["table_id"]
    : null;

$new_table_id = $table_id;







    mysqli_begin_transaction($conn);

try {

    if ($status === "cancelled") {

        // Release old table if assigned
        if ($old_table_id !== null) {

            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $old_table_id
            );

            mysqli_stmt_execute($stmt);
        }

    } else {

        // Check new table

        if (
            $new_table_id !== null &&
            $new_table_id !== $old_table_id
        ) {

            $sql = "SELECT status
                    FROM restaurant_tables
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $new_table_id
            );

            mysqli_stmt_execute($stmt);

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

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $old_table_id
            );

            mysqli_stmt_execute($stmt);
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

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $new_table_id
            );

            mysqli_stmt_execute($stmt);
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

    mysqli_stmt_execute($stmt);


    mysqli_commit($conn);

    header("Location: view.php?id=" . $id);
    exit;

} catch (Exception $error) {

    mysqli_rollback($conn);

    die($error->getMessage());
}
}

$page_title = "Edit Order #" . $id;
$active_menu = "orders";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Edit Order #<?php echo $id; ?></h2>
        <p class="page-header-subtitle">
            Update service type, dining table, or status progression
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

<form method="POST">
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
                        <select name="customer_id" id="customerId" class="form-select pos-form-control">
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
                        <select name="order_type" id="orderType" class="form-select pos-form-control" required>
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
                        <select name="table_id" id="tableId" class="form-select pos-form-control">
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
                            <select name="waiter_id" id="waiterId" class="form-select pos-form-control" required>
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

        <!-- Right: Status Progression & Actions -->
        <div class="col-12 col-lg-5">
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-clock-history me-2 text-primary"></i>Status &amp; Workflow</span>
                </div>
                <div class="pos-card-body">
                    <!-- Status -->
                    <div class="mb-3">
                        <label for="orderStatus" class="form-label pos-form-label">Order Status <span class="text-danger">*</span></label>
                        <select name="status" id="orderStatus" class="form-select pos-form-control" required>
                            <option value="pending" <?php if ($order["status"] === "pending") echo "selected"; ?>>
                                Pending
                            </option>
                            <option value="preparing" <?php if ($order["status"] === "preparing") echo "selected"; ?>>
                                Preparing
                            </option>
                            <option value="ready" <?php if ($order["status"] === "ready") echo "selected"; ?>>
                                Ready
                            </option>
                            <option value="cancelled" <?php if ($order["status"] === "cancelled") echo "selected"; ?>>
                                Cancelled
                            </option>
                        </select>
                        <small class="text-muted d-block mt-1">Cancelling an order automatically releases any occupied dining table.</small>
                    </div>

                    <!-- Readonly Order Summary Card -->
                    <div class="order-summary-box mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="text-muted small">Payment Status:</span>
                            <span class="badge-subtle badge-status-<?php echo $order['payment_status']; ?> text-capitalize">
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

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm mb-2">
                        <i class="bi bi-save"></i>
                        <span>Update Order</span>
                    </button>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary w-100 py-2">
                        Cancel
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