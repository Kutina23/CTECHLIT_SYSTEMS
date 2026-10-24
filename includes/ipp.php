<?php
/**
 * Minimal IPP/2.0 client (RFC 8011) for network printers.
 *
 * Supports Print-Job, Get-Job-Attributes, Cancel-Job and Get-Printer-Attributes,
 * which is enough to send PDF/JPEG/PNG documents straight to an IPP printer,
 * follow the job until it finishes, and report the printer's state.
 */

const IPP_OP_PRINT_JOB = 0x0002;
const IPP_OP_CANCEL_JOB = 0x0008;
const IPP_OP_GET_JOB_ATTRIBUTES = 0x0009;
const IPP_OP_GET_PRINTER_ATTRIBUTES = 0x000B;

const IPP_TAG_OPERATION = 0x01;
const IPP_TAG_JOB = 0x02;
const IPP_TAG_END = 0x03;

const IPP_INTEGER = 0x21;
const IPP_BOOLEAN = 0x22;
const IPP_ENUM = 0x23;
const IPP_TEXT = 0x41;
const IPP_NAME = 0x42;
const IPP_KEYWORD = 0x44;
const IPP_URI = 0x45;
const IPP_CHARSET = 0x47;
const IPP_LANGUAGE = 0x48;
const IPP_MIME = 0x49;

const IPP_JOB_STATES = [3 => 'pending', 4 => 'held', 5 => 'processing', 6 => 'stopped', 7 => 'canceled', 8 => 'aborted', 9 => 'completed'];
const IPP_PRINTER_STATES = [3 => 'idle', 4 => 'printing', 5 => 'stopped'];

/** Formats the server can send directly; other files are printed manually. */
const IPP_DIRECT_FORMATS = ['application/pdf', 'image/jpeg', 'image/png'];

function ippEndpoint(array $printerConfig): array
{
    $ip = (string) ($printerConfig['ip'] ?? '');
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        throw new RuntimeException('The printer has no valid IP address configured.');
    }
    $port = (int) ($printerConfig['port'] ?? 631) ?: 631;
    $path = '/' . ltrim((string) ($printerConfig['ipp_path'] ?? '/ipp/print'), '/');
    $host = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
    return ['http' => "http://$host:$port$path", 'uri' => "ipp://$host:$port$path"];
}

function ippAttribute(int $tag, string $name, array|string|int|bool $values): string
{
    $out = '';
    foreach ((array) $values as $index => $value) {
        $encoded = match ($tag) {
            IPP_INTEGER, IPP_ENUM => pack('N', (int) $value),
            IPP_BOOLEAN => chr($value ? 1 : 0),
            default => (string) $value,
        };
        $attrName = $index === 0 ? $name : '';
        $out .= chr($tag) . pack('n', strlen($attrName)) . $attrName . pack('n', strlen($encoded)) . $encoded;
    }
    return $out;
}

/**
 * Send an IPP request. Returns ['status' => int, 'ok' => bool, 'groups' => [[name => value|values]]].
 */
function ippRequest(array $printerConfig, int $operation, array $operationAttributes, array $jobAttributes = [], ?string $documentPath = null, int $timeout = 8): array
{
    $endpoint = ippEndpoint($printerConfig);
    static $requestId = 0;
    $requestId++;

    $body = pack('nnN', 0x0200, $operation, $requestId) . chr(IPP_TAG_OPERATION)
        . ippAttribute(IPP_CHARSET, 'attributes-charset', 'utf-8')
        . ippAttribute(IPP_LANGUAGE, 'attributes-natural-language', 'en')
        . ippAttribute(IPP_URI, 'printer-uri', $endpoint['uri']);
    foreach ($operationAttributes as [$tag, $name, $value]) {
        $body .= ippAttribute($tag, $name, $value);
    }
    if ($jobAttributes) {
        $body .= chr(IPP_TAG_JOB);
        foreach ($jobAttributes as [$tag, $name, $value]) {
            $body .= ippAttribute($tag, $name, $value);
        }
    }
    $body .= chr(IPP_TAG_END);
    if ($documentPath !== null) {
        $document = file_get_contents($documentPath);
        if ($document === false) {
            throw new RuntimeException('The document file could not be read.');
        }
        $body .= $document;
    }

    $handle = curl_init($endpoint['http']);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/ipp', 'Expect:'],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => $documentPath !== null ? max($timeout, 60) : $timeout,
    ]);
    $response = curl_exec($handle);
    $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($response === false || $response === '') {
        throw new RuntimeException('The printer did not respond' . ($error !== '' ? " ($error)" : '') . '.');
    }
    if ($httpStatus !== 200) {
        throw new RuntimeException("The printer rejected the request (HTTP $httpStatus). Check the IPP path in the printer settings.");
    }
    return ippParse($response);
}

function ippParse(string $data): array
{
    if (strlen($data) < 9) {
        throw new RuntimeException('The printer sent an incomplete IPP response.');
    }
    $status = unpack('n', substr($data, 2, 2))[1];
    $groups = [];
    $current = null;
    $lastName = null;
    $offset = 8;
    $length = strlen($data);
    while ($offset < $length) {
        $tag = ord($data[$offset]);
        $offset++;
        if ($tag === IPP_TAG_END) {
            break;
        }
        if ($tag < 0x10) {
            $groups[] = [];
            $current = count($groups) - 1;
            continue;
        }
        if ($offset + 2 > $length) {
            break;
        }
        $nameLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $name = substr($data, $offset, $nameLength);
        $offset += $nameLength;
        $valueLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
        $raw = substr($data, $offset, $valueLength);
        $offset += $valueLength;
        $value = match ($tag) {
            IPP_INTEGER, IPP_ENUM => $valueLength === 4 ? unpack('N', $raw)[1] : null,
            IPP_BOOLEAN => $raw === "\x01",
            default => $raw,
        };
        if (is_int($value) && $value > 0x7FFFFFFF) {
            $value -= 0x100000000;
        }
        if ($current === null) {
            $groups[] = [];
            $current = 0;
        }
        if ($name === '' && $lastName !== null) {
            $existing = $groups[$current][$lastName];
            $groups[$current][$lastName] = array_merge((array) $existing, [$value]);
        } else {
            $groups[$current][$name] = $value;
            $lastName = $name;
        }
    }
    return ['status' => $status, 'ok' => $status < 0x0100, 'groups' => $groups];
}

function ippAttr(array $response, string $name): mixed
{
    foreach ($response['groups'] as $group) {
        if (array_key_exists($name, $group)) {
            return $group[$name];
        }
    }
    return null;
}

function ippStatusMessage(array $response): string
{
    $message = ippAttr($response, 'status-message');
    $codes = [0x0400 => 'bad request', 0x0401 => 'forbidden', 0x0402 => 'authentication required', 0x0406 => 'not found',
        0x040A => 'document format not supported', 0x040B => 'attributes not supported', 0x0500 => 'internal printer error',
        0x0506 => 'printer is busy', 0x0507 => 'printer is not accepting jobs'];
    return trim((string) ($message ?: ($codes[$response['status']] ?? sprintf('status 0x%04X', $response['status']))));
}

/**
 * Send a document. Returns the printer's job id.
 */
function ippPrintJob(array $printerConfig, string $path, string $mimeType, string $jobName, int $copies, bool $duplex, bool $color, string $user): int
{
    $response = ippRequest($printerConfig, IPP_OP_PRINT_JOB, [
        [IPP_NAME, 'requesting-user-name', mb_substr($user, 0, 60) ?: 'CTECHLIT'],
        [IPP_NAME, 'job-name', mb_substr($jobName, 0, 120)],
        [IPP_MIME, 'document-format', $mimeType],
    ], [
        [IPP_INTEGER, 'copies', max(1, min(999, $copies))],
        [IPP_KEYWORD, 'sides', $duplex ? 'two-sided-long-edge' : 'one-sided'],
        [IPP_KEYWORD, 'print-color-mode', $color ? 'color' : 'monochrome'],
    ], $path);
    if (!$response['ok']) {
        // Some printers reject optional job attributes; retry once with only the essentials.
        if (in_array($response['status'], [0x040B, 0x0400], true)) {
            $response = ippRequest($printerConfig, IPP_OP_PRINT_JOB, [
                [IPP_NAME, 'requesting-user-name', 'CTECHLIT'],
                [IPP_NAME, 'job-name', mb_substr($jobName, 0, 120)],
                [IPP_MIME, 'document-format', $mimeType],
            ], [[IPP_INTEGER, 'copies', max(1, min(999, $copies))]], $path);
        }
        if (!$response['ok']) {
            throw new RuntimeException('The printer refused the job: ' . ippStatusMessage($response) . '.');
        }
    }
    $jobId = ippAttr($response, 'job-id');
    if (!is_int($jobId)) {
        throw new RuntimeException('The printer accepted the job but did not return a job number.');
    }
    return $jobId;
}

/** Returns ['state' => pending|processing|completed|canceled|aborted|..., 'reasons' => string]. */
function ippJobState(array $printerConfig, int $jobId): array
{
    $response = ippRequest($printerConfig, IPP_OP_GET_JOB_ATTRIBUTES, [
        [IPP_INTEGER, 'job-id', $jobId],
        [IPP_KEYWORD, 'requested-attributes', ['job-state', 'job-state-reasons', 'job-state-message']],
    ], [], null, 3);
    if (!$response['ok']) {
        throw new RuntimeException(ippStatusMessage($response));
    }
    $state = (int) ippAttr($response, 'job-state');
    $reasons = (array) ippAttr($response, 'job-state-reasons');
    return [
        'state' => IPP_JOB_STATES[$state] ?? 'unknown',
        'reasons' => trim(implode(', ', array_filter(array_merge($reasons, [ippAttr($response, 'job-state-message')]), fn($r) => $r && $r !== 'none'))),
    ];
}

function ippCancelJob(array $printerConfig, int $jobId): bool
{
    $response = ippRequest($printerConfig, IPP_OP_CANCEL_JOB, [[IPP_INTEGER, 'job-id', $jobId]], [], null, 3);
    return $response['ok'];
}

/** Printer state, reasons and supported formats. */
function ippPrinterInfo(array $printerConfig): array
{
    $response = ippRequest($printerConfig, IPP_OP_GET_PRINTER_ATTRIBUTES, [
        [IPP_KEYWORD, 'requested-attributes', ['printer-state', 'printer-state-reasons', 'printer-make-and-model', 'document-format-supported', 'queued-job-count', 'marker-names', 'marker-levels']],
    ], [], null, 4);
    if (!$response['ok']) {
        throw new RuntimeException(ippStatusMessage($response));
    }
    $state = (int) ippAttr($response, 'printer-state');
    $markerNames = (array) ippAttr($response, 'marker-names');
    $markerLevels = (array) ippAttr($response, 'marker-levels');
    $supplies = [];
    foreach ($markerNames as $i => $markerName) {
        if (isset($markerLevels[$i]) && is_int($markerLevels[$i]) && $markerLevels[$i] >= 0) {
            $supplies[] = ['name' => $markerName, 'level' => $markerLevels[$i]];
        }
    }
    return [
        'state' => IPP_PRINTER_STATES[$state] ?? 'unknown',
        'reasons' => array_values(array_filter((array) ippAttr($response, 'printer-state-reasons'), fn($r) => $r && $r !== 'none')),
        'model' => ippAttr($response, 'printer-make-and-model'),
        'formats' => (array) ippAttr($response, 'document-format-supported'),
        'queued' => ippAttr($response, 'queued-job-count'),
        'supplies' => $supplies,
    ];
}
