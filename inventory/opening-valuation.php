<?php
/**
 * Opening Inventory Valuation Setup
 * Admin interface to set initial verified unit costs for raw materials
 * to establish Moving Weighted Average Cost (MAC) anchor without fabricating data.
 */

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $opening_costs = $_POST["unit_cost"] ?? [];
    $acting_user_id = (int) ($_SESSION["user_id"] ?? 0);

    if (!is_array($opening_costs) || empty($opening_costs)) {
        $error = "No inventory valuation data received.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Prepared statements for concurrency and audit safety
            $check_stmt = mysqli_prepare($conn, "
                SELECT id, name, current_stock, current_unit_cost 
                FROM raw_materials 
                WHERE id = ? 
                FOR UPDATE
            ");

            $history_stmt = mysqli_prepare($conn, "
                SELECT 
                    SUM(CASE WHEN type = 'purchase' THEN 1 ELSE 0 END) AS purchase_count,
                    SUM(CASE WHEN reference_type = 'opening_valuation' OR notes = 'Opening MAC unit cost valuation baseline' THEN 1 ELSE 0 END) AS baseline_count
                FROM inventory_transactions 
                WHERE raw_material_id = ?
            ");

            $update_stmt = mysqli_prepare($conn, "
                UPDATE raw_materials 
                SET current_unit_cost = ? 
                WHERE id = ? AND current_unit_cost = 0.0000
            ");

            $audit_stmt = mysqli_prepare($conn, "
                INSERT INTO inventory_transactions 
                (raw_material_id, type, quantity, unit_cost, total_cost, reference_type, reference_id, created_by, notes)
                VALUES (?, 'adjustment', 0.000, ?, 0.00, 'opening_valuation', NULL, ?, 'Opening MAC unit cost valuation baseline')
            ");

            $updated_count = 0;
            $skipped_count = 0;

            foreach ($opening_costs as $raw_material_id => $cost_input) {
                $mat_id = (int) $raw_material_id;
                $cost = round((float) $cost_input, 4);

                if ($mat_id <= 0 || $cost <= 0) {
                    continue;
                }

                // 1. Lock and inspect raw material atomically
                mysqli_stmt_bind_param($check_stmt, "i", $mat_id);
                mysqli_stmt_execute($check_stmt);
                $mat_res = mysqli_stmt_get_result($check_stmt);
                $mat = mysqli_fetch_assoc($mat_res);

                if (!$mat) {
                    continue;
                }

                $existing_cost = (float) $mat["current_unit_cost"];

                // Rule A: If non-zero unit cost is already established, reject overwrite
                if ($existing_cost > 0.0000) {
                    $skipped_count++;
                    continue;
                }

                // 2. Inspect transaction history for purchases or existing opening baseline
                mysqli_stmt_bind_param($history_stmt, "i", $mat_id);
                mysqli_stmt_execute($history_stmt);
                $hist_res = mysqli_stmt_get_result($history_stmt);
                $hist = mysqli_fetch_assoc($hist_res);

                $purchase_count = (int) ($hist["purchase_count"] ?? 0);
                $baseline_count = (int) ($hist["baseline_count"] ?? 0);

                // Rule B: If material has purchase history or opening baseline was already logged, reject
                if ($purchase_count > 0 || $baseline_count > 0) {
                    $skipped_count++;
                    continue;
                }

                // 3. Atomically update unit cost (only if still 0.0000)
                mysqli_stmt_bind_param($update_stmt, "di", $cost, $mat_id);
                mysqli_stmt_execute($update_stmt);

                if (mysqli_stmt_affected_rows($update_stmt) > 0) {
                    // 4. Record audit trail with explicit reference_type = 'opening_valuation'
                    mysqli_stmt_bind_param($audit_stmt, "idi", $mat_id, $cost, $acting_user_id);
                    mysqli_stmt_execute($audit_stmt);
                    $updated_count++;
                } else {
                    $skipped_count++;
                }
            }

            mysqli_commit($conn);
            $msg = "Successfully established opening valuation baseline for $updated_count raw material(s).";
            if ($skipped_count > 0) {
                $msg .= " ($skipped_count material(s) were protected from overwrite because a cost or transaction baseline already exists).";
            }
            $success = $msg;
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to save opening inventory valuation: " . $e->getMessage();
        }
    }
}

// Fetch all raw materials with current stock, active cost, purchase transaction status, and baseline status
$materials_res = mysqli_query($conn, "
    SELECT 
        rm.id, 
        rm.name, 
        rm.unit, 
        rm.current_stock, 
        rm.minimum_stock, 
        rm.current_unit_cost,
        (SELECT COUNT(*) FROM inventory_transactions it WHERE it.raw_material_id = rm.id AND it.type = 'purchase') AS purchase_tx_count,
        (SELECT COUNT(*) FROM inventory_transactions it WHERE it.raw_material_id = rm.id AND (it.reference_type = 'opening_valuation' OR it.notes = 'Opening MAC unit cost valuation baseline')) AS baseline_tx_count
    FROM raw_materials rm
    ORDER BY rm.name ASC
");
$materials = [];
$total_inventory_val = 0;
while ($row = mysqli_fetch_assoc($materials_res)) {
    $materials[] = $row;
    $total_inventory_val += ((float)$row['current_stock'] * (float)$row['current_unit_cost']);
}

$page_title = "Opening Inventory Valuation";
$active_menu = "inventory";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Opening Inventory Valuation</h2>
        <p class="page-header-subtitle">Establish verified unit costs for active raw materials to anchor Moving Weighted Average Costing (MAC)</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="materials.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Raw Materials</span>
        </a>
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

<div class="alert alert-info border py-2 px-3 mb-4 small d-flex align-items-center gap-2">
    <i class="bi bi-info-circle fs-5 flex-shrink-0 text-primary"></i>
    <div>
        <strong>Accounting Integrity Policy:</strong> Entering baseline unit costs establishes the active valuation for on-hand inventory. Future supplier purchases will recalculate this cost using the Moving Weighted Average Cost formula. Historical kitchen consumption records prior to this valuation are preserved with zero cost.
    </div>
</div>

<form method="POST" action="">
    <?php echo csrf_field(); ?>
    <div class="pos-card mb-4">
        <div class="pos-card-header d-flex justify-content-between align-items-center">
            <span class="pos-card-title">
                <i class="bi bi-calculator text-primary me-2"></i>Raw Material Cost Anchors
            </span>
            <span class="badge bg-light text-primary border font-monospace">
                Total Valuation: Rs. <?php echo number_format($total_inventory_val, 2); ?>
            </span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Raw Material</th>
                        <th style="width: 140px;">Base Unit</th>
                        <th style="width: 180px;" class="text-end">Current On-Hand</th>
                        <th style="width: 220px;">Active Unit Cost (Rs.)</th>
                        <th style="width: 200px;" class="text-end">Asset Value (Rs.)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $idx => $m) { 
                        $stock = (float) $m["current_stock"];
                        $cost = (float) $m["current_unit_cost"];
                        $val = round($stock * $cost, 2);
                        $has_purchases = ((int) $m["purchase_tx_count"]) > 0;
                        $has_baseline  = ((int) $m["baseline_tx_count"]) > 0;
                        $has_cost      = $cost > 0.0000;
                        $is_locked     = $has_purchases || $has_baseline || $has_cost;

                        $lock_reason = "";
                        if ($has_purchases) {
                            $lock_reason = "Active MAC Established (" . $m['purchase_tx_count'] . " purchase receipts)";
                        } elseif ($has_baseline) {
                            $lock_reason = "Opening Baseline Initialized";
                        } elseif ($has_cost) {
                            $lock_reason = "Active Cost Configured";
                        }
                    ?>
                        <tr class="<?php echo $is_locked ? 'table-light' : ''; ?>">
                            <td class="text-muted small"><?php echo $idx + 1; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($m["name"], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($is_locked) { ?>
                                    <div class="small">
                                        <span class="badge bg-secondary-subtle text-secondary border">
                                            <i class="bi bi-lock-fill me-1"></i><?php echo htmlspecialchars($lock_reason, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </div>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars($m["unit"], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="text-end font-monospace">
                                <?php echo number_format($stock, 3); ?>
                            </td>
                            <td>
                                <?php if ($is_locked) { ?>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted">Rs.</span>
                                        <input 
                                            type="text" 
                                            class="form-control pos-form-control font-monospace text-muted bg-light" 
                                            value="<?php echo htmlspecialchars(number_format($cost, 4, '.', ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            disabled
                                            title="Cost is permanently anchored. Initial valuation cannot be overwritten."
                                        >
                                    </div>
                                    <input type="hidden" name="unit_cost[<?php echo $m['id']; ?>]" value="<?php echo htmlspecialchars(number_format($cost, 4, '.', ''), ENT_QUOTES, 'UTF-8'); ?>">
                                <?php } else { ?>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light text-muted">Rs.</span>
                                        <input 
                                            type="number" 
                                            step="0.0001" 
                                            min="0.0001" 
                                            name="unit_cost[<?php echo $m['id']; ?>]" 
                                            class="form-control pos-form-control font-monospace fw-semibold" 
                                            value=""
                                            placeholder="0.0000"
                                            required
                                        >
                                    </div>
                                <?php } ?>
                            </td>
                            <td class="text-end font-monospace fw-semibold text-dark">
                                Rs. <?php echo number_format($val, 2); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <div class="pos-card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                Precision: 4 decimal places supported for fractional ingredient costing.
            </span>
            <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-2 shadow-sm">
                <i class="bi bi-save"></i>
                <span>Save Valuation Baseline</span>
            </button>
        </div>
    </div>
</form>

<?php
require_once "../includes/footer.php";
