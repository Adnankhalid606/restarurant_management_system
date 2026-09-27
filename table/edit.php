<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

$sql = "SELECT * FROM restaurant_tables WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$table = mysqli_fetch_assoc($result);

if (!$table) {
    die("Table not found.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $table_number = $_POST["table_number"];
    $capacity = $_POST["capacity"];
    $status = $_POST["status"];

    $sql = "UPDATE restaurant_tables
            SET table_number = ?, capacity = ?, status = ?
            WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "iisi",
        $table_number,
        $capacity,
        $status,
        $id
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Edit Table #" . $table["table_number"];
$active_menu = "table";

require_once "../includes/header.php";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Edit Table #<?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?></h2>
        <p class="page-header-subtitle">Update dining table configuration, seating capacity, or floor status</p>
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
                    <i class="bi bi-pencil-square text-primary me-2"></i>Edit Table Record
                </span>
                <span class="badge bg-white text-muted border">ID #<?php echo $table["id"]; ?></span>
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
                                value="<?php echo htmlspecialchars($table["table_number"], ENT_QUOTES, 'UTF-8'); ?>"
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
                                value="<?php echo htmlspecialchars($table["capacity"], ENT_QUOTES, 'UTF-8'); ?>"
                                min="1"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Maximum number of guest chairs or covers for this table.</div>
                    </div>

                    <!-- Status -->
                    <div class="mb-4">
                        <label for="tableStatus" class="form-label pos-form-label">
                            Current Status <span class="text-danger">*</span>
                        </label>
                        <select name="status" id="tableStatus" class="form-select pos-form-control py-2" required>
                            <option value="available" <?php if ($table["status"] === "available") echo "selected"; ?>>
                                Available (Ready for walk-in or new dine-in order)
                            </option>
                            <option value="occupied" <?php if ($table["status"] === "occupied") echo "selected"; ?>>
                                Occupied (Seated guests currently dining)
                            </option>
                            <option value="reserved" <?php if ($table["status"] === "reserved") echo "selected"; ?>>
                                Reserved (Held for upcoming reservation)
                            </option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="delete.php?id=<?php echo $table["id"]; ?>" 
                           class="btn btn-outline-danger px-3 d-inline-flex align-items-center gap-1"
                           onclick="return confirm('Are you sure you want to delete Table <?php echo htmlspecialchars($table['table_number'], ENT_QUOTES, 'UTF-8'); ?>?');">
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </a>
                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="bi bi-check-lg"></i>
                                <span>Update Table</span>
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