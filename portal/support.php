<?php
/**
 * CTECHLIT & SYSTEMS — All Support Tickets
 */

require_once __DIR__ . '/../includes/auth.php';
requirePermission('support.view');

$canManage = hasPermission('support.manage');
$page = max(1, (int) ($_GET['page'] ?? 1));
$pageSize = 25;
$totalTickets = (int) Database::fetchColumn("SELECT COUNT(*) FROM support_tickets");
$openTickets = (int) Database::fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE status IN ('open', 'in_progress', 'waiting_customer')");
$resolvedTickets = (int) Database::fetchColumn("SELECT COUNT(*) FROM support_tickets WHERE status IN ('resolved', 'closed')");
$totalPages = max(1, (int) ceil($totalTickets / $pageSize));
$page = min($page, $totalPages);
$offset = ($page - 1) * $pageSize;
$requesterNameExpression = supportTicketRequesterNameSql();
$tickets = Database::fetchAll(
    "SELECT st.ticket_id, st.user_id, st.assigned_to, st.subject, st.description, st.priority,
            st.status, st.category, st.created_at, {$requesterNameExpression} AS requestor_name,
            a.full_name AS assignee_name
     FROM support_tickets st
     LEFT JOIN users u ON u.user_id = st.user_id
     LEFT JOIN staff a ON a.staff_id = st.assigned_to
     ORDER BY st.created_at DESC, st.ticket_id DESC
     LIMIT {$pageSize} OFFSET {$offset}"
);
$customers = $canManage ? Database::fetchAll("SELECT user_id, full_name FROM users ORDER BY full_name") : [];
$staffMembers = $canManage ? Database::fetchAll("SELECT staff_id, full_name FROM staff ORDER BY full_name") : [];
$slaMet = $totalTickets > 0 ? round(($resolvedTickets / $totalTickets) * 100) : 0;
$ticketViewTitle = 'All Tickets';
$ticketViewSubtitle = 'Support cases and service requests';
$ticketViewPage = $page;
$ticketViewTotalPages = $totalPages;
$ticketViewTotal = $totalTickets;
$ticketViewOffset = $offset;
$ticketOpenOnly = false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>All Support Tickets — CTECHLIT & SYSTEMS</title>
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
    <?php include __DIR__ . '/../includes/support-management.php'; ?>
  </main>
</body>
</html>
