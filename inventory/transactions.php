<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "kitchen"]);

$sql = "SELECT
            inventory_transactions.id,
            raw_materials.name AS material_name,
            raw_materials.unit,
            inventory_transactions.type,
            inventory_transactions.quantity,
            inventory_transactions.created_at

        FROM inventory_transactions

        JOIN raw_materials
            ON inventory_transactions.raw_material_id = raw_materials.id

        ORDER BY inventory_transactions.id DESC";

$result = mysqli_query($conn, $sql);

$transactions = [];
$total_count = 0;
$inflow_count = 0;
$outflow_count = 0;

while ($t = mysqli_fetch_assoc($result)) {
    $transactions[] = $t;
    $total_count++;
    if ($t['type'] === 'purchase' || $t['type'] === 'adjustment_in') {
        $inflow_count++;
    } else {
        $outflow_count++;
    }
}

$page_title = "Inventory Transactions Log";
$active_menu = "inventory";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Inventory Transactions</h2>
        <p class="page-header-subtitle">Stock movements audit ledger &amp; kitchen consumption log</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="materials.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-box-seam"></i>
            <span>Materials Directory</span>
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="add-transaction.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
                <i class="bi bi-plus-lg"></i>
                <span>Add Transaction</span>
            </a>
        <?php } ?>
    </div>
</div>

<!-- Transactions Summary Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-12 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Movements</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-arrow-left-right fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Stock Inflows (+)</span>
                <span class="fs-4 fw-bold text-success"><?php echo $inflow_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-box-arrow-in-down fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-sm-4">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Stock Outflows (&minus;)</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo $outflow_count; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-box-arrow-up fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search Controls Bar -->
<div class="pos-card mb-4">
    <div class="p-3">
        <div class="row g-2 align-items-center justify-content-between">
            <div class="col-12 col-md-5">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-search text-muted"></i>
                    </span>
                    <input 
                        type="text" 
                        class="form-control pos-form-control border-start-0" 
                        id="txSearch" 
                        placeholder="Search by material name..."
                    >
                </div>
            </div>

            <div class="col-12 col-md-auto d-flex align-items-center gap-3">
                <div class="btn-group btn-group-sm" role="group" id="txFilterGroup">
                    <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                        All (<?php echo $total_count; ?>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="inflow">
                        Inflows (<?php echo $inflow_count; ?>)
                    </button>
                    <button type="button" class="btn btn-outline-secondary" data-filter="outflow">
                        Outflows (<?php echo $outflow_count; ?>)
                    </button>
                </div>

                <div class="text-muted small d-none d-md-block">
                    <span id="filteredTxCount"><?php echo $total_count; ?></span> of <?php echo $total_count; ?> records
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($transactions)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center shadow-sm">
        <div class="text-muted mb-3">
            <i class="bi bi-clock-history fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Transactions Recorded</h5>
        <p class="text-muted small mb-4">There are currently no recorded stock adjustments or movements.</p>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="add-transaction.php" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i>Record First Transaction
            </a>
        <?php } ?>
    </div>
<?php } else { ?>
    <!-- Transactions Table -->
    <div class="pos-card overflow-hidden shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="txTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Raw Material</th>
                        <th style="width: 180px;">Transaction Type</th>
                        <th class="text-end" style="width: 160px;">Quantity Moved</th>
                        <th style="width: 200px;">Date &amp; Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $tx) {
                        $is_inflow = ($tx['type'] === 'purchase' || $tx['type'] === 'adjustment_in');
                        $flow_tag = $is_inflow ? 'inflow' : 'outflow';

                        $badge_class = match($tx['type']) {
                            'purchase' => 'bg-success-subtle text-success border-success-subtle',
                            'adjustment_in' => 'bg-info-subtle text-info border-info-subtle',
                            'consumption' => 'bg-secondary-subtle text-secondary border',
                            'adjustment_out' => 'bg-warning-subtle text-warning border-warning-subtle',
                            default => 'bg-light text-muted border',
                        };

                        $type_icon = match($tx['type']) {
                            'purchase' => 'bi-box-arrow-in-down',
                            'adjustment_in' => 'bi-plus-circle',
                            'consumption' => 'bi-fire',
                            'adjustment_out' => 'bi-dash-circle',
                            default => 'bi-circle',
                        };

                        $type_label = match($tx['type']) {
                            'purchase' => 'Purchase (+)',
                            'adjustment_in' => 'Adjustment In (+)',
                            'consumption' => 'Consumption (&minus;)',
                            'adjustment_out' => 'Adjustment Out (&minus;)',
                            default => htmlspecialchars($tx['type'], ENT_QUOTES, 'UTF-8'),
                        };
                    ?>
                        <tr class="tx-row" 
                            data-name="<?php echo htmlspecialchars(mb_strtolower($tx['material_name']), ENT_QUOTES, 'UTF-8'); ?>"
                            data-flow="<?php echo $flow_tag; ?>">
                            <td class="text-muted fw-semibold">#<?php echo $tx["id"]; ?></td>
                            <td>
                                <div class="fw-semibold text-dark d-flex align-items-center">
                                    <i class="bi bi-box-seam text-secondary me-2"></i>
                                    <span><?php echo htmlspecialchars($tx["material_name"], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?> border px-2 py-1">
                                    <i class="bi <?php echo $type_icon; ?> me-1"></i><?php echo $type_label; ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold fs-6 <?php echo $is_inflow ? 'text-success' : 'text-dark'; ?>">
                                    <?php echo $is_inflow ? '+' : '&minus;'; ?> <?php echo htmlspecialchars($tx["quantity"], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="text-muted small"><?php echo htmlspecialchars($tx["unit"], ENT_QUOTES, 'UTF-8'); ?></span>
                            </td>
                            <td class="text-muted small">
                                <i class="bi bi-clock me-1"></i><?php echo formatDateTime($tx["created_at"]); ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <!-- No Search Results Found Row (hidden by default) -->
        <div id="noTxResults" class="p-4 text-center text-muted d-none">
            <i class="bi bi-search fs-3 d-block mb-2 text-secondary opacity-50"></i>
            No matching transactions found
        </div>
    </div>

    <!-- Client-side Search and Filter Script -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('txSearch');
        const filterButtons = document.querySelectorAll('#txFilterGroup button');
        const rows = document.querySelectorAll('.tx-row');
        const countLabel = document.getElementById('filteredTxCount');
        const noResults = document.getElementById('noTxResults');

        let currentFilter = 'all';

        function applyFilters() {
            const query = searchInput.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(row => {
                const name = row.getAttribute('data-name') || '';
                const flow = row.getAttribute('data-flow') || '';

                const matchesQuery = !query || name.includes(query);
                const matchesFilter = (currentFilter === 'all') || (flow === currentFilter);

                if (matchesQuery && matchesFilter) {
                    row.classList.remove('d-none');
                    visibleCount++;
                } else {
                    row.classList.add('d-none');
                }
            });

            if (countLabel) {
                countLabel.textContent = visibleCount;
            }

            if (visibleCount === 0) {
                noResults.classList.remove('d-none');
            } else {
                noResults.classList.add('d-none');
            }
        }

        searchInput.addEventListener('input', applyFilters);

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.getAttribute('data-filter');
                applyFilters();
            });
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>