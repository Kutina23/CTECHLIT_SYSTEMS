<?php
/**
 * CTECHLIT & SYSTEMS — Logout
 * Destroys session and redirects to portal auth
 */

declare(strict_types=1);

require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Logout
logout();

// Redirect to portal auth page
redirect('portal/auth.php');
