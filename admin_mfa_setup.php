<?php
// admin_mfa_setup.php

require_once 'functions.php'; // starts session + includes Connectdb.php
requireAdminLogin();

$admin   = getCurrentAdmin();
$adminId = $admin['admin_id'] ?? 0;

if (!$admin || $adminId <= 0) {
    header('Location: admin_login.php');
    exit();
}

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

global $conn;

// --- Handle enable/disable ---
$action = $_GET['action'] ?? 'enable';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    requireCsrfPost('admin_profile.php');
}

// Disable MFA: clear secret and flag
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'disable') {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE admin
         SET mfa_enabled = 0, mfa_secret = NULL
         WHERE admin_id = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $adminId);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'Two-factor authentication has been disabled for this admin account.';
        header('Location: admin_profile.php');
        exit();
    }
}

// Get current admin email + MFA status
$stmt = mysqli_prepare($conn, "
    SELECT email, mfa_secret, mfa_enabled
    FROM admin
    WHERE admin_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'i', $adminId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$adminRow = mysqli_fetch_assoc($result);

if (!$adminRow) {
    $error  = 'Unable to load admin data.';
    $adminRow = ['email' => '', 'mfa_secret' => '', 'mfa_enabled' => 0];
    $mfaData = ['secret' => '', 'qrurl' => '', 'qrimage' => ''];
} else {
    // Only generate a new secret if none exists yet AND action is enable
    if ($action === 'enable' && empty($adminRow['mfa_secret'])) {
        $secret = generateTOTPSecret();
        if (enableAdminMFA($adminId, $secret)) {
            $adminRow['mfa_secret'] = $secret;
        } else {
            $error   = 'Failed to generate MFA secret for this admin.';
            $mfaData = ['secret' => '', 'qrurl' => '', 'qrimage' => ''];
        }
    }

    if (!empty($adminRow['mfa_secret'])) {
        $mfaData = buildMFASetupData($adminRow['email'], $adminRow['mfa_secret'], 'ScanFit Admin');
    } else {
        $mfaData = ['secret' => '', 'qrurl' => '', 'qrimage' => ''];
    }
}

// Handle verification only when enabling
if ($action === 'enable'
    && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['verifycode'])
    && !empty($adminRow['mfa_secret'])
) {
    $code = trim($_POST['verifycode']);

    if ($code === '' || !ctype_digit($code) || strlen($code) !== 6) {
        $error = 'Please enter a valid 6-digit code.';
    } else {
        if (verifyTOTPSecret($adminRow['mfa_secret'], $code)) {
            // Mark enabled explicitly (in case it was only set by enableAdminMFA)
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE admin
                 SET mfa_enabled = 1
                 WHERE admin_id = ?"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $adminId);
                mysqli_stmt_execute($stmt);
            }

            $success = 'MFA enabled for your admin account!';
            // Refresh admin data
            $admin    = getCurrentAdmin();
            $adminRow = array_merge($adminRow, ['mfa_enabled' => 1]);
        } else {
            $error = 'Invalid code. Ensure your device time is correct.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Admin MFA - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .container {
            background: white;
            padding: 2.5rem;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            width: 100%;
            max-width: 500px;
        }
        h1 {
            text-align: center;
            color: #2c3e50;
            margin-bottom: 1.5rem;
            font-size: 2rem;
            font-weight: 800;
        }
        h3 {
            color: #2c3e50;
            margin-bottom: 1rem;
            font-size: 1.3rem;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
        }
        .qr-container {
            text-align: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 15px;
            border: 2px dashed #667eea;
        }
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 1rem auto;
            border: 3px solid #fff;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        code {
            background: #667eea;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.95rem;
        }
        form { margin-bottom: 2rem; }
        input[type="text"] {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 12px;
            font-size: 1.2rem;
            text-align: center;
            letter-spacing: 0.3rem;
            margin-bottom: 1rem;
            font-weight: 600;
            transition: border-color 0.3s;
        }
        input[type="text"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        button {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(102,126,234,0.4);
        }
        .nav-links {
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        .nav-links a {
            padding: 0.8rem 1.5rem;
            background: #f8f9fa;
            color: #667eea;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .nav-links a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-1px);
        }
        .info {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Admin Two-Factor Authentication</h1>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($action === 'enable' && !empty($mfaData['secret'])): ?>
            <div class="qr-container">
                <h3>Step 1: Add Account in Authenticator</h3>
                <p>Open <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>Microsoft Authenticator</strong></p>

                <?php if (!empty($mfaData['qrurl'])): ?>
                    <?php if (!empty($mfaData['qrimage'])): ?>
                        <img class="qr-code" src="<?php echo htmlspecialchars($mfaData['qrimage']); ?>" alt="ScanFit admin MFA QR code">
                    <?php endif; ?>
                    <p><small>Manual entry secret: <code><?php echo htmlspecialchars($mfaData['secret']); ?></code></small></p>
                    <p style="margin-top:0.8rem;">
                        <small>OTP URI:</small><br>
                        <code style="display:inline-block;word-break:break-all;"><?php echo htmlspecialchars($mfaData['qrurl']); ?></code>
                    </p>
                <?php else: ?>
                    <p>Unable to generate QR code. Please refresh this page.</p>
                <?php endif; ?>
            </div>

            <form method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="enable">
                <h3>Step 2: Verify Code</h3>
                <p class="info">Enter the <strong>6-digit code</strong> from your authenticator app to finish enabling MFA.</p>
                <input type="text" name="verifycode" maxlength="6" placeholder="123456" required autofocus>
                <button type="submit">✅ Enable Admin Two-Factor Authentication</button>
            </form>
        <?php elseif ($action === 'enable' && empty($mfaData['secret'])): ?>
            <p class="info">Unable to generate an MFA secret. Please try again later.</p>
        <?php endif; ?>

        <div class="nav-links">
            <a href="admin_profile.php">⚙️ Profile</a>
            <a href="admin_dashboard.php">📊 Dashboard</a>
            <a href="admin_logout.php">🚪 Logout</a>
        </div>
    </div>
</body>
</html>
