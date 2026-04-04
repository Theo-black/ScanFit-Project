<?php
require_once 'functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$userId = (int)($_SESSION['force_reset_user_id'] ?? 0);
if ($userId <= 0) {
    header('Location: login.php');
    exit();
}

global $conn;

$stmt = mysqli_prepare(
    $conn,
    "SELECT customer_id, first_name, email, password_reset_required, account_blocked
     FROM customer
     WHERE customer_id = ?
     LIMIT 1"
);
if (!$stmt) {
    header('Location: login.php');
    exit();
}
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$customer = mysqli_fetch_assoc($res);

if (!$customer || (int)$customer['password_reset_required'] !== 1) {
    unset($_SESSION['force_reset_user_id'], $_SESSION['pending_force_password_reset'], $_SESSION['pending_mfa_user_id']);
    header('Location: login.php');
    exit();
}
if ((int)$customer['account_blocked'] === 1) {
    unset($_SESSION['force_reset_user_id'], $_SESSION['pending_force_password_reset'], $_SESSION['pending_mfa_user_id']);
    $_SESSION['error'] = 'Your account has been blocked by an administrator.';
    header('Location: login.php');
    exit();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('force_password_reset.php');

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $confirmPassword === '') {
        $error = 'Both password fields are required.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $updateStmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET password_hash = ?, password_reset_required = 0, failed_login_attempts = 0, account_locked = 0
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'si', $passwordHash, $userId);
            if (mysqli_stmt_execute($updateStmt)) {
                session_regenerate_id(true);
                $_SESSION['customer_id'] = $userId;
                $_SESSION['success'] = 'Password updated successfully.';
                unset($_SESSION['force_reset_user_id'], $_SESSION['pending_force_password_reset'], $_SESSION['pending_mfa_user_id']);
                header('Location: index.php');
                exit();
            } else {
                $error = 'Unable to save your new password. Please try again.';
            }
        } else {
            $error = 'Unable to update password at this time.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1d3557 0%, #457b9d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.2);
        }
        h1 {
            font-size: 1.6rem;
            margin-bottom: 0.5rem;
            color: #1f2937;
        }
        p {
            color: #4b5563;
            margin-bottom: 1.2rem;
            line-height: 1.5;
        }
        .field {
            margin-bottom: 1rem;
        }
        label {
            display: block;
            margin-bottom: 0.35rem;
            font-size: 0.9rem;
            color: #374151;
            font-weight: 600;
        }
        input[type="password"] {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 10px;
            font-size: 0.95rem;
        }
        .btn {
            width: 100%;
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
        }
        .error {
            margin-bottom: 1rem;
            background: #fee2e2;
            color: #991b1b;
            padding: 0.7rem 0.9rem;
            border-radius: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>Password Reset Required</h1>
    <p>
        Hi <?php echo htmlspecialchars($customer['first_name']); ?>.
        For security, you must set a new password before continuing.
    </p>

    <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfInput(); ?>
        <div class="field">
            <label for="new_password">New Password</label>
            <input type="password" id="new_password" name="new_password" minlength="8" required>
        </div>
        <div class="field">
            <label for="confirm_password">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Save New Password</button>
    </form>
</div>
</body>
</html>
