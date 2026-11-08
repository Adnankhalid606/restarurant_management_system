<?php
require_once __DIR__ . '/helpers.php';
start_secure_session();

if (!isset($base_path)) {
    $base_path = file_exists('./config/database.php') ? './' : '../';
}

$page_title = $page_title ?? 'Restaurant POS';
$no_shell = $no_shell ?? false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8'); ?> - RestoBar POS</title>

    <!-- Local Bootstrap 5.3 CSS -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/vendor/bootstrap/css/bootstrap.min.css">
    
    <!-- Local Bootstrap Icons -->
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    
    <link rel="stylesheet" href="<?php echo $base_path; ?>assets/css/style.css">
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('restobar_theme');
                const theme = savedTheme ? savedTheme : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch(e) {}
        })();
    </script>
</head>
<body class="pos-body">

<?php if (!$no_shell) { ?>
    <div class="app-wrapper">
        <?php require_once __DIR__ . '/sidebar.php'; ?>

        <div class="app-main">
            <?php require_once __DIR__ . '/topbar.php'; ?>
            
            <main class="app-content">
<?php } ?>
