<?php
/**
 * CTECHLIT & SYSTEMS — Glass Sidebar Component
 * Role-based modular navigation with permission filtering
 */

require_once __DIR__ . '/auth.php';

$menuItems = getSidebarMenu();
$currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$currentUser = currentStaff() ?: currentUser();
$isAdmin = hasPermission('staff.assign_permissions');
?>

<aside class="glass-sidebar" id="sidebar">
    <div class="sidebar-header">
        <a href="<?php echo base_path('index.php'); ?>" class="sidebar-logo">
            <svg viewBox="0 0 400 80" width="120" height="24" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="logoGradSidebar" x1="0%" y1="0%" x2="100%" y2="0%">
                        <stop offset="0%" stop-color="#7C4DFF"/>
                        <stop offset="50%" stop-color="#2FD9EE"/>
                        <stop offset="100%" stop-color="#B43FE0"/>
                    </linearGradient>
                </defs>
                <g transform="translate(4, 4)">
                    <circle cx="28" cy="28" r="24" fill="none" stroke="url(#logoGradSidebar)" stroke-width="3" opacity="0.9"/>
                    <circle cx="28" cy="28" r="4" fill="url(#logoGradSidebar)"/>
                </g>
                <text x="72" y="34" font-family="'Space Grotesk', sans-serif" font-weight="700" font-size="16" fill="#1a1a2e">CTECHLIT<tspan font-weight="400" fill="#6B7394">&</tspan>SYSTEMS</text>
            </svg>
        </a>
        <button class="sidebar-close" id="sidebarClose" aria-label="Close sidebar">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($menuItems as $item): ?>
                <?php
                    $hasChildren = !empty($item['children']);
                    $isActive = !$hasChildren && $currentPath === $item['href'];
                    $badgeCount = null;

                    // Get dynamic badge count
                    if (!empty($item['badge'])) {
                        $badgeCount = getSidebarBadge($item['badge']);
                    }
                ?>
                <li class="nav-item <?php echo $isActive ? 'active' : ''; ?>">
                    <?php if ($hasChildren): ?>
                        <div class="nav-link nav-parent" data-menu="<?php echo $item['id']; ?>" title="<?php echo sanitize($item['label']); ?>" aria-label="<?php echo sanitize($item['label']); ?>">
                            <div class="nav-icon">
                                <i class="fas <?php echo $item['icon']; ?>"></i>
                            </div>
                            <span class="nav-label"><?php echo sanitize($item['label']); ?></span>
                            <?php if ($badgeCount !== null && $badgeCount > 0): ?>
                                <span class="nav-badge"><?php echo $badgeCount; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-down nav-arrow"></i>
                        </div>
                        <ul class="nav-submenu" id="submenu-<?php echo $item['id']; ?>">
                            <?php foreach ($item['children'] as $child): ?>
                                <?php if (hasPermission($child['permission'])): ?>
                                    <li class="nav-subitem">
                                        <a href="<?php echo $child['href']; ?>" class="nav-sublink">
                                            <?php echo sanitize($child['label']); ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <a href="<?php echo $item['href']; ?>" class="nav-link" title="<?php echo sanitize($item['label']); ?>" aria-label="<?php echo sanitize($item['label']); ?>">
                            <div class="nav-icon">
                                <i class="fas <?php echo $item['icon']; ?>"></i>
                            </div>
                            <span class="nav-label"><?php echo sanitize($item['label']); ?></span>
                            <?php if ($badgeCount !== null && $badgeCount > 0): ?>
                                <span class="nav-badge"><?php echo $badgeCount; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="user-card">
            <div class="user-avatar">
                <?php echo strtoupper(substr($currentUser['full_name'] ?? 'U', 0, 1)); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo sanitize($currentUser['full_name'] ?? 'User'); ?></span>
                <span class="user-role"><?php echo getRoleLabel($currentUser['role'] ?? 'user'); ?></span>
            </div>
            <a href="<?php echo base_path('logout.php', false); ?>" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </div>
    </div>
</aside>

<!-- Overlay for mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarClose = document.getElementById('sidebarClose');

    function updateToggleState() {
        if (!sidebarToggle) return;
        const desktop = window.innerWidth > 768;
        const expanded = desktop
            ? !document.body.classList.contains('sidebar-collapsed')
            : Boolean(sidebar && sidebar.classList.contains('open'));
        const label = desktop
            ? (expanded ? 'Collapse sidebar' : 'Expand sidebar')
            : (expanded ? 'Close sidebar' : 'Open sidebar');
        sidebarToggle.setAttribute('aria-expanded', String(expanded));
        sidebarToggle.setAttribute('aria-label', label);
        sidebarToggle.setAttribute('title', label);
    }

    function setDesktopCollapsed(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        document.querySelectorAll('.nav-submenu.open').forEach(function(menu) {
            menu.classList.remove('open');
            menu.style.removeProperty('top');
            if (menu.previousElementSibling) {
                menu.previousElementSibling.classList.remove('open');
            }
        });
        updateToggleState();
    }

    let savedCollapsed = false;
    try {
        savedCollapsed = window.localStorage.getItem('portalSidebarCollapsed') === 'true';
    } catch (error) {
        console.warn('Unable to restore the sidebar preference.', error);
    }
    setDesktopCollapsed(window.innerWidth > 768 && savedCollapsed);

    function openSidebar() {
        if (!sidebar) return;
        sidebar.classList.add('open');
        if (sidebarOverlay) {
            sidebarOverlay.classList.add('active');
        }
        document.body.style.overflow = 'hidden';
        updateToggleState();
    }

    function closeSidebar() {
        if (!sidebar) return;
        sidebar.classList.remove('open');
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('active');
        }
        document.body.style.overflow = '';
        updateToggleState();
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            if (window.innerWidth > 768) {
                const collapsed = !document.body.classList.contains('sidebar-collapsed');
                setDesktopCollapsed(collapsed);
                savedCollapsed = collapsed;
                try {
                    window.localStorage.setItem('portalSidebarCollapsed', String(collapsed));
                } catch (error) {
                    console.warn('Unable to save the sidebar preference.', error);
                }
                return;
            }

            if (sidebar && sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            closeSidebar();
            setDesktopCollapsed(savedCollapsed);
        }
    });

    // Sidebar submenu toggle
    const navParents = document.querySelectorAll('.nav-parent');
    navParents.forEach(function(parent) {
        parent.addEventListener('click', function(e) {
            e.preventDefault();
            if (document.body.classList.contains('sidebar-collapsed') && window.innerWidth > 768) {
                const submenuId = this.getAttribute('data-menu');
                const submenu = document.getElementById('submenu-' + submenuId);
                const isOpen = submenu && submenu.classList.contains('open');
                document.querySelectorAll('.nav-submenu.open').forEach(function(menu) {
                    menu.classList.remove('open');
                    menu.style.removeProperty('top');
                    if (menu.previousElementSibling) {
                        menu.previousElementSibling.classList.remove('open');
                    }
                });
                if (submenu && !isOpen) {
                    submenu.classList.add('open');
                    this.classList.add('open');
                    const top = this.getBoundingClientRect().top;
                    const maxTop = window.innerHeight - submenu.getBoundingClientRect().height - 12;
                    submenu.style.top = Math.max(12, Math.min(top, maxTop)) + 'px';
                }
                return;
            }

            const submenuId = this.getAttribute('data-menu');
            const submenu = document.getElementById('submenu-' + submenuId);
            if (submenu) {
                const isOpen = submenu.classList.contains('open');
                // Close all other submenus
                document.querySelectorAll('.nav-submenu.open').forEach(function(menu) {
                    menu.classList.remove('open');
                    if (menu.previousElementSibling) {
                        menu.previousElementSibling.classList.remove('open');
                    }
                });
                // Toggle current submenu
                if (!isOpen) {
                    submenu.classList.add('open');
                    this.classList.add('open');
                }
            }
        });
    });
});
</script>
