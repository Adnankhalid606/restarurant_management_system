<?php
/**
 * Shared Kitchen Workflow Service
 * Encapsulates order status advancement, inventory deduction, and table state management.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

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

        mysqli_begin_transaction($conn);

        try {
            // Fetch and lock order with current status, payment_status and table
            $sql = "SELECT id, table_id, status, payment_status FROM orders WHERE id = ? FOR UPDATE";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception('Database error preparing query.');
            }
            mysqli_stmt_bind_param($stmt, 'i', $order_id);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Database error loading order state.');
            }
            $res = mysqli_stmt_get_result($stmt);
            $order = mysqli_fetch_assoc($res);

            if (!$order) {
                throw new Exception('Order #' . $order_id . ' not found.');
            }

            $current_status = $order['status'];

            // Terminal state checks
            if ($current_status === 'completed' || $current_status === 'cancelled') {
                throw new Exception('Order #' . $order_id . ' is already ' . $current_status . ' and cannot be updated.');
            }

            // Validate workflow transition rules strictly against locked row
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
                throw new Exception('Cannot transition order #' . $order_id . ' from "' . ucfirst($current_status) . '" to "' . ucfirst($target_status) . '".');
            }
            // Protect order cancellation against active payments and orphaned invoices
            if ($target_status === 'cancelled') {
                $bill_stmt = mysqli_prepare($conn, "SELECT id, invoice_number, payment_status, total_amount FROM bills WHERE order_id = ? FOR UPDATE");
                mysqli_stmt_bind_param($bill_stmt, "i", $order_id);
                mysqli_stmt_execute($bill_stmt);
                $linked_bill = mysqli_fetch_assoc(mysqli_stmt_get_result($bill_stmt));

                if ($linked_bill) {
                    $inv_label = !empty($linked_bill['invoice_number']) ? $linked_bill['invoice_number'] : ('Invoice #' . $linked_bill['id']);

                    // Check if any payment records exist in invoice_payments
                    $pay_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS payment_count, COALESCE(SUM(amount), 0) AS total_paid FROM invoice_payments WHERE bill_id = ?");
                    mysqli_stmt_bind_param($pay_stmt, "i", $linked_bill['id']);
                    mysqli_stmt_execute($pay_stmt);
                    $pay_data = mysqli_fetch_assoc(mysqli_stmt_get_result($pay_stmt));
                    $payment_count = (int) $pay_data['payment_count'];
                    $total_paid = (float) $pay_data['total_paid'];

                    if ($payment_count > 0 || $total_paid > 0 || $linked_bill['payment_status'] === 'paid' || $order['payment_status'] === 'paid') {
                        throw new Exception("Cannot cancel Order #$order_id: Recorded payments exist on $inv_label (Rs. " . number_format($total_paid, 2) . " collected). A payment reversal/refund must be handled through an authorized process before cancellation.");
                    }

                    // Invoice exists with zero payments: safely remove uncollected invoice within same transaction
                    $del_bill_stmt = mysqli_prepare($conn, "DELETE FROM bills WHERE id = ?");
                    mysqli_stmt_bind_param($del_bill_stmt, "i", $linked_bill['id']);
                    if (!mysqli_stmt_execute($del_bill_stmt)) {
                        throw new Exception("Failed to remove uncollected invoice for Order #$order_id: " . mysqli_error($conn));
                    }
                }
            }

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

                // Check stock availability, capture moving average unit costs, and validate UOM compliance with row-level lock
                $ingredient_costs = [];
                foreach ($required_ingredients as $raw_material_id => $ingredient) {
                    $sql = "SELECT rm.name, rm.current_stock, rm.current_unit_cost, uom.name AS uom_name, uom.symbol AS uom_symbol, uom.allow_decimal 
                            FROM raw_materials rm
                            LEFT JOIN units_of_measure uom ON rm.unit_id = uom.id
                            WHERE rm.id = ? FOR UPDATE";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'i', $raw_material_id);
                    mysqli_stmt_execute($stmt);
                    $mat_res = mysqli_stmt_get_result($stmt);
                    $material = mysqli_fetch_assoc($mat_res);

                    if (!$material) {
                        throw new Exception('Raw material #' . $raw_material_id . ' not found in catalog.');
                    }

                    $allow_decimal = (int) ($material['allow_decimal'] ?? 1);
                    $req_qty = (float) $ingredient['quantity'];

                    // Enforce UOM decimal rule on kitchen consumption
                    if ($allow_decimal === 0 && (floor($req_qty) != $req_qty)) {
                        throw new Exception('Fractional recipe consumption is invalid for whole-number item "' . $material['name'] . '" (' . $material['uom_symbol'] . '). Quantity: ' . $req_qty);
                    }

                    if ((float) $material['current_stock'] < $req_qty) {
                        throw new Exception('Insufficient inventory stock for "' . $material['name'] . '" (Required: ' . $req_qty . ', Available: ' . (float)$material['current_stock'] . ').');
                    }

                    $ingredient_costs[$raw_material_id] = (float) ($material['current_unit_cost'] ?? 0);
                }

                $acting_user_id = (int) ($_SESSION['user_id'] ?? 0);

                // Deduct inventory and record audit transactions with cost snapshot
                foreach ($required_ingredients as $raw_material_id => $ingredient) {
                    $required_qty = (float) $ingredient['quantity'];
                    $unit_cost = (float) ($ingredient_costs[$raw_material_id] ?? 0);
                    $total_cost = round($required_qty * $unit_cost, 2);

                    $sql = "UPDATE raw_materials SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'did', $required_qty, $raw_material_id, $required_qty);
                    mysqli_stmt_execute($stmt);

                    if (mysqli_stmt_affected_rows($stmt) !== 1) {
                        throw new Exception('Inventory stock deduction failed for material #' . $raw_material_id . '.');
                    }

                    $sql = "INSERT INTO inventory_transactions 
                            (raw_material_id, type, quantity, unit_cost, total_cost, reference_type, reference_id, created_by, notes)
                            VALUES (?, 'consumption', ?, ?, ?, 'order', ?, ?, 'Kitchen order completion')";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, 'idddii', $raw_material_id, $required_qty, $unit_cost, $total_cost, $order_id, $acting_user_id);
                    mysqli_stmt_execute($stmt);
                }
            }

            // Update order status
            $sql = "UPDATE orders SET status = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $target_status, $order_id);
            mysqli_stmt_execute($stmt);

            // Automatically generate customer invoice if order transitioned to ready or completed
            if ($target_status === 'ready' || $target_status === 'completed') {
                ensureOrderInvoice($conn, $order_id);
            }

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
