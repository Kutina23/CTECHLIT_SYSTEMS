<?php
/**
 * Printer Management API
 * Handle printer network config, connection testing, Wake-on-LAN
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'] ?? ($_POST ?: ($_GET ?: []));
if (!is_array($input)) {
    $input = [];
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['peripheral_id'])) {
                $config = getPrinterConfig((int) $_GET['peripheral_id']);
                if ($config === null) {
                    errorResponse('Printer not found or not configured', 404);
                }
                successResponse($config);
            } else {
                $printers = portalPrinters();
                // Parse config for each printer
                foreach ($printers as &$p) {
                    $p['config'] = $p['config'] ? json_decode($p['config'], true) : null;
                }
                successResponse($printers);
            }
            break;

        case 'POST':
            $action = $_GET['action'] ?? '';
            
            if ($action === 'test') {
                validateRequired($input, ['peripheral_id']);
                $result = testPrinterConnection((int) $input['peripheral_id']);
                if ($result['success']) {
                    successResponse(['reachable' => true, 'status' => $result['status']], $result['message']);
                } else {
                    jsonResponse(['success' => false, 'message' => $result['message'], 'data' => ['reachable' => false, 'status' => $result['status']]], 200);
                }
            } elseif ($action === 'wake') {
                validateRequired($input, ['peripheral_id']);
                $result = wakePrinter((int) $input['peripheral_id']);
                if ($result['success']) {
                    successResponse(['reachable' => true], $result['message']);
                } else {
                    jsonResponse(['success' => false, 'message' => $result['message'], 'data' => ['reachable' => false]], 200);
                }
            } elseif ($action === 'configure') {
                validateRequired($input, ['peripheral_id', 'ip_address']);
                $ip = trim((string) $input['ip_address']);
                if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                    errorResponse('Enter a valid printer IP address.');
                }
                $protocol = $input['protocol'] ?? 'ipp';
                if (!in_array($protocol, ['ipp', 'lpd'], true)) {
                    errorResponse('Printer protocol must be IPP or LPD.');
                }
                $result = setPrinterConfig(
                    (int) $input['peripheral_id'],
                    $ip,
                    trim((string) ($input['mac_address'] ?? '')),
                    $protocol
                );
                if ($result) {
                    successResponse(['configured' => true], 'Printer connection settings saved.');
                }
                errorResponse('Failed to save printer connection settings.', 500);
            } else {
                // Configure printer network settings
                validateRequired($input, ['peripheral_id', 'ip_address', 'mac_address']);
                $result = setPrinterConfig(
                    (int) $input['peripheral_id'],
                    $input['ip_address'],
                    $input['mac_address'],
                    $input['protocol'] ?? 'ipp'
                );
                if ($result) {
                    successResponse(null, 'Printer configured successfully');
                } else {
                    errorResponse('Failed to configure printer');
                }
            }
            break;

        case 'PUT':
            validateRequired($input, ['peripheral_id', 'ip_address', 'mac_address']);
            $result = setPrinterConfig(
                (int) $input['peripheral_id'],
                $input['ip_address'],
                $input['mac_address'],
                $input['protocol'] ?? 'ipp'
            );
            if ($result) {
                successResponse(null, 'Printer updated successfully');
            } else {
                errorResponse('Failed to update printer');
            }
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}