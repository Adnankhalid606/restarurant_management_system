<?php

require_once '../config/database.php';
require_once '../includes/role.php';

requireRole(['admin', 'kitchen']);

$id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($id <= 0) {
    die('Invalid order ID.');
}

// Get order

$sql = 'SELECT
            id,
            table_id,
            status
        FROM orders
        WHERE id = ?';

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$order = mysqli_fetch_assoc($result);

if (!$order) {
    die('Order not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status'] ?? '';

    // Check status

    $allowed_statuses = [
        'pending',
        'preparing',
        'ready',
        'completed',
        'cancelled'
    ];

    if (!in_array($status, $allowed_statuses)) {
        die('Invalid status.');
    }

    // Check current status

    if (
        $order['status'] === 'completed' ||
        $order['status'] === 'cancelled'
    ) {
        die('This order can no longer be updated.');
    }

    // Check transition

    $valid_transition = false;

    if (
        $order['status'] === 'pending' &&
        $status === 'preparing'
    ) {
        $valid_transition = true;
    }

    if (
        $order['status'] === 'preparing' &&
        $status === 'ready'
    ) {
        $valid_transition = true;
    }

    if (
        $order['status'] === 'ready' &&
        $status === 'completed'
    ) {
        $valid_transition = true;
    }

    if ($status === 'cancelled') {
        $valid_transition = true;
    }

    if (!$valid_transition) {
        die('Invalid status transition.');
    }

    mysqli_begin_transaction($conn);

    try {
        // Consume inventory

        if ($status === 'completed') {
            // Get order items

            $sql = 'SELECT
                order_items.menu_item_id,
                order_items.quantity,
                menu_items.name AS menu_item_name
            FROM order_items
            JOIN menu_items
                ON order_items.menu_item_id = menu_items.id
            WHERE order_items.order_id = ?';

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $id
            );

            mysqli_stmt_execute($stmt);

            $order_items = mysqli_stmt_get_result($stmt);

            // Store required ingredients

            $required_ingredients = [];

            while ($order_item = mysqli_fetch_assoc($order_items)) {
                // Find recipe

                $sql = 'SELECT
                    recipe_items.raw_material_id,
                    recipe_items.quantity,
                    raw_materials.name AS material_name
                FROM recipes
                JOIN recipe_items
                    ON recipes.id = recipe_items.recipe_id
                JOIN raw_materials
                    ON recipe_items.raw_material_id = raw_materials.id
                WHERE recipes.menu_item_id = ?';

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $order_item['menu_item_id']
                );

                mysqli_stmt_execute($stmt);

                $ingredients = mysqli_stmt_get_result($stmt);

                // Skip items without recipes

                if (mysqli_num_rows($ingredients) === 0) {
                    continue;
                }

                while ($ingredient = mysqli_fetch_assoc($ingredients)) {
                    $raw_material_id = (int) $ingredient['raw_material_id'];

                    $required_quantity =
                        (float) $ingredient['quantity']
                        * (int) $order_item['quantity'];

                    // Add ingredient quantity

                    if (!isset($required_ingredients[$raw_material_id])) {
                        $required_ingredients[$raw_material_id] = [
                            'quantity' => 0
                        ];
                    }

                    $required_ingredients[$raw_material_id]['quantity'] +=
                        $required_quantity;
                }
            }

            // Lock and check stock

            foreach ($required_ingredients as $raw_material_id => $ingredient) {
                $sql = 'SELECT
                    name,
                    current_stock
                FROM raw_materials
                WHERE id = ?
                FOR UPDATE';

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'i',
                    $raw_material_id
                );

                mysqli_stmt_execute($stmt);

                $result = mysqli_stmt_get_result($stmt);

                $material = mysqli_fetch_assoc($result);

                if (!$material) {
                    throw new Exception('Raw material not found.');
                }

                // Check stock

                if (
                    (float) $material['current_stock'] <
                    $ingredient['quantity']
                ) {
                    throw new Exception(
                        'Not enough '
                        . $material['name']
                        . ' in stock.'
                    );
                }
            }

            // Deduct inventory

            foreach ($required_ingredients as $raw_material_id => $ingredient) {
                $required_quantity = $ingredient['quantity'];

                $sql = 'UPDATE raw_materials
                SET current_stock = current_stock - ?
                WHERE id = ?
                AND current_stock >= ?';

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'did',
                    $required_quantity,
                    $raw_material_id,
                    $required_quantity
                );

                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) !== 1) {
                    throw new Exception('Inventory update failed.');
                }

                // Record consumption

                $sql = "INSERT INTO inventory_transactions
                (
                    raw_material_id,
                    type,
                    quantity,
                    reference_type,
                    reference_id
                )
                VALUES
                (
                    ?,
                    'consumption',
                    ?,
                    'order',
                    ?
                )";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    'idi',
                    $raw_material_id,
                    $required_quantity,
                    $id
                );

                mysqli_stmt_execute($stmt);
            }
        }
        // Update order

        $sql = 'UPDATE orders
                SET status = ?
                WHERE id = ?';

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            'si',
            $status,
            $id
        );

        mysqli_stmt_execute($stmt);

        // Release table

        if (
            (
                $status === 'completed' ||
                $status === 'cancelled'
            ) &&
            !empty($order['table_id'])
        ) {
            $sql = "UPDATE restaurant_tables
                    SET status = 'available'
                    WHERE id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                'i',
                $order['table_id']
            );

            mysqli_stmt_execute($stmt);
        }

        mysqli_commit($conn);

        header('Location: view.php?id=' . $id);
        exit;
    } catch (Exception $error) {
        mysqli_rollback($conn);

        die($error->getMessage());
    }
}

$page_title = "Update Status - Order #" . $id;
$active_menu = "kitchen";

require_once "../includes/header.php";

$status_class = match($order["status"]) {
    'completed' => 'badge-status-completed',
    'preparing' => 'badge-status-preparing',
    'ready' => 'badge-status-ready',
    'cancelled' => 'badge-status-cancelled',
    default => 'badge-status-pending',
};

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Update Status &bull; Order #<?php echo $id; ?></h2>
        <p class="page-header-subtitle">
            Progress order through kitchen prep pipeline
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Ticket</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title"><i class="bi bi-arrow-repeat me-2 text-primary"></i>Kitchen Workflow Transition</span>
                <span class="badge bg-white text-muted border">Ticket #<?php echo $id; ?></span>
            </div>
            <div class="pos-card-body p-4">
                <!-- Current Status Pill Card -->
                <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded border mb-4">
                    <div>
                        <span class="text-muted small d-block mb-1">Current Order State:</span>
                        <span class="badge-subtle <?php echo $status_class; ?> text-capitalize fs-6">
                            <?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <div class="text-end text-muted small">
                        <i class="bi bi-clock-history me-1"></i>Awaiting Next Step
                    </div>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label for="statusSelect" class="form-label pos-form-label">Next Workflow Action <span class="text-danger">*</span></label>
                        <select name="status" id="statusSelect" class="form-select pos-form-control py-2 fs-6" required>
                            <option value="">-- Choose New Status --</option>
                            <?php if ($order['status'] === 'pending') { ?>
                                <option value="preparing">Start Preparing (Move to Active Cooking)</option>
                                <option value="cancelled">Cancel Order (Release Table)</option>
                            <?php } elseif ($order['status'] === 'preparing') { ?>
                                <option value="ready">Mark as Ready (Food Plated / Expedited)</option>
                                <option value="cancelled">Cancel Order (Release Table)</option>
                            <?php } elseif ($order['status'] === 'ready') { ?>
                                <option value="completed">Complete Order (Finalize Ticket, Deduct Stock &amp; Release Table)</option>
                                <option value="cancelled">Cancel Order (Release Table)</option>
                            <?php } ?>
                        </select>
                    </div>

                    <?php if ($order['status'] === 'ready') { ?>
                        <div class="alert alert-warning py-2 px-3 small d-flex align-items-center mb-4 border-warning-subtle">
                            <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
                            <div>
                                Advancing to <strong>Completed</strong> will automatically verify and deduct raw materials from inventory according to recipes.
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="alert alert-light border py-2 px-3 small text-muted mb-4 d-flex align-items-center gap-2">
                            <i class="bi bi-info-circle text-primary fs-5"></i>
                            <div>
                                Orders progress sequentially: <strong>Pending &rarr; Preparing &rarr; Ready &rarr; Completed</strong>.
                            </div>
                        </div>
                    <?php } ?>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm mb-2">
                        <i class="bi bi-check-circle"></i>
                        <span>Confirm &amp; Update Status</span>
                    </button>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary w-100 py-2">
                        Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>