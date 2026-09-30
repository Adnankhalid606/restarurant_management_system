<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = $_POST["price"] ?? "";
    $is_available = $_POST["is_available"] ?? "1";
    $image_filename = null;

    if ($name === "") {
        $error = "Please enter an item name.";
    } elseif ($price === "" || !is_numeric($price) || (float)$price < 0) {
        $error = "Please enter a valid price.";
    }

    if ($error === "" && isset($_FILES["image"]) && $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES["image"]["error"] !== UPLOAD_ERR_OK) {
            $error = "File upload failed with error code: " . (int)$_FILES["image"]["error"];
        } elseif ($_FILES["image"]["size"] > 3 * 1024 * 1024) {
            $error = "Image size cannot exceed 3MB.";
        } else {
            $allowed_mimes = ["image/jpeg", "image/png", "image/webp"];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES["image"]["tmp_name"]);
            finfo_close($finfo);

            $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
            $allowed_exts = ["jpg", "jpeg", "png", "webp"];

            if (!in_array($mime, $allowed_mimes) || !in_array($ext, $allowed_exts)) {
                $error = "Only JPG, PNG, and WebP images are allowed.";
            } else {
                $upload_dir = dirname(__DIR__) . "/assets/uploads/menu/";
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $image_filename = "menu_" . bin2hex(random_bytes(8)) . "_" . time() . "." . $ext;
                $dest_path = $upload_dir . $image_filename;

                if (!move_uploaded_file($_FILES["image"]["tmp_name"], $dest_path)) {
                    $error = "Failed to save the uploaded image file.";
                    $image_filename = null;
                }
            }
        }
    }

    if ($error === "") {
        $sql = "INSERT INTO menu_items
                (name, category, price, is_available, image)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssdis",
            $name,
            $category,
            $price,
            $is_available,
            $image_filename
        );

        mysqli_stmt_execute($stmt);

        header("Location: index.php");
        exit;
    }
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

<?php if ($error !== "") { ?>
    <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 border-danger-subtle" role="alert">
        <i class="bi bi-exclamation-triangle-fill text-danger me-2 fs-5"></i>
        <div class="small fw-medium"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
<?php } ?>

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
                <form method="POST" enctype="multipart/form-data">
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

                    <!-- Item Image Upload -->
                    <div class="mb-3">
                        <label for="itemImage" class="form-label pos-form-label">
                            Item Photo / Image
                        </label>
                        <input
                            type="file"
                            class="form-control pos-form-control"
                            id="itemImage"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp"
                            onchange="previewImage(this)"
                        >
                        <div class="form-text text-muted small">Optional. Allowed formats: JPG, PNG, WebP (max 3MB). Displayed on the POS Order screen.</div>
                        <div id="imagePreviewContainer" class="mt-2 d-none">
                            <img id="imagePreview" src="#" alt="Preview" class="rounded border object-fit-cover" style="width: 90px; height: 90px;">
                        </div>
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

<script>
function previewImage(input) {
    const previewContainer = document.getElementById('imagePreviewContainer');
    const preview = document.getElementById('imagePreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            previewContainer.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        previewContainer.classList.add('d-none');
        preview.src = '#';
    }
}
</script>

<?php

require_once "../includes/footer.php";

?>