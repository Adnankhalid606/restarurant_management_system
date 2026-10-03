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
            reservations.id,
            customers.name AS customer_name,
            customers.phone AS customer_phone,
            restaurant_tables.table_number,
            restaurant_tables.capacity,
            reservations.reservation_date,
            reservations.reservation_time,
            reservations.reservation_end_time,
            reservations.guests,
            reservations.status,
            reservations.created_at

        FROM reservations

        JOIN customers
            ON reservations.customer_id = customers.id

        JOIN restaurant_tables
            ON reservations.table_id = restaurant_tables.id

        WHERE reservations.id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $id
);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$reservation = mysqli_fetch_assoc($result);

if (!$reservation) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

$page_title = "Reservation #" . $reservation["id"];
$active_menu = "reservations";

require_once "../includes/header.php";

$st = $reservation['status'] ?? 'pending';
$badge_class = match($st) {
    'confirmed' => 'bg-success-subtle text-success border-success-subtle',
    'pending' => 'bg-warning-subtle text-warning border-warning-subtle',
    'completed' => 'bg-secondary-subtle text-secondary border',
    'cancelled' => 'bg-danger-subtle text-danger border-danger-subtle',
    default => 'bg-light text-muted border',
};
$status_icon = match($st) {
    'confirmed' => 'bi-check-circle-fill',
    'pending' => 'bi-hourglass-split',
    'completed' => 'bi-check2-all',
    'cancelled' => 'bi-x-circle',
    default => 'bi-circle',
};
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Reservation #<?php echo $reservation["id"]; ?></h2>
        <p class="page-header-subtitle">
            Booking for <?php echo htmlspecialchars($reservation["customer_name"], ENT_QUOTES, 'UTF-8'); ?> &bull; Table <?php echo htmlspecialchars($reservation["table_number"], ENT_QUOTES, 'UTF-8'); ?>
        </p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>All Reservations</span>
        </a>
        <a href="edit.php?id=<?php echo $reservation["id"]; ?>" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-pencil"></i>
            <span>Edit Booking</span>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Main Reservation Details -->
    <div class="col-12 col-lg-8">
        <!-- Schedule & Status Card -->
        <div class="pos-card shadow-sm mb-4">
            <div class="pos-card-header bg-light d-flex align-items-center justify-content-between">
                <span class="pos-card-title">
                    <i class="bi bi-calendar-event text-primary me-2"></i>Schedule &amp; Seating Details
                </span>
                <span class="badge <?php echo $badge_class; ?> border text-capitalize px-3 py-1 fs-6">
                    <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>
            <div class="pos-card-body p-4">
                <div class="row g-4">
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded border">
                            <span class="text-muted small d-block mb-1">
                                <i class="bi bi-calendar3 me-1"></i>Reservation Date
                            </span>
                            <span class="fs-5 fw-bold text-dark">
                                <?php echo formatDate($reservation["reservation_date"]); ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <div class="p-3 bg-light rounded border">
                            <span class="text-muted small d-block mb-1">
                                <i class="bi bi-clock me-1"></i>Reserved Time Window
                            </span>
                            <span class="fs-5 fw-bold text-dark">
                                <?php echo formatTime($reservation["reservation_time"]); ?><?php echo !empty($reservation["reservation_end_time"]) ? ' &ndash; ' . formatTime($reservation["reservation_end_time"]) : ''; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="row g-4">
                    <div class="col-12 col-sm-6">
                        <span class="text-muted small d-block mb-1">Assigned Dining Table</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-2">
                                <i class="bi bi-grid-3x3-gap me-1"></i>Table <?php echo htmlspecialchars($reservation["table_number"], ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                            <span class="table-capacity-chip">
                                <i class="bi bi-people-fill text-secondary"></i>
                                Max <?php echo htmlspecialchars($reservation["capacity"], ENT_QUOTES, 'UTF-8'); ?> Seats
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-sm-6">
                        <span class="text-muted small d-block mb-1">Booked Party Size</span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="fs-5 fw-bold text-dark">
                                <?php echo htmlspecialchars($reservation["guests"], ENT_QUOTES, 'UTF-8'); ?> Guests
                            </span>
                            <?php if ($reservation["guests"] > $reservation["capacity"]) { ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Exceeds Capacity
                                </span>
                            <?php } else { ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle">
                                    <i class="bi bi-check-circle-fill me-1"></i>Capacity OK
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="pos-card-footer bg-light px-4 py-2 border-top text-muted small d-flex justify-content-between">
                <span><i class="bi bi-clock-history me-1"></i>Booked on: <?php echo formatDateTime($reservation["created_at"]); ?></span>
                <span>Reference: #<?php echo $reservation["id"]; ?></span>
            </div>
        </div>
    </div>

    <!-- Customer & Actions Sidebar -->
    <div class="col-12 col-lg-4">
        <!-- Customer Info Card -->
        <div class="pos-card shadow-sm mb-4">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-person-circle text-primary me-2"></i>Guest Contact
                </span>
            </div>
            <div class="pos-card-body p-4">
                <div class="mb-3">
                    <span class="text-muted small d-block mb-1">Customer Name</span>
                    <span class="fs-5 fw-bold text-dark">
                        <?php echo htmlspecialchars($reservation["customer_name"], ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <div>
                    <span class="text-muted small d-block mb-1">Phone Number</span>
                    <?php if (!empty($reservation["customer_phone"])) { ?>
                        <a href="tel:<?php echo htmlspecialchars($reservation["customer_phone"], ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-telephone-fill text-primary"></i>
                            <span><?php echo htmlspecialchars($reservation["customer_phone"], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php } else { ?>
                        <span class="text-muted fst-italic">No phone number recorded</span>
                    <?php } ?>
                </div>
            </div>
        </div>

        <!-- Management Actions Card -->
        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-gear text-secondary me-2"></i>Reservation Actions
                </span>
            </div>
            <div class="pos-card-body p-3 d-flex flex-column gap-2">
                <a href="edit.php?id=<?php echo $reservation["id"]; ?>" class="btn btn-primary d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-pencil"></i>
                    <span>Modify Reservation</span>
                </a>
                <a href="index.php" class="btn btn-outline-secondary d-flex align-items-center justify-content-center gap-2">
                    <i class="bi bi-list-ul"></i>
                    <span>Return to Bookings</span>
                </a>
                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                    <hr class="my-1">
                    <a href="delete.php?id=<?php echo $reservation["id"]; ?>" 
                       class="btn btn-outline-danger d-flex align-items-center justify-content-center gap-2"
                       onclick="return confirm('Are you sure you want to permanently cancel and delete reservation #<?php echo $reservation['id']; ?>?');">
                        <i class="bi bi-trash"></i>
                        <span>Delete Reservation</span>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

<?php

require_once "../includes/footer.php";

?>