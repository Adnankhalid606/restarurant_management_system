<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = $_POST["password"];
    $role = $_POST["role"];


    // Check if email already exists
    $sql = "SELECT id
            FROM users
            WHERE email = ?";

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $email);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if (mysqli_num_rows($result) > 0) {

        $error = "Email already exists.";
    } else {


        // Create user
        $sql = "INSERT INTO users (name, email, password, role)
                VALUES (?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "ssss",
            $name,
            $email,
            $password,
            $role
        );

        mysqli_stmt_execute($stmt);

        header("Location: index.php");
        exit;
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
                    </div>

                    <div class="row g-3 mb-4">
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