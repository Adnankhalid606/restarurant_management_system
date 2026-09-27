<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = $_GET["id"];

$sql = "SELECT * FROM customers WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    die("Customer not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $phone = $_POST["phone"];
    $address = $_POST["address"];

    $sql = "UPDATE customers
            SET name = ?, phone = ?, address = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "sssi",
        $name,
        $phone,
        $address,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Edit Customer #" . $id;
$active_menu = "customers";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Edit Customer #<?php echo $id; ?></h2>
        <p class="page-header-subtitle">Update guest contact details and delivery preferences</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Customers</span>
        </a>
        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
            <a href="../customer-ledger/index.php?customer_id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
                <i class="bi bi-journal-text"></i>
                <span>Customer Ledger</span>
            </a>
        <?php } ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light d-flex align-items-center justify-content-between">
                <span class="pos-card-title">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Modify Customer Record
                </span>
                <span class="badge bg-white text-muted border">ID #<?php echo $id; ?></span>
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
                                value="<?php echo htmlspecialchars($customer["name"], ENT_QUOTES, 'UTF-8'); ?>"
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
                                value="<?php echo htmlspecialchars($customer["phone"] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
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
                            ><?php echo htmlspecialchars($customer["address"] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-text text-muted small">Useful for delivery orders and billing invoices.</div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                            <a href="delete.php?id=<?php echo $id; ?>" 
                               class="btn btn-outline-danger px-3 d-inline-flex align-items-center gap-1"
                               onclick="return confirm('Are you sure you want to delete customer <?php echo htmlspecialchars(addslashes($customer['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Customers with existing orders cannot be deleted.');">
                                <i class="bi bi-trash"></i>
                                <span>Delete</span>
                            </a>
                        <?php } else { ?>
                            <div></div>
                        <?php } ?>

                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="bi bi-check-lg"></i>
                                <span>Update Customer</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>