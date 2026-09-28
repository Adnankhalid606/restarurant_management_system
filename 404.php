<?php
/**
 * Custom 404 Page Not Found
 * Gracefully handles missing routes and invalid resources across the application.
 */

http_response_code(404);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
$base_path = '/restaurant_pos/';
$page_title = '404 - Page Not Found';
$no_shell = !$is_logged_in;

require_once __DIR__ . '/includes/header.php';

$requested_path = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8');
?>

<?php if ($no_shell) { ?>
<div class="min-vh-100 d-flex align-items-center justify-content-center p-3 bg-light">
    <div class="pos-card text-center p-4 p-md-5 shadow-sm" style="max-width: 540px; width: 100%;">
        <div class="mb-4">
            <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary border border-primary-subtle" style="width: 80px; height: 80px;">
                <i class="bi bi-compass fs-1"></i>
            </div>
        </div>

        <h1 class="display-5 fw-bold text-dark mb-2">404</h1>
        <h4 class="fw-semibold text-secondary mb-3">Page Not Found</h4>

        <p class="text-muted small mb-4">
            The page or resource you requested could not be located. It may have been moved, renamed, or is temporarily unavailable.
        </p>

        <?php if (!empty($requested_path)) { ?>
            <div class="p-2 mb-4 bg-light rounded border text-muted small font-monospace text-truncate" title="<?php echo $requested_path; ?>">
                <span class="text-secondary fw-semibold">Requested:</span> <?php echo $requested_path; ?>
            </div>
        <?php } ?>

        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?php echo $base_path; ?>auth/login.php" class="btn btn-primary d-inline-flex align-items-center gap-2 px-3 py-2">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Go to Login</span>
            </a>
            <button type="button" onclick="history.back()" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2">
                <i class="bi bi-arrow-left"></i>
                <span>Go Back</span>
            </button>
        </div>
    </div>
</div>
<?php } else { ?>
<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="pos-card text-center p-4 p-md-5 my-4 shadow-sm">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary-subtle text-primary border border-primary-subtle" style="width: 84px; height: 84px;">
                        <i class="bi bi-compass fs-1"></i>
                    </div>
                </div>

                <h1 class="display-4 fw-bold text-dark mb-1">404</h1>
                <h3 class="fw-semibold text-secondary mb-3">Page Not Found</h3>

                <p class="text-muted mb-4">
                    The page or resource you are looking for does not exist or has been relocated within the restaurant management system.
                </p>

                <?php if (!empty($requested_path)) { ?>
                    <div class="p-2 mb-4 bg-light rounded border text-muted small font-monospace text-truncate" title="<?php echo $requested_path; ?>">
                        <span class="text-secondary fw-semibold">URL:</span> <?php echo $requested_path; ?>
                    </div>
                <?php } ?>

                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    <a href="<?php echo $base_path; ?>index.php" class="btn btn-primary d-inline-flex align-items-center gap-2 px-4 py-2">
                        <i class="bi bi-house-door"></i>
                        <span>Back to Dashboard</span>
                    </a>
                    <button type="button" onclick="history.back()" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2 px-3 py-2">
                        <i class="bi bi-arrow-left"></i>
                        <span>Go Back</span>
                    </button>
                </div>

                <hr class="my-4 border-secondary-subtle">

                <div class="text-start">
                    <span class="text-uppercase text-muted fw-bold small d-block mb-3" style="font-size: 0.75rem; letter-spacing: 0.05em;">
                        Quick Operational Links
                    </span>
                    <div class="row g-2">
                        <div class="col-6 col-sm-4">
                            <a href="<?php echo $base_path; ?>orders/index.php" class="btn btn-light border text-dark w-100 text-start small d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-receipt text-primary"></i>
                                <span>Orders</span>
                            </a>
                        </div>
                        <div class="col-6 col-sm-4">
                            <a href="<?php echo $base_path; ?>bills/index.php" class="btn btn-light border text-dark w-100 text-start small d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-credit-card text-success"></i>
                                <span>Billing</span>
                            </a>
                        </div>
                        <div class="col-6 col-sm-4">
                            <a href="<?php echo $base_path; ?>kitchen/index.php" class="btn btn-light border text-dark w-100 text-start small d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-fire text-warning"></i>
                                <span>Kitchen</span>
                            </a>
                        </div>
                        <div class="col-6 col-sm-4">
                            <a href="<?php echo $base_path; ?>reservations/index.php" class="btn btn-light border text-dark w-100 text-start small d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-calendar3 text-info"></i>
                                <span>Reservations</span>
                            </a>
                        </div>
                        <div class="col-6 col-sm-4">
                            <a href="<?php echo $base_path; ?>inventory/materials.php" class="btn btn-light border text-dark w-100 text-start small d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-boxes text-secondary"></i>
                                <span>Inventory</span>
                            </a>
                        </div>
                        <div class="col-6 col-sm-4">
                            <a href="<?php echo $base_path; ?>reports/sales.php" class="btn btn-light border text-dark w-100 text-start small d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-graph-up text-danger"></i>
                                <span>Reports</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php } ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
