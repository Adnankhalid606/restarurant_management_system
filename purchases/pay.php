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


// Get purchase

$sql = "SELECT
            id,
            supplier_id,
            total_amount,
            payment_status
        FROM purchases
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$purchase = mysqli_fetch_assoc($result);

if (!$purchase) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}


// Check payment

if ($purchase["payment_status"] === "paid") {
    $_SESSION["error"] = "Purchase is already paid.";
    header("Location: view.php?id=" . $id);
    exit;
}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    mysqli_begin_transaction($conn);

    try {

        // Lock purchase

        $sql = "SELECT
                    id,
                    payment_status
                FROM purchases
                WHERE id = ?
                FOR UPDATE";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $purchase = mysqli_fetch_assoc($result);

        if (!$purchase) {
            throw new Exception("Purchase not found.");
        }


        // Check payment

        if ($purchase["payment_status"] === "paid") {
            throw new Exception("Purchase is already paid.");
        }


        // Mark paid

        $sql = "UPDATE purchases
                SET payment_status = 'paid'
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($stmt);


        mysqli_commit($conn);

        header("Location: view.php?id=" . $id);
        exit;
    } catch (Exception $error) {

        mysqli_rollback($conn);

        die($error->getMessage());
    }
}

$page_title = "Pay Purchase #" . $purchase["id"];
$active_menu = "purchases";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-light text-dark border font-monospace">
                #PO-<?php echo str_pad($purchase["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
            <span class="badge-subtle badge-status-preparing">
                <i class="bi bi-clock-history me-1"></i>Awaiting Settlement
            </span>
        </div>
        <h2 class="page-header-title">Confirm Purchase Payment</h2>
        <p class="page-header-subtitle">Record full disbursement for Purchase Order #PO-<?php echo str_pad($purchase["id"], 3, '0', STR_PAD_LEFT); ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Purchase</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-credit-card text-success me-2"></i>Payment Confirmation
                </span>
                <span class="badge bg-light text-dark border">
                    Current: <?php echo ucfirst($purchase["payment_status"]); ?>
                </span>
            </div>
            <div class="pos-card-body p-4 text-center">
                <div class="mb-3 text-muted">
                    <div class="d-inline-flex p-3 rounded-circle bg-light border text-success mb-2">
                        <i class="bi bi-wallet2 fs-1"></i>
                    </div>
                </div>

                <h6 class="text-muted small text-uppercase mb-1">Total Invoice Payable</h6>
                <div class="fs-1 fw-bold text-dark font-monospace mb-3">
                    Rs. <?php echo number_format($purchase["total_amount"], 2); ?>
                </div>

                <div class="p-3 bg-light rounded border text-start mb-4">
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="text-muted small">Purchase Reference:</span>
                        <span class="font-monospace fw-semibold text-dark">#PO-<?php echo str_pad($purchase["id"], 3, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                        <span class="text-muted small">Current Payment Status:</span>
                        <span class="badge badge-subtle badge-status-cancelled text-uppercase">
                            <?php echo htmlspecialchars($purchase["payment_status"]); ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-1">
                        <span class="text-muted small">Status After Confirmation:</span>
                        <span class="badge badge-subtle badge-status-ready text-uppercase">
                            Paid
                        </span>
                    </div>
                </div>

                <p class="text-muted small mb-4">
                    Are you sure you want to mark this purchase as <strong>Paid</strong>? This action updates the purchase record and reflects in vendor liability accounting.
                </p>

                <form method="POST">
                    <div class="d-flex flex-column gap-2">
                        <button type="submit" class="btn btn-success py-2 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm">
                            <i class="bi bi-check2-circle fs-5"></i>
                            <span>Confirm Payment</span>
                        </button>
                        <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary py-2">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>