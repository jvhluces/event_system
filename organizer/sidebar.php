<?php
$sidebarPage = basename($_SERVER['PHP_SELF'] ?? '');
$sidebarActive = match ($sidebarPage) {
    'org_dash.php' => 'home',
    'event_reviews.php' => 'reviews',
    'archived_events.php' => 'archive',
    'myprofile.php' => 'profile',
    'settingss.php' => 'settings',
    'help.php' => 'help',
    default => 'events',
};

$sidebarName = trim((string)($organizerName ?? $userName ?? $firstname ?? 'Organizer'));
if ($sidebarName === '') {
    $sidebarName = 'Organizer';
}

$sidebarImage = (string)($profileImage ?? $avatar ?? '');
$sidebarInitial = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($sidebarName, 0, 1))
    : strtoupper(substr($sidebarName, 0, 1));

$sidebarLink = static function (string $page) use ($sidebarActive): string {
    return $sidebarActive === $page ? ' class="active" aria-current="page"' : '';
};
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="org_dash.php" class="brand">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>
        <button type="button" class="close-sidebar" onclick="closeSidebar()" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <div class="sidebar-profile">
        <div class="sidebar-avatar">
            <?php if ($sidebarImage !== ''): ?>
                <img src="<?= htmlspecialchars($sidebarImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Profile">
            <?php else: ?>
                <span><?= htmlspecialchars($sidebarInitial, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
        <div class="sidebar-profile-info">
            <div class="sidebar-profile-name"><?= htmlspecialchars($sidebarName, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="sidebar-profile-role">Organizer</div>
        </div>
        <i class="bi bi-chevron-down profile-chevron"></i>
    </div>

    <nav class="sidebar-menu" aria-label="Organizer navigation">
        <div class="sidebar-label">Main Menu</div>
        <a href="org_dash.php#home"<?= $sidebarLink('home'); ?>>
            <i class="bi bi-house-door-fill"></i><span>Home</span>
        </a>
        <a href="all_events.php"<?= $sidebarLink('events'); ?>>
            <i class="bi bi-calendar3"></i><span>Explore Events</span>
        </a>
        <a href="event_reviews.php"<?= $sidebarLink('reviews'); ?>>
            <i class="bi bi-star"></i><span>Event Reviews</span>
        </a>
        <a href="archived_events.php"<?= $sidebarLink('archive'); ?>>
            <i class="bi bi-archive-fill"></i><span>Archived Events</span>
        </a>

        <div class="sidebar-divider"></div>
        <a href="myprofile.php"<?= $sidebarLink('profile'); ?>>
            <i class="bi bi-person"></i><span>My Profile</span>
        </a>
        <a href="settingss.php"<?= $sidebarLink('settings'); ?>>
            <i class="bi bi-gear"></i><span>Settings</span>
        </a>
        <a href="help.php"<?= $sidebarLink('help'); ?>>
            <i class="bi bi-question-circle"></i><span>Help &amp; Support</span>
        </a>
        <a href="../auth/logout_user.php" class="logout-link" onclick="return confirm('Are you sure you want to logout?');">
            <i class="bi bi-box-arrow-right"></i><span>Logout</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-line"></div>
        <p>Better Events.<br>Stronger Communities.</p>
    </div>
</aside>

<div class="sidebar-overlay" id="overlay" onclick="closeSidebar()"></div>
