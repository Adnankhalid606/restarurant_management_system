<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SESSION["role"] !== "admin") {
    die("Access denied.");
}

$salary_id = (int) ($_GET["id"] ?? 0);
if ($salary_id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Get salary
$sql = "
    SELECT
        id,
        user_id,
        amount,
        salary_type,
        salary_date,
        payment_status,
        description
    FROM salaries
    WHERE id = ?
";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $salary_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$salary = mysqli_fetch_assoc($result);

if (!$salary) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

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

$employees_result = mysqli_query($conn, $sql);

$error = "";

$posted_user_id = (int) $salary["user_id"];
$posted_amount = (string) $salary["amount"];
$posted_salary_type = (string) $salary["salary_type"];
$posted_salary_date = (string) $salary["salary_date"];
$posted_payment_status = (string) $salary["payment_status"];
$posted_description = (string) ($salary["description"] ?? "");

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!verify_csrf_token()) {
        csrf_failure_response();
    }

    $posted_user_id = (int) ($_POST["user_id"] ?? 0);
    $amount_raw = $_POST["amount"] ?? null;
    $posted_salary_type = trim((string) ($_POST["salary_type"] ?? ""));
    $posted_salary_date = trim((string) ($_POST["salary_date"] ?? date("Y-m-d")));
    $posted_payment_status = trim((string) ($_POST["payment_status"] ?? "unpaid"));
    $posted_description = trim((string) ($_POST["description"] ?? ""));

    // 1. Validate employee
    if ($posted_user_id <= 0) {
        $error = "Please select an active employee staff member.";
    } else {
        $check_emp_sql = "
            SELECT id
            FROM users
            WHERE id = ?
            AND role != 'admin'
            AND is_active = 1
        ";
        $stmt = mysqli_prepare($conn, $check_emp_sql);
        mysqli_stmt_bind_param($stmt, "i", $posted_user_id);
        mysqli_stmt_execute($stmt);
        $employee_result = mysqli_stmt_get_result($stmt);

        if (mysqli_num_rows($employee_result) === 0) {
            $error = "Selected employee is invalid, deactivated, or not eligible for staff payroll.";
        }
    }

    // 2. Strict amount validation
    if ($error === "") {
        if ($amount_raw === null || is_array($amount_raw) || !is_scalar($amount_raw)) {
            $error = "Salary amount is required and must be a valid numeric amount.";
        } else {
            $amount_str = trim((string) $amount_raw);
            $posted_amount = $amount_str;

            if ($amount_str === "") {
                $error = "Salary amount is required.";
            } elseif (!preg_match('/^\d+(\.\d{1,2})?$/', $amount_str)) {
                $error = "Salary amount must be a valid positive number with at most 2 decimal places.";
            } else {
                $amount_val = (float) $amount_str;
                if (!is_finite($amount_val) || $amount_val <= 0) {
                    $error = "Salary amount must be strictly greater than zero.";
                } elseif ($amount_val > 99999999.99) {
                    $error = "Salary amount exceeds the maximum permitted limit of 99,999,999.99.";
                }
            }
        }
    }

    // 3. Validate salary type
    if ($error === "" && !in_array($posted_salary_type, ["daily", "monthly"], true)) {
        $error = "Invalid salary frequency selected. Must be daily or monthly.";
    }

    // 4. Validate payment status
    if ($error === "" && !in_array($posted_payment_status, ["unpaid", "paid"], true)) {
        $error = "Invalid payment status selected. Must be paid or unpaid.";
    }

    // 5. Validate disbursement date
    if ($error === "" && $posted_salary_date === "") {
        $error = "Disbursement date is required.";
    }

    // Update salary if all validations pass
    if ($error === "") {
        $amount = round((float) $amount_str, 2);

        $sql = "
            UPDATE salaries
            SET
                user_id = ?,
                amount = ?,
                salary_type = ?,
                salary_date = ?,
                payment_status = ?,
                description = ?
            WHERE id = ?
        ";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "idssssi",
            $posted_user_id,
            $amount,
            $posted_salary_type,
            $posted_salary_date,
            $posted_payment_status,
            $posted_description,
            $salary_id
        );

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION["flash_success"] = "Salary record #SAL-" . str_pad($salary_id, 3, '0', STR_PAD_LEFT) . " was successfully updated.";
            header("Location: index.php");
            exit;
        } else {
            $error = "Database error while updating staff salary record.";
        }
    }
}

$page_title = "Edit Salary Record #" . $salary_id;
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
            <span class="text-dark small fw-semibold">Edit Record #SAL-<?php echo str_pad($salary["id"], 3, '0', STR_PAD_LEFT); ?></span>
        </div>
        <h2 class="page-header-title">
            Edit Salary Record
            <span class="badge bg-light text-dark border font-monospace fs-6 fw-normal ms-2">
                #SAL-<?php echo str_pad($salary["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
        </h2>
        <p class="page-header-subtitle">Update employee payroll disbursement details, status, or amounts</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Salaries</span>
        </a>
    </div>
</div>

<?php if ($error !== "") { ?>
    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
        <div>
            <strong>Validation Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    </div>
<?php } ?>

<!-- Salary Entry Form Container -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Modify Disbursement Voucher
                </span>
                <span class="badge bg-light text-dark border font-monospace">
                    Record #<?php echo $salary["id"]; ?>
                </span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST" action="edit.php?id=<?php echo $salary["id"]; ?>">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Employee Staff Member <span class="text-danger">*</span>
                            </label>
                            <select name="user_id" class="form-select" required>
                                <?php 
                                mysqli_data_seek($employees_result, 0);
                                while ($row = mysqli_fetch_assoc($employees_result)) { ?>
                                    <option value="<?php echo $row["id"]; ?>" <?php echo (int)$row["id"] === $posted_user_id ? "selected" : ""; ?>>
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
                                       value="<?php echo htmlspecialchars($posted_amount, ENT_QUOTES, 'UTF-8'); ?>" 
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
                                <option value="monthly" <?php echo $posted_salary_type === "monthly" ? "selected" : ""; ?>>
                                    Monthly
                                </option>
                                <option value="daily" <?php echo $posted_salary_type === "daily" ? "selected" : ""; ?>>
                                    Daily
                                </option>
                            </select>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark">
                                Disbursement Date <span class="text-danger">*</span>
                            </label>
                            <input type="date" 
                                   name="salary_date" 
                                   value="<?php echo htmlspecialchars($posted_salary_date, ENT_QUOTES, 'UTF-8'); ?>" 
                                   class="form-control" 
                                   required>
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label fw-semibold text-dark">
                                Payment Status <span class="text-danger">*</span>
                            </label>
                            <select name="payment_status" class="form-select" required>
                                <option value="paid" <?php echo $posted_payment_status === "paid" ? "selected" : ""; ?>>
                                    Paid (Disbursed)
                                </option>
                                <option value="unpaid" <?php echo $posted_payment_status === "unpaid" ? "selected" : ""; ?>>
                                    Unpaid (Pending)
                                </option>
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
                                  placeholder="e.g. Regular monthly wage, overtime compensation, incentive, or advance payout..."><?php echo htmlspecialchars($posted_description, ENT_QUOTES, 'UTF-8'); ?></textarea>
                    </div>

                    <!-- Accounting Guidance Note -->
                    <div class="alert alert-info py-2 px-3 d-flex align-items-center gap-2 mb-4 border-info-subtle">
                        <i class="bi bi-info-circle-fill text-info fs-5"></i>
                        <span class="small text-secondary">
                            <strong>Financial Note:</strong> Salaries marked with <strong>Paid</strong> status are automatically reconciled into Operating Payroll under the Profit &amp; Loss reports.
                        </span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                        <button type="submit" form="deleteSalaryForm" class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-trash"></i>
                            <span>Delete Record</span>
                        </button>
                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm d-inline-flex align-items-center gap-1">
                                <i class="bi bi-check2-circle"></i>
                                <span>Update Salary Record</span>
                            </button>
                        </div>
                    </div>
                </form>

                <form id="deleteSalaryForm" method="POST" action="delete.php" class="d-none" onsubmit="return confirm('Are you sure you want to delete this salary record?');">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" value="<?php echo $salary["id"]; ?>">
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>