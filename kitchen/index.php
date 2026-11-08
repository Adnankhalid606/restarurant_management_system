<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once __DIR__ . "/ticket-card.php";

requireRole(["admin", "kitchen"]);

// Helper for human-readable order age
function getKitchenOrderAge($datetime_str) {
    if (empty($datetime_str)) return '-';
    $time = strtotime($datetime_str);
    $diff = max(0, time() - $time);
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } else {
        $hrs = floor($diff / 3600);
        return $hrs . ' hr' . ($hrs > 1 ? 's' : '') . ' ago';
    }
}

// Get kitchen orders with item counts aggregated
$sql = "SELECT
            orders.id,
            orders.order_type,
            orders.status,
            orders.created_at,
            customers.name AS customer_name,
            restaurant_tables.table_number,
            users.name AS waiter_name,
            COUNT(order_items.id) AS total_items,
            COALESCE(SUM(order_items.quantity), 0) AS total_quantity

        FROM orders

        LEFT JOIN customers
            ON orders.customer_id = customers.id

        LEFT JOIN restaurant_tables
            ON orders.table_id = restaurant_tables.id

        LEFT JOIN users
            ON orders.waiter_id = users.id

        LEFT JOIN order_items
            ON orders.id = order_items.order_id

        WHERE orders.status IN ('pending', 'preparing', 'ready')

        GROUP BY orders.id
        ORDER BY orders.id ASC";

$result = mysqli_query($conn, $sql);

$page_title = "Kitchen Display";
$active_menu = "kitchen";

require_once "../includes/header.php";

// Group active orders by status in memory
$active_orders = [];
$status_counts = [
    'pending' => 0,
    'preparing' => 0,
    'ready' => 0
];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $active_orders[] = $row;
        if (isset($status_counts[$row['status']])) {
            $status_counts[$row['status']]++;
        }
    }
}
$total_active = count($active_orders);

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">
            <i class="bi bi-fire text-danger me-2"></i>Kitchen Display System (KDS)
        </h2>
        <p class="page-header-subtitle">
            Active Kitchen Queue &bull; <span id="total-active-count"><?php echo $total_active; ?></span> Tickets In Progress
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" onclick="window.location.reload()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-clockwise"></i>
            <span>Refresh Queue</span>
        </button>
    </div>
</div>

<!-- Global KDS Toast / Feedback Notification -->
<div id="kdsAlertContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 1090;"></div>

<!-- Empty Queue Notice (Toggled dynamically or statically) -->
<div id="kdsEmptyState" class="pos-card text-center py-5 <?php echo ($total_active === 0) ? '' : 'd-none'; ?>">
    <i class="bi bi-check2-circle display-4 text-success opacity-75 d-block mb-3"></i>
    <h4 class="fw-bold text-dark">Kitchen Queue Clear</h4>
    <p class="text-muted small mb-0">There are no pending, preparing, or ready orders right now.</p>
</div>

<!-- 3-Stage Kitchen Kanban Pipeline -->
<div class="row g-3 <?php echo ($total_active === 0) ? 'd-none' : ''; ?>" id="kdsKanbanBoard">
    <!-- Stage 1: PENDING (New Orders) -->
    <div class="col-12 col-md-4">
        <div class="kds-column" id="kds-column-pending">
            <div class="kds-column-header">
                <span class="fw-bold text-dark fs-6">
                    <i class="bi bi-clock text-warning me-1"></i>New Orders
                </span>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-semibold" id="counter-pending">
                    <?php echo $status_counts['pending']; ?> Pending
                </span>
            </div>

            <div class="kds-tickets-container" id="tickets-container-pending">
                <?php
                $pending_found = false;
                foreach ($active_orders as $order) {
                    if ($order['status'] !== 'pending') continue;
                    $pending_found = true;
                    echo renderKdsTicketCard($order);
                }
                ?>
            </div>

            <div class="text-center py-4 text-muted small empty-placeholder <?php echo $pending_found ? 'd-none' : ''; ?>" id="empty-pending">
                <i class="bi bi-check2-circle fs-3 d-block mb-1 text-secondary opacity-50"></i>
                No pending tickets
            </div>
        </div>
    </div>

    <!-- Stage 2: PREPARING (In Preparation) -->
    <div class="col-12 col-md-4">
        <div class="kds-column" id="kds-column-preparing">
            <div class="kds-column-header">
                <span class="fw-bold text-dark fs-6">
                    <i class="bi bi-fire text-primary me-1"></i>In Preparation
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-semibold" id="counter-preparing">
                    <?php echo $status_counts['preparing']; ?> Cooking
                </span>
            </div>

            <div class="kds-tickets-container" id="tickets-container-preparing">
                <?php
                $preparing_found = false;
                foreach ($active_orders as $order) {
                    if ($order['status'] !== 'preparing') continue;
                    $preparing_found = true;
                    echo renderKdsTicketCard($order);
                }
                ?>
            </div>

            <div class="text-center py-4 text-muted small empty-placeholder <?php echo $preparing_found ? 'd-none' : ''; ?>" id="empty-preparing">
                <i class="bi bi-slash-circle fs-3 d-block mb-1 text-secondary opacity-50"></i>
                No orders currently cooking
            </div>
        </div>
    </div>

    <!-- Stage 3: READY (Ready for Serving) -->
    <div class="col-12 col-md-4">
        <div class="kds-column" id="kds-column-ready">
            <div class="kds-column-header">
                <span class="fw-bold text-dark fs-6">
                    <i class="bi bi-check2-all text-success me-1"></i>Ready for Serving
                </span>
                <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold" id="counter-ready">
                    <?php echo $status_counts['ready']; ?> Plated
                </span>
            </div>

            <div class="kds-tickets-container" id="tickets-container-ready">
                <?php
                $ready_found = false;
                foreach ($active_orders as $order) {
                    if ($order['status'] !== 'ready') continue;
                    $ready_found = true;
                    echo renderKdsTicketCard($order);
                }
                ?>
            </div>

            <div class="text-center py-4 text-muted small empty-placeholder <?php echo $ready_found ? 'd-none' : ''; ?>" id="empty-ready">
                <i class="bi bi-inbox fs-3 d-block mb-1 text-secondary opacity-50"></i>
                No plated orders waiting
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirm Order Cancellation -->
<div class="modal fade" id="kdsCancelModal" tabindex="-1" aria-labelledby="kdsCancelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title d-flex align-items-center gap-2 text-danger" id="kdsCancelModalLabel">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span>Cancel Kitchen Order</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-4">
                <p class="fs-6 mb-2">
                    Are you sure you want to cancel <strong class="text-dark" id="cancelOrderTargetText">Order #--</strong>?
                </p>
                <div class="alert alert-warning py-2 px-3 small border-warning-subtle mb-3">
                    <i class="bi bi-info-circle me-1"></i>
                    This will remove the ticket from the active kitchen workflow and release any associated dining table.
                </div>
                <div class="small text-muted" id="cancelOrderMetaText">
                    Customer: Walk-in &bull; Dine-in
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                    Go Back
                </button>
                <button type="button" class="btn btn-danger d-flex align-items-center gap-2" id="kdsConfirmCancelBtn">
                    <i class="bi bi-x-circle"></i>
                    <span>Confirm Cancellation</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Client-side Interactive KDS Workflow Controller -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    let activeCancelOrderId = null;
    let cancelModalInstance = null;

    const modalEl = document.getElementById('kdsCancelModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        cancelModalInstance = new bootstrap.Modal(modalEl);
    }

    // Helper: Display Toast Notification
    function showKdsNotice(message, isSuccess = true) {
        const container = document.getElementById('kdsAlertContainer');
        if (!container) return;

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${isSuccess ? 'success' : 'danger'} alert-dismissible fade show shadow-sm d-flex align-items-center gap-2`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.innerHTML = `
            <i class="bi ${isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'} fs-5 flex-shrink-0"></i>
            <div class="small fw-medium">${message}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        container.appendChild(alertDiv);

        setTimeout(() => {
            alertDiv.classList.remove('show');
            setTimeout(() => alertDiv.remove(), 250);
        }, 4000);
    }

    // Helper: Update Column Counters & Empty State Placeholders
    function updateKdsColumnStates() {
        const stages = ['pending', 'preparing', 'ready'];
        let totalCount = 0;

        stages.forEach(st => {
            const container = document.getElementById(`tickets-container-${st}`);
            const counter = document.getElementById(`counter-${st}`);
            const placeholder = document.getElementById(`empty-${st}`);

            const count = container ? container.querySelectorAll('.kds-ticket').length : 0;
            totalCount += count;

            if (counter) {
                if (st === 'pending') counter.textContent = `${count} Pending`;
                else if (st === 'preparing') counter.textContent = `${count} Cooking`;
                else if (st === 'ready') counter.textContent = `${count} Plated`;
            }

            if (placeholder) {
                if (count === 0) {
                    placeholder.classList.remove('d-none');
                } else {
                    placeholder.classList.add('d-none');
                }
            }
        });

        const totalActiveEl = document.getElementById('total-active-count');
        if (totalActiveEl) totalActiveEl.textContent = totalCount;

        const board = document.getElementById('kdsKanbanBoard');
        const emptyState = document.getElementById('kdsEmptyState');
        if (totalCount === 0) {
            if (board) board.classList.add('d-none');
            if (emptyState) emptyState.classList.remove('d-none');
        } else {
            if (board) board.classList.remove('d-none');
            if (emptyState) emptyState.classList.add('d-none');
        }
    }

    // Helper: Mutate Ticket HTML to Match New Target Stage
    function updateTicketForStage(ticket, targetStatus) {
        ticket.setAttribute('data-status', targetStatus);
        const header = ticket.querySelector('.kds-ticket-header');
        const orderId = ticket.getAttribute('data-order-id');

        // Reset and apply stage classes
        ticket.className = 'kds-ticket';
        if (header) {
            header.className = 'kds-ticket-header';
            if (targetStatus === 'preparing') {
                ticket.classList.add('border-primary-subtle');
                header.classList.add('bg-primary-subtle', 'text-primary', 'border-primary-subtle');
            } else if (targetStatus === 'ready') {
                ticket.classList.add('border-success-subtle');
                header.classList.add('bg-success-subtle', 'text-success', 'border-success-subtle');
            } else {
                header.classList.add('bg-light');
            }
        }

        // Update primary action button
        const actionBtn = ticket.querySelector('.kds-action-btn');
        if (actionBtn) {
            if (targetStatus === 'preparing') {
                actionBtn.className = 'btn btn-primary btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1 kds-action-btn';
                actionBtn.setAttribute('data-action', 'ready');
                actionBtn.innerHTML = '<i class="bi bi-check-circle"></i><span>Mark Ready</span>';
            } else if (targetStatus === 'ready') {
                actionBtn.className = 'btn btn-success btn-sm w-100 fw-semibold d-flex align-items-center justify-content-center gap-1 kds-action-btn';
                actionBtn.setAttribute('data-action', 'completed');
                actionBtn.innerHTML = '<i class="bi bi-check2-all"></i><span>Mark Completed / Served</span>';
            }
        }
    }

    // Perform Async Status Update
    function executeStatusTransition(orderId, targetStatus, triggerBtn) {
        if (triggerBtn) {
            triggerBtn.disabled = true;
            triggerBtn.dataset.originalHtml = triggerBtn.innerHTML;
            triggerBtn.innerHTML = `<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Updating...`;
        }

        fetch('action.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                order_id: orderId,
                status: targetStatus
            })
        })
        .then(response => {
            return response.json().then(data => ({ status: response.status, body: data }));
        })
        .then(({ status, body }) => {
            if (status !== 200 || !body.success) {
                throw new Error(body.message || 'Workflow transition failed.');
            }

            const ticket = document.getElementById(`kds-ticket-${orderId}`);
            if (ticket) {
                if (targetStatus === 'completed' || targetStatus === 'cancelled') {
                    // Smooth exit animation
                    ticket.style.transition = 'opacity 0.25s ease, transform 0.25s ease';
                    ticket.style.opacity = '0';
                    ticket.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        ticket.remove();
                        updateKdsColumnStates();
                    }, 250);
                } else {
                    // Move ticket to target stage container
                    const targetContainer = document.getElementById(`tickets-container-${targetStatus}`);
                    if (targetContainer) {
                        updateTicketForStage(ticket, targetStatus);
                        targetContainer.appendChild(ticket);
                        updateKdsColumnStates();
                    }
                }
            } else {
                updateKdsColumnStates();
            }

            showKdsNotice(body.message, true);
        })
        .catch(err => {
            console.error('KDS Action Error:', err);
            showKdsNotice(err.message, false);
            if (triggerBtn) {
                triggerBtn.disabled = false;
                if (triggerBtn.dataset.originalHtml) {
                    triggerBtn.innerHTML = triggerBtn.dataset.originalHtml;
                }
            }
        });
    }

    // Event Delegation: Primary Workflow Buttons (Start Preparing, Mark Ready, Mark Completed)
    document.addEventListener('click', function(e) {
        const actionBtn = e.target.closest('.kds-action-btn');
        if (actionBtn) {
            e.preventDefault();
            const orderId = actionBtn.getAttribute('data-order-id');
            const targetStatus = actionBtn.getAttribute('data-action');
            if (orderId && targetStatus) {
                executeStatusTransition(orderId, targetStatus, actionBtn);
            }
            return;
        }

        // Cancellation Trigger: Open Modal
        const cancelBtn = e.target.closest('.kds-cancel-trigger');
        if (cancelBtn) {
            e.preventDefault();
            activeCancelOrderId = cancelBtn.getAttribute('data-order-id');
            const orderNum = cancelBtn.getAttribute('data-order-number');
            const cust = cancelBtn.getAttribute('data-customer');
            const table = cancelBtn.getAttribute('data-table');

            const targetText = document.getElementById('cancelOrderTargetText');
            if (targetText) targetText.textContent = `Order #${orderNum}`;

            const metaText = document.getElementById('cancelOrderMetaText');
            if (metaText) metaText.textContent = `Customer: ${cust} • ${table}`;

            if (cancelModalInstance) {
                cancelModalInstance.show();
            }
        }
    });

    // Modal Confirmation: Confirm Order Cancellation
    const confirmCancelBtn = document.getElementById('kdsConfirmCancelBtn');
    if (confirmCancelBtn) {
        confirmCancelBtn.addEventListener('click', function() {
            if (!activeCancelOrderId) return;
            const orderIdToCancel = activeCancelOrderId;
            activeCancelOrderId = null;

            if (cancelModalInstance) {
                cancelModalInstance.hide();
            }

            const ticket = document.getElementById(`kds-ticket-${orderIdToCancel}`);
            const cancelTrigger = ticket ? ticket.querySelector('.kds-cancel-trigger') : null;

            executeStatusTransition(orderIdToCancel, 'cancelled', cancelTrigger);
        });
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>
