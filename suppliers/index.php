<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$sql = "SELECT *
        FROM suppliers
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$suppliers = [];
$total_suppliers = 0;
$with_phone = 0;
$with_address = 0;

while ($supplier = mysqli_fetch_assoc($result)) {
    $suppliers[] = $supplier;
    $total_suppliers++;
    if (!empty(trim($supplier["phone"] ?? ""))) {
        $with_phone++;
    }
    if (!empty(trim($supplier["address"] ?? ""))) {
        $with_address++;
    }
}

$page_title = "Suppliers Directory";
$active_menu = "suppliers";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Suppliers Directory</h2>
        <p class="page-header-subtitle">Approved raw material vendors, procurement partners &amp; supply ledger records</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Add Supplier</span>
        </a>
    </div>
</div>

<?php 
$err_msg = $_SESSION["error"] ?? $_SESSION["flash_error"] ?? "";
$warn_msg = $_SESSION["warning"] ?? $_SESSION["flash_warning"] ?? "";
$succ_msg = $_SESSION["success"] ?? $_SESSION["flash_success"] ?? "";
?>

<?php if (!empty($err_msg)) { ?>
    <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
        <div><?php echo htmlspecialchars($err_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <?php unset($_SESSION["error"], $_SESSION["flash_error"]); ?>
<?php } ?>

<?php if (!empty($warn_msg)) { ?>
    <div class="alert alert-warning d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="bi bi-exclamation-circle-fill fs-5 me-2 flex-shrink-0"></i>
        <div><?php echo htmlspecialchars($warn_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <?php unset($_SESSION["warning"], $_SESSION["flash_warning"]); ?>
<?php } ?>

<?php if (!empty($succ_msg)) { ?>
    <div class="alert alert-success d-flex align-items-center mb-4 shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill fs-5 me-2 flex-shrink-0"></i>
        <div><?php echo htmlspecialchars($succ_msg, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
    <?php unset($_SESSION["success"], $_SESSION["flash_success"]); ?>
<?php } ?>

<!-- Supplier Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Active Suppliers</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_suppliers; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-truck fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Phone Contacts</span>
                <span class="fs-4 fw-bold text-primary"><?php echo $with_phone; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-telephone fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Addresses on File</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo $with_address; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-geo-alt fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Ledger Status</span>
                <span class="fs-4 fw-bold text-success">Synced</span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-journal-bookmark-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Live Controls Bar -->
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
                        id="supplierSearch" 
                        placeholder="Search by vendor name, phone, or address..."
                    >
                </div>
            </div>
            <div class="col-12 col-md-auto text-md-end text-muted small">
                Showing <strong id="visibleCount"><?php echo $total_suppliers; ?></strong> of <?php echo $total_suppliers; ?> suppliers
            </div>
        </div>
    </div>
</div>

<!-- Suppliers Directory Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-building text-primary me-2"></i>Registered Vendor Partners
        </span>
        <span class="badge bg-light text-dark border">
            Purchasing &amp; Supply Network
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if ($total_suppliers === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-truck fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Suppliers Registered</h5>
                <p class="text-muted small mb-3">
                    Add raw material vendors to track purchases, maintain supplier ledgers, and manage restocking.
                </p>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Add First Supplier
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="suppliersTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Supplier ID</th>
                            <th>Vendor / Company</th>
                            <th>Phone Contact</th>
                            <th>Warehouse / Address</th>
                            <th style="width: 130px;">Ledger</th>
                            <th class="text-end pe-3" style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($suppliers as $supplier) { ?>
                            <tr class="supplier-table-row">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #SUP-<?php echo str_pad($supplier["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="supplier-avatar">
                                            <i class="bi bi-truck"></i>
                                        </div>
                                        <div>
                                            <a href="edit.php?id=<?php echo $supplier["id"]; ?>" class="fw-semibold text-dark text-decoration-none supplier-name-text">
                                                <?php echo htmlspecialchars($supplier["name"], ENT_QUOTES, 'UTF-8'); ?>
                                            </a>
                                            <div class="text-muted small">Registered Vendor Partner</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty(trim($supplier["phone"] ?? ""))) { ?>
                                        <a href="tel:<?php echo htmlspecialchars($supplier["phone"], ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-telephone text-primary"></i>
                                            <span class="font-monospace"><?php echo htmlspecialchars($supplier["phone"], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </a>
                                    <?php } else { ?>
                                        <span class="text-muted small">&mdash; No phone &mdash;</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty(trim($supplier["address"] ?? ""))) { ?>
                                        <div class="d-flex align-items-start gap-1 text-muted small" style="max-width: 320px;">
                                            <i class="bi bi-geo-alt text-secondary mt-1 flex-shrink-0"></i>
                                            <span class="text-truncate-2"><?php echo htmlspecialchars($supplier["address"], ENT_QUOTES, 'UTF-8'); ?></span>
                                        </div>
                                    <?php } else { ?>
                                        <span class="text-muted small">&mdash; No address on record &mdash;</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <a href="../supplier-ledger/view.php?id=<?php echo $supplier["id"]; ?>" 
                                       class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1"
                                       title="View Supplier Account Ledger">
                                        <i class="bi bi-journal-text text-primary"></i>
                                        <span>Ledger</span>
                                    </a>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit.php?id=<?php echo $supplier["id"]; ?>" 
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                                           title="Edit Supplier Details">
                                            <i class="bi bi-pencil"></i>
                                            <span>Edit</span>
                                        </a>

                                        <a href="delete.php?id=<?php echo $supplier["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                                           title="Delete Supplier"
                                           onclick="return confirm('Are you sure you want to delete supplier <?php echo htmlspecialchars(addslashes($supplier['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Suppliers referenced by purchase records cannot be deleted.');">
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
                <div class="fw-semibold text-dark">No matching suppliers found</div>
                <div class="text-muted small">Try searching for a different vendor name, phone number, or address</div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('supplierSearch');
    const tableRows = document.querySelectorAll('.supplier-table-row');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noSearchResults');
    const suppliersTable = document.getElementById('suppliersTable');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const query = this.value.trim().toLowerCase();
            let matches = 0;

            tableRows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                    matches++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (visibleCount) {
                visibleCount.textContent = matches.toString();
            }

            if (noResults && suppliersTable) {
                if (matches === 0 && tableRows.length > 0) {
                    noResults.classList.remove('d-none');
                    suppliersTable.classList.add('d-none');
                } else {
                    noResults.classList.add('d-none');
                    suppliersTable.classList.remove('d-none');
                }
            }
        });
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>