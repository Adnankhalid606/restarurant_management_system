<?php
/**
 * Shared Kitchen Workflow Service
 * Encapsulates order status advancement, inventory deduction, and table state management.
 */

require_once __DIR__ . '/../config/database.php';

if (!function_exists('transitionKitchenOrderStatus')) {
    /**
     * Transition a kitchen order to a target status while enforcing business rules,
     * inventory deductions upon completion, and dining table release upon completion/cancellation.
     *
     * @param mysqli $conn Active database connection
     * @param int $order_id Order ID
     * @param string $target_status Desired target status ('preparing', 'ready', 'completed', 'cancelled')
     * @return array ['success' => bool, 'message' => string, 'order' => array|null]
     */
    function transitionKitchenOrderStatus($conn, $order_id, $target_status) {
        $order_id = (int) $order_id;
        $target_status = trim($target_status);

        if ($order_id <= 0) {
            return ['success' => false, 'message' => 'Invalid order identifier.'];
        }

        $allowed_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
        if (!in_array($target_status, $allowed_statuses, true)) {
            return ['success' => false, 'message' => 'Invalid status requested.'];
        }

        // Fetch order with current status and table
        $sql = "SELECT id, table_id, status FROM orders WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error preparing query.'];
        }
        mysqli_stmt_bind_param($stmt, 'i', $order_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($res);

        if (!$order) {
            return ['success' => false, 'message' => 'Order #' . $order_id . ' not found.'];
        }

        $current_status = $order['status'];

        // Terminal state checks
        if ($current_status === 'completed' || $current_status === 'cancelled') {
            return ['success' => false, 'message' => 'Order #' . $order_id . ' is already ' . $current_status . ' and cannot be updated.'];
        }

        // Validate workflow transition rules strictly
        $valid = false;
        if ($current_status === 'pending' && $target_status === 'preparing') {
            $valid = true;
        } elseif ($current_status === 'preparing' && $target_status === 'ready') {
            $valid = true;
        } elseif ($current_status === 'ready' && $target_status === 'completed') {
            $valid = true;
        } elseif ($target_status === 'cancelled') {
            // Any active status (pending, preparing, ready) can be cancelled
            $valid = true;
        }

        if (!$valid) {
            return [
                'success' => false, 
                'message' => 'Cannot transition order #' . $order_id . ' from "' . ucfirst($current_status) . '" to "' . ucfirst($target_status) . '".'
            ];
        }

        mysqli_begin_transaction($conn);

        try {
            // If completing the order, consume inventory according to recipe requirements
            if ($target_status === 'completed') {
                $sql = "SELECT order_items.menu_item_id, order_items.quantity, menu_items.name AS menu_item_name
                        FROM order_items
                        JOIN menu_items ON order_items.menu_item_id = menu_items.id
                        WHERE order_items.order_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $order_id);
                mysqli_stmt_execute($stmt);
                $order_items = mysqli_stmt_get_result($stmt);

                $required_ingredients = [];

                while ($order_item = mysqli_fetch_assoc($order_items)) {
                    $sql = "SELECT recipe_items.raw_material_id, recipe_items.quantity, raw_materials.name AS material_name
                            FROM recipes
                            JOIN recipe_items ON recipes.id = recipe_items.recipe_id
                            JOIN raw_materials ON recipe_items.raw_material_id = raw_materials.id
                            WHERE recipes.menu_item_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'i', $order_item['menu_item_id']);
                    mysqli_stmt_execute($stmt);
                    $ingredients = mysqli_stmt_get_result($stmt);

                    while ($ingredient = mysqli_fetch_assoc($ingredients)) {
                        $raw_material_id = (int) $ingredient['raw_material_id'];
                        $required_quantity = (float) $ingredient['quantity'] * (int) $order_item['quantity'];

                        if (!isset($required_ingredients[$raw_material_id])) {
                            $required_ingredients[$raw_material_id] = [
                                'quantity' => 0
                            ];
                        }
                        $required_ingredients[$raw_material_id]['quantity'] += $required_quantity;
                    }
                }

                // Check stock availability with row-level lock
                foreach ($required_ingredients as $raw_material_id => $ingredient) {
                    $sql = "SELECT name, current_stock FROM raw_materials WHERE id = ? FOR UPDATE";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'i', $raw_material_id);
                    mysqli_stmt_execute($stmt);
                    $mat_res = mysqli_stmt_get_result($stmt);
                    $material = mysqli_fetch_assoc($mat_res);

                    if (!$material) {
                        throw new Exception('Raw material #' . $raw_material_id . ' not found in catalog.');
                    }

                    if ((float) $material['current_stock'] < $ingredient['quantity']) {
                        throw new Exception('Insufficient inventory stock for "' . $material['name'] . '" (Required: ' . $ingredient['quantity'] . ', Available: ' . (float)$material['current_stock'] . ').');
                    }
                }

                // Deduct inventory and record audit transactions
                foreach ($required_ingredients as $raw_material_id => $ingredient) {
                    $required_qty = $ingredient['quantity'];

                    $sql = "UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'did', $required_qty, $raw_material_id, $required_qty);
                    mysqli_stmt_execute($stmt);

                    if (mysqli_stmt_affected_rows($stmt) !== 1) {
                        throw new Exception('Inventory stock deduction failed for material #' . $raw_material_id . '.');
                    }

                    $sql = "INSERT INTO inventory_transactions (raw_material_id, type, quantity, reference_type, reference_id)
                            VALUES (?, 'consumption', ?, 'order', ?)";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'idi', $raw_material_id, $required_qty, $order_id);
                    mysqli_stmt_execute($stmt);
                }
            }

            // Update order status
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $target_status, $order_id);
            mysqli_stmt_execute($stmt);

            // Release dining table if completed or cancelled
            if (($target_status === 'completed' || $target_status === 'cancelled') && !empty($order['table_id'])) {
                $sql = "UPDATE restaurant_tables SET status = 'available' WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $order['table_id']);
                mysqli_stmt_execute($stmt);
            }

            mysqli_commit($conn);

            $order['status'] = $target_status;
            return [
                'success' => true,
                'message' => 'Order #' . $order_id . ' successfully updated to ' . ucfirst($target_status) . '.',
                'order' => $order
            ];

        } catch (Exception $e) {
            mysqli_rollback($conn);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
