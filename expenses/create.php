<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $expense_head = $_POST["expense_head"];
    $description = $_POST["description"];
    $amount = $_POST["amount"];
    $expense_date = $_POST["expense_date"];

    $sql = "INSERT INTO expenses
            (
                expense_head,
                description,
                amount,
                expense_date
            )
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssds",
        $expense_head,
        $description,
        $amount,
        $expense_date
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Add Expense";
$active_menu = "expenses";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Record Operating Expense</h2>
        <p class="page-header-subtitle">Log utility bills, facility rent, kitchen fuel, maintenance or miscellaneous outlays</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Expenses</span>
        </a>
    </div>
</div>

<form method="POST">
    <div class="row g-4 justify-content-center">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-receipt text-primary me-2"></i>Expense Voucher Details
                    </span>
                    <span class="badge bg-light text-dark border">
                        General Outlay Entry
                    </span>
                </div>
                <div class="pos-card-body p-4">
                    <div class="row g-3">
                        <!-- Expense Head -->
                        <div class="col-12 col-md-6">
                            <label for="expenseHead" class="form-label pos-form-label">
                                Expense Head / Category <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-tag text-muted"></i>
                                </span>
                                <input
                                    type="text"
                                    class="form-control pos-form-control border-start-0"
                                    id="expenseHead"
                                    name="expense_head"
                                    list="expenseHeadSuggestions"
                                    placeholder="e.g. Electricity, Rent, Gas"
                                    value="<?php echo htmlspecialchars($_POST["expense_head"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                                <datalist id="expenseHeadSuggestions">
                                    <option value="Electricity">
                                    <option value="Natural Gas / Fuel">
                                    <option value="Water &amp; Sanitation">
                                    <option value="Commercial Rent">
                                    <option value="Equipment Maintenance">
                                    <option value="Kitchen Supplies &amp; Cleaning">
                                    <option value="Packaging &amp; Disposables">
                                    <option value="Marketing &amp; Social Media">
                                    <option value="Staff Welfare &amp; Refreshments">
                                    <option value="Waste Disposal">
                                    <option value="Miscellaneous">
                                </datalist>
                            </div>
                            <div class="form-text text-muted small">Standard expense category for financial classification.</div>
                        </div>

                        <!-- Amount -->
                        <div class="col-12 col-md-6">
                            <label for="expenseAmount" class="form-label pos-form-label">
                                Amount (Rs.) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-muted">
                                    Rs.
                                </span>
                                <input
                                    type="number"
                                    class="form-control pos-form-control border-start-0 text-end font-monospace"
                                    id="expenseAmount"
                                    name="amount"
                                    step="0.01"
                                    min="0.01"
                                    placeholder="0.00"
                                    value="<?php echo htmlspecialchars($_POST["amount"] ?? "", ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>
                            <div class="form-text text-muted small">Total financial outlay paid or payable.</div>
                        </div>

                        <!-- Expense Date -->
                        <div class="col-12 col-md-6">
                            <label for="expenseDate" class="form-label pos-form-label">
                                Expense Date <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0">
                                    <i class="bi bi-calendar-event text-muted"></i>
                                </span>
                                <input
                                    type="date"
                                    class="form-control pos-form-control border-start-0"
                                    id="expenseDate"
                                    name="expense_date"
                                    value="<?php echo htmlspecialchars($_POST["expense_date"] ?? date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>
                            <div class="form-text text-muted small">Effective date for Profit &amp; Loss accounting.</div>
                        </div>

                        <!-- Description / Particulars -->
                        <div class="col-12">
                            <label for="expenseDescription" class="form-label pos-form-label">
                                Description / Particulars
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 align-items-start pt-2">
                                    <i class="bi bi-card-text text-muted"></i>
                                </span>
                                <textarea
                                    class="form-control pos-form-control border-start-0"
                                    id="expenseDescription"
                                    name="description"
                                    rows="3"
                                    placeholder="Enter expense details, bill reference number, or vendor name..."
                                ><?php echo htmlspecialchars($_POST["description"] ?? "", ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="form-text text-muted small">Optional particulars, invoice/bill numbers, or notes.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions & Reporting Column -->
        <div class="col-12 col-lg-4">
            <!-- Action Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-save2 text-primary me-2"></i>Save Voucher
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <p class="text-muted small mb-3">
                        Saving this expense creates an official general ledger debit that automatically updates operational overhead metrics.
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Save Expense</span>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Reporting Notice Card -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-graph-up-arrow text-secondary me-2"></i>P&amp;L Statement Impact
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="recipe-flow-card mb-3">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <i class="bi bi-file-earmark-bar-graph text-primary fs-5"></i>
                            <div>
                                <strong class="small d-block text-dark">Profit &amp; Loss Statement</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">
                                    Deducted as operational expense (OPEX) from gross sales revenue.
                                </span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <i class="bi bi-clock-history text-success fs-5"></i>
                            <div>
                                <strong class="small d-block text-dark">Monthly Reports</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">
                                    Summarized under the specified Expense Head for month-end reconciliation.
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Role restricted: Only authorized administrators may log operational expenses.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php

require_once "../includes/footer.php";

?>