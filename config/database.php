<?php
/**
 * CTECHLIT & SYSTEMS — Database Connection Manager
 * Lazy PDO connection manager
 */

declare(strict_types=1);

$GLOBALS['config'] = require_once __DIR__ . '/config.php';

/**
 * Database Connection Manager
 */
class Database
{
    private static ?PDO $instance = null;
    private static string $dsn;
    private static array $options;

    private function __construct() {}

    /**
     * Get database configuration
     */
    private static function getConfig(): array
    {
        return $GLOBALS['config']['database'] ?? [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'name' => $_ENV['DB_NAME'] ?? 'ctechlit_systems',
            'user' => $_ENV['DB_USER'] ?? 'root',
            'pass' => $_ENV['DB_PASS'] ?? '',
            'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
        ];
    }

    /**
     * Initialize connection settings without opening a connection.
     */
    private static function initializeConnection(): void
    {
        $config = self::getConfig();

        self::$dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        self::$options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$config['charset']} COLLATE {$config['collation']}",
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];
    }

    /**
     * Create a new PDO connection
     */
    private static function createConnection(): PDO
    {
        $config = self::getConfig();
        try {
            return new PDO(
                self::$dsn,
                $config['user'],
                $config['pass'],
                self::$options
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new PDOException("Database connection failed: " . $e->getMessage(), (int) $e->getCode());
        }
    }

    /**
     * Get the request's lazily-created PDO connection.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            self::initializeConnection();
            self::$instance = self::createConnection();
            self::syncTimeZone(self::$instance);
        }

        return self::$instance;
    }

    /**
     * Make NOW()/CURDATE() use the application timezone so SQL time math agrees with PHP
     * (the MySQL server may run on a different system timezone).
     */
    private static function syncTimeZone(PDO $connection): void
    {
        try {
            $zone = new DateTimeZone($_ENV['APP_TIMEZONE'] ?? date_default_timezone_get());
            $offset = (new DateTimeImmutable('now', $zone))->format('P');
            $connection->exec("SET time_zone = '$offset'");
        } catch (Throwable $e) {
            error_log('Could not align the database timezone: ' . $e->getMessage());
        }
    }

    /**
     * Retained for compatibility; the single connection remains available
     * until the request ends.
     */
    public static function releaseConnection(PDO $conn): void
    {
    }

    /**
     * Get singleton instance (for backward compatibility)
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::getConnection();
        }
        return self::$instance;
    }

    /**
     * Close the managed connection.
     */
    public static function closeAll(): void
    {
        self::$instance = null;
    }

    /**
     * Execute a query with optional parameters
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch all rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch single row
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::query($sql, $params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Fetch single column value
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $stmt = self::query($sql, $params);
        $value = $stmt->fetchColumn($column);
        return $value !== false ? $value : null;
    }

    /**
     * Execute INSERT/UPDATE/DELETE and return affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Execute INSERT and return the inserted ID.
     */
    public static function insert(string $sql, array $params = []): int
    {
        $conn = self::getConnection();
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return (int) $conn->lastInsertId();
    }

    /**
     * Get last inserted ID
     */
    public static function lastInsertId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    /**
     * Begin transaction
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit transaction
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback transaction
     */
    public static function rollback(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * Check if table exists
     */
    public static function tableExists(string $table): bool
    {
        $table = trim($table);
        if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }

        $sql = "SELECT TABLE_NAME FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1";
        return self::fetchColumn($sql, [$table]) !== null;
    }

    /**
     * Get table row count
     */
    public static function rowCount(string $table): int
    {
        $sql = "SELECT COUNT(*) as cnt FROM `$table`";
        return (int) self::fetchColumn($sql);
    }

    /**
     * Escape identifier (table/column names)
     */
    public static function escapeIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Build WHERE clause from array
     */
    public static function buildWhere(array $conditions, string $prefix = 'WHERE'): string
    {
        if (empty($conditions)) {
            return '';
        }

        $parts = [];
        foreach ($conditions as $column => $value) {
            if (is_array($value)) {
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $parts[] = self::escapeIdentifier($column) . " IN ($placeholders)";
            } elseif ($value === null) {
                $parts[] = self::escapeIdentifier($column) . ' IS NULL';
            } else {
                $parts[] = self::escapeIdentifier($column) . ' = ?';
            }
        }

        return $prefix . ' ' . implode(' AND ', $parts);
    }

    /**
     * Flatten WHERE conditions for parameter binding
     */
    public static function flattenParams(array $conditions): array
    {
        $params = [];
        foreach ($conditions as $value) {
            if (is_array($value)) {
                $params = array_merge($params, $value);
            } elseif ($value !== null) {
                $params[] = $value;
            }
        }
        return $params;
    }
}
