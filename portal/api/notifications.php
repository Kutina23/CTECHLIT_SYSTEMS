<?php
/**
 * Recipient notifications and admin system-notification management.
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$rawInput = file_get_contents('php://input');
$decodedInput = json_decode($rawInput ?: '[]', true);
$input = is_array($decodedInput) ? $decodedInput : [];

function notificationRecipientFilter(): array
{
    $userId = (int) ($_SESSION['user_id'] ?? 0);
    if (($_SESSION['user_type'] ?? '') === 'staff') {
        return [
            '(staff_id = ? AND user_id IS NULL) OR (staff_id IS NULL AND user_id IS NULL)',
            [$userId],
        ];
    }

    return [
        '(user_id = ? AND staff_id IS NULL) OR (user_id IS NULL AND staff_id IS NULL)',
        [$userId],
    ];
}

function requireNotificationAdmin(): void
{
    if (($_SESSION['user_type'] ?? '') !== 'staff' || !hasPermission('settings.edit')) {
        errorResponse('Forbidden: You do not have permission to manage system notifications', 403);
    }
}

try {
    if ($method !== 'GET') {
        $csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? ''));
        if (!verifyCsrfToken($csrfToken)) {
            errorResponse('Invalid or expired security token. Reload the page and try again.', 403);
        }
    }

    switch ($method) {
        case 'GET':
            [$recipientFilter, $recipientParams] = notificationRecipientFilter();
            $notifications = Database::fetchAll(
                "SELECT notification_id, type, title, message, is_read, created_at,
                        JSON_UNQUOTE(JSON_EXTRACT(data, '$.system_notification_id')) AS system_notification_id
                 FROM notifications
                 WHERE (($recipientFilter)
                   AND (expires_at IS NULL OR expires_at > NOW()))
                 ORDER BY created_at DESC, notification_id DESC
                 LIMIT 50",
                $recipientParams
            );
            $unreadCount = (int) Database::fetchColumn(
                "SELECT COUNT(*) FROM notifications
                 WHERE (($recipientFilter))
                   AND is_read = 0
                   AND (expires_at IS NULL OR expires_at > NOW())",
                $recipientParams
            );
            successResponse([
                'notifications' => $notifications,
                'unread_count' => $unreadCount,
            ], 'Notifications loaded');

        case 'POST':
            requireNotificationAdmin();
            $audience = trim((string) ($input['audience'] ?? ''));
            $type = trim((string) ($input['type'] ?? ''));
            $title = trim((string) ($input['title'] ?? ''));
            $message = trim((string) ($input['message'] ?? ''));
            $expiresAtInput = trim((string) ($input['expires_at'] ?? ''));

            if (!in_array($audience, ['all', 'staff', 'users'], true)) {
                errorResponse('Select a valid notification audience');
            }
            if (!in_array($type, ['info', 'warning', 'success', 'error', 'promotion'], true)) {
                errorResponse('Select a valid notification type');
            }
            if ($title === '' || mb_strlen($title) > 255) {
                errorResponse('Notification title is required and must be 255 characters or fewer');
            }
            if ($message === '' || mb_strlen($message) > 5000) {
                errorResponse('Notification message is required and must be 5,000 characters or fewer');
            }

            $expiresAt = null;
            if ($expiresAtInput !== '') {
                try {
                    $expiryDate = new DateTimeImmutable($expiresAtInput);
                } catch (Throwable $exception) {
                    errorResponse('Enter a valid expiration date and time');
                }
                if ($expiryDate <= new DateTimeImmutable()) {
                    errorResponse('Expiration must be in the future');
                }
                $expiresAt = $expiryDate->format('Y-m-d H:i:s');
            }

            $recipientRows = [];
            if ($audience === 'all' || $audience === 'staff') {
                foreach (Database::fetchAll("SELECT staff_id FROM staff WHERE is_active = 1") as $recipient) {
                    $recipientRows[] = [null, (int) $recipient['staff_id']];
                }
            }
            if ($audience === 'all' || $audience === 'users') {
                foreach (Database::fetchAll("SELECT user_id FROM users WHERE is_active = 1") as $recipient) {
                    $recipientRows[] = [(int) $recipient['user_id'], null];
                }
            }
            if (!$recipientRows) {
                errorResponse('There are no active recipients for this audience');
            }

            $batchId = bin2hex(random_bytes(16));
            $payload = json_encode([
                'system_notification_id' => $batchId,
                'audience' => $audience,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($payload === false) {
                throw new RuntimeException('Notification metadata could not be encoded');
            }

            $connection = Database::getInstance();
            $connection->beginTransaction();
            try {
                $insert = $connection->prepare(
                    "INSERT INTO notifications (user_id, staff_id, type, title, message, data, is_read, expires_at)
                     VALUES (?, ?, ?, ?, ?, ?, 0, ?)"
                );
                foreach ($recipientRows as [$userId, $staffId]) {
                    $insert->execute([$userId, $staffId, $type, $title, $message, $payload, $expiresAt]);
                }
                $connection->commit();
            } catch (Throwable $exception) {
                if ($connection->inTransaction()) {
                    $connection->rollBack();
                }
                throw $exception;
            }

            successResponse([
                'system_notification_id' => $batchId,
                'recipient_count' => count($recipientRows),
            ], 'System notification sent');

        case 'PUT':
            [$recipientFilter, $recipientParams] = notificationRecipientFilter();
            if (!empty($input['mark_all_read'])) {
                Database::execute(
                    "UPDATE notifications SET is_read = 1, read_at = NOW()
                     WHERE (($recipientFilter)) AND is_read = 0",
                    $recipientParams
                );
                successResponse(null, 'Notifications marked as read');
            }

            $notificationId = filter_var($input['notification_id'] ?? null, FILTER_VALIDATE_INT);
            if ($notificationId === false || $notificationId <= 0) {
                errorResponse('A valid notification_id is required');
            }
            $params = array_merge([$notificationId], $recipientParams);
            $exists = Database::fetchOne(
                "SELECT notification_id FROM notifications
                 WHERE notification_id = ? AND (($recipientFilter))",
                $params
            );
            if (!$exists) {
                errorResponse('Notification not found', 404);
            }
            Database::execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW()
                 WHERE notification_id = ? AND (($recipientFilter))",
                $params
            );
            successResponse(null, 'Notification marked as read');

        case 'DELETE':
            requireNotificationAdmin();
            $batchId = (string) ($input['system_notification_id'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/', $batchId)) {
                errorResponse('A valid system_notification_id is required');
            }
            $deleted = Database::execute(
                "DELETE FROM notifications
                 WHERE JSON_UNQUOTE(JSON_EXTRACT(data, '$.system_notification_id')) = ?",
                [$batchId]
            );
            if ($deleted < 1) {
                errorResponse('System notification not found', 404);
            }
            successResponse(['deleted_count' => $deleted], 'System notification deleted');

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Throwable $exception) {
    error_log('Notification API failed: ' . $exception->getMessage());
    errorResponse('Notification operation failed. Please try again.', 500);
}
