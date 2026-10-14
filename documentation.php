<?php
require_once __DIR__ . '/config/functions.php';

$helpTableExists = Database::tableExists('help_resources');
$documentation = $helpTableExists
    ? Database::fetchAll(
        "SELECT title, content, url FROM help_resources
         WHERE resource_type = 'documentation' AND is_published = 1
         ORDER BY sort_order, title"
    )
    : [];
$faqs = $helpTableExists
    ? Database::fetchAll(
        "SELECT title, content, url FROM help_resources
         WHERE resource_type = 'faq' AND is_published = 1
         ORDER BY sort_order, title"
    )
    : [];

function publicHelpSafeUrl(?string $url): ?string
{
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        return preg_match('#^uploads/help-center/[a-f0-9]{32}\.(pdf|docx|txt)$#', (string) $url) ? $url : null;
    }
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($scheme, ['http', 'https'], true) ? $url : null;
}

function renderPublicHelpResources(array $resources): void
{
    if (!$resources) {
        echo '<p class="public-help-empty">No content is published here yet.</p>';
        return;
    }
    foreach ($resources as $resource) {
        echo '<article class="public-resource-card">';
        echo '<h2>' . htmlspecialchars($resource['title'], ENT_QUOTES, 'UTF-8') . '</h2>';
        if (trim((string) $resource['content']) !== '') {
            echo '<p>' . htmlspecialchars($resource['content'], ENT_QUOTES, 'UTF-8') . '</p>';
        }
        $url = publicHelpSafeUrl($resource['url'] ?? null);
        if ($url !== null) {
            $label = str_starts_with($url, 'uploads/help-center/') ? 'Open document' : 'Learn more';
            echo '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . $label . ' <i class="fas fa-arrow-up-right-from-square" aria-hidden="true"></i></a>';
        }
        echo '</article>';
    }
}

$seoTitle = 'Documentation — ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$seoDescription = 'Guides, FAQs, and documentation to help you use ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS') . ' services. Get answers and learn more about our internet, printing, and digital services.';
$seoCanonical = '/documentation.php';
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
        <h1>Documentation</h1>
        <p>Guides and answers to help you use our services.</p>
      </header>
      <?php if (!$helpTableExists): ?>
        <p class="public-help-notice">Help content is temporarily unavailable. Please contact us for assistance.</p>
      <?php else: ?>
        <div class="public-resource-list"><?php renderPublicHelpResources($documentation); ?></div>
        <section class="public-faq-section">
          <header class="section-head"><h2>Frequently Asked Questions</h2></header>
          <div class="public-resource-list"><?php renderPublicHelpResources($faqs); ?></div>
        </section>
      <?php endif; ?>
    </div>
  </main>
  <script src="assets/js/public-help-nav.js"></script>
</body>
</html>
