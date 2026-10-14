<?php
/**
 * CTECHLIT & SYSTEMS — Portal Authentication
 * Clean, professional role-based login for Admin & Staff
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// If already authenticated, redirect to dashboard
if (isAuthenticated() && $_SESSION['user_type'] === 'staff') {
    redirect('./');
}

$error = '';
$success = '';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!isValidEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up staff by email
        $staff = Database::fetchOne(
            "SELECT staff_id, email, password_hash, full_name, role, is_active FROM staff WHERE email = ?",
            [$email]
        );

        if (!$staff) {
            $error = 'Invalid email or password.';
        } elseif (!$staff['is_active']) {
            $error = 'Your account has been deactivated. Please contact an administrator.';
        } elseif (!verifyPassword($password, $staff['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
                // Successful login
                loginUser((int) $staff['staff_id'], 'staff', $remember);

                $role = strtolower($staff['role'] ?? '');
                if ($role === 'ceo' || $role === 'admin') {
                    redirect('./admin/');
                } else {
                    redirect('./');
                }
            }
    }
}

// Get current page for active state
$currentPage = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — CTECHLIT & SYSTEMS</title>
    <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/portal.css">
    <style>
        /* ============================================================
           AUTH PAGE — Clean, professional, no card design
           ============================================================ */

        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #EEF2FF 0%, #E0F2FE 45%, #F5F3FF 100%);
            padding: 24px;
            position: relative;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .button-loading-spinner {
            display: inline-block;
            width: 1em;
            height: 1em;
            flex: 0 0 1em;
            border: 2px solid currentColor;
            border-right-color: transparent;
            border-radius: 50%;
            animation: button-loading-spin 0.7s linear infinite;
        }

        button[aria-busy="true"] {
            cursor: wait;
            opacity: 0.8;
        }

        @keyframes button-loading-spin {
            to { transform: rotate(360deg); }
        }

        .auth-page::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: 0;
            pointer-events: none;
            background:
                radial-gradient(600px 500px at 10% 20%, rgba(124, 58, 237, 0.12), transparent 60%),
                radial-gradient(500px 400px at 90% 70%, rgba(6, 182, 212, 0.10), transparent 60%),
                radial-gradient(500px 400px at 50% 110%, rgba(236, 72, 153, 0.08), transparent 60%);
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 1;
        }

        .auth-brand {
            text-align: center;
            margin-bottom: 40px;
        }

        .auth-logo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            border-radius: var(--radius-lg);
            background: linear-gradient(135deg, var(--violet), var(--cyan), var(--pink));
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(124, 58, 237, 0.25);
        }

        .auth-logo i {
            font-size: 24px;
            color: white;
        }

        .auth-brand h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--ink);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
        }

        .auth-brand p {
            font-size: 14px;
            color: var(--muted);
            font-weight: 400;
        }

        .auth-form {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(16px) saturate(150%);
            -webkit-backdrop-filter: blur(16px) saturate(150%);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: var(--radius-xl);
            padding: 36px 32px;
            box-shadow: 0 10px 28px rgba(15, 21, 48, 0.08);
            position: relative;
            overflow: hidden;
        }

        .auth-form::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(255,255,255,.7) 0%, rgba(255,255,255,.25) 50%, rgba(255,255,255,.40) 100%);
            pointer-events: none;
            z-index: 1;
            border-radius: inherit;
        }

        .auth-form > * {
            position: relative;
            z-index: 2;
        }

        .auth-form-header {
            margin-bottom: 28px;
        }

        .auth-form-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 4px;
        }

        .auth-form-header p {
            font-size: 13px;
            color: var(--muted);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: var(--ink-secondary);
            margin-bottom: 6px;
        }

        .form-input-wrapper {
            position: relative;
        }

        .form-input-wrapper > i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 14px;
            pointer-events: none;
            transition: var(--transition);
        }

        .form-input {
            width: 100%;
            padding: 12px 42px 12px 42px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: var(--radius);
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(10px);
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            color: var(--ink);
            transition: var(--transition);
            outline: none;
        }

        .form-input:focus {
            border-color: var(--violet-light);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.10);
            background: rgba(255, 255, 255, 0.75);
        }

        .form-input:focus + i {
            color: var(--violet);
        }

        .form-input::placeholder {
            color: var(--muted);
        }

        .form-input.error {
            border-color: var(--red);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.08);
        }

        .password-toggle {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            font-size: 14px;
            cursor: pointer;
            padding: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            border-radius: var(--radius-sm);
        }

        .password-toggle:hover {
            color: var(--ink-secondary);
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(8px);
        }

        .form-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }

        .form-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--violet);
            cursor: pointer;
        }

        .form-checkbox span {
            font-size: 13px;
            color: var(--ink-secondary);
            user-select: none;
        }

        .form-link {
            font-size: 13px;
            color: var(--violet);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
        }

        .form-link:hover {
            color: var(--violet-light);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--violet), var(--cyan), var(--pink));
            color: white;
            box-shadow: 0 4px 12px rgba(124, 58, 237, 0.30);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(124, 58, 237, 0.40);
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .auth-footer {
            text-align: center;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.55);
        }

        .auth-footer p {
            font-size: 13px;
            color: var(--muted);
        }

        .auth-footer a {
            color: var(--violet);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-footer a:hover {
            color: var(--violet-light);
        }

        .alert {
            padding: 12px 16px;
            border-radius: var(--radius);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.08);
            color: var(--red);
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.08);
            color: var(--green);
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        .alert i {
            font-size: 16px;
            flex-shrink: 0;
        }

        .auth-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }

        .auth-divider::before,
        .auth-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: rgba(255, 255, 255, 0.55);
        }

        .auth-divider span {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .role-hint {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            background: rgba(255, 255, 255, 0.55);
            backdrop-filter: blur(10px);
            border-radius: var(--radius);
            margin-bottom: 20px;
            font-size: 12px;
            color: var(--ink-secondary);
            border: 1px solid rgba(255, 255, 255, 0.6);
        }

        .role-hint i {
            color: var(--violet);
            font-size: 14px;
        }

        .role-hint strong {
            color: var(--ink);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .auth-form {
                padding: 28px 20px;
            }

            .auth-brand h1 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <!-- Brand -->
        

        <!-- Login Form -->
        <div class="auth-form">
            <div class="auth-form-header">
                <h2>Welcome back</h2>
                <p>Sign in to access the  portal</p>
            </div>

            <!-- Error Alert -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo sanitize($error); ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Alert -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo sanitize($success); ?></span>
                </div>
            <?php endif; ?>

            <!-- Role Hint -->
            <div class="role-hint">
                <i class="fas fa-info-circle"></i>
                <span>Use your <strong> credentials</strong> to sign in.</span>
            </div>

            <form method="POST" action="" autocomplete="off">
                <!-- Email -->
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <div class="form-input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            class="form-input"
                            placeholder="you@ctechlitsystems.com"
                            value="<?php echo sanitize($_POST['email'] ?? ''); ?>"
                            required
                            autofocus
                        >
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <!-- Password -->
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="form-input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-input"
                            placeholder="Enter your password"
                            required
                        >
                        <i class="fas fa-lock"></i>
                        <button type="button" class="password-toggle" id="togglePassword" aria-label="Toggle password visibility">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="form-actions">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Remember me</span>
                    </label>
                </div>

                <!-- Submit -->
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    Sign In
                </button>
            </form>

            <div class="auth-divider">
                <span>Secure Access</span>
            </div>

            <div class="auth-footer">
                <p>Protected by role-based access control</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-focus email field
        document.getElementById('email').focus();

        // Add error class to inputs if there was an error
        document.querySelectorAll('.form-input').forEach(input => {
            if (input.value && !input.checkValidity()) {
                input.classList.add('error');
            }
        });

        // Password visibility toggle
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const toggleIcon = togglePassword.querySelector('i');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            toggleIcon.classList.toggle('fa-eye');
            toggleIcon.classList.toggle('fa-eye-slash');
        });
    </script>
<script src="../assets/js/button-loading.js"></script>
</body>
</html>
