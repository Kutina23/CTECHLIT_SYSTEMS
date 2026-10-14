<?php
/**
 * CTECHLIT & SYSTEMS — Unresolved Feedback
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('feedback.respond');

$canRespond = true;
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;
$totalFeedback = (int) Database::fetchColumn("SELECT COUNT(*) FROM feedback");
$pendingFeedback = (int) Database::fetchColumn("SELECT COUNT(*) FROM feedback WHERE is_resolved = 0");
$totalPages = max(1, (int) ceil($pendingFeedback / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$feedbackItems = Database::fetchAll(
    "SELECT f.feedback_id, f.user_id, f.rating, f.category, f.comment, f.is_resolved, f.created_at,
            COALESCE(u.full_name, f.customer_name) AS user_name
     FROM feedback f
     LEFT JOIN users u ON u.user_id = f.user_id
     WHERE f.is_resolved = 0
     ORDER BY f.created_at DESC, f.feedback_id DESC
     LIMIT {$pageSize} OFFSET {$offset}"
);
$customers = Database::fetchAll("SELECT user_id, full_name FROM users ORDER BY full_name");
$positiveFeedback = (int) Database::fetchColumn("SELECT COUNT(*) FROM feedback WHERE rating >= 4");
$escalatedFeedback = (int) Database::fetchColumn("SELECT COUNT(*) FROM feedback WHERE rating <= 2");
$positiveRate = $totalFeedback > 0 ? round(($positiveFeedback / $totalFeedback) * 100) : 0;
$feedbackViewTitle = 'Unresolved Feedback';
$feedbackViewSubtitle = 'Feedback awaiting review or resolution';
$feedbackViewPage = $page;
$feedbackViewTotalPages = $totalPages;
$feedbackViewTotal = $pendingFeedback;
$feedbackViewOffset = $offset;
$feedbackUnresolvedOnly = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Unresolved Feedback — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <?php include __DIR__ . '/../includes/feedback-management.php'; ?>
  </main>
</body>
</html>
