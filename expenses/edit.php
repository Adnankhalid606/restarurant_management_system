<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

$sql = "SELECT *
        FROM expenses
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$expense = mysqli_fetch_assoc($result);

if (!$expense) {
    die("Expense not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $expense_head = $_POST["expense_head"];
    $description = $_POST["description"];
    $amount = $_POST["amount"];
    $expense_date = $_POST["expense_date"];

    $sql = "UPDATE expenses

            SET expense_head = ?,
                description = ?,
                amount = ?,
                expense_date = ?

            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdsi",
        $expense_head,
        $description,
        $amount,
        $expense_date,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Edit Expense #" . $expense["id"];
$active_menu = "expenses";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-light text-dark border font-monospace">
                #EXP-<?php echo str_pad($expense["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
            <span class="badge badge-subtle badge-status-completed">
                <i class="bi bi-receipt me-1"></i>Existing Voucher
            </span>
        </div>
        <h2 class="page-header-title">Edit Expense: <?php echo htmlspecialchars($expense["expense_head"], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="page-header-subtitle">Update outlay amount, classification category, date or descriptive notes</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Expenses</span>
        </a>
        <a href="delete.php?id=<?php echo $expense["id"]; ?>" 
           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
           onclick="return confirm('Are you sure you want to delete Expense #EXP-<?php echo str_pad($expense["id"], 3, '0', STR_PAD_LEFT); ?>?');">
            <i class="bi bi-trash"></i>
            <span>Delete</span>
        </a>
    </div>
</div>

<form method="POST">
    <div class="row g-4 justify-content-center">
        <!-- Main Form Column -->
        <div class="col-12 col-lg-8">
            <div class="pos-card">
                <div class="pos-card-header d-flex justify-content-between align-items-center">
                    <span class="pos-card-title">
                        <i class="bi bi-pencil-square text-primary me-2"></i>Modify Expense Voucher Details
                    </span>
                    <span class="badge bg-light text-dark border">
                        ID #<?php echo $expense["id"]; ?>
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
                                    value="<?php echo htmlspecialchars($expense["expense_head"], ENT_QUOTES, 'UTF-8'); ?>"
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
                                    value="<?php echo htmlspecialchars($expense["amount"], ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>
                            <div class="form-text text-muted small">Financial outlay value in PKR.</div>
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
                                    value="<?php echo htmlspecialchars($expense["expense_date"], ENT_QUOTES, 'UTF-8'); ?>"
                                    required
                                >
                            </div>
                            <div class="form-text text-muted small">Effective date for financial reconciliation.</div>
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
                                    placeholder="Enter expense details or vendor notes..."
                                ><?php echo htmlspecialchars($expense["description"] ?? "", ENT_QUOTES, 'UTF-8'); ?></textarea>
                            </div>
                            <div class="form-text text-muted small">Optional particulars, invoice/bill numbers, or notes.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions Column -->
        <div class="col-12 col-lg-4">
            <!-- Action Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-save2 text-primary me-2"></i>Update Voucher
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <p class="text-muted small mb-3">
                        Updating this entry adjusts the historical ledger and updates consolidated Profit &amp; Loss totals.
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Update Expense</span>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Ledger Audit Card -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-shield-check text-secondary me-2"></i>Ledger Record
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small">Voucher ID</span>
                        <span class="font-monospace fw-semibold text-dark">#EXP-<?php echo str_pad($expense["id"], 3, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted small">Category</span>
                        <span class="fw-semibold text-dark"><?php echo htmlspecialchars($expense["expense_head"], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-muted small">Current Value</span>
                        <span class="fw-bold text-danger font-monospace">Rs. <?php echo number_format((float)$expense["amount"], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php

require_once "../includes/footer.php";

?>