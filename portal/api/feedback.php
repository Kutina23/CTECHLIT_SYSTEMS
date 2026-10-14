<?php
/**
 * Feedback CRUD API
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'];
$categories = ['service', 'station', 'staff', 'cleanliness', 'other'];

function validateFeedbackFields(array $data): array
{
    global $categories;

    $category = trim((string) ($data['category'] ?? ''));
    $comment = trim((string) ($data['comment'] ?? ''));
    $rating = filter_var($data['rating'] ?? null, FILTER_VALIDATE_INT);
    $isResolved = filter_var($data['is_resolved'] ?? 0, FILTER_VALIDATE_INT);
    $userIdValue = $data['user_id'] ?? null;
    $userId = $userIdValue === null || $userIdValue === '' ? null : filter_var($userIdValue, FILTER_VALIDATE_INT);

    if (!in_array($category, $categories, true)) {
        errorResponse('Select a valid feedback topic');
    }
    if ($rating === false || $rating < 1 || $rating > 5) {
        errorResponse('Rating must be between 1 and 5');
    }
    if (strlen($comment) > 60000) {
        errorResponse('Feedback comment is too long');
    }
    if ($isResolved === false || !in_array($isResolved, [0, 1], true)) {
        errorResponse('Invalid feedback status');
    }
    if ($userIdValue !== null && $userIdValue !== '' && ($userId === false || $userId <= 0)) {
        errorResponse('Select a valid customer');
    }
    if ($userId !== null && !Database::fetchOne("SELECT user_id FROM users WHERE user_id = ?", [$userId])) {
        errorResponse('Selected customer was not found', 404);
    }

    return [$userId, $rating, $category, $comment, $isResolved];
}

try {
    switch ($method) {
        case 'GET':
            requirePermission('feedback.view');
            if (isset($_GET['feedback_id'])) {
                $feedback = Database::fetchOne(
                    "SELECT f.feedback_id, f.user_id, f.rating, f.category, f.comment, f.is_resolved,
                            f.testimonial_approved, f.testimonial_approved_by, f.testimonial_approved_at,
                            f.resolved_by, f.resolved_at, f.created_at, f.updated_at,
                            COALESCE(u.full_name, f.customer_name) AS user_name
                     FROM feedback f
                     LEFT JOIN users u ON u.user_id = f.user_id
                     WHERE f.feedback_id = ?",
                    [(int) $_GET['feedback_id']]
                );
                if (!$feedback) {
                    errorResponse('Feedback not found', 404);
                }
                successResponse($feedback);
            }
            successResponse(portalFeedback(100));

        case 'POST':
            requirePermission('feedback.respond');
            [$userId, $rating, $category, $comment, $isResolved] = validateFeedbackFields($input);
            $staffId = (int) ($_SESSION['user_id'] ?? 0);
            $feedbackId = Database::insert(
                "INSERT INTO feedback (user_id, rating, category, comment, is_resolved, resolved_by, resolved_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)",
                [$userId, $rating, $category, $comment, $isResolved, $isResolved ? $staffId : null, $isResolved ? date('Y-m-d H:i:s') : null]
            );
            logAuditTrail('feedback_created', 'feedback', $feedbackId, ['category' => $category, 'rating' => $rating], $staffId, 'staff');
            successResponse(['feedback_id' => $feedbackId], 'Feedback created successfully');

        case 'PUT':
            requirePermission('feedback.respond');
            if (($input['action'] ?? '') === 'testimonial_approval') {
                $feedbackId = filter_var($input['feedback_id'] ?? null, FILTER_VALIDATE_INT);
                $approved = filter_var($input['testimonial_approved'] ?? null, FILTER_VALIDATE_INT);
                if ($feedbackId === false || $feedbackId <= 0) {
                    errorResponse('A valid feedback_id is required');
                }
                if ($approved === false || !in_array($approved, [0, 1], true)) {
                    errorResponse('Choose whether this feedback is approved as a testimonial');
                }
                if (!Database::fetchOne("SELECT feedback_id FROM feedback WHERE feedback_id = ?", [$feedbackId])) {
                    errorResponse('Feedback not found', 404);
                }
                $staffId = (int) ($_SESSION['user_id'] ?? 0);
                Database::execute(
                    "UPDATE feedback
                     SET testimonial_approved = ?, testimonial_approved_by = ?, testimonial_approved_at = ?
                     WHERE feedback_id = ?",
                    [
                        $approved,
                        $approved && $staffId ? $staffId : null,
                        $approved ? date('Y-m-d H:i:s') : null,
                        $feedbackId,
                    ]
                );
                logAuditTrail(
                    $approved ? 'feedback_testimonial_approved' : 'feedback_testimonial_unpublished',
                    'feedback',
                    $feedbackId,
                    ['testimonial_approved' => $approved],
                    $staffId,
                    'staff'
                );
                successResponse(
                    ['feedback_id' => $feedbackId, 'testimonial_approved' => $approved],
                    $approved ? 'Feedback approved for the testimonials section' : 'Feedback removed from the testimonials section'
                );
            }
            if (empty($input['feedback_id']) || !filter_var($input['feedback_id'], FILTER_VALIDATE_INT)) {
                errorResponse('A valid feedback_id is required');
            }
            $feedbackId = (int) $input['feedback_id'];
            if (!Database::fetchOne("SELECT feedback_id FROM feedback WHERE feedback_id = ?", [$feedbackId])) {
                errorResponse('Feedback not found', 404);
            }
            [$userId, $rating, $category, $comment, $isResolved] = validateFeedbackFields($input);
            $staffId = (int) ($_SESSION['user_id'] ?? 0);
            Database::execute(
                "UPDATE feedback
                 SET user_id = ?, rating = ?, category = ?, comment = ?, is_resolved = ?,
                     resolved_by = ?, resolved_at = ?
                 WHERE feedback_id = ?",
                [
                    $userId,
                    $rating,
                    $category,
                    $comment,
                    $isResolved,
                    $isResolved ? $staffId : null,
                    $isResolved ? date('Y-m-d H:i:s') : null,
                    $feedbackId,
                ]
            );
            logAuditTrail('feedback_updated', 'feedback', $feedbackId, ['category' => $category, 'rating' => $rating, 'is_resolved' => $isResolved], $staffId, 'staff');
            successResponse(['feedback_id' => $feedbackId], 'Feedback updated successfully');

        case 'DELETE':
            requirePermission('feedback.respond');
            if (empty($input['feedback_id']) || !filter_var($input['feedback_id'], FILTER_VALIDATE_INT)) {
                errorResponse('A valid feedback_id is required');
            }
            $feedbackId = (int) $input['feedback_id'];
            if (Database::execute("DELETE FROM feedback WHERE feedback_id = ?", [$feedbackId]) < 1) {
                errorResponse('Feedback not found', 404);
            }
            logAuditTrail('feedback_deleted', 'feedback', $feedbackId, [], (int) ($_SESSION['user_id'] ?? 0), 'staff');
            successResponse(null, 'Feedback deleted successfully');

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
