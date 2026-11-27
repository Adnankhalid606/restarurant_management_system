<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once "../includes/pagination.php";

requireRole(["admin"]);

$pg = get_pagination_params(25, [25, 50, 100]);
$q = trim($_GET["q"] ?? "");

// 1. Summary KPIs across all salaries
$kpi_sql = "SELECT
                COUNT(*) AS total_records,
                COALESCE(SUM(amount), 0) AS total_amount,
                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END), 0) AS total_paid,
                COALESCE(SUM(CASE WHEN payment_status <> 'paid' THEN amount ELSE 0 END), 0) AS total_unpaid
            FROM salaries";
$kpi_row = mysqli_fetch_assoc(mysqli_query($conn, $kpi_sql));
$total_records = (int) ($kpi_row['total_records'] ?? 0);
$total_amount = (float) ($kpi_row['total_amount'] ?? 0);
$total_paid = (float) ($kpi_row['total_paid'] ?? 0);
$total_unpaid = (float) ($kpi_row['total_unpaid'] ?? 0);

// 2. Filtered WHERE clause
$where_clauses = [];
$bind_params = [];
$bind_types = "";

if ($q !== "") {
    $where_clauses[] = "(users.name LIKE ? OR salaries.salary_type LIKE ? OR salaries.payment_status LIKE ? OR COALESCE(salaries.description, '') LIKE ? OR CAST(salaries.id AS CHAR) LIKE ? OR CAST(salaries.salary_date AS CHAR) LIKE ?)";
    $like_q = "%" . $q . "%";
    for ($i = 0; $i < 6; $i++) {
        $bind_params[] = $like_q;
    }
    $bind_types .= "ssssss";
}

$where_sql = !empty($where_clauses) ? ("WHERE " . implode(" AND ", $where_clauses)) : "";

// 3. Filtered COUNT query
$count_sql = "SELECT COUNT(*) AS total
              FROM salaries
              INNER JOIN users ON salaries.user_id = users.id
              $where_sql";
$c_stmt = mysqli_prepare($conn, $count_sql);
if ($bind_types !== "") {
    mysqli_stmt_bind_param($c_stmt, $bind_types, ...$bind_params);
}
mysqli_stmt_execute($c_stmt);
$filtered_total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($c_stmt))['total'] ?? 0);
mysqli_stmt_close($c_stmt);

$pagination = finalize_pagination($filtered_total, $pg['page'], $pg['per_page']);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

// 4. Paginated data query (stable secondary sort by salaries.id DESC)
$sql = "
    SELECT
        salaries.id,
        users.name AS employee_name,
        salaries.amount,
        salaries.salary_type,
        salaries.salary_date,
        salaries.payment_status,
        salaries.description
    FROM salaries
    INNER JOIN users
        ON salaries.user_id = users.id
    $where_sql
    ORDER BY salaries.salary_date DESC, salaries.id DESC
    LIMIT ? OFFSET ?
";

$d_stmt = mysqli_prepare($conn, $sql);
$data_types = $bind_types . "ii";
$data_params = array_merge($bind_params, [$limit, $offset]);
mysqli_stmt_bind_param($d_stmt, $data_types, ...$data_params);
mysqli_stmt_execute($d_stmt);
$result = mysqli_stmt_get_result($d_stmt);

$salaries = [];
while ($row = mysqli_fetch_assoc($result)) {
    $salaries[] = $row;
}
mysqli_stmt_close($d_stmt);

$page_title = "Staff Salaries & Payroll";
$active_menu = "salary";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Staff Salaries &amp; Payroll</h2>
        <p class="page-header-subtitle">Employee wage disbursements, payroll records, and payment reconciliation</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-circle"></i>
            <span>Add Salary</span>
        </a>
        <a href="../reports/profit-loss.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-graph-up"></i>
            <span>P&amp;L Report</span>
        </a>
        <a href="../index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
    </div>
</div>

<!-- Payroll KPI Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Total Payroll Logged</span>
                <span class="fs-4 fw-bold text-dark font-monospace">
                    Rs. <?php echo number_format($total_amount, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-cash-stack fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-paid">
            <div>
                <span class="text-muted small d-block">Disbursed (Paid)</span>
                <span class="fs-4 fw-bold text-success font-monospace">
                    Rs. <?php echo number_format($total_paid, 2); ?>
                </span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check2-circle fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-outstanding">
            <div>
                <span class="text-muted small d-block">Pending (Unpaid)</span>
                <span class="fs-4 fw-bold <?php echo $total_unpaid > 0 ? 'text-danger' : 'text-secondary'; ?> font-monospace">
                    Rs. <?php echo number_format($total_unpaid, 2); ?>
                </span>
            </div>
            <div class="badge-subtle <?php echo $total_unpaid > 0 ? 'badge-status-cancelled' : 'badge-status-preparing'; ?> p-2 rounded">
                <i class="bi bi-clock-history fs-4 <?php echo $total_unpaid > 0 ? 'text-danger' : 'text-secondary'; ?>"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Recorded Entries</span>
                <span class="fs-4 fw-bold text-dark font-monospace"><?php echo $total_records; ?></span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-people fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Salary Register Card -->
<div class="pos-card">
    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title">
                <i class="bi bi-credit-card-2-front text-primary me-2"></i>Salary Disbursements Register
            </span>
            <span class="badge bg-light text-dark border font-monospace" id="salaryCountBadge">
                <?php echo $total_records; ?> Records
            </span>
        </div>
        <div class="d-flex align-items-center gap-2" style="min-width: 260px;">
            <form method="GET" class="input-group input-group-sm m-0">
                <input type="hidden" name="per_page" value="<?php echo (int)$pagination['per_page']; ?>">
                <input type="hidden" name="page" value="1">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       name="q"
                       id="salarySearchInput" 
                       class="form-control border-start-0" 
                       value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>"
                       placeholder="Filter employee, type, status..." 
                       aria-label="Search salary records">
                <button type="submit" class="btn btn-outline-primary">Search</button>
                <?php if ($q !== "") { ?>
                    <a href="?per_page=<?php echo (int)$pagination['per_page']; ?>" class="btn btn-outline-secondary">Reset</a>
                <?php } ?>
            </form>
        </div>
    </div>

    <div class="pos-card-body p-0">
        <?php if (empty($salaries)) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-cash-coin fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No Salary Records Found</h5>
                <p class="text-muted small mb-3"><?php echo ($q !== "") ? "Try adjusting your search keywords." : "There are no payroll disbursement entries recorded in the system yet."; ?></p>
                <?php if ($q === "") { ?>
                    <a href="create.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i>Add First Salary Entry
                    </a>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="salaryTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">Record #</th>
                            <th>Employee</th>
                            <th>Frequency / Type</th>
                            <th>Disbursement Date</th>
                            <th>Payment Status</th>
                            <th>Remarks / Description</th>
                            <th class="text-end" style="width: 150px;">Amount</th>
                            <th class="text-end pe-3" style="width: 110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="salaryTableBody">
                        <?php foreach ($salaries as $row) { 
                            $pay_status = strtolower(trim($row["payment_status"] ?? "unpaid"));
                            $salary_type = strtolower(trim($row["salary_type"] ?? "monthly"));
                            $amount = (float) $row["amount"];
                        ?>
                            <tr class="salary-row"
                                data-employee="<?php echo strtolower(htmlspecialchars($row["employee_name"])); ?>"
                                data-type="<?php echo $salary_type; ?>"
                                data-status="<?php echo $pay_status; ?>"
                                data-date="<?php echo $row["salary_date"]; ?>"
                                data-desc="<?php echo strtolower(htmlspecialchars($row["description"] ?? "")); ?>"
                                data-id="<?php echo $row["id"]; ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #SAL-<?php echo str_pad($row["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="salary-avatar">
                                            <i class="bi bi-person"></i>
                                        </div>
                                        <div>
                                            <span class="fw-semibold text-dark d-block">
                                                <?php echo htmlspecialchars($row["employee_name"]); ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($salary_type === "daily") { ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle text-capitalize">
                                            <i class="bi bi-calendar-day me-1"></i>Daily
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-capitalize">
                                            <i class="bi bi-calendar-month me-1"></i>Monthly
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="text-dark small">
                                        <?php echo formatDate($row["salary_date"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pay_status === "paid") { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Paid</span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge-subtle badge-status-cancelled d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-clock"></i>
                                            <span>Unpaid</span>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if (!empty($row["description"])) { ?>
                                        <span class="text-dark small d-inline-block text-truncate" style="max-width: 220px;" title="<?php echo htmlspecialchars($row["description"]); ?>">
                                            <?php echo htmlspecialchars($row["description"]); ?>
                                        </span>
                                    <?php } else { ?>
                                        <span class="text-muted small fst-italic">No remarks</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    Rs. <?php echo number_format($amount, 2); ?>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit.php?id=<?php echo $row["id"]; ?>" 
                                           class="btn btn-outline-secondary btn-sm" 
                                           title="Edit Record">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <form method="POST" action="delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete this salary record?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="id" value="<?php echo $row["id"]; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Record">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr id="noSalaryMatchesRow" style="display: none;">
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> No salary entries match your search filter.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot class="table-light border-top">
                        <tr>
                            <td colspan="6" class="text-end fw-bold py-2">Total Payroll Logged:</td>
                            <td class="text-end py-2 fw-bold text-dark font-monospace">
                                Rs. <?php echo number_format($total_amount, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="6" class="text-end fw-bold py-2 text-success">Total Disbursed (Paid):</td>
                            <td class="text-end py-2 fw-bold text-success font-monospace">
                                Rs. <?php echo number_format($total_paid, 2); ?>
                            </td>
                            <td></td>
                        </tr>
                        <?php if ($total_unpaid > 0) { ?>
                            <tr class="table-danger">
                                <td colspan="6" class="text-end fw-bold py-2 text-danger">Total Pending (Unpaid):</td>
                                <td class="text-end py-2 fw-bold text-danger font-monospace">
                                    Rs. <?php echo number_format($total_unpaid, 2); ?>
                                </td>
                                <td></td>
                            </tr>
                        <?php } ?>
                    </tfoot>
                </table>
            </div>
            <?php render_pagination_bar($pagination); ?>
        <?php } ?>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>