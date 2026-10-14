<?php
require_once __DIR__ . '/config/functions.php';

$phone = (string) siteSetting('contact_phone', '+233 24 123 4567');
$phoneRaw = preg_replace('/[^0-9+]/', '', (string) siteSetting('contact_phone_raw', $phone));
$email = (string) siteSetting('contact_email', 'ctechlit@gmail.com');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $email = 'ctechlit@gmail.com';
}
$address = (string) siteSetting('contact_address', 'Central Daffiama');
$hours = (string) siteSetting('opening_hours', 'Monday – Saturday');
$seoTitle = 'Contact Us — ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$seoDescription = 'Get in touch with ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS') . '. Call, email, or visit us in ' . $address . ' for internet, printing, and digital services.';
$seoCanonical = '/contact.php';
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
        <div class="eyebrow">We're here to help</div>
        <h1>Contact Us</h1>
        <p>Reach out to our team or send us a detailed support request.</p>
      </header>
      <div class="public-resource-list public-contact-list">
        <article class="public-resource-card">
          <h2><i class="fas fa-phone" aria-hidden="true"></i> Phone</h2>
          <p><a href="tel:<?= htmlspecialchars($phoneRaw, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') ?></a></p>
        </article>
        <article class="public-resource-card">
          <h2><i class="fas fa-envelope" aria-hidden="true"></i> Email</h2>
          <p><a href="mailto:<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?></a></p>
        </article>
        <article class="public-resource-card">
          <h2><i class="fas fa-location-dot" aria-hidden="true"></i> Visit Us</h2>
          <p><?= htmlspecialchars($address, ENT_QUOTES, 'UTF-8') ?></p>
          <p><strong>Hours:</strong> <?= htmlspecialchars($hours, ENT_QUOTES, 'UTF-8') ?></p>
        </article>
      </div>
      <p class="public-contact-ticket"><a class="btn btn-primary" href="support-tickets.php">Submit a support ticket</a></p>
    </div>
  </main>
  <script src="assets/js/public-help-nav.js"></script>
</body>
</html>
