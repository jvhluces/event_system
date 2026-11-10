<?php
/*
 * Notification menu (bell + dropdown panel)
 * Kailangan: $notificationCenter = organizer_notification_center($conn, $userId);
 * Kasama na rito ang CSS at JS, kaya hindi na kailangan ng ibang link.
 */

if (!function_exists('notification_safe_link')) {
    require_once __DIR__ . '/notification_center.php';
}

$notifUnread = (int) ($notificationCenter['unread_count'] ?? 0);
$notifItems  = $notificationCenter['notifications'] ?? [];
$notifError  = (string) ($notificationCenter['error'] ?? '');
$notifToken  = htmlspecialchars((string) ($notificationCenter['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');

if (!defined('NOTIFICATION_ASSETS_LOADED')):
    define('NOTIFICATION_ASSETS_LOADED', true);
?>
<style>
/* ============ NOTIFICATION MENU ============ */
.notification-menu {
    position: relative;
    display: inline-flex;
    align-items: center;
    flex: 0 0 auto;
}

.notification-menu .notification-toggle {
    position: relative;
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 1px solid #e1e7f0;
    border-radius: 12px;
    background: #fff;
    color: #24446f;
    font-size: 19px;
    line-height: 1;
    cursor: pointer;
    transition: background .2s ease, color .2s ease, border-color .2s ease;
}

.notification-menu .notification-toggle:hover,
.notification-menu .notification-toggle[aria-expanded="true"] {
    background: #edf3fc;
    border-color: #cddaf0;
    color: #2457a6;
}

.notification-menu .notification-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    min-width: 20px;
    height: 20px;
    padding: 0 5px;
    display: grid;
    place-items: center;
    border: 2px solid #fff;
    border-radius: 999px;
    background: #dc2626;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 1;
}

/* ---------- Panel ---------- */
.notification-menu .notification-panel {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    z-index: 2000;
    width: 380px;
    max-width: calc(100vw - 24px);
    max-height: min(540px, calc(100vh - 96px));
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border: 1px solid #e3e9f2;
    border-radius: 16px;
    background: #fff;
    color: #203b60;
    box-shadow: 0 18px 48px rgba(22, 45, 78, .18);
    text-align: left;
}

.notification-menu .notification-panel[hidden] {
    display: none;
}

.notification-menu .notification-panel::before {
    content: "";
    position: absolute;
    top: -7px;
    right: 15px;
    width: 12px;
    height: 12px;
    border-top: 1px solid #e3e9f2;
    border-left: 1px solid #e3e9f2;
    background: #fff;
    transform: rotate(45deg);
}

.notification-menu .notification-panel-header {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 18px;
    border-bottom: 1px solid #edf1f7;
    background: #fff;
}

.notification-menu .notification-panel-header h2 {
    margin: 0;
    color: #152e52;
    font-size: 16px;
    font-weight: 700;
    line-height: 1.3;
}

.notification-menu .notification-panel-header form {
    margin: 0;
}

.notification-menu .notification-mark-all {
    padding: 6px 11px;
    border: 0;
    border-radius: 999px;
    background: #edf3fc;
    color: #2457a6;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    cursor: pointer;
    transition: background .2s ease;
}

.notification-menu .notification-mark-all:hover {
    background: #dbe7fa;
}

/* ---------- List ---------- */
.notification-menu .notification-list {
    flex: 1 1 auto;
    min-height: 0;
    margin: 0;
    padding: 8px;
    list-style: none;
    overflow-y: auto;
    overscroll-behavior: contain;
}

.notification-menu .notification-item {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: 6px;
    margin: 0 0 4px;
    padding: 0;
    border-radius: 12px;
    background: transparent;
    transition: background .2s ease;
}

.notification-menu .notification-item:last-child {
    margin-bottom: 0;
}

.notification-menu .notification-item:hover {
    background: #f6f9fe;
}

.notification-menu .notification-item.unread {
    background: #f0f6ff;
}

.notification-menu .notification-item.unread:hover {
    background: #e7f0fd;
}

.notification-menu .notification-open-form {
    flex: 1 1 auto;
    min-width: 0;
    margin: 0;
}

.notification-menu .notification-item-button {
    width: 100%;
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin: 0;
    padding: 12px 6px 12px 12px;
    border: 0;
    border-radius: 12px;
    background: transparent;
    color: inherit;
    font: inherit;
    text-align: left;
    text-decoration: none;
    cursor: pointer;
}

.notification-menu .notification-item-static {
    cursor: default;
}

.notification-menu .notification-item-icon {
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: #e2ebff;
    color: #2457a6;
    font-size: 16px;
}

.notification-menu .notification-item:not(.unread) .notification-item-icon {
    background: #eef1f5;
    color: #7184a0;
}

.notification-menu .notification-item-content {
    flex: 1 1 auto;
    min-width: 0;
}

.notification-menu .notification-item-content p {
    margin: 0 0 4px;
    color: #203b60;
    font-size: 13.5px;
    font-weight: 500;
    line-height: 1.45;
    white-space: normal;
    overflow-wrap: anywhere;
}

.notification-menu .notification-item:not(.unread) .notification-item-content p {
    color: #4b6283;
    font-weight: 400;
}

.notification-menu .notification-item-content time {
    display: block;
    color: #7184a0;
    font-size: 12px;
    line-height: 1.3;
}

.notification-menu .notification-item.unread .notification-item-content time {
    color: #2457a6;
    font-weight: 600;
}

.notification-menu .notification-mark-form {
    flex: 0 0 auto;
    margin: 0;
    padding: 12px 10px 0 0;
}

.notification-menu .notification-mark-read {
    width: 30px;
    height: 30px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 1px solid #cddaf0;
    border-radius: 50%;
    background: #fff;
    color: #2457a6;
    font-size: 16px;
    cursor: pointer;
    transition: background .2s ease, color .2s ease;
}

.notification-menu .notification-mark-read:hover {
    background: #2457a6;
    border-color: #2457a6;
    color: #fff;
}

/* ---------- Empty / Error ---------- */
.notification-menu .notification-empty {
    padding: 38px 24px 42px;
    text-align: center;
}

.notification-menu .notification-empty i {
    display: block;
    margin-bottom: 10px;
    color: #9aacc5;
    font-size: 34px;
}

.notification-menu .notification-message {
    margin: 0;
    padding: 0;
    color: #667b9b;
    font-size: 13.5px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.notification-menu .notification-error {
    margin: 12px;
    padding: 12px 14px;
    border: 1px solid #fecaca;
    border-radius: 10px;
    background: #fef2f2;
    color: #b91c1c;
}

/* ---------- Mobile ---------- */
@media (max-width: 575.98px) {
    .notification-menu .notification-panel {
        position: fixed;
        top: 70px;
        right: 12px;
        left: 12px;
        width: auto;
        max-width: none;
        max-height: calc(100vh - 86px);
    }

    .notification-menu .notification-panel::before {
        display: none;
    }
}
</style>
<script>
(function () {
    if (window.__notificationMenuReady) { return; }
    window.__notificationMenuReady = true;

    function parts() {
        return {
            menu:  document.getElementById('notificationMenu'),
            btn:   document.getElementById('notificationToggle'),
            panel: document.getElementById('notificationPanel')
        };
    }

    function setOpen(open) {
        var p = parts();
        if (!p.btn || !p.panel) { return; }
        p.panel.hidden = !open;
        p.btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    document.addEventListener('click', function (event) {
        var p = parts();
        if (!p.menu || !p.panel) { return; }

        if (event.target.closest('#notificationToggle')) {
            event.preventDefault();
            event.stopImmediatePropagation();
            setOpen(p.panel.hidden);
            return;
        }

        if (!p.panel.hidden && !p.menu.contains(event.target)) {
            setOpen(false);
        }
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { setOpen(false); }
    });
})();
</script>
<?php endif; ?>

<div class="notification-menu" id="notificationMenu">
    <button
        type="button"
        class="notification-toggle"
        id="notificationToggle"
        aria-label="Notifications<?= $notifUnread > 0 ? ', ' . $notifUnread . ' unread' : ''; ?>"
        aria-expanded="false"
        aria-controls="notificationPanel"
    >
        <i class="bi bi-bell" aria-hidden="true"></i>
        <?php if ($notifUnread > 0): ?>
            <span class="notification-badge">
                <?= $notifUnread > 99 ? '99+' : $notifUnread; ?>
            </span>
        <?php endif; ?>
    </button>

    <section
        class="notification-panel"
        id="notificationPanel"
        aria-labelledby="notificationHeading"
        hidden
    >
        <div class="notification-panel-header">
            <h2 id="notificationHeading">Notifications</h2>

            <?php if ($notifUnread > 0): ?>
                <form method="post">
                    <input type="hidden" name="notification_csrf_token" value="<?= $notifToken; ?>">
                    <input type="hidden" name="notification_action" value="mark_all_read">
                    <button type="submit" class="notification-mark-all">
                        Mark all as read
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($notifError !== ''): ?>
            <p class="notification-message notification-error" role="alert">
                <?= htmlspecialchars($notifError, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        <?php elseif ($notifItems === []): ?>
            <div class="notification-empty">
                <i class="bi bi-bell-slash" aria-hidden="true"></i>
                <p class="notification-message">Wala ka pang notification.</p>
            </div>
        <?php else: ?>
            <ul class="notification-list">
                <?php foreach ($notifItems as $notification): ?>
                    <?php
                    $notifId     = (int) ($notification['id'] ?? 0);
                    $notifRead   = (int) ($notification['is_read'] ?? 0) === 1;
                    $notifLink   = notification_safe_link($notification['link'] ?? '');
                    $notifText   = htmlspecialchars((string) ($notification['message'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $notifTime   = (string) ($notification['created_at'] ?? '');
                    $notifLabel  = htmlspecialchars(notification_time_label($notifTime), ENT_QUOTES, 'UTF-8');
                    $notifTitle  = $notifTime !== '' && strtotime($notifTime)
                        ? htmlspecialchars(date('M j, Y g:i A', strtotime($notifTime)), ENT_QUOTES, 'UTF-8')
                        : '';
                    $notifIso    = htmlspecialchars($notifTime, ENT_QUOTES, 'UTF-8');
                    ?>
                    <li class="notification-item<?= $notifRead ? '' : ' unread'; ?>">

                        <?php if (!$notifRead): ?>
                            <!-- Pindutin = mark as read + pumunta sa link (kung meron) -->
                            <form method="post" class="notification-open-form">
                                <input type="hidden" name="notification_csrf_token" value="<?= $notifToken; ?>">
                                <input type="hidden" name="notification_id" value="<?= $notifId; ?>">
                                <input type="hidden" name="notification_action" value="mark_read">
                                <button type="submit" class="notification-item-button">
                                    <span class="notification-item-icon"><i class="bi bi-bell-fill" aria-hidden="true"></i></span>
                                    <span class="notification-item-content">
                                        <p><?= $notifText; ?></p>
                                        <time datetime="<?= $notifIso; ?>" title="<?= $notifTitle; ?>"><?= $notifLabel; ?></time>
                                    </span>
                                </button>
                            </form>

                            <!-- Mark as read lang, hindi aalis sa page -->
                            <form method="post" class="notification-mark-form">
                                <input type="hidden" name="notification_csrf_token" value="<?= $notifToken; ?>">
                                <input type="hidden" name="notification_id" value="<?= $notifId; ?>">
                                <input type="hidden" name="notification_action" value="mark_read">
                                <input type="hidden" name="notification_stay" value="1">
                                <button type="submit" class="notification-mark-read" aria-label="Mark notification as read" title="Mark as read">
                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                </button>
                            </form>

                        <?php elseif ($notifLink !== ''): ?>
                            <div class="notification-open-form">
                                <a href="<?= htmlspecialchars($notifLink, ENT_QUOTES, 'UTF-8'); ?>" class="notification-item-button">
                                    <span class="notification-item-icon"><i class="bi bi-bell" aria-hidden="true"></i></span>
                                    <span class="notification-item-content">
                                        <p><?= $notifText; ?></p>
                                        <time datetime="<?= $notifIso; ?>" title="<?= $notifTitle; ?>"><?= $notifLabel; ?></time>
                                    </span>
                                </a>
                            </div>

                        <?php else: ?>
                            <div class="notification-open-form">
                                <div class="notification-item-button notification-item-static">
                                    <span class="notification-item-icon"><i class="bi bi-bell" aria-hidden="true"></i></span>
                                    <span class="notification-item-content">
                                        <p><?= $notifText; ?></p>
                                        <time datetime="<?= $notifIso; ?>" title="<?= $notifTitle; ?>"><?= $notifLabel; ?></time>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>

                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>