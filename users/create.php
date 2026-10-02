<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = preg_replace('/\s+/', ' ', trim($_POST["name"] ?? ""));
    $username = strtolower(trim($_POST["username"] ?? ""));
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";
    $role = trim($_POST["role"] ?? "waiter");
    $valid_roles = ["admin", "waiter", "kitchen"];

    if ($name === "" || $username === "" || $email === "" || $password === "") {
        $error = "All fields marked with an asterisk are required.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = "Full Name must be between 2 and 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[a-z0-9_.-]{3,30}$/', $username)) {
        $error = "Username must be 3–30 characters and contain only lowercase letters, numbers, underscores, dots, or hyphens.";
    } elseif (!in_array($role, $valid_roles, true)) {
        $error = "Invalid system role selected.";
    } else {
        // Check if email or username already exists
        $sql = "SELECT email, username
                FROM users
                WHERE email = ? OR username = ?";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $email, $username);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($existing = mysqli_fetch_assoc($result)) {
            if (strcasecmp($existing["username"], $username) === 0) {
                $error = "Username is already taken. Please choose a different one.";
            } else {
                $error = "Email is already registered. Please use another email address.";
            }
        } else {
            try {
                // Create user
                $sql = "INSERT INTO users (name, username, email, password, role)
                        VALUES (?, ?, ?, ?, ?)";

                $stmt = mysqli_prepare($conn, $sql);

                mysqli_stmt_bind_param(
                    $stmt,
                    "sssss",
                    $name,
                    $username,
                    $email,
                    $password,
                    $role
                );

                mysqli_stmt_execute($stmt);

                $_SESSION["flash_success"] = "Staff account for '" . $name . "' (@" . $username . ") was created successfully.";
                header("Location: index.php");
                exit;
            } catch (mysqli_sql_exception $e) {
                $error = "Database rejected account creation: duplicate username/email or invalid role data.";
            }
        }
    }
}

$page_title = "Create Staff Account";
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
            <span class="text-dark small fw-semibold">New Account</span>
        </div>
        <h2 class="page-header-title">Create Staff Account</h2>
        <p class="page-header-subtitle">Register a new user profile with defined system role and authorization</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-arrow-left"></i>
            <span>Back to Users</span>
        </a>
    </div>
</div>

<!-- User Create Form Container -->
<div class="row justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="pos-card shadow-sm">
            <div class="pos-card-header d-flex justify-content-between align-items-center">
                <span class="pos-card-title">
                    <i class="bi bi-person-plus text-primary me-2"></i>Account Details
                </span>
                <span class="badge bg-light text-dark border">
                    Staff Registration
                </span>
            </div>
            <div class="pos-card-body p-4">
                <?php if ($error !== "") { ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 mb-4" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php } ?>

                <form method="POST" action="create.php">
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
                                    placeholder="e.g. Ali Khan"
                                    value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
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
                                    placeholder="e.g. ali_waiter"
                                    value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
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
                                    placeholder="e.g. staff@restobar.com"
                                    value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                    required>
                            </div>
                            <div class="form-text">Unique email used for system sign-in.</div>
                        </div>

                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                Password <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-key"></i>
                                </span>
                                <input type="password"
                                    name="password"
                                    class="form-control"
                                    placeholder="Enter user password"
                                    required>
                            </div>
                            <div class="form-text">Account authentication credential.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold text-dark">
                                System Role <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted">
                                    <i class="bi bi-shield-check"></i>
                                </span>
                                <select name="role" class="form-select" required>
                                    <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>
                                        Admin (Full Access &amp; Configuration)
                                    </option>
                                    <option value="waiter" <?php echo (!isset($_POST['role']) || $_POST['role'] === 'waiter') ? 'selected' : ''; ?>>
                                        Waiter (POS Orders, Tables &amp; Guests)
                                    </option>
                                    <option value="kitchen" <?php echo (isset($_POST['role']) && $_POST['role'] === 'kitchen') ? 'selected' : ''; ?>>
                                        Kitchen (Kitchen Display &amp; Inventory)
                                    </option>
                                </select>
                            </div>
                            <div class="form-text">Determines system modules and capabilities.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end align-items-center gap-2 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary px-3">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-4 shadow-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-person-plus"></i>
                            <span>Create User Account</span>
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