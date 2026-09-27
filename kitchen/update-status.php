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

?>

<!DOCTYPE html>
<html>

<head>

    <title>Update Kitchen Status</title>

</head>

<body>

    <h1>
        Update Kitchen Status
    </h1>


    <p>

        <strong>
            Current Status:
        </strong>

        <?php echo $order['status']; ?>

    </p>


    <form method="POST">

        <label>
            New Status
        </label>

        <br>

        <select name="status" required>

            <?php if ($order['status'] === 'pending') { ?>

                <option value="preparing">
                    Preparing
                </option>

                <option value="cancelled">
                    Cancelled
                </option>

            <?php } ?>


            <?php if ($order['status'] === 'preparing') { ?>

                <option value="ready">
                    Ready
                </option>

                <option value="cancelled">
                    Cancelled
                </option>

            <?php } ?>


            <?php if ($order['status'] === 'ready') { ?>

                <option value="completed">
                    Completed
                </option>

                <option value="cancelled">
                    Cancelled
                </option>

            <?php } ?>

        </select>

        <br><br>


        <button type="submit">
            Update Status
        </button>

    </form>


    <br>

    <a href="view.php?id=<?php echo $id; ?>">
        Back to Kitchen Order
    </a>

</body>

</html>