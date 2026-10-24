<?php
/**
 * CTECHLIT & SYSTEMS — Public landing page
 * Every figure, service, rate and contact detail below is read from MySQL.
 */
require_once __DIR__ . '/config/functions.php';

$businessName = siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$businessTagline = siteSetting('business_tagline', 'Internet Café & Digital Services');
$maintenanceMode = false;
try {
    $maintenanceValue = Database::fetchColumn(
        'SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1',
        ['maintenance_mode']
    );
    $maintenanceMode = in_array(strtolower((string) $maintenanceValue), ['1', 'true', 'yes', 'on'], true);
} catch (Throwable $e) {
    logWarning('Unable to load public maintenance status: ' . $e->getMessage());
}
$contactPhone = siteSetting('contact_phone', '+233 24 123 4567');
$contactPhoneRaw = siteSetting('contact_phone_raw', '233241234567');
$contactEmail = siteSetting('contact_email', 'ctechlit@gmail.com');
$visitAddress = siteSetting('contact_address', 'Central Daffiama, Daffiama-Bussie-Issa District, Upper West Region');
$mapUrl = siteSetting(
    'map_url',
    'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($visitAddress)
);
$mapEmbedUrl = 'https://maps.google.com/maps?q=' . rawurlencode($visitAddress) . '&output=embed';

$heroTitle = siteSetting('hero_title', 'A faster, friendlier place to browse, print and get things done');
$heroHighlight = siteSetting('hero_highlight', 'faster, friendlier');
$heroTitleHtml = sanitize($heroTitle);
if ($heroHighlight !== '' && stripos($heroTitle, $heroHighlight) !== false) {
    $heroTitleHtml = str_ireplace(
        sanitize($heroHighlight),
        '<em>' . sanitize($heroHighlight) . '</em>',
        $heroTitleHtml
    );
}

$services = siteServices();
$plans = sitePricingPlans();
$testimonials = siteTestimonials();
$faqs = siteFaqs();
$trustBadges = siteTrustBadges();
$howSteps = siteHowItWorks();
$roadmapItems = siteRoadmapItems();
$personas = sitePersonas();
$galleryItems = siteGalleryItems();
$spotlightItems = siteSpotlightItems();
$whyFeatures = siteWhyFeatures();
$aboutFeatures = siteAboutFeatures();

// "Gaming passes" shows plans that include a gaming service; the gaming
// service itself is appended when the catalogue has too few pass rows.
$gamingPlans = array_values(array_filter($plans, function (array $plan): bool {
    return $plan['is_gaming'];
}));
if (count($gamingPlans) < 2) {
    foreach ($services as $service) {
        if ($service['category'] !== 'gaming') {
            continue;
        }
        $gamingPlans[] = [
            'name' => $service['name'],
            'price_label' => $service['price_label'],
            'unit_label' => $service['unit_label'],
            'description' => $service['description'] ?: 'Play on a dedicated gaming bay.',
        ];
    }
}

$stations = siteStations();
$stationStats = siteStationStats();
$live = siteLiveStats();
$gamingFree = 0;
foreach ($stations as $station) {
    if ($station['is_gaming'] && $station['status'] === 'available') {
        $gamingFree++;
    }
}
// Only counts are shown publicly — never customers' file names or stations.
$printQueue = 0;
try {
    $printQueue = (int) Database::fetchColumn("SELECT COUNT(*) FROM print_jobs WHERE status IN ('queued','printing')");
} catch (Throwable $e) {
    $printQueue = 0;
}
$openStatus = siteOpenStatus();
$openingHours = siteSetting('opening_hours', 'Monday – Saturday');
$serviceCategories = [];
foreach ($services as $service) {
    $key = (string) ($service['category'] ?? 'other');
    $serviceCategories[$key] = ucwords(str_replace('_', ' ', $key));
}
$assetVersion = static fn(string $file): string => $file . '?v=' . (@filemtime(__DIR__ . '/' . $file) ?: '1');
$brandLogo = '<svg viewBox="0 0 64 64" width="64" height="64" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="iconGrad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#7C4DFF"/><stop offset="100%" stop-color="#B43FE0"/></linearGradient></defs><circle cx="32" cy="32" r="30" fill="rgba(255,255,255,0.8)" stroke="url(#iconGrad)" stroke-width="2"/><path d="M38 14 L38 24 L48 24" fill="none" stroke="#2FD9EE" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M26 40 L26 50 L16 50" fill="none" stroke="#7C4DFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><path d="M38 14 A18 18 0 0 0 26 50" fill="none" stroke="url(#iconGrad)" stroke-width="3" stroke-linecap="round"/><circle cx="38" cy="14" r="3.5" fill="#2FD9EE"/><circle cx="48" cy="24" r="3.5" fill="#7C4DFF"/><circle cx="26" cy="50" r="3.5" fill="#B43FE0"/><circle cx="16" cy="50" r="3.5" fill="#2FD9EE"/><circle cx="32" cy="32" r="5" fill="url(#iconGrad)"/><circle cx="32" cy="32" r="2" fill="#fff"/></svg>';

$seoTitle = $businessName . ' — ' . $businessTagline;
$seoDescription = siteSetting('footer_about', 'Digital access, ICT training and technology support.') . ' ' . $visitAddress . '.';
$seoCanonical = '/';
$seoImage = rtrim((string) ($GLOBALS['config']['app']['url'] ?? 'http://localhost'), '/') . '/assets/images/logo.svg';
$seoType = 'website';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= sanitize($businessName) ?> — <?= sanitize($businessTagline) ?></title>
<link rel="icon" type="image/svg+xml" href="assets/images/logo-icon.svg">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<meta name="theme-color" content="#EEF2FF">
<link rel="stylesheet" href="<?= $assetVersion('assets/css/custom.css') ?>">
<link rel="stylesheet" href="<?= $assetVersion('assets/css/landing.css') ?>">
<?php require __DIR__ . '/includes/seo.php'; ?>
<?php if ($maintenanceMode): ?>
<style>
  .maintenance-status { position: relative; z-index: 20; padding: 12px 20px; color: #78350f; background: #fef3c7; border-bottom: 1px solid #f59e0b; text-align: center; font-weight: 600; }
  .maintenance-status i { margin-right: 8px; }
</style>
<?php endif; ?>
</head>
<body>

<?php if ($maintenanceMode): ?>
<div class="maintenance-status" role="status">
  <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
  Maintenance mode is active. Some services may be temporarily unavailable.
</div>
<?php endif; ?>

<canvas id="bg-canvas"></canvas>
<div class="veil"></div>

<a class="skip-link" href="#services">Skip to content</a>
<nav id="nav" aria-label="Main">
  <div class="wrap nav-shell">
    <a href="#" class="brand">
      <svg viewBox="0 0 400 80" width="180" height="36" xmlns="http://www.w3.org/2000/svg">
        <defs>
          <linearGradient id="logoGradNav" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#7C4DFF"/>
            <stop offset="50%" stop-color="#2FD9EE"/>
            <stop offset="100%" stop-color="#B43FE0"/>
          </linearGradient>
          <filter id="glowNav">
            <feGaussianBlur stdDeviation="1.5" result="b"/>
            <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
          </filter>
        </defs>
        <g transform="translate(4, 4)">
          <circle cx="28" cy="28" r="24" fill="none" stroke="url(#logoGradNav)" stroke-width="3" opacity="0.9"/>
          <path d="M28 8 L28 20 L40 20" fill="none" stroke="#2FD9EE" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <path d="M28 36 L28 48 L16 48" fill="none" stroke="#7C4DFF" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
          <circle cx="28" cy="8" r="3" fill="#2FD9EE" filter="url(#glowNav)"/>
          <circle cx="40" cy="20" r="3" fill="#7C4DFF" filter="url(#glowNav)"/>
          <circle cx="28" cy="48" r="3" fill="#B43FE0" filter="url(#glowNav)"/>
          <circle cx="16" cy="48" r="3" fill="#2FD9EE" filter="url(#glowNav)"/>
          <circle cx="28" cy="28" r="4" fill="url(#logoGradNav)"/>
        </g>
        <text x="72" y="34" font-family="'Space Grotesk', sans-serif" font-weight="700" font-size="20" fill="#F4F2FC">CTECHLIT<tspan font-weight="400" fill="#A79FC9">&amp;</tspan>SYSTEMS</text>
        <text x="72" y="50" font-family="'IBM Plex Sans', sans-serif" font-weight="400" font-size="8" fill="#2FD9EE" letter-spacing="1.2"><?= sanitize(mb_strtoupper($businessTagline)) ?></text>
      </svg>
    </a>
    <button class="hamburger" aria-label="Menu" aria-expanded="false" aria-controls="primaryNav">
      <span></span><span></span><span></span>
    </button>
    <div class="navlinks" id="primaryNav">
      <a href="#services" data-spy>Services</a><a href="#gaming" data-spy>Gaming</a><a href="#pricing" data-spy>Pricing</a><a href="#testimonials" data-spy>Reviews</a><a href="#faq" data-spy>FAQ</a><a href="#visit" data-spy>Visit</a>
      <div class="help-nav-dropdown">
        <button class="help-nav-trigger" type="button" aria-expanded="false" aria-controls="publicHelpMenu">
          Help <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="help-nav-menu" id="publicHelpMenu">
          <a href="documentation.php"><i class="fas fa-book-open" aria-hidden="true"></i><span><strong>Documentation</strong><small>Read the docs</small></span></a>
          <a href="tutorials.php"><i class="fas fa-video" aria-hidden="true"></i><span><strong>Tutorials</strong><small>Watch videos</small></span></a>
          <a href="contact.php"><i class="fas fa-envelope" aria-hidden="true"></i><span><strong>Contact Us</strong><small>Get in touch</small></span></a>
          <a href="support-tickets.php"><i class="fas fa-ticket-alt" aria-hidden="true"></i><span><strong>Submit a ticket</strong><small>Get help from our team</small></span></a>
        </div>
      </div>
    </div>
    <div class="nav-actions">
      <?php if ($openStatus): ?><span class="open-pill <?= $openStatus['open'] ? 'is-open' : 'is-closed' ?>" title="<?= sanitize($openingHours) ?>"><span class="dot"></span><?= sanitize($openStatus['label']) ?></span><?php endif; ?>
      <a href="<?= sanitize($mapUrl) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener"><i class="fa-solid fa-location-arrow" aria-hidden="true"></i> Directions</a>
    </div>
  </div>
</nav>

<section id="hero" class="parallax-section">
  <div class="parallax-bg" data-parallax-speed="0.3"></div>
  <video class="hero-video" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1550684848-fac1c5b4e853?w=1920&q=80">
    <source src="https://assets.mixkit.co/videos/preview/mixkit-abstract-technology-network-connections-27611-large.mp4" type="video/mp4">
  </video>
  <div class="hero-overlay"></div>
  <div class="hero-stars"></div>
  <div class="wrap hero-grid">
    <div class="stagger-text">
      <div class="hero-badge d0"><span class="hero-badge-dot"></span><?= sanitize(siteSetting('hero_eyebrow', $businessTagline)) ?><?php if ($openStatus): ?><span class="hero-badge-sep"></span><span class="<?= $openStatus['open'] ? 'txt-open' : 'txt-closed' ?>"><?= sanitize($openStatus['label']) ?></span><span class="hb-detail">· <?= sanitize($openStatus['detail']) ?></span><?php endif; ?></div>
      <h1 class="reveal in d1"><?= $heroTitleHtml ?></h1>
      <p class="lead reveal in d2"><?= sanitize(siteSetting('hero_lead', 'Fast machines, a comfortable space, and staff who actually help.')) ?></p>
      <div class="hero-ctas reveal in d3">
        <a href="#services" class="btn btn-primary">Explore services <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        <a href="#pricing" class="btn btn-ghost">See prices</a>
      </div>
      <div class="hero-stats reveal in d4" aria-label="Live café status">
        <div class="hero-stat"><i class="fa-solid fa-desktop" aria-hidden="true"></i><div><b><?= (int) $stationStats['total'] ?></b><span>Client stations</span></div></div>
        <div class="hero-stat"><i class="fa-solid fa-circle-check" aria-hidden="true"></i><div><b><?= (int) $stationStats['available'] ?></b><span>Free right now</span></div></div>
        <div class="hero-stat"><i class="fa-solid fa-bolt" aria-hidden="true"></i><div><b><?= (int) $live['active_sessions'] ?></b><span>Sessions running</span></div></div>
      </div>
      <ul class="hero-chips reveal in d5" aria-label="Popular services">
        <?php foreach (array_slice($services, 0, 5) as $service): ?>
        <li><i class="<?= sanitize($service['icon']) ?>" aria-hidden="true"></i><?= sanitize($service['name']) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="hero-visual reveal right">
      <div class="glow"></div>
      <div class="hero-orb">
        <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <radialGradient id="orbGlow" cx="50%" cy="50%" r="50%">
      <stop offset="0%" stop-color="#7C4DFF" stop-opacity=".55"/>
      <stop offset="100%" stop-color="#7C4DFF" stop-opacity="0"/>
    </radialGradient>
    <filter id="orbBlur" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur stdDeviation="6"/>
    </filter>
  </defs>
  <circle cx="200" cy="200" r="190" fill="url(#orbGlow)"/>
  <circle cx="200" cy="200" r="172" fill="none" stroke="#2FD9EE" stroke-opacity=".3" stroke-width="10" filter="url(#orbBlur)"/>
  <polygon class="facet dly-290" points="133.1,48.7 133.1,66.3 117.7,75.2 102.4,66.3 102.4,48.6 117.7,39.8" fill="rgb(190,245,255)" opacity="0.86" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="166.0,48.7 166.0,66.3 150.6,75.2 135.3,66.3 135.3,48.6 150.6,39.8" fill="rgb(190,245,255)" opacity="0.85" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="198.9,48.7 198.9,66.3 183.5,75.2 168.2,66.3 168.2,48.6 183.5,39.8" fill="rgb(186,241,255)" opacity="0.81" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="231.8,48.7 231.8,66.3 216.5,75.2 201.1,66.3 201.1,48.6 216.5,39.8" fill="rgb(167,222,255)" opacity="0.75" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="264.7,48.7 264.7,66.3 249.4,75.2 234.0,66.3 234.0,48.6 249.4,39.8" fill="rgb(144,200,255)" opacity="0.67" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-290" points="297.6,48.7 297.6,66.3 282.3,75.2 266.9,66.3 266.9,48.6 282.3,39.8" fill="rgb(21,15,61)" opacity="0.58" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="116.6,77.2 116.6,94.8 101.3,103.7 85.9,94.8 85.9,77.2 101.3,68.3" fill="rgb(190,245,255)" opacity="0.87" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-232" points="149.5,77.2 149.5,94.8 134.2,103.7 118.9,94.8 118.9,77.2 134.2,68.3" fill="rgb(190,245,255)" opacity="0.89" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="182.4,77.2 182.4,94.8 167.1,103.7 151.8,94.8 151.8,77.2 167.1,68.3" fill="rgb(190,245,255)" opacity="0.87" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-201" points="215.3,77.2 215.3,94.8 200.0,103.7 184.7,94.8 184.7,77.2 200.0,68.3" fill="rgb(177,233,255)" opacity="0.81" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="248.2,77.2 248.2,94.8 232.9,103.7 217.6,94.8 217.6,77.2 232.9,68.3" fill="rgb(150,206,255)" opacity="0.71" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-232" points="281.1,77.2 281.1,94.8 265.8,103.7 250.5,94.8 250.5,77.2 265.8,68.3" fill="rgb(41,27,100)" opacity="0.61" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="314.1,77.2 314.1,94.8 298.7,103.7 283.4,94.8 283.4,77.2 298.7,68.3" fill="rgb(29,20,77)" opacity="0.51" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="100.1,105.7 100.1,123.3 84.8,132.2 69.5,123.3 69.5,105.7 84.8,96.8" fill="rgb(190,245,255)" opacity="0.86" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="133.1,105.7 133.1,123.3 117.7,132.2 102.4,123.3 102.4,105.7 117.7,96.8" fill="rgb(190,245,255)" opacity="0.90" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-174" points="166.0,105.7 166.0,123.3 150.6,132.2 135.3,123.3 135.3,105.7 150.6,96.8" fill="rgb(190,245,255)" opacity="0.91" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="198.9,105.7 198.9,123.3 183.5,132.2 168.2,123.3 168.2,105.7 183.5,96.8" fill="rgb(190,245,255)" opacity="0.88" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="231.8,105.7 231.8,123.3 216.5,132.2 201.1,123.3 201.1,105.7 216.5,96.8" fill="rgb(160,215,255)" opacity="0.77" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-174" points="264.7,105.7 264.7,123.3 249.4,132.2 234.0,123.3 234.0,105.7 249.4,96.8" fill="rgb(62,39,138)" opacity="0.63" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="297.6,105.7 297.6,123.3 282.3,132.2 266.9,123.3 266.9,105.7 282.3,96.8" fill="rgb(50,32,115)" opacity="0.51" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="330.5,105.7 330.5,123.3 315.2,132.2 299.9,123.3 299.9,105.7 315.2,96.8" fill="rgb(34,22,86)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="83.7,134.2 83.7,151.8 68.4,160.7 53.0,151.8 53.0,134.2 68.4,125.3" fill="rgb(189,244,255)" opacity="0.82" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-201" points="116.6,134.2 116.6,151.8 101.3,160.7 85.9,151.8 85.9,134.2 101.3,125.3" fill="rgb(190,245,255)" opacity="0.87" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="149.5,134.2 149.5,151.8 134.2,160.7 118.9,151.8 118.9,134.2 134.2,125.3" fill="rgb(190,245,255)" opacity="0.92" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-116" points="182.4,134.2 182.4,151.8 167.1,160.7 151.8,151.8 151.8,134.2 167.1,125.3" fill="rgb(190,245,255)" opacity="0.94" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-101" points="215.3,134.2 215.3,151.8 200.0,160.7 184.7,151.8 184.7,134.2 200.0,125.3" fill="rgb(177,233,255)" opacity="0.86" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-116" points="248.2,134.2 248.2,151.8 232.9,160.7 217.6,151.8 217.6,134.2 232.9,125.3" fill="rgb(82,52,177)" opacity="0.66" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="281.1,134.2 281.1,151.8 265.8,160.7 250.5,151.8 250.5,134.2 265.8,125.3" fill="rgb(69,44,152)" opacity="0.50" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-201" points="314.1,134.2 314.1,151.8 298.7,160.7 283.4,151.8 283.4,134.2 298.7,125.3" fill="rgb(52,34,120)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="347.0,134.2 347.0,151.8 331.6,160.7 316.3,151.8 316.3,134.2 331.6,125.3" fill="rgb(34,22,86)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="67.2,162.7 67.2,180.3 51.9,189.2 36.6,180.3 36.6,162.7 51.9,153.8" fill="rgb(172,228,255)" opacity="0.76" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="100.1,162.7 100.1,180.3 84.8,189.2 69.5,180.3 69.5,162.7 84.8,153.8" fill="rgb(177,232,255)" opacity="0.80" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="133.1,162.7 133.1,180.3 117.7,189.2 102.4,180.3 102.4,162.7 117.7,153.8" fill="rgb(184,239,255)" opacity="0.86" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-101" points="166.0,162.7 166.0,180.3 150.6,189.2 135.3,180.3 135.3,162.7 150.6,153.8" fill="rgb(190,245,255)" opacity="0.92" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-058" points="198.9,162.7 198.9,180.3 183.5,189.2 168.2,180.3 168.2,162.7 183.5,153.8" fill="rgb(190,245,255)" opacity="0.97" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-058" points="231.8,162.7 231.8,180.3 216.5,189.2 201.1,180.3 201.1,162.7 216.5,153.8" fill="rgb(103,64,216)" opacity="0.69" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-101" points="264.7,162.7 264.7,180.3 249.4,189.2 234.0,180.3 234.0,162.7 249.4,153.8" fill="rgb(88,55,187)" opacity="0.50" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="297.6,162.7 297.6,180.3 282.3,189.2 266.9,180.3 266.9,162.7 282.3,153.8" fill="rgb(69,44,152)" opacity="0.47" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="330.5,162.7 330.5,180.3 315.2,189.2 299.9,180.3 299.9,162.7 315.2,153.8" fill="rgb(50,32,115)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="363.4,162.7 363.4,180.3 348.1,189.2 332.8,180.3 332.8,162.7 348.1,153.8" fill="rgb(29,20,77)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-290" points="50.8,191.2 50.8,208.8 35.5,217.7 20.1,208.8 20.1,191.2 35.5,182.3" fill="rgb(153,209,255)" opacity="0.68" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-232" points="83.7,191.2 83.7,208.8 68.4,217.7 53.0,208.8 53.0,191.2 68.4,182.3" fill="rgb(153,209,255)" opacity="0.71" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-174" points="116.6,191.2 116.6,208.8 101.3,217.7 85.9,208.8 85.9,191.2 101.3,182.3" fill="rgb(153,209,255)" opacity="0.74" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-116" points="149.5,191.2 149.5,208.8 134.2,217.7 118.9,208.8 118.9,191.2 134.2,182.3" fill="rgb(153,209,255)" opacity="0.77" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-058" points="182.4,191.2 182.4,208.8 167.1,217.7 151.8,208.8 151.8,191.2 167.1,182.3" fill="rgb(153,209,255)" opacity="0.80" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-000" points="215.3,191.2 215.3,208.8 200.0,217.7 184.7,208.8 184.7,191.2 200.0,182.3" fill="rgb(124,77,255)" opacity="0.55" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-058" points="248.2,191.2 248.2,208.8 232.9,217.7 217.6,208.8 217.6,191.2 232.9,182.3" fill="rgb(103,64,216)" opacity="0.52" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-116" points="281.1,191.2 281.1,208.8 265.8,217.7 250.5,208.8 250.5,191.2 265.8,182.3" fill="rgb(82,52,177)" opacity="0.49" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-174" points="314.1,191.2 314.1,208.8 298.7,217.7 283.4,208.8 283.4,191.2 298.7,182.3" fill="rgb(62,39,138)" opacity="0.46" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-232" points="347.0,191.2 347.0,208.8 331.6,217.7 316.3,208.8 316.3,191.2 331.6,182.3" fill="rgb(41,27,100)" opacity="0.43" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-290" points="379.9,191.2 379.9,208.8 364.5,217.7 349.2,208.8 349.2,191.2 364.5,182.3" fill="rgb(21,15,61)" opacity="0.40" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="67.2,219.7 67.2,237.3 51.9,246.2 36.6,237.3 36.6,219.7 51.9,210.8" fill="rgb(132,187,255)" opacity="0.62" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="100.1,219.7 100.1,237.3 84.8,246.2 69.5,237.3 69.5,219.7 84.8,210.8" fill="rgb(125,181,255)" opacity="0.63" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="133.1,219.7 133.1,237.3 117.7,246.2 102.4,237.3 102.4,219.7 117.7,210.8" fill="rgb(69,44,152)" opacity="0.62" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-101" points="166.0,219.7 166.0,237.3 150.6,246.2 135.3,237.3 135.3,219.7 150.6,210.8" fill="rgb(88,55,187)" opacity="0.56" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-058" points="198.9,219.7 198.9,237.3 183.5,246.2 168.2,237.3 168.2,219.7 183.5,210.8" fill="rgb(103,64,216)" opacity="0.52" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-058" points="231.8,219.7 231.8,237.3 216.5,246.2 201.1,237.3 201.1,219.7 216.5,210.8" fill="rgb(103,64,216)" opacity="0.52" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-101" points="264.7,219.7 264.7,237.3 249.4,246.2 234.0,237.3 234.0,219.7 249.4,210.8" fill="rgb(88,55,187)" opacity="0.50" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="297.6,219.7 297.6,237.3 282.3,246.2 266.9,237.3 266.9,219.7 282.3,210.8" fill="rgb(69,44,152)" opacity="0.47" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="330.5,219.7 330.5,237.3 315.2,246.2 299.9,237.3 299.9,219.7 315.2,210.8" fill="rgb(50,32,115)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="363.4,219.7 363.4,237.3 348.1,246.2 332.8,237.3 332.8,219.7 348.1,210.8" fill="rgb(29,20,77)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="83.7,248.2 83.7,265.9 68.4,274.7 53.0,265.9 53.0,248.2 68.4,239.3" fill="rgb(34,22,86)" opacity="0.54" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-201" points="116.6,248.2 116.6,265.9 101.3,274.7 85.9,265.9 85.9,248.2 101.3,239.3" fill="rgb(52,34,120)" opacity="0.51" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="149.5,248.2 149.5,265.9 134.2,274.7 118.9,265.9 118.9,248.2 134.2,239.3" fill="rgb(69,44,152)" opacity="0.47" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-116" points="182.4,248.2 182.4,265.9 167.1,274.7 151.8,265.9 151.8,248.2 167.1,239.3" fill="rgb(82,52,177)" opacity="0.49" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-101" points="215.3,248.2 215.3,265.9 200.0,274.7 184.7,265.9 184.7,248.2 200.0,239.3" fill="rgb(88,55,187)" opacity="0.50" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-116" points="248.2,248.2 248.2,265.9 232.9,274.7 217.6,265.9 217.6,248.2 232.9,239.3" fill="rgb(82,52,177)" opacity="0.49" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="281.1,248.2 281.1,265.9 265.8,274.7 250.5,265.9 250.5,248.2 265.8,239.3" fill="rgb(69,44,152)" opacity="0.47" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-201" points="314.1,248.2 314.1,265.9 298.7,274.7 283.4,265.9 283.4,248.2 298.7,239.3" fill="rgb(52,34,120)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="347.0,248.2 347.0,265.9 331.6,274.7 316.3,265.9 316.3,248.2 331.6,239.3" fill="rgb(34,22,86)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="100.1,276.6 100.1,294.4 84.8,303.2 69.5,294.4 69.5,276.6 84.8,267.8" fill="rgb(34,22,86)" opacity="0.43" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="133.1,276.6 133.1,294.4 117.7,303.2 102.4,294.4 102.4,276.6 117.7,267.8" fill="rgb(50,32,115)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-174" points="166.0,276.6 166.0,294.4 150.6,303.2 135.3,294.4 135.3,276.6 150.6,267.8" fill="rgb(62,39,138)" opacity="0.46" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="198.9,276.6 198.9,294.4 183.5,303.2 168.2,294.4 168.2,276.6 183.5,267.8" fill="rgb(69,44,152)" opacity="0.47" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-154" points="231.8,276.6 231.8,294.4 216.5,303.2 201.1,294.4 201.1,276.6 216.5,267.8" fill="rgb(69,44,152)" opacity="0.47" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-174" points="264.7,276.6 264.7,294.4 249.4,303.2 234.0,294.4 234.0,276.6 249.4,267.8" fill="rgb(62,39,138)" opacity="0.46" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="297.6,276.6 297.6,294.4 282.3,303.2 266.9,294.4 266.9,276.6 282.3,267.8" fill="rgb(50,32,115)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="330.5,276.6 330.5,294.4 315.2,303.2 299.9,294.4 299.9,276.6 315.2,267.8" fill="rgb(34,22,86)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="116.6,305.1 116.6,322.9 101.3,331.7 85.9,322.9 85.9,305.1 101.3,296.3" fill="rgb(29,20,77)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-232" points="149.5,305.1 149.5,322.9 134.2,331.7 118.9,322.9 118.9,305.1 134.2,296.3" fill="rgb(41,27,100)" opacity="0.43" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="182.4,305.1 182.4,322.9 167.1,331.7 151.8,322.9 151.8,305.1 167.1,296.3" fill="rgb(50,32,115)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-201" points="215.3,305.1 215.3,322.9 200.0,331.7 184.7,322.9 184.7,305.1 200.0,296.3" fill="rgb(52,34,120)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-209" points="248.2,305.1 248.2,322.9 232.9,331.7 217.6,322.9 217.6,305.1 232.9,296.3" fill="rgb(50,32,115)" opacity="0.45" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-232" points="281.1,305.1 281.1,322.9 265.8,331.7 250.5,322.9 250.5,305.1 265.8,296.3" fill="rgb(41,27,100)" opacity="0.43" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="314.1,305.1 314.1,322.9 298.7,331.7 283.4,322.9 283.4,305.1 298.7,296.3" fill="rgb(29,20,77)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-290" points="133.1,333.6 133.1,351.4 117.7,360.2 102.4,351.4 102.4,333.6 117.7,324.8" fill="rgb(21,15,61)" opacity="0.40" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="166.0,333.6 166.0,351.4 150.6,360.2 135.3,351.4 135.3,333.6 150.6,324.8" fill="rgb(29,20,77)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="198.9,333.6 198.9,351.4 183.5,360.2 168.2,351.4 168.2,333.6 183.5,324.8" fill="rgb(34,22,86)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-253" points="231.8,333.6 231.8,351.4 216.5,360.2 201.1,351.4 201.1,333.6 216.5,324.8" fill="rgb(34,22,86)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-266" points="264.7,333.6 264.7,351.4 249.4,360.2 234.0,351.4 234.0,333.6 249.4,324.8" fill="rgb(29,20,77)" opacity="0.42" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <polygon class="facet dly-290" points="297.6,333.6 297.6,351.4 282.3,360.2 266.9,351.4 266.9,333.6 282.3,324.8" fill="rgb(21,15,61)" opacity="0.40" stroke="rgba(180,220,255,.35)" stroke-width="0.6"/>
  <circle cx="130" cy="120" r="3" fill="#fff" opacity=".9"/>
  <circle cx="150" cy="100" r="2" fill="#2FD9EE" opacity=".85"/>
  <circle cx="115" cy="145" r="1.6" fill="#fff" opacity=".7"/>
</svg>
      </div>
      <div class="orbit-card oc1"><div class="oc-ic"><i class="fa-solid fa-print"></i></div><div><b><?= $printQueue ? 'Print desk busy' : 'Print queue clear' ?></b><span><?= $printQueue ? $printQueue . ' job' . ($printQueue === 1 ? '' : 's') . ' in the queue' : 'No jobs waiting at the desk' ?></span></div></div>
      <div class="orbit-card oc2"><div class="oc-ic"><i class="fa-solid fa-wifi"></i></div><div><b>Stations online</b><span><?= (int) $stationStats['online'] ?> of <?= (int) $stationStats['total'] ?> online<?= (int) $live['printers_online'] > 0 ? ' · ' . (int) $live['printers_online'] . ' printers ready' : '' ?></span></div></div>
      <div class="orbit-card oc3"><div class="oc-ic"><i class="fa-solid fa-gamepad"></i></div><div><b>Gaming bays</b><span><?= $gamingFree ?> of <?= (int) $stationStats['gaming'] ?> gaming bays free</span></div></div>
    </div>
  </div>
  <a class="scroll-cue" href="#gap" aria-label="Scroll to learn more"><span></span></a>
  <svg class="skyline" viewBox="0 0 1400 220" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="0" width="1400" height="220" fill="none"/><rect x="-20" y="122" width="70" height="118" fill="rgba(255,255,255,0.45)"/><rect x="55" y="148" width="45" height="92" fill="rgba(255,255,255,0.45)"/><rect x="102" y="136" width="80" height="104" fill="rgba(255,255,255,0.45)"/><rect x="186" y="146" width="110" height="94" fill="rgba(255,255,255,0.45)"/><rect x="302" y="151" width="60" height="89" fill="rgba(255,255,255,0.45)"/><rect x="364" y="53" width="90" height="187" fill="rgba(255,255,255,0.45)"/><rect x="456" y="137" width="60" height="103" fill="rgba(255,255,255,0.45)"/><rect x="522" y="145" width="90" height="95" fill="rgba(255,255,255,0.45)"/><rect x="620" y="129" width="110" height="111" fill="rgba(255,255,255,0.45)"/><rect x="733" y="145" width="45" height="95" fill="rgba(255,255,255,0.45)"/><rect x="784" y="59" width="110" height="181" fill="rgba(255,255,255,0.45)"/><rect x="896" y="149" width="60" height="91" fill="rgba(255,255,255,0.45)"/><rect x="962" y="126" width="80" height="114" fill="rgba(255,255,255,0.45)"/><rect x="1046" y="124" width="90" height="116" fill="rgba(255,255,255,0.45)"/><rect x="1142" y="82" width="50" height="158" fill="rgba(255,255,255,0.45)"/><rect x="1198" y="114" width="80" height="126" fill="rgba(255,255,255,0.45)"/><rect x="1280" y="112" width="110" height="128" fill="rgba(255,255,255,0.45)"/><rect x="1394" y="144" width="50" height="96" fill="rgba(255,255,255,0.45)"/><rect x="-14" y="132" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.72"/><rect x="-1" y="149" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.81"/><rect x="-1" y="166" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.64"/><rect x="-1" y="200" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.63"/><rect x="12" y="149" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.57"/><rect x="12" y="166" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.69"/><rect x="12" y="200" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.76"/><rect x="25" y="149" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.66"/><rect x="25" y="200" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.93"/><rect x="38" y="166" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.64"/><rect x="61" y="209" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.66"/><rect x="74" y="192" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.56"/><rect x="74" y="209" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.91"/><rect x="87" y="175" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.75"/><rect x="108" y="163" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.94"/><rect x="121" y="146" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.58"/><rect x="121" y="163" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.51"/><rect x="121" y="197" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.5"/><rect x="160" y="146" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.86"/><rect x="160" y="197" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.68"/><rect x="192" y="156" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.7"/><rect x="192" y="173" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.52"/><rect x="192" y="190" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.74"/><rect x="205" y="173" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.78"/><rect x="205" y="190" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.93"/><rect x="218" y="173" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.95"/><rect x="231" y="156" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.84"/><rect x="244" y="173" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.66"/><rect x="257" y="173" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.89"/><rect x="257" y="207" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.91"/><rect x="270" y="173" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.85"/><rect x="270" y="190" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.78"/><rect x="308" y="178" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.87"/><rect x="321" y="161" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.72"/><rect x="334" y="195" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.93"/><rect x="370" y="97" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.71"/><rect x="370" y="114" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.78"/><rect x="383" y="63" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.55"/><rect x="383" y="114" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.7"/><rect x="383" y="148" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.68"/><rect x="396" y="63" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.51"/><rect x="396" y="199" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.56"/><rect x="409" y="63" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.79"/><rect x="409" y="165" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.6"/><rect x="422" y="63" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.69"/><rect x="422" y="80" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.66"/><rect x="435" y="80" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.85"/><rect x="435" y="131" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.71"/><rect x="435" y="182" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.74"/><rect x="462" y="181" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.62"/><rect x="541" y="155" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.9"/><rect x="541" y="172" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.56"/><rect x="541" y="189" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.64"/><rect x="554" y="172" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.85"/><rect x="554" y="206" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.79"/><rect x="567" y="172" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.94"/><rect x="567" y="189" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.68"/><rect x="580" y="189" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.95"/><rect x="593" y="189" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.51"/><rect x="626" y="156" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.73"/><rect x="626" y="173" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.55"/><rect x="626" y="207" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.54"/><rect x="639" y="139" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.62"/><rect x="639" y="156" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.88"/><rect x="652" y="190" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.53"/><rect x="665" y="156" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.79"/><rect x="665" y="190" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.53"/><rect x="678" y="156" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.69"/><rect x="678" y="207" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.61"/><rect x="691" y="139" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.62"/><rect x="691" y="156" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.78"/><rect x="691" y="190" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.73"/><rect x="691" y="207" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.86"/><rect x="704" y="156" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.83"/><rect x="704" y="190" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.61"/><rect x="752" y="189" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.94"/><rect x="752" y="206" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.83"/><rect x="765" y="155" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.94"/><rect x="765" y="189" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.83"/><rect x="765" y="206" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.52"/><rect x="790" y="171" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.57"/><rect x="803" y="69" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.94"/><rect x="803" y="103" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.6"/><rect x="803" y="120" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.67"/><rect x="803" y="171" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.85"/><rect x="803" y="188" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.56"/><rect x="816" y="103" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.6"/><rect x="829" y="103" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.57"/><rect x="829" y="154" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.9"/><rect x="842" y="86" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.84"/><rect x="842" y="137" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.76"/><rect x="855" y="69" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.52"/><rect x="855" y="154" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.78"/><rect x="868" y="69" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.84"/><rect x="868" y="137" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.71"/><rect x="868" y="188" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.6"/><rect x="915" y="159" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.63"/><rect x="915" y="176" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.79"/><rect x="915" y="193" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.65"/><rect x="941" y="159" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.53"/><rect x="941" y="176" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.54"/><rect x="941" y="193" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.63"/><rect x="968" y="187" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.59"/><rect x="981" y="153" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.53"/><rect x="994" y="187" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.56"/><rect x="1007" y="153" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.73"/><rect x="1007" y="204" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.68"/><rect x="1020" y="136" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.81"/><rect x="1052" y="134" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.5"/><rect x="1052" y="185" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.82"/><rect x="1065" y="134" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.53"/><rect x="1065" y="185" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.84"/><rect x="1078" y="134" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.88"/><rect x="1078" y="151" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.61"/><rect x="1078" y="168" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.64"/><rect x="1091" y="151" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.68"/><rect x="1091" y="202" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.52"/><rect x="1104" y="202" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.91"/><rect x="1117" y="151" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.65"/><rect x="1117" y="168" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.83"/><rect x="1148" y="92" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.75"/><rect x="1148" y="126" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.53"/><rect x="1161" y="92" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.69"/><rect x="1161" y="126" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.65"/><rect x="1161" y="143" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.67"/><rect x="1161" y="177" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.84"/><rect x="1174" y="143" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.72"/><rect x="1204" y="175" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.67"/><rect x="1217" y="141" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.56"/><rect x="1230" y="158" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.88"/><rect x="1230" y="192" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.6"/><rect x="1243" y="124" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.55"/><rect x="1256" y="124" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.5"/><rect x="1256" y="141" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.91"/><rect x="1256" y="175" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.78"/><rect x="1286" y="156" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.74"/><rect x="1286" y="207" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.5"/><rect x="1299" y="156" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.79"/><rect x="1299" y="207" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.51"/><rect x="1312" y="156" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.72"/><rect x="1312" y="207" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.69"/><rect x="1338" y="122" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.83"/><rect x="1338" y="156" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.64"/><rect x="1338" y="190" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.62"/><rect x="1351" y="122" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.72"/><rect x="1351" y="139" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.72"/><rect x="1351" y="173" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.57"/><rect x="1351" y="207" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.56"/><rect x="1364" y="122" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.58"/><rect x="1364" y="173" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.95"/><rect x="1364" y="207" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.79"/><rect x="1400" y="188" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.67"/><rect x="1413" y="154" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.55"/><rect x="1413" y="171" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.66"/><rect x="1413" y="205" width="4" height="6" rx="1" fill="#7C4DFF" opacity="0.67"/><rect x="1426" y="171" width="4" height="6" rx="1" fill="#2FD9EE" opacity="0.54"/><rect x="1426" y="205" width="4" height="6" rx="1" fill="#B43FE0" opacity="0.91"/><rect x="0" y="216" width="1400" height="4" fill="url(#skylineRim)"/><defs><linearGradient id="skylineRim" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2FD9EE" stop-opacity="0"/><stop offset="0.5" stop-color="#2FD9EE" stop-opacity="0.55"/><stop offset="1" stop-color="#7C4DFF" stop-opacity="0"/></linearGradient></defs></svg>
</section>

<section id="gap" class="tight">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Why people choose us</div>
      <h2>A café built around getting things done</h2>
      <p>Not just a room full of computers — a comfortable space with the right tools and the right help, whenever you need it.</p>
    </div>
    <div class="stat-row stagger">
      <div class="stat-card reveal d0 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><span class="stat-ic"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><b><?= count($services) ?>-in-1</b><p><?= sanitize(siteSetting('gap_first_card', 'Browsing, printing, typing, scanning, mobile money and more in one visit')) ?></p></div>
      <div class="stat-card reveal d1 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><span class="stat-ic"><i class="fa-solid fa-microchip" aria-hidden="true"></i></span><b>Fresh</b><p>Brand-new machines and a clean, comfortable space to work in</p></div>
      <div class="stat-card reveal d2 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><span class="stat-ic"><i class="fa-solid fa-hands-holding-circle" aria-hidden="true"></i></span><b>Guided</b><p>An attendant who sits with you through the screen, not past it</p></div>
    </div>
  </div>
</section>

<section id="gallery" class="tight parallax-section">
  <div class="parallax-layer layer-1" data-parallax-speed="0.2"></div>
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Take a look inside</div>
      <h2>A space designed to be worked in, not just passed through</h2>
    </div>
    <div class="gallery-grid stagger">
      <?php
      $galleryLabels = array_column($galleryItems, 'label');
      $galleryLabels = array_pad($galleryLabels, 3, '');
      ?>
      <div class="gtile tall reveal d0">
        <svg viewBox="0 0 400 500" preserveAspectRatio="xMidYMid slice">
          <defs>
            <linearGradient id="gNet" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/>
            </linearGradient>
            <filter id="glow"><feGaussianBlur stdDeviation="2.5" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
          </defs>
          <rect width="400" height="500" fill="url(#gNet)"/>
          <!-- Cloud -->
          <g class="cloud-pulse" transform="translate(155, 40)">
            <ellipse cx="45" cy="28" rx="40" ry="22" fill="none" stroke="#2FD9EE" stroke-width="2" opacity=".9"/>
            <ellipse cx="70" cy="22" rx="30" ry="18" fill="none" stroke="#2FD9EE" stroke-width="2" opacity=".9"/>
            <ellipse cx="55" cy="35" rx="28" ry="16" fill="none" stroke="#2FD9EE" stroke-width="2" opacity=".9"/>
            <text x="55" y="32" text-anchor="middle" fill="#2FD9EE" font-size="11" font-family="Space Grotesk" font-weight="600" opacity=".9">CLOUD</text>
          </g>
          <!-- Cloud to router -->
          <line x1="200" y1="90" x2="200" y2="170" stroke="#2FD9EE" stroke-width="1.5" class="net-line" opacity=".7"/>
          <!-- Computers top row -->
          <rect x="35" y="110" width="75" height="52" rx="5" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".85"/>
          <rect x="162" y="110" width="75" height="52" rx="5" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".85"/>
          <rect x="290" y="110" width="75" height="52" rx="5" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".85"/>
          <!-- Computers bottom row -->
          <rect x="35" y="230" width="75" height="52" rx="5" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".85"/>
          <rect x="162" y="230" width="75" height="52" rx="5" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".85"/>
          <rect x="290" y="230" width="75" height="52" rx="5" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".85"/>
          <!-- Router -->
          <rect x="155" y="350" width="90" height="50" rx="6" fill="none" stroke="#B43FE0" stroke-width="2.5" opacity=".9"/>
          <circle cx="175" cy="375" r="4" fill="#2FD9EE" class="net-pulse" filter="url(#glow)"/>
          <circle cx="200" cy="375" r="4" fill="#7C4DFF" class="net-pulse dly-060" filter="url(#glow)"/>
          <circle cx="225" cy="375" r="4" fill="#B43FE0" class="net-pulse dly-120" filter="url(#glow)"/>
          <!-- Connection lines -->
          <path d="M72,162 L72,260 L200,260 L200,350" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-line" opacity=".6"/>
          <path d="M200,162 L200,350" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-line-rev" opacity=".6"/>
          <path d="M327,162 L327,260 L200,260 L200,350" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-line" opacity=".6"/>
          <path d="M72,282 L72,260 L200,260 L200,350" fill="none" stroke="#7C4DFF" stroke-width="1.5" class="net-line-rev" opacity=".6"/>
          <path d="M200,282 L200,350" fill="none" stroke="#7C4DFF" stroke-width="1.5" class="net-line" opacity=".6"/>
          <path d="M327,282 L327,260 L200,260 L200,350" fill="none" stroke="#7C4DFF" stroke-width="1.5" class="net-line-rev" opacity=".6"/>
          <!-- Data packets -->
          <circle r="3" fill="#2FD9EE" filter="url(#glow)">
            <animateMotion dur="2s" repeatCount="indefinite" path="M72,162 L72,260 L200,260 L200,350"/>
          </circle>
          <circle r="3" fill="#7C4DFF" filter="url(#glow)">
            <animateMotion dur="2.5s" repeatCount="indefinite" path="M327,162 L327,260 L200,260 L200,350"/>
          </circle>
          <circle r="2.5" fill="#B43FE0" filter="url(#glow)">
            <animateMotion dur="1.8s" repeatCount="indefinite" path="M200,162 L200,350"/>
          </circle>
        </svg>
        <div class="glabel"><?= sanitize($galleryLabels[0] ?: 'LAN Workstation') ?></div>
      </div>
      <div class="gtile reveal d1">
        <svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice">
          <defs>
            <linearGradient id="gWk" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#19233D"/><stop offset="1" stop-color="#0E1526"/>
            </linearGradient>
          </defs>
          <rect width="400" height="300" fill="url(#gWk)"/>
          <!-- Workstation -->
          <rect x="120" y="60" width="160" height="100" rx="10" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".8"/>
          <rect x="140" y="85" width="50" height="7" rx="3" fill="#7C4DFF"/>
          <rect x="140" y="105" width="90" height="5" rx="2" fill="#A79FC9" opacity=".7"/>
          <rect x="140" y="118" width="70" height="5" rx="2" fill="#A79FC9" opacity=".7"/>
          <!-- Network activity bars -->
          <rect x="280" y="80" width="8" height="30" rx="2" fill="#2FD9EE" opacity=".6" class="net-pulse"/>
          <rect x="295" y="70" width="8" height="40" rx="2" fill="#7C4DFF" opacity=".6" class="net-pulse dly-030"/>
          <rect x="310" y="85" width="8" height="25" rx="2" fill="#B43FE0" opacity=".6" class="net-pulse dly-060"/>
          <!-- Connection line to router -->
          <line x1="200" y1="160" x2="200" y2="220" stroke="#2FD9EE" stroke-width="1.5" class="net-line" opacity=".5"/>
          <circle cx="200" cy="230" r="6" fill="none" stroke="#B43FE0" stroke-width="1.5" class="net-pulse"/>
        </svg>
        <div class="glabel"><?= sanitize($galleryLabels[1] ?: 'Workstation Row') ?></div>
      </div>
      <div class="gtile reveal d2">
        <svg viewBox="0 0 400 300" preserveAspectRatio="xMidYMid slice">
          <defs>
            <linearGradient id="gCl" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/>
            </linearGradient>
          </defs>
          <rect width="400" height="300" fill="url(#gCl)"/>
          <!-- Cloud detail -->
          <g class="cloud-pulse" transform="translate(140, 50)">
            <ellipse cx="60" cy="35" rx="55" ry="30" fill="none" stroke="#2FD9EE" stroke-width="2.5" opacity=".9"/>
            <ellipse cx="90" cy="28" rx="40" ry="24" fill="none" stroke="#2FD9EE" stroke-width="2.5" opacity=".9"/>
            <ellipse cx="70" cy="45" rx="35" ry="20" fill="none" stroke="#2FD9EE" stroke-width="2.5" opacity=".9"/>
            <text x="70" y="40" text-anchor="middle" fill="#2FD9EE" font-size="13" font-family="Space Grotesk" font-weight="600" opacity=".9">CLOUD</text>
          </g>
          <!-- Connection lines from cloud -->
          <line x1="200" y1="110" x2="120" y2="180" stroke="#2FD9EE" stroke-width="1.5" class="net-line" opacity=".6"/>
          <line x1="200" y1="110" x2="200" y2="180" stroke="#7C4DFF" stroke-width="1.5" class="net-line-rev" opacity=".6"/>
          <line x1="200" y1="110" x2="280" y2="180" stroke="#B43FE0" stroke-width="1.5" class="net-line" opacity=".6"/>
          <!-- Data packets -->
          <circle r="3" fill="#2FD9EE">
            <animateMotion dur="1.5s" repeatCount="indefinite" path="M200,110 L120,180"/>
          </circle>
          <circle r="3" fill="#7C4DFF">
            <animateMotion dur="1.8s" repeatCount="indefinite" path="M200,110 L200,180"/>
          </circle>
          <circle r="3" fill="#B43FE0">
            <animateMotion dur="2s" repeatCount="indefinite" path="M200,110 L280,180"/>
          </circle>
          <!-- Router indicators -->
          <circle cx="120" cy="190" r="5" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-pulse"/>
          <circle cx="200" cy="190" r="5" fill="none" stroke="#7C4DFF" stroke-width="1.5" class="net-pulse dly-040"/>
          <circle cx="280" cy="190" r="5" fill="none" stroke="#B43FE0" stroke-width="1.5" class="net-pulse dly-080"/>
        </svg>
        <div class="glabel"><?= sanitize($galleryLabels[2] ?: 'Cloud Connect') ?></div>
      </div>
    </div>
  </div>
</section>

<section id="about" class="parallax-section">
  <div class="parallax-layer layer-2" data-parallax-speed="0.15"></div>
  <div class="wrap about-grid">
    <div class="about-copy reveal left">
      <div class="eyebrow">About the café</div>
      <h2>Built new, on purpose</h2>
      <p class="about-lead">Rather than refurbished machines, we chose brand-new desktop computers — full manufacturer warranty, longer service life, fewer breakdowns right when you need them least.</p>
      <div class="about-features">
        <?php foreach ($aboutFeatures as $index => $feature): ?>
        <div class="about-feature reveal <?= staggerClass($index) ?>">
          <div class="about-feature-icon"><i class="<?= sanitize($feature['icon_class']) ?>"></i></div>
          <div>
            <h4><?= sanitize($feature['title']) ?></h4>
            <p><?= sanitize($feature['description']) ?></p>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$aboutFeatures): ?>
        <div class="about-feature">
          <div class="about-feature-icon"><i class="fa-solid fa-microchip"></i></div>
          <div>
            <h4>Brand-New Hardware</h4>
            <p>Core i5 desktops with full manufacturer warranty and latest security patches</p>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="about-visual reveal right">
      <div class="grid-screens">
        <div>
          <svg viewBox="0 0 200 150" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="150" fill="url(#gAbout1)"/>
            <defs><linearGradient id="gAbout1" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/></linearGradient></defs>
            <rect x="50" y="20" width="100" height="65" rx="4" fill="none" stroke="#7C4DFF" stroke-width="2"/>
            <rect x="60" y="30" width="80" height="4" rx="2" fill="#7C4DFF" opacity=".8"/>
            <rect x="60" y="40" width="60" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="60" y="50" width="50" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="85" y="85" width="30" height="4" rx="2" fill="#7C4DFF" opacity=".7"/>
            <rect x="70" y="89" width="60" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
            <circle cx="100" cy="75" r="3" fill="#2FD9EE" class="net-pulse"/>
          </svg>
        </div>
        <div>
          <svg viewBox="0 0 200 150" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="150" fill="url(#gAbout2)"/>
            <defs><linearGradient id="gAbout2" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#19233D"/><stop offset="1" stop-color="#0E1526"/></linearGradient></defs>
            <rect x="60" y="25" width="80" height="55" rx="6" fill="none" stroke="#7C4DFF" stroke-width="2"/>
            <rect x="70" y="35" width="30" height="4" rx="2" fill="#7C4DFF" opacity=".8"/>
            <rect x="70" y="45" width="50" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="70" y="55" width="40" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="90" y="80" width="20" height="25" rx="3" fill="none" stroke="#2FD9EE" stroke-width="1.5"/>
            <circle cx="100" cy="70" r="3" fill="#B43FE0" class="net-pulse dly-050"/>
          </svg>
        </div>
        <div>
          <svg viewBox="0 0 200 150" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="150" fill="url(#gAbout3)"/>
            <defs><linearGradient id="gAbout3" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/></linearGradient></defs>
            <rect x="70" y="30" width="60" height="45" rx="4" fill="none" stroke="#7C4DFF" stroke-width="2"/>
            <rect x="78" y="38" width="44" height="3" rx="1" fill="#7C4DFF" opacity=".8"/>
            <rect x="78" y="46" width="35" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="78" y="54" width="28" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="85" y="75" width="30" height="3" rx="1" fill="#7C4DFF" opacity=".7"/>
            <rect x="75" y="78" width="50" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
            <circle cx="100" cy="68" r="3" fill="#2FD9EE" class="net-pulse dly-100"/>
          </svg>
        </div>
        <div>
          <svg viewBox="0 0 200 150" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="150" fill="url(#gAbout4)"/>
            <defs><linearGradient id="gAbout4" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#19233D"/><stop offset="1" stop-color="#0E1526"/></linearGradient></defs>
            <rect x="40" y="35" width="120" height="50" rx="4" fill="none" stroke="#B43FE0" stroke-width="2"/>
            <rect x="50" y="43" width="100" height="4" rx="2" fill="#B43FE0" opacity=".8"/>
            <rect x="50" y="53" width="80" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="50" y="63" width="60" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <circle cx="100" cy="85" r="4" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-pulse dly-030"/>
            <circle cx="85" cy="85" r="3" fill="#7C4DFF" class="net-pulse dly-080"/>
            <circle cx="115" cy="85" r="3" fill="#B43FE0" class="net-pulse dly-120"/>
          </svg>
        </div>
        <div>
          <svg viewBox="0 0 200 150" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="150" fill="url(#gAbout5)"/>
            <defs><linearGradient id="gAbout5" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/></linearGradient></defs>
            <rect x="55" y="20" width="90" height="60" rx="4" fill="none" stroke="#7C4DFF" stroke-width="2"/>
            <rect x="65" y="30" width="70" height="4" rx="2" fill="#7C4DFF" opacity=".8"/>
            <rect x="65" y="40" width="55" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="65" y="50" width="45" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="80" y="80" width="40" height="3" rx="1" fill="#7C4DFF" opacity=".7"/>
            <rect x="70" y="83" width="60" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
            <circle cx="100" cy="70" r="3" fill="#2FD9EE" class="net-pulse dly-070"/>
          </svg>
        </div>
        <div>
          <svg viewBox="0 0 200 150" xmlns="http://www.w3.org/2000/svg">
            <rect width="200" height="150" fill="url(#gAbout6)"/>
            <defs><linearGradient id="gAbout6" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#19233D"/><stop offset="1" stop-color="#0E1526"/></linearGradient></defs>
            <rect x="45" y="25" width="110" height="55" rx="4" fill="none" stroke="#7C4DFF" stroke-width="2"/>
            <rect x="55" y="33" width="90" height="4" rx="2" fill="#7C4DFF" opacity=".8"/>
            <rect x="55" y="43" width="70" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="55" y="53" width="55" height="3" rx="1" fill="#A79FC9" opacity=".6"/>
            <rect x="75" y="80" width="50" height="3" rx="1" fill="#7C4DFF" opacity=".7"/>
            <rect x="65" y="83" width="70" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
            <circle cx="100" cy="70" r="3" fill="#2FD9EE" class="net-pulse dly-120"/>
          </svg>
        </div>
      </div>
      <div class="about-tech-badge">
        <span class="badge-dot"></span>
        <span><?= (int) $stationStats['online'] ?> Workstations Online</span>
      </div>
    </div>
  </div>
</section>

<section id="services">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow"><?= sanitize(siteSetting('services_eyebrow', 'Services')) ?></div>
      <h2><?= sanitize(siteSetting('services_title', 'Everything one desk in Daffiama can now do')) ?></h2>
      <p><?= sanitize(siteSetting('services_lead', 'Walk in, and walk out done.')) ?></p>
    </div>
    <?php if (count($serviceCategories) > 2): ?>
    <div class="filter-chips reveal" role="group" aria-label="Filter services">
      <button type="button" class="chip" data-filter="all" aria-pressed="true">All <span><?= count($services) ?></span></button>
      <?php foreach ($serviceCategories as $key => $label): ?>
      <button type="button" class="chip" data-filter="<?= sanitize($key) ?>" aria-pressed="false"><?= sanitize($label) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="card-grid stagger" id="serviceGrid">
      <?php foreach ($services as $index => $service): ?>
      <article class="svc-card reveal <?= staggerClass($index) ?> branded" data-category="<?= sanitize((string) ($service['category'] ?? 'other')) ?>"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="svc-top"><div class="svc-icon"><i class="<?= sanitize($service['icon']) ?>" aria-hidden="true"></i></div><span class="svc-cat"><?= sanitize($serviceCategories[(string) ($service['category'] ?? 'other')] ?? '') ?></span></div><h3><?= sanitize($service['name']) ?></h3><p><?= sanitize($service['description'] ?: $service['unit_label'] . ' · ' . $service['price_label']) ?></p><div class="svc-meta"><b><?= sanitize($service['price_label']) ?></b> <span><?= sanitize($service['unit_label']) ?></span></div></article>
      <?php endforeach; ?>
      <?php if (!$services): ?>
      <div class="empty-note">Service list unavailable right now — call the front desk on <a href="tel:<?= sanitize($contactPhoneRaw) ?>"><?= sanitize($contactPhone) ?></a>.</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="gaming" class="parallax-section">
  <div class="parallax-layer layer-1" data-parallax-speed="0.25"></div>
  <div class="wrap">
    <div class="gaming-grid center">
      <div class="reveal left">
        <div class="gaming-badge"><span class="dot"></span>NOW OPEN</div>
        <div class="eyebrow eyebrow-tight">Gaming Center</div>
        <h2 class="gaming-section-head">Game on with the crew</h2>
        <p class="lead-muted flush-top">A dedicated bay of high-performance rigs for solo runs, squad nights and everything between. Subscribe once, play whenever you're free.</p>
        <div class="gaming-features stagger">
          <div class="gaming-card reveal d0 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="gc-icon rgb1"><i class="fa-solid fa-gamepad"></i></div><h3>High-Performance Rigs</h3><p>Dedicated GPUs, high refresh monitors, wired headsets and mechanical keyboards on every station.</p></div>
          <div class="gaming-card reveal d1 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="gc-icon rgb2"><i class="fa-solid fa-trophy"></i></div><h3>Popular Titles</h3><p>FIFA, Call of Duty, PES, racing &amp; battle royale favorites — updated regularly.</p></div>
          <div class="gaming-card reveal d2 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="gc-icon rgb3"><i class="fa-solid fa-users"></i></div><h3>Multiplayer &amp; LAN</h3><p>Squad up on linked stations for local head-to-heads and team battles.</p></div>
          <div class="gaming-card reveal d3 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="gc-icon rgb4"><i class="fa-solid fa-medal"></i></div><h3>Weekend Tournaments</h3><p>Casual leaderboards and prizes for regulars. Bring your squad.</p></div>
        </div>
        <div class="gaming-stats">
          <div class="gaming-stat"><b><?= (int) $stationStats['gaming'] ?></b><span>Gaming Stations</span></div>
          <div class="gaming-stat"><b>RGB</b><span>Lighting</span></div>
          <div class="gaming-stat"><b><?= $gamingFree ?></b><span>Bays free now</span></div>
        </div>
      </div>
      <div class="gaming-visual reveal right">
        <div class="g-glow"></div>
        <div class="gaming-bay">
          <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient id="gamingBg" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/>
              </linearGradient>
              <filter id="neonGlow"><feGaussianBlur stdDeviation="3" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
              <linearGradient id="rgb1" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#B43FE0"/><stop offset="1" stop-color="#7C4DFF"/></linearGradient>
              <linearGradient id="rgb2" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2FD9EE"/><stop offset="1" stop-color="#7C4DFF"/></linearGradient>
            </defs>
            <rect width="400" height="400" fill="url(#gamingBg)" rx="14"/>
            <!-- Floor reflection -->
            <rect x="40" y="340" width="320" height="4" rx="2" fill="url(#rgb1)" opacity=".3" filter="url(#neonGlow)"/>
            <!-- Station 1 - Left -->
            <g transform="translate(55, 80)">
              <rect x="0" y="40" width="90" height="60" rx="6" fill="none" stroke="url(#rgb1)" stroke-width="2.5" filter="url(#neonGlow)"/>
              <rect x="8" y="48" width="30" height="4" rx="2" fill="#B43FE0" opacity=".8"/>
              <rect x="8" y="58" width="50" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
              <rect x="8" y="68" width="40" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
              <circle cx="75" cy="55" r="4" fill="#2FD9EE" opacity=".9" class="net-pulse"/>
              <!-- RGB strip -->
              <rect x="0" y="100" width="90" height="3" rx="1" fill="url(#rgb1)" opacity=".7" filter="url(#neonGlow)"/>
              <!-- Chair -->
              <rect x="25" y="115" width="40" height="35" rx="6" fill="none" stroke="#7C4DFF" stroke-width="1.5" opacity=".6"/>
              <rect x="35" y="125" width="20" height="15" rx="3" fill="none" stroke="#7C4DFF" stroke-width="1" opacity=".4"/>
            </g>
            <!-- Station 2 - Right -->
            <g transform="translate(255, 80)">
              <rect x="0" y="40" width="90" height="60" rx="6" fill="none" stroke="url(#rgb2)" stroke-width="2.5" filter="url(#neonGlow)"/>
              <rect x="8" y="48" width="30" height="4" rx="2" fill="#2FD9EE" opacity=".8"/>
              <rect x="8" y="58" width="50" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
              <rect x="8" y="68" width="40" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
              <circle cx="75" cy="55" r="4" fill="#B43FE0" opacity=".9" class="net-pulse dly-050"/>
              <!-- RGB strip -->
              <rect x="0" y="100" width="90" height="3" rx="1" fill="url(#rgb2)" opacity=".7" filter="url(#neonGlow)"/>
              <!-- Chair -->
              <rect x="25" y="115" width="40" height="35" rx="6" fill="none" stroke="#7C4DFF" stroke-width="1.5" opacity=".6"/>
              <rect x="35" y="125" width="20" height="15" rx="3" fill="none" stroke="#7C4DFF" stroke-width="1" opacity=".4"/>
            </g>
            <!-- Center Hub / Console -->
            <g transform="translate(140, 200)">
              <rect x="0" y="0" width="120" height="70" rx="8" fill="none" stroke="#7C4DFF" stroke-width="2" opacity=".8"/>
              <rect x="10" y="10" width="100" height="6" rx="3" fill="#7C4DFF" opacity=".6"/>
              <rect x="10" y="22" width="70" height="4" rx="2" fill="#A79FC9" opacity=".4"/>
              <rect x="10" y="32" width="60" height="4" rx="2" fill="#A79FC9" opacity=".4"/>
              <circle cx="100" cy="18" r="5" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-pulse dly-030"/>
              <circle cx="85" cy="18" r="3" fill="#B43FE0" opacity=".7" class="net-pulse dly-080"/>
              <!-- RGB underglow -->
              <rect x="5" y="65" width="110" height="4" rx="2" fill="url(#rgb1)" opacity=".5" filter="url(#neonGlow)"/>
            </g>
            <!-- Headset icons -->
            <g transform="translate(90, 150)" opacity=".7">
              <path d="M0,8 Q0,0 8,0 Q16,0 16,8" fill="none" stroke="#2FD9EE" stroke-width="1.5"/>
              <rect x="-3" y="6" width="6" height="10" rx="2" fill="none" stroke="#2FD9EE" stroke-width="1.2"/>
              <rect x="13" y="6" width="6" height="10" rx="2" fill="none" stroke="#2FD9EE" stroke-width="1.2"/>
            </g>
            <g transform="translate(290, 150)" opacity=".7">
              <path d="M0,8 Q0,0 8,0 Q16,0 16,8" fill="none" stroke="#B43FE0" stroke-width="1.5"/>
              <rect x="-3" y="6" width="6" height="10" rx="2" fill="none" stroke="#B43FE0" stroke-width="1.2"/>
              <rect x="13" y="6" width="6" height="10" rx="2" fill="none" stroke="#B43FE0" stroke-width="1.2"/>
            </g>
            <!-- Game controller -->
            <g transform="translate(175, 290)" opacity=".6">
              <rect x="0" y="0" width="50" height="28" rx="8" fill="none" stroke="#7C4DFF" stroke-width="1.5"/>
              <circle cx="15" cy="14" r="4" fill="none" stroke="#2FD9EE" stroke-width="1"/>
              <circle cx="35" cy="14" r="4" fill="none" stroke="#B43FE0" stroke-width="1"/>
              <rect x="20" y="10" width="10" height="8" rx="2" fill="none" stroke="#7C4DFF" stroke-width="1"/>
            </g>
            <!-- Floating particles -->
            <circle cx="60" cy="200" r="2" fill="#B43FE0" opacity=".6" class="net-pulse"/>
            <circle cx="340" cy="180" r="2" fill="#2FD9EE" opacity=".6" class="net-pulse dly-070"/>
            <circle cx="200" cy="120" r="1.5" fill="#7C4DFF" opacity=".5" class="net-pulse dly-120"/>
          </svg>
          <!-- Floating cards -->
          <div class="gaming-visual-card gvc1 gvc-flex"><div class="gvc-icon bg-violet"><i class="fa-solid fa-gamepad"></i></div><div><b class="gvc-title">Live Match</b><span class="gvc-sub">FIFA 24 · Station 2</span></div></div>
          <div class="gaming-visual-card gvc2 gvc-flex"><div class="gvc-icon bg-cyan"><i class="fa-solid fa-users"></i></div><div><b class="gvc-title">Squad Online</b><span class="gvc-sub">4 players linked</span></div></div>
          <div class="gaming-visual-card gvc3 gvc-flex"><div class="gvc-icon bg-magenta"><i class="fa-solid fa-trophy"></i></div><div><b class="gvc-title">Tournament</b><span class="gvc-sub">Weekend · Prize pool</span></div></div>
        </div>
      </div>
    </div>
    <div class="section-head reveal gaming-section-head gaming-center-head">
      <div class="eyebrow">Gaming passes</div>
      <h2 class="gaming-price-title">Pick a pass, start playing</h2>
      <p class="lead-muted mt-8">Flexible options for every type of gamer. No hidden fees, no long contracts.</p>
    </div>
    <div class="price-grid stagger">
      <?php foreach ($gamingPlans as $index => $plan): ?>
      <div class="price-card reveal <?= staggerClass($index) ?><?= $index === 1 ? ' hi' : '' ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><span class="unit"><?= sanitize($plan['name']) ?></span><b class="amt"><?= sanitize($plan['price_label']) ?></b><span class="unit"><?= sanitize($plan['unit_label']) ?></span><p><?= sanitize($plan['description'] ?: 'Flexible gaming access.') ?></p></div>
      <?php endforeach; ?>
      <?php if (!$gamingPlans): ?>
      <div class="empty-note">Gaming passes unavailable right now — ask the front desk for the current list.</div>
      <?php endif; ?>
    </div>
    <div class="cta-row"><a href="#visit" class="btn btn-primary reveal">Subscribe at the Front Desk</a></div>
  </div>
</section>

<section id="spotlight">
  <div class="wrap spot-grid">
    <div class="reveal left">
      <div class="eyebrow">Most requested</div>
      <h2>The tasks people dread, made simple</h2>
      <p class="lead-muted mt-14">These are the jobs that bring most people through our door — and the ones our attendants know inside out.</p>
    </div>
    <div class="stamp-list stagger">
      <?php foreach ($spotlightItems as $index => $item): ?>
      <div class="stamp reveal <?= staggerClass($index) ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="stamp-mark"><?= sanitize(mb_substr($item['title'], 0, 2)) ?></div><div><h4><?= sanitize($item['title']) ?></h4><p><?= sanitize($item['description']) ?></p></div></div>
      <?php endforeach; ?>
      <?php if (!$spotlightItems): ?>
      <div class="empty-note">Most requested services list is being updated — call the front desk for the current list.</div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="why">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Why here</div>
      <h2>A café built to stay reliable</h2>
      <p>Three things we invested in so your visit is never cut short.</p>
    </div>
    <div class="why-grid stagger">
      <article class="why-card glass reveal d0">
        <div class="why-visual"><svg viewBox="0 0 400 250" preserveAspectRatio="xMidYMid slice">
          <defs>
            <linearGradient id="gWhy1" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/>
            </linearGradient>
          </defs>
          <rect width="400" height="250" fill="url(#gWhy1)"/>
          <!-- Monitor -->
          <rect x="100" y="35" width="130" height="88" rx="6" fill="none" stroke="#7C4DFF" stroke-width="2"/>
          <rect x="115" y="50" width="42" height="5" rx="2" fill="#7C4DFF"/>
          <rect x="115" y="62" width="75" height="4" rx="2" fill="#A79FC9" opacity=".6"/>
          <rect x="115" y="72" width="55" height="4" rx="2" fill="#A79FC9" opacity=".6"/>
          <!-- Stand -->
          <rect x="158" y="123" width="14" height="12" fill="none" stroke="#7C4DFF" stroke-width="1.5"/>
          <rect x="142" y="133" width="46" height="4" rx="2" fill="none" stroke="#7C4DFF" stroke-width="1.5"/>
          <!-- Tower -->
          <rect x="220" y="60" width="36" height="72" rx="3" fill="none" stroke="#2FD9EE" stroke-width="1.5"/>
          <circle cx="238" cy="82" r="3" fill="#2FD9EE" opacity=".7" class="net-pulse"/>
          <circle cx="238" cy="98" r="3" fill="#7C4DFF" opacity=".7" class="net-pulse dly-050"/>
          <circle cx="238" cy="114" r="3" fill="#B43FE0" opacity=".7" class="net-pulse dly-100"/>
          <!-- Warranty badge -->
          <g transform="translate(45, 165)">
            <path d="M22,3 L42,10 L42,32 L22,40 L2,32 L2,10 Z" fill="none" stroke="#2FD9EE" stroke-width="1.5" opacity=".8"/>
            <text x="22" y="25" text-anchor="middle" fill="#2FD9EE" font-size="7" font-family="Space Grotesk" font-weight="600">WARRANTY</text>
          </g>
        </svg></div>
        <div class="why-copy"><div class="feat-num"><?= sanitize($whyFeatures[0]['feature_number'] ?? '01') ?></div><h3><?= sanitize($whyFeatures[0]['title'] ?? 'Warranty-backed hardware') ?></h3><p><?= sanitize($whyFeatures[0]['description'] ?? 'Six brand-new Core i5 desktops, not refurbished units — chosen to keep downtime, and repair costs, out of your visit.') ?></p></div>
      </article>
      <article class="why-card glass reveal d1">
        <div class="why-visual"><svg viewBox="0 0 400 250" preserveAspectRatio="xMidYMid slice">
          <defs>
            <linearGradient id="gWhy2" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#19233D"/><stop offset="1" stop-color="#0E1526"/>
            </linearGradient>
          </defs>
          <rect width="400" height="250" fill="url(#gWhy2)"/>
          <!-- UPS unit -->
          <rect x="120" y="45" width="160" height="85" rx="6" fill="none" stroke="#7C4DFF" stroke-width="2"/>
          <rect x="135" y="58" width="42" height="5" rx="2" fill="#7C4DFF"/>
          <rect x="135" y="70" width="75" height="4" rx="2" fill="#A79FC9" opacity=".6"/>
          <rect x="135" y="82" width="55" height="4" rx="2" fill="#A79FC9" opacity=".6"/>
          <!-- Power indicator -->
          <circle cx="255" cy="68" r="6" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-pulse"/>
          <path d="M255,63 L257,68 L253,68 Z" fill="#2FD9EE" opacity=".8"/>
          <!-- Power lines -->
          <line x1="80" y1="90" x2="120" y2="90" stroke="#2FD9EE" stroke-width="1.5" class="net-line" opacity=".6"/>
          <line x1="280" y1="90" x2="320" y2="90" stroke="#7C4DFF" stroke-width="1.5" class="net-line-rev" opacity=".6"/>
          <!-- Lightning bolts -->
          <path d="M90,80 L93,90 L86,90 L91,102" fill="none" stroke="#B43FE0" stroke-width="1.5" opacity=".7" class="net-pulse"/>
          <path d="M310,80 L313,90 L306,90 L311,102" fill="none" stroke="#B43FE0" stroke-width="1.5" opacity=".7" class="net-pulse dly-050"/>
          <!-- Stations -->
          <rect x="45" y="150" width="50" height="32" rx="3" fill="none" stroke="#7C4DFF" stroke-width="1.5" opacity=".7"/>
          <rect x="175" y="150" width="50" height="32" rx="3" fill="none" stroke="#7C4DFF" stroke-width="1.5" opacity=".7"/>
          <rect x="305" y="150" width="50" height="32" rx="3" fill="none" stroke="#7C4DFF" stroke-width="1.5" opacity=".7"/>
          <!-- Connections -->
          <line x1="120" y1="130" x2="70" y2="150" stroke="#2FD9EE" stroke-width="1" class="net-line" opacity=".5"/>
          <line x1="200" y1="130" x2="200" y2="150" stroke="#7C4DFF" stroke-width="1" class="net-line-rev" opacity=".5"/>
          <line x1="280" y1="130" x2="330" y2="150" stroke="#B43FE0" stroke-width="1" class="net-line" opacity=".5"/>
        </svg></div>
        <div class="why-copy"><div class="feat-num"><?= sanitize($whyFeatures[1]['feature_number'] ?? '02') ?></div><h3><?= sanitize($whyFeatures[1]['title'] ?? 'Power you can rely on') ?></h3><p><?= sanitize($whyFeatures[1]['description'] ?? 'UPS backup on every pair of stations keeps your session — and your unsaved form — running through "dumsor."') ?></p></div>
      </article>
      <article class="why-card glass reveal d2">
        <div class="why-visual"><svg viewBox="0 0 400 250" preserveAspectRatio="xMidYMid slice">
          <defs>
            <linearGradient id="gWhy3" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0" stop-color="#1F2A47"/><stop offset="1" stop-color="#0E1526"/>
            </linearGradient>
          </defs>
          <rect width="400" height="250" fill="url(#gWhy3)"/>
          <!-- CCTV Camera -->
          <g transform="translate(120, 35)">
            <rect x="12" y="12" width="45" height="28" rx="4" fill="none" stroke="#7C4DFF" stroke-width="2"/>
            <circle cx="34" cy="26" r="9" fill="none" stroke="#2FD9EE" stroke-width="1.5"/>
            <circle cx="34" cy="26" r="3.5" fill="#2FD9EE" opacity=".6" class="net-pulse"/>
            <circle cx="32" cy="24" r="1.2" fill="#fff" opacity=".8"/>
            <!-- Mount -->
            <rect x="31" y="40" width="6" height="12" fill="none" stroke="#7C4DFF" stroke-width="1.5"/>
            <line x1="34" y1="52" x2="22" y2="65" stroke="#7C4DFF" stroke-width="1.5"/>
            <line x1="34" y1="52" x2="46" y2="65" stroke="#7C4DFF" stroke-width="1.5"/>
          </g>
          <!-- Coverage area -->
          <path d="M185,70 L255,95 L255,140 L185,115 Z" fill="none" stroke="#2FD9EE" stroke-width="1" opacity=".3" class="net-pulse"/>
          <!-- Shield -->
          <g transform="translate(240, 110)">
            <path d="M22,7 L40,13 L40,37 L22,45 L4,37 L4,13 Z" fill="none" stroke="#B43FE0" stroke-width="2" opacity=".9"/>
            <path d="M22,20 L25,27 L32,20" fill="none" stroke="#2FD9EE" stroke-width="1.5" opacity=".9"/>
          </g>
          <!-- Front desk -->
          <rect x="90" y="175" width="220" height="35" rx="4" fill="none" stroke="#7C4DFF" stroke-width="1.5" opacity=".8"/>
          <rect x="108" y="188" width="35" height="4" rx="2" fill="#7C4DFF" opacity=".6"/>
          <rect x="108" y="198" width="60" height="3" rx="1" fill="#A79FC9" opacity=".5"/>
          <!-- Attendant -->
          <circle cx="200" cy="168" r="5" fill="none" stroke="#2FD9EE" stroke-width="1.5" class="net-pulse"/>
          <path d="M194,180 L200,174 L206,180" fill="none" stroke="#2FD9EE" stroke-width="1.5" opacity=".7"/>
        </svg></div>
        <div class="why-copy"><div class="feat-num"><?= sanitize($whyFeatures[2]['feature_number'] ?? '03') ?></div><h3><?= sanitize($whyFeatures[2]['title'] ?? 'Secure, monitored space') ?></h3><p><?= sanitize($whyFeatures[2]['description'] ?? 'CCTV coverage and a supervised front desk, so parents and institutions alike trust who\'s on the machines.') ?></p></div>
      </article>
    </div>
  </div>
</section>

<section id="how">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">How it works</div>
      <h2>Four steps, one visit</h2>
    </div>
    <div class="steps stagger">
      <?php foreach ($howSteps as $index => $step): ?>
      <div class="step reveal <?= staggerClass($index) ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="num"><?= (int) $step['step_number'] ?></div><h3><?= sanitize($step['title']) ?></h3><p><?= sanitize($step['description']) ?></p></div>
      <?php endforeach; ?>
      <?php if (!$howSteps): ?>
      <div class="step reveal d0 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="num">1</div><h3>Walk in</h3><p>Two rooms, central Daffiama, near the SHS &amp; Technical Institute.</p></div>
      <div class="step reveal d1 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="num">2</div><h3>Tell us what you need</h3><p>Browsing, printing, an application — the attendant points you right.</p></div>
      <div class="step reveal d2 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="num">3</div><h3>Get guided help</h3><p>We sit with you through the screen, not just past it.</p></div>
      <div class="step reveal d3 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="num">4</div><h3>Pay &amp; go</h3><p>Cash or mobile money, receipt on request.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="pricing">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow"><?= sanitize(siteSetting('pricing_eyebrow', 'Pricing')) ?></div>
      <h2><?= sanitize(siteSetting('pricing_title', 'Straightforward rates')) ?></h2>
      <p><?= sanitize(siteSetting('pricing_lead', 'Indicative pricing — ask the front desk for the current full list.')) ?></p>
    </div>
    <?php if ($plans): ?>
    <div class="seg-tabs reveal" role="tablist" aria-label="Price lists">
      <button type="button" role="tab" id="tabRates" aria-controls="panelRates" aria-selected="true">Services &amp; rates</button>
      <button type="button" role="tab" id="tabPlans" aria-controls="panelPlans" aria-selected="false" tabindex="-1">Packages &amp; passes</button>
    </div>
    <?php endif; ?>
    <h3 class="grid-head reveal sr-only-when-tabs">Services &amp; rates</h3>
    <div class="price-grid stagger" id="panelRates" role="tabpanel" aria-labelledby="tabRates">
      <?php foreach ($services as $index => $service): ?>
      <div class="price-card reveal <?= staggerClass($index) ?><?= $index === 0 ? ' hi' : '' ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><span class="unit"><?= sanitize($service['name']) ?></span><b class="amt"><?= sanitize($service['price_label']) ?></b><span class="unit"><?= sanitize($service['unit_label']) ?></span><p><?= sanitize($service['description'] ?: 'Ask the front desk for details.') ?></p></div>
      <?php endforeach; ?>
      <?php if (!$services): ?>
      <div class="empty-note">Rates unavailable right now — call <?= sanitize($contactPhone) ?> for the current list.</div>
      <?php endif; ?>
    </div>
    <?php if ($plans): ?>
    <h3 class="grid-head reveal sr-only-when-tabs">Packages &amp; passes</h3>
    <div class="price-grid stagger" id="panelPlans" role="tabpanel" aria-labelledby="tabPlans">
      <?php foreach ($plans as $index => $plan): ?>
      <div class="price-card reveal <?= staggerClass($index) ?><?= $index === 0 ? ' hi' : '' ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><?= $index === 0 ? '<span class="price-flag">Popular</span>' : '' ?><span class="unit"><?= sanitize($plan['name']) ?></span><b class="amt"><?= sanitize($plan['price_label']) ?></b><span class="unit"><?= sanitize($plan['unit_label']) ?></span><p><?= sanitize($plan['description'] ?: 'Flexible package.') ?></p></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<section id="roadmap">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Where we're headed</div>
      <h2>Built to grow with Daffiama</h2>
    </div>
    <div class="road-line stagger">
      <?php foreach ($roadmapItems as $index => $item): ?>
      <div class="road-item reveal <?= staggerClass($index) ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><b><?= sanitize($item['period']) ?></b><h4><?= sanitize($item['title']) ?></h4><p><?= sanitize($item['description']) ?></p></div>
      <?php endforeach; ?>
      <?php if (!$roadmapItems): ?>
      <div class="road-item reveal d0 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><b>NOW</b><h4>The café</h4><p>Six stations, full service, open daily.</p></div>
      <div class="road-item reveal d1 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><b>YEAR 2</b><h4>ICT training</h4><p>Off-peak courses in computer literacy &amp; Office skills.</p></div>
      <div class="road-item reveal d2 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><b>YEAR 2–3</b><h4>Cybersecurity awareness</h4><p>Guidance for local shops, schools and cooperatives.</p></div>
      <div class="road-item reveal d3 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><b>ONGOING</b><h4>Green initiative</h4><p>Tree planting &amp; responsible e-waste disposal.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="who">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Who we serve</div>
      <h2>Made for every desk in town</h2>
    </div>
    <div class="persona-grid stagger">
      <?php foreach ($personas as $index => $persona): ?>
      <div class="persona reveal <?= staggerClass($index) ?> branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="glyph"><?= sanitize($persona['glyph']) ?></div><h4><?= sanitize($persona['name']) ?></h4><p><?= sanitize($persona['description']) ?></p></div>
      <?php endforeach; ?>
      <?php if (!$personas): ?>
      <div class="persona reveal d0 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="glyph">Aa</div><h4>Students</h4><p>Research, assignments and applications, without the wait.</p></div>
      <div class="persona reveal d1 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="glyph">Jb</div><h4>Job seekers</h4><p>Recruitment portals and a properly formatted CV.</p></div>
      <div class="persona reveal d2 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="glyph">Tc</div><h4>Traders &amp; small businesses</h4><p>Printing, scanning and typing for everyday records.</p></div>
      <div class="persona reveal d3 branded"><div class="brand-watermark"><?php echo $brandLogo; ?></div><div class="glyph">Td</div><h4>Remote workers</h4><p>A reliable desk, fast Wi-Fi, and coffee within reach.</p></div>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="testimonials">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Testimonials</div>
      <h2>What our visitors say</h2>
      <p class="lead-muted">Real feedback from people who use our desks every week.</p>
    </div>
    <div class="testimonial-carousel">
      <div class="testimonial-track">
        <?php foreach ($testimonials as $index => $t): ?>
        <div class="testimonial-card reveal <?= staggerClass($index) ?>">
          <div class="testimonial-stars"><?= str_repeat('★', (int) ($t['rating'] ?? 5)) ?></div>
          <div class="quote-mark">"</div>
          <p><?= sanitize($t['content']) ?></p>
          <div class="testimonial-author">
            <div class="testimonial-avatar avatar-bg-<?= sanitize($t['avatar_color'] ?? 'violet') ?>">
              <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="14" r="7" fill="var(--<?= sanitize($t['avatar_color'] ?? 'violet') ?>)" opacity=".8"/><path d="M8,38 Q8,26 20,26 Q32,26 32,38" fill="var(--<?= sanitize($t['avatar_color'] ?? 'violet') ?>)" opacity=".6"/></svg>
            </div>
            <div><strong><?= sanitize($t['customer_name']) ?></strong><span><?= sanitize($t['customer_role']) ?></span></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$testimonials): ?>
        <div class="testimonial-card">
          <div class="testimonial-stars">★★★★★</div>
          <div class="quote-mark">"</div>
          <p>Reviews are being collected — check back soon or share your feedback at the front desk.</p>
          <div class="testimonial-author">
            <div class="testimonial-avatar avatar-bg-violet">
              <svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg"><circle cx="20" cy="14" r="7" fill="var(--violet)" opacity=".8"/><path d="M8,38 Q8,26 20,26 Q32,26 32,38" fill="var(--violet)" opacity=".6"/></svg>
            </div>
            <div><strong>CTECHLIT & SYSTEMS</strong><span>Station visitor</span></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <button class="carousel-btn carousel-prev" aria-label="Previous testimonial"><i class="fa-solid fa-chevron-left"></i></button>
      <button class="carousel-btn carousel-next" aria-label="Next testimonial"><i class="fa-solid fa-chevron-right"></i></button>
      <div class="carousel-dots"></div>
    </div>
    <p class="testimonial-feedback-cta"><a class="btn btn-primary" href="feedback.php">Share your feedback</a></p>
  </div>
</section>

<section id="faq">
  <div class="wrap">
    <div class="faq-layout">
    <div class="section-head reveal faq-head">
      <div class="eyebrow">FAQ</div>
      <h2>Common questions</h2>
      <p class="lead-muted">Everything you need to know before your first visit.</p>
      <div class="faq-help glass">
        <i class="fa-solid fa-headset" aria-hidden="true"></i>
        <div><b>Still have a question?</b><span>Call <a href="tel:<?= sanitize($contactPhoneRaw) ?>"><?= sanitize($contactPhone) ?></a> or <a href="support-tickets.php">send us a message</a>.</span></div>
      </div>
    </div>
    <div class="faq-list stagger">
      <?php foreach ($faqs as $index => $faq): ?>
      <div class="faq-item reveal <?= staggerClass($index) ?>">
        <button type="button" class="faq-question" aria-expanded="false" aria-controls="faq-a-<?= $index ?>" id="faq-q-<?= $index ?>"><span><?= sanitize($faq['question']) ?></span><span class="faq-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span></button>
        <div class="faq-answer" id="faq-a-<?= $index ?>" role="region" aria-labelledby="faq-q-<?= $index ?>"><p><?= sanitize($faq['answer']) ?></p></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$faqs): ?>
      <div class="empty-note">No FAQs available right now — call <?= sanitize($contactPhone) ?> for quick answers.</div>
      <?php endif; ?>
    </div>
    </div>
  </div>
</section>

<section id="trust">
  <div class="wrap">
    <div class="section-head reveal">
      <div class="eyebrow">Trusted by</div>
      <h2>Built on real relationships</h2>
      <p class="lead-muted">Proudly serving Daffiama and the surrounding communities.</p>
    </div>
    <div class="trust-row stagger">
      <?php
      $trustNames = array_column($trustBadges, 'name');
      $trustNames = array_pad($trustNames, 4, '');
      ?>
      <div class="trust-badge reveal d0">
        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="22" fill="none" stroke="var(--violet)" stroke-width="2"/><path d="M24,12 L28,20 L36,20 L30,26 L32,34 L24,30 L16,34 L18,26 L12,20 L20,20 Z" fill="var(--cyan)" opacity=".8"/></svg>
        <span><?= sanitize($trustNames[0] ?: 'Daffiama SHS') ?></span>
      </div>
      <div class="trust-badge reveal d1">
        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><rect x="8" y="8" width="32" height="32" rx="6" fill="none" stroke="var(--violet)" stroke-width="2"/><circle cx="24" cy="22" r="6" fill="none" stroke="var(--cyan)" stroke-width="2"/><path d="M14,36 Q14,28 24,28 Q34,28 34,36" fill="none" stroke="var(--magenta)" stroke-width="2"/></svg>
        <span><?= sanitize($trustNames[1] ?: 'Technical Institute') ?></span>
      </div>
      <div class="trust-badge reveal d2">
        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><rect x="6" y="14" width="36" height="24" rx="4" fill="none" stroke="var(--violet)" stroke-width="2"/><path d="M6,22 L42,22" stroke="var(--cyan)" stroke-width="1.5" opacity=".6"/><circle cx="14" cy="18" r="2" fill="var(--magenta)"/><circle cx="22" cy="18" r="2" fill="var(--cyan)"/><circle cx="30" cy="18" r="2" fill="var(--violet)"/></svg>
        <span><?= sanitize($trustNames[2] ?: 'Local Businesses') ?></span>
      </div>
      <div class="trust-badge reveal d3">
        <svg viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg"><path d="M24,6 L42,18 L42,38 L24,44 L6,38 L6,18 Z" fill="none" stroke="var(--violet)" stroke-width="2"/><path d="M24,6 L24,24 M24,24 L42,18 M24,24 L6,18" stroke="var(--cyan)" stroke-width="1.5" opacity=".7"/></svg>
        <span><?= sanitize($trustNames[3] ?: 'Community Groups') ?></span>
      </div>
    </div>
  </div>
</section>

<section id="cta-banner" class="tight">
  <div class="wrap cta-banner-inner reveal">
    <div class="eyebrow">Ready to get started?</div>
    <h2>Your next visit starts here</h2>
    <p>Six stations, one front desk, and an attendant who actually helps. No trip to Wa required.</p>
    <div class="cta-actions">
      <a href="#visit" class="btn btn-primary">Plan Your Visit</a>
      <a href="tel:<?= sanitize($contactPhoneRaw) ?>" class="btn btn-ghost"><i class="fa-solid fa-phone"></i> Call <?= sanitize($contactPhone) ?></a>
    </div>
  </div>
</section>

<section id="visit" class="tight">
  <div class="wrap visit-grid">
    <div class="visit-copy reveal left">
      <div class="eyebrow"><?= sanitize(siteSetting('visit_eyebrow', 'Visit us')) ?></div>
      <h2><?= sanitize(siteSetting('visit_title', 'Central Daffiama, easy to find')) ?></h2>
      <p><?= sanitize(siteSetting('visit_lead', 'Near the Daffiama Senior High School & Technical Institute.')) ?></p>
      <div class="visit-list">
        <div class="visit-row"><div class="visit-icon"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></div><div><div class="vlabel">Address</div><div class="vvalue"><?= sanitize($visitAddress) ?></div></div></div>
        <div class="visit-row"><div class="visit-icon"><i class="fa-solid fa-clock"></i></div><div><div class="vlabel">Hours</div><div class="vvalue"><?= sanitize($openingHours) ?><?php if ($openStatus): ?> <span class="open-pill <?= $openStatus['open'] ? 'is-open' : 'is-closed' ?>"><span class="dot"></span><?= sanitize($openStatus['label']) ?> · <?= sanitize($openStatus['detail']) ?></span><?php endif; ?></div></div></div>
        <div class="visit-row"><div class="visit-icon"><i class="fa-solid fa-phone"></i></div><div><div class="vlabel">Phone</div><div class="vvalue"><a href="tel:<?= sanitize($contactPhoneRaw) ?>"><?= sanitize($contactPhone) ?></a></div></div></div>
        <div class="visit-row"><div class="visit-icon"><i class="fa-solid fa-envelope"></i></div><div><div class="vlabel">Email</div><div class="vvalue"><a href="mailto:<?= sanitize($contactEmail) ?>"><?= sanitize($contactEmail) ?></a></div></div></div>
      </div>
      <div class="visit-actions">
        <a href="<?= sanitize($mapUrl) ?>" class="btn btn-primary" target="_blank" rel="noopener"><i class="fa-solid fa-location-arrow" aria-hidden="true"></i> Get directions</a>
        <a href="tel:<?= sanitize($contactPhoneRaw) ?>" class="btn btn-ghost"><i class="fa-solid fa-phone" aria-hidden="true"></i> Call us</a>
      </div>
    </div>
    <div class="map-card reveal right">
      <iframe class="visit-map" src="<?= sanitize($mapEmbedUrl) ?>" title="Map showing <?= sanitize($businessName) ?> at <?= sanitize($visitAddress) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>
  </div>
</section>

<section id="final" class="wrap tight">
  <h2 class="reveal">Come see what a well-equipped desk can do for you.</h2>
  <p class="reveal delay-1"><?= (int) $stationStats['total'] ?> stations. One attendant who knows every form. No trip to Wa required.</p>
  <a href="#visit" class="btn btn-primary reveal delay-2">Plan Your Visit</a>
</section>

<footer>
  <div class="wrap">
    <div class="foot-grid">
      <div class="foot-brand">
        <div class="brand brand-foot"><span class="dot"></span><?= sanitize($businessName) ?></div>
        <p class="foot-about"><?= sanitize(siteSetting('footer_about', 'Digital access, ICT training and technology support.')) ?></p>
        <?php if ($openStatus): ?><span class="open-pill <?= $openStatus['open'] ? 'is-open' : 'is-closed' ?>"><span class="dot"></span><?= sanitize($openStatus['label']) ?> · <?= sanitize($openStatus['detail']) ?></span><?php endif; ?>
      </div>
      <div>
        <h5>Explore</h5>
        <a href="#services">Services</a><a href="#gaming">Gaming</a><a href="#pricing">Pricing</a><a href="#roadmap">Growth plan</a>
      </div>
      <div>
        <h5>Help</h5>
        <a href="documentation.php">Documentation</a><a href="tutorials.php">Tutorials</a><a href="support-tickets.php">Submit a ticket</a><a href="feedback.php">Share feedback</a>
      </div>
      <div>
        <h5>Contact</h5>
        <a href="tel:<?= sanitize($contactPhoneRaw) ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> <?= sanitize($contactPhone) ?></a><a href="mailto:<?= sanitize($contactEmail) ?>"><i class="fa-solid fa-envelope" aria-hidden="true"></i> <?= sanitize($contactEmail) ?></a><a href="#visit"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> <?= sanitize(siteSetting('visit_title', 'Central Daffiama')) ?></a>
      </div>
    </div>
    <div class="foot-bottom">
      <span>© <?= date('Y') ?> <?= sanitize($businessName) ?>. All rights reserved.</span>
      <span><?= sanitize(siteSetting('footer_note', 'Sole Proprietorship')) ?> · <a href="portal/auth.php">Staff sign-in</a></span>
    </div>
  </div>
</footer>
<a href="#hero" class="to-top" aria-label="Back to top"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i></a>

<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script src="<?= $assetVersion('assets/js/landing.js') ?>"></script>
<script src="assets/js/public-help-nav.js"></script>
<script src="assets/js/button-loading.js"></script>
</body>
</html>
