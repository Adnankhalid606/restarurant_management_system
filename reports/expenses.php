<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$from = $_GET["from"] ?? date("Y-m-01");
$to = $_GET["to"] ?? date("Y-m-d");

$sql = "
    SELECT
        id,
        expense_head,
        description,
        amount,
        expense_date
    FROM expenses
    WHERE expense_date BETWEEN ? AND ?
    ORDER BY expense_date DESC
";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "ss", $from, $to);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$total_expenses = 0;

$rows = [];

while ($row = mysqli_fetch_assoc($result)) {

    $rows[] = $row;

    $total_expenses += $row["amount"];
}

$page_title = "Expenses Report";
$active_menu = "reports";

require_once "../includes/header.php";
?>

<!-- Print-Only Report Header -->
<div class="d-none d-print-block mb-4">
    <div class="d-flex justify-content-between align-items-center border-bottom pb-2">
        <div>
            <h3 class="mb-0 fw-bold">RestoBar POS &amp; Management</h3>
            <p class="text-muted small mb-0">Operational Expenditures &amp; Overheads Report</p>
        </div>
        <div class="text-end">
            <div class="fw-semibold">Period: <?php echo htmlspecialchars($from); ?> to <?php echo htmlspecialchars($to); ?></div>
            <small class="text-muted">Printed on <?php echo date('M d, Y h:i A'); ?></small>
        </div>
    </div>
</div>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Operating Expenses Report</h2>
        <p class="page-header-subtitle">General operational overheads, utility payments, maintenance &amp; recurring outlays</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reports Hub</span>
        </a>
        <button type="button" class="btn btn-outline-dark btn-sm d-inline-flex align-items-center gap-1" onclick="window.print()">
            <i class="bi bi-printer"></i>
            <span>Print Report</span>
        </button>
    </div>
</div>

<!-- Date Filter Form Card -->
<div class="pos-card mb-4 d-print-none">
    <div class="pos-card-body p-3">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-12 col-sm-4 col-md-3">
                <label for="from" class="form-label pos-form-label mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>From Date:
                </label>
                <input
                    type="date"
                    id="from"
                    name="from"
                    class="form-control pos-form-control"
                    value="<?php echo htmlspecialchars($from); ?>"
                    required>
            </div>

            <div class="col-12 col-sm-4 col-md-3">
                <label for="to" class="form-label pos-form-label mb-1">
                    <i class="bi bi-calendar-event me-1 text-muted"></i>To Date:
                </label>
                <input
                    type="date"
                    id="to"
                    name="to"
                    class="form-control pos-form-control"
                    value="<?php echo htmlspecialchars($to); ?>"
                    required>
            </div>

            <div class="col-12 col-sm-4 col-md-auto">
                <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-1">
                    <i class="bi bi-filter"></i>
                    <span>Generate Report</span>
                </button>
            </div>

            <div class="col-12 col-md text-md-end text-muted small">
                Active Period: <strong><?php echo date('M d, Y', strtotime($from)); ?></strong> &mdash; <strong><?php echo date('M d, Y', strtotime($to)); ?></strong>
            </div>
        </form>
    </div>
</div>

<!-- Expense Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Recorded Vouchers</span>
                <span class="fs-4 fw-bold text-dark"><?php echo count($rows); ?></span>
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
                <span class="fs-4 fw-bold text-danger font-monospace">Rs. <?php echo number_format($total_expenses, 2); ?></span>
            </div>
            <div class="badge-subtle badge-status-cancelled p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-danger"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Average Voucher</span>
                <span class="fs-4 fw-bold text-secondary font-monospace">
                    Rs. <?php echo count($rows) > 0 ? number_format($total_expenses / count($rows), 2) : "0.00"; ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-calculator fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">P&amp;L Treatment</span>
                <span class="fs-6 fw-bold text-primary d-block">OPEX Debit</span>
                <span class="text-muted small" style="font-size: 0.75rem;">Offsets Gross Sales</span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-arrow-down-right fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Expense Details Table -->
<div class="pos-card">
    <div class="pos-card-header d-flex justify-content-between align-items-center">
        <span class="pos-card-title">
            <i class="bi bi-table text-primary me-2"></i>Expense Vouchers &amp; Outlays
        </span>
        <span class="badge bg-light text-dark border">
            Sorted by Expense Date
        </span>
    </div>
    <div class="pos-card-body p-0">
        <?php if (count($rows) === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-journal-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Expenses in Range</h5>
                <p class="text-muted small mb-0">
                    No operating expense vouchers were logged between <?php echo htmlspecialchars($from); ?> and <?php echo htmlspecialchars($to); ?>.
                </p>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Voucher #</th>
                            <th style="width: 130px;">Date</th>
                            <th>Expense Head / Category</th>
                            <th>Description / Particulars</th>
                            <th class="text-end pe-3" style="width: 160px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row) { 
                            $amt = (float)($row["amount"] ?? 0);
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #EXP-<?php echo str_pad($row["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="text-dark small d-block">
                                        <?php echo !empty($row["expense_date"]) ? date('M d, Y', strtotime($row["expense_date"])) : "&mdash;"; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="expense-head-badge">
                                        <?php echo htmlspecialchars($row["expense_head"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty(trim($row["description"] ?? ""))) { ?>
                                        <span class="text-dark small">
                                            <?php echo htmlspecialchars($row["description"]); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small">&mdash; No details &mdash;</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end pe-3 font-monospace fw-bold text-dark">
                                    Rs. <?php echo number_format($amt, 2); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="4" class="text-end fw-bold py-3">Total Operating Expenses:</td>
                            <td class="text-end pe-3 py-3 fw-bold text-danger font-monospace fs-5">
                                Rs. <?php echo number_format($total_expenses, 2); ?>
                            </td>
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