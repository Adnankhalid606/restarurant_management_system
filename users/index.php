<?php

require_once "../config/database.php";
require_once "../includes/role.php";

requireRole(["admin"]);

$sql = "SELECT id, name, email, role, is_active, created_at
        FROM users
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$users = [];
$total_users = 0;
$active_users = 0;
$inactive_users = 0;
$admin_count = 0;
$waiter_count = 0;
$kitchen_count = 0;

while ($u = mysqli_fetch_assoc($result)) {
    $users[] = $u;
    $total_users++;
    if ($u["is_active"] == 1) {
        $active_users++;
    } else {
        $inactive_users++;
    }
    if ($u["role"] === "admin") {
        $admin_count++;
    } elseif ($u["role"] === "waiter") {
        $waiter_count++;
    } elseif ($u["role"] === "kitchen") {
        $kitchen_count++;
    }
}

$current_user_id = $_SESSION["user_id"] ?? 0;

$page_title = "Staff Accounts & Administration";
$active_menu = "users";

require_once "../includes/header.php";

?>

<!-- Page Header Bar -->
<div class="page-header-bar">
    <div>
        <h2 class="page-header-title">Staff Accounts &amp; Administration</h2>
        <p class="page-header-subtitle">User authentication, role authorization, and account security management</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="create.php" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-person-plus"></i>
            <span>Create User</span>
        </a>
        <a href="../index.php" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-1 shadow-sm">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
    </div>
</div>

<!-- Administration Metrics Summary -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Registered Accounts</span>
                <span class="fs-4 fw-bold text-dark font-monospace"><?php echo $total_users; ?></span>
            </div>
            <div class="badge-subtle badge-status-completed p-2 rounded">
                <i class="bi bi-people fs-4 text-primary"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-paid">
            <div>
                <span class="text-muted small d-block">Active Accounts</span>
                <span class="fs-4 fw-bold text-success font-monospace"><?php echo $active_users; ?></span>
            </div>
            <div class="badge-subtle badge-status-ready p-2 rounded">
                <i class="bi bi-check2-circle fs-4 text-success"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-outstanding">
            <div>
                <span class="text-muted small d-block">Inactive / Suspended</span>
                <span class="fs-4 fw-bold <?php echo $inactive_users > 0 ? 'text-danger' : 'text-secondary'; ?> font-monospace">
                    <?php echo $inactive_users; ?>
                </span>
            </div>
            <div class="badge-subtle <?php echo $inactive_users > 0 ? 'badge-status-cancelled' : 'badge-status-preparing'; ?> p-2 rounded">
                <i class="bi bi-slash-circle fs-4 <?php echo $inactive_users > 0 ? 'text-danger' : 'text-secondary'; ?>"></i>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="pos-card p-3 d-flex align-items-center justify-content-between ledger-summary-kpi kpi-billed">
            <div>
                <span class="text-muted small d-block">Role Distribution</span>
                <span class="small fw-semibold text-dark d-block">
                    <?php echo $admin_count; ?> Admin &bull; <?php echo $waiter_count; ?> Waiter &bull; <?php echo $kitchen_count; ?> Kit
                </span>
            </div>
            <div class="badge-subtle badge-status-preparing p-2 rounded">
                <i class="bi bi-shield-check fs-4 text-primary"></i>
            </div>
        </div>
    </div>
</div>

<!-- Search & Users Register Card -->
<div class="pos-card">
    <div class="pos-card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="d-flex align-items-center gap-2">
            <span class="pos-card-title">
                <i class="bi bi-person-badge text-primary me-2"></i>Staff Account Registry
            </span>
            <span class="badge bg-light text-dark border font-monospace" id="usersCountBadge">
                <?php echo $total_users; ?> Total
            </span>
        </div>
        <div class="d-flex align-items-center gap-2" style="min-width: 260px;">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0 text-muted">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" 
                       id="userSearchInput" 
                       class="form-control border-start-0" 
                       placeholder="Filter name, email, role..." 
                       aria-label="Search users">
            </div>
        </div>
    </div>

    <div class="pos-card-body p-0">
        <?php if ($total_users === 0) { ?>
            <div class="text-center py-5">
                <div class="mb-3 text-muted">
                    <i class="bi bi-person-x fs-1"></i>
                </div>
                <h5 class="fw-semibold text-dark">No User Accounts Found</h5>
                <p class="text-muted small mb-3">There are no user accounts registered in the database.</p>
                <a href="create.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-person-plus me-1"></i>Create First User
                </a>
            </div>
        <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="usersTable">
                    <thead>
                        <tr>
                            <th class="ps-3" style="width: 100px;">User #</th>
                            <th>Staff Member</th>
                            <th>Email Identifier</th>
                            <th>System Role</th>
                            <th>Account Status</th>
                            <th>Registered Date</th>
                            <th class="text-end pe-3" style="width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody">
                        <?php foreach ($users as $user) { 
                            $is_current_user = ($user["id"] == $current_user_id);
                            $role_clean = strtolower(trim($user["role"] ?? "waiter"));
                            $is_active = (int) ($user["is_active"] ?? 0);
                        ?>
                            <tr class="user-row <?php echo $is_current_user ? 'table-light' : ''; ?>"
                                data-name="<?php echo strtolower(htmlspecialchars($user["name"])); ?>"
                                data-email="<?php echo strtolower(htmlspecialchars($user["email"])); ?>"
                                data-role="<?php echo $role_clean; ?>"
                                data-status="<?php echo $is_active ? 'active' : 'inactive'; ?>"
                                data-id="<?php echo $user["id"]; ?>">
                                <td class="ps-3">
                                    <span class="badge bg-light text-dark border font-monospace">
                                        #USR-<?php echo str_pad($user["id"], 3, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <?php if ($role_clean === "admin") { ?>
                                            <div class="user-avatar user-avatar-admin">
                                                <i class="bi bi-shield-lock"></i>
                                            </div>
                                        <?php } elseif ($role_clean === "kitchen") { ?>
                                            <div class="user-avatar user-avatar-kitchen">
                                                <i class="bi bi-fire"></i>
                                            </div>
                                        <?php } else { ?>
                                            <div class="user-avatar user-avatar-waiter">
                                                <i class="bi bi-person"></i>
                                            </div>
                                        <?php } ?>
                                        <div>
                                            <span class="fw-semibold text-dark d-block">
                                                <?php echo htmlspecialchars($user["name"]); ?>
                                                <?php if ($is_current_user) { ?>
                                                    <span class="badge bg-primary text-white ms-1" style="font-size: 0.65rem;">You</span>
                                                <?php } ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="text-dark small d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-envelope text-muted"></i>
                                        <?php echo htmlspecialchars($user["email"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($role_clean === "admin") { ?>
                                        <span class="badge bg-dark text-white text-capitalize">
                                            <i class="bi bi-shield-lock me-1"></i>Admin
                                        </span>
                                    <?php } elseif ($role_clean === "kitchen") { ?>
                                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle text-capitalize">
                                            <i class="bi bi-fire me-1"></i>Kitchen
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle text-capitalize">
                                            <i class="bi bi-person-badge me-1"></i>Waiter
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if ($is_active == 1) { ?>
                                        <span class="badge-subtle badge-status-ready d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-check-circle-fill"></i>
                                            <span>Active</span>
                                        </span>
                                    <?php } else { ?>
                                        <span class="badge-subtle badge-status-cancelled d-inline-flex align-items-center gap-1">
                                            <i class="bi bi-dash-circle-fill"></i>
                                            <span>Inactive</span>
                                        </span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <span class="text-dark small">
                                        <?php echo date('M d, Y', strtotime($user["created_at"])); ?>
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <a href="edit.php?id=<?php echo $user["id"]; ?>" 
                                           class="btn btn-outline-secondary btn-sm" 
                                           title="Edit User Account">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($is_current_user) { ?>
                                            <button type="button" 
                                                    class="btn btn-outline-secondary btn-sm opacity-50" 
                                                    disabled 
                                                    title="You cannot delete your own account">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php } else { ?>
                                            <a href="delete.php?id=<?php echo $user["id"]; ?>" 
                                               class="btn btn-outline-danger btn-sm" 
                                               onclick="return confirm('Are you sure you want to delete this user account?');" 
                                               title="Delete User Account">
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                        <tr id="noUserMatchesRow" style="display: none;">
                            <td colspan="7" class="text-center py-4 text-muted">
                                <i class="bi bi-search me-1"></i> No staff accounts match your search filter.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php } ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearchInput');
    const tableBody = document.getElementById('usersTableBody');
    const noMatchesRow = document.getElementById('noUserMatchesRow');
    const countBadge = document.getElementById('usersCountBadge');

    if (searchInput && tableBody) {
        const rows = tableBody.querySelectorAll('.user-row');
        const total = rows.length;

        searchInput.addEventListener('input', function() {
            const query = this.value.trim().toLowerCase();
            let visibleCount = 0;

            rows.forEach(function(row) {
                const name = row.getAttribute('data-name') || '';
                const email = row.getAttribute('data-email') || '';
                const role = row.getAttribute('data-role') || '';
                const status = row.getAttribute('data-status') || '';
                const id = row.getAttribute('data-id') || '';

                if (query === '' || name.includes(query) || email.includes(query) || role.includes(query) || status.includes(query) || id.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (noMatchesRow) {
                noMatchesRow.style.display = (visibleCount === 0 && query !== '') ? '' : 'none';
            }

            if (countBadge) {
                countBadge.textContent = query !== '' ? `${visibleCount} of ${total}` : `${total} Total`;
            }
        });
    }
});
</script>

<?php

require_once "../includes/footer.php";

?>