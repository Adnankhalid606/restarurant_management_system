<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

// Get employees
$sql = "
    SELECT
        id,
        name,
        role
    FROM users
    WHERE role != 'admin'
    AND is_active = 1
    ORDER BY name ASC
";

$result = mysqli_query($conn, $sql);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $user_id = (int) $_POST["user_id"];
    $amount = (float) $_POST["amount"];
    $salary_type = $_POST["salary_type"];
    $salary_date = $_POST["salary_date"];
    $payment_status = $_POST["payment_status"];
    $description = trim($_POST["description"]);

    // Validate salary type
    if (!in_array($salary_type, ["daily", "monthly"])) {
        die("Invalid salary type.");
    }

    // Validate payment status
    if (!in_array($payment_status, ["unpaid", "paid"])) {
        die("Invalid payment status.");
    }

    // Validate amount
    if ($amount <= 0) {
        die("Salary amount must be greater than zero.");
    }

    // Check employee
    $sql = "
        SELECT id
        FROM users
        WHERE id = ?
        AND role != 'admin'
        AND is_active = 1
    ";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);

    $employee_result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($employee_result) === 0) {
        die("Invalid employee.");
    }

    // Insert salary
    $sql = "
        INSERT INTO salaries
        (
            user_id,
            amount,
            salary_type,
            salary_date,
            payment_status,
            description
        )
        VALUES (?, ?, ?, ?, ?, ?)
    ";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "idssss",
        $user_id,
        $amount,
        $salary_type,
        $salary_date,
        $payment_status,
        $description
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Record Staff Salary";
$active_menu = "salary";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="index.php" class="text-muted small text-decoration-none">
                <i class="bi bi-credit-card-2-front me-1"></i>Staff Salaries
            </a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold">New Disbursement</span>
        </div>
        <h2 class="page-header-title">Record Staff Salary</h2>
        <p class="page-header-subtitle">Log employee wage payments, advance settlements, or daily compensation</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Salaries</span>
        </a>
    </div>
</div>

<!-- Salary Entry Form Container -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-wallet2 text-primary me-2"></i>Disbursement Details
                </span>
                <span class="badge bg-light text-dark border">
                    Payroll Voucher
                </span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST" action="create.php">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Employee Staff Member <span class="text-danger">*</span>
                            </label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Select Active Employee</option>
                                <?php 
                                mysqli_data_seek($result, 0);
                                while ($row = mysqli_fetch_assoc($result)) { ?>
                                    <option value="<?php echo $row["id"]; ?>">
                                        <?php echo htmlspecialchars($row["name"]); ?> - <?php echo ucfirst($row["role"]); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="form-text">Active staff members eligible for payroll.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Salary Amount <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">Rs.</span>
                                <input type="number" 
                                       name="amount" 
                                       step="0.01" 
                                       min="0.01" 
                                       class="form-control font-monospace fw-bold" 
                                       placeholder="0.00" 
                                       required>
                            </div>
                            <div class="form-text">Total net wage or disbursement amount.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark">
                                Salary Frequency <span class="text-danger">*</span>
                            </label>
                            <select name="salary_type" class="form-select" required>
                                <option value="monthly" selected>Monthly</option>
                                <option value="daily">Daily</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark">
                                Disbursement Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   name="salary_date" 
                                   value="<?php echo date("Y-m-d"); ?>" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark">
                                Payment Status <span class="text-danger">*</span>
                            </label>
                            <select name="payment_status" class="form-select" required>
                                <option value="paid" selected>Paid (Disbursed)</option>
                                <option value="unpaid">Unpaid (Pending)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold text-dark">
                            Remarks / Description <span class="text-muted fw-normal">(Optional)</span>
                        </label>
                        <textarea name="description" 
                                  rows="3" 
                                  class="form-control" 
                                  placeholder="e.g. Regular monthly wage, overtime compensation, incentive, or advance payout..."></textarea>
                    </div>

                    <!-- Accounting Guidance Note -->
                    <div class="alert alert-info py-2 px-3 d-flex align-items-center gap-2 mb-4 border-info-subtle">
                        <i class="bi bi-info-circle-fill text-info fs-5"></i>
                        <span class="small text-secondary">
                            <strong>Financial Note:</strong> Salaries marked with <strong>Paid</strong> status are automatically reconciled into Operating Payroll under the Profit &amp; Loss reports.
                        </span>
                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2 pt-2 border-top">
                        <a href="index.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-check2-circle"></i>
                            <span>Save Salary Record</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>