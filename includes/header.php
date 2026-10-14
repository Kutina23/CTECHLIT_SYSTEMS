<?php
/**
 * CTECHLIT & SYSTEMS — Glass Header Component
 * Modern glass-morphism design with user menu
 */

require_once __DIR__ . '/auth.php';

$currentUser = currentStaff() ?: currentUser();
$userType = $_SESSION['user_type'] ?? 'guest';
$isAdmin = hasPermission('staff.assign_permissions');
$canUseJarvis = hasPermission('jarvis.use');
$canViewJarvis = hasPermission('jarvis.view');
$canCreateSystemNotifications = $userType === 'staff' && hasPermission('settings.edit');
$notificationApiUrl = base_path('api/notifications.php');
if (strpos($notificationApiUrl, '/portal/') === false) {
    $notificationApiUrl = preg_replace('#/api/notifications\.php$#', '/portal/api/notifications.php', $notificationApiUrl);
}
$jarvisApiUrl = base_path('api/jarvis.php');
// Versioned asset URLs so browsers load updated files instead of stale cached copies.
$assetUrl = static function (string $path): string {
    $file = __DIR__ . '/../' . $path;
    return base_path($path, false) . (is_file($file) ? '?v=' . filemtime($file) : '');
};
$notificationCsrfToken = generateCsrfToken();
?>

<header class="glass-header">
    <div class="header-left">
        <button class="sidebar-toggle" id="sidebarToggle" aria-label="Collapse sidebar" aria-expanded="true" title="Collapse sidebar">
            <i class="fas fa-bars"></i>
        </button>
        <a href="<?php echo base_path('index.php'); ?>" class="header-logo">
            <svg viewBox="0 0 400 80" width="140" height="28" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="logoGradHeader" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#7C4DFF"/>
                        <stop offset="50%" stop-color="#2FD9EE"/>
                        <stop offset="100%" stop-color="#B43FE0"/>
                    </linearGradient>
                </defs>
                <g transform="translate(4, 4)">
                    <circle cx="28" cy="28" r="24" fill="none" stroke="url(#logoGradHeader)" stroke-width="3" opacity="0.9"/>
                    <circle cx="28" cy="28" r="4" fill="url(#logoGradHeader)"/>
                </g>
                <text x="72" y="34" font-family="'Space Grotesk', sans-serif" font-weight="700" font-size="18" fill="#1a1a2e">CTECHLIT<tspan font-weight="400" fill="#6B7394">&</tspan>SYSTEMS</text>
            </svg>
        </a>
    </div>

    <div class="header-center">
        <?php if ($canUseJarvis): ?>
        <button class="deck-launcher" id="jarvisOpen" type="button" title="Ask Jarvis (Alt+J)" aria-haspopup="dialog" aria-expanded="false" aria-controls="jarvisPanel">
            <span class="deck-launcher-orb" aria-hidden="true">
                <span class="deck-bars"><i></i><i></i><i></i><i></i><i></i></span>
            </span>
            <span class="deck-launcher-label">Jarvis</span>
        </button>
        <?php endif; ?>
    </div>

    <div class="header-right">
        <!-- Notifications -->
        <div class="header-action">
            <button class="icon-btn notification-btn" id="notificationBtn" aria-label="Notifications">
                <i class="fas fa-bell"></i>
                <span class="notification-badge" id="notificationBadge">0</span>
            </button>
            <div class="dropdown notification-dropdown" id="notificationDropdown">
                <div class="dropdown-header">
                    <h3>Notifications</h3>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <?php if ($canCreateSystemNotifications): ?>
                            <button class="icon-btn" id="createSystemNotification" type="button" aria-label="Create system notification" title="Create system notification" style="width:30px;height:30px;">
                                <i class="fas fa-plus"></i>
                            </button>
                        <?php endif; ?>
                        <button class="mark-read-btn" id="markAllRead" type="button">Mark all read</button>
                    </div>
                </div>
                <div class="notification-list" id="notificationList">
                    <div class="notification-empty">No notifications</div>
                </div>
            </div>
        </div>

        <!-- User Menu -->
        <div class="header-action user-menu">
            <button class="user-menu-btn" id="userMenuBtn">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)); ?>
                </div>
                <div class="user-info">
                    <span class="user-name"><?php echo sanitize($currentUser['full_name'] ?? 'User'); ?></span>
                    <span class="user-role"><?php echo getRoleLabel($currentUser['role'] ?? 'user'); ?></span>
                </div>
                <i class="fas fa-chevron-down"></i>
            </button>
            <div class="dropdown user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="user-avatar large">
                        <?php echo strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo sanitize($currentUser['full_name'] ?? 'User'); ?></h4>
                        <p><?php echo sanitize($currentUser['email'] ?? $currentUser['phone'] ?? ''); ?></p>
                        <span class="role-badge"><?php echo getRoleLabel($currentUser['role'] ?? 'user'); ?></span>
                    </div>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?php echo base_path('profile.php'); ?>" class="dropdown-item">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="<?php echo base_path('settings.php'); ?>" class="dropdown-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <?php if ($isAdmin): ?>
                <a href="<?php echo base_path('admin/staff.php'); ?>" class="dropdown-item">
                    <i class="fas fa-user-shield"></i> Staff Management
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?php echo base_path('logout.php', false); ?>" class="dropdown-item logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<?php if ($canCreateSystemNotifications): ?>
<div class="modal-overlay" id="notificationDrawerOverlay" role="dialog" aria-modal="true" aria-labelledby="notificationFormTitle">
    <form class="modal" id="systemNotificationForm">
        <div class="modal-header">
            <h3 class="modal-title" id="notificationFormTitle">Create System Notification</h3>
            <button class="modal-close" type="button" id="closeNotificationDrawer" aria-label="Close form"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label" for="systemNotificationAudience">Audience</label>
                <select class="form-select form-input" id="systemNotificationAudience" name="audience" required>
                    <option value="all">Everyone</option>
                    <option value="staff">Staff only</option>
                    <option value="users">Customers only</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="systemNotificationType">Notification type</label>
                <select class="form-select form-input" id="systemNotificationType" name="type" required>
                    <option value="info">Information</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="error">Important / error</option>
                    <option value="promotion">Promotion</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="systemNotificationTitle">Title</label>
                <input class="form-input" id="systemNotificationTitle" name="title" maxlength="255" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="systemNotificationMessage">Message</label>
                <textarea class="form-textarea form-input" id="systemNotificationMessage" name="message" rows="5" maxlength="5000" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="systemNotificationExpiry">Expires at (optional)</label>
                <input class="form-input" id="systemNotificationExpiry" name="expires_at" type="datetime-local">
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" type="button" id="cancelNotificationDrawer">Cancel</button>
            <button class="btn btn-primary" type="submit" id="saveSystemNotification">Send Notification</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script src="<?php echo sanitize($assetUrl('assets/js/button-loading.js')); ?>" defer></script>
<script src="<?php echo sanitize($assetUrl('assets/js/ctl-dialog.js')); ?>"></script>
<script>window.CTL_CURRENCY = <?php echo json_encode(siteSetting('currency_symbol', 'GHS')); ?>;</script>
<script src="<?php echo sanitize($assetUrl('assets/js/payments.js')); ?>" defer></script>

<?php if ($canUseJarvis): ?>
<link rel="stylesheet" href="<?php echo sanitize($assetUrl('assets/css/jarvis-deck.css')); ?>">
<script>
window.jarvisConfig = {
    apiUrl: <?php echo json_encode($jarvisApiUrl); ?>,
    csrfToken: <?php echo json_encode($notificationCsrfToken); ?>,
    userName: <?php echo json_encode($currentUser['full_name'] ?? ''); ?>
};
</script>

<section class="deck-panel" id="jarvisPanel" role="dialog" aria-labelledby="jarvisTitle" hidden data-state="idle" data-session="off" data-duplex="off">
    <header class="deck-head">
        <span class="deck-app-icon" aria-hidden="true">
            <span class="deck-bars"><i></i><i></i><i></i><i></i><i></i></span>
        </span>
        <div>
            <h2 id="jarvisTitle">Jarvis</h2>
            <p>Voice AI Assistant</p>
        </div>
        <button class="deck-close" id="jarvisClose" type="button" aria-label="Close Jarvis"><i class="fas fa-times"></i></button>
    </header>

    <div class="deck-hero">
        <button class="deck-orb" id="jarvisOrb" type="button" aria-label="Start or stop talking to Jarvis">
            <span class="deck-orb-ring r2" aria-hidden="true"></span>
            <span class="deck-orb-ring" aria-hidden="true"></span>
            <span class="deck-orb-core" aria-hidden="true">
                <span class="deck-bars"><i></i><i></i><i></i><i></i><i></i></span>
            </span>
        </button>
        <p class="deck-status" id="jarvisStatus" aria-live="polite">Tap the mic to talk</p>
        <p class="deck-mode"><span class="deck-dot" aria-hidden="true"></span><span id="jarvisModeText">Duplex Mode Ready</span></p>
    </div>

    <div class="deck-conversation" id="jarvisConversation" aria-live="polite">
        <p class="deck-empty">Ask about customers, sales or billing, or control hardware: <strong>“lock station 2”</strong>, <strong>“wake all stations”</strong>, <strong>“which stations are online?”</strong></p>
    </div>

    <form class="deck-composer" id="jarvisComposer" hidden>
        <label class="deck-sr-only" for="jarvisInput">Message Jarvis</label>
        <input id="jarvisInput" type="text" maxlength="1000" autocomplete="off" placeholder="Type a request…">
        <button type="submit" aria-label="Send"><i class="fas fa-paper-plane"></i></button>
    </form>

    <footer class="deck-foot">
        <button class="deck-side-btn" id="jarvisKeyboard" type="button" aria-label="Type instead of speaking" aria-pressed="false" title="Type instead"><i class="fas fa-keyboard"></i></button>
        <div class="deck-mic-wrap">
            <button class="deck-mic" id="jarvisMic" type="button" aria-label="Start talking"><i class="fas fa-microphone"></i></button>
            <span class="deck-mic-label" id="jarvisMicLabel">Tap to speak</span>
        </div>
        <button class="deck-side-btn" id="jarvisClear" type="button" aria-label="Clear conversation" title="Clear conversation"><i class="fas fa-rotate-left"></i></button>
    </footer>
</section>

<script src="<?php echo sanitize($assetUrl('assets/js/jarvis.js')); ?>" defer></script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Notification dropdown toggle
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationDropdown = document.getElementById('notificationDropdown');
    const notificationList = document.getElementById('notificationList');
    const notificationBadge = document.getElementById('notificationBadge');
    const notificationApiUrl = <?php echo json_encode($notificationApiUrl); ?>;
    const notificationCsrfToken = <?php echo json_encode($notificationCsrfToken); ?>;
    const canCreateSystemNotifications = <?php echo $canCreateSystemNotifications ? 'true' : 'false'; ?>;

    function renderNotifications(notifications, unreadCount) {
        notificationBadge.textContent = unreadCount > 99 ? '99+' : String(unreadCount);
        notificationBadge.style.display = unreadCount > 0 ? '' : 'none';
        notificationList.replaceChildren();
        if (!notifications.length) {
            const empty = document.createElement('div');
            empty.className = 'notification-empty';
            empty.textContent = 'No notifications';
            notificationList.appendChild(empty);
            return;
        }

        notifications.forEach(notification => {
            const isRead = Number(notification.is_read) === 1;
            const item = document.createElement('div');
            item.className = 'notification-item';
            item.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:var(--radius-sm);${isRead ? '' : 'background:rgba(124,58,237,.08);'}`;
            const icon = document.createElement('i');
            icon.className = `fas ${notification.type === 'warning' ? 'fa-exclamation-triangle' : notification.type === 'error' ? 'fa-exclamation-circle' : notification.type === 'success' ? 'fa-check-circle' : notification.type === 'promotion' ? 'fa-tag' : 'fa-info-circle'}`;
            icon.style.cssText = 'margin-top:3px;color:var(--violet);';
            const content = document.createElement('div');
            content.style.cssText = 'flex:1;min-width:0;cursor:pointer;';
            const title = document.createElement('strong');
            title.textContent = notification.title;
            title.style.cssText = 'display:block;color:var(--ink);font-size:13px;';
            const message = document.createElement('span');
            message.textContent = notification.message;
            message.style.cssText = 'display:block;color:var(--ink-secondary);font-size:12px;white-space:pre-wrap;overflow-wrap:anywhere;margin-top:3px;';
            const date = document.createElement('small');
            date.textContent = new Date(notification.created_at.replace(' ', 'T')).toLocaleString();
            date.style.cssText = 'display:block;color:var(--muted);font-size:10px;margin-top:5px;';
            content.append(title, message, date);
            if (!isRead) {
                content.addEventListener('click', () => markNotificationRead(notification.notification_id));
            }
            item.append(icon, content);

            if (canCreateSystemNotifications && notification.system_notification_id) {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'header-btn';
                remove.title = 'Delete system notification';
                remove.setAttribute('aria-label', 'Delete system notification');
                remove.innerHTML = '<i class="fas fa-trash"></i>';
                remove.style.cssText = 'color:var(--red);flex:0 0 auto;';
                remove.addEventListener('click', () => deleteSystemNotification(notification.system_notification_id));
                item.appendChild(remove);
            }
            notificationList.appendChild(item);
        });
    }

    async function loadNotifications() {
        try {
            const response = await fetch(notificationApiUrl, {
                credentials:'same-origin',
                headers:{'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json'}
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Unable to load notifications');
            renderNotifications(result.data.notifications || [], Number(result.data.unread_count || 0));
        } catch (error) {
            notificationList.textContent = error.message || 'Unable to load notifications';
        }
    }

    async function updateNotifications(method, payload) {
        const response = await fetch(notificationApiUrl, {
            method,
            credentials:'same-origin',
            headers:{'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json', 'X-CSRF-Token':notificationCsrfToken},
            body:JSON.stringify(payload)
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Notification update failed');
        await loadNotifications();
    }

    async function markNotificationRead(notificationId) {
        try {
            await updateNotifications('PUT', {notification_id:notificationId});
        } catch (error) {
            CtlDialog.alert({title: 'Something went wrong', message: error.message || 'Notification could not be marked as read.', tone: 'danger', icon: 'fa-circle-exclamation'});
        }
    }

    async function deleteSystemNotification(systemNotificationId) {
        if (!(await CtlDialog.confirm({title: 'Delete this notification?', message: 'It will be removed for everyone who received it.', tone: 'danger', icon: 'fa-trash', confirmText: 'Delete'}))) return;
        try {
            await updateNotifications('DELETE', {system_notification_id:systemNotificationId});
        } catch (error) {
            CtlDialog.alert({title: 'Something went wrong', message: error.message || 'Notification could not be deleted.', tone: 'danger', icon: 'fa-circle-exclamation'});
        }
    }

    loadNotifications();
    
    if (notificationBtn && notificationDropdown) {
        notificationBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Close user dropdown if open
            const userDropdown = document.getElementById('userDropdown');
            const userMenuBtn = document.getElementById('userMenuBtn');
            if (userDropdown && userMenuBtn) {
                userDropdown.classList.remove('active');
                userMenuBtn.classList.remove('open');
            }
            notificationDropdown.classList.toggle('active');
            if (notificationDropdown.classList.contains('active')) loadNotifications();
        });
    }

    // User menu dropdown toggle
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            // Close notification dropdown if open
            if (notificationDropdown) {
                notificationDropdown.classList.remove('active');
            }
            userDropdown.classList.toggle('active');
            this.classList.toggle('open');
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (notificationDropdown && !notificationDropdown.contains(e.target) && e.target !== notificationBtn) {
            notificationDropdown.classList.remove('active');
        }
        if (userDropdown && !userDropdown.contains(e.target) && e.target !== userMenuBtn && !userMenuBtn.contains(e.target)) {
            userDropdown.classList.remove('active');
            userMenuBtn.classList.remove('open');
        }
    });

    // Mark all read button
    const markAllReadBtn = document.getElementById('markAllRead');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', async function() {
            try {
                await updateNotifications('PUT', {mark_all_read:true});
            } catch (error) {
                CtlDialog.alert({title: 'Something went wrong', message: error.message || 'Notifications could not be marked as read.', tone: 'danger', icon: 'fa-circle-exclamation'});
            }
        });
    }

    if (canCreateSystemNotifications) {
        const drawer = document.getElementById('notificationDrawerOverlay');
        const form = document.getElementById('systemNotificationForm');
        const closeDrawer = () => {
            drawer.classList.add('closing');
            window.setTimeout(() => {
                drawer.classList.remove('active', 'closing');
                document.body.style.overflow = '';
            }, 250);
        };
        const openDrawer = () => {
            form.reset();
            drawer.classList.add('active');
            notificationDropdown.classList.remove('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('systemNotificationTitle').focus();
        };
        document.getElementById('createSystemNotification').addEventListener('click', openDrawer);
        document.getElementById('closeNotificationDrawer').addEventListener('click', closeDrawer);
        document.getElementById('cancelNotificationDrawer').addEventListener('click', closeDrawer);
        drawer.addEventListener('click', event => {
            if (event.target === drawer) closeDrawer();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && drawer.classList.contains('active')) closeDrawer();
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const submitButton = document.getElementById('saveSystemNotification');
            submitButton.disabled = true;
            try {
                const formData = new FormData(form);
                const response = await fetch(notificationApiUrl, {
                    method:'POST',
                    credentials:'same-origin',
                    headers:{'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest', 'Accept':'application/json', 'X-CSRF-Token':notificationCsrfToken},
                    body:JSON.stringify(Object.fromEntries(formData.entries()))
                });
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Notification could not be sent');
                closeDrawer();
                form.reset();
                await loadNotifications();
            } catch (error) {
                CtlDialog.alert({title: 'Something went wrong', message: error.message || 'Notification could not be sent.', tone: 'danger', icon: 'fa-circle-exclamation'});
            } finally {
                submitButton.disabled = false;
            }
        });
    }
});
</script>
