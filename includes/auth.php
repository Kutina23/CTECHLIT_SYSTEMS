<?php
/**
 * CTECHLIT & SYSTEMS — Authentication & Authorization
 * Role-Based Access Control (RBAC) System
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/functions.php';

// ============================================================
// AUTHENTICATION
// ============================================================

/**
 * Check if user is authenticated
 */
function isAuthenticated(): bool
{
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        return false;
    }
    static $checked = false;
    if (!$checked) {
        $checked = true;
        if (!sessionIsFresh()) {
            endSession();
            return false;
        }
    }
    return true;
}

/**
 * Enforce idle and absolute session lifetimes and drop sessions of staff who were deactivated.
 * Idle limit = SESSION_LIFETIME (default 2 h); absolute limit = SESSION_MAX_AGE (default 14 h, one working day).
 */
function sessionIsFresh(): bool
{
    $now = time();
    // Staff idle sign-out: Settings → Security overrides SESSION_LIFETIME from config/.env.
    $idleMinutes = (int) systemSetting('session_timeout_minutes', '0');
    $idle = $idleMinutes >= 5 ? $idleMinutes * 60 : max(300, (int) ($GLOBALS['config']['security']['session_lifetime'] ?? 7200));
    $maxAge = max($idle, (int) ($_ENV['SESSION_MAX_AGE'] ?? 50400));
    $loggedInAt = (int) ($_SESSION['logged_in_at'] ?? 0);
    $lastActivity = (int) ($_SESSION['last_activity'] ?? $loggedInAt);
    if (!empty($_SESSION['remember'])) {
        $idle = $maxAge;
    }
    if ($loggedInAt === 0 || $now - $lastActivity > $idle || $now - $loggedInAt > $maxAge) {
        return false;
    }
    // A stolen cookie replayed from a different browser is rejected.
    if (isset($_SESSION['ua_hash']) && !hash_equals($_SESSION['ua_hash'], hash('sha256', getUserAgent()))) {
        return false;
    }
    if (($_SESSION['user_type'] ?? '') === 'staff' && $now - (int) ($_SESSION['active_checked_at'] ?? 0) > 60) {
        $active = Database::fetchColumn('SELECT is_active FROM staff WHERE staff_id = ?', [(int) $_SESSION['user_id']]);
        if (!$active) {
            return false;
        }
        $_SESSION['active_checked_at'] = $now;
    }
    $_SESSION['last_activity'] = $now;
    return true;
}

function endSession(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Get current authenticated user
 */
function currentUser(): ?array
{
    if (!isAuthenticated()) {
        return null;
    }

    $userId = $_SESSION['user_id'];
    return Database::fetchOne(
        "SELECT user_id, email, phone, full_name, membership_tier, is_active FROM users WHERE user_id = ?",
        [$userId]
    );
}

/**
 * Get current authenticated staff
 */
function currentStaff(): ?array
{
    if (!isAuthenticated() || $_SESSION['user_type'] !== 'staff') {
        return null;
    }

    $staffId = $_SESSION['user_id'];
    return Database::fetchOne(
        "SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, is_active FROM staff WHERE staff_id = ?",
        [$staffId]
    );
}

/**
 * Detect AJAX/JSON requests
 */
function isAjaxRequest(): bool
{
    $requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    $contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

    return $requestedWith === 'xmlhttprequest'
        || str_contains($accept, 'application/json')
        || str_contains($contentType, 'application/json');
}

/**
 * Return a JSON error for AJAX requests instead of redirecting to HTML
 */
function sendJsonAuthError(): void
{
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please log in again.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Require authentication (redirect if not logged in)
 */
function requireAuth(string $redirect = '/login.php'): void
{
    enforceSameOrigin();
    if (!isAuthenticated()) {
        if (isAjaxRequest()) {
            sendJsonAuthError();
        }
        redirect($redirect === '/login.php' ? base_path('login.php', false) : $redirect);
    }
}

/**
 * Require staff authentication
 */
function requireStaffAuth(string $redirect = '/login.php'): void
{
    enforceSameOrigin();
    if (!isAuthenticated() || ($_SESSION['user_type'] ?? '') !== 'staff') {
        if (isAjaxRequest()) {
            sendJsonAuthError();
        }
        redirect($redirect === '/login.php' ? base_path('login.php', false) : $redirect);
    }
}

/**
 * Login user
 */
function loginUser(int $userId, string $userType = 'user', bool $remember = false): void
{
    // New session ID on every login (prevents session fixation); old session data is discarded.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION = [];
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_type'] = $userType;
    $_SESSION['logged_in_at'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['active_checked_at'] = time();
    $_SESSION['ip_address'] = getClientIp();
    $_SESSION['user_agent'] = getUserAgent();
    $_SESSION['ua_hash'] = hash('sha256', getUserAgent());
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    // "Remember me" keeps the session open for the full working day instead of the idle limit.
    if ($remember) {
        $_SESSION['remember'] = true;
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + (int) ($_ENV['SESSION_MAX_AGE'] ?? 50400),
            'path' => $params['path'], 'domain' => $params['domain'],
            'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Strict',
        ]);
    }

    // Update last login
    if ($userType === 'staff') {
        Database::execute(
            "UPDATE staff SET last_login_at = NOW() WHERE staff_id = ?",
            [$userId]
        );
        logAuditTrail('staff_login', 'staff', $userId, [
            'user_type' => $userType,
            'login_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
        ], $userId, 'staff');
    } else {
        Database::execute(
            "UPDATE users SET last_login_at = NOW() WHERE user_id = ?",
            [$userId]
        );
    }
}

/**
 * Logout user
 */
function logout(): void
{
    $actorId = $_SESSION['user_id'] ?? null;
    $actorType = $_SESSION['user_type'] ?? 'staff';

    if ($actorId) {
        logAuditTrail('staff_logout', 'staff', (int) $actorId, ['user_type' => $actorType], (int) $actorId, $actorType);
    }

    $_SESSION = [];
    if (isset($_COOKIE['remember_token'])) {
        setcookie('remember_token', '', time() - 3600, '/');
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    session_destroy();
}

// ============================================================
// AUTHORIZATION & PERMISSIONS
// ============================================================

/**
 * Permission definitions
 */
const PERMISSIONS = [
    // Dashboard
    'dashboard.view' => 'View Dashboard',
    'dashboard.stats' => 'View Statistics',

    // Users
    'users.view' => 'View Users',
    'users.create' => 'Create Users',
    'users.edit' => 'Edit Users',
    'users.delete' => 'Delete Users',

    // Stations
    'stations.view' => 'View Stations',
    'stations.manage' => 'Manage Stations',
    'stations.control' => 'Control Stations (start/stop sessions)',

    // Sessions
    'sessions.view' => 'View Sessions',
    'sessions.manage' => 'Manage Sessions',
    'sessions.end' => 'End Sessions',

    // Services
    'services.view' => 'View Services',
    'services.create' => 'Create Services',
    'services.edit' => 'Edit Services',
    'services.delete' => 'Delete Services',

    // Print Jobs
    'print_jobs.view' => 'View Print Jobs',
    'print_jobs.manage' => 'Manage Print Jobs',
    'print_jobs.cancel' => 'Cancel Print Jobs',

    // Printers
    'printers.view' => 'View Printers',
    'printers.create' => 'Create Printers',
    'printers.edit' => 'Edit Printers',
    'printers.delete' => 'Delete Printers',

    // Transactions
    'transactions.view' => 'View Transactions',
    'transactions.refund' => 'Process Refunds',
    'transactions.reports' => 'View Financial Reports',

    // POS & Finance
    'pos.view' => 'View POS',
    'pos.manage' => 'Manage POS Sales',
    'audit.view' => 'View Audit Trail',

    // Customer Billing
    'billing.view' => 'View Customer Billing',
    'billing.manage' => 'Manage Billing, Invoices & Payments',

    // Inventory
    'inventory.view' => 'View Inventory',
    'inventory.manage' => 'Manage Inventory',
    'inventory.adjust' => 'Adjust Stock',

    // Cards
    'cards.create' => 'Create ID & Loyalty Cards',

    // Jarvis
    'jarvis.view' => 'View Jarvis Assistant',
    'jarvis.use' => 'Use Jarvis Assistant',

    // Personal Devices
    'personal_devices.view' => 'View Personal Devices',
    'personal_devices.manage' => 'Manage Personal Devices',

    // Branded Assets
    'branded_assets.view' => 'View Branded Assets',
    'branded_assets.create' => 'Generate Branded Assets',
    'branded_assets.edit' => 'Edit Branded Asset Templates',
    'branded_assets.delete' => 'Delete Branded Asset Templates',

    // Staff
    'staff.view' => 'View Staff',
    'staff.create' => 'Create Staff',
    'staff.edit' => 'Edit Staff',
    'staff.delete' => 'Delete Staff',
    'staff.assign_permissions' => 'Assign Permissions',

    // Settings
    'settings.view' => 'View Settings',
    'settings.edit' => 'Edit Settings',

    // Reports
    'reports.view' => 'View Reports',
    'reports.export' => 'Export Reports',

    // Feedback
    'feedback.view' => 'View Feedback',
    'feedback.respond' => 'Respond to Feedback',

    // Support
    'support.view' => 'View Support Tickets',
    'support.manage' => 'Manage Support Tickets',
];

/**
 * Role definitions. Only administrators have implicit permissions; all other
 * staff receive permissions through explicit staff_permissions assignments.
 */
const ROLES = [
    'ceo' => [
        'label' => 'Chief Executive Officer',
        'description' => 'System administrator with full access and permission management',
        'permissions' => '*', // All permissions (wildcard)
    ],
    'admin' => [
        'label' => 'Administrator',
        'description' => 'Full system access',
        'permissions' => '*', // All permissions (wildcard)
    ],
    'manager' => [
        'label' => 'Manager',
        'description' => 'Permissions assigned individually by an administrator',
        'permissions' => [],
    ],
    'attendant' => [
        'label' => 'Attendant',
        'description' => 'Permissions assigned individually by an administrator',
        'permissions' => [],
    ],
    'technician' => [
        'label' => 'Technician',
        'description' => 'Permissions assigned individually by an administrator',
        'permissions' => [],
    ],
];

/**
 * Get effective permissions: all permissions for admins, explicit grants for others.
 */
function getStaffPermissions(int $staffId): array
{
    $staff = Database::fetchOne(
        "SELECT role FROM staff WHERE staff_id = ? AND is_active = 1",
        [$staffId]
    );

    if (!$staff) {
        return [];
    }

    $role = strtolower($staff['role']);

    if ($role === 'admin' || $role === 'ceo') {
        return array_keys(PERMISSIONS);
    }

    $customPerms = Database::fetchAll(
        "SELECT permission FROM staff_permissions WHERE staff_id = ? AND granted = 1",
        [$staffId]
    );

    $permissions = array_values(array_unique(array_column($customPerms, 'permission')));

    // Resolve wildcard to all permissions so callers get usable permission keys
    if (in_array('*', $permissions, true)) {
        return array_keys(PERMISSIONS);
    }

    return $permissions;
}

/**
 * Check if current staff has permission
 */
function hasPermission(string $permission): bool
{
    $staff = currentStaff();
    if (!$staff) {
        return false;
    }

    // Admin/CEO has all permissions (wildcard)
    $role = strtolower($staff['role']);

    if ($role === 'admin' || $role === 'ceo') {
        return true;
    }

    $permissions = getStaffPermissions((int) $staff['staff_id']);
    if (in_array('*', $permissions, true)) {
        return true;
    }
    return in_array($permission, $permissions, true);
}

/** Is the signed-in staff member an administrator (role admin or ceo)? */
function currentStaffIsAdmin(): bool
{
    $staff = currentStaff();
    return $staff !== null && in_array(strtolower((string) $staff['role']), ['admin', 'ceo'], true);
}

/**
 * Guard staff-account changes against privilege escalation:
 * only administrators may create/edit administrators or hand out the admin/ceo role.
 */
function assertCanManageStaffAccount(?string $targetRole, ?string $newRole = null): void
{
    $privileged = ['admin', 'ceo'];
    if (currentStaffIsAdmin()) {
        // Only the CEO can create or modify another CEO.
        $me = strtolower((string) (currentStaff()['role'] ?? ''));
        if ($me !== 'ceo' && (strtolower((string) $targetRole) === 'ceo' || strtolower((string) $newRole) === 'ceo')) {
            errorResponse('Only the CEO can change a CEO account.', 403);
        }
        return;
    }
    if (in_array(strtolower((string) $targetRole), $privileged, true) || in_array(strtolower((string) $newRole), $privileged, true)) {
        errorResponse('Only an administrator can create or change administrator accounts.', 403);
    }
}

/** Minimum password policy for staff accounts. Returns an error message or null. */
function staffPasswordProblem(string $password): ?string
{
    if (strlen($password) < 10) {
        return 'Use a password of at least 10 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Use a password with both letters and numbers.';
    }
    if (preg_match('/^(password|letmein|qwerty|admin|welcome|12345)/i', $password)) {
        return 'That password is too easy to guess.';
    }
    return null;
}

/**
 * Require permission (redirect or show error if not authorized)
 */
function requirePermission(string $permission, string $redirect = ''): void
{
    if ($redirect === '') {
        $redirect = base_path('index.php');
    }

    if (!hasPermission($permission)) {
        if (isAjaxRequest()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Forbidden: You do not have permission to access this resource'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        redirect($redirect);
    }
}

/**
 * Get all permissions with labels
 */
function getAllPermissions(): array
{
    return PERMISSIONS;
}

/**
 * Get all roles with their permissions
 */
function getAllRoles(): array
{
    $normalized = [];
    foreach (ROLES as $key => $role) {
        $normalized[strtolower($key)] = $role;
    }
    return $normalized;
}

/**
 * Get role label
 */
function getRoleLabel(string $role): string
{
    $labels = [
        'ceo' => 'Chief Executive Officer',
        'admin' => 'Administrator',
        'manager' => 'Manager',
        'attendant' => 'Attendant',
        'technician' => 'Technician',
    ];

    return $labels[strtolower($role)] ?? ucfirst($role);
}

/**
 * Get role description
 */
function getRoleDescription(string $role): string
{
    $descriptions = [
        'ceo' => 'System administrator with full access and permission management',
        'admin' => 'Full system access',
        'manager' => 'Permissions assigned individually by an administrator',
        'attendant' => 'Permissions assigned individually by an administrator',
        'technician' => 'Permissions assigned individually by an administrator',
    ];

    return $descriptions[strtolower($role)] ?? '';
}

// ============================================================
// SIDEBAR MENU BUILDER
// ============================================================

/**
 * Get sidebar menu items based on user permissions
 */
function getSidebarMenu(): array
{
    $menuItems = [
        // Dashboard
        [
            'id' => 'dashboard',
            'label' => 'Dashboard',
            'icon' => 'fa-chart-line',
            'href' => base_path('index.php'),
            'permission' => 'dashboard.view',
            'badge' => null,
        ],

        // Analytics
        [
            'id' => 'analytics',
            'label' => 'Analytics',
            'icon' => 'fa-chart-pie',
            'href' => base_path('analytics.php'),
            'permission' => 'dashboard.stats',
            'badge' => null,
        ],

        // Customers
        [
            'id' => 'customers',
            'label' => 'Customers',
            'icon' => 'fa-users',
            'href' => base_path('customers.php'),
            'permission' => 'users.view',
            'badge' => null,
            'children' => [
                ['label' => 'All Customers', 'href' => base_path('customers.php'), 'permission' => 'users.view'],
                ['label' => 'Add Customer', 'href' => base_path('customers.php'), 'permission' => 'users.create'],
            ],
        ],

        // Products
        [
            'id' => 'products',
            'label' => 'Products',
            'icon' => 'fa-box',
            'href' => base_path('products.php'),
            'permission' => 'inventory.view',
            'badge' => null,
        ],

        // Orders
        [
            'id' => 'orders',
            'label' => 'Orders',
            'icon' => 'fa-shopping-cart',
            'href' => base_path('orders.php'),
            'permission' => 'dashboard.view',
            'badge' => null,
        ],

        // Invoices
        [
            'id' => 'invoices',
            'label' => 'Invoices',
            'icon' => 'fa-file-invoice',
            'href' => base_path('invoices.php'),
            'permission' => 'billing.view',
            'badge' => null,
        ],

        // Stations
        [
            'id' => 'stations',
            'label' => 'Stations',
            'icon' => 'fa-desktop',
            'href' => base_path('stations.php'),
            'permission' => 'stations.view',
            'badge' => null,
            'children' => [
                ['label' => 'All Stations', 'href' => base_path('stations.php'), 'permission' => 'stations.view'],
                ['label' => 'Device Control', 'href' => base_path('devices.php'), 'permission' => 'stations.control'],
                ['label' => 'Add Station', 'href' => base_path('stations.php'), 'permission' => 'stations.manage'],
            ],
        ],

        // Sessions
        [
            'id' => 'sessions',
            'label' => 'Sessions',
            'icon' => 'fa-clock',
            'href' => base_path('sessions.php'),
            'permission' => 'sessions.view',
            'badge' => null,
            'children' => [
                ['label' => 'Active Sessions', 'href' => base_path('sessions.php'), 'permission' => 'sessions.view'],
                ['label' => 'Session History', 'href' => base_path('session-history.php'), 'permission' => 'sessions.view'],
            ],
        ],

        // Personal Devices (BYOD / Hotspot)
        [
            'id' => 'personal_devices',
            'label' => 'Personal Devices',
            'icon' => 'fa-mobile-alt',
            'href' => base_path('personal-devices.php'),
            'permission' => 'personal_devices.view',
            'badge' => null,
        ],

        // Services
        [
            'id' => 'services',
            'label' => 'Services',
            'icon' => 'fa-concierge-bell',
            'href' => base_path('services.php'),
            'permission' => 'services.view',
            'badge' => null,
            'children' => [
                ['label' => 'All Services', 'href' => base_path('services.php'), 'permission' => 'services.view'],
                ['label' => 'Add Service', 'href' => base_path('services.php'), 'permission' => 'services.create'],
            ],
        ],

        // Print Jobs
        [
            'id' => 'print_jobs',
            'label' => 'Print Jobs',
            'icon' => 'fa-print',
            'href' => base_path('print-jobs.php'),
            'permission' => 'print_jobs.view',
            'badge' => 'print_pending',
            'children' => [
                ['label' => 'All Print Jobs', 'href' => base_path('print-jobs.php'), 'permission' => 'print_jobs.view'],
                ['label' => 'Pending Jobs', 'href' => base_path('pending-jobs.php'), 'permission' => 'print_jobs.manage'],
                ['label' => 'Add Printer', 'href' => base_path('printers.php'), 'permission' => 'printers.create'],
            ],
        ],

        // POS & Finance
        [
            'id' => 'pos',
            'label' => 'POS & Finance',
            'icon' => 'fa-cash-register',
            'href' => base_path('pos.php'),
            'permission' => 'pos.view',
            'badge' => null,
            'children' => [
                ['label' => 'POS Counter', 'href' => base_path('pos.php'), 'permission' => 'pos.view'],
                ['label' => 'Customer Billing', 'href' => base_path('billing.php'), 'permission' => 'billing.view'],
                ['label' => 'Device Usage', 'href' => base_path('admin/device-usage.php'), 'permission' => 'pos.manage'],
                ['label' => 'Audit Trail', 'href' => base_path('admin/audit.php'), 'permission' => 'audit.view'],
            ],
        ],

        // Transactions
        [
            'id' => 'transactions',
            'label' => 'Transactions',
            'icon' => 'fa-money-bill-wave',
            'href' => base_path('transactions.php'),
            'permission' => 'transactions.view',
            'badge' => null,
            'children' => [
                ['label' => 'All Transactions', 'href' => base_path('transactions.php'), 'permission' => 'transactions.view'],
                ['label' => 'Refunds', 'href' => base_path('refunds.php'), 'permission' => 'transactions.refund'],
                ['label' => 'Financial Reports', 'href' => base_path('financial-reports.php'), 'permission' => 'transactions.reports'],
            ],
        ],

        // Inventory
        [
            'id' => 'inventory',
            'label' => 'Inventory',
            'icon' => 'fa-boxes-stacked',
            'href' => base_path('inventory.php'),
            'permission' => 'inventory.view',
            'badge' => 'low_stock',
            'children' => [
                ['label' => 'All Products', 'href' => base_path('inventory.php'), 'permission' => 'inventory.view'],
                ['label' => 'Stock Adjustments', 'href' => base_path('stock-adjustments.php'), 'permission' => 'inventory.adjust'],
                ['label' => 'Low Stock Alerts', 'href' => base_path('low-stock.php'), 'permission' => 'inventory.view'],
            ],
        ],

        // Feedback
        [
            'id' => 'feedback',
            'label' => 'Feedback',
            'icon' => 'fa-comments',
            'href' => base_path('feedback.php'),
            'permission' => 'feedback.view',
            'badge' => 'unresolved',
            'children' => [
                ['label' => 'All Feedback', 'href' => base_path('feedback.php'), 'permission' => 'feedback.view'],
                ['label' => 'Unresolved', 'href' => base_path('unresolved-feedback.php'), 'permission' => 'feedback.respond'],
            ],
        ],

        // Support
        [
            'id' => 'support',
            'label' => 'Support',
            'icon' => 'fa-headset',
            'href' => base_path('support.php'),
            'permission' => 'support.view',
            'badge' => 'open_tickets',
            'children' => [
                ['label' => 'All Tickets', 'href' => base_path('support.php'), 'permission' => 'support.view'],
                ['label' => 'Open Tickets', 'href' => base_path('open-tickets.php'), 'permission' => 'support.manage'],
            ],
        ],

        // Branded Assets
        [
            'id' => 'branded_assets',
            'label' => 'Branded Assets',
            'icon' => 'fa-building-columns',
            'href' => base_path('corporate.php'),
            'permission' => 'branded_assets.view',
            'badge' => null,
        ],

        // Staff Management (Admin only)
        [
            'id' => 'staff',
            'label' => 'Staff Management',
            'icon' => 'fa-user-shield',
            'href' => base_path('staff.php'),
            'permission' => 'staff.view',
            'badge' => null,
            'children' => [
                ['label' => 'All Staff', 'href' => base_path('staff.php'), 'permission' => 'staff.view'],
                ['label' => 'Add Staff', 'href' => base_path('staff.php'), 'permission' => 'staff.create'],
                ['label' => 'Roles & Permissions', 'href' => base_path('admin/roles.php'), 'permission' => 'staff.assign_permissions'],
            ],
        ],

        // Reports (Admin only)
        [
            'id' => 'reports',
            'label' => 'Reports',
            'icon' => 'fa-chart-bar',
            'href' => base_path('admin/reports.php'),
            'permission' => 'reports.view',
            'badge' => null,
        ],

        // Settings
        [
            'id' => 'settings',
            'label' => 'Settings',
            'icon' => 'fa-cog',
            'href' => base_path('settings.php'),
            'permission' => 'settings.view',
            'badge' => null,
        ],

        // Help
        [
            'id' => 'help',
            'label' => 'Help',
            'icon' => 'fa-question-circle',
            'href' => base_path('help.php'),
            'permission' => 'dashboard.view',
            'badge' => null,
        ],
    ];

    // Filter menu items by permissions
    return array_filter($menuItems, function ($item) {
        return hasPermission($item['permission']);
    });
}

/**
 * Get sidebar badge count
 */
function getSidebarBadge(string $badgeType): ?int
{
    switch ($badgeType) {
        case 'print_pending':
            return Database::fetchColumn(
                "SELECT COUNT(*) FROM print_jobs WHERE status = 'queued' OR status = 'printing'"
            );

        case 'low_stock':
            return Database::fetchColumn(
                "SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level AND is_active = 1"
            );

        case 'unresolved':
            return Database::fetchColumn(
                "SELECT COUNT(*) FROM feedback WHERE is_resolved = 0"
            );

        case 'open_tickets':
            return Database::fetchColumn(
                "SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'in_progress', 'waiting_customer')"
            );

        default:
            return null;
    }
}
