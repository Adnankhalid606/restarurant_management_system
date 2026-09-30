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

// All available menu items with category and image
$menu_items_query = mysqli_query(
    $conn,
    "SELECT id, name, category, price, image
     FROM menu_items
     WHERE is_available = 1
     ORDER BY category ASC, name ASC"
);

$available_menu_items = [];
$menu_categories = [];

while ($m_item = mysqli_fetch_assoc($menu_items_query)) {
    $available_menu_items[] = $m_item;
    $cat = trim($m_item['category'] ?? '');
    if ($cat !== '' && !in_array($cat, $menu_categories)) {
        $menu_categories[] = $cat;
    }
}


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

<?php
// Prepare any prefill items if POST was submitted with errors
$prefill_cart_items = [];
if (!empty($_POST["menu_item_id"]) && is_array($_POST["menu_item_id"])) {
    foreach ($_POST["menu_item_id"] as $idx => $mid) {
        $mid = (int)$mid;
        $qty = isset($_POST["quantity"][$idx]) ? (int)$_POST["quantity"][$idx] : 1;
        if ($qty < 1) $qty = 1;
        foreach ($available_menu_items as $mi) {
            if ((int)$mi["id"] === $mid) {
                $prefill_cart_items[] = [
                    "id" => $mid,
                    "name" => $mi["name"],
                    "price" => (float)$mi["price"],
                    "quantity" => $qty
                ];
                break;
            }
        }
    }
}
$posted_order_type = $_POST["order_type"] ?? "";
$posted_cust_id = $_POST["customer_id"] ?? "";
$posted_table_id = $_POST["table_id"] ?? "";
$posted_waiter_id = $_POST["waiter_id"] ?? "";
?>

<form method="POST" id="createOrderForm">
    <div class="row g-3">
        <!-- Left: Visual Image Menu Display (col-12 col-lg-7) -->
        <div class="col-12 col-lg-7">
            <div class="pos-card h-100 d-flex flex-column">
                <div class="pos-card-header bg-light pb-2">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 w-100">
                        <span class="pos-card-title">
                            <i class="bi bi-grid-fill text-primary me-2"></i>Menu Catalogue
                        </span>
                        <!-- Compact Search Bar -->
                        <div class="input-group input-group-sm" style="max-width: 220px;">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input 
                                type="text" 
                                id="menuItemSearch" 
                                class="form-control pos-form-control border-start-0" 
                                placeholder="Search dishes..."
                                autocomplete="off"
                            >
                        </div>
                    </div>
                </div>

                <div class="pos-card-body p-3 d-flex flex-column flex-grow-1">
                    <!-- Compact Category Filter Pills -->
                    <div class="d-flex align-items-center gap-1 overflow-auto pb-2 mb-3" id="categoryFilterPills" style="scrollbar-width: thin;">
                        <button type="button" class="btn btn-sm btn-primary pos-cat-pill active" data-category="all">
                            All (<?php echo count($available_menu_items); ?>)
                        </button>
                        <?php foreach ($menu_categories as $cat) { ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary pos-cat-pill" data-category="<?php echo htmlspecialchars(mb_strtolower($cat), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($cat, ENT_QUOTES, 'UTF-8'); ?>
                            </button>
                        <?php } ?>
                    </div>

                    <!-- Scrollable Visual Menu Cards Grid -->
                    <div class="pos-menu-scroll-container flex-grow-1">
                        <div class="row g-2" id="menuCardsContainer">
                            <?php foreach ($available_menu_items as $item) {
                                $has_img = !empty($item['image']) && file_exists(dirname(__DIR__) . '/assets/uploads/menu/' . $item['image']);
                                $cat_clean = mb_strtolower(trim($item['category'] ?? ''));
                                $name_clean = mb_strtolower($item['name']);
                            ?>
                                <div class="col-6 col-sm-4 menu-item-card-col" 
                                     data-name="<?php echo htmlspecialchars($name_clean, ENT_QUOTES, 'UTF-8'); ?>"
                                     data-category="<?php echo htmlspecialchars($cat_clean, ENT_QUOTES, 'UTF-8'); ?>">
                                    <div class="pos-menu-card h-100" 
                                         onclick="addToCart(<?php echo (int)$item['id']; ?>, <?php echo htmlspecialchars(json_encode($item['name']), ENT_QUOTES, 'UTF-8'); ?>, <?php echo (float)$item['price']; ?>)"
                                         role="button"
                                         tabindex="0"
                                         title="Click to add <?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <div class="pos-menu-card-img-wrap">
                                            <?php if ($has_img) { ?>
                                                <img src="../assets/uploads/menu/<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>" 
                                                     class="pos-menu-card-img" 
                                                     loading="lazy">
                                            <?php } else { ?>
                                                <div class="pos-menu-card-placeholder">
                                                    <i class="bi bi-egg-fried fs-2 text-secondary opacity-50"></i>
                                                </div>
                                            <?php } ?>
                                            <?php if (!empty($item['category'])) { ?>
                                                <span class="badge bg-dark bg-opacity-75 position-absolute top-0 start-0 m-1" style="font-size: 0.68rem; font-weight: 500;">
                                                    <?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            <?php } ?>
                                        </div>
                                        <div class="pos-menu-card-body">
                                            <div class="pos-menu-card-title"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="d-flex align-items-center justify-content-between mt-auto pt-1">
                                                <span class="pos-menu-card-price">Rs. <?php echo number_format($item['price'], 2); ?></span>
                                                <span class="btn btn-sm btn-outline-primary py-0 px-2 rounded-pill" style="font-size: 0.72rem;">
                                                    <i class="bi bi-plus"></i> Add
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>

                        <!-- No Search Results Found Row -->
                        <div id="noCardsFoundMessage" class="text-center text-muted p-5 d-none">
                            <i class="bi bi-search fs-2 d-block mb-2 text-secondary opacity-50"></i>
                            <span>No dishes found matching your search.</span>
                        </div>
                    </div>

                    <div class="text-muted small mt-2 pt-2 border-top d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-info-circle me-1"></i>Click card to add. Repeated clicks increase quantity.</span>
                        <span class="fw-semibold text-secondary" id="filteredItemCount"><?php echo count($available_menu_items); ?> dishes</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Service Details & Order Basket (col-12 col-lg-5) -->
        <div class="col-12 col-lg-5">
            <!-- Service & Guest Card -->
            <div class="pos-card mb-3">
                <div class="pos-card-header bg-light">
                    <span class="pos-card-title"><i class="bi bi-info-circle me-2 text-primary"></i>Service &amp; Guest</span>
                </div>
                <div class="pos-card-body p-3">
                    <!-- Customer -->
                    <div class="mb-2">
                        <label for="customerId" class="form-label pos-form-label mb-1">Customer</label>
                        <select name="customer_id" id="customerId" class="form-select pos-form-control py-1 fs-6">
                            <option value="">Walk-in Customer</option>
                            <?php mysqli_data_seek($customers, 0); ?>
                            <?php while ($customer = mysqli_fetch_assoc($customers)) { ?>
                                <option value="<?php echo $customer["id"]; ?>" <?php if ($posted_cust_id == $customer["id"]) echo "selected"; ?>>
                                    <?php echo htmlspecialchars($customer["name"], ENT_QUOTES, 'UTF-8'); ?><?php if (!empty($customer["phone"])) { echo " - " . htmlspecialchars($customer["phone"], ENT_QUOTES, 'UTF-8'); } ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Order Type -->
                    <div class="mb-2">
                        <label for="orderType" class="form-label pos-form-label mb-1">Order Type <span class="text-danger">*</span></label>
                        <select name="order_type" id="orderType" class="form-select pos-form-control py-1 fs-6" required>
                            <option value="">-- Select Order Type --</option>
                            <option value="dine_in" <?php if ($posted_order_type === "dine_in") echo "selected"; ?>>Dine In</option>
                            <option value="delivery" <?php if ($posted_order_type === "delivery") echo "selected"; ?>>Delivery</option>
                            <option value="pickup" <?php if ($posted_order_type === "pickup") echo "selected"; ?>>Pickup</option>
                        </select>
                    </div>

                    <!-- Dining Table (conditional) -->
                    <div id="tableSection" class="mb-2" style="<?php echo ($posted_order_type === 'dine_in') ? 'display: block;' : 'display: none;'; ?>">
                        <label for="tableId" class="form-label pos-form-label mb-1">Dining Table <span class="text-danger">*</span></label>
                        <select name="table_id" id="tableId" class="form-select pos-form-control py-1 fs-6">
                            <option value="">-- Select Table --</option>
                            <?php mysqli_data_seek($tables, 0); ?>
                            <?php while ($table = mysqli_fetch_assoc($tables)) { ?>
                                <option value="<?php echo $table["id"]; ?>" <?php if ($posted_table_id == $table["id"]) echo "selected"; ?>>
                                    Table <?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?> (Capacity: <?php echo htmlspecialchars($table["capacity"], ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php } ?>
                        </select>
                        <small class="text-muted d-block">Available dining tables only.</small>
                    </div>

                    <!-- Waiter Assignment -->
                    <div class="mb-0">
                        <?php if ($_SESSION["role"] === "admin") { ?>
                            <label for="waiterId" class="form-label pos-form-label mb-1">Assigned Waiter <span class="text-danger">*</span></label>
                            <select name="waiter_id" id="waiterId" class="form-select pos-form-control py-1 fs-6" required>
                                <option value="">-- Select Waiter --</option>
                                <?php mysqli_data_seek($waiters, 0); ?>
                                <?php while ($waiter = mysqli_fetch_assoc($waiters)) { ?>
                                    <option value="<?php echo $waiter["id"]; ?>" <?php if ($posted_waiter_id == $waiter["id"]) echo "selected"; ?>>
                                        <?php echo htmlspecialchars($waiter["name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        <?php } else { ?>
                            <label class="form-label pos-form-label mb-1">Assigned Waiter</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person-badge"></i></span>
                                <input type="text" class="form-control pos-form-control bg-light border-start-0 py-1" value="<?php echo htmlspecialchars($_SESSION["user_name"], ENT_QUOTES, 'UTF-8'); ?>" readonly>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Current Order Basket Card -->
            <div class="pos-card shadow-sm">
                <div class="pos-card-header bg-light d-flex align-items-center justify-content-between">
                    <span class="pos-card-title">
                        <i class="bi bi-receipt me-2 text-primary"></i>Order Ticket
                    </span>
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="clearCart()" title="Empty basket">
                        <i class="bi bi-trash"></i> Clear
                    </button>
                </div>
                <div class="pos-card-body p-3">
                    <!-- Selected Cart Items Container -->
                    <div id="cartItemsContainer" style="max-height: 290px; overflow-y: auto; padding-right: 2px;">
                        <!-- Dynamically populated by JavaScript -->
                    </div>

                    <!-- Empty Cart Notice -->
                    <div id="emptyCartNotice" class="text-center text-muted p-4 border rounded bg-light mb-3">
                        <i class="bi bi-basket3 fs-2 d-block mb-1 text-secondary opacity-50"></i>
                        <span class="small">Ticket is empty.<br>Click dishes on the left to add items.</span>
                    </div>

                    <!-- Order Summary Box -->
                    <div class="order-summary-box my-3 p-2 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Total Items:</span>
                            <span id="summaryItemCount" class="fw-semibold text-dark">0</span>
                        </div>
                        <hr class="my-1 border-secondary-subtle">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark">Total Amount:</span>
                            <span id="summaryTotal" class="fs-4 fw-bold text-primary">Rs. 0.00</span>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm" id="submitOrderBtn">
                        <i class="bi bi-check2-circle fs-5"></i>
                        <span>Create Order</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
// Cart state: object keyed by menu_item_id -> { id, name, price, quantity }
let cart = {};

const prefillData = <?php echo json_encode($prefill_cart_items); ?>;

document.addEventListener('DOMContentLoaded', function () {
    // 1. Dining Table conditional toggle
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

    // 2. Search & Category Filters
    const searchInput = document.getElementById('menuItemSearch');
    const categoryButtons = document.querySelectorAll('#categoryFilterPills button');
    const cardCols = document.querySelectorAll('.menu-item-card-col');
    const noCardsFound = document.getElementById('noCardsFoundMessage');
    const filteredCountLabel = document.getElementById('filteredItemCount');
    let currentCategory = 'all';

    function filterMenuCards() {
        const query = (searchInput.value || '').trim().toLowerCase();
        let visibleCount = 0;

        cardCols.forEach(function (col) {
            const name = col.getAttribute('data-name') || '';
            const cat = col.getAttribute('data-category') || '';

            const matchesQuery = !query || name.includes(query) || cat.includes(query);
            const matchesCat = (currentCategory === 'all') || (cat === currentCategory);

            if (matchesQuery && matchesCat) {
                col.classList.remove('d-none');
                visibleCount++;
            } else {
                col.classList.add('d-none');
            }
        });

        if (filteredCountLabel) {
            filteredCountLabel.textContent = visibleCount + ' dishes';
        }

        if (noCardsFound) {
            if (visibleCount === 0) {
                noCardsFound.classList.remove('d-none');
            } else {
                noCardsFound.classList.add('d-none');
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterMenuCards);
    }

    categoryButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            categoryButtons.forEach(b => {
                b.classList.remove('btn-primary', 'active');
                b.classList.add('btn-outline-secondary');
            });
            this.classList.remove('btn-outline-secondary');
            this.classList.add('btn-primary', 'active');
            currentCategory = this.getAttribute('data-category') || 'all';
            filterMenuCards();
        });
    });

    // 3. Prepopulate cart on page load (if any previous items on validation failure)
    if (prefillData && prefillData.length) {
        prefillData.forEach(function (item) {
            cart[item.id] = {
                id: item.id,
                name: item.name,
                price: parseFloat(item.price),
                quantity: parseInt(item.quantity) || 1
            };
        });
    }

    renderCart();

    // 4. Form validation on submit
    const createOrderForm = document.getElementById('createOrderForm');
    if (createOrderForm) {
        createOrderForm.addEventListener('submit', function (e) {
            if (Object.keys(cart).length === 0) {
                e.preventDefault();
                alert('Please add at least one menu item to the order ticket before submitting.');
            }
        });
    }
});

// Add item or increment quantity on card click
function addToCart(id, name, price) {
    if (cart[id]) {
        cart[id].quantity += 1;
    } else {
        cart[id] = {
            id: id,
            name: name,
            price: parseFloat(price),
            quantity: 1
        };
    }
    renderCart();
}

// Quantity step (-1 or +1)
function stepItemQty(id, delta) {
    if (!cart[id]) return;
    const newQty = cart[id].quantity + delta;
    if (newQty <= 0) {
        delete cart[id];
    } else {
        cart[id].quantity = newQty;
    }
    renderCart();
}

// Direct numeric input handler
function onQtyInput(id, value) {
    if (!cart[id]) return;
    const parsed = parseInt(value, 10);
    if (!isNaN(parsed) && parsed > 0) {
        cart[id].quantity = parsed;
        updateTotalsOnly();
    }
}

// On blur, normalize or remove if invalid
function onQtyBlur(id, inputElement) {
    if (!cart[id]) return;
    const parsed = parseInt(inputElement.value, 10);
    if (isNaN(parsed) || parsed <= 0) {
        delete cart[id];
        renderCart();
    } else {
        cart[id].quantity = parsed;
        renderCart();
    }
}

// Remove single item from cart
function removeFromCart(id) {
    if (cart[id]) {
        delete cart[id];
        renderCart();
    }
}

// Clear all items from cart
function clearCart() {
    if (Object.keys(cart).length === 0) return;
    if (confirm('Clear all items from this order ticket?')) {
        cart = {};
        renderCart();
    }
}

// Quick recalculation without re-rendering entire DOM
function updateTotalsOnly() {
    let totalItems = 0;
    let grandTotal = 0;

    Object.values(cart).forEach(function (item) {
        const subtotal = item.price * item.quantity;
        totalItems += item.quantity;
        grandTotal += subtotal;

        const subtotalElem = document.getElementById('line-subtotal-' + item.id);
        if (subtotalElem) {
            subtotalElem.textContent = 'Rs. ' + subtotal.toFixed(2);
        }
    });

    const summaryItemCount = document.getElementById('summaryItemCount');
    const summaryTotal = document.getElementById('summaryTotal');
    if (summaryItemCount) summaryItemCount.textContent = totalItems.toString();
    if (summaryTotal) summaryTotal.textContent = 'Rs. ' + grandTotal.toFixed(2);
}

// Render full cart DOM
function renderCart() {
    const container = document.getElementById('cartItemsContainer');
    const emptyNotice = document.getElementById('emptyCartNotice');
    if (!container) return;

    const items = Object.values(cart);

    if (items.length === 0) {
        container.innerHTML = '';
        if (emptyNotice) emptyNotice.classList.remove('d-none');
        updateTotalsOnly();
        return;
    }

    if (emptyNotice) emptyNotice.classList.add('d-none');

    let html = '';
    let totalItems = 0;
    let grandTotal = 0;

    items.forEach(function (item) {
        const subtotal = item.price * item.quantity;
        totalItems += item.quantity;
        grandTotal += subtotal;

        html += `
            <div class="cart-item-row" id="cart-row-${item.id}">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <div class="fw-semibold text-dark text-truncate me-2" style="font-size: 0.88rem;" title="${escapeHtml(item.name)}">
                        ${escapeHtml(item.name)}
                    </div>
                    <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="removeFromCart(${item.id})" title="Remove item">
                        <i class="bi bi-x-circle fs-6"></i>
                    </button>
                </div>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="text-muted small">
                        Rs. ${item.price.toFixed(2)}
                    </div>
                    <div class="d-flex align-items-center">
                        <button type="button" class="cart-qty-btn" onclick="stepItemQty(${item.id}, -1)" title="Decrease quantity">-</button>
                        <input type="number" 
                               name="quantity[]" 
                               id="qty-input-${item.id}" 
                               value="${item.quantity}" 
                               min="1" 
                               class="cart-qty-input" 
                               oninput="onQtyInput(${item.id}, this.value)"
                               onblur="onQtyBlur(${item.id}, this)">
                        <button type="button" class="cart-qty-btn" onclick="stepItemQty(${item.id}, 1)" title="Increase quantity">+</button>
                    </div>
                    <div class="fw-bold text-dark text-end line-subtotal" id="line-subtotal-${item.id}" style="min-width: 75px;">
                        Rs. ${subtotal.toFixed(2)}
                    </div>
                </div>
                <input type="hidden" name="menu_item_id[]" value="${item.id}">
            </div>
        `;
    });

    container.innerHTML = html;

    const summaryItemCount = document.getElementById('summaryItemCount');
    const summaryTotal = document.getElementById('summaryTotal');
    if (summaryItemCount) summaryItemCount.textContent = totalItems.toString();
    if (summaryTotal) summaryTotal.textContent = 'Rs. ' + grandTotal.toFixed(2);
}

function escapeHtml(string) {
    return String(string).replace(/[&<>"'`=\/]/g, function (s) {
        return ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
            '/': '&#x2F;',
            '`': '&#x60;',
            '=': '&#x3D;'
        })[s];
    });
}
</script>

<?php

require_once "../includes/footer.php";

?>