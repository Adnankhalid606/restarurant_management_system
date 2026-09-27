<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

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

        ORDER BY purchases.id DESC";

$result = mysqli_query($conn, $sql);

$purchases = [];
$total_purchases = 0;
$total_spend = 0.0;
$paid_count = 0;
$pending_count = 0;

while ($purchase = mysqli_fetch_assoc($result)) {
    $purchases[] = $purchase;
    $total_purchases++;
    $amount = (float)($purchase["total_amount"] ?? 0);
    $total_spend += $amount;
    
    $status = strtolower(trim($purchase["payment_status"] ?? ""));
    if ($status === "paid") {
        $paid_count++;
    } else {
        $pending_count++;
    }
}

$page_title = "Purchases Directory";
$active_menu = "purchases";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Procurement &amp; Purchases</h2>
        <p class="page-header-subtitle">Raw material purchase orders, vendor invoices &amp; inventory intake records</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>New Purchase</span>
        </a>
    </div>
</div>

<!-- Purchase Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Purchases</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_purchases; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cart-check fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Procurement Spend</span>
                <span class="fs-4 fw-bold text-dark">Rs. <?php echo number_format($total_spend, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Paid Invoices</span>
                <span class="fs-4 fw-bold text-success"><?php echo $paid_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Pending / Partial</span>
                <span class="fs-4 fw-bold text-danger"><?php echo $pending_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-clock-history fs-4 text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter Controls -->
<div class="pos-card mb-4">
    <div class="pos-card-body p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control pos-form-control border-start-0" 
                        id="purchaseSearch" 
                        placeholder="Search by supplier, reference #, or amount..."
                    >
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                <select id="statusFilter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Payment Statuses</option>
                    <option value="paid">Paid</option>
                    <option value="partial">Partial</option>
                    <option value="unpaid">Unpaid</option>
                </select>
                <span class="text-muted small">
                    Showing <strong id="visibleCount"><?php echo $total_purchases; ?></strong> of <?php echo $total_purchases; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Purchases Directory Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-receipt text-primary me-2"></i>Purchase Orders &amp; Receipts
        </span>
        <span class="badge bg-light text-dark border">
            Auto-Updates Stock on Creation
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if ($total_purchases === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-cart-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Purchase Orders Found</h5>
                <p class="text-muted small mb-3">
                    Record raw material intake from suppliers to increase inventory stock and track vendor payables.
                </p>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Create First Purchase
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="purchasesTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">PO #</th>
                            <th>Supplier / Vendor</th>
                            <th>Purchase Date</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center" style="width: 130px;">Payment Status</th>
                            <th class="text-end pe-3" style="width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($purchases as $p) { 
                            $status_clean = strtolower(trim($p["payment_status"] ?? "unpaid"));
                            $amount_val = (float)($p["total_amount"] ?? 0);
                        ?>
                            <tr class="purchase-table-row" data-status="<?php echo htmlspecialchars($status_clean); ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #PO-<?php echo str_pad($p["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="supplier-avatar" style="width: 32px; height: 32px; font-size: 0.95rem;">
                                            <i class="bi bi-truck"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark purchase-supplier-name">
                                                <?php echo htmlspecialchars($p["supplier_name"] ?? "Standard Vendor"); ?>
                                            </span>
                                            <div class="text-muted small" style="font-size: 0.75rem;">
                                                Logged <?php echo formatDate($p["created_at"]); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small d-block">
                                        <?php echo formatDate($p["purchase_date"], '&mdash;'); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-dark font-monospace">
                                        Rs. <?php echo number_format($amount_val, 2); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php if ($status_clean === "paid") { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Paid</span>
                                        </span>
                                    <?php } elseif ($status_clean === "partial") { ?>
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
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="view.php?id=<?php echo $p["id"]; ?>" 
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                                           title="View Purchase Details">
                                            <i class="bi bi-eye"></i>
                                            <span>View</span>
                                        </a>

                                        <?php if ($status_clean !== "paid") { ?>
                                            <a href="pay.php?id=<?php echo $p["id"]; ?>" 
                                               class="btn btn-outline-success btn-sm d-inline-flex align-items-center gap-1"
                                               title="Mark Purchase as Paid">
                                                <i class="bi bi-credit-card"></i>
                                                <span>Pay</span>
                                            </a>
                                        <?php } ?>

                                        <a href="delete.php?id=<?php echo $p["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                                           title="Delete Purchase"
                                           onclick="return confirm('Are you sure you want to delete Purchase #PO-<?php echo str_pad($p["id"], 3, '0', STR_PAD_LEFT); ?>? Note: Purchases with recorded inventory movements cannot be deleted.');">
                                            <i class="bi bi-trash"></i>
                                            <span>Delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Client-side No Results State -->
            <div id="noSearchResults" class="text-center py-5 d-none">
                <i class="bi bi-search text-muted fs-2 mb-2 d-block"></i>
                <div class="fw-semibold text-dark">No matching purchases found</div>
                <div class="text-muted small">Try adjusting your search keywords or payment status filter</div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('purchaseSearch');
    const statusFilter = document.getElementById('statusFilter');
    const tableRows = document.querySelectorAll('.purchase-table-row');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noSearchResults');
    const purchasesTable = document.getElementById('purchasesTable');

    function filterPurchases() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const selectedStatus = statusFilter ? statusFilter.value.trim().toLowerCase() : '';
        let matches = 0;

        tableRows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const rowStatus = row.getAttribute('data-status') || '';

            const matchesText = text.includes(query);
            const matchesStatus = (selectedStatus === '') || (rowStatus === selectedStatus);

            if (matchesText && matchesStatus) {
                row.style.display = '';
                matches++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount) {
            visibleCount.textContent = matches.toString();
        }

        if (noResults && purchasesTable) {
            if (matches === 0 && tableRows.length > 0) {
                noResults.classList.remove('d-none');
                purchasesTable.classList.add('d-none');
            } else {
                noResults.classList.add('d-none');
                purchasesTable.classList.remove('d-none');
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterPurchases);
    }
    if (statusFilter) {
        statusFilter.addEventListener('change', filterPurchases);
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>