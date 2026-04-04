<?php
// customers_admin.php

require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR']);
global $conn;

$adminRole = $_SESSION['admin_role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['customer_id'])) {
    requireCsrfPost('customers_admin.php');

    if (!in_array($adminRole, ['SUPER_ADMIN', 'ADMIN'], true)) {
        $_SESSION['error'] = 'Only ADMIN or SUPER_ADMIN can perform account security actions.';
        header('Location: customers_admin.php');
        exit();
    }

    $action = $_POST['action'];
    $customerId = (int)$_POST['customer_id'];

    if ($customerId <= 0) {
        $_SESSION['error'] = 'Invalid customer selected.';
        header('Location: customers_admin.php');
        exit();
    }

    if ($action === 'unlock') {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET account_locked = 0, failed_login_attempts = 0
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Account unlocked.';
        } else {
            $_SESSION['error'] = 'Failed to unlock account.';
        }
    } elseif ($action === 'block') {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET account_blocked = 1,
                 account_locked = 1
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Account blocked.';
        } else {
            $_SESSION['error'] = 'Failed to block account.';
        }
    } elseif ($action === 'unblock') {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET account_blocked = 0,
                 account_locked = 0,
                 failed_login_attempts = 0
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Account unblocked.';
        } else {
            $_SESSION['error'] = 'Failed to unblock account.';
        }
    } elseif ($action === 'delete_account') {
        $stmt = mysqli_prepare(
            $conn,
            "DELETE FROM customer WHERE customer_id = ? LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) === 1) {
                $_SESSION['success'] = 'Customer account deleted.';
            } else {
                $_SESSION['error'] = 'Could not delete this account (it may be referenced by existing orders).';
            }
        } else {
            $_SESSION['error'] = 'Failed to prepare account deletion.';
        }
    } elseif ($action === 'set_temp_password') {
        $tempPassword = $_POST['temp_password'] ?? '';
        if (strlen($tempPassword) < 8) {
            $_SESSION['error'] = 'Temporary password must be at least 8 characters.';
            header('Location: customers_admin.php');
            exit();
        }

        $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET password_hash = ?, password_reset_required = 1, account_locked = 0, failed_login_attempts = 0
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $passwordHash, $customerId);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Temporary password set. User will be forced to change password on next login.';
        } else {
            $_SESSION['error'] = 'Failed to set temporary password.';
        }
    } elseif ($action === 'reset_security') {
        $stmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET account_locked = 0,
                 account_blocked = 0,
                 failed_login_attempts = 0,
                 password_reset_required = 0,
                 mfasecret = NULL,
                 mfaenabled = 0,
                 mfabackupcodes = NULL
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Customer security settings reset.';
        } else {
            $_SESSION['error'] = 'Failed to reset customer security settings.';
        }
    }

    header('Location: customers_admin.php');
    exit();
}

$successMsg = $_SESSION['success'] ?? null;
$errorMsg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$sql = "
    SELECT
        customer_id,
        first_name,
        last_name,
        email,
        phone,
        created_at,
        failed_login_attempts,
        account_locked,
        account_blocked,
        password_reset_required
    FROM customer
    ORDER BY created_at DESC
";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Customers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e5e7eb;padding:2rem}
        a{text-decoration:none;color:#38bdf8}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;font-size:.85rem;color:#9ca3af}
        .nav a{color:#38bdf8}
        h1{font-size:1.8rem;margin-bottom:1rem}
        table{width:100%;border-collapse:collapse;background:#020617;border-radius:12px;overflow:hidden}
        th,td{padding:.75rem 1rem;font-size:.9rem;border-bottom:1px solid #111827;text-align:left}
        th{background:#020617;font-weight:600;color:#9ca3af}
        tr:nth-child(even){background:#020617}
    </style>
</head>
<body>
<div class="nav">
    <a href="admin_dashboard.php">← Dashboard</a>
</div>

<h1>Customers</h1>

<?php if ($successMsg): ?>
    <div style="background:#14532d;color:#bbf7d0;padding:.7rem .9rem;border-radius:10px;margin-bottom:1rem;">
        <?php echo htmlspecialchars($successMsg); ?>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div style="background:#7f1d1d;color:#fecaca;padding:.7rem .9rem;border-radius:10px;margin-bottom:1rem;">
        <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php endif; ?>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Failed Attempts</th>
        <th>Locked</th>
        <th>Blocked</th>
        <th>Force Reset</th>
        <th>Created</th>
        <th>Account Controls</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?php echo (int)$row['customer_id']; ?></td>
            <td><?php echo htmlspecialchars(trim(((string)($row['first_name'] ?? '')) . ' ' . ((string)($row['last_name'] ?? '')))); ?></td>
            <td><?php echo htmlspecialchars((string)($row['email'] ?? '')); ?></td>
            <td><?php echo htmlspecialchars((string)($row['phone'] ?? '')); ?></td>
            <td><?php echo (int)$row['failed_login_attempts']; ?></td>
            <td><?php echo (int)$row['account_locked'] === 1 ? 'YES' : 'NO'; ?></td>
            <td><?php echo (int)$row['account_blocked'] === 1 ? 'YES' : 'NO'; ?></td>
            <td><?php echo (int)$row['password_reset_required'] === 1 ? 'YES' : 'NO'; ?></td>
            <td><?php echo htmlspecialchars((string)($row['created_at'] ?? '')); ?></td>
            <td>
                <?php if (in_array($adminRole, ['SUPER_ADMIN', 'ADMIN'], true)): ?>
                    <form method="POST" style="display:inline-block;margin:0 .3rem .3rem 0;">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="action" value="unlock">
                        <input type="hidden" name="customer_id" value="<?php echo (int)$row['customer_id']; ?>">
                        <button type="submit" style="background:#1d4ed8;color:#fff;border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">Unlock</button>
                    </form>

                    <?php if ((int)$row['account_blocked'] === 1): ?>
                        <form method="POST" style="display:inline-block;margin:0 .3rem .3rem 0;">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="action" value="unblock">
                            <input type="hidden" name="customer_id" value="<?php echo (int)$row['customer_id']; ?>">
                            <button type="submit" style="background:#065f46;color:#fff;border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">Unblock</button>
                        </form>
                    <?php else: ?>
                        <form method="POST" style="display:inline-block;margin:0 .3rem .3rem 0;">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="action" value="block">
                            <input type="hidden" name="customer_id" value="<?php echo (int)$row['customer_id']; ?>">
                            <button type="submit" style="background:#7c2d12;color:#fff;border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">Block</button>
                        </form>
                    <?php endif; ?>

                    <form method="POST" style="display:inline-block;margin:0 .3rem .3rem 0;">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="action" value="set_temp_password">
                        <input type="hidden" name="customer_id" value="<?php echo (int)$row['customer_id']; ?>">
                        <input type="text" name="temp_password" placeholder="Temp password" minlength="8" required
                               style="padding:.35rem .5rem;border-radius:8px;border:1px solid #374151;background:#0f172a;color:#e5e7eb;width:140px;">
                        <button type="submit" style="background:#6d28d9;color:#fff;border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">Set Temp</button>
                    </form>

                    <form method="POST" style="display:inline-block;margin:0 .3rem .3rem 0;"
                          onsubmit="return confirm('Reset security settings for this customer?');">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="action" value="reset_security">
                        <input type="hidden" name="customer_id" value="<?php echo (int)$row['customer_id']; ?>">
                        <button type="submit" style="background:#b91c1c;color:#fff;border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">Reset Security</button>
                    </form>

                    <form method="POST" style="display:inline-block;margin:0 .3rem .3rem 0;"
                          onsubmit="return confirm('Delete this customer account? This is permanent.');">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="action" value="delete_account">
                        <input type="hidden" name="customer_id" value="<?php echo (int)$row['customer_id']; ?>">
                        <button type="submit" style="background:#991b1b;color:#fff;border:none;border-radius:8px;padding:.35rem .6rem;cursor:pointer;">Delete</button>
                    </form>
                <?php else: ?>
                    <span style="color:#9ca3af;">View only</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
