<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT * FROM customers ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$customers = [];
$total_customers = 0;
$with_phone = 0;
$with_address = 0;

while ($c = mysqli_fetch_assoc($result)) {
    $customers[] = $c;
    $total_customers++;
    if (!empty($c['phone'])) {
        $with_phone++;
    }
    if (!empty($c['address'])) {
        $with_address++;
    }
}

$page_title = "Customers Directory";
$active_menu = "customers";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Customers Directory</h2>
        <p class="page-header-subtitle">Guest profiles, contact information &amp; service directory</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-person-plus"></i>
            <span>Add Customer</span>
        </a>
    </div>
</div>

<!-- Customer Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Registered Guests</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_customers; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-people fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Phone on Record</span>
                <span class="fs-4 fw-bold text-primary"><?php echo $with_phone; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-telephone fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Address on File</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo $with_address; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-geo-alt fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Controls Bar -->
<div class="pos-card mb-4">
    <div class="p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-6 col-lg-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control pos-form-control border-start-0" 
                        id="customerSearch" 
                        placeholder="Search by name, phone or address..."
                    >
                </div>
            </div>
            <div class="col-auto text-muted small">
                <span id="filteredCount"><?php echo $total_customers; ?></span> of <?php echo $total_customers; ?> customers
            </div>
        </div>
    </div>
</div>

<?php if (empty($customers)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center shadow-sm">
        <div class="text-muted mb-3">
            <i class="bi bi-people fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Customers Registered</h5>
        <p class="text-muted small mb-4">There are currently no customer profiles in the system.</p>
        <a href="create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-person-plus me-1"></i>Add First Customer
        </a>
    </div>
<?php } else { ?>
    <!-- Customers Table -->
    <div class="pos-card overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="customersTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Customer Name</th>
                        <th>Phone Number</th>
                        <th>Address</th>
                        <th style="width: 140px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $c) { 
                        $first_letter = mb_strtoupper(mb_substr($c['name'], 0, 1, 'UTF-8'));
                    ?>
                        <tr class="customer-row" 
                            data-search="<?php echo htmlspecialchars(mb_strtolower($c['name'] . ' ' . ($c['phone'] ?? '') . ' ' . ($c['address'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="text-muted fw-semibold">#<?php echo $c["id"]; ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="customer-avatar">
                                        <?php echo htmlspecialchars($first_letter, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                    <span class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($c["name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <?php if (!empty($c["phone"])) { ?>
                                    <a href="tel:<?php echo htmlspecialchars($c["phone"], ENT_QUOTES, 'UTF-8'); ?>" class="text-decoration-none text-dark d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-telephone text-primary small"></i>
                                        <span><?php echo htmlspecialchars($c["phone"], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </a>
                                <?php } else { ?>
                                    <span class="text-muted fst-italic small">Not provided</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if (!empty($c["address"])) { ?>
                                    <span class="text-muted small d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-geo-alt text-secondary small"></i>
                                        <span><?php echo htmlspecialchars($c["address"], ENT_QUOTES, 'UTF-8'); ?></span>
                                    </span>
                                <?php } else { ?>
                                    <span class="text-muted fst-italic small">Not provided</span>
                                <?php } ?>
                            </td>
                            <td class="text-end">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                        <a href="../customer-ledger/index.php?customer_id=<?php echo $c['id']; ?>" 
                                           class="btn btn-outline-secondary btn-sm" 
                                           title="Customer Financial Ledger">
                                            <i class="bi bi-journal-text"></i>
                                        </a>
                                    <?php } ?>

                                    <a href="edit.php?id=<?php echo $c["id"]; ?>" 
                                       class="btn btn-outline-secondary btn-sm" 
                                       title="Edit Profile">
                                        <i class="bi bi-pencil"></i>
                                    </a>

                                    <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                        <a href="delete.php?id=<?php echo $c["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm" 
                                           title="Delete Customer"
                                           onclick="return confirm('Are you sure you want to delete customer <?php echo htmlspecialchars(addslashes($c['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Customers with existing orders cannot be deleted.');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        
        <!-- No Search Results Found Row (hidden by default) -->
        <div id="noSearchResults" class="p-4 text-center text-muted d-none">
            <i class="bi bi-search fs-3 d-block mb-2 text-secondary opacity-50"></i>
            No matching customers found
        </div>
    </div>

    <!-- Client-side Search Filter Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('customerSearch');
        const rows = document.querySelectorAll('.customer-row');
        const countLabel = document.getElementById('filteredCount');
        const noResults = document.getElementById('noSearchResults');
        const total = rows.length;

        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const searchData = row.getAttribute('data-search') || '';
                if (!query || searchData.includes(query)) {
                    row.classList.remove('d-none');
                    visibleCount++;
                } else {
                    row.classList.add('d-none');
                }
            });

            countLabel.textContent = visibleCount;

            if (visibleCount === 0 && query !== '') {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>