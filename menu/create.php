<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $category = $_POST["category"];
    $price = $_POST["price"];
    $is_available = $_POST["is_available"];

    $sql = "INSERT INTO menu_items
            (name, category, price, is_available)
            VALUES (?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssdi",
        $name,
        $category,
        $price,
        $is_available
    );

    mysqli_stmt_execute($stmt);

    header("Location: index.php");
    exit;
}

$page_title = "Add Menu Item";
$active_menu = "menu";

require_once "../includes/header.php";

$posted_name = $_POST["name"] ?? "";
$posted_cat = $_POST["category"] ?? "";
$posted_price = $_POST["price"] ?? "";
$posted_avail = $_POST["is_available"] ?? "1";
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Add Menu Item</h2>
        <p class="page-header-subtitle">Introduce a new dish, drink, or meal to the restaurant catalogue</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Menu</span>
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header bg-light">
                <span class="pos-card-title">
                    <i class="bi bi-plus-circle text-primary me-2"></i>Item Specification
                </span>
                <span class="badge bg-white text-muted border">New Catalogue Item</span>
            </div>
            <div class="pos-card-body p-4">
                <form method="POST">
                    <!-- Item Name -->
                    <div class="mb-3">
                        <label for="itemName" class="form-label pos-form-label">
                            Dish / Item Name <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-egg-fried text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0 py-2"
                                id="itemName"
                                name="name"
                                placeholder="e.g. Chicken Biryani"
                                value="<?php echo htmlspecialchars($posted_name, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">The official name that appears on tickets, orders, and guest checks.</div>
                    </div>

                    <!-- Category -->
                    <div class="mb-3">
                        <label for="itemCategory" class="form-label pos-form-label">
                            Category / Group
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0">
                                <i class="bi bi-tag text-muted"></i>
                            </span>
                            <input
                                type="text"
                                class="form-control pos-form-control border-start-0 py-2"
                                id="itemCategory"
                                name="category"
                                placeholder="e.g. Main Course, Starters, Drinks"
                                value="<?php echo htmlspecialchars($posted_cat, ENT_QUOTES, 'UTF-8'); ?>"
                            >
                        </div>
                        <div class="form-text text-muted small">Groups similar items together on the POS screen.</div>
                    </div>

                    <!-- Selling Price -->
                    <div class="mb-3">
                        <label for="itemPrice" class="form-label pos-form-label">
                            Selling Price (Rs.) <span class="text-danger">*</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 fw-semibold text-dark">Rs.</span>
                            <input
                                type="number"
                                class="form-control pos-form-control border-start-0 py-2 fw-semibold fs-6"
                                id="itemPrice"
                                name="price"
                                step="0.01"
                                min="0"
                                placeholder="0.00"
                                value="<?php echo htmlspecialchars($posted_price, ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Customer billing rate per unit.</div>
                    </div>

                    <!-- Availability Status -->
                    <div class="mb-4">
                        <label for="itemAvailability" class="form-label pos-form-label">
                            Initial Availability Status <span class="text-danger">*</span>
                        </label>
                        <select name="is_available" id="itemAvailability" class="form-select pos-form-control py-2" required>
                            <option value="1" <?php if ($posted_avail === "1") echo "selected"; ?>>
                                Available (Active &amp; ready for order taking)
                            </option>
                            <option value="0" <?php if ($posted_avail === "0") echo "selected"; ?>>
                                Unavailable (Temporarily sold out or out of stock)
                            </option>
                        </select>
                        <div class="form-text text-muted small">Unavailable items cannot be selected for new customer orders.</div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-end gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                            <i class="bi bi-check-lg"></i>
                            <span>Save Menu Item</span>
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