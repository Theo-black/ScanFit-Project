<?php
// admin_mfa_verify.php
// Step 2 of admin login when MFA is enabled

require_once 'functions.php'; // starts session + DB

// If admin is already fully logged in (with MFA), go to dashboard
if (isset($_SESSION['admin_id']) && empty($_SESSION['admin_mfa_pending'])) {
    header('Location: admin_dashboard.php');
    exit();
}

// Get pending admin id from session, set at end of admin_login.php
$pendingAdminId = $_SESSION['admin_mfa_pending'] ?? null;
if (!$pendingAdminId) {
    // No pending MFA session, send back to login
    header('Location: admin_login.php');
    exit();
}

$admin = getAdminById((int)$pendingAdminId);
if (!$admin || (int)$admin['mfa_enabled'] !== 1 || empty($admin['mfa_secret'])) {
    // Admin no longer has MFA enabled or not found; reset and return to login
    unset($_SESSION['admin_mfa_pending']);
    header('Location: admin_login.php');
    exit();
}

$error = null;

// Handle MFA code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('admin_login.php');
    $code = trim($_POST['code'] ?? '');

    if ($code === '') {
        $error = 'Please enter the 6-digit code.';
    } elseif (!ctype_digit($code) || strlen($code) !== 6) {
        $error = 'The code must be exactly 6 digits.';
    } else {
        // Use your helper from functions.php (TOTP verification)
        if (verifyAdminTOTP($admin, $code)) {
            // Success: promote pending MFA to full admin login
            $_SESSION['admin_id'] = (int)$admin['admin_id'];
            unset($_SESSION['admin_mfa_pending']);

            // Optional: regenerate session ID to avoid fixation
            session_regenerate_id(true);

            header('Location: admin_dashboard.php');
            exit();
        } else {
            $error = 'Invalid or expired code. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin MFA Verification - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:linear-gradient(135deg,#141E30 0%,#243B55 100%);
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            padding:2rem;color:#fff
        }
        .login-card{
            background:#1f2933;border-radius:20px;padding:3rem;
            box-shadow:0 30px 80px rgba(0,0,0,.5);max-width:420px;width:100%
        }
        .logo{text-align:center;font-size:2rem;font-weight:800;color:#4f9cf9;margin-bottom:1.5rem}
        h1{text-align:center;margin-bottom:.5rem;font-size:1.8rem}
        .subtitle{text-align:center;margin-bottom:2rem;color:#9aa5b1;font-size:.95rem}
        .form-group{margin-bottom:1.5rem}
        .form-group label{display:block;margin-bottom:.4rem;font-weight:600;color:#e5e9f0}
        .form-group input{
            width:100%;padding:0.9rem 1rem;border-radius:10px;border:1px solid #3e4c59;
            background:#111827;color:#e5e9f0;font-size:1.2rem;outline:none;
            text-align:center;letter-spacing:0.35rem;font-weight:600;
        }
        .form-group input:focus{border-color:#4f9cf9}
        .error-msg{
            background:#b91c1c;color:#fff;padding:0.8rem 1rem;border-radius:10px;
            margin-bottom:1.2rem;font-size:.9rem;text-align:center
        }
        .submit-btn{
            width:100%;padding:1rem;border:none;border-radius:12px;
            background:linear-gradient(135deg,#4f9cf9 0%,#6366f1 100%);
            color:#fff;font-weight:700;font-size:1rem;cursor:pointer;
            transition:transform .2s,box-shadow .2s
        }
        .submit-btn:hover{
            transform:translateY(-1px);
            box-shadow:0 10px 30px rgba(79,156,249,.4)
        }
        .helper{
            margin-top:1rem;text-align:center;font-size:.85rem;color:#9aa5b1
        }
        .back-link{
            margin-top:1.5rem;text-align:center;font-size:.9rem;color:#9aa5b1
        }
        .back-link a{color:#4f9cf9;text-decoration:none;font-weight:600}
        .back-link a:hover{text-decoration:underline}
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">SCANFIT ADMIN</div>
    <h1>Two-Factor Verification</h1>
    <p class="subtitle">
        Enter the 6-digit code from your authenticator app to finish signing in.
    </p>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="admin_mfa_verify.php">
        <?php echo csrfInput(); ?>
        <div class="form-group">
            <label for="code">Authentication code</label>
            <input
                type="text"
                id="code"
                name="code"
                maxlength="6"
                pattern="\d{6}"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123456"
                required
            >
        </div>
        <button type="submit" class="submit-btn">Verify &amp; Continue</button>
    </form>

    <p class="helper">
        Code not working? Check your phone time and be sure you are using the ScanFit admin entry.
    </p>

    <div class="back-link">
        <a href="admin_login.php">← Back to admin login</a>
    </div>
</div>
</body>
</html>
