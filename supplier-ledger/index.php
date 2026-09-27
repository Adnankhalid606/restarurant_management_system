<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

// Get suppliers
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM suppliers
    ORDER BY name ASC
";

$result = mysqli_query($conn, $sql);

$suppliers = [];
$total_suppliers = 0;
$with_phone = 0;
$with_address = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $suppliers[] = $row;
    $total_suppliers++;
    if (!empty($row["phone"])) {
        $with_phone++;
    }
    if (!empty($row["address"])) {
        $with_address++;
    }
}

$highlight_id = isset($_GET["supplier_id"]) ? (int) $_GET["supplier_id"] : 0;

$page_title = "Supplier Ledger Register";
$active_menu = "supplier-ledger";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Supplier Ledger</h2>
        <p class="page-header-subtitle">Vendor payables register, procurement order histories, and statement balances</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="../suppliers/index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-truck"></i>
            <span>Supplier Directory</span>
        </a>
        <a href="../purchases/index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-cart-check"></i>
            <span>Purchases</span>
        </a>
        <a href="../index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
    </div>
</div>

<!-- Ledger Summary Metrics -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Registered Vendors</span>
                <span class="fs-4 fw-bold text-dark font-monospace"><?php echo $total_suppliers; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-truck fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-paid">
            <div>
                <span class="text-muted small d-block">With Phone Contact</span>
                <span class="fs-4 fw-bold text-success font-monospace"><?php echo $with_phone; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-telephone fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">With Address / Warehouse</span>
                <span class="fs-4 fw-bold text-info font-monospace"><?php echo $with_address; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-geo-alt fs-4 text-info"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-outstanding">
            <div>
                <span class="text-muted small d-block">Ledger Access</span>
                <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1">Admin Authorized</span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-shield-lock fs-4 text-danger"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Supplier Register Card -->
<div class="pos-card">
    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title">
                <i class="bi bi-journal-bookmark text-primary me-2"></i>Vendor Payable Accounts
            </span>
            <span class="badge bg-light text-dark border font-monospace" id="supplierCountBadge">
                <?php echo $total_suppliers; ?> Total
            </span>
        </div>
        <div class="d-flex align-items-center gap-2" style="min-width: 260px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       id="supplierLedgerSearch" 
                       class="form-control border-start-0" 
                       placeholder="Filter by vendor, phone, address..." 
                       aria-label="Search supplier ledger">
            </div>
        </div>
    </div>

    <div class="pos-card-body p-0">
        <?php if ($total_suppliers === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-truck fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Suppliers Registered</h5>
                <p class="text-muted small mb-3">There are no supplier profiles in the database yet.</p>
                <a href="../suppliers/create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i>Register First Supplier
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="supplierTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Vendor #</th>
                            <th>Supplier Name</th>
                            <th>Phone Contact</th>
                            <th>Address / Location</th>
                            <th class="text-end pe-3" style="width: 160px;">Ledger Action</th>
                        </tr>
                    </thead>
                    <tbody id="supplierTableBody">
                        <?php foreach ($suppliers as $s) { 
                            $is_highlighted = ($highlight_id > 0 && $s["id"] === $highlight_id);
                            $phone_display = !empty($s["phone"]) ? htmlspecialchars($s["phone"]) : "";
                            $address_display = !empty($s["address"]) ? htmlspecialchars($s["address"]) : "";
                        ?>
                            <tr class="supplier-row <?php echo $is_highlighted ? 'table-primary' : ''; ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($s["name"])); ?>"
                                data-phone="<?php echo strtolower($phone_display); ?>"
                                data-address="<?php echo strtolower($address_display); ?>"
                                data-id="<?php echo $s["id"]; ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #SUP-<?php echo str_pad($s["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="supplier-ledger-avatar">
                                            <i class="bi bi-truck"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark d-block">
                                                <?php echo htmlspecialchars($s["name"]); ?>
                                            </span>
                                            <?php if ($is_highlighted) { ?>
                                                <span class="badge bg-primary text-white" style="font-size: 0.7rem;">Selected Target</span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($s["phone"])) { ?>
                                        <span class="text-dark small d-inline-flex align-items-center gap-1 font-monospace">
                                            <i class="bi bi-telephone text-muted"></i>
                                            <?php echo htmlspecialchars($s["phone"]); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small fst-italic">Not provided</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($s["address"])) { ?>
                                        <span class="text-dark small d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 280px;" title="<?php echo htmlspecialchars($s["address"]); ?>">
                                            <i class="bi bi-geo-alt text-muted"></i>
                                            <?php echo htmlspecialchars($s["address"]); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small fst-italic">Not recorded</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="view.php?id=<?php echo $s["id"]; ?>" 
                                       class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-journal-bookmark"></i>
                                        <span>View Statement</span>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr id="noSupplierMatchesRow" style="display: none;">
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> No supplier accounts match your search filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('supplierLedgerSearch');
    const tableBody = document.getElementById('supplierTableBody');
    const noMatchesRow = document.getElementById('noSupplierMatchesRow');
    const countBadge = document.getElementById('supplierCountBadge');

    if (searchInput && tableBody) {
        const rows = tableBody.querySelectorAll('.supplier-row');
        const total = rows.length;

        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(function(row) {
                const name = row.getAttribute('data-name') || '';
                const phone = row.getAttribute('data-phone') || '';
                const address = row.getAttribute('data-address') || '';
                const id = row.getAttribute('data-id') || '';

                if (query === '' || name.includes(query) || phone.includes(query) || address.includes(query) || id.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noMatchesRow) {
                noMatchesRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
            }

            if (countBadge) {
                countBadge.textContent = query !== '' ? `${visibleCount} of ${total}` : `${total} Total`;
            }
        });
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>