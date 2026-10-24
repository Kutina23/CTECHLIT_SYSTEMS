<?php
/**
 * Print Jobs API
 *
 * GET  ?board=1                   printers with their current job, queue length and today's stats
 * GET  ?queue=1                   active jobs (used by the POS print desk)
 * GET  ?jobs=1[&status=&printer=&search=&from=&to=&page=]   job history
 * GET  ?print_job_id=N            one job
 * GET  ?options=1                 prices, customers, printers and stations for the new-job form
 * GET  ?download=N                the uploaded document (staff only)
 * GET  ?printer_info=N            live IPP status of a printer (state, supplies)
 * POST multipart file + {peripheral_id, user_id|customer_name, pages?, copies, color_mode, duplex, priority, payment_method, reference?, station_id?, notes?}
 * POST {action: complete|fail|cancel|retry|resend|collect|priority|move|reprint, print_job_id, ...}
 * DELETE {print_job_id}           remove a finished job and its file
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/print-queue.php';

$input = $GLOBALS['request_input'] ?? [];
if (!is_array($input)) {
    $input = [];
}

const PQ_ALLOWED_TYPES = [
    'pdf' => ['application/pdf'],
    'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
    'txt' => ['text/plain'],
    'rtf' => ['text/rtf', 'application/rtf', 'text/plain'],
    'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'],
];

function storePrintUpload(): array
{
    $upload = $_FILES['file'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Choose the document to print.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException(in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'The file is larger than the server allows (' . ini_get('upload_max_filesize') . ').' : 'The file could not be uploaded.');
    }
    if (!is_uploaded_file($upload['tmp_name']) || $upload['size'] < 1 || $upload['size'] > 25 * 1024 * 1024) {
        throw new InvalidArgumentException('Choose a file up to 25 MB.');
    }
    $name = basename(str_replace('\\', '/', (string) $upload['name']));
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!isset(PQ_ALLOWED_TYPES[$ext])) {
        throw new InvalidArgumentException('Choose a PDF, Word, Excel, PowerPoint, text or image file.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!in_array($mime, PQ_ALLOWED_TYPES[$ext], true)) {
        throw new InvalidArgumentException('The file contents do not match its .' . $ext . ' extension.');
    }
    $dir = dirname(__DIR__, 2) . '/uploads/print-jobs';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('The print upload folder could not be created.');
    }
    if (!is_file($dir . '/.htaccess')) {
        file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($upload['tmp_name'], $dir . '/' . $stored)) {
        throw new RuntimeException('The file could not be stored.');
    }
    // Office "zip" detections are reported with their real Office type for clarity.
    $officeTypes = ['docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    return [
        'file_name' => mb_substr($name, 0, 255),
        'file_path' => 'uploads/print-jobs/' . $stored,
        'disk_path' => $dir . '/' . $stored,
        'file_size' => (int) $upload['size'],
        'mime_type' => $mime === 'application/zip' && isset($officeTypes[$ext]) ? $officeTypes[$ext] : $mime,
    ];
}

const PQ_LIST_SELECT = "SELECT pj.print_job_id, pj.file_name, pj.mime_type, pj.file_size, pj.pages, pj.copies, pj.color_mode, pj.duplex, pj.priority,
        pj.status, pj.dispatch, pj.amount, pj.unit_price, pj.payment_status, pj.error_message, pj.notes, pj.peripheral_id, pj.user_id,
        pj.created_at, pj.started_at, pj.completed_at, pj.printer_job_id,
        u.full_name AS customer_name, pr.name AS printer_name, st.station_code, sf.full_name AS staff_name,
        TIMESTAMPDIFF(MINUTE, pj.created_at, COALESCE(pj.started_at, NOW())) AS wait_minutes
    FROM print_jobs pj
    LEFT JOIN users u ON u.user_id = pj.user_id
    LEFT JOIN peripherals pr ON pr.peripheral_id = pj.peripheral_id
    LEFT JOIN stations st ON st.station_id = pj.station_id
    LEFT JOIN staff sf ON sf.staff_id = pj.staff_id";

$method = $_SERVER['REQUEST_METHOD'];
$storedPath = null;

try {
    if ($method === 'GET') {
        requirePermission('print_jobs.view');

        if (isset($_GET['download'])) {
            $job = pqJob((int) $_GET['download']);
            $disk = dirname(__DIR__, 2) . '/' . ltrim((string) $job['file_path'], '/');
            if (!is_file($disk)) {
                errorResponse('The file is no longer on the server.', 404);
            }
            logAuditTrail('print_job_file_opened', 'print_job', (int) $job['print_job_id'], []);
            $inline = in_array($job['mime_type'], ['application/pdf', 'image/jpeg', 'image/png', 'text/plain'], true);
            header('Content-Type: ' . ($job['mime_type'] ?: 'application/octet-stream'));
            header('Content-Length: ' . filesize($disk));
            header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace(['"', "\r", "\n"], '', $job['file_name']) . '"');
            header('X-Content-Type-Options: nosniff');
            header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; plugin-types application/pdf");
            header('Cache-Control: private, no-store');
            readfile($disk);
            exit;
        }

        if (isset($_GET['printer_info'])) {
            $printer = pqPrinter((int) $_GET['printer_info']);
            if (deviceControlMode() !== 'lan') {
                successResponse(['available' => false], 'Live printer status is only available when the server is on the café network.');
            }
            try {
                $info = ippPrinterInfo($printer['cfg']);
                Database::execute("UPDATE peripherals SET status = 'online' WHERE peripheral_id = ? AND status <> 'maintenance'", [$printer['peripheral_id']]);
                successResponse(['available' => true] + $info);
            } catch (Throwable $error) {
                Database::execute("UPDATE peripherals SET status = 'offline' WHERE peripheral_id = ? AND status <> 'maintenance'", [$printer['peripheral_id']]);
                successResponse(['available' => false, 'state' => 'offline'], 'Printer not reachable: ' . $error->getMessage());
            }
        }

        if (isset($_GET['print_job_id'])) {
            successResponse(pqJob((int) $_GET['print_job_id']));
        }

        if (isset($_GET['options'])) {
            successResponse([
                'prices' => pqPrices(),
                'customers' => Database::fetchAll(
                    'SELECT u.user_id, u.full_name, u.phone, COALESCE(ba.balance, 0) AS wallet_balance
                     FROM users u LEFT JOIN billing_accounts ba ON ba.user_id = u.user_id
                     WHERE u.is_active = 1 ORDER BY u.full_name'
                ),
                'printers' => Database::fetchAll("SELECT peripheral_id, name, status FROM peripherals WHERE peripheral_type = 'printer' ORDER BY name"),
                'stations' => Database::fetchAll('SELECT station_id, station_code, name FROM stations ORDER BY station_code'),
            ]);
        }

        pqSync();
        // Unblock printers whose queue is waiting (e.g. after a job finished elsewhere).
        foreach (Database::fetchAll("SELECT DISTINCT peripheral_id FROM print_jobs WHERE status = 'queued'") as $row) {
            pqAdvance((int) $row['peripheral_id']);
        }

        if (isset($_GET['board'])) {
            $printers = Database::fetchAll(
                "SELECT p.peripheral_id, p.name, p.model, p.status, p.config, s.station_code
                 FROM peripherals p LEFT JOIN stations s ON s.station_id = p.station_id
                 WHERE p.peripheral_type = 'printer' ORDER BY p.name"
            );
            $active = Database::fetchAll(PQ_LIST_SELECT . " WHERE pj.status IN ('printing', 'queued') ORDER BY pj.peripheral_id, FIELD(pj.status, 'printing', 'queued'), pj.priority DESC, pj.created_at ASC");
            $byPrinter = [];
            foreach ($active as $job) {
                $byPrinter[$job['peripheral_id']][] = $job;
            }
            foreach ($printers as &$printer) {
                $cfg = json_decode((string) $printer['config'], true) ?: [];
                $jobs = $byPrinter[$printer['peripheral_id']] ?? [];
                foreach ($jobs as $i => &$job) {
                    $job['queue_position'] = $job['status'] === 'printing' ? 0 : $i + (isset($jobs[0]) && $jobs[0]['status'] === 'printing' ? 0 : 1);
                }
                unset($job);
                $printer['ip'] = $cfg['ip'] ?? null;
                $printer['protocol'] = $cfg['protocol'] ?? 'ipp';
                $printer['auto_print'] = deviceControlMode() === 'lan' && ($cfg['protocol'] ?? 'ipp') === 'ipp' && !empty($cfg['ip']);
                $printer['jobs'] = $jobs;
                unset($printer['config']);
            }
            unset($printer);
            $today = Database::fetchOne(
                "SELECT COUNT(*) AS jobs,
                        COALESCE(SUM(CASE WHEN status = 'completed' THEN pages * copies END), 0) AS sides,
                        COALESCE(SUM(CASE WHEN status = 'completed' AND color_mode = 'color' THEN pages * copies END), 0) AS color_sides,
                        COALESCE(SUM(CASE WHEN status NOT IN ('cancelled') AND payment_status IN ('paid','on_account') THEN amount END), 0) AS revenue,
                        COALESCE(SUM(CASE WHEN payment_status = 'unpaid' AND status <> 'cancelled' THEN amount END), 0) AS uncollected,
                        SUM(status = 'failed') AS failed,
                        ROUND(AVG(CASE WHEN started_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, created_at, started_at) END) / 60, 1) AS avg_wait
                 FROM print_jobs WHERE DATE(created_at) = CURDATE()"
            );
            successResponse([
                'printers' => $printers,
                'today' => $today,
                'prices' => pqPrices(),
                'mode' => deviceControlMode(),
                'attention' => Database::fetchAll(PQ_LIST_SELECT . " WHERE pj.status = 'failed' OR (pj.payment_status = 'unpaid' AND pj.status = 'completed') ORDER BY pj.updated_at DESC LIMIT 20"),
            ]);
        }

        if (isset($_GET['queue'])) {
            $jobs = Database::fetchAll(PQ_LIST_SELECT . " WHERE pj.status IN ('printing','queued') ORDER BY pj.peripheral_id, FIELD(pj.status, 'printing', 'queued'), pj.priority DESC, pj.created_at ASC LIMIT 50");
            $position = [];
            foreach ($jobs as &$job) {
                $key = $job['peripheral_id'];
                $position[$key] = ($position[$key] ?? ($job['status'] === 'printing' ? -1 : 0)) + 1;
                $job['queue_position'] = $job['status'] === 'printing' ? 0 : $position[$key];
            }
            unset($job);
            successResponse($jobs);
        }

        // History
        $where = [];
        $params = [];
        if (in_array($_GET['status'] ?? '', ['queued', 'printing', 'completed', 'failed', 'cancelled'], true)) {
            $where[] = 'pj.status = ?';
            $params[] = $_GET['status'];
        }
        if (in_array($_GET['payment'] ?? '', ['unpaid', 'paid', 'on_account', 'refunded', 'waived'], true)) {
            $where[] = 'pj.payment_status = ?';
            $params[] = $_GET['payment'];
        }
        if (!empty($_GET['printer'])) {
            $where[] = 'pj.peripheral_id = ?';
            $params[] = (int) $_GET['printer'];
        }
        if (trim((string) ($_GET['search'] ?? '')) !== '') {
            $like = '%' . addcslashes(trim((string) $_GET['search']), '%_\\') . '%';
            $where[] = '(pj.file_name LIKE ? OR u.full_name LIKE ? OR pj.print_job_id = ?)';
            array_push($params, $like, $like, (int) preg_replace('/\D/', '', (string) $_GET['search']));
        }
        foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
            if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', (string) ($_GET[$key] ?? ''))) {
                $where[] = "DATE(pj.created_at) $op ?";
                $params[] = $_GET[$key];
            }
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) Database::fetchColumn('SELECT COUNT(*) FROM print_jobs pj LEFT JOIN users u ON u.user_id = pj.user_id' . $whereSql, $params);
        $totals = Database::fetchOne(
            "SELECT COALESCE(SUM(CASE WHEN pj.status = 'completed' THEN pj.pages * pj.copies END), 0) AS sides,
                    COALESCE(SUM(CASE WHEN pj.payment_status IN ('paid','on_account') THEN pj.amount END), 0) AS billed
             FROM print_jobs pj LEFT JOIN users u ON u.user_id = pj.user_id" . $whereSql,
            $params
        );
        successResponse([
            'jobs' => Database::fetchAll(PQ_LIST_SELECT . $whereSql . ' ORDER BY pj.created_at DESC LIMIT 25 OFFSET ' . (($page - 1) * 25), $params),
            'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 25)), 'totals' => $totals,
        ]);
    }

    if ($method === 'DELETE') {
        requirePermission('print_jobs.cancel');
        $job = pqJob((int) ($input['print_job_id'] ?? 0));
        if (!in_array($job['status'], ['completed', 'failed', 'cancelled'], true)) {
            errorResponse('Cancel the job before deleting it.', 409);
        }
        $disk = dirname(__DIR__, 2) . '/' . ltrim((string) $job['file_path'], '/');
        Database::execute('DELETE FROM print_jobs WHERE print_job_id = ?', [$job['print_job_id']]);
        if (is_file($disk) && !Database::fetchColumn('SELECT COUNT(*) FROM print_jobs WHERE file_path = ?', [$job['file_path']])) {
            @unlink($disk);
        }
        logAuditTrail('print_job_deleted', 'print_job', (int) $job['print_job_id'], ['file' => $job['file_name']]);
        successResponse(null, 'Print job deleted');
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $action = (string) ($input['action'] ?? $_POST['action'] ?? 'create');
    if ($action === 'create') {
        requirePermission('print_jobs.manage');
        $file = storePrintUpload();
        $storedPath = $file['disk_path'];
        $job = pqCreate($input, $file);
        $storedPath = null;
        $message = 'Print job PR-' . $job['print_job_id'] . ' queued on ' . $job['printer_name'] . ' — ' . siteSetting('currency_symbol', 'GHS') . ' ' . number_format((float) $job['amount'], 2)
            . ($job['payment_status'] === 'unpaid' ? ' to collect' : ($job['payment_status'] === 'on_account' ? ' on account' : ' paid'));
        if ($job['status'] === 'printing' && $job['dispatch'] === 'ipp' && !$job['error_message']) {
            $message .= '. Sent to the printer.';
        }
        successResponse($job, $message);
    }

    $jobId = (int) ($input['print_job_id'] ?? 0);
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

    switch ($action) {
        case 'complete':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            if (!in_array($job['status'], ['printing', 'queued'], true)) {
                throw new InvalidArgumentException('Only queued or printing jobs can be marked as printed.');
            }
            pqFinish($jobId, 'completed', null);
            successResponse(pqJob($jobId), 'PR-' . $jobId . ' marked as printed' . ($job['payment_status'] === 'unpaid' ? ' — remember to collect payment.' : '.'));

        case 'fail':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            if ($job['status'] !== 'printing') {
                throw new InvalidArgumentException('Only a job that is printing can be marked as failed.');
            }
            pqFinish($jobId, 'failed', mb_substr(trim((string) ($input['reason'] ?? 'Marked as failed by staff')), 0, 255));
            successResponse(pqJob($jobId), 'PR-' . $jobId . ' marked as failed. Retry it or cancel it to refund.');

        case 'cancel':
            requirePermission('print_jobs.cancel');
            $reason = trim((string) ($input['reason'] ?? ''));
            if ($reason === '') {
                throw new InvalidArgumentException('Give a reason for cancelling.');
            }
            $result = pqCancel($jobId, $reason);
            successResponse(pqJob($jobId), 'PR-' . $jobId . ' cancelled.' . (isset($result['refund']['message']) ? ' ' . $result['refund']['message'] : ''));

        case 'retry':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            if ($job['status'] !== 'failed') {
                throw new InvalidArgumentException('Only failed jobs can be retried.');
            }
            $printer = pqPrinter((int) $job['peripheral_id']);
            Database::execute(
                "UPDATE print_jobs SET status = 'queued', started_at = NULL, completed_at = NULL, error_message = NULL, printer_job_id = NULL, dispatch = ? WHERE print_job_id = ?",
                [pqCanDispatch($printer, $job['mime_type']) ? 'ipp' : 'manual', $jobId]
            );
            pqAdvance((int) $job['peripheral_id']);
            successResponse(pqJob($jobId), 'PR-' . $jobId . ' is back in the queue.');

        case 'resend':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            $printer = pqPrinter((int) $job['peripheral_id']);
            if ($job['status'] !== 'printing' || !pqCanDispatch($printer, $job['mime_type'])) {
                throw new InvalidArgumentException('This job cannot be sent to the printer automatically.');
            }
            Database::execute("UPDATE print_jobs SET dispatch = 'ipp', error_message = NULL, printer_job_id = NULL WHERE print_job_id = ?", [$jobId]);
            pqDispatch($jobId);
            $job = pqJob($jobId);
            successResponse($job, $job['error_message'] ? $job['error_message'] : 'Sent to ' . $job['printer_name'] . '.');

        case 'collect':
            requirePermission('print_jobs.manage');
            $job = pqCollect($jobId, (string) ($input['payment_method'] ?? ''), $reference);
            successResponse($job, 'Payment of ' . number_format((float) $job['amount'], 2) . ' recorded for PR-' . $jobId . '.');

        case 'priority':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            if ($job['status'] !== 'queued') {
                throw new InvalidArgumentException('Only queued jobs can be re-prioritised.');
            }
            Database::execute('UPDATE print_jobs SET priority = ? WHERE print_job_id = ?', [max(0, min(10, (int) ($input['priority'] ?? 0))), $jobId]);
            successResponse(pqJob($jobId), 'Priority updated.');

        case 'move':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            if ($job['status'] !== 'queued' && $job['status'] !== 'failed') {
                throw new InvalidArgumentException('Only queued or failed jobs can be moved to another printer.');
            }
            $printer = pqPrinter((int) ($input['peripheral_id'] ?? 0));
            if ((int) $printer['peripheral_id'] === (int) $job['peripheral_id']) {
                throw new InvalidArgumentException('Choose a different printer.');
            }
            Database::execute(
                "UPDATE print_jobs SET peripheral_id = ?, status = 'queued', error_message = NULL, printer_job_id = NULL, started_at = NULL, completed_at = NULL, dispatch = ? WHERE print_job_id = ?",
                [$printer['peripheral_id'], pqCanDispatch($printer, $job['mime_type']) ? 'ipp' : 'manual', $jobId]
            );
            pqAdvance((int) $printer['peripheral_id']);
            successResponse(pqJob($jobId), 'Moved to ' . $printer['name'] . '.');

        case 'reprint':
            requirePermission('print_jobs.manage');
            $job = pqJob($jobId);
            $disk = dirname(__DIR__, 2) . '/' . ltrim((string) $job['file_path'], '/');
            if (!is_file($disk)) {
                throw new InvalidArgumentException('The original file is no longer on the server.');
            }
            $copy = pqCreate([
                'peripheral_id' => $input['peripheral_id'] ?? $job['peripheral_id'],
                'user_id' => $job['user_id'], 'station_id' => $job['station_id'],
                'pages' => $job['pages'], 'copies' => $input['copies'] ?? $job['copies'],
                'color_mode' => $job['color_mode'], 'duplex' => $job['duplex'], 'priority' => $job['priority'],
                'payment_method' => $input['payment_method'] ?? 'collect', 'reference' => $reference,
                'notes' => 'Reprint of PR-' . $jobId,
            ], [
                'file_name' => $job['file_name'], 'file_path' => $job['file_path'], 'disk_path' => $disk,
                'file_size' => $job['file_size'], 'mime_type' => $job['mime_type'],
            ]);
            successResponse($copy, 'Reprint queued as PR-' . $copy['print_job_id'] . '.');

        default:
            errorResponse('Unknown print job action', 400);
    }
} catch (InvalidArgumentException $e) {
    if ($storedPath && is_file($storedPath)) {
        @unlink($storedPath);
    }
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($storedPath && is_file($storedPath)) {
        @unlink($storedPath);
    }
    error_log('Print jobs API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    errorResponse('The print job request could not be completed.', 500);
}
