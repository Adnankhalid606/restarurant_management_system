<?php
/**
 * Unit of Measure (UOM) Management Module
 * Admin interface to manage database-driven units of measure, symbols, decimal policies, and statuses.
 */

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";
$success = "";

// Handle Form Submissions (Create, Edit, Toggle Status)
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $action = trim($_POST["action"] ?? "");

    // 1. ADD NEW UNIT
    if ($action === "create") {
        $name = trim($_POST["name"] ?? "");
        $symbol = trim($_POST["symbol"] ?? "");
        $allow_decimal = isset($_POST["allow_decimal"]) && (int)$_POST["allow_decimal"] === 1 ? 1 : 0;

        if ($name === "") {
            $error = "Unit name is required.";
        } elseif ($symbol === "") {
            $error = "Unit symbol is required.";
        } else {
            // Check uniqueness
            $check_stmt = mysqli_prepare($conn, "SELECT id, name, symbol FROM units_of_measure WHERE name = ? OR symbol = ?");
            mysqli_stmt_bind_param($check_stmt, "ss", $name, $symbol);
            mysqli_stmt_execute($check_stmt);
            $check_res = mysqli_stmt_get_result($check_stmt);

            if (mysqli_num_rows($check_res) > 0) {
                $dup = mysqli_fetch_assoc($check_res);
                if (strcasecmp($dup['name'], $name) === 0) {
                    $error = "A unit of measure with the name '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' already exists.";
                } else {
                    $error = "A unit of measure with the symbol '" . htmlspecialchars($symbol, ENT_QUOTES, 'UTF-8') . "' already exists.";
                }
            } else {
                $insert_stmt = mysqli_prepare($conn, "
                    INSERT INTO units_of_measure (name, symbol, allow_decimal, status) 
                    VALUES (?, ?, ?, 'active')
                ");
                mysqli_stmt_bind_param($insert_stmt, "ssi", $name, $symbol, $allow_decimal);
                if (mysqli_stmt_execute($insert_stmt)) {
                    $success = "Unit of measure '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . " ($symbol)' added successfully.";
                } else {
                    $error = "Failed to add unit: " . mysqli_error($conn);
                }
            }
        }
    }

    // 2. EDIT UNIT
    elseif ($action === "edit") {
        $id = (int) ($_POST["id"] ?? 0);
        $name = trim($_POST["name"] ?? "");
        $symbol = trim($_POST["symbol"] ?? "");
        $allow_decimal = isset($_POST["allow_decimal"]) && (int)$_POST["allow_decimal"] === 1 ? 1 : 0;
        $status = ($_POST["status"] ?? "active") === "inactive" ? "inactive" : "active";

        if ($id <= 0) {
            $error = "Invalid unit identifier.";
        } elseif ($name === "" || $symbol === "") {
            $error = "Unit name and symbol are required.";
        } else {
            // Fetch current unit details
            $current_stmt = mysqli_prepare($conn, "SELECT id, name, symbol, allow_decimal, status FROM units_of_measure WHERE id = ?");
            mysqli_stmt_bind_param($current_stmt, "i", $id);
            mysqli_stmt_execute($current_stmt);
            $current_unit = mysqli_fetch_assoc(mysqli_stmt_get_result($current_stmt));

            if (!$current_unit) {
                $error = "Unit of measure not found.";
            } else {
                // Check name or symbol collision with other units
                $check_stmt = mysqli_prepare($conn, "SELECT id, name, symbol FROM units_of_measure WHERE (name = ? OR symbol = ?) AND id != ?");
                mysqli_stmt_bind_param($check_stmt, "ssi", $name, $symbol, $id);
                mysqli_stmt_execute($check_stmt);
                $dup_res = mysqli_stmt_get_result($check_stmt);

                if (mysqli_num_rows($dup_res) > 0) {
                    $dup = mysqli_fetch_assoc($dup_res);
                    if (strcasecmp($dup['name'], $name) === 0) {
                        $error = "A unit with name '$name' already exists.";
                    } else {
                        $error = "A unit with symbol '$symbol' already exists.";
                    }
                } else {
                    // Safe decimal transition check:
                    // If switching from allow_decimal = 1 to allow_decimal = 0,
                    // check whether assigned materials have fractional stock, fractional minimum thresholds,
                    // or fractional quantities in active recipes.
                    if ((int)$current_unit['allow_decimal'] === 1 && $allow_decimal === 0) {
                        // 1. Check current stock on hand
                        $conflict_stmt = mysqli_prepare($conn, "
                            SELECT rm.name, rm.current_stock 
                            FROM raw_materials rm
                            WHERE rm.unit_id = ? AND MOD(rm.current_stock, 1) != 0
                            LIMIT 1
                        ");
                        mysqli_stmt_bind_param($conflict_stmt, "i", $id);
                        mysqli_stmt_execute($conflict_stmt);
                        $conflict_res = mysqli_stmt_get_result($conflict_stmt);
                        $conflict = mysqli_fetch_assoc($conflict_res);

                        if ($conflict) {
                            $error = "Cannot disable decimal quantities: Material '" . htmlspecialchars($conflict['name'], ENT_QUOTES, 'UTF-8') . "' has fractional stock (" . number_format($conflict['current_stock'], 3) . "). Fractional stock must be reconciled before switching to a whole-number policy.";
                        } else {
                            // 2. Check minimum stock reorder threshold
                            $min_stmt = mysqli_prepare($conn, "
                                SELECT rm.name, rm.minimum_stock 
                                FROM raw_materials rm
                                WHERE rm.unit_id = ? AND MOD(rm.minimum_stock, 1) != 0
                                LIMIT 1
                            ");
                            mysqli_stmt_bind_param($min_stmt, "i", $id);
                            mysqli_stmt_execute($min_stmt);
                            $min_res = mysqli_stmt_get_result($min_stmt);
                            $min_conflict = mysqli_fetch_assoc($min_res);

                            if ($min_conflict) {
                                $error = "Cannot disable decimal quantities: Material '" . htmlspecialchars($min_conflict['name'], ENT_QUOTES, 'UTF-8') . "' has a fractional minimum stock threshold (" . number_format($min_conflict['minimum_stock'], 3) . "). Threshold must be a whole number.";
                            } else {
                                // 3. Check recipe ingredient quantities
                                $recipe_stmt = mysqli_prepare($conn, "
                                    SELECT rm.name, ri.quantity, m.name AS menu_item_name
                                    FROM recipe_items ri
                                    JOIN raw_materials rm ON ri.raw_material_id = rm.id
                                    JOIN recipes r ON ri.recipe_id = r.id
                                    JOIN menu_items m ON r.menu_item_id = m.id
                                    WHERE rm.unit_id = ? AND MOD(ri.quantity, 1) != 0
                                    LIMIT 1
                                ");
                                mysqli_stmt_bind_param($recipe_stmt, "i", $id);
                                mysqli_stmt_execute($recipe_stmt);
                                $recipe_res = mysqli_stmt_get_result($recipe_stmt);
                                $rec_conflict = mysqli_fetch_assoc($recipe_res);

                                if ($rec_conflict) {
                                    $error = "Cannot disable decimal quantities: Recipe for '" . htmlspecialchars($rec_conflict['menu_item_name'], ENT_QUOTES, 'UTF-8') . "' uses fractional " . htmlspecialchars($rec_conflict['name'], ENT_QUOTES, 'UTF-8') . " (" . number_format($rec_conflict['quantity'], 3) . "). Recipe formulation must be adjusted first.";
                                }
                            }
                        }
                    }

                    if ($error === "") {
                        $update_stmt = mysqli_prepare($conn, "
                            UPDATE units_of_measure 
                            SET name = ?, symbol = ?, allow_decimal = ?, status = ?
                            WHERE id = ?
                        ");
                        mysqli_stmt_bind_param($update_stmt, "ssisi", $name, $symbol, $allow_decimal, $status, $id);
                        if (mysqli_stmt_execute($update_stmt)) {
                            // Also update the legacy unit text column on assigned raw_materials to keep in sync
                            $sync_stmt = mysqli_prepare($conn, "UPDATE raw_materials SET unit = ? WHERE unit_id = ?");
                            mysqli_stmt_bind_param($sync_stmt, "si", $symbol, $id);
                            mysqli_stmt_execute($sync_stmt);

                            $success = "Unit of measure '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' updated successfully.";
                        } else {
                            $error = "Failed to update unit: " . mysqli_error($conn);
                        }
                    }
                }
            }
        }
    }

    // 3. TOGGLE STATUS
    elseif ($action === "toggle_status") {
        $id = (int) ($_POST["id"] ?? 0);
        if ($id > 0) {
            $stmt = mysqli_prepare($conn, "
                UPDATE units_of_measure 
                SET status = IF(status = 'active', 'inactive', 'active') 
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, "i", $id);
            if (mysqli_stmt_execute($stmt)) {
                $success = "Unit status toggled successfully.";
            } else {
                $error = "Failed to update status.";
            }
        }
    }
}

// Fetch all UOMs with count of assigned raw materials
$uoms_query = "
    SELECT 
        uom.id, 
        uom.name, 
        uom.symbol, 
        uom.allow_decimal, 
        uom.status, 
        uom.created_at,
        COUNT(rm.id) AS assigned_materials_count
    FROM units_of_measure uom
    LEFT JOIN raw_materials rm ON uom.id = rm.unit_id
    GROUP BY uom.id
    ORDER BY uom.status ASC, uom.name ASC
";
$uoms_res = mysqli_query($conn, $uoms_query);
$uoms = [];
while ($row = mysqli_fetch_assoc($uoms_res)) {
    $uoms[] = $row;
}

$page_title = "Units of Measure (UOM)";
$active_menu = "inventory";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Units of Measure (UOM)</h2>
        <p class="page-header-subtitle">Standardized measurement units and decimal quantity enforcement policies</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="materials.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Inventory</span>
        </a>
        <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#addUomModal">
            <i class="bi bi-plus-circle"></i>
            <span>Add New Unit</span>
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

<!-- UOM Directory Card -->
<div class="pos-card mb-4">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-rulers text-primary me-2"></i>Measurement Units Library
        </span>
        <span class="badge bg-light text-primary border font-monospace">
            Total Units: <?php echo count($uoms); ?>
        </span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Unit Name</th>
                    <th style="width: 120px;">Symbol</th>
                    <th style="width: 180px;">Quantity Policy</th>
                    <th style="width: 140px;" class="text-center">Assigned Items</th>
                    <th style="width: 120px;" class="text-center">Status</th>
                    <th style="width: 120px;" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($uoms)) { ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">
                            No units of measure found.
                        </td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($uoms as $idx => $u) { 
                        $is_active = $u["status"] === "active";
                        $allow_dec = (int)$u["allow_decimal"] === 1;
                        $assigned  = (int)$u["assigned_materials_count"];
                    ?>
                        <tr class="<?php echo !$is_active ? 'table-light opacity-75' : ''; ?>">
                            <td class="text-muted small"><?php echo $idx + 1; ?></td>
                            <td>
                                <strong class="text-dark"><?php echo htmlspecialchars($u["name"], ENT_QUOTES, 'UTF-8'); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                    <?php echo htmlspecialchars($u["symbol"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($allow_dec) { ?>
                                    <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-123"></i> Fractional Allowed
                                    </span>
                                <?php } else { ?>
                                    <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-hash"></i> Whole Numbers Only
                                    </span>
                                <?php } ?>
                            </td>
                            <td class="text-center">
                                <?php if ($assigned > 0) { ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                        <?php echo $assigned; ?> material<?php echo $assigned > 1 ? 's' : ''; ?>
                                    </span>
                                <?php } else { ?>
                                    <span class="text-muted small">0 materials</span>
                                <?php } ?>
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
                                        title="Edit Unit"
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8'); ?>)"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" action="" class="d-inline" onsubmit="return confirm('Change status of <?php echo htmlspecialchars(addslashes($u['name'])); ?>?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                        <button 
                                            type="submit" 
                                            class="btn btn-sm <?php echo $is_active ? 'btn-outline-danger' : 'btn-outline-success'; ?>" 
                                            title="<?php echo $is_active ? 'Deactivate Unit' : 'Activate Unit'; ?>"
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

<!-- Modal: Add New Unit -->
<div class="modal fade" id="addUomModal" tabindex="-1" aria-labelledby="addUomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title" id="addUomModalLabel"><i class="bi bi-plus-circle text-primary me-2"></i>Add Unit of Measure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="create_name" class="form-label pos-form-label mb-1">Unit Name <span class="text-danger">*</span></label>
                    <input type="text" id="create_name" name="name" class="form-control pos-form-control" placeholder="e.g. Kilogram, Piece, Litre" required>
                </div>
                <div class="mb-3">
                    <label for="create_symbol" class="form-label pos-form-label mb-1">Symbol / Abbreviation <span class="text-danger">*</span></label>
                    <input type="text" id="create_symbol" name="symbol" class="form-control pos-form-control font-monospace" placeholder="e.g. kg, pcs, L" required>
                </div>
                <div class="mb-3">
                    <label class="form-label pos-form-label mb-1">Decimal Policy</label>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="create_allow_decimal" name="allow_decimal" value="1" checked>
                        <label class="form-check-label small" for="create_allow_decimal">
                            Allow fractional / decimal quantities (e.g. 2.5 kg)
                        </label>
                    </div>
                    <div class="text-muted small mt-1">
                        When unchecked, the application strictly prevents decimal quantities (e.g. 1 bun, not 1.5 buns).
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-save"></i>
                    <span>Save Unit</span>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Unit -->
<div class="modal fade" id="editUomModal" tabindex="-1" aria-labelledby="editUomModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" class="modal-content">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" id="edit_id" name="id" value="">
            <div class="modal-header">
                <h5 class="modal-title" id="editUomModalLabel"><i class="bi bi-pencil text-primary me-2"></i>Edit Unit of Measure</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="edit_name" class="form-label pos-form-label mb-1">Unit Name <span class="text-danger">*</span></label>
                    <input type="text" id="edit_name" name="name" class="form-control pos-form-control" required>
                </div>
                <div class="mb-3">
                    <label for="edit_symbol" class="form-label pos-form-label mb-1">Symbol / Abbreviation <span class="text-danger">*</span></label>
                    <input type="text" id="edit_symbol" name="symbol" class="form-control pos-form-control font-monospace" required>
                </div>
                <div class="mb-3">
                    <label class="form-label pos-form-label mb-1">Decimal Policy</label>
                    <div class="form-check form-switch mt-1">
                        <input class="form-check-input" type="checkbox" role="switch" id="edit_allow_decimal" name="allow_decimal" value="1">
                        <label class="form-check-label small" for="edit_allow_decimal">
                            Allow fractional / decimal quantities
                        </label>
                    </div>
                    <div class="text-muted small mt-1">
                        Notice: Disabling decimals will be rejected if any assigned raw material currently carries a fractional stock balance.
                    </div>
                </div>
                <div class="mb-3">
                    <label for="edit_status" class="form-label pos-form-label mb-1">Status</label>
                    <select id="edit_status" name="status" class="form-select pos-form-control">
                        <option value="active">Active (Available for assignments)</option>
                        <option value="inactive">Inactive (Preserved in records, blocked from new assignments)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
                    <i class="bi bi-save"></i>
                    <span>Update Unit</span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(uom) {
    document.getElementById('edit_id').value = uom.id;
    document.getElementById('edit_name').value = uom.name;
    document.getElementById('edit_symbol').value = uom.symbol;
    document.getElementById('edit_allow_decimal').checked = parseInt(uom.allow_decimal) === 1;
    document.getElementById('edit_status').value = uom.status;

    const modal = new bootstrap.Modal(document.getElementById('editUomModal'));
    modal.show();
}
</script>

<?php
require_once "../includes/footer.php";
