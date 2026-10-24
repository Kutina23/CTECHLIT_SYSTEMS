<?php
/**
 * CTECHLIT & SYSTEMS — Print queue engine
 *
 * - One queue per printer, ordered by priority then arrival.
 * - Price per printed side from the Printing services (B&W / Colour), with Settings as fallback.
 * - Payment at submission (cash, MoMo, card, bank, wallet, on account) or on collection.
 * - PDF/JPEG/PNG are sent straight to IPP printers when the server is on the café network;
 *   everything else is printed by staff and marked done.
 */

require_once __DIR__ . '/billing.php';
require_once __DIR__ . '/ipp.php';
require_once __DIR__ . '/device-control.php';

const PQ_PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'account', 'collect'];

/** Price per printed side for B&W and colour. */
function pqPrices(): array
{
    static $prices = null;
    if ($prices !== null) {
        return $prices;
    }
    $bw = null;
    $color = null;
    foreach (Database::fetchAll("SELECT name, base_price FROM services WHERE category = 'printing' AND is_active = 1 AND price_unit = 'per_page'") as $service) {
        $name = strtolower($service['name']);
        if ($color === null && preg_match('/colou?r/', $name)) {
            $color = (float) $service['base_price'];
        } elseif ($bw === null && preg_match('/black|b\s*&\s*w|mono|grey|gray/', $name)) {
            $bw = (float) $service['base_price'];
        }
    }
    $setting = static function (string $key): ?float {
        $value = Database::fetchColumn('SELECT setting_value FROM system_settings WHERE setting_key = ?', [$key]);
        return is_numeric($value) ? (float) $value : null;
    };
    $prices = [
        'bw' => $bw ?? $setting('print_cost_bw') ?? 0.50,
        'color' => $color ?? $setting('print_cost_color') ?? 2.00,
    ];
    return $prices;
}

function pqPrinter(int $peripheralId): array
{
    $printer = Database::fetchOne("SELECT * FROM peripherals WHERE peripheral_id = ? AND peripheral_type = 'printer'", [$peripheralId]);
    if (!$printer) {
        throw new InvalidArgumentException('Choose a valid printer.');
    }
    $printer['cfg'] = json_decode((string) ($printer['config'] ?? ''), true) ?: [];
    return $printer;
}

/** Can the server send this file straight to this printer? */
function pqCanDispatch(array $printer, ?string $mime): bool
{
    return deviceControlMode() === 'lan'
        && ($printer['cfg']['protocol'] ?? 'ipp') === 'ipp'
        && !empty($printer['cfg']['ip'])
        && in_array($mime, IPP_DIRECT_FORMATS, true)
        && $printer['status'] !== 'maintenance';
}

/** Best-effort page count for common formats (null when unknown). */
function pqDetectPages(string $path, string $mime): ?int
{
    if (str_starts_with($mime, 'image/')) {
        return 1;
    }
    if ($mime === 'application/pdf') {
        $data = (string) @file_get_contents($path, false, null, 0, 30 * 1024 * 1024);
        $pages = preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $data);
        if (!$pages && preg_match_all('#/Count\s+(\d+)#', $data, $m)) {
            $pages = max(array_map('intval', $m[1]));
        }
        return $pages > 0 ? $pages : null;
    }
    if (class_exists('ZipArchive') && in_array($mime, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'], true)) {
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $xml = (string) $zip->getFromName('docProps/app.xml');
            $zip->close();
            if (preg_match('#<(Pages|Slides)>(\d+)</#', $xml, $m)) {
                return (int) $m[2] ?: null;
            }
        }
    }
    return null;
}

function pqResolveCustomer(array $input): int
{
    if (!empty($input['user_id'])) {
        $id = (int) Database::fetchColumn('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1', [(int) $input['user_id']]);
        if (!$id) {
            throw new InvalidArgumentException('That customer is inactive or no longer exists.');
        }
        return $id;
    }
    $name = trim((string) ($input['customer_name'] ?? '')) ?: 'Walk-in Customer';
    $id = (int) Database::fetchColumn('SELECT user_id FROM users WHERE LOWER(full_name) = LOWER(?) AND is_active = 1 LIMIT 1', [$name]);
    return $id ?: Database::insert("INSERT INTO users (full_name, membership_tier, is_active) VALUES (?, 'standard', 1)", [mb_substr($name, 0, 100)]);
}

function pqJob(int $jobId): array
{
    $job = Database::fetchOne(
        "SELECT pj.*, u.full_name AS customer_name, u.phone AS customer_phone, st.station_code, pr.name AS printer_name,
                sf.full_name AS staff_name, t.status AS charge_status, t.receipt_number,
                TIMESTAMPDIFF(MINUTE, pj.created_at, COALESCE(pj.started_at, NOW())) AS wait_minutes
         FROM print_jobs pj
         LEFT JOIN users u ON u.user_id = pj.user_id
         LEFT JOIN stations st ON st.station_id = pj.station_id
         LEFT JOIN peripherals pr ON pr.peripheral_id = pj.peripheral_id
         LEFT JOIN staff sf ON sf.staff_id = pj.staff_id
         LEFT JOIN transactions t ON t.transaction_id = pj.transaction_id
         WHERE pj.print_job_id = ?",
        [$jobId]
    );
    if (!$job) {
        throw new InvalidArgumentException('Print job not found.');
    }
    return $job;
}

function pqCharge(int $userId, float $amount, string $method, string $description, ?string $reference): array
{
    if ($method === 'collect') {
        return ['transaction_id' => null, 'payment_status' => 'unpaid'];
    }
    $payment = billingCollectPayment($userId, $amount, $method, $description, ['reference' => $reference]);
    return ['transaction_id' => $payment['transaction_id'], 'payment_status' => $payment['status'] === 'pending' ? 'on_account' : 'paid', 'receipt' => $payment['receipt_number']];
}

/**
 * Create and queue a job from an already-stored upload.
 */
function pqCreate(array $input, array $file): array
{
    $printer = pqPrinter((int) ($input['peripheral_id'] ?? 0));
    if ($printer['status'] === 'maintenance') {
        throw new InvalidArgumentException("{$printer['name']} is under maintenance. Choose another printer.");
    }
    $copies = max(1, min(500, (int) ($input['copies'] ?? 1)));
    $detected = pqDetectPages($file['disk_path'], $file['mime_type']);
    $pages = !empty($input['pages']) ? (int) $input['pages'] : (int) $detected;
    if ($pages < 1 || $pages > 5000) {
        throw new InvalidArgumentException('Enter the number of pages (it could not be detected for this file type).');
    }
    $color = ($input['color_mode'] ?? 'bw') === 'color';
    $duplex = !empty($input['duplex']) && $input['duplex'] !== '0' && $input['duplex'] !== 'false';
    $unit = pqPrices()[$color ? 'color' : 'bw'];
    $amount = round($pages * $copies * $unit, 2);
    $method = (string) ($input['payment_method'] ?? 'collect');
    if (!in_array($method, PQ_PAYMENT_METHODS, true)) {
        throw new InvalidArgumentException('Choose a valid payment option.');
    }
    $priority = max(0, min(10, (int) ($input['priority'] ?? 0)));
    $stationId = !empty($input['station_id']) ? (int) $input['station_id'] : null;
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

    $jobId = billingAtomic(function () use ($input, $file, $printer, $pages, $copies, $color, $duplex, $unit, $amount, $method, $priority, $stationId, $reference) {
        $userId = pqResolveCustomer($input);
        $charge = pqCharge($userId, $amount, $method, "Printing: {$file['file_name']} ({$pages}p × {$copies}, " . ($color ? 'colour' : 'B&W') . ')', $reference);
        return Database::insert(
            "INSERT INTO print_jobs (user_id, station_id, peripheral_id, staff_id, transaction_id, file_name, file_path, file_size, mime_type,
                                     pages, copies, color_mode, duplex, unit_price, amount, payment_status, dispatch, priority, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'queued', ?)",
            [
                $userId, $stationId, $printer['peripheral_id'], billingStaffId(), $charge['transaction_id'],
                $file['file_name'], $file['file_path'], $file['file_size'], $file['mime_type'],
                $pages, $copies, $color ? 'color' : 'bw', $duplex ? 1 : 0, $unit, $amount, $charge['payment_status'],
                pqCanDispatch($printer, $file['mime_type']) ? 'ipp' : 'manual', $priority,
                mb_substr(trim((string) ($input['notes'] ?? '')), 0, 255) ?: null,
            ]
        );
    });
    logAuditTrail('print_job_created', 'print_job', $jobId, ['printer' => $printer['peripheral_id'], 'pages' => $pages, 'copies' => $copies, 'amount' => $amount, 'payment' => $method]);
    pqAdvance((int) $printer['peripheral_id']);
    return pqJob($jobId) + ['pages_detected' => $detected !== null];
}

/**
 * Start the next job on a printer if it is free. Sends it over IPP when possible.
 */
function pqAdvance(int $peripheralId): ?array
{
    $busy = Database::fetchColumn("SELECT print_job_id FROM print_jobs WHERE peripheral_id = ? AND status = 'printing' LIMIT 1", [$peripheralId]);
    if ($busy) {
        return null;
    }
    $next = Database::fetchOne(
        "SELECT * FROM print_jobs WHERE peripheral_id = ? AND status = 'queued' ORDER BY priority DESC, created_at ASC, print_job_id ASC LIMIT 1",
        [$peripheralId]
    );
    if (!$next) {
        return null;
    }
    Database::execute("UPDATE print_jobs SET status = 'printing', started_at = NOW(), error_message = NULL WHERE print_job_id = ? AND status = 'queued'", [$next['print_job_id']]);
    if ($next['dispatch'] === 'ipp') {
        pqDispatch((int) $next['print_job_id']);
    }
    return pqJob((int) $next['print_job_id']);
}

/** Send a printing job to its IPP printer; on failure fall back to manual printing. */
function pqDispatch(int $jobId): void
{
    $job = pqJob($jobId);
    $printer = pqPrinter((int) $job['peripheral_id']);
    $disk = dirname(__DIR__) . '/' . ltrim((string) $job['file_path'], '/');
    try {
        if (!is_file($disk)) {
            throw new RuntimeException('The uploaded file is missing on the server.');
        }
        $printerJobId = ippPrintJob($printer['cfg'], $disk, (string) $job['mime_type'], 'PR-' . $jobId . ' ' . $job['file_name'],
            (int) $job['copies'], (bool) $job['duplex'], $job['color_mode'] === 'color', (string) ($job['customer_name'] ?? 'Customer'));
        Database::execute("UPDATE print_jobs SET printer_job_id = ?, dispatch = 'ipp' WHERE print_job_id = ?", [$printerJobId, $jobId]);
        Database::execute("UPDATE peripherals SET status = 'online' WHERE peripheral_id = ? AND status <> 'maintenance'", [$printer['peripheral_id']]);
    } catch (Throwable $error) {
        Database::execute(
            "UPDATE print_jobs SET dispatch = 'manual', error_message = ? WHERE print_job_id = ?",
            ['Not sent automatically: ' . rtrim(mb_substr($error->getMessage(), 0, 200), '.') . '. Print it from the file and mark it done.', $jobId]
        );
        if (str_contains($error->getMessage(), 'did not respond')) {
            Database::execute("UPDATE peripherals SET status = 'offline' WHERE peripheral_id = ? AND status <> 'maintenance'", [$printer['peripheral_id']]);
        }
    }
}

/** Poll IPP printers for jobs they are processing and finish/advance them. */
function pqSync(): int
{
    $changed = 0;
    $jobs = Database::fetchAll("SELECT print_job_id, peripheral_id, printer_job_id FROM print_jobs WHERE status = 'printing' AND dispatch = 'ipp' AND printer_job_id IS NOT NULL");
    foreach ($jobs as $job) {
        try {
            $state = ippJobState(pqPrinter((int) $job['peripheral_id'])['cfg'], (int) $job['printer_job_id']);
        } catch (Throwable $error) {
            continue; // printer unreachable right now; try again on the next poll
        }
        if ($state['state'] === 'completed') {
            pqFinish((int) $job['print_job_id'], 'completed', null);
            $changed++;
        } elseif (in_array($state['state'], ['canceled', 'aborted'], true)) {
            pqFinish((int) $job['print_job_id'], 'failed', 'Printer reported the job ' . $state['state'] . ($state['reasons'] ? ': ' . $state['reasons'] : '') . '.');
            $changed++;
        } elseif ($state['reasons'] !== '') {
            Database::execute('UPDATE print_jobs SET error_message = ? WHERE print_job_id = ?', ['Printer: ' . mb_substr($state['reasons'], 0, 200), $job['print_job_id']]);
        }
    }
    return $changed;
}

function pqFinish(int $jobId, string $status, ?string $message): void
{
    $job = pqJob($jobId);
    Database::execute(
        'UPDATE print_jobs SET status = ?, completed_at = NOW(), error_message = ? WHERE print_job_id = ?',
        [$status, $message, $jobId]
    );
    logAuditTrail('print_job_' . $status, 'print_job', $jobId, ['message' => $message]);
    pqAdvance((int) $job['peripheral_id']);
}

/** Cancel a job, stopping it on the printer and reversing any payment. */
function pqCancel(int $jobId, string $reason): array
{
    $job = pqJob($jobId);
    if (!in_array($job['status'], ['queued', 'printing', 'failed'], true)) {
        throw new InvalidArgumentException('Only queued, printing or failed jobs can be cancelled.');
    }
    if ($job['status'] === 'printing' && $job['dispatch'] === 'ipp' && $job['printer_job_id']) {
        try {
            ippCancelJob(pqPrinter((int) $job['peripheral_id'])['cfg'], (int) $job['printer_job_id']);
        } catch (Throwable $error) {
            // The printer may already be done; the queue is still updated below.
        }
    }
    $refund = null;
    if ($job['transaction_id'] && in_array($job['payment_status'], ['paid', 'on_account'], true)) {
        $refund = billingRefundTransaction((int) $job['transaction_id'], 'Print job PR-' . $jobId . ' cancelled: ' . $reason);
        Database::execute("UPDATE print_jobs SET payment_status = 'refunded' WHERE print_job_id = ?", [$jobId]);
    } elseif ($job['payment_status'] === 'unpaid') {
        Database::execute("UPDATE print_jobs SET payment_status = 'waived' WHERE print_job_id = ?", [$jobId]);
    }
    $wasPrinting = $job['status'] === 'printing';
    Database::execute(
        "UPDATE print_jobs SET status = 'cancelled', completed_at = NOW(), error_message = ? WHERE print_job_id = ?",
        ['Cancelled: ' . mb_substr($reason, 0, 200), $jobId]
    );
    logAuditTrail('print_job_cancelled', 'print_job', $jobId, ['reason' => $reason, 'refund' => $refund['mode'] ?? null]);
    if ($wasPrinting) {
        pqAdvance((int) $job['peripheral_id']);
    }
    return ['refund' => $refund];
}

/** Take payment for a job that was set to pay on collection. */
function pqCollect(int $jobId, string $method, ?string $reference): array
{
    $job = pqJob($jobId);
    if ($job['payment_status'] !== 'unpaid') {
        throw new InvalidArgumentException('This job has no payment to collect.');
    }
    if (!in_array($method, ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'account'], true)) {
        throw new InvalidArgumentException('Choose how the customer is paying.');
    }
    return billingAtomic(function () use ($job, $method, $reference) {
        $charge = pqCharge((int) $job['user_id'], (float) $job['amount'], $method, "Printing: {$job['file_name']} (PR-{$job['print_job_id']})", $reference);
        Database::execute('UPDATE print_jobs SET transaction_id = ?, payment_status = ? WHERE print_job_id = ?', [$charge['transaction_id'], $charge['payment_status'], $job['print_job_id']]);
        return pqJob((int) $job['print_job_id']);
    });
}
