<?php
/**
 * Helper to render an individual KDS ticket HTML cleanly.
 * Reused for initial server-side render and KDS real-time state moves.
 */

if (!function_exists('renderKdsTicketCard')) {
    function renderKdsTicketCard($order) {
        $id = (int) $order['id'];
        $status = $order['status'];
        $age = getKitchenOrderAge($order['created_at']);
        $order_type = $order['order_type'];
        $table_num = !empty($order['table_number']) ? htmlspecialchars($order['table_number'], ENT_QUOTES, 'UTF-8') : '-';
        $waiter = htmlspecialchars($order['waiter_name'] ?? '-', ENT_QUOTES, 'UTF-8');
        $customer = htmlspecialchars($order['customer_name'] ?? 'Walk-in', ENT_QUOTES, 'UTF-8');
        $total_items = (int) ($order['total_items'] ?? 0);
        $total_qty = (int) ($order['total_quantity'] ?? 0);

        // Header theme badge based on status
        $header_class = 'bg-light';
        $border_class = '';
        if ($status === 'preparing') {
            $header_class = 'bg-primary-subtle text-primary border-primary-subtle';
            $border_class = 'border-primary-subtle';
        } elseif ($status === 'ready') {
            $header_class = 'bg-success-subtle text-success border-success-subtle';
            $border_class = 'border-success-subtle';
        }

        ob_start();
        ?>
        <div class="kds-ticket <?php echo $border_class; ?>" id="kds-ticket-<?php echo $id; ?>" data-order-id="<?php echo $id; ?>" data-status="<?php echo $status; ?>">
            <div class="kds-ticket-header <?php echo $header_class; ?>">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold fs-6">#<?php echo $id; ?></span>
                    <?php if ($total_items > 0) { ?>
                        <span class="badge bg-light text-muted border py-1 px-2" style="font-size: 0.72rem;">
                            <?php echo $total_qty; ?> <?php echo ($total_qty === 1 ? 'item' : 'items'); ?>
                        </span>
                    <?php } ?>
                </div>
                <span class="kds-age-pill bg-white text-muted border">
                    <i class="bi bi-stopwatch me-1"></i><?php echo $age; ?>
                </span>
            </div>
            <div class="kds-ticket-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <?php if ($order_type === "dine_in") { ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                            <i class="bi bi-grid-3x3-gap me-1"></i>Table <?php echo $table_num; ?>
                        </span>
                    <?php } elseif ($order_type === "pickup") { ?>
                        <span class="badge bg-secondary-subtle text-secondary border">
                            <i class="bi bi-bag me-1"></i>Pickup
                        </span>
                    <?php } elseif ($order_type === "delivery") { ?>
                        <span class="badge bg-secondary-subtle text-secondary border">
                            <i class="bi bi-truck me-1"></i>Delivery
                        </span>
                    <?php } ?>

                    <span class="text-muted small">
                        <?php echo date('h:i A', strtotime($order['created_at'])); ?>
                    </span>
                </div>

                <div class="small text-muted mb-1">
                    <i class="bi bi-person-badge me-1"></i>Server: <strong><?php echo $waiter; ?></strong>
                </div>
                <div class="small text-muted mb-3">
                    <i class="bi bi-person me-1"></i>Guest: <?php echo $customer; ?>
                </div>

                <!-- Primary Action Buttons Based on Status -->
                <div class="d-flex flex-column gap-1 mb-2">
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm w-100 fw-medium d-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-receipt"></i>
                        <span>View Items to Prepare</span>
                    </a>

                    <?php if ($status === 'pending') { ?>
                        <button type="button" 
                                class="btn btn-warning btn-sm w-100 fw-semibold text-dark d-flex align-items-center justify-content-center gap-1 kds-action-btn"
                                data-action="preparing"
                                data-order-id="<?php echo $id; ?>">
                            <i class="bi bi-fire"></i>
                            <span>Start Preparing</span>
                        </button>
                    <?php } elseif ($status === 'preparing') { ?>
                        <button type="button" 
                                class="btn btn-primary btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1 kds-action-btn"
                                data-action="ready"
                                data-order-id="<?php echo $id; ?>">
                            <i class="bi bi-check-circle"></i>
                            <span>Mark Ready</span>
                        </button>
                    <?php } elseif ($status === 'ready') { ?>
                        <button type="button" 
                                class="btn btn-success btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1 kds-action-btn"
                                data-action="completed"
                                data-order-id="<?php echo $id; ?>">
                            <i class="bi bi-check2-all"></i>
                            <span>Mark Completed / Served</span>
                        </button>
                    <?php } ?>
                </div>

                <!-- Secondary Cancellation Trigger -->
                <?php if (in_array($status, ['pending', 'preparing', 'ready'], true)) { ?>
                    <button type="button" 
                            class="btn btn-sm btn-link text-danger w-100 text-decoration-none py-1 d-flex align-items-center justify-content-center gap-1 kds-cancel-trigger"
                            data-order-id="<?php echo $id; ?>"
                            data-order-number="<?php echo $id; ?>"
                            data-customer="<?php echo $customer; ?>"
                            data-table="<?php echo ($order_type === 'dine_in' ? 'Table ' . $table_num : ucfirst($order_type)); ?>">
                        <i class="bi bi-x-circle"></i>
                        <span style="font-size: 0.8rem;">Cancel Order</span>
                    </button>
                <?php } ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
