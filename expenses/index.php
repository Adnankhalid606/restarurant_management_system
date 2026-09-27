<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$sql = "SELECT *
        FROM expenses
        ORDER BY expense_date DESC, id DESC";

$result = mysqli_query($conn, $sql);

$expenses = [];
$total_expenses = 0;
$total_amount = 0.0;
$heads_count = [];
$current_month = date('Y-m');
$current_month_total = 0.0;

while ($expense = mysqli_fetch_assoc($result)) {
    $expenses[] = $expense;
    $total_expenses++;
    $amt = (float)($expense["amount"] ?? 0);
    $total_amount += $amt;
    
    $head = trim($expense["expense_head"] ?? "");
    if ($head !== "") {
        $heads_count[$head] = ($heads_count[$head] ?? 0) + 1;
    }
    
    if (!empty($expense["expense_date"]) && substr($expense["expense_date"], 0, 7) === $current_month) {
        $current_month_total += $amt;
    }
}

$page_title = "Expenses Register";
$active_menu = "expenses";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Operating Expenses</h2>
        <p class="page-header-subtitle">General operational overheads, utility payments, maintenance &amp; recurring outlays</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>Add Expense</span>
        </a>
    </div>
</div>

<!-- Expense Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Recorded Entries</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $total_expenses; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-receipt fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Outlay</span>
                <span class="fs-4 fw-bold text-dark font-monospace">Rs. <?php echo number_format($total_amount, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">This Month (<?php echo date('M Y'); ?>)</span>
                <span class="fs-4 fw-bold text-primary font-monospace">Rs. <?php echo number_format($current_month_total, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-calendar-month fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Active Heads</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo count($heads_count); ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-tags fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Controls Bar -->
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
                        id="expenseSearch" 
                        placeholder="Search by expense head, particulars, or amount..."
                    >
                </div>
            </div>
            <div class="col-12 col-md-auto d-flex align-items-center gap-2">
                <select id="headFilter" class="form-select form-select-sm" style="width: auto;">
                    <option value="">All Expense Heads</option>
                    <?php foreach (array_keys($heads_count) as $h) { ?>
                        <option value="<?php echo htmlspecialchars($h, ENT_QUOTES, 'UTF-8'); ?>">
                            <?php echo htmlspecialchars($h, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="text-muted small">
                    Showing <strong id="visibleCount"><?php echo $total_expenses; ?></strong> of <?php echo $total_expenses; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Expenses Directory Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-journal-text text-primary me-2"></i>Operating Expense Register
        </span>
        <span class="badge bg-light text-dark border">
            Reconciles in P&amp;L Statements
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if ($total_expenses === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-wallet2 fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Expenses Recorded</h5>
                <p class="text-muted small mb-3">
                    Log utility payments, kitchen fuel, maintenance, packaging, or petty cash expenses to monitor operational overheads.
                </p>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i> Record First Expense
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="expensesTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Voucher #</th>
                            <th style="width: 130px;">Expense Date</th>
                            <th>Expense Head / Category</th>
                            <th>Description / Particulars</th>
                            <th class="text-end" style="width: 160px;">Amount</th>
                            <th class="text-end pe-3" style="width: 150px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp) { 
                            $amt_display = (float)($exp["amount"] ?? 0);
                            $head_val = trim($exp["expense_head"] ?? "General");
                        ?>
                            <tr class="expense-table-row" data-head="<?php echo htmlspecialchars($head_val, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #EXP-<?php echo str_pad($exp["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark fw-semibold small d-block">
                                        <?php echo !empty($exp["expense_date"]) ? date('M d, Y', strtotime($exp["expense_date"])) : "&mdash;"; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="expense-avatar">
                                            <i class="bi bi-receipt"></i>
                                        </div>
                                        <div>
                                            <span class="expense-head-badge">
                                                <?php echo htmlspecialchars($head_val, ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty(trim($exp["description"] ?? ""))) { ?>
                                        <span class="text-dark small">
                                            <?php echo htmlspecialchars($exp["description"], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small">&mdash; No details entered &mdash;</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end">
                                    <span class="fw-bold text-dark font-monospace">
                                        Rs. <?php echo number_format($amt_display, 2); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit.php?id=<?php echo $exp["id"]; ?>" 
                                           class="btn btn-outline-primary btn-sm d-inline-flex align-items-center gap-1"
                                           title="Edit Expense">
                                            <i class="bi bi-pencil"></i>
                                            <span>Edit</span>
                                        </a>

                                        <a href="delete.php?id=<?php echo $exp["id"]; ?>" 
                                           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                                           title="Delete Expense"
                                           onclick="return confirm('Are you sure you want to delete Expense #EXP-<?php echo str_pad($exp["id"], 3, '0', STR_PAD_LEFT); ?> (Rs. <?php echo number_format($amt_display, 2); ?>)?');">
                                            <i class="bi bi-trash"></i>
                                            <span>Delete</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="4" class="text-end fw-bold py-3">Total Operational Expenses:</td>
                            <td class="text-end fw-bold text-danger font-monospace fs-5 py-3">
                                Rs. <?php echo number_format($total_amount, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Client-side No Results State -->
            <div id="noSearchResults" class="text-center py-5 d-none">
                <i class="bi bi-search text-muted fs-2 mb-2 d-block"></i>
                <div class="fw-semibold text-dark">No matching expenses found</div>
                <div class="text-muted small">Try adjusting your search keywords or category filter</div>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('expenseSearch');
    const headFilter = document.getElementById('headFilter');
    const tableRows = document.querySelectorAll('.expense-table-row');
    const visibleCount = document.getElementById('visibleCount');
    const noResults = document.getElementById('noSearchResults');
    const expensesTable = document.getElementById('expensesTable');

    function filterExpenses() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const selectedHead = headFilter ? headFilter.value.trim().toLowerCase() : '';
        let matches = 0;

        tableRows.forEach(function (row) {
            const text = row.textContent.toLowerCase();
            const rowHead = (row.getAttribute('data-head') || '').toLowerCase();

            const matchesText = text.includes(query);
            const matchesHead = (selectedHead === '') || (rowHead === selectedHead);

            if (matchesText && matchesHead) {
                row.style.display = '';
                matches++;
            } else {
                row.style.display = 'none';
            }
        });

        if (visibleCount) {
            visibleCount.textContent = matches.toString();
        }

        if (noResults && expensesTable) {
            if (matches === 0 && tableRows.length > 0) {
                noResults.classList.remove('d-none');
                expensesTable.classList.add('d-none');
            } else {
                noResults.classList.add('d-none');
                expensesTable.classList.remove('d-none');
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterExpenses);
    }
    if (headFilter) {
        headFilter.addEventListener('change', filterExpenses);
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>