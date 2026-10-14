<?php
$publicHelpBusinessName = siteSetting('business_name', 'CTECHLIT & SYSTEMS');
?>
<canvas id="bg-canvas"></canvas>
<div class="veil"></div>
<nav id="nav">
  <div class="wrap">
    <a class="brand" href="index.php"><span class="dot"></span><?= sanitize($publicHelpBusinessName) ?></a>
    <button class="hamburger" type="button" aria-label="Open menu" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>
    <div class="navlinks">
      <a href="index.php#services">Services</a>
      <a href="index.php#visit">Visit Us</a>
      <div class="help-nav-dropdown">
        <button class="help-nav-trigger" type="button" aria-expanded="false" aria-controls="publicHelpMenu">
          Help <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="help-nav-menu" id="publicHelpMenu">
          <a href="documentation.php"><i class="fas fa-book-open" aria-hidden="true"></i><span><strong>Documentation</strong><small>Read the docs</small></span></a>
          <a href="tutorials.php"><i class="fas fa-video" aria-hidden="true"></i><span><strong>Tutorials</strong><small>Watch videos</small></span></a>
          <a href="contact.php"><i class="fas fa-envelope" aria-hidden="true"></i><span><strong>Contact Us</strong><small>Get in touch</small></span></a>
          <a href="support-tickets.php"><i class="fas fa-ticket-alt" aria-hidden="true"></i><span><strong>Submit a ticket</strong><small>Get help from our team</small></span></a>
          <a href="feedback.php"><i class="fas fa-star" aria-hidden="true"></i><span><strong>Share Feedback</strong><small>Rate your experience</small></span></a>
        </div>
      </div>
    </div>
    <a href="contact.php" class="btn btn-primary">Contact Us</a>
  </div>
</nav>
<script src="assets/js/button-loading.js" defer></script>
