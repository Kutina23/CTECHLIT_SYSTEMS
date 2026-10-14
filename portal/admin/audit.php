<?php
/**
 * Admin Audit Trail
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('audit.view');

$hasAuditTable = Database::tableExists('audit_logs');
$auditPageSize = 25;
$auditTotal = $hasAuditTable
    ? (int) Database::fetchColumn("SELECT COUNT(*) FROM audit_logs")
    : 0;
$auditTotalPages = max(1, (int) ceil($auditTotal / $auditPageSize));
$auditPage = min(
    $auditTotalPages,
    max(1, (int) ($_GET['audit_page'] ?? 1))
);
$auditOffset = ($auditPage - 1) * $auditPageSize;

$auditTrail = $hasAuditTable
    ? Database::fetchAll(
        "SELECT al.*, s.full_name AS staff_name
         FROM audit_logs al
         LEFT JOIN staff s ON s.staff_id = al.staff_id
         ORDER BY al.created_at DESC, al.audit_id DESC
         LIMIT {$auditPageSize} OFFSET {$auditOffset}"
    )
    : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Trail — CTECHLIT</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>

    <div class="portal-content">
      <div class="card glass-card animate-fade-in" id="audit-trail">
        <div class="card-header">
          <div>
            <div class="card-title">Audit Trail</div>
            <div class="card-subtitle">Login and finance actions tracked for admin review</div>
          </div>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead>
              <tr><th>Staff</th><th>Action</th><th>Entity</th><th>Details</th><th>Time</th></tr>
            </thead>
            <tbody>
              <?php if (empty($auditTrail)): ?>
                <tr><td colspan="5" style="text-align:center; color: var(--ink-secondary);">No audit events recorded.</td></tr>
              <?php else: ?>
                <?php foreach ($auditTrail as $entry): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($entry['staff_name'] ?? 'System'); ?></td>
                    <td><?php echo htmlspecialchars($entry['action'] ?? 'unknown'); ?></td>
                    <td><?php echo htmlspecialchars($entry['entity_type'] ?? 'n/a'); ?></td>
                    <td><?php echo htmlspecialchars($entry['details'] ?? '{}'); ?></td>
                    <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($entry['created_at'] ?? 'now'))); ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-top:16px;">
          <span style="font-size:13px; color:var(--ink-secondary);">
            <?php if ($auditTotal > 0): ?>
              Showing <?php echo $auditOffset + 1; ?>-<?php echo min($auditOffset + $auditPageSize, $auditTotal); ?> of <?php echo $auditTotal; ?> entries
            <?php else: ?>
              No audit entries
            <?php endif; ?>
            (Page <?php echo $auditPage; ?> of <?php echo $auditTotalPages; ?>)
          </span>
          <nav aria-label="Audit trail pagination" style="display:flex; gap:8px;">
            <?php if ($auditPage > 1): ?>
              <a class="btn btn-secondary" href="?audit_page=<?php echo $auditPage - 1; ?>#audit-trail" aria-label="Previous audit page">Previous</a>
            <?php else: ?>
              <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Previous</span>
            <?php endif; ?>
            <?php if ($auditPage < $auditTotalPages): ?>
              <a class="btn btn-secondary" href="?audit_page=<?php echo $auditPage + 1; ?>#audit-trail" aria-label="Next audit page">Next</a>
            <?php else: ?>
              <span class="btn btn-secondary" aria-disabled="true" style="opacity:.5; pointer-events:none;">Next</span>
            <?php endif; ?>
          </nav>
        </div>
      </div>
    </div>
  </main>
</body>
</html>
