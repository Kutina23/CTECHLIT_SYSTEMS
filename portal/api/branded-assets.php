<?php
/**
 * Brand Studio API — shared design library and brand kit.
 *
 * GET  ?kit=1                         brand kit + business details
 * GET  ?list=1[&type=&search=&mine=1] design library (with thumbnails)
 * GET  ?asset_id=N                    one design with its full config
 * POST action=save      {asset_id?, asset_type, name, config, thumbnail?}
 * POST action=duplicate {asset_id}
 * POST action=rename    {asset_id, name}
 * POST action=kit       {primary, secondary, dark, website?}
 * POST action=logo      multipart file "logo" (PNG/JPG/WebP, ≤ 2 MB)   |  action=remove_logo
 * DELETE {asset_id}
 */

require_once __DIR__ . '/common.php';

requirePermission('branded_assets.view');

const BRAND_TYPES = ['flyer', 'powerpoint', 'letterhead', 'business_card'];

if (!Database::tableExists('branded_assets')) {
    errorResponse('Run database/migrations/014_create_branded_assets_table.php and 020_brand_studio.php first.', 500);
}

$method = $_SERVER['REQUEST_METHOD'];
$staffId = (int) ($_SESSION['user_id'] ?? 0);
$isAdmin = hasPermission('staff.assign_permissions');

function brandKit(): array
{
    $raw = Database::fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'brand_kit'");
    $kit = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
    $kit += ['primary' => '#7C3AED', 'secondary' => '#06B6D4', 'dark' => '#0F172A', 'logo' => null, 'website' => ''];
    $logoFile = $kit['logo'] ? __DIR__ . '/../../uploads/branding/' . basename((string) $kit['logo']) : null;
    $kit['logo_url'] = $logoFile && is_file($logoFile)
        ? base_path('uploads/branding/' . basename((string) $kit['logo']), false) . '?v=' . filemtime($logoFile)
        : null;
    return $kit;
}

function saveBrandKit(array $kit): void
{
    unset($kit['logo_url']);
    Database::execute(
        "INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public, updated_by)
         VALUES ('brand_kit', ?, 'json', 'Brand Studio colours and logo shared by all staff', 0, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)",
        [json_encode($kit), (int) ($_SESSION['user_id'] ?? 0) ?: null]
    );
}

function validHex(mixed $value): ?string
{
    $value = trim((string) $value);
    return preg_match('/\A#[0-9A-Fa-f]{6}\z/', $value) ? strtoupper($value) : null;
}

function brandAsset(int $assetId): array
{
    $asset = Database::fetchOne(
        'SELECT a.asset_id, a.asset_type, a.name, a.config, a.preview_data AS thumbnail, a.created_by, a.created_at, a.updated_at,
                s.full_name AS created_by_name
         FROM branded_assets a LEFT JOIN staff s ON s.staff_id = a.created_by WHERE a.asset_id = ?',
        [$assetId]
    );
    if (!$asset) {
        throw new InvalidArgumentException('Design not found.');
    }
    $asset['config'] = json_decode((string) $asset['config'], true) ?: [];
    return $asset;
}

function cleanThumbnail(mixed $value): ?string
{
    $value = is_string($value) ? $value : '';
    return preg_match('#\Adata:image/(jpeg|png|webp);base64,[A-Za-z0-9+/=]+\z#', $value) && strlen($value) <= 400000 ? $value : null;
}

function canChange(array $asset, string $permission): bool
{
    global $staffId, $isAdmin;
    return hasPermission($permission) && ((int) $asset['created_by'] === $staffId || $isAdmin);
}

try {
    if ($method === 'GET') {
        if (isset($_GET['kit'])) {
            successResponse([
                'kit' => brandKit(),
                'business' => [
                    'name' => siteSetting('business_name', 'CTECHLIT & SYSTEMS'),
                    'tagline' => siteSetting('business_tagline', ''),
                    'phone' => siteSetting('contact_phone', ''),
                    'email' => siteSetting('contact_email', ''),
                    'address' => siteSetting('contact_address', ''),
                ],
                'can' => [
                    'create' => hasPermission('branded_assets.create'),
                    'edit' => hasPermission('branded_assets.edit'),
                    'delete' => hasPermission('branded_assets.delete'),
                    'admin' => $isAdmin,
                ],
                'me' => $staffId,
            ]);
        }
        if (isset($_GET['asset_id'])) {
            successResponse(brandAsset((int) $_GET['asset_id']));
        }
        $where = [];
        $params = [];
        if (in_array($_GET['type'] ?? '', BRAND_TYPES, true)) {
            $where[] = 'a.asset_type = ?';
            $params[] = $_GET['type'];
        }
        if (trim((string) ($_GET['search'] ?? '')) !== '') {
            $where[] = 'a.name LIKE ?';
            $params[] = '%' . addcslashes(trim((string) $_GET['search']), '%_\\') . '%';
        }
        if (!empty($_GET['mine'])) {
            $where[] = 'a.created_by = ?';
            $params[] = $staffId;
        }
        successResponse(Database::fetchAll(
            'SELECT a.asset_id, a.asset_type, a.name, a.preview_data AS thumbnail, a.created_by, a.updated_at, s.full_name AS created_by_name
             FROM branded_assets a LEFT JOIN staff s ON s.staff_id = a.created_by'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY a.updated_at DESC LIMIT 200',
            $params
        ));
    }

    if ($method === 'DELETE') {
        $asset = brandAsset((int) ($input['asset_id'] ?? 0));
        if (!canChange($asset, 'branded_assets.delete')) {
            errorResponse('You can only delete your own designs (or need administrator rights).', 403);
        }
        Database::execute('DELETE FROM branded_assets WHERE asset_id = ?', [$asset['asset_id']]);
        logAuditTrail('branded_asset_deleted', 'branded_asset', (int) $asset['asset_id'], ['name' => $asset['name']]);
        successResponse(null, 'Design deleted');
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $action = (string) ($input['action'] ?? $_POST['action'] ?? 'save');

    switch ($action) {
        case 'save':
            $assetId = (int) ($input['asset_id'] ?? 0);
            $type = (string) ($input['asset_type'] ?? '');
            $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 150);
            $config = $input['config'] ?? null;
            if (!in_array($type, BRAND_TYPES, true)) {
                throw new InvalidArgumentException('Unknown design type.');
            }
            if ($name === '') {
                throw new InvalidArgumentException('Give the design a name.');
            }
            if (!is_array($config)) {
                throw new InvalidArgumentException('The design data is missing.');
            }
            $configJson = json_encode($config, JSON_UNESCAPED_UNICODE);
            if (strlen((string) $configJson) > 6 * 1024 * 1024) {
                throw new InvalidArgumentException('This design is too large to save — use a smaller photo.');
            }
            $thumbnail = cleanThumbnail($input['thumbnail'] ?? null);
            if ($assetId > 0) {
                $asset = brandAsset($assetId);
                if (!canChange($asset, 'branded_assets.edit')) {
                    errorResponse('You can only edit your own designs. Use “Duplicate” to make your own copy.', 403);
                }
                Database::execute(
                    'UPDATE branded_assets SET asset_type = ?, name = ?, config = ?, preview_data = COALESCE(?, preview_data) WHERE asset_id = ?',
                    [$type, $name, $configJson, $thumbnail, $assetId]
                );
            } else {
                requirePermission('branded_assets.create');
                $assetId = Database::insert(
                    'INSERT INTO branded_assets (asset_type, name, config, preview_data, created_by) VALUES (?, ?, ?, ?, ?)',
                    [$type, $name, $configJson, $thumbnail, $staffId]
                );
                logAuditTrail('branded_asset_created', 'branded_asset', $assetId, ['type' => $type, 'name' => $name]);
            }
            $saved = brandAsset($assetId);
            unset($saved['config']);
            successResponse($saved, 'Design saved');

        case 'duplicate':
            requirePermission('branded_assets.create');
            $asset = brandAsset((int) ($input['asset_id'] ?? 0));
            $copyId = Database::insert(
                'INSERT INTO branded_assets (asset_type, name, config, preview_data, created_by) VALUES (?, ?, ?, ?, ?)',
                [$asset['asset_type'], mb_substr($asset['name'] . ' (copy)', 0, 150), json_encode($asset['config'], JSON_UNESCAPED_UNICODE), $asset['thumbnail'], $staffId]
            );
            $copy = brandAsset($copyId);
            unset($copy['config']);
            successResponse($copy, 'Copy created');

        case 'rename':
            $asset = brandAsset((int) ($input['asset_id'] ?? 0));
            if (!canChange($asset, 'branded_assets.edit')) {
                errorResponse('You can only rename your own designs.', 403);
            }
            $name = mb_substr(trim((string) ($input['name'] ?? '')), 0, 150);
            if ($name === '') {
                throw new InvalidArgumentException('Give the design a name.');
            }
            Database::execute('UPDATE branded_assets SET name = ? WHERE asset_id = ?', [$name, $asset['asset_id']]);
            successResponse(['asset_id' => $asset['asset_id'], 'name' => $name], 'Renamed');

        case 'kit':
            requirePermission('branded_assets.edit');
            $kit = brandKit();
            foreach (['primary', 'secondary', 'dark'] as $key) {
                if (array_key_exists($key, $input)) {
                    $color = validHex($input[$key]);
                    if ($color === null) {
                        throw new InvalidArgumentException("Choose a valid $key colour.");
                    }
                    $kit[$key] = $color;
                }
            }
            if (array_key_exists('website', $input)) {
                $kit['website'] = mb_substr(trim((string) $input['website']), 0, 120);
            }
            saveBrandKit($kit);
            logAuditTrail('brand_kit_updated', 'brand_kit', null, array_intersect_key($kit, array_flip(['primary', 'secondary', 'dark', 'website'])));
            successResponse(brandKit(), 'Brand kit updated');

        case 'logo':
            requirePermission('branded_assets.edit');
            $file = $_FILES['logo'] ?? null;
            if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Choose a logo image to upload.');
            }
            if ($file['size'] > 2 * 1024 * 1024) {
                throw new InvalidArgumentException('The logo must be 2 MB or smaller.');
            }
            $info = @getimagesize($file['tmp_name']);
            $extensions = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
            if (!$info || !isset($extensions[$info[2]])) {
                throw new InvalidArgumentException('Upload a PNG, JPG or WebP image (PNG with a transparent background works best).');
            }
            $dir = __DIR__ . '/../../uploads/branding';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fileName = 'logo-' . bin2hex(random_bytes(6)) . '.' . $extensions[$info[2]];
            if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fileName)) {
                throw new RuntimeException('The logo could not be stored.');
            }
            // Previous logo files are kept (not deleted) so a replaced logo can always be restored.
            $kit = brandKit();
            $kit['logo'] = $fileName;
            saveBrandKit($kit);
            logAuditTrail('brand_logo_uploaded', 'brand_kit', null, ['file' => $fileName, 'width' => $info[0], 'height' => $info[1]]);
            successResponse(brandKit(), 'Logo uploaded');

        case 'remove_logo':
            requirePermission('branded_assets.edit');
            $kit = brandKit();
            $kit['logo'] = null; // the file stays in uploads/branding so it can be restored
            logAuditTrail('brand_logo_removed', 'brand_kit', null, []);
            saveBrandKit($kit);
            successResponse(brandKit(), 'Logo removed');

        default:
            errorResponse('Unknown action', 400);
    }
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Brand Studio API error: ' . $e->getMessage());
    errorResponse('The request could not be completed.', 500);
}
