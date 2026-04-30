<?php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);
global $conn;

$successMsg = $_SESSION['success'] ?? null;
$errorMsg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('coupons_admin.php');
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $code = normalizeCouponCode((string)($_POST['code'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $discountType = strtoupper(trim((string)($_POST['discount_type'] ?? 'PERCENT')));
            $discountValue = (float)($_POST['discount_value'] ?? 0);
            $startsAt = trim((string)($_POST['starts_at'] ?? ''));
            $endsAt = trim((string)($_POST['ends_at'] ?? ''));

            if ($code === '') {
                throw new Exception('Coupon code is required.');
            }
            if (!in_array($discountType, ['PERCENT', 'FIXED'], true)) {
                throw new Exception('Invalid discount type.');
            }
            if ($discountValue <= 0) {
                throw new Exception('Discount value must be greater than zero.');
            }
            if ($discountType === 'PERCENT' && $discountValue > 100) {
                throw new Exception('Percent discount cannot exceed 100.');
            }

            $startsAtDb = $startsAt !== '' ? str_replace('T', ' ', $startsAt) . ':00' : null;
            $endsAtDb = $endsAt !== '' ? str_replace('T', ' ', $endsAt) . ':00' : null;
            $active = isset($_POST['active']) ? 1 : 0;

            $stmt = mysqli_prepare(
                $conn,
                "INSERT INTO coupon (code, description, discount_type, discount_value, active, starts_at, ends_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            if (!$stmt) {
                throw new Exception('Could not prepare coupon create.');
            }
            mysqli_stmt_bind_param($stmt, 'sssdiss', $code, $description, $discountType, $discountValue, $active, $startsAtDb, $endsAtDb);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Could not save coupon. The code may already exist.');
            }
            $_SESSION['success'] = 'Coupon created.';
        } elseif ($action === 'toggle') {
            $couponId = (int)($_POST['coupon_id'] ?? 0);
            $active = (int)($_POST['active'] ?? 0);
            if ($couponId <= 0) {
                throw new Exception('Invalid coupon.');
            }

            $stmt = mysqli_prepare($conn, "UPDATE coupon SET active = ? WHERE coupon_id = ?");
            if (!$stmt) {
                throw new Exception('Could not prepare coupon update.');
            }
            mysqli_stmt_bind_param($stmt, 'ii', $active, $couponId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Could not update coupon.');
            }
            $_SESSION['success'] = $active ? 'Coupon activated.' : 'Coupon deactivated.';
        } else {
            throw new Exception('Invalid coupon action.');
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: coupons_admin.php');
    exit();
}

$coupons = mysqli_query($conn, "SELECT * FROM coupon ORDER BY active DESC, created_at DESC, code ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Coupons - ScanFit Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e5e7eb;padding:2rem}
        a{color:#38bdf8;text-decoration:none}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;color:#9ca3af;font-size:.9rem}
        h1{font-size:1.8rem;margin-bottom:1rem}
        .panel{background:#020617;border:1px solid #1f2937;border-radius:14px;padding:1rem;margin-bottom:1rem}
        form.coupon-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end}
        label{display:grid;gap:.35rem;color:#9ca3af;font-size:.85rem}
        input,select{width:100%;padding:.6rem .7rem;border-radius:8px;border:1px solid #1f2937;background:#0b1220;color:#e5e7eb}
        .check{display:flex;align-items:center;gap:.45rem;color:#e5e7eb}
        .check input{width:auto}
        button{border:none;border-radius:999px;padding:.6rem .9rem;background:#38bdf8;color:#0f172a;font-weight:800;cursor:pointer;width:max-content}
        .danger{background:#ef4444;color:#fff}
        table{width:100%;border-collapse:collapse}
        th,td{padding:.65rem;border-top:1px solid #111827;text-align:left;font-size:.9rem;vertical-align:middle}
        th{color:#9ca3af}
        .msg{padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}
        .ok{background:#14532d;color:#bbf7d0}
        .err{background:#7f1d1d;color:#fecaca}
        .badge{display:inline-block;border-radius:999px;padding:.2rem .55rem;font-size:.75rem;font-weight:800}
        .active{background:#14532d;color:#bbf7d0}
        .inactive{background:#374151;color:#d1d5db}
        .inline{display:inline}
    </style>
</head>
<body>
<div class="nav"><a href="admin_dashboard.php">Back to Dashboard</a> / Coupons</div>
<h1>Coupons</h1>
<?php if ($successMsg): ?><div class="msg ok"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="msg err"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

<div class="panel">
    <form method="POST" class="coupon-form">
        <?php echo csrfInput(); ?>
        <input type="hidden" name="action" value="create">
        <label>Code
            <input type="text" name="code" placeholder="SUMMER10" required>
        </label>
        <label>Description
            <input type="text" name="description" placeholder="Optional note">
        </label>
        <label>Type
            <select name="discount_type">
                <option value="PERCENT">Percent</option>
                <option value="FIXED">Fixed amount</option>
            </select>
        </label>
        <label>Value
            <input type="number" name="discount_value" min="0.01" step="0.01" required>
        </label>
        <label>Starts
            <input type="datetime-local" name="starts_at">
        </label>
        <label>Ends
            <input type="datetime-local" name="ends_at">
        </label>
        <label class="check">
            <input type="checkbox" name="active" checked> Active
        </label>
        <button type="submit">Create Coupon</button>
    </form>
</div>

<div class="panel">
    <table>
        <tr>
            <th>Code</th>
            <th>Discount</th>
            <th>Window</th>
            <th>Status</th>
            <th>Action</th>
        </tr>
        <?php if (!$coupons || mysqli_num_rows($coupons) === 0): ?>
            <tr><td colspan="5">No coupons created yet.</td></tr>
        <?php else: ?>
            <?php while ($coupon = mysqli_fetch_assoc($coupons)): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars((string)$coupon['code']); ?></strong><br>
                        <span><?php echo htmlspecialchars((string)($coupon['description'] ?? '')); ?></span>
                    </td>
                    <td>
                        <?php if ($coupon['discount_type'] === 'FIXED'): ?>
                            $<?php echo number_format((float)$coupon['discount_value'], 2); ?>
                        <?php else: ?>
                            <?php echo rtrim(rtrim(number_format((float)$coupon['discount_value'], 2), '0'), '.'); ?>%
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo $coupon['starts_at'] ? htmlspecialchars((string)$coupon['starts_at']) : 'Any time'; ?>
                        <br>to
                        <?php echo $coupon['ends_at'] ? htmlspecialchars((string)$coupon['ends_at']) : 'No end'; ?>
                    </td>
                    <td>
                        <span class="badge <?php echo (int)$coupon['active'] === 1 ? 'active' : 'inactive'; ?>">
                            <?php echo (int)$coupon['active'] === 1 ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" class="inline">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="coupon_id" value="<?php echo (int)$coupon['coupon_id']; ?>">
                            <input type="hidden" name="active" value="<?php echo (int)$coupon['active'] === 1 ? 0 : 1; ?>">
                            <button type="submit" class="<?php echo (int)$coupon['active'] === 1 ? 'danger' : ''; ?>">
                                <?php echo (int)$coupon['active'] === 1 ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php endif; ?>
    </table>
</div>
</body>
</html>
