<?php
/**
 * Help center resource CRUD API.
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'];
$resourceTypes = ['documentation', 'tutorial', 'faq'];
$uploadedDiskPath = null;

if ($method === 'POST' && isset($input['_method'])) {
    $overrideMethod = strtoupper((string) $input['_method']);
    if (in_array($overrideMethod, ['PUT', 'DELETE'], true)) {
        $method = $overrideMethod;
    }
}

function storeHelpResourceUpload(string $type): ?array
{
    $upload = $_FILES['file'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($upload['error'] ?? null) !== UPLOAD_ERR_OK) {
        $message = in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The selected file exceeds the server upload limit.'
            : 'The selected file could not be uploaded.';
        throw new InvalidArgumentException($message);
    }
    if (!isset($upload['tmp_name'], $upload['name'], $upload['size']) || !is_uploaded_file($upload['tmp_name'])) {
        throw new InvalidArgumentException('The selected file upload is invalid.');
    }
    if ((int) $upload['size'] < 1 || (int) $upload['size'] > 25 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose a file smaller than 25 MB.');
    }

    $originalName = basename(str_replace('\\', '/', (string) $upload['name']));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $allowedExtensions = [
        'documentation' => ['pdf', 'docx', 'txt'],
        'tutorial' => ['mp4', 'webm'],
    ];
    if (!in_array($extension, $allowedExtensions[$type] ?? [], true)) {
        throw new InvalidArgumentException(
            $type === 'tutorial'
                ? 'Tutorial uploads must be MP4 or WEBM videos.'
                : 'Documentation uploads must be PDF, DOCX, or TXT files.'
        );
    }

    $allowedMimes = [
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'txt' => ['text/plain'],
        'mp4' => ['video/mp4'],
        'webm' => ['video/webm'],
    ];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!in_array($mimeType, $allowedMimes[$extension], true)) {
        throw new InvalidArgumentException('The selected file type does not match its file extension.');
    }

    $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'help-center';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the help content upload directory.');
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $diskPath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($upload['tmp_name'], $diskPath)) {
        throw new RuntimeException('Unable to store the uploaded help content file.');
    }

    return [
        'url' => 'uploads/help-center/' . $storedName,
        'disk_path' => $diskPath,
    ];
}

function removeHelpResourceUpload(?string $url): void
{
    if (!is_string($url) || !preg_match('#^uploads/help-center/[a-f0-9]{32}\.(pdf|docx|txt|mp4|webm)$#', $url)) {
        return;
    }

    $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $url);
    if (is_file($path) && !unlink($path)) {
        throw new RuntimeException('Help content was saved, but its previous uploaded file could not be removed.');
    }
}

function extractHelpDocxText(string $filePath): string
{
    if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
        throw new RuntimeException('DOCX preview is unavailable because the server is missing ZIP or XML support.');
    }

    $archive = new ZipArchive();
    if ($archive->open($filePath) !== true) {
        throw new RuntimeException('The uploaded DOCX document could not be opened.');
    }

    try {
        $documentXml = $archive->getFromName('word/document.xml');
    } finally {
        $archive->close();
    }
    if (!is_string($documentXml)) {
        throw new RuntimeException('The uploaded DOCX document has no readable document content.');
    }

    $document = new DOMDocument();
    $previousErrorMode = libxml_use_internal_errors(true);
    try {
        $loaded = $document->loadXML($documentXml, LIBXML_NONET | LIBXML_NOBLANKS);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorMode);
    }
    if (!$loaded) {
        throw new RuntimeException('The uploaded DOCX document contains invalid XML.');
    }

    $xpath = new DOMXPath($document);
    $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $paragraphs = $xpath->query('//w:body//w:p');
    if ($paragraphs === false) {
        throw new RuntimeException('The uploaded DOCX document text could not be read.');
    }

    $lines = [];
    foreach ($paragraphs as $paragraph) {
        $parts = $xpath->query('.//w:t | .//w:tab | .//w:br | .//w:cr', $paragraph);
        $line = '';
        if ($parts !== false) {
            foreach ($parts as $part) {
                if ($part->localName === 'tab') {
                    $line .= "\t";
                } elseif ($part->localName === 'br' || $part->localName === 'cr') {
                    $line .= "\n";
                } else {
                    $line .= $part->textContent;
                }
            }
        }
        $lines[] = $line;
    }

    return trim(implode("\n", $lines));
}

function validateHelpResource(array $data, bool $hasUpload = false): array
{
    global $resourceTypes;

    $type = trim((string) ($data['resource_type'] ?? ''));
    $title = trim((string) ($data['title'] ?? ''));
    $content = trim((string) ($data['content'] ?? ''));
    $url = trim((string) ($data['url'] ?? ''));
    $sortOrder = filter_var($data['sort_order'] ?? 0, FILTER_VALIDATE_INT);
    $published = filter_var($data['is_published'] ?? 0, FILTER_VALIDATE_INT);

    if (!in_array($type, $resourceTypes, true)) {
        errorResponse('Select a valid help content type');
    }
    if ($title === '' || strlen($title) > 255) {
        errorResponse('Title is required and must be 255 characters or fewer');
    }
    if ($sortOrder === false) {
        errorResponse('Display order must be a whole number');
    }
    if ($published === false || !in_array($published, [0, 1], true)) {
        errorResponse('Choose whether the content is published');
    }
    if ($url !== '') {
        $isHelpUpload = $type === 'documentation'
            ? (bool) preg_match('#^uploads/help-center/[a-f0-9]{32}\.(pdf|docx|txt)$#', $url)
            : ($type === 'tutorial' && (bool) preg_match('#^uploads/help-center/[a-f0-9]{32}\.(mp4|webm)$#', $url));
        $parts = parse_url($url);
        if (!$isHelpUpload && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true))) {
            errorResponse('Links must use a valid http or https URL');
        }
    }
    if ($type === 'faq' && $content === '') {
        errorResponse('FAQ content is required');
    }
    if ($content === '' && $url === null && !$hasUpload) {
        errorResponse('Add content, an external link, or upload a file');
    }

    return [$type, $title, $content, $url !== '' ? $url : null, $sortOrder, $published];
}

try {
    switch ($method) {
        case 'GET':
            requirePermission('dashboard.view');
            if (isset($_GET['preview'])) {
                requirePermission('settings.edit');
                $resourceId = filter_var($_GET['preview'], FILTER_VALIDATE_INT);
                if ($resourceId === false || $resourceId <= 0) {
                    errorResponse('A valid help resource ID is required');
                }
                $resource = Database::fetchOne(
                    "SELECT resource_id, title, resource_type, url FROM help_resources WHERE resource_id = ?",
                    [$resourceId]
                );
                if (!$resource || $resource['resource_type'] !== 'documentation' || !preg_match('#^uploads/help-center/[a-f0-9]{32}\.docx$#', (string) $resource['url'])) {
                    errorResponse('DOCX preview is unavailable for this help resource', 404);
                }
                $filePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $resource['url']);
                if (!is_file($filePath)) {
                    errorResponse('The uploaded document could not be found', 404);
                }
                successResponse([
                    'resource_id' => (int) $resource['resource_id'],
                    'title' => $resource['title'],
                    'content' => extractHelpDocxText($filePath),
                ], 'Document preview loaded');
            }
            $resources = Database::fetchAll(
                "SELECT resource_id, resource_type, title, content, url, sort_order, is_published, updated_at
                 FROM help_resources
                 ORDER BY resource_type, sort_order, title"
            );
            successResponse($resources);

        case 'POST':
            requirePermission('settings.edit');
            $hasUpload = isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $validationInput = $input;
            if ($hasUpload) {
                $validationInput['url'] = '';
            }
            [$type, $title, $content, $url, $sortOrder, $published] = validateHelpResource($validationInput, $hasUpload);
            $uploadedFile = storeHelpResourceUpload($type);
            $uploadedDiskPath = $uploadedFile['disk_path'] ?? null;
            if ($uploadedFile !== null) {
                $url = $uploadedFile['url'];
            }
            $staffId = (int) ($_SESSION['user_id'] ?? 0);
            $resourceId = Database::insert(
                "INSERT INTO help_resources (resource_type, title, content, url, sort_order, is_published, created_by, updated_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$type, $title, $content, $url, $sortOrder, $published, $staffId ?: null, $staffId ?: null]
            );
            $uploadedDiskPath = null;
            logAuditTrail('help_resource_created', 'help_resource', $resourceId, ['type' => $type, 'title' => $title], $staffId ?: null, 'staff');
            successResponse(['resource_id' => $resourceId], 'Help content created');

        case 'PUT':
            requirePermission('settings.edit');
            $resourceId = filter_var($input['resource_id'] ?? null, FILTER_VALIDATE_INT);
            if ($resourceId === false || $resourceId <= 0) {
                errorResponse('A valid resource_id is required');
            }
            $existingResource = Database::fetchOne("SELECT resource_id, url FROM help_resources WHERE resource_id = ?", [$resourceId]);
            if (!$existingResource) {
                errorResponse('Help content not found', 404);
            }
            $hasUpload = isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $validationInput = $input;
            if ($hasUpload) {
                $validationInput['url'] = '';
            }
            [$type, $title, $content, $url, $sortOrder, $published] = validateHelpResource($validationInput, $hasUpload);
            $uploadedFile = storeHelpResourceUpload($type);
            $uploadedDiskPath = $uploadedFile['disk_path'] ?? null;
            if ($uploadedFile !== null) {
                $url = $uploadedFile['url'];
            }
            $staffId = (int) ($_SESSION['user_id'] ?? 0);
            Database::execute(
                "UPDATE help_resources
                 SET resource_type = ?, title = ?, content = ?, url = ?, sort_order = ?, is_published = ?, updated_by = ?
                 WHERE resource_id = ?",
                [$type, $title, $content, $url, $sortOrder, $published, $staffId ?: null, $resourceId]
            );
            $uploadedDiskPath = null;
            if (($existingResource['url'] ?? null) !== $url) {
                removeHelpResourceUpload($existingResource['url'] ?? null);
            }
            logAuditTrail('help_resource_updated', 'help_resource', $resourceId, ['type' => $type, 'title' => $title], $staffId ?: null, 'staff');
            successResponse(['resource_id' => $resourceId], 'Help content updated');

        case 'DELETE':
            requirePermission('settings.edit');
            $resourceId = filter_var($input['resource_id'] ?? null, FILTER_VALIDATE_INT);
            if ($resourceId === false || $resourceId <= 0) {
                errorResponse('A valid resource_id is required');
            }
            $existingResource = Database::fetchOne("SELECT url FROM help_resources WHERE resource_id = ?", [$resourceId]);
            if (!$existingResource || Database::execute("DELETE FROM help_resources WHERE resource_id = ?", [$resourceId]) < 1) {
                errorResponse('Help content not found', 404);
            }
            removeHelpResourceUpload($existingResource['url'] ?? null);
            logAuditTrail('help_resource_deleted', 'help_resource', $resourceId, [], (int) ($_SESSION['user_id'] ?? 0), 'staff');
            successResponse(null, 'Help content deleted');

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    if ($uploadedDiskPath !== null && is_file($uploadedDiskPath)) {
        unlink($uploadedDiskPath);
    }
    errorResponse($e->getMessage(), 400);
} catch (Throwable $e) {
    if ($uploadedDiskPath !== null && is_file($uploadedDiskPath)) {
        unlink($uploadedDiskPath);
    }
    errorResponse($e->getMessage(), 500);
}
