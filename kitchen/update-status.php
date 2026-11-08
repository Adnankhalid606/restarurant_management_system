<?php

require_once '../config/database.php';
require_once '../includes/role.php';
require_once __DIR__ . '/service.php';

requireRole(['admin', 'kitchen']);

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Get order
$sql = 'SELECT
            id,
            table_id,
            status
        FROM orders
        WHERE id = ?';

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($result);

if (!$order) {
    $_SESSION['error'] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $status = trim($_POST['status'] ?? '');
    $res = transitionKitchenOrderStatus($conn, $id, $status);

    if ($res['success']) {
        $_SESSION['flash_success'] = $res['message'];
        header('Location: view.php?id=' . $id);
        exit;
    } else {
        $error_msg = $res['message'];
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

<?php if (!empty($error_msg)) { ?>
    <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-4 border-danger-subtle" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
        <div class="small fw-medium"><?php echo htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

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
                        <?php if ($order['status'] === 'completed') { ?>
                            <i class="bi bi-check2-circle text-success me-1"></i>Completed Workflow
                        <?php } else { ?>
                            <i class="bi bi-clock-history me-1"></i>Awaiting Next Step
                        <?php } ?>
                    </div>
                </div>

                <?php if ($order['status'] === 'completed') { ?>
                    <div class="alert alert-success p-3 border-success-subtle mb-4">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <h6 class="mb-0 fw-bold text-dark">Order Already Completed</h6>
                        </div>
                        <p class="small text-secondary mb-0">
                            This order has completed the kitchen workflow. No further status changes are available.
                        </p>
                    </div>

                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary w-100 py-2 d-inline-flex align-items-center justify-content-center gap-1">
                        <i class="bi bi-arrow-left"></i>
                        <span>Back to Ticket</span>
                    </a>
                <?php } else { ?>
                    <form method="POST">
                        <?php echo csrf_field(); ?>
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
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>