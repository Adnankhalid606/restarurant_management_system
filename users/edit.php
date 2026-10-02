<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$id = $_GET["id"];

$error = "";


// Get user
$sql = "SELECT id, name, username, email, role, is_active
        FROM users
        WHERE id = ?";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    die("User not found.");
}


// Update user
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = preg_replace('/\s+/', ' ', trim($_POST["name"] ?? ""));
    $username = strtolower(trim($_POST["username"] ?? ""));
    $email = strtolower(trim($_POST["email"] ?? ""));
    $role = trim($_POST["role"] ?? "waiter");
    $is_active = (isset($_POST["is_active"]) && (int)$_POST["is_active"] === 0) ? 0 : 1;
    $password = $_POST["password"] ?? "";
    $valid_roles = ["admin", "waiter", "kitchen"];

    if ($name === "" || $username === "" || $email === "") {
        $error = "Name, username, and email fields are required.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = "Full Name must be between 2 and 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[a-z0-9_.-]{3,30}$/', $username)) {
        $error = "Username must be 3–30 characters and contain only lowercase letters, numbers, underscores, dots, or hyphens.";
    } elseif (!in_array($role, $valid_roles, true)) {
        $error = "Invalid system role selected.";
    } else {
        // Check if another user already uses this email or username
        $sql = "SELECT id, email, username
                FROM users
                WHERE (email = ? OR username = ?)
                AND id != ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $email, $username, $id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($existing = mysqli_fetch_assoc($result)) {
            if (strcasecmp($existing["username"], $username) === 0) {
                $error = "Username is already taken by another account.";
            } else {
                $error = "Email address is already registered to another account.";
            }
        } else {
            try {
                // Update password only if a new password was entered
                if ($password !== "") {

                    $sql = "UPDATE users
                            SET name = ?,
                                username = ?,
                                email = ?,
                                password = ?,
                                role = ?,
                                is_active = ?
                            WHERE id = ?";

                    $stmt = mysqli_prepare($conn, $sql);

                    mysqli_stmt_bind_param(
                        $stmt,
                        "sssssii",
                        $name,
                        $username,
                        $email,
                        $password,
                        $role,
                        $is_active,
                        $id
                    );

                } else {

                    $sql = "UPDATE users
                            SET name = ?,
                                username = ?,
                                email = ?,
                                role = ?,
                                is_active = ?
                            WHERE id = ?";

                    $stmt = mysqli_prepare($conn, $sql);

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssssii",
                        $name,
                        $username,
                        $email,
                        $role,
                        $is_active,
                        $id
                    );
                }

                mysqli_stmt_execute($stmt);

                // If updating currently logged in user, refresh active session
                if (isset($_SESSION["user_id"]) && (int)$_SESSION["user_id"] === (int)$id) {
                    $_SESSION["user_name"] = $name;
                    $_SESSION["username"] = $username;
                    $_SESSION["user_email"] = $email;
                    $_SESSION["role"] = $role;
                }

                $_SESSION["flash_success"] = "Account for '" . $name . "' (@" . $username . ") was updated successfully.";
                header("Location: index.php");
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = "Database rejected account update: duplicate credential or invalid role data.";
            }
        }
    }
}

$is_current_user = isset($_SESSION["user_id"]) && $_SESSION["user_id"] == $id;

$page_title = "Edit Staff Account — " . htmlspecialchars($user["name"]);
$active_menu = "users";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="index.php" class="text-muted small text-decoration-none">
                <i class="bi bi-people me-1"></i>Staff Accounts
            </a>
            <span class="text-muted small">/</span>
            <span class="text-dark small fw-semibold">Edit #USR-<?php echo str_pad($user["id"], 3, '0', STR_PAD_LEFT); ?></span>
        </div>
        <h2 class="page-header-title">
            Edit Staff Account
            <span class="badge bg-light text-dark border font-monospace fs-6 fw-normal ms-2">
                #USR-<?php echo str_pad($user["id"], 3, '0', STR_PAD_LEFT); ?>
            </span>
            <?php if ($is_current_user) { ?>
                <span class="badge bg-primary text-white fs-6 fw-normal ms-1">You</span>
            <?php } ?>
        </h2>
        <p class="page-header-subtitle">Update account identity, authorization permissions, password, or active status</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Users</span>
        </a>
    </div>
</div>

<!-- User Edit Form Container -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-pencil-square text-primary me-2"></i>Modify Account Credentials
                </span>
                <span class="badge bg-light text-dark border font-monospace">
                    Account #<?php echo $user["id"]; ?>
                </span>
            </div>
            <div class="pos-card-body p-4">
                <?php if ($error !== "") { ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php } ?>

                <form method="POST" action="edit.php?id=<?php echo $user["id"]; ?>">
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-person"></i>
                                </span>
                                <input type="text" 
                                       name="name" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user["name"]); ?>" 
                                       required>
                            </div>
                            <div class="form-text">Staff member's legal or display name.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Username <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-at"></i>
                                </span>
                                <input type="text" 
                                       name="username" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user["username"] ?? ""); ?>" 
                                       required>
                            </div>
                            <div class="form-text">Unique username for login terminal access.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Email Identifier <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-envelope"></i>
                                </span>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?php echo htmlspecialchars($user["email"]); ?>" 
                                       required>
                            </div>
                            <div class="form-text">System login email identifier.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                System Role <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-shield-check"></i>
                                </span>
                                <select name="role" class="form-select" required>
                                    <option value="admin" <?php echo ($user["role"] === "admin") ? "selected" : ""; ?>>
                                        Admin (Full Access &amp; Configuration)
                                    </option>
                                    <option value="waiter" <?php echo ($user["role"] === "waiter") ? "selected" : ""; ?>>
                                        Waiter (POS Orders, Tables &amp; Guests)
                                    </option>
                                    <option value="kitchen" <?php echo ($user["role"] === "kitchen") ? "selected" : ""; ?>>
                                        Kitchen (Kitchen Display &amp; Inventory)
                                    </option>
                                </select>
                            </div>
                            <div class="form-text">Determines system modules and capabilities.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Account Status <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-toggle-on"></i>
                                </span>
                                <select name="is_active" class="form-select" required>
                                    <option value="1" <?php echo ($user["is_active"] == 1) ? "selected" : ""; ?>>
                                        Active (Allowed to Sign In)
                                    </option>
                                    <option value="0" <?php echo ($user["is_active"] == 0) ? "selected" : ""; ?>>
                                        Inactive (Suspended / Disabled)
                                    </option>
                                </select>
                            </div>
                            <div class="form-text">Inactive accounts cannot authenticate into the system.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                New Password <span class="text-muted fw-normal">(Optional)</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-key"></i>
                                </span>
                                <input type="password" 
                                       name="password" 
                                       class="form-control" 
                                       placeholder="Leave blank to retain current">
                            </div>
                            <div class="form-text">Only enter a password if updating credentials.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                        <?php if ($is_current_user) { ?>
                            <span class="text-muted small fst-italic">
                                <i class="bi bi-info-circle me-1"></i>You cannot delete your own active account.
                            </span>
                        <?php } else { ?>
                            <a href="delete.php?id=<?php echo $user["id"]; ?>" 
                               class="btn btn-outline-danger btn-sm d-inline-flex align-items-center gap-1"
                               onclick="return confirm('Are you sure you want to delete this user account?');">
                                <i class="bi bi-trash"></i>
                                <span>Delete Account</span>
                            </a>
                        <?php } ?>

                        <div class="d-flex align-items-center gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-3">
                                Cancel
                            </a>
                            <button type="submit" class="btn btn-primary px-4 shadow-sm d-inline-flex align-items-center gap-1">
                                <i class="bi bi-check2-circle"></i>
                                <span>Update User Account</span>
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