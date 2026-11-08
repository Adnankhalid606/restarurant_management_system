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

    // Accessible Theme Controller
    const themeBtn = document.getElementById('themeToggleBtn');
    function updateThemeToggleUI(theme) {
        if (!themeBtn) return;
        const isDark = theme === 'dark';
        themeBtn.setAttribute('aria-checked', isDark ? 'true' : 'false');
        themeBtn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        themeBtn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');
    }

    if (themeBtn) {
        const initialTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
        updateThemeToggleUI(initialTheme);

        themeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'light';
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-bs-theme', targetTheme);
            updateThemeToggleUI(targetTheme);
            
            try {
                localStorage.setItem('restobar_theme', targetTheme);
            } catch(err) {
                console.warn('localStorage is unavailable:', err);
            }
        });
    }
});
</script>

</body>
</html>
