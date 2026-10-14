<?php
require_once __DIR__ . '/../../includes/auth.php';
requirePermission('settings.view');

$currentUser = currentStaff() ?: currentUser();
$message = '';
$error = '';

$settingDefinitions = [
    'business_name' => ['default' => 'CTECHLIT & SYSTEMS', 'type' => 'string', 'public' => true, 'description' => 'Business name shown across the portal and public website'],
    'business_tagline' => ['default' => 'Internet Café & Digital Services', 'type' => 'string', 'public' => true, 'description' => 'Short tagline shown with the business name'],
    'currency' => ['default' => 'USD', 'type' => 'string', 'public' => true, 'description' => 'Default billing currency'],
    'timezone' => ['default' => 'GMT', 'type' => 'string', 'public' => false, 'description' => 'Default application timezone for Ghana and other business operations'],
    'hourly_rate' => ['default' => '2.00', 'type' => 'number', 'public' => true, 'description' => 'Base hourly station rate'],
    'session_timeout_minutes' => ['default' => '480', 'type' => 'number', 'public' => false, 'description' => 'Maximum duration before automatic logout'],
    'max_concurrent_sessions' => ['default' => '6', 'type' => 'number', 'public' => false, 'description' => 'Maximum simultaneous active sessions'],
    'allow_walkins' => ['default' => 'true', 'type' => 'boolean', 'public' => true, 'description' => 'Allow walk-in customers to use the service without an account'],
    'maintenance_mode' => ['default' => 'false', 'type' => 'boolean', 'public' => false, 'description' => 'Temporarily disable customer-facing access'],
    'print_cost_bw' => ['default' => '0.10', 'type' => 'number', 'public' => true, 'description' => 'Black and white print cost per page'],
    'print_cost_color' => ['default' => '0.50', 'type' => 'number', 'public' => true, 'description' => 'Colour print cost per page'],
    'contact_address' => ['default' => 'Central Daffiama, Daffiama-Bussie-Issa District, Upper West Region', 'type' => 'string', 'public' => true, 'description' => 'Primary customer contact address'],
    'contact_phone' => ['default' => '+233 24 123 4567', 'type' => 'string', 'public' => true, 'description' => 'Public phone number'],
    'contact_phone_raw' => ['default' => '233241234567', 'type' => 'string', 'public' => true, 'description' => 'Dialable phone number used in call links'],
    'contact_email' => ['default' => 'ctechlit@gmail.com', 'type' => 'string', 'public' => true, 'description' => 'Primary public email address'],
    'opening_hours' => ['default' => 'Monday – Saturday, 7:00 AM – 8:00 PM', 'type' => 'string', 'public' => true, 'description' => 'Store opening hours displayed publicly'],
    'map_url' => ['default' => 'https://www.google.com/maps/search/?api=1&query=Central+Daffiama+Upper+West+Region', 'type' => 'string', 'public' => true, 'description' => 'Google Maps link for directions'],
];

$settings = [];
foreach ($settingDefinitions as $key => $config) {
    $settings[$key] = $config['default'];
}

if (class_exists('Database') && Database::tableExists('system_settings')) {
    try {
        foreach ($settingDefinitions as $key => $config) {
            $value = Database::fetchColumn('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1', [$key]);
            if ($value !== null && $value !== '') {
                $settings[$key] = (string) $value;
            }
        }
    } catch (Throwable $e) {
        logWarning('Unable to load system settings for admin panel: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        try {
            $settingsToPersist = [];
            foreach ($settingDefinitions as $key => $config) {
                $value = $_POST[$key] ?? $config['default'];

                if ($config['type'] === 'boolean') {
                    $value = (isset($_POST[$key]) && in_array($_POST[$key], ['1', 'on', 'true', 'yes'], true)) ? 'true' : 'false';
                } elseif ($config['type'] === 'number') {
                    $value = (string) (floatval($value) ?: 0);
                } elseif ($key === 'currency') {
                    $value = trim(strtoupper((string) $value));
                } elseif ($key === 'contact_phone') {
                    $value = trim((string) $value);
                    $settingsToPersist['contact_phone_raw'] = preg_replace('/\D+/', '', $value) ?: '233241234567';
                } elseif ($key === 'contact_phone_raw') {
                    continue;
                } else {
                    $value = trim((string) $value);
                }

                $settingsToPersist[$key] = $value;
            }

            if (!empty($settingsToPersist['contact_phone'])) {
                $settingsToPersist['contact_phone_raw'] = preg_replace('/\D+/', '', $settingsToPersist['contact_phone']) ?: '233241234567';
            }

            Database::beginTransaction();
            foreach ($settingsToPersist as $key => $value) {
                $config = $settingDefinitions[$key] ?? ['type' => 'string', 'public' => false, 'description' => 'System setting'];
                Database::execute(
                    'INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public, updated_by) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), data_type = VALUES(data_type), description = VALUES(description), is_public = VALUES(is_public), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP',
                    [$key, (string) $value, $config['type'], $config['description'], (int) ($config['public'] ? 1 : 0), $currentUser['staff_id'] ?? null]
                );
            }
            Database::commit();

            foreach ($settingsToPersist as $key => $val) {
                $settings[$key] = (string) $val;
            }
            $message = 'System settings updated successfully';
        } catch (Throwable $e) {
            if (class_exists('Database')) {
                Database::rollback();
            }
            $error = 'Unable to save settings. Please check your database connection and try again.';
            logError('Failed to persist system settings: ' . $e->getMessage());
        }
    }
}

$csrfToken = generateCsrfToken();
$currencySymbol = strtoupper($settings['currency'] ?? 'USD');
$quickRate = number_format((float) ($settings['hourly_rate'] ?? 2.00), 2);
$sessionLimit = (int) ($settings['max_concurrent_sessions'] ?? 6);
$maintenanceMode = ($settings['maintenance_mode'] ?? 'false') === 'true';
$allowWalkins = ($settings['allow_walkins'] ?? 'true') === 'true';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Settings — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
  <style>
    .page-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px; }
    .page-header h1 { font-size: clamp(2rem, 3vw, 2.6rem); margin: 0; }
    .page-header p { color: var(--muted); margin-top: 6px; }
    .section-header .eyebrow { font-size: 12px; letter-spacing: 0.12em; font-weight: 700; text-transform: uppercase; color: var(--violet); }
    .section-header .title { font-size: 20px; font-weight: 700; color: var(--ink); }
    .settings-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px; }
    .settings-grid .field { display: flex; flex-direction: column; gap: 8px; }
    .settings-grid .field.full { grid-column: 1 / -1; }
    .settings-grid label, .toggle-row label { font-size: 13px; font-weight: 600; color: var(--ink); }
    .settings-grid input, .settings-grid select, .settings-grid textarea { width: 100%; border: 1px solid rgba(148, 163, 184, 0.35); border-radius: 12px; padding: 12px 14px; background: rgba(255,255,255,0.72); color: var(--ink); transition: var(--transition); font: inherit; }
    .settings-grid textarea { min-height: 110px; resize: vertical; }
    .settings-grid input:focus, .settings-grid select:focus, .settings-grid textarea:focus { outline: none; border-color: rgba(124, 58, 237, 0.55); box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12); }
    .toggle-list { display: grid; gap: 14px; }
    .toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; background: rgba(148,163,184,0.08); border-radius: 14px; border: 1px solid rgba(148,163,184,0.15); }
    .toggle-row .meta { display: flex; flex-direction: column; gap: 4px; }
    .toggle-row .meta strong { font-size: 14px; color: var(--ink); }
    .toggle-row .meta span { font-size: 12px; color: var(--muted); }
    .toggle-row input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--violet); }
    .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; margin-bottom: 24px; }
    .stat-card { padding: 18px; border-radius: 16px; background: rgba(255,255,255,0.7); border: 1px solid rgba(255,255,255,0.8); box-shadow: var(--shadow-md); }
    .stat-card .label { color: var(--muted); font-size: 12px; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }
    .stat-card .value { margin-top: 10px; font-size: 1.5rem; font-weight: 700; color: var(--ink); }
    .stat-card .subtle { font-size: 12px; color: var(--muted); margin-top: 4px; }
    @media (max-width: 900px) { .stats-grid, .settings-grid { grid-template-columns: 1fr; } .page-header { flex-direction: column; align-items: flex-start; } }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>
    <div class="portal-content">
      <?php if ($message): ?>
        <div class="card animate-fade-in" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--green); margin-bottom: 20px; padding: 14px 20px; border-radius: var(--radius-lg);">
          <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
          <?php echo sanitize($message); ?>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="card animate-fade-in" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--red); margin-bottom: 20px; padding: 14px 20px; border-radius: var(--radius-lg);">
          <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
          <?php echo sanitize($error); ?>
        </div>
      <?php endif; ?>
      <div class="page-header">
        <div>
          <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--violet); margin-bottom: 8px;">System controls</div>
          <h1>System Settings</h1>
          <p>Manage the business profile, pricing rules, access controls, and customer-facing experience.</p>
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap;">
          <a href="<?php echo base_path('', false); ?>" target="_blank" rel="noopener noreferrer" class="header-filter" style="display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:10px 16px; text-decoration:none; border:1px solid rgba(148,163,184,0.25); color:var(--ink); background:rgba(255,255,255,0.6); border-radius:12px; font-weight:600;"> <i class="fas fa-external-link-alt"></i> View site </a>
          <button type="submit" form="settings-form" class="header-filter" style="background: linear-gradient(135deg, var(--violet), var(--cyan)); color:white; border:none; padding:10px 18px; border-radius:12px; font-weight:700; cursor:pointer;">
            <i class="fas fa-save"></i> Save changes
          </button>
        </div>
      </div>
      <div class="stats-grid">
        <div class="stat-card">
          <div class="label">Hourly rate</div>
          <div class="value"><?php echo sanitize($currencySymbol); ?> <?php echo sanitize($quickRate); ?></div>
          <div class="subtle">Current station pricing</div>
        </div>
        <div class="stat-card">
          <div class="label">Session cap</div>
          <div class="value"><?php echo (int) $sessionLimit; ?></div>
          <div class="subtle">Concurrent active users</div>
        </div>
        <div class="stat-card">
          <div class="label">Walk-ins</div>
          <div class="value"><?php echo $allowWalkins ? 'Enabled' : 'Disabled'; ?></div>
          <div class="subtle">Customer self-service access</div>
        </div>
        <div class="stat-card">
          <div class="label">Maintenance</div>
          <div class="value"><?php echo $maintenanceMode ? 'On' : 'Off'; ?></div>
          <div class="subtle">Public site status</div>
        </div>
      </div>
      <form id="settings-form" method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">
        <div class="card glass-card animate-fade-in" style="margin-bottom: 20px; padding: 22px; border-radius: 18px;">
          <div class="section-header">
            <div>
              <div class="eyebrow">Business profile</div>
              <div class="title">Brand & location</div>
            </div>
          </div>
          <div class="settings-grid">
            <div class="field">
              <label for="business_name">Business name</label>
              <input id="business_name" name="business_name" type="text" value="<?php echo sanitize($settings['business_name'] ?? 'CTECHLIT & SYSTEMS'); ?>">
            </div>
            <div class="field">
              <label for="business_tagline">Business tagline</label>
              <input id="business_tagline" name="business_tagline" type="text" value="<?php echo sanitize($settings['business_tagline'] ?? 'Internet Café & Digital Services'); ?>">
            </div>
            <div class="field">
              <label for="currency">Default currency</label>
              <select id="currency" name="currency">
                <option value="USD" <?php echo (($settings['currency'] ?? 'USD') === 'USD') ? 'selected' : ''; ?>>USD</option>
                <option value="KES" <?php echo (($settings['currency'] ?? 'USD') === 'KES') ? 'selected' : ''; ?>>KES</option>
                <option value="GHS" <?php echo (($settings['currency'] ?? 'USD') === 'GHS') ? 'selected' : ''; ?>>GHS</option>
                <option value="GBP" <?php echo (($settings['currency'] ?? 'USD') === 'GBP') ? 'selected' : ''; ?>>GBP</option>
              </select>
            </div>
            <div class="field">
              <label for="timezone">Timezone</label>
              <select id="timezone" name="timezone">
                <option value="GMT" <?php echo (($settings['timezone'] ?? 'GMT') === 'GMT') ? 'selected' : ''; ?>>GMT</option>
                <option value="UTC" <?php echo (($settings['timezone'] ?? 'GMT') === 'UTC') ? 'selected' : ''; ?>>UTC</option>
                <option value="Africa/Accra" <?php echo (($settings['timezone'] ?? 'GMT') === 'Africa/Accra') ? 'selected' : ''; ?>>Africa/Accra</option>
                <option value="Africa/Nairobi" <?php echo (($settings['timezone'] ?? 'GMT') === 'Africa/Nairobi') ? 'selected' : ''; ?>>Africa/Nairobi</option>
                <option value="America/New_York" <?php echo (($settings['timezone'] ?? 'GMT') === 'America/New_York') ? 'selected' : ''; ?>>America/New_York</option>
                <option value="America/Los_Angeles" <?php echo (($settings['timezone'] ?? 'GMT') === 'America/Los_Angeles') ? 'selected' : ''; ?>>America/Los_Angeles</option>
              </select>
            </div>
            <div class="field full">
              <label for="contact_address">Address</label>
              <textarea id="contact_address" name="contact_address"><?php echo sanitize($settings['contact_address'] ?? 'Central Daffiama, Daffiama-Bussie-Issa District, Upper West Region'); ?></textarea>
            </div>
            <div class="field">
              <label for="contact_email">Support email</label>
              <input id="contact_email" name="contact_email" type="email" value="<?php echo sanitize($settings['contact_email'] ?? 'ctechlit@gmail.com'); ?>">
            </div>
            <div class="field">
              <label for="contact_phone">Public phone</label>
              <input id="contact_phone" name="contact_phone" type="text" value="<?php echo sanitize($settings['contact_phone'] ?? '+233 24 123 4567'); ?>">
            </div>
            <div class="field">
              <label for="opening_hours">Opening hours</label>
              <input id="opening_hours" name="opening_hours" type="text" value="<?php echo sanitize($settings['opening_hours'] ?? 'Monday – Saturday, 7:00 AM – 8:00 PM'); ?>">
            </div>
            <div class="field full">
              <label for="map_url">Map link</label>
              <input id="map_url" name="map_url" type="url" value="<?php echo sanitize($settings['map_url'] ?? 'https://www.google.com/maps/search/?api=1&query=Central+Daffiama+Upper+West+Region'); ?>">
            </div>
          </div>
        </div>
        <div class="card glass-card animate-fade-in" style="margin-bottom: 20px; padding: 22px; border-radius: 18px;">
          <div class="section-header">
            <div>
              <div class="eyebrow">Preferences</div>
              <div class="title">Operational switches</div>
            </div>
          </div>
          <div class="toggle-list">
            <div class="toggle-row">
              <div class="meta">
                <strong>Maintenance mode</strong>
                <span>Temporarily block public access and show a maintenance banner.</span>
              </div>
              <label>
                <input type="checkbox" name="maintenance_mode" value="true" <?php echo ($settings['maintenance_mode'] ?? 'false') === 'true' ? 'checked' : ''; ?>>
              </label>
            </div>
            <div class="toggle-row">
              <div class="meta">
                <strong>Walk-in customers</strong>
                <span>Allow customers to use the system without a registered account.</span>
              </div>
              <label>
                <input type="checkbox" name="allow_walkins" value="true" <?php echo ($settings['allow_walkins'] ?? 'true') === 'true' ? 'checked' : ''; ?>>
              </label>
            </div>
          </div>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
