<?php
// login.php

require_once 'functions.php';  // Includes Connectdb.php and new MFA helpers

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('login.php');

    if (isset($_POST['mfa_code'])) {
        // MFA verification step
        $userId = $_SESSION['pending_mfa_user_id'] ?? 0;
        $code = trim($_POST['mfa_code']);
        
        if (verifyTOTP($userId, $code) || verifyAndConsumeMFABackupCode($userId, $code)) {
            unset($_SESSION['pending_mfa_user_id']);
            if (!empty($_SESSION['pending_force_password_reset'])) {
                unset($_SESSION['pending_force_password_reset']);
                $_SESSION['force_reset_user_id'] = $userId;
                header('Location: force_password_reset.php');
                exit();
            }

            session_regenerate_id(true);
            $_SESSION['customer_id'] = $userId;
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid MFA or backup code. Try again.';
        }
    } else {
        unset($_SESSION['pending_force_password_reset']);

        // Password step
        $email = trim($_POST['email']);
        $password = $_POST['password'] ?? '';
        
        $stmt = mysqli_prepare($conn, "
            SELECT
                customer_id,
                password_hash,
                failed_login_attempts,
                account_locked,
                account_blocked,
                password_reset_required,
                email_verified
            FROM customer
            WHERE email = ?
            LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        if ($user && (int)$user['account_blocked'] === 1) {
            $error = 'Your account has been blocked by an administrator.';
        } elseif ($user && (int)$user['account_locked'] === 1) {
            $error = 'Your account is locked after too many login attempts. Please contact an admin.';
        } elseif ($user && (int)($user['email_verified'] ?? 0) !== 1) {
            $error = 'Please verify your email address before logging in.';
        } elseif ($user && password_verify($password, $user['password_hash'])) {
            $resetStmt = mysqli_prepare(
                $conn,
                "UPDATE customer SET failed_login_attempts = 0 WHERE customer_id = ? LIMIT 1"
            );
            if ($resetStmt) {
                mysqli_stmt_bind_param($resetStmt, 'i', $user['customer_id']);
                mysqli_stmt_execute($resetStmt);
            }

            if (requiresMFA($user['customer_id'])) {

                // Start MFA challenge
                $_SESSION['pending_mfa_user_id'] = $user['customer_id'];
                $_SESSION['pending_force_password_reset'] = (int)$user['password_reset_required'] === 1 ? 1 : 0;
            } else {
                if ((int)$user['password_reset_required'] === 1) {
                    $_SESSION['force_reset_user_id'] = (int)$user['customer_id'];
                    header('Location: force_password_reset.php');
                    exit();
                }

                // No MFA, direct login
                session_regenerate_id(true);
                $_SESSION['customer_id'] = $user['customer_id'];
                header('Location: index.php');
                exit();
            }
        } else {
            if ($user) {
                $attempts = ((int)$user['failed_login_attempts']) + 1;
                $locked = $attempts >= 3 ? 1 : 0;
                if ($attempts > 3) {
                    $attempts = 3;
                }

                $lockStmt = mysqli_prepare(
                    $conn,
                    "UPDATE customer
                     SET failed_login_attempts = ?, account_locked = ?
                     WHERE customer_id = ?
                     LIMIT 1"
                );
                if ($lockStmt) {
                    mysqli_stmt_bind_param($lockStmt, 'iii', $attempts, $locked, $user['customer_id']);
                    mysqli_stmt_execute($lockStmt);
                }

                if ($locked === 1) {
                    $error = 'Your account is now locked after 3 failed attempts. Please contact an admin.';
                } else {
                    $remaining = 3 - $attempts;
                    $error = 'Invalid email or password. ' . $remaining . ' attempt(s) remaining.';
                }
            } else {
                $error = 'Invalid email or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: linear-gradient(135deg, #667eee 0%, #7645a2 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .login-container {
        background: white;
        padding: 2rem;
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        width: 100%;
        max-width: 400px;
    }

    .form-group {
        margin-bottom: 1rem;          /* space between inputs */
    }

    .btn-primary {
        width: 100%;
        padding: 0.75rem;
        background: #667eee;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        margin-top: 0.5rem;           /* space above the button */
    }

    .google-btn {
        display: block;
        width: 100%;
        text-align: center;
        padding: 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        text-decoration: none;
        color: #111827;
        font-weight: 600;
        margin-top: 0.75rem;
        background: #ffffff;
    }

    .google-btn:hover {
        background: #f9fafb;
    }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if (isset($_SESSION['pending_mfa_user_id'])): ?>
            <!-- MFA Challenge Page -->
            <h2>Two-Factor Authentication</h2>
            <p>Enter your 6-digit authenticator code or one backup code.</p>
            <?php if ($error): ?><div style="color:red"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="POST">
                <?php echo csrfInput(); ?>
                <div class="form-group">
                    <label>MFA / Backup Code:</label>
                    <input type="text" name="mfa_code" maxlength="16" required style="width:100%; padding:0.75rem; border:1px solid #ddd; border-radius:8px;">
                </div>
                <button type="submit" class="btn-primary" style="width:100%; padding:0.75rem; background:#667eee; color:white; border:none; border-radius:8px; cursor:pointer;">Verify</button>
            </form>
            <p><a href="logout.php">Back to Login</a></p>
        <?php else: ?>

            
            <!-- Standard Password Login -->
           
<div style="text-align:center;margin-bottom:1.5rem;">
    <h2 style="
        margin:0 0 .25rem;
        font-size:2rem;
        color:#6366f1; /* violet accent */
        font-weight:800;
        letter-spacing:0.05em;
        font-family:'Poppins','Segoe UI',sans-serif;
    ">
        LOGIN
    </h2>
    <p style="
        margin:0;
        color:#9ca3af; /* soft gray */
        font-size:0.95rem;
        font-family:'Poppins','Segoe UI',sans-serif;
    ">
        Enter your details to access your ScanFit account
    </p>
</div>


            <?php if ($error): ?><div style="color:red"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
            <form method="POST">
                <?php echo csrfInput(); ?>
                <div class="form-group">
                    <label>Email:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required style="width:100%; padding:0.75rem; border:1px solid #ddd; border-radius:8px;">
                </div>
                <div class="form-group">
                    <label>Password:</label>
                    <input type="password" name="password" required style="width:100%; padding:0.75rem; border:1px solid #ddd; border-radius:8px;">
                </div>
                <button type="submit" class="btn-primary" style="width:100%; padding:0.75rem; background:#667eee; color:white; border:none; border-radius:8px; cursor:pointer;">Login</button>
                <?php if (isGoogleOAuthConfigured()): ?>
                    <a href="google_login.php?mode=login" class="google-btn">Continue with Google</a>
                <?php endif; ?>
            </form>

            <p style="margin-top:0.5rem;font-size:0.9rem;text-align:center;">
    New here? <a href="register.php" style="color:#333;text-decoration:underline;">Sign up</a>
    &nbsp;|&nbsp;
    Admin? <a href="admin_login.php" style="color:#333;text-decoration:underline;">Go to admin portal</a>
    &nbsp;|&nbsp;
    <a href="index.php" style="color:#6366f1;text-decoration:underline;">Back to storefront</a>
</p>


        <?php endif; ?>
    </div>
</body>
</html>
