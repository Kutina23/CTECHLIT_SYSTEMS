<?php
require_once __DIR__ . '/config/functions.php';

$ticketTableReady = Database::tableExists('support_tickets')
    && (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'support_tickets'
           AND column_name IN ('requester_name', 'requester_email')"
    ) === 2;
$csrfToken = generateCsrfToken();
$formError = '';
$fieldValues = ['requester_name' => '', 'requester_email' => '', 'subject' => '', 'category' => 'general', 'description' => ''];
$confirmation = $_SESSION['public_ticket_confirmation'] ?? null;
$confirmationSubmitted = !empty($_SESSION['public_ticket_submitted']);
unset($_SESSION['public_ticket_confirmation'], $_SESSION['public_ticket_submitted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fieldValues as $field => $_) {
        $fieldValues[$field] = trim((string) ($_POST[$field] ?? ''));
    }
    if (!$ticketTableReady) {
        $formError = 'Ticket submission is temporarily unavailable. Please contact us by phone or email.';
    } elseif (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $formError = 'Your form session expired. Please reload the page and try again.';
    } elseif (!empty($_POST['website'])) {
        $_SESSION['public_ticket_submitted'] = true;
        header('Location: support-tickets.php');
        exit;
    } elseif (time() - (int) ($_SESSION['public_ticket_last_submission'] ?? 0) < 30) {
        $formError = 'Please wait a short time before submitting another ticket.';
    } elseif (
        $fieldValues['requester_name'] === '' || strlen($fieldValues['requester_name']) > 100 ||
        !filter_var($fieldValues['requester_email'], FILTER_VALIDATE_EMAIL) || strlen($fieldValues['requester_email']) > 255 ||
        $fieldValues['subject'] === '' || strlen($fieldValues['subject']) > 255 ||
        $fieldValues['description'] === '' || strlen($fieldValues['description']) > 10000 ||
        !in_array($fieldValues['category'], ['technical', 'billing', 'account', 'general'], true)
    ) {
        $formError = 'Please enter a valid name, email, subject, category, and description.';
    } else {
        try {
            $ticketId = Database::insert(
                "INSERT INTO support_tickets
                    (user_id, requester_name, requester_email, subject, description, category, priority, status)
                 VALUES (NULL, ?, ?, ?, ?, ?, 'medium', 'open')",
                [
                    $fieldValues['requester_name'],
                    $fieldValues['requester_email'],
                    $fieldValues['subject'],
                    $fieldValues['description'],
                    $fieldValues['category'],
                ]
            );
            $_SESSION['public_ticket_last_submission'] = time();
            $_SESSION['public_ticket_confirmation'] = (int) $ticketId;
            $_SESSION['public_ticket_submitted'] = true;
            header('Location: support-tickets.php');
            exit;
        } catch (Throwable $error) {
            error_log('Public support ticket submission failed: ' . $error->getMessage());
            $formError = 'We could not submit your ticket right now. Please try again later or contact us directly.';
        }
    }
}
$seoTitle = 'Support Tickets — ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$seoDescription = 'Submit a support ticket to ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS') . '. Tell us about technical issues, billing questions, or account support for our Daffiama digital centre.';
$seoCanonical = '/support-tickets.php';
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
<body class="public-ticket-open">
  <?php require __DIR__ . '/includes/public-help-header.php'; ?>
  <main class="public-ticket-overlay">
    <section class="public-ticket-dialog" role="dialog" aria-modal="true" aria-labelledby="ticket-title">
      <aside class="public-ticket-aside">
        <div class="public-ticket-brand"><span class="public-ticket-brand-icon"><i class="fas fa-headset" aria-hidden="true"></i></span>Help Center</div>
        <div>
          <p class="public-ticket-kicker">We're here to help</p>
          <h1>Let's get this sorted.</h1>
          <p class="public-ticket-intro">Send our team a support request. Share a few details and we'll follow up by email.</p>
        </div>
        <div class="public-ticket-steps">
          <div><span><i class="fas fa-pen-to-square" aria-hidden="true"></i></span><p><strong>Tell us what's going on</strong><small>A clear description helps us get started.</small></p></div>
          <div><span><i class="fas fa-inbox" aria-hidden="true"></i></span><p><strong>We review your request</strong><small>Our support team will take it from here.</small></p></div>
          <div><span><i class="fas fa-envelope" aria-hidden="true"></i></span><p><strong>Hear back from us</strong><small>We'll use your email address to follow up.</small></p></div>
        </div>
        <a class="public-ticket-back" href="contact.php"><i class="fas fa-arrow-left" aria-hidden="true"></i> Back to contact options</a>
      </aside>

      <div class="public-ticket-content">
        <a class="public-ticket-close" href="contact.php" aria-label="Close ticket form and return to Contact Us"><i class="fas fa-xmark" aria-hidden="true"></i></a>
        <header class="public-ticket-heading">
          <p class="public-ticket-kicker">Support request</p>
          <h2 id="ticket-title">Submit a ticket</h2>
          <p>Complete the form below and we'll be in touch.</p>
        </header>
        <?php if ($confirmationSubmitted): ?>
          <p class="public-help-notice" role="status">
            Thank you. <?php if ($confirmation): ?>Your ticket reference is <strong>#SUP-<?= (int) $confirmation ?></strong>.<?php else: ?>If your request needs attention, our team will follow up.<?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if ($formError !== ''): ?>
          <p class="public-help-error" role="alert"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form class="public-help-form public-ticket-form" method="post" action="support-tickets.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="public-help-honeypot" aria-hidden="true">
              <label for="website">Leave this field empty</label>
              <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="public-ticket-fields">
              <label for="requester-name">Your name
                <input id="requester-name" name="requester_name" maxlength="100" autocomplete="name" required value="<?= htmlspecialchars($fieldValues['requester_name'], ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label for="requester-email">Email address
                <input id="requester-email" name="requester_email" type="email" maxlength="255" autocomplete="email" required value="<?= htmlspecialchars($fieldValues['requester_email'], ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label for="ticket-category">Category
                <select id="ticket-category" name="category" required>
                  <?php foreach (['general' => 'General', 'technical' => 'Technical', 'billing' => 'Billing', 'account' => 'Account'] as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $fieldValues['category'] === $value ? ' selected' : '' ?>><?= $label ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label for="ticket-subject">Subject
                <input id="ticket-subject" name="subject" maxlength="255" required value="<?= htmlspecialchars($fieldValues['subject'], ENT_QUOTES, 'UTF-8') ?>">
              </label>
              <label class="public-ticket-message" for="ticket-description">How can we help?
                <textarea id="ticket-description" name="description" rows="5" maxlength="10000" required><?= htmlspecialchars($fieldValues['description'], ENT_QUOTES, 'UTF-8') ?></textarea>
                <small>Include any details that could help us understand the issue.</small>
              </label>
            </div>
            <?php if ($ticketTableReady): ?>
              <button class="btn btn-primary public-ticket-submit" type="submit"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submit support request</button>
            <?php else: ?>
              <button class="btn btn-primary public-ticket-submit" type="submit" disabled aria-describedby="ticket-unavailable-message"><i class="fas fa-paper-plane" aria-hidden="true"></i> Submission unavailable</button>
            <?php endif; ?>
            <p class="public-ticket-privacy"><i class="fas fa-lock" aria-hidden="true"></i> Your contact details are only used to respond to this request.</p>
          </form>
        <?php if (!$ticketTableReady): ?>
          <p class="public-help-error" id="ticket-unavailable-message" role="alert">Ticket submission is temporarily unavailable. Please contact us by phone or email.</p>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script src="assets/js/public-help-nav.js"></script>
</body>
</html>
