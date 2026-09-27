<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin", "waiter"]);

$customers = mysqli_query(
    $conn,
    "SELECT id, name
     FROM customers
     ORDER BY name ASC"
);

$tables = mysqli_query(
    $conn,
    "SELECT id, table_number, capacity
     FROM restaurant_tables
     ORDER BY table_number ASC"
);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customer_id = $_POST["customer_id"];
    $table_id = $_POST["table_id"];
    $reservation_date = $_POST["reservation_date"];
    $reservation_time = $_POST["reservation_time"];
    $reservation_end_time = $_POST["reservation_end_time"];
    $guests = $_POST["guests"];
    $status = $_POST["status"];
    if ($reservation_end_time <= $reservation_time) {

        $error = "End time must be after start time.";
    }
    if ($error === "") {

        //Check table capacity


        $sql = "SELECT capacity
            FROM restaurant_tables
            WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $table_id
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $table = mysqli_fetch_assoc($result);

        if (!$table) {
            die("Table not found.");
        }

        if ($guests > $table["capacity"]) {

            $error = "Number of guests is greater than table capacity.";
        } else {

            //Check overlapping reservation


            $sql = "SELECT id
                FROM reservations

                WHERE table_id = ?

                AND reservation_date = ?

                AND status IN ('pending', 'confirmed')

                AND reservation_time < ?

                AND reservation_end_time > ?";

            $stmt = mysqli_prepare($conn, $sql);

            mysqli_stmt_bind_param(
                $stmt,
                "isss",
                $table_id,
                $reservation_date,
                $reservation_end_time,
                $reservation_time
            );

            mysqli_stmt_execute($stmt);

            $result = mysqli_stmt_get_result($stmt);

            if (mysqli_num_rows($result) > 0) {

                $error = "This table is already reserved during this time.";
            } else {

                //Create reservation

                $sql = "INSERT INTO reservations
                    (
                        customer_id,
                        table_id,
                        reservation_date,
                        reservation_time,
                        reservation_end_time,
                        guests,
                        status
                    )

                    VALUES (?, ?, ?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "iisssis",
                    $customer_id,
                    $table_id,
                    $reservation_date,
                    $reservation_time,
                    $reservation_end_time,
                    $guests,
                    $status
                );

                mysqli_stmt_execute($stmt);

                header("Location: index.php");
                exit;
            }
        }
    }
}

$page_title = "Create Reservation";
$active_menu = "reservations";

require_once "../includes/header.php";

$posted_cust = $_POST["customer_id"] ?? "";
$posted_tbl = $_POST["table_id"] ?? "";
$posted_date = $_POST["reservation_date"] ?? date("Y-m-d");
$posted_start = $_POST["reservation_time"] ?? "19:00";
$posted_end = $_POST["reservation_end_time"] ?? "21:00";
$posted_guests = $_POST["guests"] ?? "2";
$posted_status = $_POST["status"] ?? "pending";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Create Reservation</h2>
        <p class="page-header-subtitle">Book a table schedule and assign party capacity</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Reservations</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <?php if ($error !== "") { ?>
            <div class="alert alert-danger d-flex align-items-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0"></i>
                <div>
                    <strong>Reservation Error:</strong> <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        <?php } ?>

        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-calendar-plus text-primary me-2"></i>New Reservation Details
                </span>
                <span class="badge bg-white text-muted border">New Booking</span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST">
                    <!-- Section 1: Customer & Table Assignment -->
                    <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2">
                        1. Guest &amp; Seating Assignment
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="customerId" class="form-label pos-form-label">
                                Customer <span class="text-danger">*</span>
                            </label>
                            <select name="customer_id" id="customerId" class="form-select pos-form-control py-2" required>
                                <option value="">-- Choose Customer --</option>
                                <?php 
                                mysqli_data_seek($customers, 0);
                                while ($customer = mysqli_fetch_assoc($customers)) { 
                                    $selected = ($customer["id"] == $posted_cust) ? "selected" : "";
                                ?>
                                    <option value="<?php echo $customer["id"]; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($customer["name"], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="form-text text-muted small">Registered customer contact.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="tableId" class="form-label pos-form-label">
                                Table Allocation <span class="text-danger">*</span>
                            </label>
                            <select name="table_id" id="tableId" class="form-select pos-form-control py-2" required>
                                <option value="">-- Choose Dining Table --</option>
                                <?php 
                                mysqli_data_seek($tables, 0);
                                while ($table = mysqli_fetch_assoc($tables)) { 
                                    $selected = ($table["id"] == $posted_tbl) ? "selected" : "";
                                ?>
                                    <option value="<?php echo $table["id"]; ?>" <?php echo $selected; ?>>
                                        Table <?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?> (Max <?php echo htmlspecialchars($table["capacity"], ENT_QUOTES, 'UTF-8'); ?> Seats)
                                    </option>
                                <?php } ?>
                            </select>
                            <div class="form-text text-muted small">Party size must not exceed capacity.</div>
                        </div>
                    </div>

                    <!-- Section 2: Date, Time & Party Size -->
                    <h6 class="text-uppercase text-muted fw-bold small mb-3 border-bottom pb-2">
                        2. Schedule &amp; Party Size
                    </h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4">
                            <label for="reservationDate" class="form-label pos-form-label">
                                Reservation Date <span class="text-danger">*</span>
                            </label>
                            <input
                                type="date"
                                class="form-control pos-form-control py-2"
                                id="reservationDate"
                                name="reservation_date"
                                value="<?php echo htmlspecialchars($posted_date, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="reservationTime" class="form-label pos-form-label">
                                Start Time <span class="text-danger">*</span>
                            </label>
                            <input
                                type="time"
                                class="form-control pos-form-control py-2"
                                id="reservationTime"
                                name="reservation_time"
                                value="<?php echo htmlspecialchars($posted_start, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-4">
                            <label for="reservationEndTime" class="form-label pos-form-label">
                                End Time <span class="text-danger">*</span>
                            </label>
                            <input
                                type="time"
                                class="form-control pos-form-control py-2"
                                id="reservationEndTime"
                                name="reservation_end_time"
                                value="<?php echo htmlspecialchars($posted_end, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="guestsCount" class="form-label pos-form-label">
                                Number of Guests <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-people text-muted"></i>
                                </span>
                                <input
                                    type="number"
                                    class="form-control pos-form-control border-start-0 py-2"
                                    id="guestsCount"
                                    name="guests"
                                    value="<?php echo htmlspecialchars($posted_guests, ENT_QUOTES, 'UTF-8'); ?>"
                                    min="1"
                                    required
                                >
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="reservationStatus" class="form-label pos-form-label">
                                Booking Status <span class="text-danger">*</span>
                            </label>
                            <select name="status" id="reservationStatus" class="form-select pos-form-control py-2">
                                <option value="pending" <?php if ($posted_status === 'pending') echo 'selected'; ?>>
                                    Pending (Awaiting Confirmation)
                                </option>
                                <option value="confirmed" <?php if ($posted_status === 'confirmed') echo 'selected'; ?>>
                                    Confirmed (Locked for Seating)
                                </option>
                                <option value="completed" <?php if ($posted_status === 'completed') echo 'selected'; ?>>
                                    Completed (Guest Finished)
                                </option>
                                <option value="cancelled" <?php if ($posted_status === 'cancelled') echo 'selected'; ?>>
                                    Cancelled (Released)
                                </option>
                            </select>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-calendar-check"></i>
                            <span>Create Reservation</span>
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