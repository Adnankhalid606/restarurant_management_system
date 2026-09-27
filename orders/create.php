<?php

require_once "../includes/role.php";
require_once "../config/database.php";

requireRole(["admin", "waiter"]);

$error = "";


//Fetch all customers for dropdown
$customers = mysqli_query(
    $conn,
    "SELECT id, name, phone
     FROM customers
     ORDER BY name ASC"
);


//fetch tables that are currently available
$tables = mysqli_query(
    $conn,
    "SELECT id, table_number, capacity
     FROM restaurant_tables
     WHERE status = 'available'
     ORDER BY table_number ASC"
);

//All available menu items
$menu_items = mysqli_query(
    $conn,
    "SELECT id, name, price
     FROM menu_items
     WHERE is_available = 1
     ORDER BY name ASC"
);


//All active waiters
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

    $order_type = $_POST["order_type"] ?? "";

    $table_id = !empty($_POST["table_id"])
        ? (int) $_POST["table_id"]
        : null;


    if ($_SESSION["role"] === "waiter") {

        // Waiter automatically gets assigned to their own order.
        $waiter_id = (int) $_SESSION["user_id"];

    } else {

        // Admin must select a waiter.
        $waiter_id = !empty($_POST["waiter_id"])
            ? (int) $_POST["waiter_id"]
            : null;

        if (!$waiter_id) {
            $error = "Please select a waiter.";
        }
    }


    if ($error === "") {

        $allowed_order_types = [
            "dine_in",
            "delivery",
            "pickup"
        ];

        if (!in_array($order_type, $allowed_order_types)) {
            $error = "Invalid order type.";
        }
    }



    if ($error === "" && $order_type === "dine_in") {

        if (!$table_id) {
            $error = "Please select a table.";
        }
    }



    if ($order_type !== "dine_in") {
        $table_id = null;
    }



    $menu_item_ids = $_POST["menu_item_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    if ($error === "" && empty($menu_item_ids)) {
        $error = "Please add at least one menu item.";
    }


    if ($error === "") {

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
            $error = "Selected waiter is invalid or inactive.";
        }
    }



    if ($error === "" && $order_type === "dine_in") {

        $today = date("Y-m-d");
        $current_time = date("H:i:s");

        $sql = "SELECT id
                FROM reservations
                WHERE table_id = ?
                AND reservation_date = ?
                AND status IN ('pending', 'confirmed')
                AND reservation_time <= ?
                AND reservation_end_time > ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "isss",
            $table_id,
            $today,
            $current_time,
            $current_time
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($result) > 0) {
            $error = "This table is currently reserved.";
        }
    }


    if ($error === "") {

        mysqli_begin_transaction($conn);

        try {

            $total_amount = 0;



            $verified_items = [];

            for ($i = 0; $i < count($menu_item_ids); $i++) {

                $menu_item_id = (int) $menu_item_ids[$i];
                $quantity = (int) $quantities[$i];

                if ($menu_item_id <= 0 || $quantity <= 0) {
                    throw new Exception("Invalid menu item or quantity.");
                }

                $sql = "SELECT id, price
                        FROM menu_items
                        WHERE id = ?
                        AND is_available = 1";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $menu_item_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                $menu_item = mysqli_fetch_assoc($result);

                if (!$menu_item) {
                    throw new Exception("Menu item is unavailable.");
                }

                $unit_price = (float) $menu_item["price"];

                $subtotal = $unit_price * $quantity;

                $total_amount += $subtotal;

                $verified_items[] = [
                    "menu_item_id" => $menu_item_id,
                    "quantity" => $quantity,
                    "unit_price" => $unit_price,
                    "subtotal" => $subtotal
                ];
            }



            if ($order_type === "dine_in") {

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            table_id,
                            waiter_id,
                            order_type,
                            status,
                            payment_status,
                            total_amount
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            'pending',
                            'unpaid',
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiisd",
                    $customer_id,
                    $table_id,
                    $waiter_id,
                    $order_type,
                    $total_amount
                );

            } else {

                $sql = "INSERT INTO orders
                        (
                            customer_id,
                            table_id,
                            waiter_id,
                            order_type,
                            status,
                            payment_status,
                            total_amount
                        )
                        VALUES
                        (
                            ?,
                            NULL,
                            ?,
                            ?,
                            'pending',
                            'unpaid',
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisd",
                    $customer_id,
                    $waiter_id,
                    $order_type,
                    $total_amount
                );
            }

            mysqli_stmt_execute($stmt);

            $order_id = mysqli_insert_id($conn);



            foreach ($verified_items as $item) {

                $sql = "INSERT INTO order_items
                        (
                            order_id,
                            menu_item_id,
                            quantity,
                            unit_price,
                            subtotal
                        )
                        VALUES
                        (
                            ?,
                            ?,
                            ?,
                            ?,
                            ?
                        )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iiidd",
                    $order_id,
                    $item["menu_item_id"],
                    $item["quantity"],
                    $item["unit_price"],
                    $item["subtotal"]
                );

                mysqli_stmt_execute($stmt);
            }


            // Occupy Table

            if ($order_type === "dine_in") {

                $sql = "UPDATE restaurant_tables
                        SET status = 'occupied'
                        WHERE id = ?
                        AND status = 'available'";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "i",
                    $table_id
                );

                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) === 0) {
                    throw new Exception(
                        "The selected table is no longer available."
                    );
                }
            }


            mysqli_commit($conn);

            header(
                "Location: view.php?id=" . $order_id
            );

            exit;

        } catch (Exception $e) {

            mysqli_rollback($conn);

            $error = $e->getMessage();
        }
    }
}

$page_title = "New Order";
$active_menu = "orders";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">New Order</h2>
        <p class="page-header-subtitle">
            Create dining, delivery, or pickup ticket
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Orders</span>
        </a>
    </div>
</div>

<?php if ($error !== "") { ?>
    <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 border-danger-subtle" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-danger me-2 fs-5"></i>
        <div class="small fw-medium"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

<form method="POST" id="createOrderForm">
    <div class="row g-4">
        <!-- Main: Order Items Entry -->
        <div class="col-12 col-lg-8">
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-basket me-2 text-primary"></i>Order Items</span>
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="addItem()">
                        <i class="bi bi-plus-lg me-1"></i>Add Item Row
                    </button>
                </div>
                <div class="pos-card-body">
                    <div id="itemsContainer">
                        <div class="order-item order-item-row">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-6">
                                    <label class="form-label pos-form-label mb-1">Menu Item</label>
                                    <select name="menu_item_id[]" class="form-select pos-form-control menu-item-select" required onchange="updateRowCalculations(this)">
                                        <option value="" data-price="0">-- Select Menu Item --</option>
                                        <?php mysqli_data_seek($menu_items, 0); ?>
                                        <?php while ($item = mysqli_fetch_assoc($menu_items)) { ?>
                                            <option value="<?php echo $item["id"]; ?>" data-price="<?php echo $item["price"]; ?>">
                                                <?php echo htmlspecialchars($item["name"], ENT_QUOTES, 'UTF-8'); ?> (Rs. <?php echo number_format($item["price"], 2); ?>)
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <label class="form-label pos-form-label mb-1">Quantity</label>
                                    <input type="number" name="quantity[]" min="1" value="1" class="form-control pos-form-control item-quantity" required oninput="updateRowCalculations(this)">
                                </div>
                                <div class="col-4 col-md-2 text-md-end">
                                    <label class="form-label pos-form-label mb-1">Subtotal</label>
                                    <div class="fw-semibold text-dark line-subtotal py-1">Rs. 0.00</div>
                                </div>
                                <div class="col-2 col-md-1 text-end">
                                    <label class="form-label pos-form-label mb-1 d-none d-md-block">&nbsp;</label>
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)" title="Remove item">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addItem()">
                            <i class="bi bi-plus-lg me-1"></i>Add Another Item
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar: Order Parameters & Action -->
        <div class="col-12 col-lg-4">
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title"><i class="bi bi-info-circle me-2 text-primary"></i>Service &amp; Guest</span>
                </div>
                <div class="pos-card-body">
                    <!-- Customer -->
                    <div class="mb-3">
                        <label for="customerId" class="form-label pos-form-label">Customer</label>
                        <select name="customer_id" id="customerId" class="form-select pos-form-control">
                            <option value="">Walk-in Customer</option>
                            <?php mysqli_data_seek($customers, 0); ?>
                            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>
                                <option value="<?php echo $customer["id"]; ?>">
                                    <?php echo htmlspecialchars($customer["name"], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($customer["phone"])) { echo " - " . htmlspecialchars($customer["phone"], ENT_QUOTES, 'UTF-8'); } ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Order Type -->
                    <div class="mb-3">
                        <label for="orderType" class="form-label pos-form-label">Order Type <span class="text-danger">*</span></label>
                        <select name="order_type" id="orderType" class="form-select pos-form-control" required>
                            <option value="">-- Select Order Type --</option>
                            <option value="dine_in">Dine In</option>
                            <option value="delivery">Delivery</option>
                            <option value="pickup">Pickup</option>
                        </select>
                    </div>

                    <!-- Dining Table -->
                    <div id="tableSection" class="mb-3" style="display: none;">
                        <label for="tableId" class="form-label pos-form-label">Dining Table <span class="text-danger">*</span></label>
                        <select name="table_id" id="tableId" class="form-select pos-form-control">
                            <option value="">-- Select Table --</option>
                            <?php mysqli_data_seek($tables, 0); ?>
                            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>
                                <option value="<?php echo $table["id"]; ?>">
                                    Table <?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?> (Capacity: <?php echo htmlspecialchars($table["capacity"], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php } ?>
                        </select>
                        <small class="text-muted d-block mt-1">Available dining tables only.</small>
                    </div>

                    <!-- Waiter Assignment -->
                    <div class="mb-3">
                        <?php if ($_SESSION["role"] === "admin") { ?>
                            <label for="waiterId" class="form-label pos-form-label">Assigned Waiter <span class="text-danger">*</span></label>
                            <select name="waiter_id" id="waiterId" class="form-select pos-form-control" required>
                                <option value="">-- Select Waiter --</option>
                                <?php mysqli_data_seek($waiters, 0); ?>
                                <?php while ($waiter = mysqli_fetch_assoc($waiters)) { ?>
                                    <option value="<?php echo $waiter["id"]; ?>">
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

            <!-- Order Total & Submit Card -->
            <div class="pos-card">
                <div class="pos-card-body">
                    <div class="order-summary-box mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Items Count:</span>
                            <span id="summaryItemCount" class="fw-semibold text-dark">0</span>
                        </div>
                        <hr class="my-2 border-secondary-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">Total Amount:</span>
                            <span id="summaryTotal" class="fs-4 fw-bold text-primary">Rs. 0.00</span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <span>Create Order</span>
                    </button>
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

    recalcAll();
});

function updateRowCalculations(element) {
    const row = element.closest('.order-item');
    if (!row) return;

    const select = row.querySelector('.menu-item-select');
    const qtyInput = row.querySelector('.item-quantity');
    const subtotalDisplay = row.querySelector('.line-subtotal');

    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price') || 0) : 0;
    const qty = parseInt(qtyInput.value) || 0;
    const subtotal = price * qty;

    if (subtotalDisplay) {
        subtotalDisplay.textContent = 'Rs. ' + subtotal.toFixed(2);
    }

    recalcAll();
}

function recalcAll() {
    let grandTotal = 0;
    let totalItems = 0;

    const rows = document.querySelectorAll('.order-item');
    rows.forEach(function (row) {
        const select = row.querySelector('.menu-item-select');
        const qtyInput = row.querySelector('.item-quantity');
        const subtotalDisplay = row.querySelector('.line-subtotal');

        const selectedOption = select ? select.options[select.selectedIndex] : null;
        const price = selectedOption ? parseFloat(selectedOption.getAttribute('data-price') || 0) : 0;
        const qty = qtyInput ? (parseInt(qtyInput.value) || 0) : 0;
        const subtotal = price * qty;

        if (subtotalDisplay) {
            subtotalDisplay.textContent = 'Rs. ' + subtotal.toFixed(2);
        }

        if (price > 0 && qty > 0) {
            grandTotal += subtotal;
            totalItems += qty;
        }
    });

    const summaryTotal = document.getElementById('summaryTotal');
    const summaryItemCount = document.getElementById('summaryItemCount');

    if (summaryTotal) summaryTotal.textContent = 'Rs. ' + grandTotal.toFixed(2);
    if (summaryItemCount) summaryItemCount.textContent = totalItems.toString();
}

function addItem() {
    const container = document.getElementById("itemsContainer");
    const firstItem = document.querySelector(".order-item");
    if (!container || !firstItem) return;

    const newItem = firstItem.cloneNode(true);
    const select = newItem.querySelector('select[name="menu_item_id[]"]');
    const qty = newItem.querySelector('input[name="quantity[]"]');
    const subtotal = newItem.querySelector('.line-subtotal');

    if (select) select.value = "";
    if (qty) qty.value = 1;
    if (subtotal) subtotal.textContent = "Rs. 0.00";

    container.appendChild(newItem);
    recalcAll();
}

function removeItem(btn) {
    const rows = document.querySelectorAll('.order-item');
    const row = btn.closest('.order-item');
    if (!row) return;

    if (rows.length > 1) {
        row.remove();
    } else {
        const select = row.querySelector('.menu-item-select');
        const qty = row.querySelector('.item-quantity');
        const subtotal = row.querySelector('.line-subtotal');
        if (select) select.value = "";
        if (qty) qty.value = 1;
        if (subtotal) subtotal.textContent = "Rs. 0.00";
    }
    recalcAll();
}
</script>

<?php

require_once "../includes/footer.php";

?>