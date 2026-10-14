<?php
/**
 * CTECHLIT & SYSTEMS — Shared SEO / Open Graph / JSON-LD include
 *
 * Expected variables set before include (all optional):
 *   $seoTitle       — page title (falls back to business name + tagline)
 *   $seoDescription — meta description (falls back to business description)
 *   $seoCanonical   — canonical path (falls back to current script name)
 *   $seoImage       — absolute URL for og:image / twitter:image
 *   $seoType        — og:type (default: website)
 *   $seoNoIndex     — bool: add robots noindex
 */

$businessName = siteSetting('business_name', 'CTECHLIT & SYSTEMS');
$businessTagline = siteSetting('business_tagline', 'Internet Café & Digital Services');
$contactAddress = siteSetting('contact_address', 'Central Daffiama, Daffiama-Bussie-Issa District, Upper West Region');
$contactPhone = siteSetting('contact_phone', '+233 24 123 4567');
$contactPhoneRaw = siteSetting('contact_phone_raw', '233241234567');
$contactEmail = siteSetting('contact_email', 'ctechlit@gmail.com');
$openingHours = siteSetting('opening_hours', 'Monday – Saturday, 7:00 AM – 8:00 PM');
$mapUrl = siteSetting('map_url', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($contactAddress));
$footerAbout = siteSetting('footer_about', "Daffiama's digital access, ICT training and technology support centre.");
$appUrl = rtrim((string) ($GLOBALS['config']['app']['url'] ?? 'http://localhost'), '/');

$seoTitle = $seoTitle ?? ($businessName . ' — ' . $businessTagline);
$seoDescription = $seoDescription ?? $footerAbout;
$seoCanonical = $seoCanonical ?? strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
$seoImage = $seoImage ?? ($appUrl . '/assets/images/logo.svg');
$seoType = $seoType ?? 'website';
$seoNoIndex = $seoNoIndex ?? false;

$seoTitle = sanitize($seoTitle);
$seoDescription = sanitize($seoDescription);
$seoCanonical = sanitize($seoCanonical);
$seoImage = sanitize($seoImage);

$canonicalUrl = $seoCanonical;
if (strpos($seoCanonical, '://') === false) {
    $canonicalUrl = rtrim($appUrl, '/') . '/' . ltrim($seoCanonical, '/');
}

$hoursLines = preg_split('/[,;]|\band\b/', $openingHours);
$hoursLines = array_map('trim', $hoursLines);
$hoursLines = array_values(array_filter($hoursLines, fn($h) => $h !== ''));
if ($hoursLines === []) {
    $hoursLines = ['Monday-Saturday 07:00-20:00'];
}

$dayMap = [
    'monday' => 'Monday', 'mon' => 'Monday',
    'tuesday' => 'Tuesday', 'tue' => 'Tuesday',
    'wednesday' => 'Wednesday', 'wed' => 'Wednesday',
    'thursday' => 'Thursday', 'thu' => 'Thursday',
    'friday' => 'Friday', 'fri' => 'Friday',
    'saturday' => 'Saturday', 'sat' => 'Saturday',
    'sunday' => 'Sunday', 'sun' => 'Sunday',
];

$daysOpen = [];
foreach ($dayMap as $key => $day) {
    if (stripos($openingHours, $key) !== false) {
        $daysOpen[$day] = true;
    }
}
if ($daysOpen === []) {
    $daysOpen = ['Monday' => true, 'Tuesday' => true, 'Wednesday' => true, 'Thursday' => true, 'Friday' => true, 'Saturday' => true];
}
$daysOpen = array_keys($daysOpen);

$opens = '07:00';
$closes = '20:00';
if (preg_match('/(\d{1,2}(?::\d{2})?\s*(?:AM|PM)?)\s*(?:–|-|to)\s*(\d{1,2}(?::\d{2})?\s*(?:AM|PM)?)/i', $openingHours, $timeMatch)) {
    $opens = date('H:i', strtotime($timeMatch[1]));
    $closes = date('H:i', strtotime($timeMatch[2]));
}

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $businessName,
    'description' => $footerAbout,
    'url' => $canonicalUrl,
    'telephone' => $contactPhone,
    'email' => $contactEmail,
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => $contactAddress,
        'addressLocality' => 'Daffiama',
        'addressRegion' => 'Upper West Region',
        'addressCountry' => 'GH',
    ],
    'openingHoursSpecification' => [[
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => $daysOpen,
        'opens' => $opens,
        'closes' => $closes,
    ]],
    'priceRange' => siteSetting('currency_symbol', 'GHS') . ' 1 – 50',
    'image' => $seoImage,
];
?>
<meta name="description" content="<?= $seoDescription ?>">
<link rel="canonical" href="<?= $canonicalUrl ?>">
<meta property="og:site_name" content="<?= $seoTitle ?>">
<meta property="og:type" content="<?= sanitize($seoType) ?>">
<meta property="og:title" content="<?= $seoTitle ?>">
<meta property="og:description" content="<?= $seoDescription ?>">
<meta property="og:url" content="<?= $canonicalUrl ?>">
<meta property="og:image" content="<?= $seoImage ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $seoTitle ?>">
<meta name="twitter:description" content="<?= $seoDescription ?>">
<meta name="twitter:image" content="<?= $seoImage ?>">
<?php if ($seoNoIndex): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<script type="application/ld+json">
<?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?>
</script>
