<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$id = (int) ($_GET["id"] ?? 0);
if ($id <= 0) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

$sql = "SELECT
            bills.id,
            bills.order_id,
            bills.subtotal,
            bills.discount,
            bills.tax,
            bills.total_amount,
            bills.payment_status,
            bills.paid_at,
            bills.created_at,
            orders.order_type,
            orders.status AS order_status,
            orders.created_at AS order_created_at,
            customers.name AS customer_name,
            customers.phone AS customer_phone
        FROM bills
        JOIN orders ON bills.order_id = orders.id
        LEFT JOIN customers ON orders.customer_id = customers.id
        WHERE bills.id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$bill = mysqli_fetch_assoc($result);

if (!$bill) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

// Get order items
$sql = "SELECT
            order_items.quantity,
            order_items.unit_price,
            order_items.subtotal,
            menu_items.name AS menu_item_name
        FROM order_items
        JOIN menu_items ON order_items.menu_item_id = menu_items.id
        WHERE order_items.order_id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $bill["order_id"]);
mysqli_stmt_execute($stmt);

$items = mysqli_stmt_get_result($stmt);

$page_title = "Bill #" . $bill["id"];
$active_menu = "bills";

require_once "../includes/header.php";

$is_paid = ($bill["payment_status"] === "paid");
?>

<!-- Page Header Bar (Hidden on print) -->
<div class="page-header-bar d-print-none">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
            <h2 class="page-header-title">Bill #<?php echo $bill["id"]; ?></h2>
            <?php if ($is_paid) { ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-check-circle-fill me-1"></i>Paid in Full
                </span>
            <?php } else { ?>
                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1">
                    <i class="bi bi-clock-history me-1"></i>Payment Pending
                </span>
            <?php } ?>
        </div>
        <p class="page-header-subtitle">
            Order #<?php echo $bill["order_id"]; ?> &bull; Created on <?php echo formatDateTime($bill["created_at"]); ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Bills</span>
        </a>
        <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-printer"></i>
            <span>Print Receipt</span>
        </button>
        <?php if (!$is_paid) { ?>
            <a href="pay.php?id=<?php echo $bill["id"]; ?>" 
               class="btn btn-success btn-sm d-inline-flex align-items-center gap-1 shadow-sm px-3"
               onclick="return confirm('Confirm settlement of Rs. <?php echo number_format($bill['total_amount'], 2); ?> for Bill #<?php echo $bill['id']; ?>?');">
                <i class="bi bi-cash-stack"></i>
                <span>Mark as Paid</span>
            </a>
        <?php } ?>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-9 col-xl-8">
        <!-- Guest Invoice Document Card -->
        <div class="pos-card p-4 p-md-5 mb-4 shadow-sm" id="printableInvoice">
            <!-- Invoice Brand Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-start pb-4 border-bottom mb-4 gap-3">
                <div>
                    <h3 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-shop text-primary"></i>
                        <span>RestoBar POS &amp; Restaurant</span>
                    </h3>
                    <p class="text-muted small mb-0">Official Guest Check &bull; Dining Receipt</p>
                </div>
                <div class="text-md-end">
                    <div class="fs-5 fw-bold text-dark">INVOICE #<?php echo $bill["id"]; ?></div>
                    <div class="text-muted small">Date: <?php echo formatDateTime($bill["created_at"]); ?></div>
                    <div class="text-muted small">Ref Order: #<?php echo $bill["order_id"]; ?></div>
                </div>
            </div>

            <!-- Customer & Order Information Grid -->
            <div class="row g-4 mb-4 pb-4 border-bottom">
                <div class="col-12 col-sm-6">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-2">Billed To (Guest)</span>
                    <div class="fw-bold text-dark fs-6">
                        <?php echo htmlspecialchars($bill["customer_name"] ?? "Walk-in Guest", ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if (!empty($bill["customer_phone"])) { ?>
                        <div class="text-muted small mt-1">
                            <i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($bill["customer_phone"], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php } ?>
                </div>

                <div class="col-12 col-sm-6 text-sm-end">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-2">Order &amp; Settlement Status</span>
                    <div class="mb-2">
                        <span class="text-muted small me-2">Service Type:</span>
                        <span class="badge bg-light text-dark border text-capitalize">
                            <?php echo htmlspecialchars(str_replace('_', ' ', $bill["order_type"]), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>
                    <div>
                        <?php if ($is_paid) { ?>
                            <div class="paid-stamp">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>PAID IN FULL</span>
                            </div>
                            <?php if (!empty($bill["paid_at"])) { ?>
                                <div class="text-muted small mt-1">
                                    Settled: <?php echo formatDateTime($bill["paid_at"]); ?>
                                </div>
                            <?php } ?>
                        <?php } else { ?>
                            <div class="unpaid-stamp">
                                <i class="bi bi-hourglass-split"></i>
                                <span>UNPAID</span>
                            </div>
                            <div class="text-muted small mt-1">Payment is pending collection</div>
                        <?php } ?>
                    </div>
                </div>
            </div>

            <!-- Itemized Order Items Table -->
            <div class="table-responsive mb-4">
                <table class="table align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Item Description</th>
                            <th class="text-center" style="width: 100px;">Qty</th>
                            <th class="text-end" style="width: 140px;">Unit Price</th>
                            <th class="text-end" style="width: 150px;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        mysqli_data_seek($items, 0);
                        while ($item = mysqli_fetch_assoc($items)) { 
                        ?>
                            <tr>
                                <td class="text-muted small"><?php echo $counter++; ?></td>
                                <td>
                                    <span class="fw-semibold text-dark">
                                        <?php echo htmlspecialchars($item["menu_item_name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border px-2 py-1">
                                        <?php echo $item["quantity"]; ?> &times;
                                    </span>
                                </td>
                                <td class="text-end text-muted">
                                    Rs. <?php echo number_format($item["unit_price"], 2); ?>
                                </td>
                                <td class="text-end fw-semibold text-dark">
                                    Rs. <?php echo number_format($item["subtotal"], 2); ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <!-- Financial Settlement Totals Breakdown -->
            <div class="row justify-content-end mb-4">
                <div class="col-12 col-sm-7 col-md-6 col-lg-5">
                    <div class="bg-light p-3 rounded border">
                        <div class="d-flex justify-content-between py-1 text-muted">
                            <span>Order Subtotal:</span>
                            <span class="fw-semibold text-dark">Rs. <?php echo number_format($bill["subtotal"], 2); ?></span>
                        </div>
                        <?php if ($bill["discount"] > 0) { ?>
                            <div class="d-flex justify-content-between py-1 text-success">
                                <span>Discount:</span>
                                <span>- Rs. <?php echo number_format($bill["discount"], 2); ?></span>
                            </div>
                        <?php } ?>
                        <?php if ($bill["tax"] > 0) { ?>
                            <div class="d-flex justify-content-between py-1 text-muted">
                                <span>Tax / Surcharge:</span>
                                <span>+ Rs. <?php echo number_format($bill["tax"], 2); ?></span>
                            </div>
                        <?php } ?>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center pt-1">
                            <span class="fw-bold text-dark fs-6">Grand Total:</span>
                            <span class="fs-4 fw-bold text-primary">Rs. <?php echo number_format($bill["total_amount"], 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Receipt Footer Note -->
            <div class="text-center pt-4 border-top text-muted small">
                <p class="mb-1">Thank you for dining with us! We appreciate your business.</p>
                <p class="mb-0 opacity-75">Computer-generated guest receipt &bull; RestoBar POS System</p>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>