<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = preg_replace('/\s+/', ' ', trim($_POST["name"] ?? ""));
    $phone = trim($_POST["phone"] ?? "");
    $address = preg_replace('/\s+/', ' ', trim($_POST["address"] ?? ""));

    if ($name === "") {
        $error = "Customer full name is required and cannot be empty or whitespace only.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = "Customer name must be between 2 and 100 characters.";
    } else {
        try {
            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO customers (name, phone, address) VALUES (?, ?, ?)"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sss",
                $name,
                $phone,
                $address
            );

            mysqli_stmt_execute($stmt);

            header("Location: index.php");
            exit;
        } catch (mysqli_sql_exception $e) {
            $error = "Database error while creating customer profile.";
        }
    }
}

$page_title = "Add Customer";
$active_menu = "customers";

require_once "../includes/header.php";

$posted_name = $_POST["name"] ?? "";
$posted_phone = $_POST["phone"] ?? "";
$posted_address = $_POST["address"] ?? "";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Add Customer</h2>
        <p class="page-header-subtitle">Register a new guest profile for dine-in, takeaway, or delivery</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Customers</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <?php if ($error !== "") { ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div>
                    <strong>Validation Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php } ?>

        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-person-plus text-primary me-2"></i>Customer Profile
                </span>
                <span class="badge bg-white text-muted border">New Record</span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST">
                    <!-- Customer Name -->
                    <div class="mb-3">
                        <label for="customerName" class="form-label pos-form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-person text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0 py-2"
                                id="customerName"
                                name="name"
                                placeholder="e.g. Ali Khan"
                                value="<?php echo htmlspecialchars($posted_name, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Guest or organization name.</div>
                    </div>

                    <!-- Phone Number -->
                    <div class="mb-3">
                        <label for="customerPhone" class="form-label pos-form-label">
                            Phone Number
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-telephone text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0 py-2"
                                id="customerPhone"
                                name="phone"
                                placeholder="e.g. +92 300 1234567"
                                value="<?php echo htmlspecialchars($posted_phone, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="form-text text-muted small">Contact number for reservations and order status.</div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="mb-4">
                        <label for="customerAddress" class="form-label pos-form-label">
                            Address / Delivery Details
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 align-items-start pt-2">
                                <i class="bi bi-geo-alt text-muted"></i>
                            </span>
                            <textarea
                                class="form-control pos-form-control border-start-0 py-2"
                                id="customerAddress"
                                name="address"
                                rows="3"
                                placeholder="Street, block, building or area notes..."
                            ><?php echo htmlspecialchars($posted_address, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-text text-muted small">Useful for delivery orders and billing invoices.</div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-check-lg"></i>
                            <span>Save Customer</span>
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