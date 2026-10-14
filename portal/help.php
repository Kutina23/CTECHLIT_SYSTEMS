<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Help & Support
 * Help center and support resources
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$helpTableExists = Database::tableExists('help_resources');
$faqs = $helpTableExists
  ? Database::fetchAll(
    "SELECT title, content, url
     FROM help_resources
     WHERE resource_type = 'faq' AND is_published = 1
     ORDER BY sort_order, title"
  )
  : [];
$canManageHelp = hasPermission('settings.edit');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Help & Support — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="help-page">
        <?php if ($canManageHelp): ?>
          <div class="card glass-card" style="margin-bottom:20px;">
            <div class="card-header">
              <div>
                <div class="card-title">Publish Help Content</div>
                <div class="card-subtitle">Create public guides and tutorial videos</div>
              </div>
              <div style="display:flex; flex-wrap:wrap; gap:8px;">
                <a class="btn btn-primary" href="admin/help.php?create=documentation"><i class="fas fa-book"></i> Create Documentation</a>
                <a class="btn btn-secondary" href="admin/help.php?create=tutorial"><i class="fas fa-video"></i> Create Tutorial Video</a>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Quick Help Cards -->
        <div class="help-card-grid">
          <a href="../documentation.php" class="help-card help-card-violet">
            <div class="help-card-icon"><i class="fas fa-book"></i></div>
            <div class="help-card-body">
              <div class="help-card-title">Documentation</div>
              <div class="help-card-subtitle">Read the docs</div>
            </div>
          </a>

          <a href="../tutorials.php" class="help-card help-card-cyan">
            <div class="help-card-icon"><i class="fas fa-video"></i></div>
            <div class="help-card-body">
              <div class="help-card-title">Tutorials</div>
              <div class="help-card-subtitle">Watch videos</div>
            </div>
          </a>

          <a href="../contact.php" class="help-card help-card-green">
            <div class="help-card-icon"><i class="fas fa-envelope"></i></div>
            <div class="help-card-body">
              <div class="help-card-title">Contact Us</div>
              <div class="help-card-subtitle">Get in touch</div>
            </div>
          </a>

          <a href="../support-tickets.php" class="help-card help-card-amber">
            <div class="help-card-icon"><i class="fas fa-ticket-alt"></i></div>
            <div class="help-card-body">
              <div class="help-card-title">Support Tickets</div>
              <div class="help-card-subtitle">View tickets</div>
            </div>
          </a>
        </div>

        <!-- FAQ Section -->
        <div class="card glass-card help-faq-card">
          <div class="card-header">
            <div>
              <div class="card-title">Frequently Asked Questions</div>
              <div class="card-subtitle">Common questions and answers</div>
            </div>
            <?php if ($canManageHelp): ?>
              <a class="btn btn-secondary" href="admin/help.php"><i class="fas fa-edit"></i> Manage Help Content</a>
            <?php endif; ?>
          </div>

          <div class="help-faq-list">
            <?php if (!$helpTableExists): ?>
              <div class="help-status help-status-warning">
                <i class="fas fa-triangle-exclamation"></i>
                <span>Help content is not available until the help center migration is installed.</span>
              </div>
            <?php elseif (!$faqs): ?>
              <div class="help-status help-status-info">
                <i class="fas fa-info-circle"></i>
                <span>No FAQs have been published yet.</span>
              </div>
            <?php else: ?>
              <?php foreach ($faqs as $faq): ?>
                <details class="help-faq-item">
                  <summary class="help-faq-summary"><?php echo htmlspecialchars($faq['title'], ENT_QUOTES, 'UTF-8'); ?></summary>
                  <div class="help-faq-content">
                    <p><?php echo nl2br(htmlspecialchars($faq['content'], ENT_QUOTES, 'UTF-8')); ?></p>
                    <?php if (!empty($faq['url'])): ?>
                      <a class="help-faq-link" href="<?php echo htmlspecialchars($faq['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
                        Learn more <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i>
                      </a>
                    <?php endif; ?>
                  </div>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
</body>
</html>
