<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");


    $sql = "INSERT INTO suppliers
            (name, phone, address)
            VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

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
}

$page_title = "Add Supplier";
$active_menu = "suppliers";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Add Supplier</h2>
        <p class="page-header-subtitle">Register a new raw material vendor or food procurement partner</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Suppliers</span>
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
                        <i class="bi bi-building text-primary me-2"></i>Vendor Profile &amp; Contact Details
                    </span>
                    <span class="badge bg-light text-dark border">
                        New Record
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
                                placeholder="e.g. Metro Wholesale Foods &amp; Provisions"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">
                            The primary trading name of the supplier as it will appear on purchase orders and bills.
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
                                placeholder="e.g. +92 300 1234567"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">
                            Primary operational contact number for purchase inquiries, dispatch updates, and billing.
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
                                placeholder="e.g. Plot #45, Industrial Wholesale Market, Sector I-9/2, Islamabad"
                                required
                            ></textarea>
                        </div>
                        <div class="form-text text-muted small">
                            Delivery warehouse or physical business location for material pick-ups and official correspondence.
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
                        <i class="bi bi-save2 text-primary me-2"></i>Save Record
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <p class="text-muted small mb-3">
                        Saving this supplier immediately makes them selectable across raw material purchases and generates their financial ledger account.
                    </p>
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Add Supplier</span>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary w-100 py-2">
                            Cancel
                        </a>
                    </div>
                </div>
            </div>

            <!-- Procurement Integration Info Card -->
            <div class="pos-card">
                <div class="pos-card-header">
                    <span class="pos-card-title">
                        <i class="bi bi-diagram-3 text-secondary me-2"></i>Procurement Flow
                    </span>
                </div>
                <div class="pos-card-body p-3">
                    <div class="recipe-flow-card mb-3">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-primary text-white rounded-pill px-2">1</span>
                            <div>
                                <strong class="small d-block text-dark">Supplier Created</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Vendor record established in the master directory.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge bg-warning text-dark rounded-pill px-2">2</span>
                            <div>
                                <strong class="small d-block text-dark">Purchase Recording</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Select vendor when booking bulk raw material purchases.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-success text-white rounded-pill px-2">3</span>
                            <div>
                                <strong class="small d-block text-dark">Ledger Tracking</strong>
                                <span class="text-muted" style="font-size: 0.78rem;">Track paid, partial, and outstanding vendor liabilities automatically.</span>
                            </div>
                        </div>
                    </div>
                    <div class="text-muted small">
                        <i class="bi bi-shield-check text-success me-1"></i>
                        Role protected: Only authorized administrators may register new vendors.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<?php

require_once "../includes/footer.php";

?>