<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

// Get customers
$sql = "
    SELECT
        id,
        name,
        phone,
        address
    FROM customers
    ORDER BY name ASC
";

$result = mysqli_query($conn, $sql);

$customers = [];
$total_customers = 0;
$with_phone = 0;
$with_address = 0;

while ($row = mysqli_fetch_assoc($result)) {
    $customers[] = $row;
    $total_customers++;
    if (!empty($row["phone"])) {
        $with_phone++;
    }
    if (!empty($row["address"])) {
        $with_address++;
    }
}

$highlight_id = isset($_GET["customer_id"]) ? (int) $_GET["customer_id"] : 0;

$page_title = "Customer Ledger Register";
$active_menu = "customer-ledger";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Customer Ledger</h2>
        <p class="page-header-subtitle">Receivables register, billing histories, and account statements</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="../customers/index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-people"></i>
            <span>Customer Profiles</span>
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
                <span class="text-muted small d-block">Registered Customers</span>
                <span class="fs-4 fw-bold text-dark font-monospace"><?php echo $total_customers; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-journal-text fs-4 text-primary"></i>
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
                <span class="text-muted small d-block">With Physical Address</span>
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

<!-- Search & Ledger Register Card -->
<div class="pos-card">
    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title">
                <i class="bi bi-person-lines-fill text-primary me-2"></i>Customer Receivable Accounts
            </span>
            <span class="badge bg-light text-dark border font-monospace" id="accountCountBadge">
                <?php echo $total_customers; ?> Total
            </span>
        </div>
        <div class="d-flex align-items-center gap-2" style="min-width: 260px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       id="customerLedgerSearch" 
                       class="form-control border-start-0" 
                       placeholder="Filter by name, phone, address..." 
                       aria-label="Search ledger">
            </div>
        </div>
    </div>

    <div class="pos-card-body p-0">
        <?php if ($total_customers === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-people fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Customers Registered</h5>
                <p class="text-muted small mb-3">There are no customer records available in the system database yet.</p>
                <a href="../customers/create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Add New Customer
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="ledgerTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Account #</th>
                            <th>Customer Name</th>
                            <th>Phone Contact</th>
                            <th>Address / Location</th>
                            <th class="text-end pe-3" style="width: 160px;">Ledger Action</th>
                        </tr>
                    </thead>
                    <tbody id="ledgerTableBody">
                        <?php foreach ($customers as $c) { 
                            $is_highlighted = ($highlight_id > 0 && $c["id"] === $highlight_id);
                            $phone_display = !empty($c["phone"]) ? htmlspecialchars($c["phone"]) : "";
                            $address_display = !empty($c["address"]) ? htmlspecialchars($c["address"]) : "";
                        ?>
                            <tr class="ledger-row <?php echo $is_highlighted ? 'table-primary' : ''; ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($c["name"])); ?>"
                                data-phone="<?php echo strtolower($phone_display); ?>"
                                data-address="<?php echo strtolower($address_display); ?>"
                                data-id="<?php echo $c["id"]; ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #CUS-<?php echo str_pad($c["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="ledger-avatar">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark d-block">
                                                <?php echo htmlspecialchars($c["name"]); ?>
                                            </span>
                                            <?php if ($is_highlighted) { ?>
                                                <span class="badge bg-primary text-white" style="font-size: 0.7rem;">Selected Target</span>
                                            <?php } ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($c["phone"])) { ?>
                                        <span class="text-dark small d-inline-flex align-items-center gap-1 font-monospace">
                                            <i class="bi bi-telephone text-muted"></i>
                                            <?php echo htmlspecialchars($c["phone"]); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small fst-italic">Not provided</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($c["address"])) { ?>
                                        <span class="text-dark small d-inline-flex align-items-center gap-1 text-truncate" style="max-width: 280px;" title="<?php echo htmlspecialchars($c["address"]); ?>">
                                            <i class="bi bi-geo-alt text-muted"></i>
                                            <?php echo htmlspecialchars($c["address"]); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small fst-italic">Not recorded</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="view.php?id=<?php echo $c["id"]; ?>" 
                                       class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-journal-text"></i>
                                        <span>View Statement</span>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr id="noSearchMatchesRow" style="display: none;">
                            <td colspan="5" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> No customer accounts match your search filter.
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
    const searchInput = document.getElementById('customerLedgerSearch');
    const tableBody = document.getElementById('ledgerTableBody');
    const noMatchesRow = document.getElementById('noSearchMatchesRow');
    const countBadge = document.getElementById('accountCountBadge');

    if (searchInput && tableBody) {
        const rows = tableBody.querySelectorAll('.ledger-row');
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