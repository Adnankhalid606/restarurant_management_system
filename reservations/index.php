<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$sql = "SELECT
            reservations.id,
            customers.name AS customer_name,
            restaurant_tables.table_number,
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

        ORDER BY
            reservations.reservation_date ASC,
            reservations.reservation_time ASC";

$result = mysqli_query($conn, $sql);

$reservations = [];
$counts = [
    'all' => 0,
    'confirmed' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0
];

while ($row = mysqli_fetch_assoc($result)) {
    $reservations[] = $row;
    $counts['all']++;
    $st = $row['status'] ?? 'pending';
    if (isset($counts[$st])) {
        $counts[$st]++;
    }
}

$page_title = "Reservations";
$active_menu = "reservations";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Table Reservations</h2>
        <p class="page-header-subtitle">Manage guest bookings, seating schedules &amp; party allocations</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-plus-lg"></i>
            <span>New Reservation</span>
        </a>
    </div>
</div>

<!-- Reservation Metric Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Total Bookings</span>
                <span class="fs-4 fw-bold text-dark"><?php echo $counts['all']; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-calendar-check fs-4"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Confirmed</span>
                <span class="fs-4 fw-bold text-success"><?php echo $counts['confirmed']; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Pending</span>
                <span class="fs-4 fw-bold text-warning"><?php echo $counts['pending']; ?></span>
            </div>
            <div class="badge-subtle badge-status-pending p-2 rounded">
                <i class="bi bi-hourglass-split fs-4 text-warning"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted small d-block">Completed</span>
                <span class="fs-4 fw-bold text-secondary"><?php echo $counts['completed']; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-check2-all fs-4 text-secondary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filters Bar -->
<div class="pos-card mb-4">
    <div class="p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="btn-group btn-group-sm" role="group" id="reservationFilterGroup">
            <button type="button" class="btn btn-outline-secondary active" data-filter="all">
                All Bookings (<?php echo $counts['all']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="confirmed">
                Confirmed (<?php echo $counts['confirmed']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="pending">
                Pending (<?php echo $counts['pending']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="completed">
                Completed (<?php echo $counts['completed']; ?>)
            </button>
            <button type="button" class="btn btn-outline-secondary" data-filter="cancelled">
                Cancelled (<?php echo $counts['cancelled']; ?>)
            </button>
        </div>

        <div class="small text-muted">
            <i class="bi bi-clock me-1"></i>Showing active &amp; historical reservations
        </div>
    </div>
</div>

<?php if (empty($reservations)) { ?>
    <!-- Empty State -->
    <div class="pos-card p-5 text-center">
        <div class="text-muted mb-3">
            <i class="bi bi-calendar-x fs-1 text-secondary opacity-50"></i>
        </div>
        <h5 class="fw-semibold text-dark mb-1">No Reservations Found</h5>
        <p class="text-muted small mb-4">There are currently no guest table reservations registered.</p>
        <a href="create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Create First Reservation
        </a>
    </div>
<?php } else { ?>
    <div class="pos-card overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="reservationsTable">
                <thead class="table-light">
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Customer / Guest</th>
                        <th>Table</th>
                        <th>Date &amp; Schedule</th>
                        <th>Party Size</th>
                        <th>Status</th>
                        <th style="width: 140px;" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reservations as $r) {
                        $st = $r['status'] ?? 'pending';
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
                        <tr data-status="<?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="text-muted fw-semibold">#<?php echo $r['id']; ?></td>
                            <td>
                                <div class="fw-bold text-dark d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-light border p-1 d-inline-flex align-items-center justify-content-center" style="width: 28px; height: 28px;">
                                        <i class="bi bi-person text-secondary small"></i>
                                    </div>
                                    <span><?php echo htmlspecialchars($r['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">
                                    <i class="bi bi-grid-3x3-gap me-1"></i>Table <?php echo htmlspecialchars($r['table_number'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <i class="bi bi-calendar3 me-1 text-muted"></i><?php echo htmlspecialchars($r['reservation_date'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($r['reservation_time'], ENT_QUOTES, 'UTF-8'); ?> &ndash; <?php echo htmlspecialchars($r['reservation_end_time'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td>
                                <span class="table-capacity-chip">
                                    <i class="bi bi-people-fill text-secondary"></i>
                                    <strong><?php echo htmlspecialchars($r['guests'], ENT_QUOTES, 'UTF-8'); ?></strong> Guests
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $badge_class; ?> border text-capitalize px-2 py-1">
                                    <i class="bi <?php echo $status_icon; ?> me-1"></i><?php echo htmlspecialchars($st, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <a href="view.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-secondary btn-sm" title="View Reservation Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit.php?id=<?php echo $r['id']; ?>" class="btn btn-outline-secondary btn-sm" title="Edit Reservation">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if (($_SESSION['role'] ?? '') === 'admin') { ?>
                                    <a href="delete.php?id=<?php echo $r['id']; ?>" 
                                       class="btn btn-outline-danger btn-sm" 
                                       title="Delete Reservation"
                                       onclick="return confirm('Are you sure you want to delete reservation #<?php echo $r['id']; ?>?');">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Client-side filter script for Reservations -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const filterButtons = document.querySelectorAll('#reservationFilterGroup button');
        const rows = document.querySelectorAll('#reservationsTable tbody tr');

        filterButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                filterButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const filter = this.getAttribute('data-filter');

                rows.forEach(row => {
                    if (filter === 'all' || row.getAttribute('data-status') === filter) {
                        row.classList.remove('d-none');
                    } else {
                        row.classList.add('d-none');
                    }
                });
            });
        });
    });
    </script>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>