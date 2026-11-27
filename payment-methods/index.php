<?php
/**
 * Payment Methods Management Module
 * Admin interface to manage payment methods with fixed classifications:
 * Cash, Bank Transfer, Mobile Wallet, Card, Other.
 */

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";
$success = "";

$allowed_classifications = ['Cash', 'Bank Transfer', 'Mobile Wallet', 'Card', 'Other'];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $action = trim($_POST["action"] ?? "");

    // 1. CREATE PAYMENT METHOD
    if ($action === "create") {
        $name = trim($_POST["name"] ?? "");
        $classification = trim($_POST["classification"] ?? "");

        if ($name === "") {
            $error = "Payment method name is required.";
        } elseif (!in_array($classification, $allowed_classifications, true)) {
            $error = "Invalid classification selected. Please choose from the predefined classifications.";
        } else {
            // Check duplicate name
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM payment_methods WHERE name = ?");
            mysqli_stmt_bind_param($check_stmt, "s", $name);
            mysqli_stmt_execute($check_stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
                $error = "A payment method with the name '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' already exists.";
            } else {
                $insert_stmt = mysqli_prepare($conn, "
                    INSERT INTO payment_methods (name, classification, status) 
                    VALUES (?, ?, 'active')
                ");
                mysqli_stmt_bind_param($insert_stmt, "ss", $name, $classification);
                if (mysqli_stmt_execute($insert_stmt)) {
                    $success = "Payment method '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' added successfully.";
                } else {
                    $error = "Failed to add payment method: " . mysqli_error($conn);
                }
            }
        }
    }

    // 2. EDIT PAYMENT METHOD
    elseif ($action === "edit") {
        $id = (int) ($_POST["id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $classification = trim($_POST["classification"] ?? "");
        $status = ($_POST["status"] ?? "active") === "inactive" ? "inactive" : "active";

        if ($id <= 0) {
            $error = "Invalid payment method identifier.";
        } elseif ($name === "") {
            $error = "Payment method name is required.";
        } elseif (!in_array($classification, $allowed_classifications, true)) {
            $error = "Invalid classification selected.";
        } else {
            // Check duplicate name
            $check_stmt = mysqli_prepare($conn, "SELECT id FROM payment_methods WHERE name = ? AND id != ?");
            mysqli_stmt_bind_param($check_stmt, "si", $name, $id);
            mysqli_stmt_execute($check_stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
                $error = "Another payment method with the name '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' already exists.";
            } else {
                $update_stmt = mysqli_prepare($conn, "
                    UPDATE payment_methods 
                    SET name = ?, classification = ?, status = ?
                    WHERE id = ?
                ");
                mysqli_stmt_bind_param($update_stmt, "sssi", $name, $classification, $status, $id);
                if (mysqli_stmt_execute($update_stmt)) {
                    $success = "Payment method '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
                } else {
                    $error = "Failed to update payment method: " . mysqli_error($conn);
                }
            }
        }
    }

    // 3. TOGGLE STATUS
    elseif ($action === "toggle_status") {
        $id = (int) ($_POST["id"] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "
                UPDATE payment_methods 
                SET status = IF(status = 'active', 'inactive', 'active') 
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Payment method status toggled successfully.";
            } else {
                $error = "Failed to update status.";
            }
        }
    }
}

// Fetch payment methods with usage count in invoice_payments
$methods_res = mysqli_query($conn, "
    SELECT 
        pm.id, 
        pm.name, 
        pm.classification, 
        pm.status, 
        pm.created_at,
        COUNT(ip.id) AS usage_count,
        COALESCE(SUM(ip.amount), 0) AS total_settled
    FROM payment_methods pm
    LEFT JOIN invoice_payments ip ON pm.id = ip.payment_method_id
    GROUP BY pm.id
    ORDER BY pm.status ASC, pm.classification ASC, pm.name ASC
");
$methods = [];
while ($row = mysqli_fetch_assoc($methods_res)) {
    $methods[] = $row;
}

$page_title = "Payment Methods";
$active_menu = "payment_methods";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Payment Methods</h2>
        <p class="page-header-subtitle">Configure accepted customer settlement accounts and fixed classifications</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="../invoices/index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Invoices</span>
        </a>
        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addMethodModal">
            <i class="bi bi-plus-circle"></i>
            <span>Add Payment Method</span>
        </button>
    </div>
</div>

<?php if ($error !== "") { ?>
    <div class="alert alert-danger border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 text-danger"></i>
        <div><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

<?php if ($success !== "") { ?>
    <div class="alert alert-success border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill fs-5 flex-shrink-0 text-success"></i>
        <div><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

<div class="pos-card mb-4">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-wallet2 text-primary me-2"></i>Configured Settlement Channels
        </span>
        <span class="badge bg-light text-primary border font-monospace">
            Total Channels: <?php echo count($methods); ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Method Name</th>
                    <th style="width: 180px;">Classification</th>
                    <th style="width: 140px;" class="text-center">Recorded Payments</th>
                    <th style="width: 160px;" class="text-end">Total Settled</th>
                    <th style="width: 120px;" class="text-center">Status</th>
                    <th style="width: 120px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($methods)) { ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No payment methods configured.
                        </td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($methods as $idx => $m) { 
                        $is_active = $m["status"] === "active";
                        $badge_class = match($m["classification"]) {
                            'Cash' => 'bg-success-subtle text-success border-success-subtle',
                            'Bank Transfer' => 'bg-primary-subtle text-primary border-primary-subtle',
                            'Mobile Wallet' => 'bg-info-subtle text-info-emphasis border-info-subtle',
                            'Card' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                            default => 'bg-secondary-subtle text-secondary border-secondary-subtle'
                        };
                    ?>
                        <tr class="<?php echo !$is_active ? 'table-light opacity-75' : ''; ?>">
                            <td class="text-muted small"><?php echo $idx + 1; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($m["name"], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?> border px-2 py-1">
                                    <?php echo htmlspecialchars($m["classification"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="text-center font-monospace">
                                <?php echo (int)$m["usage_count"]; ?>
                            </td>
                            <td class="text-end font-monospace fw-semibold text-dark">
                                Rs. <?php echo number_format((float)$m["total_settled"], 2); ?>
                            </td>
                            <td class="text-center">
                                <?php if ($is_active) { ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        Active
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-light text-muted border">
                                        Inactive
                                    </span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button 
                                        type="button" 
                                        class="btn btn-sm btn-outline-secondary" 
                                        title="Edit Method"
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($m), ENT_QUOTES, 'UTF-8'); ?>)"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline m-0 p-0" onsubmit="return confirm('Change status of <?php echo htmlspecialchars(addslashes($m['name'])); ?>?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $m['id']; ?>">
                                        <button 
                                            type="submit" 
                                            class="btn btn-sm <?php echo $is_active ? 'btn-outline-danger' : 'btn-outline-success'; ?>" 
                                            title="<?php echo $is_active ? 'Deactivate Method' : 'Activate Method'; ?>"
                                        >
                                            <i class="bi <?php echo $is_active ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Add New Payment Method -->
<div class="modal fade" id="addMethodModal" tabindex="-1" aria-labelledby="addMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title" id="addMethodModalLabel"><i class="bi bi-plus-circle text-primary me-2"></i>Add Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="create_name" class="form-label pos-form-label mb-1">Method Name <span class="text-danger">*</span></label>
                    <input type="text" id="create_name" name="name" class="form-control pos-form-control" placeholder="e.g. Cash Drawer, HBL Bank, JazzCash" required>
                </div>
                <div class="mb-3">
                    <label for="create_classification" class="form-label pos-form-label mb-1">Classification Type <span class="text-danger">*</span></label>
                    <select id="create_classification" name="classification" class="form-select pos-form-control" required>
                        <option value="">-- Select Predefined Classification --</option>
                        <?php foreach ($allowed_classifications as $cls) { ?>
                            <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                        <?php } ?>
                    </select>
                    <div class="form-text text-muted small">Fixed classification category for financial aggregation and audit tracking.</div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-save"></i>
                    <span>Save Method</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Payment Method -->
<div class="modal fade" id="editMethodModal" tabindex="-1" aria-labelledby="editMethodModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id" name="id" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="editMethodModalLabel"><i class="bi bi-pencil text-primary me-2"></i>Edit Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="edit_name" class="form-label pos-form-label mb-1">Method Name <span class="text-danger">*</span></label>
                    <input type="text" id="edit_name" name="name" class="form-control pos-form-control" required>
                </div>
                <div class="mb-3">
                    <label for="edit_classification" class="form-label pos-form-label mb-1">Classification Type <span class="text-danger">*</span></label>
                    <select id="edit_classification" name="classification" class="form-select pos-form-control" required>
                        <?php foreach ($allowed_classifications as $cls) { ?>
                            <option value="<?php echo $cls; ?>"><?php echo $cls; ?></option>
                        <?php } ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="edit_status" class="form-label pos-form-label mb-1">Status</label>
                    <select id="edit_status" name="status" class="form-select pos-form-control">
                        <option value="active">Active (Available for settlement)</option>
                        <option value="inactive">Inactive (Historical records preserved, unavailable for new settlements)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-save"></i>
                    <span>Update Method</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(method) {
    document.getElementById('edit_id').value = method.id;
    document.getElementById('edit_name').value = method.name;
    document.getElementById('edit_classification').value = method.classification;
    document.getElementById('edit_status').value = method.status;

    const modal = new bootstrap.Modal(document.getElementById('editMethodModal'));
    modal.show();
}
</script>

<?php
require_once "../includes/footer.php";
