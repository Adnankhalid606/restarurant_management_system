<?php
/**
 * Shared Application Footer Component
 * Closes the layout shell, renders footer, and injects vendor/app scripts.
 */

if (!isset($base_path)) {
    $base_path = file_exists('./config/database.php') ? './' : '../';
}

$no_shell = $no_shell ?? false;
?>

<?php if (!$no_shell) { ?>
            </main>

            <footer class="app-footer">
                <div>
                    <span>&copy; <?php echo date("Y"); ?> <strong>RestoBar POS &amp; Management System</strong></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-light text-secondary border">Local Server</span>
                    <span class="text-muted d-none d-sm-inline">v1.0.0</span>
                </div>
            </footer>
        </div>
    </div>
<?php } ?>

<!-- Local Bootstrap 5.3 Bundle JS -->
<script src="<?php echo $base_path; ?>assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Mobile Navigation Toggle Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (toggle && sidebar && backdrop) {
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });

        backdrop.addEventListener('click', function() {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });

        // Close sidebar on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
                backdrop.classList.remove('show');
            }
        });
    }
});
</script>

</body>
</html>
