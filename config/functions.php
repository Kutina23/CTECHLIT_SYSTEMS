<?php
/**
 * CTECHLIT & SYSTEMS — Helper Functions
 * Common utility functions used throughout the application
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/config.php';

// ============================================================
// INITIALIZATION
// ============================================================

// Set timezone
date_default_timezone_set($GLOBALS['config']['app']['timezone'] ?? 'UTC');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? '1' : '0');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', (string) ($GLOBALS['config']['security']['session_lifetime'] ?? 7200));
    session_start();
}

// ============================================================
// ERROR HANDLING
// ============================================================

/**
 * Custom error handler
 */
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/**
 * Custom exception handler
 */
set_exception_handler(function (Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    
    if ($GLOBALS['config']['app']['debug'] ?? false) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Please try again later.'
        ]);
    }
    exit;
});

// ============================================================
// RESPONSE HELPERS
// ============================================================

/**
 * Send JSON response
 */
if (!function_exists('jsonResponse')) {
    function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

/**
 * Send success response
 * Accepts both message-first calls and legacy data-first API calls.
 */
if (!function_exists('successResponse')) {
    function successResponse(mixed $first = 'Success', mixed $second = [], int $statusCode = 200): void
    {
        $message = 'Success';
        $data = [];

        if (is_array($first) || $first === null) {
            $data = is_array($first) ? $first : [];
            $message = is_string($second) ? $second : 'Success';
        } elseif (is_string($first)) {
            $message = $first;
            $data = is_array($second) ? $second : (is_null($second) ? [] : [$second]);
        } else {
            $data = $first;
            $message = is_string($second) ? $second : 'Success';
        }

        jsonResponse([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }
}

/**
 * Send error response
 */
if (!function_exists('errorResponse')) {
    function errorResponse(string $message = 'Error', int $statusCode = 400, array $errors = []): void
    {
        jsonResponse([
            'success' => false,
            'message' => $message,
            'error' => $message,
            'errors' => $errors
        ], $statusCode);
    }
}

/**
 * Get an absolute URL path relative to the application or portal root.
 */
function base_path(string $path = '', bool $portalRoot = true): string
{
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $scriptFilename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');

    if (
        $documentRoot !== ''
        && strncasecmp($scriptFilename, $documentRoot . '/', strlen($documentRoot) + 1) === 0
    ) {
        $scriptPath = substr($scriptFilename, strlen($documentRoot));
    }

    $scriptPath = parse_url($scriptPath, PHP_URL_PATH) ?: '/';
    $segments = array_values(array_filter(explode('/', trim($scriptPath, '/')), 'strlen'));
    if ($segments !== [] && pathinfo(end($segments), PATHINFO_EXTENSION) !== '') {
        array_pop($segments);
    }

    $portalIndex = array_search('portal', $segments, true);
    if ($portalIndex !== false) {
        $segments = array_slice($segments, 0, $portalRoot ? $portalIndex + 1 : $portalIndex);
    }

    $base = rtrim('/' . implode('/', $segments), '/');
    $path = ltrim($path, '/');

    return $base . '/' . $path;
}

/**
 * Redirect to URL
 */
function redirect(string $url, int $statusCode = 302): void
{
    header("Location: $url", true, $statusCode);
    exit;
}

// ============================================================
// VALIDATION HELPERS
// ============================================================

/**
 * Validate email
 */
function isValidEmail(string $email): bool
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number (Ghana format)
 *
 * Accepted forms after stripping non-digits:
 *   +233241234567
 *   0241234567
 *   241234567
 *
 * Requires exactly 9 digits after optional +233 / 233 / 0 prefix.
 */
function isValidPhone(string $phone): bool
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(?:233|\+233|0)?(24|25|53|54|55|56|57|58|59|30|31|32|33|34|35|36|37|38|39)\d{7}$/', $phone) === 1;
}

/**
 * Validate UUID
 */
function isValidUuid(string $uuid): bool
{
    return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
}

/**
 * Sanitize string input
 */
function sanitize(string $input): string
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Log user and staff activity for audit trails
 */
function logAuditTrail(string $action, string $entityType, ?int $entityId = null, array $details = [], ?int $actorId = null, ?string $actorType = null): void
{
    if (!Database::tableExists('audit_logs')) {
        return;
    }

    $staffId = $actorId ?? ($_SESSION['user_id'] ?? null);
    $userType = $actorType ?? ($_SESSION['user_type'] ?? 'staff');
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    Database::execute(
        "INSERT INTO audit_logs (staff_id, actor_type, action, entity_type, entity_id, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$staffId, $userType, $action, $entityType, $entityId, $detailsJson ?: '{}', $ipAddress]
    );
}

/**
 * Validate required fields
 */
function validateRequired(array $data, array $fields): array
{
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[] = "$field is required";
        }
    }
    return $errors;
}

// ============================================================
// AUTHENTICATION HELPERS
// ============================================================

/**
 * Hash password
 */
function hashPassword(string $password): string
{
    $cost = $GLOBALS['config']['security']['bcrypt_cost'] ?? 12;
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
}

/**
 * Verify password
 */
function verifyPassword(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Generate JWT token
 */
function generateJwt(array $payload, int $expiry = null): string
{
    $secret = $GLOBALS['config']['security']['jwt_secret'] ?? '';
    if (empty($secret)) {
        throw new RuntimeException('JWT secret not configured');
    }

    $expiry = $expiry ?? ($GLOBALS['config']['security']['jwt_expiry'] ?? 86400);
    $issuedAt = time();
    $expire = $issuedAt + $expiry;

    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload['iat'] = $issuedAt;
    $payload['exp'] = $expire;

    $base64UrlHeader = base64UrlEncode(json_encode($header));
    $base64UrlPayload = base64UrlEncode(json_encode($payload));
    $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $secret, true);
    $base64UrlSignature = base64UrlEncode($signature);

    return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
}

/**
 * Verify JWT token
 */
function verifyJwt(string $token): ?array
{
    $secret = $GLOBALS['config']['security']['jwt_secret'] ?? '';
    if (empty($secret)) {
        throw new RuntimeException('JWT secret not configured');
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    list($header, $payload, $signature) = $parts;

    $expectedSignature = hash_hmac('sha256', "$header.$payload", $secret, true);
    $expectedSignature = base64UrlEncode($expectedSignature);

    if (!hash_equals($expectedSignature, $signature)) {
        return null;
    }

    $decoded = json_decode(base64UrlDecode($payload), true);
    if (!$decoded || $decoded['exp'] < time()) {
        return null;
    }

    return $decoded;
}

/**
 * Base64 URL encode
 */
function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/**
 * Base64 URL decode
 */
function base64UrlDecode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Get current authenticated user
 */
function getCurrentUser(): ?array
{
    $token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $token = str_replace('Bearer ', '', $token);
    
    if (empty($token)) {
        return null;
    }

    $payload = verifyJwt($token);
    if (!$payload) {
        return null;
    }

    $userId = $payload['sub'] ?? null;
    if (!$userId) {
        return null;
    }

    return Database::fetchOne(
        "SELECT user_id, email, phone, full_name, membership_tier, is_active FROM users WHERE user_id = ?",
        [$userId]
    );
}

/**
 * Require JWT authentication (for API endpoints)
 */
function requireJwtAuth(): array
{
    $user = getCurrentUser();
    if (!$user || !$user['is_active']) {
        errorResponse('Unauthorized', 401);
    }
    return $user;
}

// ============================================================
// CSRF PROTECTION
// ============================================================

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCsrfToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ============================================================
// INPUT HELPERS
// ============================================================

/**
 * Get input from POST/GET with optional default
 */
function input(string $key, mixed $default = null): mixed
{
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

/**
 * Get all input data
 */
function allInput(): array
{
    return array_merge($_POST, $_GET);
}

/**
 * Get JSON input from request body
 */
function jsonInput(): array
{
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

// ============================================================
// FILE UPLOAD HELPERS
// ============================================================

/**
 * Handle file upload
 */
function uploadFile(array $file, string $directory, array $allowedExtensions = []): array
{
    $config = $GLOBALS['config']['upload'] ?? [];
    $maxSize = $config['max_size'] ?? 10485760;
    $allowedExtensions = $allowedExtensions ?: $config['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'pdf'];

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file uploaded'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File too large'];
        default:
            return ['success' => false, 'error' => 'Unknown upload error'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File exceeds maximum size'];
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions, true)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }

    // Create directory if not exists
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        return ['success' => false, 'error' => 'Failed to create upload directory'];
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = rtrim($directory, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to move uploaded file'];
    }

    return [
        'success' => true,
        'filename' => $filename,
        'path' => $destination,
        'size' => $file['size'],
        'extension' => $extension
    ];
}

// ============================================================
// DATE/TIME HELPERS
// ============================================================

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'Y-m-d H:i:s'): string
{
    return date($format, strtotime($date));
}

/**
 * Get relative time (e.g., "2 hours ago")
 */
function relativeTime(string $datetime): string
{
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return "$minutes minute" . ($minutes > 1 ? 's' : '') . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return "$hours hour" . ($hours > 1 ? 's' : '') . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return "$days day" . ($days > 1 ? 's' : '') . " ago";
    }

    return date('M j, Y', $timestamp);
}

/**
 * Start of day timestamp
 */
function startOfDay(string $date = null): int
{
    $date = $date ?: date('Y-m-d');
    return strtotime($date . ' 00:00:00');
}

/**
 * End of day timestamp
 */
function endOfDay(string $date = null): int
{
    $date = $date ?: date('Y-m-d');
    return strtotime($date . ' 23:59:59');
}

// ============================================================
// PAGINATION HELPERS
// ============================================================

/**
 * Get pagination parameters
 */
function getPaginationParams(): array
{
    $page = max(1, (int) (input('page', 1)));
    $limit = min(100, max(1, (int) (input('limit', 20))));
    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

/**
 * Build pagination response
 */
function paginationResponse(array $items, int $total, int $page, int $limit): array
{
    $totalPages = (int) ceil($total / $limit);

    return [
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ];
}

// ============================================================
// LOGGING HELPERS
// ============================================================

/**
 * Log message to file
 */
function logMessage(string $level, string $message, array $context = []): void
{
    $logPath = $GLOBALS['config']['logging']['path'] ?? 'logs/';
    $logFile = $logPath . date('Y-m-d') . '.log';
    
    if (!is_dir($logPath) && !mkdir($logPath, 0755, true) && !is_dir($logPath)) {
        return;
    }

    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $line = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;

    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Log info message
 */
function logInfo(string $message, array $context = []): void
{
    logMessage('INFO', $message, $context);
}

/**
 * Log error message
 */
function logError(string $message, array $context = []): void
{
    logMessage('ERROR', $message, $context);
}

/**
 * Log warning message
 */
function logWarning(string $message, array $context = []): void
{
    logMessage('WARNING', $message, $context);
}

// ============================================================
// CACHING HELPERS
// ============================================================

/**
 * Get cached value
 */
function cacheGet(string $key, mixed $default = null): mixed
{
    $driver = $GLOBALS['config']['cache']['driver'] ?? 'file';
    
    if ($driver === 'redis') {
        try {
            $redis = new Redis();
            $redis->connect(
                $GLOBALS['config']['cache']['host'] ?? '127.0.0.1',
                $GLOBALS['config']['cache']['port'] ?? 6379
            );
            $value = $redis->get($key);
            return $value !== false ? json_decode($value, true) : $default;
        } catch (Exception $e) {
            return $default;
        }
    }

    // File-based cache fallback
    $cacheDir = __DIR__ . '/../cache/';
    $cacheFile = $cacheDir . md5($key) . '.cache';

    if (!file_exists($cacheFile)) {
        return $default;
    }

    $data = file_get_contents($cacheFile);
    $cached = json_decode($data, true);

    if ($cached && $cached['expires'] > time()) {
        return $cached['value'];
    }

    @unlink($cacheFile);
    return $default;
}

/**
 * Set cached value
 */
function cacheSet(string $key, mixed $value, int $ttl = null): bool
{
    $ttl = $ttl ?? ($GLOBALS['config']['cache']['ttl'] ?? 3600);
    $driver = $GLOBALS['config']['cache']['driver'] ?? 'file';

    if ($driver === 'redis') {
        try {
            $redis = new Redis();
            $redis->connect(
                $GLOBALS['config']['cache']['host'] ?? '127.0.0.1',
                $GLOBALS['config']['cache']['port'] ?? 6379
            );
            return $redis->setex($key, $ttl, json_encode($value));
        } catch (Exception $e) {
            return false;
        }
    }

    // File-based cache fallback
    $cacheDir = __DIR__ . '/../cache/';
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0755, true) && !is_dir($cacheDir)) {
        return false;
    }

    $cacheFile = $cacheDir . md5($key) . '.cache';
    $data = json_encode([
        'value' => $value,
        'expires' => time() + $ttl
    ]);

    return file_put_contents($cacheFile, $data, LOCK_EX) !== false;
}

/**
 * Delete cached value
 */
function cacheDelete(string $key): bool
{
    $driver = $GLOBALS['config']['cache']['driver'] ?? 'file';

    if ($driver === 'redis') {
        try {
            $redis = new Redis();
            $redis->connect(
                $GLOBALS['config']['cache']['host'] ?? '127.0.0.1',
                $GLOBALS['config']['cache']['port'] ?? 6379
            );
            return $redis->del($key) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    $cacheDir = __DIR__ . '/../cache/';
    $cacheFile = $cacheDir . md5($key) . '.cache';
    return @unlink($cacheFile);
}

// ============================================================
// SECURITY HELPERS
// ============================================================

/**
 * Generate random string
 */
function randomString(int $length = 32): string
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Generate API key
 */
function generateApiKey(): string
{
    return 'ctk_' . randomString(40);
}

/**
 * Hash API key for storage
 */
function hashApiKey(string $apiKey): string
{
    return hash('sha256', $apiKey);
}

/**
 * Verify API key
 */
function verifyApiKey(string $apiKey, string $hash): bool
{
    return hash_equals($hash, hashApiKey($apiKey));
}

/**
 * Rate limiting check
 */
function checkRateLimit(string $key, int $maxRequests = 60, int $windowSeconds = 60): bool
{
    $cacheKey = "rate_limit:$key";
    $data = cacheGet($cacheKey, ['count' => 0, 'window_start' => time()]);

    $now = time();
    if ($now - $data['window_start'] >= $windowSeconds) {
        // Reset window
        cacheSet($cacheKey, ['count' => 1, 'window_start' => $now], $windowSeconds);
        return true;
    }

    if ($data['count'] >= $maxRequests) {
        return false;
    }

    cacheSet($cacheKey, [
        'count' => $data['count'] + 1,
        'window_start' => $data['window_start']
    ], $windowSeconds - ($now - $data['window_start']));

    return true;
}

// ============================================================
// UTILITY HELPERS
// ============================================================

/**
 * Get client IP address
 */
function getClientIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ips = explode(',', $_SERVER[$header]);
            return trim($ips[0]);
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Get user agent
 */
function getUserAgent(): string
{
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Format currency
 */
function formatCurrency(float $amount, string $currency = 'USD'): string
{
    $symbols = [
        'USD' => '$',
        'KES' => 'KSh ',
        'EUR' => '€',
        'GBP' => '£'
    ];

    $symbol = $symbols[$currency] ?? $currency . ' ';
    return $symbol . number_format($amount, 2);
}

/**
 * Truncate string
 */
function truncate(string $string, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($string) <= $length) {
        return $string;
    }
    return mb_substr($string, 0, $length) . $suffix;
}

/**
 * Generate slug from string
 */
function slugify(string $string): string
{
    $string = transliterator_transliterate('Any-Latin; Latin-ASCII', $string);
    $string = preg_replace('/[^a-z0-9]+/i', '-', strtolower($string));
    $string = trim($string, '-');
    return $string ?: 'n-a';
}

// ============================================================
// DATABASE HELPERS
// ============================================================

/**
 * Find record by ID
 */
function findById(string $table, int $id): ?array
{
    $sql = "SELECT * FROM " . Database::escapeIdentifier($table) . " WHERE id = ?";
    return Database::fetchOne($sql, [$id]);
}

/**
 * Insert record and return ID
 */
function insertRecord(string $table, array $data): int
{
    $columns = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $columnList = implode(',', array_map([Database::class, 'escapeIdentifier'], $columns));

    $sql = "INSERT INTO " . Database::escapeIdentifier($table) . " ($columnList) VALUES ($placeholders)";
    Database::query($sql, array_values($data));

    return (int) Database::lastInsertId();
}

/**
 * Update record by ID
 */
function updateRecord(string $table, int $id, array $data): bool
{
    if (empty($data)) {
        return false;
    }

    $setParts = [];
    foreach (array_keys($data) as $column) {
        $setParts[] = Database::escapeIdentifier($column) . " = ?";
    }

    $sql = "UPDATE " . Database::escapeIdentifier($table) . " SET " . implode(', ', $setParts) . " WHERE id = ?";
    $params = array_values($data);
    $params[] = $id;

    return Database::execute($sql, $params) > 0;
}

/**
 * Delete record by ID
 */
function deleteRecord(string $table, int $id): bool
{
    $sql = "DELETE FROM " . Database::escapeIdentifier($table) . " WHERE id = ?";
    return Database::execute($sql, [$id]) > 0;
}

// ============================================================
// PORTAL DATA HELPERS
// ============================================================

/**
 * Get portal summary stats from database tables.
 */
function portalSummaryStats(): array
{
    $totalRevenue = (float) (Database::fetchColumn("SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE status = 'completed'") ?? 0);
    $completedOrders = (int) (Database::fetchColumn("SELECT COUNT(*) FROM transactions WHERE status = 'completed'") ?? 0);
    $totalOrders = (int) (Database::fetchColumn("SELECT COUNT(*) FROM transactions") ?? 0);
    $pendingOrders = (int) (Database::fetchColumn("SELECT COUNT(*) FROM transactions WHERE status = 'pending'") ?? 0);
    $totalCustomers = (int) (Database::fetchColumn("SELECT COUNT(*) FROM users WHERE is_active = 1") ?? 0);
    $newCustomers = (int) (Database::fetchColumn("SELECT COUNT(*) FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')") ?? 0);
    $totalStaff = (int) (Database::fetchColumn("SELECT COUNT(*) FROM staff WHERE is_active = 1") ?? 0);
    $activeStations = (int) (Database::fetchColumn("SELECT COUNT(*) FROM stations WHERE status = 'occupied' OR status = 'available'") ?? 0);
    $activeSessions = (int) (Database::fetchColumn("SELECT COUNT(*) FROM sessions WHERE status = 'active'") ?? 0);
    $avgOrderValue = $completedOrders > 0 ? $totalRevenue / $completedOrders : 0.0;
    $conversionRate = $totalCustomers > 0 ? (($completedOrders / $totalCustomers) * 100) : 0.0;

    return [
        'total_revenue' => $totalRevenue,
        'total_orders' => $totalOrders,
        'completed_orders' => $completedOrders,
        'pending_orders' => $pendingOrders,
        'total_customers' => $totalCustomers,
        'new_customers' => $newCustomers,
        'total_staff' => $totalStaff,
        'active_stations' => $activeStations,
        'active_sessions' => $activeSessions,
        'avg_order_value' => $avgOrderValue,
        'conversion_rate' => $conversionRate,
    ];
}

/**
 * Get products table data.
 */
function portalProducts(): array
{
    return Database::fetchAll(
        "SELECT product_id, name, category, selling_price, stock_quantity, min_stock_level,
                CASE WHEN stock_quantity <= min_stock_level THEN 'Low Stock' WHEN stock_quantity = 0 THEN 'Out of Stock' ELSE 'In Stock' END AS stock_status
         FROM products WHERE is_active = 1 ORDER BY stock_quantity ASC, name ASC LIMIT 20"
    );
}

/**
 * Get top selling products by revenue.
 */
function portalTopProducts(int $limit = 10): array
{
    return Database::fetchAll(
        "SELECT p.product_id, p.name, p.category, p.selling_price,
                COALESCE(SUM(ti.quantity), 0) AS total_sold,
                COALESCE(SUM(ti.quantity * ti.unit_price), 0) AS total_revenue
         FROM products p
         LEFT JOIN transaction_items ti ON ti.service_id = p.product_id
         LEFT JOIN transactions t ON t.transaction_id = ti.transaction_id AND t.status = 'completed'
         WHERE p.is_active = 1
         GROUP BY p.product_id, p.name, p.category, p.selling_price
         ORDER BY total_revenue DESC, total_sold DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Get customers ranked by spend, then by order count.
 */
function portalCustomers(): array
{
    return Database::fetchAll(
        "SELECT u.user_id, u.full_name, u.email, u.membership_tier, u.total_spent, u.total_visits, u.last_login_at, u.is_active,
                (SELECT COUNT(*) FROM transactions t WHERE t.user_id = u.user_id) AS order_count
         FROM users u
         ORDER BY u.total_spent DESC, order_count DESC, u.last_login_at DESC"
    );
}

/**
 * Get recent orders list.
 */
function portalOrders(): array
{
    return Database::fetchAll(
        "SELECT t.transaction_id, u.full_name AS customer_name, t.total_amount, t.created_at, t.status,
                (SELECT COUNT(*) FROM transaction_items ti WHERE ti.transaction_id = t.transaction_id) AS item_count,
                COALESCE(t.receipt_number, CONCAT('#TXN-', t.transaction_id)) AS order_number
         FROM transactions t
         LEFT JOIN users u ON u.user_id = t.user_id
         ORDER BY t.created_at DESC
         LIMIT 10"
    );
}

/**
 * Get all transactions with type information for the transactions page.
 */
function portalTransactions(int $limit = 50): array
{
    $refundJoin = '';
    $refundSelect = '0.00 AS refunded_amount, t.total_amount AS refundable_amount';
    if (refundsTableExists()) {
        $refundJoin = "LEFT JOIN (SELECT transaction_id, SUM(amount) AS refunded FROM transaction_refunds GROUP BY transaction_id) rf ON rf.transaction_id = t.transaction_id";
        $refundSelect = "COALESCE(rf.refunded, 0) AS refunded_amount,
                         GREATEST(t.total_amount - COALESCE(rf.refunded, 0), 0) AS refundable_amount";
    }

    return Database::fetchAll(
        "SELECT t.transaction_id,
                u.full_name AS customer_name,
                t.total_amount,
                t.created_at,
                t.status,
                t.receipt_number,
                {$refundSelect},
                CASE
                    WHEN t.status = 'refunded' OR t.status = 'partially_refunded' THEN 'Refund'
                    WHEN ti.service_id IS NOT NULL THEN 'Purchase'
                    ELSE 'Top-up'
                END AS type_name
         FROM transactions t
         LEFT JOIN users u ON u.user_id = t.user_id
         LEFT JOIN transaction_items ti ON ti.transaction_id = t.transaction_id
         {$refundJoin}
         GROUP BY t.transaction_id, u.full_name, t.total_amount, t.created_at, t.status, t.receipt_number, rf.refunded
         ORDER BY t.created_at DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Refund/revenue totals used by the transactions page.
 */
function portalRefundTotals(): array
{
    $gross = (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE status IN ('completed', 'partially_refunded', 'refunded')"
    );
    $pending = (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE status = 'pending'"
    );
    $refundedTotal = refundsTableExists()
        ? (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM transaction_refunds")
        : 0.0;

    return [
        'gross_revenue' => $gross,
        'refunded_total' => $refundedTotal,
        'net_revenue' => max(0.0, round($gross - $refundedTotal, 2)),
        'pending_amount' => $pending,
        'refund_count' => refundsTableExists()
            ? (int) Database::fetchColumn("SELECT COUNT(*) FROM transaction_refunds")
            : 0,
    ];
}

/**
 * Get recent invoices from completed transactions.
 */
function portalInvoices(): array
{
    return Database::fetchAll(
        "SELECT t.transaction_id, u.full_name AS customer_name, t.total_amount, t.created_at,
                DATE_ADD(t.created_at, INTERVAL 14 DAY) AS due_date, t.status,
                COALESCE(t.receipt_number, CONCAT('INV-', t.transaction_id)) AS invoice_number
         FROM transactions t
         LEFT JOIN users u ON u.user_id = t.user_id
         ORDER BY t.created_at DESC
         LIMIT 10"
    );
}

/**
 * Get revenue data for the last N days.
 */
function portalRevenueSeries(int $days = 7): array
{
    $rows = Database::fetchAll(
        "SELECT DATE(created_at) AS label,
                COALESCE(SUM(total_amount), 0) AS revenue,
                COUNT(*) AS orders
         FROM transactions
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(created_at)
         ORDER BY DATE(created_at)",
        [$days]
    );

    $labels = [];
    $revenues = [];
    $orders = [];
    foreach ($rows as $row) {
        $labels[] = date('D', strtotime($row['label']));
        $revenues[] = (float) ($row['revenue'] ?? 0);
        $orders[] = (int) ($row['orders'] ?? 0);
    }

    return [
        'labels' => $labels,
        'revenue' => $revenues,
        'orders' => $orders,
    ];
}

/**
 * Get revenue data for the last N months.
 */
function portalMonthlyRevenueSeries(int $months = 6): array
{
    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(created_at, '%b') AS label,
                COALESCE(SUM(total_amount), 0) AS revenue
         FROM transactions
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
         GROUP BY YEAR(created_at), MONTH(created_at)
         ORDER BY YEAR(created_at), MONTH(created_at)",
        [$months]
    );

    $labels = [];
    $revenues = [];
    foreach ($rows as $row) {
        $labels[] = $row['label'];
        $revenues[] = (float) ($row['revenue'] ?? 0);
    }

    return ['labels' => $labels, 'revenue' => $revenues];
}

/**
 * Get category breakdown for revenue chart.
 */
function portalCategoryBreakdown(): array
{
    $rows = Database::fetchAll(
        "SELECT p.category, COALESCE(SUM(t.total_amount), 0) AS revenue
         FROM products p
         LEFT JOIN transaction_items ti ON ti.service_id = p.product_id
         LEFT JOIN transactions t ON t.transaction_id = ti.transaction_id
         GROUP BY p.category
         ORDER BY revenue DESC"
    );

    $labels = [];
    $values = [];
    foreach ($rows as $row) {
        $labels[] = ucfirst((string) ($row['category'] ?? 'Other'));
        $values[] = (float) ($row['revenue'] ?? 0);
    }

    return ['labels' => $labels, 'values' => $values];
}

/**
 * Get recent activity feed.
 */
function portalRecentActivity(): array
{
    return Database::fetchAll(
        "SELECT u.full_name, t.total_amount, t.created_at, t.status, 'transaction' AS activity_type,
                CONCAT('Payment received from ', u.full_name) AS summary
         FROM transactions t
         LEFT JOIN users u ON u.user_id = t.user_id
         WHERE t.status = 'completed'
         ORDER BY t.created_at DESC
         LIMIT 4"
    );
}

/**
 * Get current staff dashboard stats.
 */
function portalStaffDashboardStats(): array
{
    $staffId = $_SESSION['user_id'] ?? 0;

    return [
        'tasks_total' => (int) (Database::fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE assigned_to = ?", [$staffId]) ?? 0),
        'tasks_completed' => (int) (Database::fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE assigned_to = ? AND status = 'resolved'", [$staffId]) ?? 0),
        'tasks_pending' => (int) (Database::fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE assigned_to = ? AND status IN ('open', 'in_progress', 'waiting_customer')", [$staffId]) ?? 0),
        'tasks_overdue' => (int) (Database::fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE assigned_to = ? AND status IN ('open', 'in_progress') AND created_at < DATE_SUB(NOW(), INTERVAL 3 DAY)", [$staffId]) ?? 0),
        'latest_tasks' => Database::fetchAll("SELECT ticket_id, subject, priority, status, created_at FROM support_tickets WHERE assigned_to = ? ORDER BY created_at DESC LIMIT 5", [$staffId]),
    ];
}

/**
 * Get current staff session records.
 */
function portalStaffSessions(): array
{
    $staffId = $_SESSION['user_id'] ?? 0;

    return Database::fetchAll(
        "SELECT s.session_id, s.started_at, s.status, u.full_name AS user_name, st.station_code, st.name AS station_name,
                TIMESTAMPDIFF(MINUTE, s.started_at, NOW()) AS elapsed_minutes
         FROM sessions s
         JOIN users u ON u.user_id = s.user_id
         JOIN stations st ON st.station_id = s.station_id
         WHERE s.staff_id = ?
         ORDER BY s.started_at DESC
         LIMIT 10",
        [$staffId]
    );
}

/**
 * Get current staff transaction history.
 */
function portalStaffTransactions(): array
{
    $staffId = $_SESSION['user_id'] ?? 0;

    return Database::fetchAll(
        "SELECT t.transaction_id, t.total_amount, t.status, t.created_at, 'Commission' AS type_name, u.full_name AS customer_name
         FROM transactions t
         LEFT JOIN users u ON u.user_id = t.user_id
         WHERE t.user_id IN (
             SELECT user_id FROM sessions WHERE staff_id = ?
         )
         ORDER BY t.created_at DESC
         LIMIT 10",
        [$staffId]
    );
}

/**
 * Get recent admin activity (staff actions).
 */
function portalAdminActivity(int $limit = 10): array
{
    return Database::fetchAll(
        "SELECT 
            s.staff_id,
            s.full_name AS staff_name,
            s.role,
            s.created_at,
            'created' AS action_type,
            CONCAT('Staff account created: ', s.full_name) AS summary
         FROM staff s
         UNION ALL
         SELECT 
            s.staff_id,
            s.full_name AS staff_name,
            s.role,
            s.updated_at AS created_at,
            'updated' AS action_type,
            CONCAT('Staff account updated: ', s.full_name) AS summary
         FROM staff s
         WHERE s.updated_at > s.created_at
         ORDER BY created_at DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Get support tickets with user details.
 */
function supportTicketRequesterNameSql(): string
{
    $hasRequesterName = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = 'support_tickets'
           AND column_name = 'requester_name'"
    ) > 0;

    return $hasRequesterName
        ? 'COALESCE(st.requester_name, u.full_name)'
        : 'u.full_name';
}

function portalSupportTickets(int $limit = 50): array
{
    $requesterNameExpression = supportTicketRequesterNameSql();

    return Database::fetchAll(
        "SELECT st.ticket_id, st.subject, st.priority, st.status, st.category, st.created_at,
                {$requesterNameExpression} AS requestor_name,
                a.full_name AS assignee_name
         FROM support_tickets st
         LEFT JOIN users u ON u.user_id = st.user_id
         LEFT JOIN staff a ON a.staff_id = st.assigned_to
         ORDER BY st.created_at DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Get inventory products with stock status.
 */
function portalInventory(int $limit = 50): array
{
    return Database::fetchAll(
        "SELECT p.product_id, p.name, p.category, p.sku, p.stock_quantity, p.min_stock_level, p.selling_price, p.is_active,
                CASE 
                    WHEN p.stock_quantity <= 0 THEN 'Out of Stock'
                    WHEN p.stock_quantity <= p.min_stock_level THEN 'Low Stock'
                    ELSE 'Healthy'
                END AS stock_status,
                (p.stock_quantity * p.cost_price) AS inventory_value
         FROM products p
         WHERE p.is_active = 1
         ORDER BY p.stock_quantity ASC, p.name ASC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Get feedback with user details.
 */
function portalFeedback(int $limit = 50): array
{
    return Database::fetchAll(
        "SELECT f.feedback_id, f.rating, f.category, f.comment, f.is_resolved, f.created_at,
                COALESCE(u.full_name, f.customer_name) AS user_name,
                s.full_name AS staff_name
         FROM feedback f
         LEFT JOIN users u ON u.user_id = f.user_id
         LEFT JOIN staff s ON s.staff_id = f.resolved_by
         ORDER BY f.created_at DESC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Get print jobs with user, station, and printer details.
 */
function portalPrintJobs(int $limit = 50, ?string $status = null): array
{
    $where = '';
    $params = [];
    if ($status !== null) {
        $where = 'WHERE pj.status = ?';
        $params[] = $status;
    }
    $params[] = $limit;

    return Database::fetchAll(
        "SELECT pj.print_job_id, pj.pages, pj.copies, pj.color_mode, pj.duplex, pj.status, pj.priority, pj.error_message,
                pj.started_at, pj.completed_at, pj.created_at,
                u.full_name AS user_name,
                st.station_code,
                pr.name AS printer_name,
                pr.model AS printer_model,
                pr.config AS printer_config
         FROM print_jobs pj
         LEFT JOIN users u ON u.user_id = pj.user_id
         LEFT JOIN stations st ON st.station_id = pj.station_id
         LEFT JOIN peripherals pr ON pr.peripheral_id = pj.peripheral_id
         $where
         ORDER BY pj.created_at DESC
         LIMIT ?",
        $params
    );
}

/**
 * Get printers with IP/MAC for connection.
 */
function portalPrinters(int $limit = 50): array
{
    return Database::fetchAll(
        "SELECT p.peripheral_id, p.name, p.model, p.serial_number, p.station_id, p.status, p.config,
                s.station_code, s.name AS station_name
         FROM peripherals p
         LEFT JOIN stations s ON s.station_id = p.station_id
         WHERE p.peripheral_type = 'printer'
         ORDER BY p.name ASC
         LIMIT ?",
        [$limit]
    );
}

/**
 * Get printer network config (IP, MAC, protocol).
 */
function getPrinterConfig(int $peripheralId): ?array
{
    $printer = Database::fetchOne(
        "SELECT config FROM peripherals WHERE peripheral_id = ? AND peripheral_type = 'printer'",
        [$peripheralId]
    );
    if (!$printer || empty($printer['config'])) {
        return null;
    }
    $config = json_decode($printer['config'], true);
    return is_array($config) ? $config : null;
}

/**
 * Set printer network config (IP, MAC, protocol).
 */
function setPrinterConfig(int $peripheralId, string $ip, string $mac = '', string $protocol = 'ipp'): bool
{
    $existingConfig = getPrinterConfig($peripheralId) ?? [];
    $config = json_encode([
        'ip' => $ip,
        'mac' => $mac !== '' ? $mac : ($existingConfig['mac'] ?? null),
        'protocol' => $protocol,
    ]);
    $updated = Database::execute(
        "UPDATE peripherals SET config = ?, updated_at = NOW() WHERE peripheral_id = ?",
        [$config, $peripheralId]
    );
    if ($updated > 0) {
        return true;
    }

    $savedConfig = getPrinterConfig($peripheralId);
    return $savedConfig !== null
        && ($savedConfig['ip'] ?? null) === $ip
        && ($savedConfig['mac'] ?? null) === ($mac !== '' ? $mac : ($existingConfig['mac'] ?? null))
        && ($savedConfig['protocol'] ?? 'ipp') === $protocol;
}

/**
 * Test printer connection.
 */
function testPrinterConnection(int $peripheralId): array
{
    $config = getPrinterConfig($peripheralId);
    if (!$config || empty($config['ip'])) {
        return ['success' => false, 'status' => 'not_configured', 'message' => 'Enter the printer IP address to configure its connection.'];
    }

    $ip = $config['ip'];
    $protocol = $config['protocol'] ?? 'ipp';
    $port = $protocol === 'lpd' ? 515 : 631;

    // Direct socket check on the café LAN, or relayed through a café agent when hosted online.
    require_once __DIR__ . '/../includes/device-control.php';
    $probe = probeDevice($ip, [$port, 9100]);
    if (!empty($probe['unknown'])) {
        return ['success' => false, 'status' => 'unknown', 'message' => $probe['message']];
    }
    $errstr = $probe['message'];

    if ($probe['online']) {
        // Update status to online
        Database::execute(
            "UPDATE peripherals SET status = 'online', updated_at = NOW() WHERE peripheral_id = ?",
            [$peripheralId]
        );
        return ['success' => true, 'status' => 'online', 'message' => "Printer reachable at $ip:$port"];
    } else {
        // Update status to offline
        Database::execute(
            "UPDATE peripherals SET status = 'offline', updated_at = NOW() WHERE peripheral_id = ?",
            [$peripheralId]
        );
        return ['success' => false, 'status' => 'offline', 'message' => "Cannot connect to printer at $ip:$port ($errstr)"];
    }
}

/**
 * Wake printer via Wake-on-LAN.
 */
function wakePrinter(int $peripheralId): array
{
    $config = getPrinterConfig($peripheralId);
    if (!$config || empty($config['mac'])) {
        return ['success' => false, 'message' => 'Printer not configured with MAC address'];
    }

    require_once __DIR__ . '/../includes/device-control.php';
    return wakeDevice($config['mac'], $config['ip'] ?? null);
}

// ============================================================
// PUBLIC SITE (LANDING PAGE) DATA HELPERS
// Used by index.php — every helper degrades gracefully so the
// page still renders when the database is unreachable.
// ============================================================

/**
 * Stagger delay class for reveal animations (--i is set in CSS, not inline).
 */
function staggerClass(int $index): string
{
    return 'd' . max(0, min(7, $index));
}

/**
 * All public settings as a key => value map.
 */
function siteSettings(): array
{
    static $settings = null;

    if ($settings !== null) {
        return $settings;
    }

    try {
        $rows = Database::fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE is_public = 1");
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Throwable $e) {
        logWarning('Unable to load public settings: ' . $e->getMessage());
        $settings = [];
    }

    return $settings;
}

/**
 * Single public setting with fallback.
 */
function siteSetting(string $key, string $default = ''): string
{
    $settings = siteSettings();
    $value = $settings[$key] ?? null;
    return ($value === null || $value === '') ? $default : (string) $value;
}

/**
 * Resolve the icon classes for a service, falling back to its category.
 */
function siteServiceIcon(?string $icon, string $category): string
{
    $icon = trim((string) $icon);
    if ($icon !== '' && preg_match('/^[a-z0-9\- ]+$/i', $icon)) {
        return $icon;
    }

    $byCategory = [
        'internet' => 'fa-solid fa-globe',
        'printing' => 'fa-solid fa-print',
        'scanning' => 'fa-solid fa-file-lines',
        'gaming' => 'fa-solid fa-gamepad',
        'typing' => 'fa-solid fa-pen-to-square',
        'mobile_money' => 'fa-solid fa-money-bill',
        'consultation' => 'fa-solid fa-headset',
    ];

    return $byCategory[$category] ?? 'fa-solid fa-circle-info';
}

/**
 * Human label for a service price unit.
 */
function sitePriceUnitLabel(string $unit): string
{
    $labels = [
        'per_hour' => 'per hour',
        'per_minute' => 'per minute',
        'per_page' => 'per page',
        'per_job' => 'per job',
        'fixed' => 'flat rate',
    ];

    return $labels[$unit] ?? 'per job';
}

/**
 * Human label for a plan duration (in minutes).
 */
function siteDurationLabel(int $minutes): string
{
    if ($minutes <= 0) {
        return 'per pack';
    }
    if ($minutes % 10080 === 0) {
        $weeks = intdiv($minutes, 10080);
        return $weeks === 1 ? 'per week' : "per $weeks weeks";
    }
    if ($minutes % 1440 === 0) {
        $days = intdiv($minutes, 1440);
        return $days === 1 ? 'per day' : "per $days days";
    }
    if ($minutes % 60 === 0) {
        $hours = intdiv($minutes, 60);
        return $hours === 1 ? 'per hour' : "per $hours hours";
    }

    return $minutes . ' minutes';
}

/**
 * Format an amount with the configured currency symbol.
 * Whole amounts stay clean: GHS 12, GHS 0.50.
 */
function sitePrice(float $amount): string
{
    $symbol = siteSetting('currency_symbol', 'GHS');
    $decimals = fmod($amount, 1.0) > 0.0 ? 2 : 0;

    return $symbol . ' ' . number_format($amount, $decimals);
}

/**
 * Active services for the services and pricing sections.
 */
function siteServices(): array
{
    try {
        $rows = Database::fetchAll(
            "SELECT service_id, name, category, description, icon, base_price, price_unit, requires_station, estimated_duration
             FROM services
             WHERE is_active = 1
             ORDER BY service_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load services: ' . $e->getMessage());
        return [];
    }

    foreach ($rows as &$row) {
        $row['icon'] = siteServiceIcon($row['icon'] ?? null, (string) $row['category']);
        $row['base_price'] = (float) $row['base_price'];
        $row['price_label'] = sitePrice($row['base_price']);
        $row['unit_label'] = sitePriceUnitLabel((string) $row['price_unit']);
    }
    unset($row);

    return $rows;
}

/**
 * Active pricing plans, flagged when they include a gaming service.
 */
function sitePricingPlans(): array
{
    try {
        $rows = Database::fetchAll(
            "SELECT p.plan_id, p.name, p.description, p.price, p.duration_minutes, p.service_ids
             FROM pricing_plans p
             WHERE p.is_active = 1
             ORDER BY p.sort_order ASC, p.plan_id ASC"
        );
        $gamingIds = array_column(
            Database::fetchAll("SELECT service_id FROM services WHERE is_active = 1 AND category = 'gaming'"),
            'service_id'
        );
    } catch (Throwable $e) {
        logWarning('Unable to load pricing plans: ' . $e->getMessage());
        return [];
    }

    foreach ($rows as &$row) {
        $row['price'] = (float) $row['price'];
        $row['price_label'] = sitePrice($row['price']);
        $row['duration_minutes'] = (int) $row['duration_minutes'];
        $row['unit_label'] = siteDurationLabel($row['duration_minutes']);
        $included = json_decode((string) $row['service_ids'], true);
        $row['service_ids'] = is_array($included) ? array_map('intval', $included) : [];
        $row['is_gaming'] = count(array_intersect($row['service_ids'], $gamingIds)) > 0;
    }
    unset($row);

    return $rows;
}

/**
 * All stations with decoded specs.
 */
function siteStations(): array
{
    try {
        $rows = Database::fetchAll(
            "SELECT station_id, station_code, name, location, status, specs, ip_address, last_heartbeat
             FROM stations
             ORDER BY station_code ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load stations: ' . $e->getMessage());
        return [];
    }

    foreach ($rows as &$row) {
        $specs = json_decode((string) $row['specs'], true);
        $row['specs'] = is_array($specs) ? $specs : [];
        $row['is_gaming'] = stripos((string) $row['location'], 'gaming') !== false
            || stripos((string) $row['name'], 'gaming') !== false;
    }
    unset($row);

    return $rows;
}

/**
 * Station counts used by the hero stats, gaming stats and live badges.
 */
function siteStationStats(): array
{
    $stats = [
        'total' => 0,
        'available' => 0,
        'occupied' => 0,
        'maintenance' => 0,
        'offline' => 0,
        'online' => 0,
        'gaming' => 0,
        'client' => 0,
    ];

    try {
        $rows = Database::fetchAll("SELECT status, COUNT(*) AS total FROM stations GROUP BY status");
    } catch (Throwable $e) {
        logWarning('Unable to load station stats: ' . $e->getMessage());
        return $stats;
    }

    foreach ($rows as $row) {
        $status = (string) $row['status'];
        if (isset($stats[$status])) {
            $stats[$status] = (int) $row['total'];
        }
        $stats['total'] += (int) $row['total'];
    }

    try {
        $stats['gaming'] = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM stations
             WHERE LOWER(name) LIKE '%gaming%' OR LOWER(location) LIKE '%gaming%'"
        ) ?? 0);
    } catch (Throwable $e) {
        logWarning('Unable to count gaming stations: ' . $e->getMessage());
    }

    $stats['online'] = $stats['total'] - $stats['offline'];
    $stats['client'] = $stats['total'] - $stats['gaming'];

    return $stats;
}

/**
 * Live figures shown in the hero orbit cards and social proof.
 */
function siteLiveStats(): array
{
    $live = [
        'active_sessions' => 0,
        'customers' => 0,
        'rating' => null,
        'printers_online' => 0,
        'latest_print_job' => null,
    ];

    try {
        $live['active_sessions'] = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM sessions WHERE status IN ('active', 'paused')"
        ) ?? 0);
        $live['customers'] = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM users WHERE is_active = 1"
        ) ?? 0);
        $live['rating'] = Database::fetchColumn("SELECT AVG(rating) FROM feedback");
        $live['printers_online'] = (int) (Database::fetchColumn(
            "SELECT COUNT(*) FROM peripherals WHERE peripheral_type = 'printer' AND status = 'online'"
        ) ?? 0);
        $live['latest_print_job'] = Database::fetchOne(
            "SELECT pj.file_name, pj.pages, pj.copies, pj.color_mode, pj.status, st.station_code
             FROM print_jobs pj
             LEFT JOIN stations st ON st.station_id = pj.station_id
             ORDER BY pj.created_at DESC
             LIMIT 1"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load live stats: ' . $e->getMessage());
    }

    return $live;
}

// ============================================================
// PUBLIC SITE — LANDING PAGE CONTENT HELPERS
// ============================================================

/**
 * Published testimonials for the Reviews section.
 */
function siteTestimonials(): array
{
    $testimonials = [];
    try {
        $testimonials = Database::fetchAll(
            "SELECT testimonial_id, customer_name, customer_role, content, rating, avatar_color
             FROM testimonials
             WHERE is_published = 1
             ORDER BY sort_order ASC, testimonial_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load testimonials: ' . $e->getMessage());
    }

    $approvalColumnExists = (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'feedback'
           AND column_name = 'testimonial_approved'"
    ) === 1;
    if (!$approvalColumnExists) {
        return $testimonials;
    }

    try {
        $approvedFeedback = Database::fetchAll(
            "SELECT f.feedback_id, f.rating, f.category, f.comment,
                    COALESCE(NULLIF(TRIM(f.customer_name), ''), NULLIF(TRIM(u.full_name), ''), 'A customer') AS customer_name
             FROM feedback f
             LEFT JOIN users u ON u.user_id = f.user_id
             WHERE f.testimonial_approved = 1
               AND f.comment IS NOT NULL
               AND TRIM(f.comment) <> ''
             ORDER BY f.testimonial_approved_at DESC, f.feedback_id DESC"
        );
        foreach ($approvedFeedback as $feedback) {
            $testimonials[] = [
                'testimonial_id' => 'feedback-' . $feedback['feedback_id'],
                'customer_name' => $feedback['customer_name'],
                'customer_role' => ucfirst((string) $feedback['category']) . ' visitor',
                'content' => $feedback['comment'],
                'rating' => (int) $feedback['rating'],
                'avatar_color' => 'violet',
            ];
        }
    } catch (Throwable $e) {
        logWarning('Unable to load feedback approved as testimonials: ' . $e->getMessage());
    }

    return $testimonials;
}

/**
 * Published FAQs for the FAQ section.
 */
function siteFaqs(): array
{
    $faqs = [];
    try {
        $faqs = Database::fetchAll(
            "SELECT faq_id, question, answer
             FROM landing_faqs
             WHERE is_published = 1
             ORDER BY sort_order ASC, faq_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load landing FAQs: ' . $e->getMessage());
    }

    if (!Database::tableExists('help_resources')) {
        return $faqs;
    }

    try {
        $helpFaqs = Database::fetchAll(
            "SELECT resource_id, title, content
             FROM help_resources
             WHERE resource_type = 'faq' AND is_published = 1
             ORDER BY sort_order ASC, resource_id ASC"
        );
        foreach ($helpFaqs as $faq) {
            $faqs[] = [
                'faq_id' => 'help-' . $faq['resource_id'],
                'question' => $faq['title'],
                'answer' => $faq['content'],
            ];
        }
    } catch (Throwable $e) {
        logWarning('Unable to load published help center FAQs: ' . $e->getMessage());
    }

    return $faqs;
}

/**
 * Published trust badges for the Trusted by section.
 */
function siteTrustBadges(): array
{
    try {
        return Database::fetchAll(
            "SELECT badge_id, name
             FROM trust_badges
             WHERE is_published = 1
             ORDER BY sort_order ASC, badge_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load trust badges: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published How It Works steps.
 */
function siteHowItWorks(): array
{
    try {
        return Database::fetchAll(
            "SELECT step_id, step_number, title, description
             FROM how_it_works
             WHERE is_published = 1
             ORDER BY sort_order ASC, step_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load how_it_works steps: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published roadmap items.
 */
function siteRoadmapItems(): array
{
    try {
        return Database::fetchAll(
            "SELECT item_id, period, title, description
             FROM roadmap_items
             WHERE is_published = 1
             ORDER BY sort_order ASC, item_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load roadmap items: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published personas for the Who we serve section.
 */
function sitePersonas(): array
{
    try {
        return Database::fetchAll(
            "SELECT persona_id, name, description, glyph
             FROM personas
             WHERE is_published = 1
             ORDER BY sort_order ASC, persona_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load personas: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published gallery item labels.
 */
function siteGalleryItems(): array
{
    try {
        return Database::fetchAll(
            "SELECT item_id, label
             FROM gallery_items
             WHERE is_published = 1
             ORDER BY sort_order ASC, item_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load gallery items: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published spotlight / most-requested items.
 */
function siteSpotlightItems(): array
{
    try {
        return Database::fetchAll(
            "SELECT item_id, icon_class, title, description
             FROM spotlight_items
             WHERE is_published = 1
             ORDER BY sort_order ASC, item_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load spotlight items: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published why-here feature rows.
 */
function siteWhyFeatures(): array
{
    try {
        return Database::fetchAll(
            "SELECT feature_id, feature_number, title, description
             FROM why_features
             WHERE is_published = 1
             ORDER BY sort_order ASC, feature_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load why features: ' . $e->getMessage());
        return [];
    }
}

/**
 * Published about-section feature blocks.
 */
function siteAboutFeatures(): array
{
    try {
        return Database::fetchAll(
            "SELECT feature_id, title, description, icon_class
             FROM about_features
             WHERE is_published = 1
             ORDER BY sort_order ASC, feature_id ASC"
        );
    } catch (Throwable $e) {
        logWarning('Unable to load about features: ' . $e->getMessage());
        return [];
    }
}

// ============================================================
// API HELPERS
// ============================================================

/**
 * Get bearer token from request
 */
function getBearerToken(): ?string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    } else {
        foreach ($_SERVER as $name => $value) {
            if (str_starts_with($name, 'HTTP_')) {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
    }

    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * Get request method
 */
function requestMethod(): string
{
    return $_SERVER['REQUEST_METHOD'] ?? 'GET';
}

/**
 * Check if request is JSON
 */
function isJsonRequest(): bool
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    return str_contains($contentType, 'application/json');
}

// ============================================================
// REFUNDS & INVENTORY HELPERS
// ============================================================

/**
 * Get the id of the signed-in staff member (0 when not a staff session).
 */
function currentStaffId(): int
{
    if (($_SESSION['user_type'] ?? '') !== 'staff') {
        return 0;
    }
    return (int) ($_SESSION['user_id'] ?? 0);
}

/**
 * Whether the refunds tables are installed.
 */
function refundsTableExists(): bool
{
    return Database::tableExists('transaction_refunds');
}

/**
 * Generate a unique receipt number for a new transaction.
 */
function generateReceiptNumber(): string
{
    do {
        $receiptNumber = 'RCP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $exists = Database::fetchColumn(
            "SELECT 1 FROM transactions WHERE receipt_number = ? LIMIT 1",
            [$receiptNumber]
        );
    } while ($exists !== null);

    return $receiptNumber;
}

/**
 * Gross total of a transaction.
 */
function transactionTotal(int $transactionId): float
{
    return (float) Database::fetchColumn(
        "SELECT COALESCE(total_amount, 0) FROM transactions WHERE transaction_id = ?",
        [$transactionId]
    );
}

/**
 * Total amount already refunded against a transaction.
 */
function transactionRefundedAmount(int $transactionId): float
{
    if (!refundsTableExists()) {
        return 0.0;
    }

    return (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(amount), 0) FROM transaction_refunds WHERE transaction_id = ?",
        [$transactionId]
    );
}

/**
 * Quantity of a transaction line item that has already been refunded.
 */
function refundItemQuantity(int $itemId): float
{
    if (!Database::tableExists('transaction_item_refunds')) {
        return 0.0;
    }

    return (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(quantity), 0) FROM transaction_item_refunds WHERE item_id = ?",
        [$itemId]
    );
}

/**
 * Refundable balance left on a transaction.
 */
function transactionRefundableAmount(int $transactionId): float
{
    $total = transactionTotal($transactionId);
    $refunded = transactionRefundedAmount($transactionId);
    return max(0.0, round($total - $refunded, 2));
}

/**
 * Recent stock movements for a product (newest first).
 */
function productStockMovements(int $productId, int $limit = 20): array
{
    if (!Database::tableExists('inventory_movements')) {
        return [];
    }

    return Database::fetchAll(
        "SELECT im.movement_id, im.movement_type, im.quantity, im.notes, im.created_at,
                s.full_name AS created_by_name
         FROM inventory_movements im
         LEFT JOIN staff s ON s.staff_id = im.created_by
         WHERE im.product_id = ?
         ORDER BY im.created_at DESC, im.movement_id DESC
         LIMIT ?",
        [$productId, $limit]
    );
}

// ============================================================
// DEBUG HELPERS
// ============================================================

/**
 * Dump variable and die
 */
function dd(mixed ...$vars): void
{
    echo '<pre>';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    die;
}

/**
 * Dump variable
 */
function dump(mixed $var): void
{
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}
