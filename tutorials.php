<?php
require_once __DIR__ . '/config/functions.php';

$helpTableExists = Database::tableExists('help_resources');
$tutorials = $helpTableExists
    ? Database::fetchAll(
        "SELECT title, content, url FROM help_resources
         WHERE resource_type = 'tutorial' AND is_published = 1
         ORDER BY sort_order, title"
    )
    : [];

/** Only http(s) links or files uploaded through the Help Center are linked. */
function tutorialSafeUrl(?string $url): ?string
{
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        return preg_match('#^uploads/help-center/[a-f0-9]{32}\.(pdf|docx|txt|mp4|webm)$#', (string) $url) ? $url : null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

$seoTitle = 'Tutorials — ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$seoDescription = 'Step-by-step video tutorials for the services at ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS') . '.';
$seoCanonical = '/tutorials.php';
$seoImage = rtrim((string) ($GLOBALS['config']['app']['url'] ?? 'http://localhost'), '/') . '/assets/images/logo.svg';
$seoType = 'website';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= sanitize($seoTitle) ?></title>
  <link rel="icon" type="image/svg+xml" href="assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="assets/css/custom.css">
  <?php require __DIR__ . '/includes/seo.php'; ?>
</head>
<body>
  <?php require __DIR__ . '/includes/public-help-header.php'; ?>
  <main class="public-help-page">
    <div class="wrap">
      <header class="section-head">
        <div class="eyebrow">Help Center</div>
        <h1>Tutorials</h1>
        <p>Short walkthroughs for the things people ask us most.</p>
      </header>
      <?php if (!$helpTableExists): ?>
        <p class="public-help-notice">Tutorials are temporarily unavailable. Please contact us for assistance.</p>
      <?php elseif (!$tutorials): ?>
        <p class="public-help-empty">No tutorials are published yet. See the <a href="documentation.php">documentation</a> or <a href="contact.php">contact us</a>.</p>
      <?php else: ?>
        <div class="public-resource-list">
          <?php foreach ($tutorials as $tutorial): ?>
            <?php $url = tutorialSafeUrl($tutorial['url'] ?? null); ?>
            <article class="public-resource-card">
              <h2><?= sanitize($tutorial['title']) ?></h2>
              <?php if (trim((string) $tutorial['content']) !== ''): ?><p><?= sanitize($tutorial['content']) ?></p><?php endif; ?>
              <?php if ($url !== null && preg_match('#^uploads/help-center/.+\.(mp4|webm)$#', $url)): ?>
                <video controls preload="metadata" style="width:100%;margin-top:14px;border-radius:12px"><source src="<?= sanitize($url) ?>"></video>
              <?php elseif ($url !== null): ?>
                <a href="<?= sanitize($url) ?>" target="_blank" rel="noopener noreferrer">Open tutorial <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <script src="assets/js/public-help-nav.js"></script>
</body>
</html>
