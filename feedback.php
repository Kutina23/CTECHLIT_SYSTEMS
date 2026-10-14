<?php
require_once __DIR__ . '/config/functions.php';

$feedbackTableReady = Database::tableExists('feedback')
    && (int) Database::fetchColumn(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = DATABASE() AND table_name = 'feedback'
           AND column_name = 'customer_name'"
    ) === 1;
$csrfToken = generateCsrfToken();
$formError = '';
$fieldValues = ['customer_name' => '', 'category' => 'service', 'rating' => '', 'comment' => ''];
$confirmationSubmitted = !empty($_SESSION['public_feedback_submitted']);
unset($_SESSION['public_feedback_submitted']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($fieldValues as $field => $_) {
        $fieldValues[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $rating = filter_var($fieldValues['rating'], FILTER_VALIDATE_INT);
    if (!$feedbackTableReady) {
        $formError = 'Feedback submission is temporarily unavailable. Please try again later.';
    } elseif (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $formError = 'Your form session expired. Please reload the page and try again.';
    } elseif (!empty($_POST['website'])) {
        $_SESSION['public_feedback_submitted'] = true;
        header('Location: feedback.php');
        exit;
    } elseif (time() - (int) ($_SESSION['public_feedback_last_submission'] ?? 0) < 30) {
        $formError = 'Please wait a short time before submitting more feedback.';
    } elseif (
        strlen($fieldValues['customer_name']) > 100 ||
        !in_array($fieldValues['category'], ['service', 'station', 'staff', 'cleanliness', 'other'], true) ||
        $rating === false || $rating < 1 || $rating > 5 ||
        $fieldValues['comment'] === '' || strlen($fieldValues['comment']) > 5000
    ) {
        $formError = 'Please enter a valid name, feedback topic, star rating, and comment.';
    } else {
        try {
            Database::insert(
                "INSERT INTO feedback (customer_name, rating, category, comment)
                 VALUES (?, ?, ?, ?)",
                [
                    $fieldValues['customer_name'] !== '' ? $fieldValues['customer_name'] : null,
                    $rating,
                    $fieldValues['category'],
                    $fieldValues['comment'],
                ]
            );
            $_SESSION['public_feedback_last_submission'] = time();
            $_SESSION['public_feedback_submitted'] = true;
            header('Location: feedback.php');
            exit;
        } catch (Throwable $error) {
            error_log('Public feedback submission failed: ' . $error->getMessage());
            $formError = 'We could not submit your feedback right now. Please try again later.';
        }
    }
}
$seoTitle = 'Share Feedback — ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$seoDescription = 'Share your experience at ' . siteSetting('business_name', 'CTECHLIT & SYSTEMS') . '. Rate our services, stations, staff, and help us improve our digital centre in Daffiama.';
$seoCanonical = '/feedback.php';
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
        <div class="eyebrow">Your experience matters</div>
        <h1>Share your feedback</h1>
        <p>Tell us how we did. Your rating and comments help us improve our services.</p>
      </header>
      <?php if ($confirmationSubmitted): ?>
        <p class="public-help-notice" role="status">Thank you for sharing your feedback with us.</p>
      <?php endif; ?>
      <?php if ($formError !== ''): ?>
        <p class="public-help-error" role="alert"><?= htmlspecialchars($formError, ENT_QUOTES, 'UTF-8') ?></p>
      <?php endif; ?>
      <?php if ($feedbackTableReady): ?>
        <form class="public-help-form" method="post" action="feedback.php">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
          <div class="public-help-honeypot" aria-hidden="true">
            <label for="website">Leave this field empty</label>
            <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
          </div>
          <label>Your name (optional)
            <input name="customer_name" maxlength="100" autocomplete="name" value="<?= htmlspecialchars($fieldValues['customer_name'], ENT_QUOTES, 'UTF-8') ?>">
          </label>
          <label>What would you like to give feedback about?
            <select name="category" required>
              <?php foreach (['service' => 'Our services', 'station' => 'Browsing stations', 'staff' => 'Staff', 'cleanliness' => 'Cleanliness', 'other' => 'Other'] as $value => $label): ?>
                <option value="<?= $value ?>"<?= $fieldValues['category'] === $value ? ' selected' : '' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <fieldset class="feedback-rating">
            <legend>Your star rating</legend>
            <div class="feedback-stars">
              <?php for ($star = 5; $star >= 1; $star--): ?>
                <input type="radio" id="rating-<?= $star ?>" name="rating" value="<?= $star ?>" required<?= $fieldValues['rating'] === (string) $star ? ' checked' : '' ?>>
                <label for="rating-<?= $star ?>" aria-label="<?= $star ?> star<?= $star === 1 ? '' : 's' ?>">★</label>
              <?php endfor; ?>
            </div>
          </fieldset>
          <label>Your feedback
            <textarea name="comment" rows="6" maxlength="5000" required><?= htmlspecialchars($fieldValues['comment'], ENT_QUOTES, 'UTF-8') ?></textarea>
          </label>
          <button class="btn btn-primary" type="submit">Submit Feedback</button>
        </form>
      <?php else: ?>
        <p class="public-help-error" role="alert">Feedback submission is temporarily unavailable. Please try again later.</p>
      <?php endif; ?>
    </div>
  </main>
  <script src="assets/js/public-help-nav.js"></script>
</body>
</html>
