<?php
/**
 * CTECHLIT & SYSTEMS — Personal Device Metering Cron
 *
 * Charges elapsed time to online personal devices, renews from wallets when
 * auto-renew is on, and disconnects devices whose time is used up. Also ends
 * expired station sessions. The portal does the same on page loads; this job
 * keeps things accurate when nobody has the portal open.
 *
 * Windows Task Scheduler (every minute):
 *   C:\xampp\php\php.exe C:\xampp\htdocs\systems\portal\cron\device-expiry.php
 */

require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/personal-devices.php';
require_once __DIR__ . '/../api/session-lifecycle.php';

$metered = pdMeterAll('cron');
$sessions = expireScheduledSessions();

echo '[' . date('Y-m-d H:i:s') . "] Metered {$metered} online device(s); ended {$sessions} expired session(s)\n";
