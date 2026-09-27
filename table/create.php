<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $table_number = $_POST["table_number"];
    $capacity = $_POST["capacity"];
    $status = $_POST["status"];

    $sql = "INSERT INTO restaurant_tables
            (table_number, capacity, status)
            VALUES (?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iis",
        $table_number,
        $capacity,
        $status
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Add Restaurant Table";
$active_menu = "table";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Add Restaurant Table</h2>
        <p class="page-header-subtitle">Configure a new dining table on the restaurant floor</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Tables</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Table Specification
                </span>
                <span class="badge bg-white text-muted border">New Record</span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST">
                    <!-- Table Number -->
                    <div class="mb-3">
                        <label for="tableNumber" class="form-label pos-form-label">
                            Table Number <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-grid-3x3-gap text-muted"></i>
                            </span>
                            <input
                                type="number"
                                class="form-control pos-form-control border-start-0"
                                id="tableNumber"
                                name="table_number"
                                placeholder="e.g. 1"
                                min="1"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Unique numerical identifier for table service.</div>
                    </div>

                    <!-- Seating Capacity -->
                    <div class="mb-3">
                        <label for="tableCapacity" class="form-label pos-form-label">
                            Seating Capacity <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-people text-muted"></i>
                            </span>
                            <input
                                type="number"
                                class="form-control pos-form-control border-start-0"
                                id="tableCapacity"
                                name="capacity"
                                placeholder="e.g. 4"
                                min="1"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Maximum number of guest chairs or covers for this table.</div>
                    </div>

                    <!-- Initial Status -->
                    <div class="mb-4">
                        <label for="tableStatus" class="form-label pos-form-label">
                            Initial Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="tableStatus" class="form-select pos-form-control py-2" required>
                            <option value="available" selected>Available (Ready for walk-in or new dine-in order)</option>
                            <option value="occupied">Occupied (Seated guests currently dining)</option>
                            <option value="reserved">Reserved (Held for upcoming reservation)</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-check-lg"></i>
                            <span>Save Table</span>
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