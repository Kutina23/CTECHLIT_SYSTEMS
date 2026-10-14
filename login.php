<?php
/**
 * CTECHLIT & SYSTEMS — Login Redirect
 * Redirects to the portal authentication page
 */

declare(strict_types=1);

require_once __DIR__ . '/config/functions.php';

// If already authenticated as staff, redirect to portal
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'staff') {
    redirect('portal/');
}

// Otherwise, redirect to portal auth page
redirect('portal/auth.php');
