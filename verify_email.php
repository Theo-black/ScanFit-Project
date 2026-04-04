<?php
require_once 'functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$token = trim((string)($_GET['token'] ?? ''));
$type = trim((string)($_GET['type'] ?? 'customer'));
$error = null;
$success = null;

if ($token === '') {
    $error = 'Verification link is missing.';
} else {
    if ($type === 'google_signup') {
        $customerId = completePendingGoogleSignupByToken($token, $error);
        if ($customerId) {
            $success = 'Your email has been verified and your account has been created. You can now log in.';
        }
    } elseif ($type === 'manual_signup') {
        $customerId = completePendingCustomerSignupByToken($token, $error);
        if ($customerId) {
            $success = 'Your email has been verified and your account has been created. You can now log in.';
        }
    } else {
        $customerId = verifyCustomerEmailByToken($token, $error);
        if ($customerId) {
            $success = 'Your email has been verified. You can now log in.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Verify Email - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            width: 100%;
            max-width: 480px;
            background: #fff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.12);
            text-align: center;
        }
        .message {
            padding: 1rem;
            border-radius: 12px;
            margin: 1rem 0;
        }
        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }
        .message.success {
            background: #dcfce7;
            color: #166534;
        }
        a.button {
            display: inline-block;
            margin-top: 1rem;
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            background: #667eea;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Verify Email</h1>
        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <a class="button" href="login.php">Go to Login</a>
    </div>
</body>
</html>
