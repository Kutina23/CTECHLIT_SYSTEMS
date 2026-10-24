<?php
/**
 * Print Jobs — live per-printer queues, job submission with pricing & payment, and job history.
 * Also served as "Pending Jobs" (pending-jobs.php), which opens on the queue board.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('print_jobs.view');

$pendingJobsPage = $pendingJobsPage ?? false;
$canManage = hasPermission('print_jobs.manage');
$canCancel = hasPermission('print_jobs.cancel');
$view = !$pendingJobsPage && ($_GET['view'] ?? '') === 'history' ? 'history' : 'board';
$v = static fn(string $file) => filemtime(__DIR__ . '/../assets/' . $file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pendingJobsPage ? 'Pending Jobs' : 'Print Jobs'; ?> — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <link rel="stylesheet" href="../assets/css/sessions.css?v=<?php echo $v('css/sessions.css'); ?>">
  <link rel="stylesheet" href="../assets/css/print-jobs.css?v=<?php echo $v('css/print-jobs.css'); ?>">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="ses-top">
        <div>
          <h1>Print Jobs</h1>
          <p id="pjSubtitle">Live printer queues · updated <span id="pjUpdated">—</span></p>
        </div>
        <nav class="ses-tabs" aria-label="Print job views">
          <a href="print-jobs.php" data-view="board"<?php echo $view === 'board' ? ' aria-current="page"' : ''; ?>><i class="fas fa-layer-group"></i> Queues</a>
          <a href="print-jobs.php?view=history" data-view="history"<?php echo $view === 'history' ? ' aria-current="page"' : ''; ?>><i class="fas fa-clock-rotate-left"></i> History</a>
        </nav>
        <?php if ($canManage): ?>
          <button class="btn btn-primary" type="button" id="pjNew"><i class="fas fa-plus"></i> New print job</button>
        <?php endif; ?>
      </div>

      <section id="pjBoardView"<?php echo $view === 'board' ? '' : ' hidden'; ?>>
        <div class="ses-stats" id="pjStats">
          <div class="ses-stat"><span>Jobs today</span><strong>—</strong></div>
        </div>
        <div class="pj-attention" id="pjAttention" hidden></div>
        <h2 class="sr-only">Printer queues</h2>
        <div class="pj-board" id="pjBoard"><div class="ses-empty">Loading printers…</div></div>
      </section>

      <section id="pjHistoryView"<?php echo $view === 'history' ? '' : ' hidden'; ?>>
        <form class="pj-filters" id="pjFilters">
          <div class="pj-search"><i class="fas fa-search" aria-hidden="true"></i><input type="search" name="search" class="form-input" placeholder="File, customer or PR number" aria-label="Search jobs"></div>
          <select name="status" class="form-input" aria-label="Status">
            <option value="">All statuses</option><option value="queued">Queued</option><option value="printing">Printing</option>
            <option value="completed">Printed</option><option value="failed">Failed</option><option value="cancelled">Cancelled</option>
          </select>
          <select name="payment" class="form-input" aria-label="Payment">
            <option value="">Any payment</option><option value="paid">Paid</option><option value="unpaid">To collect</option>
            <option value="on_account">On account</option><option value="refunded">Refunded</option><option value="waived">Waived</option>
          </select>
          <select name="printer" class="form-input" aria-label="Printer"><option value="">All printers</option></select>
          <input type="date" name="from" class="form-input" aria-label="From date">
          <input type="date" name="to" class="form-input" aria-label="To date">
        </form>
        <div class="pj-totals" id="pjTotals"></div>
        <div class="card pj-table-card">
          <div class="pj-table-wrap">
            <table class="pj-table">
              <thead><tr><th>Job</th><th>Document</th><th>Customer</th><th>Printer</th><th>Pages</th><th>Amount</th><th>Status</th><th>Submitted</th><th><span class="sr-only">Actions</span></th></tr></thead>
              <tbody id="pjRows"><tr><td colspan="9" class="pj-muted">Loading…</td></tr></tbody>
            </table>
          </div>
          <div class="pj-pager" id="pjPager"></div>
        </div>
      </section>
    </div>
  </main>

  <div class="modal-overlay" id="sesModal" role="dialog" aria-modal="true" aria-labelledby="sesModalTitle">
    <div class="modal pj-modal">
      <div class="modal-header">
        <h3 class="modal-title" id="sesModalTitle">Print job</h3>
        <button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="sesModalBody"></div>
      <div class="modal-footer" id="sesModalFooter"></div>
    </div>
  </div>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
    window.PJ_CONFIG = <?php echo json_encode(['canManage' => $canManage, 'canCancel' => $canCancel, 'view' => $view]); ?>;
  </script>
  <script src="../assets/js/print-jobs.js?v=<?php echo $v('js/print-jobs.js'); ?>"></script>
</body>
</html>
