<?php
/**
 * Download a ready-to-run station agent installer (ZIP) for one station.
 *
 * GET agent-package.php?station_id=N[&relay=1][&server_url=https://example.com/systems]
 *
 * The ZIP contains the agent, the setup script and install.cmd pre-filled with the
 * station's ID, code, MAC address, the server URL and the shared agent key, so the
 * PC owner just double-clicks install.cmd. Requires the Manage Stations permission
 * because the package contains the agent key.
 */

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/device-control.php';
requireStaffAuth();
requirePermission('stations.manage');

function agentPackageFail(string $message, int $status = 400): void
{
    http_response_code($status);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
    exit;
}

/**
 * The URL a station should use to reach this server: explicit override, AGENT_SERVER_URL,
 * or the address the admin is browsing on.
 */
function agentServerUrl(?string $override): string
{
    $candidate = trim((string) ($override ?: deviceEnv('AGENT_SERVER_URL')));
    if ($candidate === '') {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
        $candidate = ($https ? 'https' : 'http') . '://' . $host . rtrim(base_path('', false), '/');
    }
    $parts = parse_url($candidate);
    if (!$parts || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
        agentPackageFail('The server URL must look like https://yourdomain.com/systems');
    }
    if (preg_match('/^(localhost|127\.|::1)/i', $parts['host'])) {
        agentPackageFail('The server URL cannot be localhost — stations need the public address (https://yourdomain.com/systems) or this server\'s LAN IP.');
    }
    return rtrim($candidate, '/');
}

$station = Database::fetchOne('SELECT station_id, station_code, name, mac_address FROM stations WHERE station_id = ?', [(int) ($_GET['station_id'] ?? 0)]);
if (!$station) {
    agentPackageFail('Station not found.', 404);
}
$mac = normalizeMacAddress($station['mac_address']);
if ($mac === null) {
    agentPackageFail("Add {$station['station_code']}'s MAC address on the Stations page first (run getmac /v on the PC to find it).");
}
$apiKey = deviceEnv('STATION_AGENT_API_KEY');
if ($apiKey === '') {
    agentPackageFail('STATION_AGENT_API_KEY is not set in config/.env on the server.', 500);
}
if (!class_exists('ZipArchive')) {
    agentPackageFail('The PHP zip extension is required to build installers.', 500);
}

$serverUrl = agentServerUrl($_GET['server_url'] ?? null);
$relayOnly = !empty($_GET['relay']);
$agentDir = realpath(__DIR__ . '/../assets/agents');
$code = preg_replace('/[^A-Za-z0-9_-]/', '', $station['station_code']);

$psArgs = sprintf(
    '-StationId %d -StationCode "%s" -ServerUrl "%s" -ApiKey "%s" -StationMacAddress "%s"%s',
    (int) $station['station_id'], $code, $serverUrl, $apiKey, str_replace(':', '-', $mac), $relayOnly ? ' -RelayOnly' : ''
);
$install = implode("\r\n", [
    '@echo off',
    'title CTECHLIT Station Agent - ' . $code,
    ':: Re-launch as Administrator if needed',
    'net session >nul 2>&1 || (powershell -NoProfile -Command "Start-Process -FilePath \'%~f0\' -Verb RunAs" & exit /b)',
    'cd /d "%~dp0"',
    'echo Installing the CTECHLIT agent for ' . $code . ' (' . str_replace(['&', '<', '>', '|', '^'], '', $station['name']) . ')...',
    'powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-agent.ps1" ' . $psArgs,
    'echo.',
    'echo Done. You can delete this folder now - it contains the agent key.',
    'pause',
    '',
]);
$uninstall = implode("\r\n", [
    '@echo off',
    'net session >nul 2>&1 || (powershell -NoProfile -Command "Start-Process -FilePath \'%~f0\' -Verb RunAs" & exit /b)',
    'powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0setup-agent.ps1" -Uninstall',
    'pause',
    '',
]);
$readme = implode("\r\n", [
    'CTECHLIT Station Agent - ' . $code . ' (' . $station['name'] . ')',
    '',
    '1. Unzip this folder on the PC ' . $code . '.',
    '2. Double-click install.cmd and allow the Administrator prompt.',
    '3. The agent installs to C:\\ProgramData\\CTECHLIT\\Agent and starts with Windows.',
    '4. Delete this folder afterwards: it contains the shared agent key.',
    '',
    'Server: ' . $serverUrl,
    'Mode:   ' . ($relayOnly ? 'relay only (never blocks this PC\'s internet)' : 'station (internet follows paid sessions)'),
    '',
    'The PC only makes outgoing HTTPS requests to the server; no router port forwarding is needed.',
    'To remove the agent, run uninstall.cmd.',
    '',
]);

$zipPath = tempnam(sys_get_temp_dir(), 'agent');
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
    agentPackageFail('Could not build the installer.', 500);
}
$folder = 'ctechlit-agent-' . $code . '/';
$zip->addFile($agentDir . DIRECTORY_SEPARATOR . 'ctechlit-station-agent.ps1', $folder . 'ctechlit-station-agent.ps1');
$zip->addFile($agentDir . DIRECTORY_SEPARATOR . 'setup-agent.ps1', $folder . 'setup-agent.ps1');
$zip->addFromString($folder . 'install.cmd', $install);
$zip->addFromString($folder . 'uninstall.cmd', $uninstall);
$zip->addFromString($folder . 'README.txt', $readme);
$zip->close();

logAuditTrail('agent_installer_downloaded', 'station', (int) $station['station_id'], ['server_url' => $serverUrl, 'relay_only' => $relayOnly]);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="ctechlit-agent-' . $code . '.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');
readfile($zipPath);
unlink($zipPath);
