<?php
/**
 * CTECHLIT & SYSTEMS — Configuration Loader
 * Loads environment variables and returns configuration array
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Application Configuration
return [
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'CTECHLIT & SYSTEMS',
        'env' => $_ENV['APP_ENV'] ?? 'production',
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'url' => $_ENV['APP_URL'] ?? 'http://localhost',
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
    ],

    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
        'name' => $_ENV['DB_NAME'] ?? 'ctechlit_systems',
        'user' => $_ENV['DB_USER'] ?? 'root',
        'pass' => $_ENV['DB_PASS'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
        'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    ],

    'security' => [
        'jwt_secret' => $_ENV['JWT_SECRET'] ?? '',
        'jwt_expiry' => (int) ($_ENV['JWT_EXPIRY'] ?? 86400),
        'bcrypt_cost' => (int) ($_ENV['BCRYPT_COST'] ?? 12),
        'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
    ],

    'upload' => [
        'max_size' => (int) ($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760),
        'path' => $_ENV['UPLOAD_PATH'] ?? 'uploads/',
        'allowed_extensions' => explode(',', $_ENV['ALLOWED_EXTENSIONS'] ?? 'jpg,jpeg,png,pdf,doc,docx'),
    ],

    'mail' => [
        'host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
        'port' => (int) ($_ENV['MAIL_PORT'] ?? 587),
        'username' => $_ENV['MAIL_USERNAME'] ?? '',
        'password' => $_ENV['MAIL_PASSWORD'] ?? '',
        'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'tls',
        'from_address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@ctechlitsystems.com',
        'from_name' => $_ENV['MAIL_FROM_NAME'] ?? 'CTECHLIT & SYSTEMS',
    ],

    'payment' => [
        'paystack' => [
            'secret_key' => $_ENV['PAYSTACK_SECRET_KEY'] ?? '',
            'public_key' => $_ENV['PAYSTACK_PUBLIC_KEY'] ?? '',
            'webhook_secret' => $_ENV['PAYSTACK_WEBHOOK_SECRET'] ?? '',
        ],
    ],

    'cache' => [
        'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
        'host' => $_ENV['CACHE_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['CACHE_PORT'] ?? 6379),
        'ttl' => (int) ($_ENV['CACHE_TTL'] ?? 3600),
    ],

    'queue' => [
        'driver' => $_ENV['QUEUE_DRIVER'] ?? 'sync',
        'host' => $_ENV['QUEUE_HOST'] ?? '127.0.0.1',
        'port' => (int) ($_ENV['QUEUE_PORT'] ?? 6379),
    ],

    'logging' => [
        'channel' => $_ENV['LOG_CHANNEL'] ?? 'daily',
        'level' => $_ENV['LOG_LEVEL'] ?? 'warning',
        'path' => $_ENV['LOG_PATH'] ?? 'logs/',
    ],

    'backup' => [
        'path' => $_ENV['BACKUP_PATH'] ?? 'backups/',
        'retention_days' => (int) ($_ENV['BACKUP_RETENTION_DAYS'] ?? 30),
    ],
];
