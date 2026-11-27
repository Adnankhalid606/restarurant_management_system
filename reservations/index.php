<?php

require_once "../config/database.php";
require_once "../includes/role.php";
require_once "../includes/pagination.php";

requireRole(["admin", "waiter"]);

$pg = get_pagination_params(25, [25, 50, 100]);
$status_filter = trim($_GET["status"] ?? "all");
if (!in_array($status_filter, ["all", "confirmed", "pending", "completed", "cancelled"], true)) {
    $status_filter = "all";
}

// 1. Summary KPI counts across all reservations
$counts = [
    'all' => 0,
    'confirmed' => 0,
    'pending' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$kpi_res = mysqli_query($conn, "SELECT status, COUNT(*) AS cnt FROM reservations GROUP BY status");
if ($kpi_res) {
    while ($kr = mysqli_fetch_assoc($kpi_res)) {
        $st = $kr['status'] ?? 'pending';
        $c = (int) $kr['cnt'];
        $counts['all'] += $c;
        if (isset($counts[$st])) {
            $counts[$st] = $c;
        }
    }
}

// 2. Filtered WHERE clause
$where_sql = "";
$bind_params = [];
$bind_types = "";
if ($status_filter !== "all") {
    $where_sql = "WHERE reservations.status = ?";
    $bind_params[] = $status_filter;
    $bind_types = "s";
}

// 3. Filtered COUNT query
$count_sql = "SELECT COUNT(*) AS total FROM reservations $where_sql";
$c_stmt = mysqli_prepare($conn, $count_sql);
if ($bind_types !== "") {
    mysqli_stmt_bind_param($c_stmt, $bind_types, ...$bind_params);
}
mysqli_stmt_execute($c_stmt);
$filtered_total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($c_stmt))['total'] ?? 0);
mysqli_stmt_close($c_stmt);

$pagination = finalize_pagination($filtered_total, $pg['page'], $pg['per_page']);
$limit = $pagination['per_page'];
$offset = $pagination['offset'];

// 4. Paginated data query (stable secondary sort by reservations.id ASC)
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
        $where_sql
        ORDER BY
            reservations.reservation_date ASC,
            reservations.reservation_time ASC,
            reservations.id ASC
        LIMIT ? OFFSET ?";

$d_stmt = mysqli_prepare($conn, $sql);
$data_types = $bind_types . "ii";
$data_params = array_merge($bind_params, [$limit, $offset]);
mysqli_stmt_bind_param($d_stmt, $data_types, ...$data_params);
mysqli_stmt_execute($d_stmt);
$result = mysqli_stmt_get_result($d_stmt);

$reservations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $reservations[] = $row;
}
mysqli_stmt_close($d_stmt);

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
            <a href="<?php echo htmlspecialchars(build_pagination_url(['status' => 'all', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($status_filter === 'all') ? 'active' : ''; ?>">
                All Bookings (<?php echo $counts['all']; ?>)
            </a>
            <a href="<?php echo htmlspecialchars(build_pagination_url(['status' => 'confirmed', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($status_filter === 'confirmed') ? 'active' : ''; ?>">
                Confirmed (<?php echo $counts['confirmed']; ?>)
            </a>
            <a href="<?php echo htmlspecialchars(build_pagination_url(['status' => 'pending', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($status_filter === 'pending') ? 'active' : ''; ?>">
                Pending (<?php echo $counts['pending']; ?>)
            </a>
            <a href="<?php echo htmlspecialchars(build_pagination_url(['status' => 'completed', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($status_filter === 'completed') ? 'active' : ''; ?>">
                Completed (<?php echo $counts['completed']; ?>)
            </a>
            <a href="<?php echo htmlspecialchars(build_pagination_url(['status' => 'cancelled', 'page' => 1]), ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline-secondary <?php echo ($status_filter === 'cancelled') ? 'active' : ''; ?>">
                Cancelled (<?php echo $counts['cancelled']; ?>)
            </a>
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
                                    <i class="bi bi-calendar3 me-1 text-muted"></i><?php echo formatDate($r['reservation_date']); ?>
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-clock me-1"></i><?php echo formatTime($r['reservation_time']); ?><?php echo !empty($r['reservation_end_time']) ? ' &ndash; ' . formatTime($r['reservation_end_time']) : ''; ?>
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
                                    <form method="POST" action="delete.php" class="d-inline m-0 p-0" onsubmit="return confirm('Are you sure you want to delete reservation #<?php echo $r['id']; ?>?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Reservation">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php render_pagination_bar($pagination); ?>
    </div>
<?php } ?>

<?php

require_once "../includes/footer.php";

?>