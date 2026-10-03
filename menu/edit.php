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

$sql = "SELECT * FROM menu_items WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$item = mysqli_fetch_assoc($result);

if (!$item) {
    $_SESSION["error"] = "Record not found or invalid identifier.";
    header("Location: index.php");
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = $_POST["price"] ?? "";
    $is_available = $_POST["is_available"] ?? "1";
    $remove_image = !empty($_POST["remove_image"]);

    $new_image_filename = $item["image"];
    $old_image_to_delete = null;

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

                $uploaded_filename = "menu_" . bin2hex(random_bytes(8)) . "_" . time() . "." . $ext;
                $dest_path = $upload_dir . $uploaded_filename;

                if (!move_uploaded_file($_FILES["image"]["tmp_name"], $dest_path)) {
                    $error = "Failed to save the uploaded image file.";
                } else {
                    $new_image_filename = $uploaded_filename;
                    if (!empty($item["image"])) {
                        $old_image_to_delete = $item["image"];
                    }
                }
            }
        }
    } elseif ($error === "" && $remove_image) {
        $new_image_filename = null;
        if (!empty($item["image"])) {
            $old_image_to_delete = $item["image"];
        }
    }

    if ($error === "") {
        $sql = "UPDATE menu_items
                SET name = ?, category = ?, price = ?, is_available = ?, image = ?
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssdisi",
            $name,
            $category,
            $price,
            $is_available,
            $new_image_filename,
            $id
        );

        if (mysqli_stmt_execute($stmt)) {
            // Only delete the old physical image after the database update succeeds
            if (!empty($old_image_to_delete) && $old_image_to_delete !== $new_image_filename) {
                $old_file_path = dirname(__DIR__) . "/assets/uploads/menu/" . $old_image_to_delete;
                if (file_exists($old_file_path)) {
                    @unlink($old_file_path);
                }
            }

            header("Location: index.php");
            exit;
        } else {
            $error = "Failed to update menu item: " . mysqli_error($conn);
        }
    }
}

$page_title = "Edit Menu Item #" . $id;
$active_menu = "menu";

require_once "../includes/header.php";

$existing_image_file = !empty($item["image"]) ? dirname(__DIR__) . "/assets/uploads/menu/" . $item["image"] : null;
$has_existing_image = $existing_image_file && file_exists($existing_image_file);
?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Edit Menu Item #<?php echo $id; ?></h2>
        <p class="page-header-subtitle">Update dish details, selling price, or toggle kitchen availability</p>
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
            <div class="pos-card-header bg-light d-flex align-items-center justify-content-between">
                <span class="pos-card-title">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Modify Menu Record
                </span>
                <span class="badge bg-white text-muted border">ID #<?php echo $id; ?></span>
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
                                value="<?php echo htmlspecialchars($_POST["name"] ?? $item["name"], ENT_QUOTES, 'UTF-8'); ?>"
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
                                value="<?php echo htmlspecialchars($_POST["category"] ?? ($item["category"] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
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
                                value="<?php echo htmlspecialchars($_POST["price"] ?? $item["price"], ENT_QUOTES, 'UTF-8'); ?>"
                                required
                            >
                        </div>
                        <div class="form-text text-muted small">Customer billing rate per unit.</div>
                    </div>

                    <!-- Current Image & Upload -->
                    <div class="mb-3">
                        <label class="form-label pos-form-label">Item Photo / Image</label>
                        <?php if ($has_existing_image) { ?>
                            <div class="d-flex align-items-center gap-3 p-2 border rounded bg-light mb-2">
                                <img src="../assets/uploads/menu/<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="Current Image" 
                                     class="rounded border object-fit-cover" 
                                     style="width: 72px; height: 72px;">
                                <div>
                                    <div class="small fw-semibold text-dark mb-1">Current Image</div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="removeImageCheck">
                                        <label class="form-check-label small text-danger" for="removeImageCheck">
                                            Remove image (switch to placeholder)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="text-muted small mb-2 d-flex align-items-center gap-2 p-2 border rounded bg-light">
                                <i class="bi bi-card-image fs-4 text-secondary"></i>
                                <span>No photo uploaded yet. A clean placeholder is displayed on the POS screen.</span>
                            </div>
                        <?php } ?>

                        <label for="itemImage" class="form-label small text-muted mb-1">
                            <?php echo $has_existing_image ? 'Replace Photo (Optional):' : 'Upload Photo:'; ?>
                        </label>
                        <input
                            type="file"
                            class="form-control pos-form-control"
                            id="itemImage"
                            name="image"
                            accept=".jpg,.jpeg,.png,.webp"
                            onchange="previewImage(this)"
                        >
                        <div class="form-text text-muted small">Allowed formats: JPG, PNG, WebP (max 3MB).</div>
                        <div id="imagePreviewContainer" class="mt-2 d-none">
                            <span class="small text-muted d-block mb-1">New Image Preview:</span>
                            <img id="imagePreview" src="#" alt="Preview" class="rounded border object-fit-cover" style="width: 80px; height: 80px;">
                        </div>
                    </div>

                    <!-- Availability Status -->
                    <div class="mb-4">
                        <label for="itemAvailability" class="form-label pos-form-label">
                            Availability Status <span class="text-danger">*</span>
                        </label>
                        <select name="is_available" id="itemAvailability" class="form-select pos-form-control py-2" required>
                            <option value="1" <?php if (($item["is_available"] ?? 1) == 1) echo "selected"; ?>>
                                Available (Active &amp; ready for order taking)
                            </option>
                            <option value="0" <?php if (($item["is_available"] ?? 1) == 0) echo "selected"; ?>>
                                Unavailable (Temporarily sold out or out of stock)
                            </option>
                        </select>
                        <div class="form-text text-muted small">Unavailable items cannot be selected for new customer orders.</div>
                    </div>

                    <!-- Actions -->
                    <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                        <a href="delete.php?id=<?php echo $id; ?>" 
                           class="btn btn-outline-danger px-3 d-inline-flex align-items-center gap-1"
                           onclick="return confirm('Are you sure you want to delete menu item <?php echo htmlspecialchars(addslashes($item['name']), ENT_QUOTES, 'UTF-8'); ?>? Note: Items with existing orders or recipes cannot be deleted.');">
                            <i class="bi bi-trash"></i>
                            <span>Delete</span>
                        </a>

                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 d-inline-flex align-items-center gap-1 shadow-sm">
                                <i class="bi bi-check-lg"></i>
                                <span>Update Menu Item</span>
                            </button>
                        </div>
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