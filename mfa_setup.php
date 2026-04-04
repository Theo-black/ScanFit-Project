<?php
// mfa_setup.php

require_once 'functions.php'; // starts session + includes Connectdb.php

if (!isLoggedIn()) {
    $_SESSION['error'] = 'Please login first.';
    header('Location: login.php');
    exit;
}

$customer_Id = getCustomerId();
$success     = $_SESSION['success'] ?? null;
$error       = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);
$newBackupCodes = [];

global $conn;

// --- Handle enable/disable actions ---
$action = $_GET['action'] ?? 'enable';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $action;
    requireCsrfPost('settings.php');
}

// Disable MFA: clear secret and backup codes, set mfaenabled = 0, then show message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'disable') {
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE customer
         SET mfaenabled = 0, mfasecret = NULL, mfabackupcodes = NULL
         WHERE customer_id = ?"
    );
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $customer_Id);
        mysqli_stmt_execute($stmt);
        $_SESSION['success'] = 'Two-factor authentication has been disabled for your account.';
        header('Location: settings.php');
        exit();
    }
}

// Get customer email + current mfa secret status
$stmt = mysqli_prepare($conn, "
    SELECT email, mfasecret, mfaenabled, mfabackupcodes
    FROM customer
    WHERE customer_id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, 'i', $customer_Id);
mysqli_stmt_execute($stmt);
$result   = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($result);

if (!$customer) {
    $error  = 'Unable to load customer data.';
    $customer = ['mfaenabled' => 0, 'mfabackupcodes' => null];
    $mfaData = ['secret' => '', 'qrurl' => '', 'qrimage' => ''];
} else {
    // Only generate a new secret if no secret exists yet AND action is enable
    if ($action === 'enable' && empty($customer['mfasecret'])) {
        $mfaData = generateMFASecret($customer['email']); // sets mfasecret + mfaenabled=0
        $customer['mfasecret'] = $mfaData['secret'];
    } elseif (!empty($customer['mfasecret'])) {
        // Rebuild otpauth URL from existing secret for display
        $mfaData = buildMFASetupData($customer['email'], $customer['mfasecret']);
    } else {
        // No secret and action is disable: no QR to show
        $mfaData = ['secret' => '', 'qrurl' => '', 'qrimage' => ''];
    }
}

// Handle verification only when enabling
if ($action === 'enable' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifycode'])) {
    $code = trim($_POST['verifycode']);

    if (verifyTOTP($customer_Id, $code)) {
        // Enable MFA + generate 10 backup codes
        $backupCodes = [];
        $backupCodeHashes = [];
        for ($i = 0; $i < 10; $i++) {
            $plainCode = strtoupper(bin2hex(random_bytes(4)));
            $backupCodes[] = $plainCode;
            $backupCodeHashes[] = password_hash($plainCode, PASSWORD_DEFAULT);
        }
        $jsonBackup = json_encode($backupCodeHashes);

        if ($jsonBackup === false) {
            $error = 'Failed to generate backup codes. Please try again.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE customer
                 SET mfaenabled = 1, mfabackupcodes = ?
                 WHERE customer_id = ?"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $jsonBackup, $customer_Id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'MFA enabled! Save your backup codes securely. They are shown only once.';
                    $newBackupCodes = $backupCodes;
                } else {
                    $error = 'Failed to save MFA backup codes.';
                }
            } else {
                $error = 'Failed to prepare MFA update.';
            }
        }
    } else {
        $error = 'Invalid code. Ensure your phone time is correct.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup MFA - ScanFit</title>
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
        .backup-codes {
            background: #fff3cd;
            padding: 1.5rem;
            border-radius: 12px;
            border-left: 5px solid #ffc107;
            margin-bottom: 1.5rem;
        }
        .backup-codes code {
            display: inline-block;
            margin: 0.2rem 0.3rem;
            padding: 0.5rem 0.8rem;
            background: #ffc107;
            color: #212529;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Two-Factor Authentication</h1>

        <?php if ($success): ?>
            <div class="success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($action === 'enable' && empty($customer['mfaenabled'])): ?>
            <div class="qr-container">
                <h3>Step 1: Add Account in Authenticator</h3>
                <p>Open <strong>Google Authenticator</strong>, <strong>Authy</strong>, or <strong>Microsoft Authenticator</strong></p>

                <?php if (!empty($mfaData['qrurl'])): ?>
                    <?php if (!empty($mfaData['qrimage'])): ?>
                        <img class="qr-code" src="<?php echo htmlspecialchars($mfaData['qrimage']); ?>" alt="ScanFit MFA QR code">
                    <?php endif; ?>
                    <p><small>Manual entry secret: <code><?php echo htmlspecialchars($mfaData['secret']); ?></code></small></p>
                    <p style="margin-top:0.8rem;">
                        <small>OTP URI:</small><br>
                        <code style="display:inline-block;word-break:break-all;"><?php echo htmlspecialchars($mfaData['qrurl']); ?></code>
                    </p>
                <?php else: ?>
                    <p>Unable to generate MFA secret. Please refresh this page.</p>
                <?php endif; ?>
            </div>

            <form method="POST">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="enable">
                <h3>Step 2: Verify Code</h3>
                <p>Enter the <strong>6-digit code</strong> from your authenticator app</p>
                <input type="text" name="verifycode" maxlength="6" placeholder="123456" required autofocus>
                <button type="submit">✅ Enable Two-Factor Authentication</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($newBackupCodes)): ?>
            <div class="backup-codes">
                <h3>🛡️ Your Backup Codes</h3>
                <em>Print or save these securely now. Each code works <strong>once</strong> and will not be shown again.</em>
                <div style="line-height: 2; margin-top: 1rem;">
                    <?php foreach ($newBackupCodes as $code): ?>
                        <code><?php echo htmlspecialchars($code); ?></code>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="nav-links">
    <a href="settings.php">⚙️ Settings</a>
    <a href="index.php">🏠 Home</a>
    <a href="logout.php">🚪 Logout</a>
</div>

    </div>
</body>
</html>
