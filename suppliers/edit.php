<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


$sql = "SELECT *
        FROM suppliers
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$supplier = mysqli_fetch_assoc($result);


if (!$supplier) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");


    $sql = "UPDATE suppliers

            SET name = ?,
                phone = ?,
                address = ?

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

$page_title = "Edit Supplier - " . $supplier["name"];
$active_menu = "suppliers";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-light text-dark border font-monospace">
                #SUP-<?php echo str_pad($supplier["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
            <span class="badge badge-subtle badge-status-completed">
                <i class="bi bi-check-circle-fill me-1"></i>Active Vendor
            </span>
        </div>
        <h2 class="page-header-title">Edit Supplier: <?php echo htmlspecialchars($supplier["name"], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="page-header-subtitle">Update vendor contact details, physical address &amp; procurement settings</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Suppliers</span>
        </a>
        <a href="../supplier-ledger/view.php?id=<?php echo $supplier["id"]; ?>" 
           class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-journal-text text-primary"></i>
            <span>View Ledger</span>
        </a>
        <a href="delete.php?id=<?php echo $supplier["id"]; ?>" 
           class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
           onclick="return confirm('Are you sure you want to delete supplier <?php echo htmlspecialchars(addslashes($supplier['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Suppliers referenced by purchase records cannot be deleted.');">
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
                        <i class="bi bi-building text-primary me-2"></i>Vendor Profile &amp; Contact Details
                    </span>
                    <span class="badge bg-light text-dark border">
                        ID #<?php echo $supplier["id"]; ?>
                    </span>
                </div>
                <div class="pos-card-body p-4">
                    <!-- Supplier Name -->
                    <div class="mb-3">
                        <label for="supplierName" class="form-label pos-form-label">
                            Supplier / Company Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-building text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0"
                                id="supplierName"
                                name="name"
                                value="<?php echo htmlspecialchars($supplier["name"], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">
                            Official vendor name used on purchase receipts and ledger records.
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="mb-3">
                        <label for="supplierPhone" class="form-label pos-form-label">
                            Phone Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-telephone text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0"
                                id="supplierPhone"
                                name="phone"
                                value="<?php echo htmlspecialchars($supplier["phone"], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">
                            Primary operational contact number for purchase inquiries and dispatch confirmations.
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="mb-2">
                        <label for="supplierAddress" class="form-label pos-form-label">
                            Physical / Warehouse Address <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 align-items-start pt-2">
                                <i class="bi bi-geo-alt text-muted"></i>
                            </span>
                            <textarea
                                class="form-control pos-form-control border-start-0"
                                id="supplierAddress"
                                name="address"
                                rows="3"
                                required
                            ><?php echo htmlspecialchars($supplier["address"], ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                        <div class="form-text text-muted small">
                            Delivery warehouse or business premises for material pick-ups and order dispatch.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Actions & Integration Column -->
        <div class="col-12 col-lg-4">
            <!-- Action Card -->
            <div class="pos-card mb-4">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-save2 text-primary me-2"></i>Save Changes
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <p class="text-muted small mb-3">
                        Updating these details modifies the vendor master record and updates linked purchasing references.
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Update Supplier</span>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Ledger Quick Access Card -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-journal-text text-secondary me-2"></i>Financial Account
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <p class="text-muted small mb-3">
                        Access this supplier's transaction history, paid invoices, and outstanding account balances:
                    </p>
                    <a href="../supplier-ledger/view.php?id=<?php echo $supplier["id"]; ?>" class="btn btn-outline-primary w-100 d-inline-flex align-items-center justify-content-center gap-2 mb-2">
                        <i class="bi bi-journal-bookmark-fill"></i>
                        <span>Open Supplier Ledger</span>
                    </a>
                    <div class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Changes are saved with relational integrity preserved.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php

require_once "../includes/footer.php";

?>