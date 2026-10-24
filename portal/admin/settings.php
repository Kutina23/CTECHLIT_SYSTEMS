<?php
/** Settings moved to the main portal page. */
require_once __DIR__ . '/../../includes/auth.php';
header('Location: ' . base_path('settings.php'), true, 301);
exit;
