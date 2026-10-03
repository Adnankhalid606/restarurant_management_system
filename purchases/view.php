<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


$sql = "SELECT
            purchases.id,
            suppliers.name AS supplier_name,
            purchases.total_amount,
            purchases.payment_status,
            purchases.purchase_date,
            purchases.created_at

        FROM purchases

        LEFT JOIN suppliers
            ON purchases.supplier_id = suppliers.id

        WHERE purchases.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$purchase = mysqli_fetch_assoc($result);


if (!$purchase) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


$sql = "SELECT
            purchase_items.quantity,
            purchase_items.unit_price,
            purchase_items.subtotal,
            raw_materials.name AS material_name,
            raw_materials.unit

        FROM purchase_items

        JOIN raw_materials
            ON purchase_items.raw_material_id = raw_materials.id

        WHERE purchase_items.purchase_id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

$items_list = [];
$total_item_count = 0;
while ($row = mysqli_fetch_assoc($items)) {
    $items_list[] = $row;
    $total_item_count++;
}

$status_clean = strtolower(trim($purchase["payment_status"] ?? "unpaid"));
$amount_val = (float)($purchase["total_amount"] ?? 0);

$page_title = "Purchase #" . $purchase["id"] . " - " . ($purchase["supplier_name"] ?? "Order");
$active_menu = "purchases";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-light text-dark border font-monospace">
                #PO-<?php echo str_pad($purchase["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
            <?php if ($status_clean === "paid") { ?>
                <span class="badge-subtle badge-status-ready">
                    <i class="bi bi-check-circle-fill me-1"></i>Paid In Full
                </span>
            <?php } elseif ($status_clean === "partial") { ?>
                <span class="badge-subtle badge-status-preparing">
                    <i class="bi bi-hourglass-split me-1"></i>Partially Paid
                </span>
            <?php } else { ?>
                <span class="badge-subtle badge-status-cancelled">
                    <i class="bi bi-exclamation-circle-fill me-1"></i>Payment Pending
                </span>
            <?php } ?>
        </div>
        <h2 class="page-header-title">Purchase Order: <?php echo htmlspecialchars($purchase["supplier_name"] ?? "Vendor Delivery"); ?></h2>
        <p class="page-header-subtitle">Official procurement receipt, line item breakdown &amp; stock intake record</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Purchases</span>
        </a>

        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Invoice</span>
        </button>

        <?php if ($status_clean !== "paid") { ?>
            <a href="pay.php?id=<?php echo $purchase["id"]; ?>" class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-credit-card"></i>
                <span>Mark Paid</span>
            </a>
        <?php } ?>

        <a href="delete.php?id=<?php echo $purchase["id"]; ?>" 
           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
           onclick="return confirm('Are you sure you want to delete Purchase #PO-<?php echo str_pad($purchase["id"], 3, '0', STR_PAD_LEFT); ?>? Note: Purchases with inventory movements cannot be deleted.');">
            <i class="bi bi-trash"></i>
            <span>Delete</span>
        </a>
    </div>
</div>

<!-- Specification Highlights -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Grand Total</span>
                <span class="fs-4 fw-bold text-dark font-monospace">Rs. <?php echo number_format($amount_val, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cash-stack fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Item Lines</span>
                <span class="fs-4 fw-bold text-primary"><?php echo $total_item_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-box-seam fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Purchase Date</span>
                <span class="fs-6 fw-bold text-dark d-block">
                    <?php echo formatDate($purchase["purchase_date"], '&mdash;'); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-calendar-event fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Stock Intake</span>
                <span class="fs-6 fw-bold text-success d-block">Credited</span>
                <span class="text-muted small" style="font-size: 0.75rem;">Inventory Updated</span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Column: Purchase Items Table -->
    <div class="col-12 col-lg-8">
        <div class="pos-card">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-list-check text-primary me-2"></i>Purchased Raw Materials &amp; Line Items
                </span>
                <span class="badge bg-light text-dark border">
                    Delivery Breakdown
                </span>
            </div>
            <div class="pos-card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3" style="width: 50px;">#</th>
                                <th>Raw Material</th>
                                <th class="text-end" style="width: 140px;">Quantity</th>
                                <th class="text-end" style="width: 140px;">Unit Price</th>
                                <th class="text-end pe-3" style="width: 150px;">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $idx = 1;
                            foreach ($items_list as $item) { 
                                $qty_display = rtrim(rtrim(number_format((float)$item["quantity"], 3), '0'), '.');
                                $unit_price_val = (float)($item["unit_price"] ?? 0);
                                $subtotal_val = (float)($item["subtotal"] ?? 0);
                            ?>
                                <tr>
                                    <td class="ps-3 text-muted fw-semibold"><?php echo $idx++; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="p-2 rounded bg-light text-secondary border">
                                                <i class="bi bi-box-seam"></i>
                                            </div>
                                            <div>
                                                <span class="fw-semibold text-dark">
                                                    <?php echo htmlspecialchars($item["material_name"]); ?>
                                                </span>
                                                <div class="text-muted small">Standard Raw Material</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end font-monospace">
                                        <strong><?php echo htmlspecialchars($qty_display); ?></strong>
                                        <span class="text-muted small ms-1"><?php echo htmlspecialchars($item["unit"]); ?></span>
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        Rs. <?php echo number_format($unit_price_val, 2); ?>
                                    </td>
                                    <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                        Rs. <?php echo number_format($subtotal_val, 2); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                        <tfoot class="table-light border-top">
                            <tr>
                                <td colspan="4" class="text-end fw-bold py-3">Total Purchase Amount:</td>
                                <td class="text-end pe-3 py-3 fw-bold text-primary font-monospace fs-5">
                                    Rs. <?php echo number_format($amount_val, 2); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            <div class="pos-card-footer bg-light p-3 border-top d-flex justify-content-between align-items-center">
                <span class="text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i>
                    Inventory stock credited upon order registration
                </span>
                <span class="text-muted small">
                    Total Line Items: <strong><?php echo $total_item_count; ?></strong>
                </span>
            </div>
        </div>
    </div>

    <!-- Side Column: Supplier & Audit Information -->
    <div class="col-12 col-lg-4">
        <!-- Supplier Card -->
        <div class="pos-card mb-4">
            <div class="pos-card-header">
                <span class="pos-card-title">
                    <i class="bi bi-truck text-primary me-2"></i>Vendor Specification
                </span>
            </div>
            <div class="pos-card-body p-3">
                <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom">
                    <div class="supplier-avatar">
                        <i class="bi bi-truck"></i>
                    </div>
                    <div>
                        <strong class="d-block text-dark fs-6"><?php echo htmlspecialchars($purchase["supplier_name"] ?? "Standard Vendor"); ?></strong>
                        <span class="text-muted small">Registered Procurement Partner</span>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Purchase Order</span>
                    <span class="font-monospace fw-semibold text-dark">#PO-<?php echo str_pad($purchase["id"], 3, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Payment Status</span>
                    <span class="text-uppercase fw-bold small <?php echo $status_clean === 'paid' ? 'text-success' : 'text-danger'; ?>">
                        <?php echo htmlspecialchars($purchase["payment_status"]); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <span class="text-muted small">Purchase Date</span>
                    <span class="text-dark small fw-semibold">
                        <?php echo formatDate($purchase["purchase_date"], '&mdash;'); ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center py-2">
                    <span class="text-muted small">Recorded Timestamp</span>
                    <span class="text-muted small">
                        <?php echo formatDateTime($purchase["created_at"]); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Inventory Tracking Information Card -->
        <div class="pos-card">
            <div class="pos-card-header">
                <span class="pos-card-title">
                    <i class="bi bi-arrow-left-right text-secondary me-2"></i>Inventory Movements
                </span>
            </div>
            <div class="pos-card-body p-3">
                <div class="recipe-flow-card mb-3">
                    <div class="d-flex align-items-start gap-2 mb-2">
                        <i class="bi bi-check2-circle text-success fs-5"></i>
                        <div>
                            <strong class="small d-block text-dark">Stock Increased</strong>
                            <span class="text-muted" style="font-size: 0.78rem;">
                                Active stock for <?php echo $total_item_count; ?> raw materials was credited upon PO creation.
                            </span>
                        </div>
                    </div>
                    <div class="d-flex align-items-start gap-2">
                        <i class="bi bi-file-earmark-text text-primary fs-5"></i>
                        <div>
                            <strong class="small d-block text-dark">Audit Trail Logged</strong>
                            <span class="text-muted" style="font-size: 0.78rem;">
                                Reference type <code>purchase</code> linked to PO #<?php echo $purchase["id"]; ?>.
                            </span>
                        </div>
                    </div>
                </div>

                <div class="text-muted small">
                    <i class="bi bi-info-circle text-primary me-1"></i>
                    To reverse inventory changes or correct stock levels, use the Inventory Stock Adjustments module.
                </div>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>