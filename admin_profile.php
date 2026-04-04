<?php
// admin_profile.php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);
global $conn;

$admin = getCurrentAdmin();
if (!$admin) {
    header('Location: admin_login.php');
    exit();
}

$error   = null;
$success = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('admin_profile.php');
    $adminId = (int)$admin['admin_id'];
    $currentRole = $_SESSION['admin_role'] ?? 'ADMIN';

    // MFA enable
    if (isset($_POST['action']) && $_POST['action'] === 'enable_mfa') {
        $secret = generateTOTPSecret();
        if (enableAdminMFA($adminId, $secret)) {
            $_SESSION['admin_mfa_secret'] = $secret;
            $_SESSION['admin_mfa_email']  = $admin['email'];
            header('Location: admin_mfa_setup.php');
            exit();
        } else {
            $error = 'Failed to enable MFA.';
        }
    }

    // MFA disable
    if (isset($_POST['action']) && $_POST['action'] === 'disable_mfa') {
        if (disableAdminMFA($adminId)) {
            $success = 'MFA has been disabled for this admin account.';
            $admin   = getCurrentAdmin();
        } else {
            $error = 'Failed to disable MFA.';
        }
    }

    // Create new admin account
    if (isset($_POST['action']) && $_POST['action'] === 'create_admin') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $newEmail    = trim($_POST['new_email'] ?? '');
        $newPassword = $_POST['new_password'] ?? '';
        $newConfirm  = $_POST['new_confirm_password'] ?? '';
        $newRoleReq  = $_POST['new_role'] ?? 'MODERATOR';

        $allowedRoles = ['MODERATOR', 'ADMIN', 'SUPER_ADMIN'];
        if (!in_array($newRoleReq, $allowedRoles, true)) {
            $newRoleReq = 'MODERATOR';
        }

        if ($currentRole !== 'SUPER_ADMIN' && $newRoleReq !== 'MODERATOR') {
            $error = 'Only SUPER_ADMIN can create ADMIN or SUPER_ADMIN accounts.';
        } elseif ($newUsername === '' || $newEmail === '' || $newPassword === '') {
            $error = 'Username, email and password are required to create an account.';
        } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid email address for the new account.';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New admin password must be at least 8 characters.';
        } elseif ($newPassword !== $newConfirm) {
            $error = 'New admin password confirmation does not match.';
        } else {
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $insertSql = "INSERT INTO admin (username, email, password_hash, role, mfa_enabled) VALUES (?, ?, ?, ?, 0)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            if ($insertStmt) {
                mysqli_stmt_bind_param($insertStmt, 'ssss', $newUsername, $newEmail, $newPasswordHash, $newRoleReq);
                if (mysqli_stmt_execute($insertStmt)) {
                    $success = 'Admin account created successfully.';
                } else {
                    $error = 'Unable to create admin account (username/email may already exist).';
                }
            } else {
                $error = 'Database error: could not prepare admin account creation.';
            }
        }
    }

    // Create new customer account
    if (isset($_POST['action']) && $_POST['action'] === 'create_customer') {
        $custFirst   = trim($_POST['cust_first_name'] ?? '');
        $custLast    = trim($_POST['cust_last_name'] ?? '');
        $custEmail   = trim($_POST['cust_email'] ?? '');
        $custPhone   = trim($_POST['cust_phone'] ?? '');
        $custPass    = $_POST['cust_password'] ?? '';
        $custConfirm = $_POST['cust_confirm_password'] ?? '';

        if ($custFirst === '' || $custLast === '' || $custEmail === '' || $custPass === '') {
            $error = 'Customer first name, last name, email, and password are required.';
        } elseif (!filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please provide a valid customer email address.';
        } elseif (strlen($custPass) < 8) {
            $error = 'Customer password must be at least 8 characters.';
        } elseif ($custPass !== $custConfirm) {
            $error = 'Customer password confirmation does not match.';
        } else {
            $custHash = password_hash($custPass, PASSWORD_BCRYPT);
            $insertCustomerSql = "
                INSERT INTO customer
                (first_name, last_name, email, phone, password_hash, mfaenabled, password_reset_required, failed_login_attempts, account_locked, account_blocked, created_at)
                VALUES (?, ?, ?, ?, ?, 0, 1, 0, 0, 0, NOW())
            ";
            $insertCustomerStmt = mysqli_prepare($conn, $insertCustomerSql);
            if ($insertCustomerStmt) {
                mysqli_stmt_bind_param(
                    $insertCustomerStmt,
                    'sssss',
                    $custFirst,
                    $custLast,
                    $custEmail,
                    $custPhone,
                    $custHash
                );
                if (mysqli_stmt_execute($insertCustomerStmt)) {
                    $success = 'Customer account created. Password is temporary and will require reset at first login.';
                } else {
                    $error = 'Unable to create customer account (email may already exist).';
                }
            } else {
                $error = 'Database error: could not prepare customer account creation.';
            }
        }
    }

    // Profile update (only when no MFA action set)
    if (!isset($_POST['action'])) {
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($username === '' || $email === '') {
            $error = 'Username and email are required';
        } elseif ($password !== '' && $password !== $confirm) {
            $error = 'Passwords do not match';
        } else {
            // Start with base update (username + email)
            $params = [$username, $email, $adminId];
            $types  = 'ssi';
            $sql    = "UPDATE admin SET username = ?, email = ?";

            // If password provided, include password_hash
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $sql   .= ", password_hash = ?";
                $params = [$username, $email, $password_hash, $adminId];
                $types  = 'sssi';
            }

            $sql .= " WHERE admin_id = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, $types, ...$params);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Profile updated successfully';

                    // Refresh admin data + session username
                    $admin = getCurrentAdmin();
                    $_SESSION['admin_name'] = $admin['username'];
                } else {
                    $error = 'Update failed (username or email may already be in use)';
                }
            } else {
                $error = 'Database error: could not prepare statement';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Profile - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Segoe UI',sans-serif;
            background:#0f172a;
            color:#e5e7eb;
            padding:2rem;
        }
        a{text-decoration:none;color:#38bdf8}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;font-size:.85rem;color:#9ca3af}
        h1{font-size:1.8rem;margin-bottom:1rem}
        .card{
            max-width:600px;
            background:#020617;
            border-radius:16px;
            padding:1.8rem;
            border:1px solid #111827;
        }
        .form-group{margin-bottom:1rem}
        label{
            display:block;
            margin-bottom:.25rem;
            font-size:.9rem;
            color:#9ca3af;
        }
        input[type=text],input[type=email],input[type=password]{
            width:100%;
            padding:.6rem .7rem;
            border-radius:8px;
            border:1px solid #1f2937;
            background:#020617;
            color:#e5e7eb;
            font-size:.9rem;
        }
        .btn{
            padding:.6rem 1.4rem;
            border:none;
            border-radius:999px;
            font-size:.9rem;
            cursor:pointer;
            font-weight:600;
        }
        .btn-primary{background:#38bdf8;color:#0f172a}
        .btn-secondary{background:#111827;color:#e5e7eb;margin-left:.6rem}
        .msg{
            margin-bottom:1rem;
            padding:.7rem .9rem;
            border-radius:10px;
            font-size:.85rem;
        }
        .msg-error{background:#b91c1c;color:#fee2e2}
        .msg-success{background:#14532d;color:#bbf7d0}
        .hint{font-size:.8rem;color:#6b7280;margin-top:.25rem}
        .mfa-section{
            margin-top:1.5rem;
            padding-top:1rem;
            border-top:1px solid #1f2937;
        }
        .mfa-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            margin-bottom:.75rem;
        }
        .mfa-title{
            font-size:.95rem;
            font-weight:600;
            color:#e5e7eb;
        }
        .badge{
            display:inline-block;
            padding:.25rem .7rem;
            border-radius:999px;
            font-size:.75rem;
            font-weight:600;
        }
        .badge-on{
            background:#14532d;
            color:#bbf7d0;
        }
        .badge-off{
            background:#374151;
            color:#e5e7eb;
        }
        .mfa-description{
            font-size:.8rem;
            color:#9ca3af;
        }
        .divider{
            margin-top:1.5rem;
            padding-top:1rem;
            border-top:1px solid #1f2937;
        }
    </style>
</head>
<body>
<div class="nav">
    <a href="admin_dashboard.php">← Back to dashboard</a>
</div>

<h1>Admin Profile</h1>

<div class="card">
    <?php if ($error): ?>
        <div class="msg msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="msg msg-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfInput(); ?>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username"
                   value="<?php echo htmlspecialchars($admin['username']); ?>" required>
        </div>

        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email"
                   value="<?php echo htmlspecialchars($admin['email']); ?>" required>
        </div>

        <div class="form-group">
            <label>New password (optional)</label>
            <input type="password" name="password" autocomplete="new-password">
            <div class="hint">Leave blank to keep current password</div>
        </div>

        <div class="form-group">
            <label>Confirm new password</label>
            <input type="password" name="confirm_password" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary">Save profile</button>
        <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
    </form>

    <div class="mfa-section">
        <div class="mfa-header">
            <div class="mfa-title">Multi-Factor Authentication (MFA)</div>
            <div>
                <?php if (!empty($admin['mfa_enabled']) && !empty($admin['mfa_secret'])): ?>
                    <span class="badge badge-on">Enabled</span>
                <?php else: ?>
                    <span class="badge badge-off">Disabled</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="mfa-description">
            Protect your admin account with a time-based one-time password (TOTP) app such as Google Authenticator or Authy.
        </div>

        <?php if (!empty($admin['mfa_enabled']) && !empty($admin['mfa_secret'])): ?>
            <form method="POST" style="margin-top:1rem;">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="disable_mfa">
                <button type="submit" class="btn btn-secondary">Disable MFA</button>
            </form>
        <?php else: ?>
            <form method="POST" style="margin-top:1rem;">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="enable_mfa">
                <button type="submit" class="btn btn-primary">Enable MFA</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="divider">
        <div class="mfa-header">
            <div class="mfa-title">Create Account</div>
            <div>
                <span class="badge badge-off">Admin Tools</span>
            </div>
        </div>
        <div class="mfa-description">
            Create a new admin/staff account. ADMIN can create MODERATOR only; SUPER_ADMIN can create any role.
        </div>

        <form method="POST" style="margin-top:1rem;">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="create_admin">

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="new_username" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="new_email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="new_password" autocomplete="new-password" required>
                <div class="hint">Minimum 8 characters</div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="new_confirm_password" autocomplete="new-password" required>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="new_role" style="width:100%;padding:.6rem .7rem;border-radius:8px;border:1px solid #1f2937;background:#020617;color:#e5e7eb;font-size:.9rem;">
                    <option value="MODERATOR">MODERATOR</option>
                    <option value="ADMIN" <?php echo (($_SESSION['admin_role'] ?? '') !== 'SUPER_ADMIN') ? 'disabled' : ''; ?>>ADMIN</option>
                    <option value="SUPER_ADMIN" <?php echo (($_SESSION['admin_role'] ?? '') !== 'SUPER_ADMIN') ? 'disabled' : ''; ?>>SUPER_ADMIN</option>
                </select>
            </div>

            <button type="submit" class="btn btn-primary">Create Account</button>
        </form>
    </div>

    <div class="divider">
        <div class="mfa-header">
            <div class="mfa-title">Create Customer Account</div>
            <div>
                <span class="badge badge-off">Storefront User</span>
            </div>
        </div>
        <div class="mfa-description">
            Create a customer account directly from admin profile. The password set here is temporary.
        </div>

        <form method="POST" style="margin-top:1rem;">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="create_customer">

            <div class="form-group">
                <label>First Name</label>
                <input type="text" name="cust_first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" name="cust_last_name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="cust_email" required>
            </div>
            <div class="form-group">
                <label>Phone (optional)</label>
                <input type="text" name="cust_phone">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="cust_password" autocomplete="new-password" required>
                <div class="hint">Minimum 8 characters</div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="cust_confirm_password" autocomplete="new-password" required>
            </div>

            <button type="submit" class="btn btn-primary">Create Customer</button>
        </form>
    </div>
</div>
</body>
</html>
