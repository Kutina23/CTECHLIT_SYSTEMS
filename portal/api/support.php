<?php
/**
 * Support ticket CRUD API
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'];
$categories = ['technical', 'billing', 'account', 'general'];
$priorities = ['low', 'medium', 'high', 'urgent'];
$statuses = ['open', 'in_progress', 'waiting_customer', 'resolved', 'closed'];

function validateTicketFields(array $data): array
{
    global $categories, $priorities, $statuses;

    $subject = trim((string) ($data['subject'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $category = trim((string) ($data['category'] ?? ''));
    $priority = trim((string) ($data['priority'] ?? ''));
    $status = trim((string) ($data['status'] ?? 'open'));
    $userValue = $data['user_id'] ?? null;
    $assignedValue = $data['assigned_to'] ?? null;
    $userId = $userValue === null || $userValue === '' ? null : filter_var($userValue, FILTER_VALIDATE_INT);
    $assignedTo = $assignedValue === null || $assignedValue === '' ? null : filter_var($assignedValue, FILTER_VALIDATE_INT);

    if ($subject === '' || strlen($subject) > 255) {
        errorResponse('Subject is required and must be 255 characters or fewer');
    }
    if ($description === '') {
        errorResponse('Ticket description is required');
    }
    if (!in_array($category, $categories, true)) {
        errorResponse('Select a valid ticket category');
    }
    if (!in_array($priority, $priorities, true)) {
        errorResponse('Select a valid ticket priority');
    }
    if (!in_array($status, $statuses, true)) {
        errorResponse('Select a valid ticket status');
    }
    if ($userValue !== null && $userValue !== '' && ($userId === false || $userId <= 0)) {
        errorResponse('Select a valid requestor');
    }
    if ($assignedValue !== null && $assignedValue !== '' && ($assignedTo === false || $assignedTo <= 0)) {
        errorResponse('Select a valid staff assignee');
    }
    if ($userId !== null && !Database::fetchOne("SELECT user_id FROM users WHERE user_id = ?", [$userId])) {
        errorResponse('Selected requestor was not found', 404);
    }
    if ($assignedTo !== null && !Database::fetchOne("SELECT staff_id FROM staff WHERE staff_id = ?", [$assignedTo])) {
        errorResponse('Selected assignee was not found', 404);
    }

    return [$userId, $assignedTo, $subject, $description, $category, $priority, $status];
}

try {
    switch ($method) {
        case 'GET':
            requirePermission('support.view');
            if (isset($_GET['ticket_id'])) {
                $ticket = Database::fetchOne(
                    "SELECT ticket_id, user_id, assigned_to, subject, description, priority, status,
                            category, resolved_at, created_at, updated_at
                     FROM support_tickets WHERE ticket_id = ?",
                    [(int) $_GET['ticket_id']]
                );
                if (!$ticket) {
                    errorResponse('Ticket not found', 404);
                }
                successResponse($ticket);
            }
            successResponse(portalSupportTickets(100));

        case 'POST':
            requirePermission('support.manage');
            [$userId, $assignedTo, $subject, $description, $category, $priority, $status] = validateTicketFields($input);
            $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;
            $ticketId = Database::insert(
                "INSERT INTO support_tickets (user_id, assigned_to, subject, description, category, priority, status, resolved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $assignedTo, $subject, $description, $category, $priority, $status, $resolvedAt]
            );
            logAuditTrail('support_ticket_created', 'support_ticket', $ticketId, ['subject' => $subject, 'status' => $status], (int) ($_SESSION['user_id'] ?? 0), 'staff');
            successResponse(['ticket_id' => $ticketId], 'Ticket created successfully');

        case 'PUT':
            requirePermission('support.manage');
            if (empty($input['ticket_id']) || !filter_var($input['ticket_id'], FILTER_VALIDATE_INT)) {
                errorResponse('A valid ticket_id is required');
            }
            $ticketId = (int) $input['ticket_id'];
            if (!Database::fetchOne("SELECT ticket_id FROM support_tickets WHERE ticket_id = ?", [$ticketId])) {
                errorResponse('Ticket not found', 404);
            }
            [$userId, $assignedTo, $subject, $description, $category, $priority, $status] = validateTicketFields($input);
            $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;
            Database::execute(
                "UPDATE support_tickets
                 SET user_id = ?, assigned_to = ?, subject = ?, description = ?, category = ?,
                     priority = ?, status = ?, resolved_at = ?
                 WHERE ticket_id = ?",
                [$userId, $assignedTo, $subject, $description, $category, $priority, $status, $resolvedAt, $ticketId]
            );
            logAuditTrail('support_ticket_updated', 'support_ticket', $ticketId, ['status' => $status, 'priority' => $priority], (int) ($_SESSION['user_id'] ?? 0), 'staff');
            successResponse(['ticket_id' => $ticketId], 'Ticket updated successfully');

        case 'DELETE':
            requirePermission('support.manage');
            if (empty($input['ticket_id']) || !filter_var($input['ticket_id'], FILTER_VALIDATE_INT)) {
                errorResponse('A valid ticket_id is required');
            }
            $ticketId = (int) $input['ticket_id'];
            if (Database::execute("DELETE FROM support_tickets WHERE ticket_id = ?", [$ticketId]) < 1) {
                errorResponse('Ticket not found', 404);
            }
            logAuditTrail('support_ticket_deleted', 'support_ticket', $ticketId, [], (int) ($_SESSION['user_id'] ?? 0), 'staff');
            successResponse(null, 'Ticket deleted successfully');

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
