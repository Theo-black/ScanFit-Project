<?php
// admin_login.php

require_once 'functions.php';

// If already logged in as admin, go to admin dashboard
if (isAdminLoggedIn()) {
    header('Location: admin_dashboard.php');
    exit();
}

$error = $_SESSION['admin_error'] ?? ($_SESSION['error'] ?? null);
unset($_SESSION['admin_error'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('admin_login.php');
    global $conn;

    $usernameOrEmail = trim($_POST['username'] ?? '');
    $password        = $_POST['password'] ?? '';

    if ($usernameOrEmail === '' || $password === '') {
        $error = 'Please provide username/email and password';
    } else {
        // Allow login by username OR email
        $sql  = "SELECT * FROM admin WHERE username = ? OR email = ? LIMIT 1";
        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $usernameOrEmail, $usernameOrEmail);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $admin  = mysqli_fetch_assoc($result);
        } else {
            $admin = null;
        }

        if ($admin && password_verify($password, $admin['password_hash'])) {
            if (!empty($admin['mfa_enabled']) && !empty($admin['mfa_secret'])) {
                // MFA required
                $_SESSION['admin_mfa_pending'] = (int)$admin['admin_id'];
                header('Location: admin_mfa_verify.php');
                exit();
            } else {
                // No MFA, log in directly
                session_regenerate_id(true);
                $_SESSION['admin_id']   = $admin['admin_id'];
                $_SESSION['admin_name'] = $admin['username'];
                $_SESSION['admin_role'] = $admin['role'];
                header('Location: admin_dashboard.php');
                exit();
            }
        } else {
            $error = 'Invalid credentials';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - Scanfit</title>
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
            background:#111827;color:#e5e9f0;font-size:.95rem;outline:none;
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
    <h1>Admin Login</h1>
    <p class="subtitle">Sign in to manage products, orders, and users</p>

    <?php if ($error): ?>
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfInput(); ?>
        <div class="form-group">
            <label>Username or Email</label>
            <input type="text" name="username" placeholder="admin or admin@example.com"
                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" placeholder="Enter admin password" required>
        </div>
        <button type="submit" class="submit-btn">Login</button>
    </form>

    <div class="back-link">
        <a href="index.php">← Back to storefront</a>
    </div>
</div>
</body>
</html>
