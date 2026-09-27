<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if (!isset($_GET["id"])) {
    die("Supplier ID is required.");
}

$supplier_id = (int) $_GET["id"];

// Get supplier
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM suppliers
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $supplier_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$supplier = mysqli_fetch_assoc($result);

if (!$supplier) {
    die("Supplier not found.");
}

// Get supplier purchases
$sql = "
    SELECT
        id,
        purchase_date,
        total_amount,
        payment_status
    FROM purchases
    WHERE supplier_id = ?
    ORDER BY purchase_date DESC
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $supplier_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$purchases = [];

$total_purchases = 0;
$total_paid = 0;
$total_outstanding = 0;

// Calculate totals
while ($row = mysqli_fetch_assoc($result)) {
    $purchases[] = $row;

    $total_purchases += $row["total_amount"];

    if ($row["payment_status"] === "paid") {
        $total_paid += $row["total_amount"];
    } else {
        $total_outstanding += $row["total_amount"];
    }
}

$page_title = "Supplier Ledger — " . htmlspecialchars($supplier["name"]);
$active_menu = "supplier-ledger";

require_once "../includes/header.php";

?>

<!-- Print-Only Header -->
<div class="d-none d-print-block mb-4 pb-3 border-bottom">
    <div class="d-flex justify-content-between align-items-start">
        <div>
            <h2 class="fw-bold mb-1">RestoBar POS</h2>
            <div class="text-secondary small">Supplier Statement &amp; Accounts Payable Ledger</div>
            <div class="text-muted small mt-1">Generated: <?php echo date("F d, Y - h:i A"); ?></div>
        </div>
        <div class="text-end">
            <h4 class="mb-0 text-dark"><?php echo htmlspecialchars($supplier["name"]); ?></h4>
            <div class="text-muted small">Vendor Account: #SUP-<?php echo str_pad($supplier["id"], 3, '0', STR_PAD_LEFT); ?></div>
            <?php if (!empty($supplier["phone"])) { ?>
                <div class="text-muted small">Phone: <?php echo htmlspecialchars($supplier["phone"]); ?></div>
            <?php } ?>
            <?php if (!empty($supplier["address"])) { ?>
                <div class="text-muted small">Address: <?php echo htmlspecialchars($supplier["address"]); ?></div>
            <?php } ?>
        </div>
    </div>
</div>

<!-- Screen Page Header Bar -->
<div class="page-header-bar d-print-none">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="index.php" class="text-muted small text-decoration-none">
                <i class="bi bi-journal-bookmark me-1"></i>Supplier Ledger
            </a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold">Vendor Statement</span>
        </div>
        <h2 class="page-header-title">
            <?php echo htmlspecialchars($supplier["name"]); ?>
            <span class="badge bg-light text-dark border font-monospace fs-6 fw-normal ms-2">
                #SUP-<?php echo str_pad($supplier["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
        </h2>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i>
            <span>Ledger Register</span>
        </a>
        <a href="../suppliers/edit.php?id=<?php echo $supplier["id"]; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-pencil"></i>
            <span>Edit Supplier</span>
        </a>
        <a href="../purchases/create.php?supplier_id=<?php echo $supplier["id"]; ?>" class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-cart-plus"></i>
            <span>New Purchase</span>
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-printer"></i>
            <span>Print Statement</span>
        </button>
    </div>
</div>

<!-- Supplier Profile Summary Box -->
<div class="ledger-info-box mb-4">
    <div class="row align-items-center g-3">
        <div class="col-12 col-md-6">
            <div class="d-flex align-items-center gap-3">
                <div class="supplier-ledger-avatar" style="width: 48px; height: 48px; font-size: 1.5rem;">
                    <i class="bi bi-truck"></i>
                </div>
                <div>
                    <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($supplier["name"]); ?></h5>
                    <div class="d-flex flex-wrap align-items-center gap-3 text-muted small">
                        <span>
                            <i class="bi bi-telephone text-secondary me-1"></i>
                            <?php echo !empty($supplier["phone"]) ? htmlspecialchars($supplier["phone"]) : "No phone recorded"; ?>
                        </span>
                        <span>
                            <i class="bi bi-geo-alt text-secondary me-1"></i>
                            <?php echo !empty($supplier["address"]) ? htmlspecialchars($supplier["address"]) : "No address recorded"; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-6 text-md-end">
            <div>
                <span class="text-muted small d-block mb-1">Payable Status</span>
                <?php if ($total_outstanding > 0) { ?>
                    <span class="badge-subtle badge-status-cancelled p-2 rounded d-inline-flex align-items-center gap-1">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>Outstanding Payable Due</span>
                    </span>
                <?php } else { ?>
                    <span class="badge-subtle badge-status-ready p-2 rounded d-inline-flex align-items-center gap-1">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>Account Fully Settled</span>
                    </span>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<!-- Financial Summary KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Total Purchases</span>
                <span class="fs-4 fw-bold text-dark font-monospace">
                    Rs. <?php echo number_format($total_purchases, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cart-check fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-paid">
            <div>
                <span class="text-muted small d-block">Total Paid</span>
                <span class="fs-4 fw-bold text-success font-monospace">
                    Rs. <?php echo number_format($total_paid, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-cash-coin fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-outstanding">
            <div>
                <span class="text-muted small d-block">Outstanding Payable</span>
                <span class="fs-4 fw-bold <?php echo $total_outstanding > 0 ? 'text-danger' : 'text-success'; ?> font-monospace">
                    Rs. <?php echo number_format($total_outstanding, 2); ?>
                </span>
            </div>
            <div class="badge-subtle <?php echo $total_outstanding > 0 ? 'badge-status-cancelled' : 'badge-status-ready'; ?> p-2 rounded">
                <i class="bi <?php echo $total_outstanding > 0 ? 'bi-exclamation-triangle text-danger' : 'bi-check2-circle text-success'; ?> fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Purchase Invoices</span>
                <span class="fs-4 fw-bold text-secondary font-monospace">
                    <?php echo count($purchases); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-receipt fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Transactions Ledger Card -->
<div class="pos-card">
    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title">
                <i class="bi bi-clock-history text-primary me-2"></i>Procurement &amp; Purchase Ledger
            </span>
            <span class="badge bg-light text-dark border font-monospace">
                <?php echo count($purchases); ?> Records
            </span>
        </div>
        <div class="text-muted small d-print-none">
            Sorted by most recent purchase date
        </div>
    </div>

    <div class="pos-card-body p-0">
        <?php if (empty($purchases)) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-cart-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Purchases Found</h5>
                <p class="text-muted small mb-3">
                    No procurement orders have been registered for this vendor in the system yet.
                </p>
                <a href="../purchases/create.php?supplier_id=<?php echo $supplier["id"]; ?>" class="btn btn-primary btn-sm">
                    <i class="bi bi-cart-plus me-1"></i>Create First Purchase
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 120px;">Purchase #</th>
                            <th>Purchase Date</th>
                            <th>Payment Status</th>
                            <th class="text-end" style="width: 160px;">Invoice Amount</th>
                            <th class="text-end pe-3" style="width: 150px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $row) { 
                            $pay_status = strtolower(trim($row["payment_status"] ?? "unpaid"));
                            $amount = (float) ($row["total_amount"] ?? 0);
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #PO-<?php echo str_pad($row["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small d-block">
                                        <?php echo date('M d, Y', strtotime($row["purchase_date"])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pay_status === "paid") { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Paid</span>
                                        </span>
                                    <?php } elseif ($pay_status === "partial") { ?>
                                        <span class="badge-subtle badge-status-preparing d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-hourglass-split"></i>
                                            <span>Partial</span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge-subtle badge-status-cancelled d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-exclamation-circle-fill"></i>
                                            <span>Unpaid</span>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    Rs. <?php echo number_format($amount, 2); ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <?php if ($row["payment_status"] !== "paid") { ?>
                                            <a href="../purchases/pay.php?id=<?php echo $row["id"]; ?>" 
                                               class="btn btn-sm btn-success d-inline-flex align-items-center gap-1 shadow-sm">
                                                <i class="bi bi-credit-card"></i>
                                                <span>Pay</span>
                                            </a>
                                        <?php } else { ?>
                                            <span class="badge bg-light text-success border py-1 px-2">
                                                <i class="bi bi-check2 me-1"></i>Settled
                                            </span>
                                        <?php } ?>
                                        <a href="../purchases/view.php?id=<?php echo $row["id"]; ?>" 
                                           class="btn btn-sm btn-outline-secondary" 
                                           title="View Purchase Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="3" class="text-end fw-bold py-2">Total Purchases:</td>
                            <td class="text-end py-2 fw-bold text-dark font-monospace">
                                Rs. <?php echo number_format($total_purchases, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end fw-bold py-2 text-success">Total Paid:</td>
                            <td class="text-end py-2 fw-bold text-success font-monospace">
                                Rs. <?php echo number_format($total_paid, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr class="<?php echo $total_outstanding > 0 ? 'table-danger' : 'table-light'; ?>">
                            <td colspan="3" class="text-end fw-bold py-3 <?php echo $total_outstanding > 0 ? 'text-danger' : 'text-dark'; ?>">
                                Net Outstanding Payable:
                            </td>
                            <td class="text-end py-3 fw-bold <?php echo $total_outstanding > 0 ? 'text-danger' : 'text-success'; ?> font-monospace fs-5">
                                Rs. <?php echo number_format($total_outstanding, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>