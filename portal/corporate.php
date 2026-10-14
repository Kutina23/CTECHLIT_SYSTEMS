<?php
/**
 * Brand Studio — on-brand flyers & social posts, presentations, letterheads and business cards.
 * The page is a thin shell; assets/js/brand-studio.js renders the library and editor.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('branded_assets.view');

$currentStaffMember = currentStaff();
$asset = static fn(string $path): string => '../' . $path . '?v=' . filemtime(__DIR__ . '/../' . $path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Brand Studio — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <link rel="stylesheet" href="<?php echo $asset('assets/css/brand-studio.css'); ?>">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <div class="portal-content">
      <div id="brandStudio" aria-live="polite"><div class="bs-empty">Loading Brand Studio…</div></div>
    </div>
  </main>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
    window.BRAND_STUDIO = {
      api: <?php echo json_encode(base_path('api/branded-assets.php')); ?>,
      staffName: <?php echo json_encode($currentStaffMember['full_name'] ?? ''); ?>
    };
  </script>
  <script src="<?php echo $asset('assets/js/brand-render.js'); ?>"></script>
  <script src="<?php echo $asset('assets/js/brand-export.js'); ?>"></script>
  <script src="<?php echo $asset('assets/js/brand-studio.js'); ?>"></script>
</body>
</html>
