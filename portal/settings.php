<?php
/**
 * System Settings — business profile, contact & hours, pricing & operations, website content,
 * security and system health. Every editable setting here is read somewhere in the system.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/device-control.php';
require_once __DIR__ . '/../includes/paystack.php';
requireStaffAuth();
requirePermission('settings.view');

$canEdit = hasPermission('settings.edit');
$currentStaffRow = currentStaff();

// Live "open now" preview for the opening-hours field.
if (isset($_GET['hours_preview'])) {
    header('Content-Type: application/json');
    $status = siteOpenStatus(mb_substr((string) $_GET['hours_preview'], 0, 120));
    echo json_encode(['success' => true, 'data' => $status]);
    exit;
}

/**
 * Field schema. type: text|textarea|email|url|number|money|toggle|select.
 * public = shown on the public website (stored with is_public = 1 so the site can read it).
 */
$sections = [
    'business' => [
        'title' => 'Business profile', 'icon' => 'fa-building', 'intro' => 'How the business is named and how money is shown across the portal, receipts and website.',
        'fields' => [
            'business_name' => ['label' => 'Business name', 'type' => 'text', 'default' => 'CTECHLIT & SYSTEMS', 'public' => true, 'max' => 100, 'required' => true, 'help' => 'Shown on receipts, invoices, the website and the browser tab.'],
            'business_tagline' => ['label' => 'Tagline', 'type' => 'text', 'default' => 'Internet Café & Digital Services', 'public' => true, 'max' => 120, 'help' => 'Short line under the business name.'],
            'currency' => ['label' => 'Currency', 'type' => 'select', 'default' => 'GHS', 'public' => true, 'options' => ['GHS' => 'GHS — Ghana cedi', 'USD' => 'USD — US dollar', 'NGN' => 'NGN — Nigerian naira', 'XOF' => 'XOF — West African CFA franc', 'EUR' => 'EUR — Euro', 'GBP' => 'GBP — Pound sterling'], 'help' => 'Billing currency for sales, invoices and online payments.'],
            'currency_symbol' => ['label' => 'Currency label on screen', 'type' => 'text', 'default' => 'GHS', 'public' => true, 'max' => 6, 'required' => true, 'help' => 'What prices start with, e.g. "GHS" or "GH₵". Changes every price on screen and on receipts.'],
            'footer_about' => ['label' => 'About text (website footer)', 'type' => 'textarea', 'default' => '', 'public' => true, 'max' => 300, 'help' => 'One or two sentences about the business. Also used as the search-engine description.'],
            'footer_note' => ['label' => 'Footer note', 'type' => 'text', 'default' => 'Sole Proprietorship', 'public' => true, 'max' => 150, 'help' => 'Small print in the website footer, e.g. registration details.'],
        ],
    ],
    'contact' => [
        'title' => 'Contact & opening hours', 'icon' => 'fa-location-dot', 'intro' => 'How customers reach you. The website works out "Open now / Closed" from the opening hours.',
        'fields' => [
            'contact_phone' => ['label' => 'Phone number', 'type' => 'text', 'default' => '', 'public' => true, 'max' => 30, 'required' => true, 'help' => 'Shown as written; the call link uses the digits only.'],
            'contact_email' => ['label' => 'Email address', 'type' => 'email', 'default' => '', 'public' => true, 'max' => 120, 'required' => true],
            'contact_address' => ['label' => 'Address', 'type' => 'textarea', 'default' => '', 'public' => true, 'max' => 250, 'required' => true, 'help' => 'Also used for the map on the website.'],
            'opening_hours' => ['label' => 'Opening hours', 'type' => 'text', 'default' => 'Monday – Saturday, 8:00 AM – 9:00 PM', 'public' => true, 'max' => 120, 'help' => 'Write it like "Monday – Saturday, 8:00 AM – 9:00 PM" or "Daily 7am – 10pm" so the open/closed status works.', 'preview' => 'hours'],
            'map_url' => ['label' => 'Directions link', 'type' => 'url', 'default' => '', 'public' => true, 'max' => 500, 'help' => 'A Google Maps link opened by the "Directions" buttons.', 'test' => true],
        ],
    ],
    'operations' => [
        'title' => 'Pricing & operations', 'icon' => 'fa-sliders', 'intro' => 'Default rates and the rules the front desk works by.',
        'fields' => [
            'hourly_rate' => ['label' => 'Station time per hour', 'type' => 'money', 'default' => '2.00', 'public' => true, 'min' => 0, 'max' => 10000, 'help' => 'Used for open sessions and the Sessions page. The "Internet Browsing" service price is used at the POS.'],
            'print_cost_bw' => ['label' => 'Black & white print, per side', 'type' => 'money', 'default' => '0.50', 'public' => true, 'min' => 0, 'max' => 1000, 'help' => 'Fallback only — the price of the "Black & White Printing" service is used when it exists.'],
            'print_cost_color' => ['label' => 'Colour print, per side', 'type' => 'money', 'default' => '2.00', 'public' => true, 'min' => 0, 'max' => 1000, 'help' => 'Fallback only — the price of the "Color Printing" service is used when it exists.'],
            'business_hours_per_day' => ['label' => 'Opening hours per day', 'type' => 'number', 'default' => '12', 'public' => false, 'min' => 1, 'max' => 24, 'step' => 0.5, 'suffix' => 'hours', 'help' => 'Analytics measures station utilisation against this.'],
            'max_concurrent_sessions' => ['label' => 'Maximum active sessions', 'type' => 'number', 'default' => '0', 'public' => false, 'min' => 0, 'max' => 500, 'step' => 1, 'suffix' => 'sessions', 'help' => 'New sessions are refused at this number. 0 = no limit (the number of stations is the natural limit).'],
            'allow_walkins' => ['label' => 'Allow walk-in sales', 'type' => 'toggle', 'default' => 'true', 'public' => true, 'help' => 'When off, the POS asks for a named customer before charging.'],
            'maintenance_mode' => ['label' => 'Maintenance notice on website', 'type' => 'toggle', 'default' => 'false', 'public' => false, 'help' => 'Shows a "some services may be unavailable" banner at the top of the public website.', 'danger' => true],
        ],
    ],
    'website' => [
        'title' => 'Website content', 'icon' => 'fa-globe', 'intro' => 'The headline text on the public homepage. Services, prices, FAQs and reviews come from their own pages.',
        'fields' => [
            'hero_eyebrow' => ['label' => 'Hero label', 'type' => 'text', 'default' => 'Internet Café & Digital Services', 'public' => true, 'max' => 80, 'group' => 'Hero'],
            'hero_title' => ['label' => 'Hero headline', 'type' => 'text', 'default' => 'A faster, friendlier place to browse, print and get things done', 'public' => true, 'max' => 120, 'required' => true, 'group' => 'Hero'],
            'hero_highlight' => ['label' => 'Highlighted words', 'type' => 'text', 'default' => 'faster, friendlier', 'public' => true, 'max' => 60, 'group' => 'Hero', 'help' => 'Part of the headline shown in the brand gradient. Must appear in the headline.'],
            'hero_lead' => ['label' => 'Hero introduction', 'type' => 'textarea', 'default' => '', 'public' => true, 'max' => 300, 'group' => 'Hero'],
            'gap_first_card' => ['label' => '"All-in-one" card text', 'type' => 'text', 'default' => '', 'public' => true, 'max' => 160, 'group' => 'Highlights'],
            'services_eyebrow' => ['label' => 'Services label', 'type' => 'text', 'default' => 'Services', 'public' => true, 'max' => 40, 'group' => 'Services section'],
            'services_title' => ['label' => 'Services heading', 'type' => 'text', 'default' => '', 'public' => true, 'max' => 120, 'group' => 'Services section'],
            'services_lead' => ['label' => 'Services introduction', 'type' => 'textarea', 'default' => '', 'public' => true, 'max' => 250, 'group' => 'Services section'],
            'pricing_eyebrow' => ['label' => 'Pricing label', 'type' => 'text', 'default' => 'Pricing', 'public' => true, 'max' => 40, 'group' => 'Pricing section'],
            'pricing_title' => ['label' => 'Pricing heading', 'type' => 'text', 'default' => '', 'public' => true, 'max' => 120, 'group' => 'Pricing section'],
            'pricing_lead' => ['label' => 'Pricing introduction', 'type' => 'textarea', 'default' => '', 'public' => true, 'max' => 250, 'group' => 'Pricing section'],
            'visit_eyebrow' => ['label' => 'Visit label', 'type' => 'text', 'default' => 'Visit us', 'public' => true, 'max' => 40, 'group' => 'Visit section'],
            'visit_title' => ['label' => 'Visit heading', 'type' => 'text', 'default' => '', 'public' => true, 'max' => 120, 'group' => 'Visit section'],
            'visit_lead' => ['label' => 'Visit introduction', 'type' => 'textarea', 'default' => '', 'public' => true, 'max' => 250, 'group' => 'Visit section'],
        ],
    ],
    'security' => [
        'title' => 'Security', 'icon' => 'fa-shield-halved', 'intro' => 'Sign-in protection for staff. Server-level settings are shown read-only below.',
        'fields' => [
            'session_timeout_minutes' => ['label' => 'Sign staff out after inactivity', 'type' => 'number', 'default' => '120', 'public' => false, 'min' => 5, 'max' => 1440, 'step' => 5, 'suffix' => 'minutes', 'help' => 'Idle staff are signed out after this long. Everyone is signed out after the daily maximum (SESSION_MAX_AGE).'],
        ],
    ],
];

$fieldIndex = [];
foreach ($sections as $sectionKey => $section) {
    foreach ($section['fields'] as $key => $field) {
        $fieldIndex[$key] = $field + ['section' => $sectionKey];
    }
}

function settingsLoad(array $fieldIndex): array
{
    $values = [];
    foreach ($fieldIndex as $key => $field) {
        $values[$key] = $field['default'];
    }
    foreach (Database::fetchAll('SELECT setting_key, setting_value FROM system_settings') as $row) {
        if (array_key_exists($row['setting_key'], $values)) {
            $values[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $values;
}

/** Normalise + validate one submitted value. Returns [value, error|null]. */
function settingsClean(array $field, $raw): array
{
    $type = $field['type'];
    if ($type === 'toggle') {
        return [in_array((string) $raw, ['1', 'on', 'true', 'yes'], true) ? 'true' : 'false', null];
    }
    $value = trim(str_replace(["\r\n", "\r"], "\n", (string) $raw));
    if ($type !== 'textarea') {
        $value = preg_replace('/\s+/', ' ', $value);
    }
    if (!empty($field['required']) && $value === '') {
        return [$value, 'This is required.'];
    }
    if (isset($field['max']) && $type !== 'number' && $type !== 'money' && mb_strlen($value) > $field['max']) {
        return [$value, 'Keep this to ' . $field['max'] . ' characters or fewer.'];
    }
    if ($value === '') {
        return [$value, null];
    }
    switch ($type) {
        case 'email':
            return [$value, filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.'];
        case 'url':
            $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
            return [$value, filter_var($value, FILTER_VALIDATE_URL) && in_array($scheme, ['http', 'https'], true) ? null : 'Enter a full link starting with https://'];
        case 'select':
            return [$value, isset($field['options'][$value]) ? null : 'Choose one of the options.'];
        case 'number':
        case 'money':
            if (!is_numeric($value)) {
                return [$value, 'Enter a number.'];
            }
            $number = (float) $value;
            if ((isset($field['min']) && $number < $field['min']) || (isset($field['max']) && $number > $field['max'])) {
                return [$value, 'Enter a value between ' . $field['min'] . ' and ' . $field['max'] . '.'];
            }
            return [$type === 'money' ? number_format($number, 2, '.', '') : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.'), null];
    }
    return [$value, null];
}

$values = settingsLoad($fieldIndex);
$errors = [];
$flash = $_SESSION['settings_flash'] ?? null;
unset($_SESSION['settings_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$canEdit) {
        http_response_code(403);
        $errors['_form'] = 'You can view settings but not change them. Ask an administrator for the "Edit Settings" permission.';
    } elseif (!verifyCsrfToken((string) ($_POST['csrf_token'] ?? ''))) {
        $errors['_form'] = 'This form expired. Reload the page and try again.';
    } else {
        $submitted = [];
        foreach ($fieldIndex as $key => $field) {
            if ($field['type'] !== 'toggle' && !array_key_exists($key, $_POST)) {
                continue; // field not on the form — leave unchanged
            }
            [$clean, $error] = settingsClean($field, $_POST[$key] ?? '');
            $submitted[$key] = $clean;
            if ($error) {
                $errors[$key] = $error;
            }
        }
        // Cross-field rules
        $title = $submitted['hero_title'] ?? $values['hero_title'];
        $highlight = $submitted['hero_highlight'] ?? $values['hero_highlight'];
        if ($highlight !== '' && stripos($title, $highlight) === false) {
            $errors['hero_highlight'] = 'These words must appear exactly in the hero headline.';
        }
        if (isset($submitted['opening_hours']) && $submitted['opening_hours'] !== '' && siteOpenStatus($submitted['opening_hours']) === null) {
            $errors['opening_hours'] = $errors['opening_hours'] ?? 'Use a format like "Monday – Saturday, 8:00 AM – 9:00 PM" so the open/closed status can be worked out.';
        }

        if ($errors) {
            $values = array_merge($values, $submitted);
            $errors['_form'] = 'Nothing was saved. Fix the ' . (count($errors) === 1 ? 'highlighted field' : count($errors) . ' highlighted fields') . ' and save again.';
        } else {
            $changed = array_keys(array_filter($submitted, static function ($v, $k) use ($values, $fieldIndex) {
                // "2" and "2.00" are the same price — compare numbers by value, everything else as text.
                if (in_array($fieldIndex[$k]['type'], ['number', 'money'], true) && is_numeric($v) && is_numeric($values[$k])) {
                    return abs((float) $v - (float) $values[$k]) > 0.0001;
                }
                return (string) $v !== (string) $values[$k];
            }, ARRAY_FILTER_USE_BOTH));
            if (in_array('contact_phone', $changed, true)) {
                $submitted['contact_phone_raw'] = preg_replace('/\D+/', '', $submitted['contact_phone']);
                $changed[] = 'contact_phone_raw';
            }
            try {
                Database::beginTransaction();
                foreach ($changed as $key) {
                    $field = $fieldIndex[$key] ?? ['type' => 'text', 'public' => true, 'label' => 'Dialable phone number'];
                    $dataType = in_array($field['type'], ['number', 'money'], true) ? 'number' : ($field['type'] === 'toggle' ? 'boolean' : 'string');
                    Database::execute(
                        'INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public, updated_by) VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), data_type = VALUES(data_type), description = VALUES(description), is_public = VALUES(is_public), updated_by = VALUES(updated_by)',
                        [$key, (string) $submitted[$key], $dataType, $field['label'], !empty($field['public']) ? 1 : 0, $currentStaffRow['staff_id'] ?? null]
                    );
                }
                Database::commit();
                if ($changed) {
                    $before = array_intersect_key($values, array_flip($changed));
                    logAuditTrail('settings_updated', 'system_settings', null, [
                        'changed' => array_values(array_diff($changed, ['contact_phone_raw'])),
                        'from' => array_map(static fn($v) => mb_substr((string) $v, 0, 80), array_diff_key($before, ['contact_phone_raw' => 1])),
                        'to' => array_map(static fn($v) => mb_substr((string) $v, 0, 80), array_intersect_key($submitted, array_flip(array_diff($changed, ['contact_phone_raw'])))),
                    ]);
                }
                $_SESSION['settings_flash'] = $changed
                    ? ['type' => 'success', 'text' => count(array_diff($changed, ['contact_phone_raw'])) . ' setting' . (count(array_diff($changed, ['contact_phone_raw'])) === 1 ? '' : 's') . ' saved.', 'keys' => array_values(array_diff($changed, ['contact_phone_raw']))]
                    : ['type' => 'info', 'text' => 'No changes to save.', 'keys' => []];
                $anchor = preg_match('/^[a-z]+$/', (string) ($_POST['_section'] ?? '')) ? '#' . $_POST['_section'] : '';
                header('Location: settings.php' . $anchor, true, 303);
                exit;
            } catch (Throwable $e) {
                Database::rollback();
                logError('Failed to save settings: ' . $e->getMessage());
                $values = array_merge($values, $submitted);
                $errors['_form'] = 'The settings could not be saved. Please try again.';
            }
        }
    }
}

// ---------- Read-only status ----------
$env = static fn(string $k): string => trim((string) ($_ENV[$k] ?? getenv($k) ?: ''));
$isSet = static function (string $value): bool {
    return $value !== '' && !preg_match('/^(your_|xxx|changeme|sk_test_x|pk_test_x)/i', $value) && !str_contains(strtolower($value), 'your_');
};
$idleMinutes = (int) $values['session_timeout_minutes'] >= 5 ? (int) $values['session_timeout_minutes'] : intdiv((int) ($GLOBALS['config']['security']['session_lifetime'] ?? 7200), 60);
$maxAgeHours = round(((int) ($env('SESSION_MAX_AGE') ?: 50400)) / 3600, 1);
$root = dirname(__DIR__);
$securityChecks = [
    ['Debug mode off', !filter_var($env('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN), 'APP_DEBUG=false in config/.env — errors are not shown to visitors.', 'Turn APP_DEBUG off in config/.env; it shows error details to visitors.'],
    ['HTTPS connection', requestIsHttps(), 'This page was loaded over HTTPS.', 'You are on plain HTTP. Fine on the café LAN; the public site must use HTTPS.'],
    ['Sign-in lockout', true, '5 wrong passwords per account (20 per network) pauses sign-in for 15 minutes.', ''],
    ['Staff session limits', true, 'Idle sign-out after ' . $idleMinutes . ' min · always after ' . $maxAgeHours . ' h.', ''],
    ['Web server protection', is_file($root . '/.htaccess') && str_contains((string) @file_get_contents($root . '/.htaccess'), 'Content-Security-Policy'), 'Config, logs, git and migrations are blocked; security headers are on.', 'The root .htaccess security rules are missing.'],
    ['Uploads cannot run code', is_file($root . '/uploads/.htaccess'), 'Files in uploads/ are served as data only.', 'uploads/.htaccess is missing.'],
    ['Proxy settings', true, $env('TRUSTED_PROXIES') !== '' ? 'Trusting forwarded addresses from: ' . $env('TRUSTED_PROXIES') : 'No proxy configured — visitor addresses come straight from the connection.', ''],
];
$mysqlVersion = (string) Database::fetchColumn('SELECT VERSION()');
$dbSize = (float) Database::fetchColumn('SELECT COALESCE(SUM(data_length + index_length), 0) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
$freeDisk = @disk_free_space($root);
$integrations = [
    ['Device control', 'fa-network-wired', true, (deviceControlMode() === 'lan' ? 'LAN' : ucfirst(deviceControlMode())) . ' mode' . (deviceControlMode() === 'cloud' ? ' — commands reach stations through their agents' : ' — direct control on the café network')],
    ['Station agents', 'fa-desktop', $isSet($env('STATION_AGENT_API_KEY')), $isSet($env('STATION_AGENT_API_KEY')) ? 'Agent key is set' : 'STATION_AGENT_API_KEY is not set'],
    ['Online payments (Paystack)', 'fa-credit-card', paystackConfigured(), paystackConfigured() ? 'Connected' : 'Not configured — add PAYSTACK_SECRET_KEY to accept online payments'],
    ['Jarvis AI', 'fa-robot', $isSet($env('AI_API_KEY')) || $isSet($env('OPENAI_API_KEY')), ($isSet($env('AI_API_KEY')) || $isSet($env('OPENAI_API_KEY'))) ? 'AI key is set' : 'No AI key — device commands still work'],
    ['Jarvis voice', 'fa-microphone', $isSet($env('ELEVENLABS_API_KEY')), $isSet($env('ELEVENLABS_API_KEY')) ? 'Premium voice enabled' : 'Using the browser voice'],
    ['Email', 'fa-envelope', $env('MAIL_HOST') !== '' && $isSet($env('MAIL_PASSWORD')), $env('MAIL_HOST') !== '' ? 'SMTP: ' . $env('MAIL_HOST') : 'No mail server configured'],
];
$writable = [];
foreach (['uploads/branding', 'uploads/print-jobs', 'uploads/help-center', 'logs'] as $dir) {
    $writable[$dir] = is_dir($root . '/' . $dir) && is_writable($root . '/' . $dir);
}
$systemFacts = [
    'Environment' => ($GLOBALS['config']['app']['env'] ?? 'production') . ' · ' . ($GLOBALS['config']['app']['url'] ?? ''),
    'Time zone' => date_default_timezone_get() . ' · server time ' . date('D j M, H:i'),
    'PHP' => PHP_VERSION . ' (' . PHP_SAPI . ')',
    'Database' => 'MySQL/MariaDB ' . $mysqlVersion . ' · ' . number_format($dbSize / 1048576, 1) . ' MB',
    'Free disk space' => $freeDisk !== false ? number_format($freeDisk / 1073741824, 1) . ' GB' : 'unknown',
    'Upload limit' => ini_get('upload_max_filesize') . ' per file',
];
$counts = Database::fetchOne(
    "SELECT (SELECT COUNT(*) FROM staff WHERE is_active = 1) AS staff, (SELECT COUNT(*) FROM users WHERE is_active = 1) AS customers,
            (SELECT COUNT(*) FROM stations) AS stations, (SELECT COUNT(*) FROM services WHERE is_active = 1) AS services"
);
$history = Database::fetchAll(
    "SELECT a.created_at, a.details, s.full_name FROM audit_logs a LEFT JOIN staff s ON s.staff_id = a.staff_id
     WHERE a.action = 'settings_updated' ORDER BY a.audit_id DESC LIMIT 15"
);
$lastSaved = Database::fetchOne('SELECT st.updated_at, sf.full_name FROM system_settings st LEFT JOIN staff sf ON sf.staff_id = st.updated_by ORDER BY st.updated_at DESC LIMIT 1');
$openStatus = siteOpenStatus($values['opening_hours']);
$labelOf = static fn(string $key): string => $fieldIndex[$key]['label'] ?? $key;
$csrf = generateCsrfToken();
$v = static fn(string $file) => filemtime(__DIR__ . '/../assets/' . $file);

function settingsField(string $key, array $field, string $value, ?string $error, bool $canEdit): string
{
    $id = 'f_' . $key;
    $attrs = ' id="' . $id . '" name="' . $key . '" data-key="' . $key . '"' . ($canEdit ? '' : ' disabled') . ($error ? ' aria-invalid="true" aria-describedby="' . $id . '_err"' : '');
    $max = isset($field['max']) && !in_array($field['type'], ['number', 'money'], true) ? ' maxlength="' . (int) $field['max'] . '"' : '';
    $badges = (!empty($field['public']) ? '<span class="st-badge pub" title="Shown on the public website"><i class="fas fa-globe"></i> Public</span>' : '')
        . (!empty($field['danger']) ? '<span class="st-badge warn">Affects customers</span>' : '');
    $html = '<div class="st-field' . ($error ? ' has-error' : '') . '" data-search="' . sanitize(strtolower($field['label'] . ' ' . ($field['help'] ?? '') . ' ' . $key)) . '">';
    if ($field['type'] === 'toggle') {
        $html .= '<div class="st-toggle-row"><div><label class="st-label" for="' . $id . '">' . sanitize($field['label']) . '</label>' . $badges
            . (!empty($field['help']) ? '<p class="st-help">' . sanitize($field['help']) . '</p>' : '') . '</div>'
            . '<label class="st-switch"><input type="checkbox" value="true"' . $attrs . ($value === 'true' ? ' checked' : '') . '><span></span></label></div>';
    } else {
        $html .= '<div class="st-label-row"><label class="st-label" for="' . $id . '">' . sanitize($field['label']) . (!empty($field['required']) ? ' <span class="req" aria-hidden="true">*</span>' : '') . '</label>' . $badges . '</div>';
        switch ($field['type']) {
            case 'textarea':
                $html .= '<textarea class="form-input" rows="3"' . $attrs . $max . '>' . sanitize($value) . '</textarea>';
                break;
            case 'select':
                $html .= '<select class="form-input"' . $attrs . '>';
                foreach ($field['options'] as $optValue => $optLabel) {
                    $html .= '<option value="' . sanitize((string) $optValue) . '"' . ((string) $optValue === $value ? ' selected' : '') . '>' . sanitize($optLabel) . '</option>';
                }
                if (!isset($field['options'][$value]) && $value !== '') {
                    $html .= '<option value="' . sanitize($value) . '" selected>' . sanitize($value) . '</option>';
                }
                $html .= '</select>';
                break;
            case 'number':
            case 'money':
                $prefix = $field['type'] === 'money' ? '<span class="st-affix" data-currency>' . sanitize(siteSetting('currency_symbol', 'GHS')) . '</span>' : '';
                $suffix = !empty($field['suffix']) ? '<span class="st-affix">' . sanitize($field['suffix']) . '</span>' : '';
                $html .= '<div class="st-input-group">' . $prefix . '<input class="form-input" type="number" inputmode="decimal" step="' . ($field['step'] ?? '0.01') . '" min="' . ($field['min'] ?? '') . '" max="' . ($field['max'] ?? '') . '" value="' . sanitize($value) . '"' . $attrs . '>' . $suffix . '</div>';
                break;
            default:
                $type = $field['type'] === 'email' ? 'email' : ($field['type'] === 'url' ? 'url' : 'text');
                $input = '<input class="form-input" type="' . $type . '" value="' . sanitize($value) . '"' . $attrs . $max . '>';
                if (!empty($field['test'])) {
                    $input = '<div class="st-input-group">' . $input . '<a class="st-affix st-test" href="' . sanitize($value ?: '#') . '" target="_blank" rel="noopener" data-test-for="' . $id . '">Test <i class="fas fa-arrow-up-right-from-square"></i></a></div>';
                }
                $html .= $input;
        }
        $foot = '';
        if (!empty($field['help'])) {
            $foot .= '<p class="st-help">' . sanitize($field['help']) . '</p>';
        }
        if (isset($field['max']) && in_array($field['type'], ['text', 'textarea', 'email', 'url'], true)) {
            $foot .= '<span class="st-count" data-count-for="' . $id . '">' . mb_strlen($value) . ' / ' . (int) $field['max'] . '</span>';
        }
        if ($foot) {
            $html .= '<div class="st-foot">' . $foot . '</div>';
        }
        if (($field['preview'] ?? '') === 'hours') {
            $html .= '<div class="st-preview" id="hoursPreview" aria-live="polite"></div>';
        }
    }
    if ($error) {
        $html .= '<p class="st-error" id="' . $id . '_err"><i class="fas fa-circle-exclamation"></i> ' . sanitize($error) . '</p>';
    }
    return $html . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <link rel="stylesheet" href="../assets/css/settings.css?v=<?php echo $v('css/settings.css'); ?>">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <header class="st-top">
        <div>
          <div class="st-eyebrow">Administration</div>
          <h1>Settings</h1>
          <p>Last change <?php echo $lastSaved && $lastSaved['updated_at'] ? sanitize(date('j M Y, H:i', strtotime($lastSaved['updated_at']))) . ($lastSaved['full_name'] ? ' by ' . sanitize($lastSaved['full_name']) : '') : '—'; ?>
            <?php if (!$canEdit): ?> · <span class="st-readonly"><i class="fas fa-lock"></i> View only</span><?php endif; ?></p>
        </div>
        <div class="st-top-actions">
          <a class="btn btn-secondary" href="../" target="_blank" rel="noopener"><i class="fas fa-up-right-from-square"></i> View website</a>
        </div>
      </header>

      <?php if ($flash): ?>
        <div class="st-alert <?php echo $flash['type'] === 'success' ? 'ok' : 'info'; ?>" role="status">
          <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-circle-info'; ?>"></i>
          <span><strong><?php echo sanitize($flash['text']); ?></strong>
          <?php if (!empty($flash['keys'])): ?> <?php echo sanitize(implode(', ', array_map($labelOf, array_slice($flash['keys'], 0, 6)))) . (count($flash['keys']) > 6 ? ' and ' . (count($flash['keys']) - 6) . ' more' : ''); ?><?php endif; ?></span>
        </div>
      <?php endif; ?>
      <?php if (!empty($errors['_form'])): ?>
        <div class="st-alert bad" role="alert"><i class="fas fa-circle-exclamation"></i><span><?php echo sanitize($errors['_form']); ?></span></div>
      <?php endif; ?>

      <div class="st-layout">
        <nav class="st-nav" aria-label="Settings sections">
          <div class="st-search"><i class="fas fa-search" aria-hidden="true"></i><label class="sr-only" for="stSearch">Search settings</label><input type="search" id="stSearch" placeholder="Search settings"></div>
          <?php foreach ($sections as $key => $section): ?>
            <a href="#<?php echo $key; ?>" data-nav="<?php echo $key; ?>"><i class="fas <?php echo $section['icon']; ?>"></i> <?php echo sanitize($section['title']); ?>
              <?php $sectionErrors = count(array_intersect_key($errors, $section['fields'])); if ($sectionErrors): ?><span class="st-nav-err"><?php echo $sectionErrors; ?></span><?php endif; ?></a>
          <?php endforeach; ?>
          <a href="#system" data-nav="system"><i class="fas fa-server"></i> System &amp; integrations</a>
          <a href="#history" data-nav="history"><i class="fas fa-clock-rotate-left"></i> Change history</a>
        </nav>

        <div class="st-main">
          <form method="POST" id="settingsForm" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf); ?>">
            <input type="hidden" name="_section" id="stSection" value="">
            <?php foreach ($sections as $sectionKey => $section): ?>
              <section class="st-card" id="<?php echo $sectionKey; ?>" data-section="<?php echo $sectionKey; ?>">
                <header class="st-card-head">
                  <span class="st-card-icon"><i class="fas <?php echo $section['icon']; ?>"></i></span>
                  <div><h2><?php echo sanitize($section['title']); ?></h2><p><?php echo sanitize($section['intro']); ?></p></div>
                  <?php if ($sectionKey === 'website'): ?><a class="st-link" href="../#hero" target="_blank" rel="noopener">Preview <i class="fas fa-arrow-up-right-from-square"></i></a><?php endif; ?>
                </header>
                <div class="st-grid">
                  <?php $group = null; foreach ($section['fields'] as $key => $field):
                    if (($field['group'] ?? null) !== $group) { $group = $field['group'] ?? null; if ($group) echo '<h3 class="st-group">' . sanitize($group) . '</h3>'; }
                    echo settingsField($key, $field, (string) $values[$key], $errors[$key] ?? null, $canEdit);
                  endforeach; ?>
                </div>
                <?php if ($sectionKey === 'security'): ?>
                  <ul class="st-checks">
                    <?php foreach ($securityChecks as [$label, $ok, $good, $bad]): ?>
                      <li class="<?php echo $ok ? 'ok' : 'warn'; ?>"><i class="fas <?php echo $ok ? 'fa-circle-check' : 'fa-triangle-exclamation'; ?>"></i>
                        <div><strong><?php echo sanitize($label); ?></strong><span><?php echo sanitize($ok ? $good : $bad); ?></span></div></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </section>
            <?php endforeach; ?>

            <?php if ($canEdit): ?>
              <div class="st-savebar" id="saveBar" role="region" aria-label="Unsaved changes" hidden>
                <span><i class="fas fa-pen"></i> <strong id="dirtyCount">0</strong> unsaved change<span id="dirtyPlural">s</span></span>
                <div>
                  <button type="button" class="btn btn-secondary" id="discardBtn">Discard</button>
                  <button type="submit" class="btn btn-primary" id="saveBtn"><i class="fas fa-check"></i> Save changes</button>
                </div>
              </div>
            <?php endif; ?>
          </form>

          <section class="st-card" id="system" data-section="system">
            <header class="st-card-head">
              <span class="st-card-icon"><i class="fas fa-server"></i></span>
              <div><h2>System &amp; integrations</h2><p>Read-only. These come from the server and <code>config/.env</code>; secret keys are never shown.</p></div>
            </header>
            <div class="st-integrations">
              <?php foreach ($integrations as [$name, $icon, $ok, $detail]): ?>
                <div class="st-int <?php echo $ok ? 'ok' : 'off'; ?>"><i class="fas <?php echo $icon; ?>"></i><div><strong><?php echo sanitize($name); ?></strong><span><?php echo sanitize($detail); ?></span></div><span class="st-dot" aria-label="<?php echo $ok ? 'Working' : 'Not configured'; ?>"></span></div>
              <?php endforeach; ?>
            </div>
            <div class="st-sys">
              <dl class="st-facts">
                <?php foreach ($systemFacts as $label => $fact): ?><div><dt><?php echo sanitize($label); ?></dt><dd><?php echo sanitize($fact); ?></dd></div><?php endforeach; ?>
              </dl>
              <div>
                <div class="st-counts">
                  <div><strong><?php echo (int) $counts['staff']; ?></strong><span>Active staff</span></div>
                  <div><strong><?php echo (int) $counts['customers']; ?></strong><span>Customers</span></div>
                  <div><strong><?php echo (int) $counts['stations']; ?></strong><span>Stations</span></div>
                  <div><strong><?php echo (int) $counts['services']; ?></strong><span>Services</span></div>
                </div>
                <ul class="st-writable">
                  <?php foreach ($writable as $dir => $ok): ?><li class="<?php echo $ok ? 'ok' : 'warn'; ?>"><i class="fas <?php echo $ok ? 'fa-folder-open' : 'fa-folder-minus'; ?>"></i> <?php echo sanitize($dir); ?> <span><?php echo $ok ? 'writable' : 'not writable'; ?></span></li><?php endforeach; ?>
                </ul>
              </div>
            </div>
          </section>

          <section class="st-card" id="history" data-section="history">
            <header class="st-card-head">
              <span class="st-card-icon"><i class="fas fa-clock-rotate-left"></i></span>
              <div><h2>Change history</h2><p>The last 15 saves, newest first.</p></div>
            </header>
            <?php if (!$history): ?>
              <p class="st-empty">No changes recorded yet. Saves made from now on appear here.</p>
            <?php else: ?>
              <ol class="st-history">
                <?php foreach ($history as $entry):
                  $details = json_decode((string) $entry['details'], true) ?: [];
                  $changedKeys = $details['changed'] ?? []; ?>
                  <li>
                    <div class="st-history-meta"><strong><?php echo sanitize($entry['full_name'] ?: 'System'); ?></strong><span><?php echo sanitize(date('j M Y, H:i', strtotime($entry['created_at']))); ?></span></div>
                    <ul>
                      <?php foreach ($changedKeys as $key): ?>
                        <li><span class="k"><?php echo sanitize($labelOf($key)); ?></span>
                          <?php if (array_key_exists($key, $details['from'] ?? [])): ?><span class="from"><?php echo sanitize((string) $details['from'][$key] === '' ? '(empty)' : $details['from'][$key]); ?></span> <i class="fas fa-arrow-right"></i> <span class="to"><?php echo sanitize((string) ($details['to'][$key] ?? '') === '' ? '(empty)' : $details['to'][$key]); ?></span><?php endif; ?></li>
                      <?php endforeach; ?>
                    </ul>
                  </li>
                <?php endforeach; ?>
              </ol>
            <?php endif; ?>
          </section>
          <p class="st-noresults" id="noResults" hidden>No settings match your search.</p>
        </div>
      </div>
    </div>
  </main>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script src="../assets/js/settings.js?v=<?php echo $v('js/settings.js'); ?>"></script>
</body>
</html>
