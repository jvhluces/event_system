<?php
$adminSidebarPage = basename($_SERVER['PHP_SELF'] ?? '');
$adminSidebarId = $adminSidebarPage === 'event_audience.php' ? 'sidebar' : 'adminSidebar';
$adminSidebarActive = static function (string $page) use ($adminSidebarPage): string {
    return $adminSidebarPage === $page ? ' active' : '';
};
require_once __DIR__ . '/../organizer/notification_center.php';
if (!isset($notificationCenter)) {
    $notificationCenter = organizer_notification_center($conn, (int) ($_SESSION['user_id'] ?? 0));
}
?>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="admin-notification-topbar">
    <?php require __DIR__ . '/../organizer/notification_menu.php'; ?>
</div>
<aside class="admin-sidebar sidebar" id="<?= htmlspecialchars($adminSidebarId, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="sidebar-close" id="sidebarClose" aria-label="Close navigation">
        <i class="bi bi-x-lg"></i>
    </button>

    <div class="sidebar-brand">
        <a href="admin.php" class="sidebar-brand-title" aria-label="Admin dashboard">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>
    </div>

    <div class="admin-profile">
        <div class="admin-avatar" aria-hidden="true">
            <i class="bi bi-shield-lock-fill"></i>
        </div>
        <div class="admin-profile-info">
            <div class="admin-name">Administrator</div>
            <div class="admin-role">Admin Panel</div>
        </div>
    </div>

    <nav class="sidebar-menu" aria-label="Admin navigation">
        <div class="sidebar-label">Main</div>
        <a href="admin.php" class="sidebar-link<?= $adminSidebarActive('admin.php'); ?>">
            <i class="bi bi-grid-1x2-fill"></i><span>Dashboard</span>
        </a>
        <a href="event_audience.php" class="sidebar-link<?= $adminSidebarActive('event_audience.php'); ?>">
            <i class="bi bi-people-fill"></i><span>Event Audience</span>
        </a>
        <a href="event.php" class="sidebar-link<?= $adminSidebarActive('event.php'); ?>">
            <i class="bi bi-calendar-event-fill"></i><span>Events</span>
        </a>
        <a href="admin_events.php" class="sidebar-link<?= $adminSidebarActive('admin_events.php'); ?>">
            <i class="bi bi-clipboard2-check-fill"></i><span>Event Approval</span>
        </a>
        <a href="venues.php" class="sidebar-link<?= $adminSidebarActive('venues.php'); ?>">
            <i class="bi bi-geo-alt-fill"></i><span>Venues</span>
        </a>
        <a href="reports.php" class="sidebar-link<?= $adminSidebarActive('reports.php'); ?>">
            <i class="bi bi-bar-chart-fill"></i><span>Reports</span>
        </a>

        <div class="sidebar-divider"></div>
        <div class="sidebar-label">System</div>
        <a href="settings.php" class="sidebar-link<?= $adminSidebarActive('settings.php'); ?>">
            <i class="bi bi-gear-fill"></i><span>System Settings</span>
        </a>
        <div class="sidebar-divider"></div>
        <a href="../auth/logout.php" class="sidebar-link logout-link"
           onclick="return confirm('Are you sure you want to logout?');">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </nav>
    <div class="admin-sidebar-footer">Better Events.<br>Stronger Communities.</div>
</aside>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('<?= htmlspecialchars($adminSidebarId, ENT_QUOTES, 'UTF-8'); ?>');
    const overlay = document.getElementById('sidebarOverlay');
    const close = document.getElementById('sidebarClose');
    const toggle = document.getElementById('sidebarToggle');

    if (!sidebar || !overlay) {
        return;
    }

    function closeSidebarMenu() {
        sidebar.classList.remove('open');
        overlay.classList.remove('show', 'open');
        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
        document.body.style.overflow = '';
    }

    overlay.addEventListener('click', closeSidebarMenu);
    close?.addEventListener('click', closeSidebarMenu);
    sidebar.querySelectorAll('.sidebar-link').forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth <= 991) {
                closeSidebarMenu();
            }
        });
    });
});
</script>
<?php if (in_array($adminSidebarPage, ['settings.php', 'venues.php', 'admin_events.php'], true)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('adminSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const toggle = document.getElementById('sidebarToggle');

    if (!sidebar || !overlay || !toggle) {
        return;
    }

    function setSidebarOpen(open) {
        sidebar.classList.toggle('open', open);
        overlay.classList.toggle('show', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.style.overflow = open ? 'hidden' : '';
    }

    toggle.addEventListener('click', function () {
        setSidebarOpen(!sidebar.classList.contains('open'));
    });
    window.addEventListener('resize', function () {
        if (window.innerWidth > 991) {
            setSidebarOpen(false);
        }
    });
});
</script>
<?php endif; ?>
<script src="../assets/js/organizer/notifications.js"></script>
