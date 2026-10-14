<?php
/**
 * Print Jobs API
 * CRUD operations for print_jobs table
 */

require_once __DIR__ . '/common.php';

$input = $GLOBALS['request_input'] ?? ($_POST ?: ($_GET ?: []));
if (!is_array($input)) {
    $input = [];
}

function storePrintJobUpload(bool $required = true): ?array
{
    $upload = $_FILES['file'] ?? null;
    if (!is_array($upload) || !isset($upload['error']) || $upload['error'] === UPLOAD_ERR_NO_FILE) {
        if (!$required) {
            return null;
        }
        throw new InvalidArgumentException('Select a document from your device to create the print job.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        $message = $upload['error'] === UPLOAD_ERR_INI_SIZE || $upload['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'The selected file exceeds the upload size limit.'
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
    $allowedTypes = [
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'txt' => ['text/plain'],
        'rtf' => ['text/rtf', 'application/rtf', 'text/plain'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
    ];
    if (!isset($allowedTypes[$extension])) {
        throw new InvalidArgumentException('Choose a PDF, Office document, text, or image file.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($upload['tmp_name']);
    if (!in_array($mimeType, $allowedTypes[$extension], true)) {
        throw new InvalidArgumentException('The selected file type does not match its file extension.');
    }

    $uploadDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'print-jobs';
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
        throw new RuntimeException('Unable to create the print-job upload directory.');
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
    $diskPath = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;
    if (!move_uploaded_file($upload['tmp_name'], $diskPath)) {
        throw new RuntimeException('Unable to store the uploaded print file.');
    }

    return [
        'file_name' => mb_substr($originalName, 0, 255),
        'file_path' => 'uploads/print-jobs/' . $storedName,
        'disk_path' => $diskPath,
    ];
}

function ensureServiceUsageForPrint(array $input): int
{
    $userId = (int) ($input['user_id'] ?? 0);
    $stationId = (int) ($input['station_id'] ?? 0);
    $staffId = (int) ($input['staff_id'] ?? ($_SESSION['user_id'] ?? 0));
    $sessionId = !empty($input['session_id']) ? (int) $input['session_id'] : null;
    $totalPrice = isset($input['amount']) ? (float) $input['amount'] : (float) ($input['pages'] ?? 0) * 0.5;

    if ($sessionId === null && $userId > 0 && $stationId > 0) {
        $sessionId = (int) Database::fetchColumn(
            "SELECT session_id FROM sessions WHERE user_id = ? AND station_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1",
            [$userId, $stationId]
        );
    }

    if (!$sessionId && $userId > 0 && $stationId > 0) {
        $sessionId = Database::insert(
            "INSERT INTO sessions (user_id, station_id, staff_id, status, notes, started_at, created_at) VALUES (?, ?, ?, 'active', ?, NOW(), NOW())",
            [$userId, $stationId, $staffId ?: null, 'Print queue session']
        );
    }

    if (!$sessionId || $sessionId <= 0) {
        throw new RuntimeException('No active session is available for this user and station. Start a session before queuing a print job.');
    }

    $serviceId = !empty($input['service_id']) ? (int) $input['service_id'] : null;
    if (!$serviceId) {
        $serviceId = (int) Database::fetchColumn(
            "SELECT service_id FROM services WHERE LOWER(category) = 'printing' AND is_active = 1 ORDER BY service_id LIMIT 1"
        );
    }
    if (!$serviceId) {
        $serviceId = (int) Database::fetchColumn("SELECT service_id FROM services ORDER BY service_id LIMIT 1");
    }
    if (!$serviceId) {
        throw new RuntimeException('No printing service is configured. Add a printing service from the Services page first.');
    }

    $existingUsageId = (int) Database::fetchColumn(
        "SELECT usage_id FROM service_usage WHERE user_id = ? AND station_id = ? AND status IN ('pending', 'in_progress') ORDER BY created_at DESC LIMIT 1",
        [$userId, $stationId]
    );

    if ($existingUsageId > 0) {
        return $existingUsageId;
    }

    $metadata = json_encode([
        'file_name' => $input['file_name'] ?? 'print-job',
        'pages' => (int) ($input['pages'] ?? 1),
        'copies' => (int) ($input['copies'] ?? 1),
        'color_mode' => $input['color_mode'] ?? 'bw',
    ]);

    $usageId = Database::insert(
        "INSERT INTO service_usage (session_id, service_id, user_id, station_id, staff_id, quantity, unit_price, total_price, status, metadata, started_at, created_at)
         VALUES (?, ?, ?, ?, ?, 1, ?, ?, 'in_progress', ?, NOW(), NOW())",
        [$sessionId, $serviceId, $userId, $stationId, $staffId ?: null, $totalPrice, $totalPrice, $metadata]
    );

    return (int) $usageId;
}

function advanceQueuedPrintJobs(?int $peripheralId = null): ?array
{
    $sql = "SELECT * FROM print_jobs WHERE status = 'queued'";
    $params = [];
    if ($peripheralId) {
        $sql .= " AND peripheral_id = ?";
        $params[] = $peripheralId;
    }
    $sql .= " ORDER BY created_at ASC LIMIT 1";

    $nextJob = Database::fetchOne($sql, $params);
    if (!$nextJob) {
        return null;
    }

    Database::execute(
        "UPDATE print_jobs SET status = 'printing', started_at = COALESCE(started_at, NOW()), updated_at = NOW() WHERE print_job_id = ?",
        [$nextJob['print_job_id']]
    );

    return $nextJob;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST' && ($input['_method'] ?? '') === 'PUT') {
    $method = 'PUT';
}
$uploadedDiskPath = null;

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['list'])) {
                $where = '';
                $params = [];
                if (isset($_GET['type'])) {
                    $where = "WHERE p.peripheral_type = ?";
                    $params[] = $_GET['type'];
                }
                $peripherals = Database::fetchAll(
                    "SELECT peripheral_id as id, name as label, model FROM peripherals $where ORDER BY name",
                    $params
                );
                successResponse($peripherals);
            } elseif (isset($_GET['print_job_id'])) {
                $job = Database::fetchOne(
                    "SELECT pj.*, u.full_name as user_name, st.station_code, pr.name as printer_name
                     FROM print_jobs pj
                     JOIN users u ON pj.user_id = u.user_id
                     JOIN stations st ON pj.station_id = st.station_id
                     JOIN peripherals pr ON pj.peripheral_id = pr.peripheral_id
                     WHERE pj.print_job_id = ?",
                    [$_GET['print_job_id']]
                );
                if (!$job) errorResponse('Print job not found', 404);
                successResponse($job);
            } elseif (isset($_GET['queue'])) {
                $jobs = Database::fetchAll(
                    "SELECT pj.print_job_id, pj.file_name, pj.pages, pj.copies, pj.color_mode, pj.priority, pj.status,
                            pj.started_at, pj.created_at,
                            u.full_name as user_name, st.station_code, pr.name as printer_name,
                            CASE
                                WHEN pj.status = 'printing' THEN 0
                                WHEN pj.status = 'queued' THEN (
                                    SELECT COUNT(*) FROM print_jobs pj2
                                    WHERE pj2.status = 'queued'
                                    AND pj2.created_at <= pj.created_at
                                )
                                ELSE NULL
                            END AS queue_position
                     FROM print_jobs pj
                     JOIN users u ON pj.user_id = u.user_id
                     JOIN stations st ON st.station_id = pj.station_id
                     JOIN peripherals pr ON pr.peripheral_id = pj.peripheral_id
                     WHERE pj.status IN ('queued', 'printing')
                     ORDER BY CASE pj.status WHEN 'printing' THEN 0 WHEN 'queued' THEN 1 ELSE 2 END, pj.created_at ASC
                     LIMIT 20"
                );
                successResponse($jobs);
            } else {
                $jobs = Database::fetchAll(
                    "SELECT pj.*, u.full_name as user_name, st.station_code, pr.name as printer_name
                     FROM print_jobs pj
                     JOIN users u ON pj.user_id = u.user_id
                     JOIN stations st ON st.station_id = pj.station_id
                     JOIN peripherals pr ON pr.peripheral_id = pj.peripheral_id
                     ORDER BY pj.created_at DESC"
                );
                successResponse($jobs);
            }
            break;

        case 'POST':
            validateRequired($input, ['user_id', 'station_id', 'peripheral_id', 'pages', 'copies', 'color_mode']);
            if (isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $uploadedFile = storePrintJobUpload();
                $uploadedDiskPath = $uploadedFile['disk_path'];
            } elseif (!empty($input['file_name']) && !empty($input['file_path'])) {
                $uploadedFile = [
                    'file_name' => $input['file_name'],
                    'file_path' => $input['file_path'],
                ];
            } else {
                storePrintJobUpload();
            }

            $usageId = ensureServiceUsageForPrint($input);
            $jobId = Database::insert(
                "INSERT INTO print_jobs (usage_id, user_id, station_id, peripheral_id, file_name, file_path, pages, copies, color_mode, duplex, priority, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued')",
                [
                    $usageId,
                    $input['user_id'],
                    $input['station_id'],
                    $input['peripheral_id'],
                    $uploadedFile['file_name'],
                    $uploadedFile['file_path'],
                    (int) $input['pages'],
                    (int) $input['copies'],
                    $input['color_mode'],
                    !empty($input['duplex']) ? 1 : 0,
                    (int) ($input['priority'] ?? 0),
                ]
            );
            $uploadedDiskPath = null;

            $activePrinting = Database::fetchOne("SELECT print_job_id FROM print_jobs WHERE status = 'printing' LIMIT 1");
            if (!$activePrinting) {
                Database::execute(
                    "UPDATE print_jobs SET status = 'printing', started_at = COALESCE(started_at, NOW()), updated_at = NOW() WHERE print_job_id = ?",
                    [$jobId]
                );
            }

            $job = Database::fetchOne(
                "SELECT pj.*, u.full_name as user_name, st.station_code, pr.name as printer_name
                 FROM print_jobs pj
                 JOIN users u ON pj.user_id = u.user_id
                 JOIN stations st ON pj.station_id = st.station_id
                 JOIN peripherals pr ON pj.peripheral_id = pr.peripheral_id
                 WHERE pj.print_job_id = ?",
                [$jobId]
            );
            successResponse($job, 'Print job queued successfully');
            break;

        case 'PUT':
            validateRequired($input, ['print_job_id']);

            $job = Database::fetchOne("SELECT * FROM print_jobs WHERE print_job_id = ?", [$input['print_job_id']]);
            if (!$job) {
                errorResponse('Print job not found', 404);
            }

            $uploadedFile = storePrintJobUpload(false);
            if ($uploadedFile !== null) {
                $uploadedDiskPath = $uploadedFile['disk_path'];
            }
            
            $updates = [];
            $params = [];
            
            $allowedFields = ['status', 'priority', 'error_message'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            if ($uploadedFile !== null) {
                $updates[] = 'file_name = ?';
                $params[] = $uploadedFile['file_name'];
                $updates[] = 'file_path = ?';
                $params[] = $uploadedFile['file_path'];
            }

            if (isset($input['status']) && in_array($input['status'], ['completed', 'failed', 'cancelled'], true)) {
                $updates[] = 'completed_at = NOW()';
            }
            
            if (empty($updates)) errorResponse('No fields to update');
            
            $params[] = $input['print_job_id'];
            Database::execute("UPDATE print_jobs SET " . implode(', ', $updates) . " WHERE print_job_id = ?", $params);
            $uploadedDiskPath = null;

            if (isset($input['status']) && in_array($input['status'], ['completed', 'failed', 'cancelled'], true)) {
                advanceQueuedPrintJobs((int) $job['peripheral_id']);
            }
            
            $job = Database::fetchOne(
                "SELECT pj.*, u.full_name as user_name, st.station_code, pr.name as printer_name
                 FROM print_jobs pj
                 JOIN users u ON pj.user_id = u.user_id
                 JOIN stations st ON pj.station_id = st.station_id
                 JOIN peripherals pr ON pj.peripheral_id = pr.peripheral_id
                 WHERE pj.print_job_id = ?",
                [$input['print_job_id']]
            );
            successResponse($job, 'Print job updated successfully');
            break;

        case 'DELETE':
            validateRequired($input, ['print_job_id']);
            Database::execute("DELETE FROM print_jobs WHERE print_job_id = ?", [$input['print_job_id']]);
            successResponse(null, 'Print job deleted successfully');
            break;

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