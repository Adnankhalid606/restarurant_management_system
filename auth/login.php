<?php

session_start();

require_once "../config/database.php";

$error = "";
if (isset($_GET["error"]) && $_GET["error"] === "deactivated") {
    $error = "Your account has been deactivated. Please contact your system administrator.";
}

if (isset($_SESSION["user_id"])) {
    header("Location: ../index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $login_input = trim($_POST["login_input"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($login_input === "" || $password === "") {
        $error = "Please provide both username/email and password.";
    } else {
        $sql = "SELECT id, name, username, email, password, role, is_active
                FROM users
                WHERE email = ? OR username = ?
                LIMIT 1";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $login_input, $login_input);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if (!$user) {

            $error = "Invalid username/email or password.";

        } elseif (!$user["is_active"]) {

            $error = "Your account is inactive.";

        } elseif ($password !== $user["password"]) {

            $error = "Invalid username/email or password.";

        } else {

            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["id"];
            $_SESSION["user_name"] = $user["name"];
            $_SESSION["username"] = $user["username"];
            $_SESSION["user_email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            header("Location: ../index.php");
            exit;
        }
    }
};

$page_title = "Sign In";
$no_shell = true;
require_once "../includes/header.php";
?>

<div class="login-wrapper">
    <div class="login-card">
        <div class="text-center mb-4">
            <div class="login-brand-icon">
                <i class="bi bi-shop"></i>
            </div>
            <h4 class="fw-bold mb-1" style="color: var(--pos-brand);">RestoBar POS</h4>
            <p class="text-muted small mb-0">Restaurant Management &bull; Terminal Access</p>
        </div>

        <?php if ($error !== "") { ?>
            <div class="alert alert-danger d-flex align-items-center py-2 px-3 mb-3 border-danger-subtle" role="alert">
                <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                <div class="small fw-medium"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        <?php } ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label for="login_input" class="form-label pos-form-label">Username or Email</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0">
                        <i class="bi bi-person-badge"></i>
                    </span>
                    <input
                        type="text"
                        id="login_input"
                        name="login_input"
                        class="form-control pos-form-control border-start-0 ps-0"
                        placeholder="username or staff@restobar.com"
                        value="<?php echo isset($_POST['login_input']) ? htmlspecialchars($_POST['login_input'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label pos-form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white text-muted border-end-0">
                        <i class="bi bi-lock"></i>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control pos-form-control border-start-0 ps-0"
                        placeholder="••••••••"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Sign In to Terminal</span>
            </button>
        </form>

        <div class="mt-4 pt-3 border-top text-center">
            <span class="text-muted small">
                <i class="bi bi-shield-check me-1 text-secondary"></i> Authorized Staff Access Only
            </span>
        </div>
    </div>
</div>

<?php
require_once "../includes/footer.php";
?>