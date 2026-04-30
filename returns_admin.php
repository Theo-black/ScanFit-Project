<?php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR']);

$validStatuses = ['REQUESTED', 'APPROVED', 'REJECTED', 'RECEIVED', 'REFUNDED'];
$filterStatus = strtoupper((string)($_GET['status'] ?? ''));
if (!in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('returns_admin.php');
    $returnId = (int)($_POST['return_id'] ?? 0);
    $status = strtoupper((string)($_POST['status'] ?? ''));
    $notes = (string)($_POST['admin_notes'] ?? '');
    if (updateReturnRequestStatus($returnId, $status, $notes)) {
        $_SESSION['success'] = 'Return request updated.';
    } else {
        $_SESSION['error'] = 'Unable to update return request.';
    }
    header('Location: returns_admin.php' . ($filterStatus !== '' ? '?status=' . urlencode($filterStatus) : ''));
    exit();
}

$successMsg = $_SESSION['success'] ?? null;
$errorMsg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
$returns = getAdminReturnRequests($filterStatus !== '' ? $filterStatus : null);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Returns - ScanFit Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e5e7eb;padding:2rem}
        a{color:#38bdf8;text-decoration:none}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;color:#9ca3af;font-size:.9rem}
        h1{font-size:1.8rem;margin-bottom:1rem}
        .panel{background:#020617;border:1px solid #1f2937;border-radius:14px;padding:1rem;margin-bottom:1rem}
        .filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem}
        .chip{border-radius:999px;padding:.4rem .75rem;background:#111827;color:#e5e7eb;font-weight:700;font-size:.85rem}
        .chip.active{background:#38bdf8;color:#0f172a}
        table{width:100%;border-collapse:collapse}
        th,td{padding:.75rem;border-top:1px solid #111827;text-align:left;vertical-align:top;font-size:.9rem}
        th{color:#9ca3af}
        select,textarea{width:100%;padding:.55rem .65rem;border-radius:8px;border:1px solid #1f2937;background:#0b1220;color:#e5e7eb}
        textarea{min-height:70px;resize:vertical;margin-top:.45rem}
        button{border:none;border-radius:999px;padding:.55rem .85rem;background:#38bdf8;color:#0f172a;font-weight:800;cursor:pointer;margin-top:.45rem}
        .msg{padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}
        .ok{background:#14532d;color:#bbf7d0}
        .err{background:#7f1d1d;color:#fecaca}
        .badge{display:inline-block;border-radius:999px;padding:.2rem .55rem;font-size:.75rem;font-weight:800;background:#312e81;color:#c7d2fe}
        .muted{color:#9ca3af;font-size:.82rem}
        @media(max-width:820px){
            table,thead,tbody,tr,th,td{display:block}
            th{display:none}
            td{padding:.65rem 0}
            tr{border-top:1px solid #111827;padding:.7rem 0}
        }
    </style>
</head>
<body>
<div class="nav"><a href="admin_dashboard.php">Dashboard</a> / Returns</div>
<h1>Returns</h1>
<?php if ($successMsg): ?><div class="msg ok"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="msg err"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

<div class="filters">
    <a class="chip <?php echo $filterStatus === '' ? 'active' : ''; ?>" href="returns_admin.php">All</a>
    <?php foreach ($validStatuses as $status): ?>
        <a class="chip <?php echo $filterStatus === $status ? 'active' : ''; ?>" href="returns_admin.php?status=<?php echo urlencode($status); ?>">
            <?php echo htmlspecialchars($status); ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="panel">
    <table>
        <thead>
        <tr>
            <th>Request</th>
            <th>Customer</th>
            <th>Reason</th>
            <th>Status</th>
            <th>Update</th>
        </tr>
        </thead>
        <tbody>
        <?php if (empty($returns)): ?>
            <tr><td colspan="5">No return requests found.</td></tr>
        <?php else: ?>
            <?php foreach ($returns as $request): ?>
                <tr>
                    <td>
                        <strong>#<?php echo (int)$request['return_id']; ?></strong>
                        <div><a href="order_view_admin.php?id=<?php echo (int)$request['order_id']; ?>">Order #<?php echo (int)$request['order_id']; ?></a></div>
                        <div class="muted"><?php echo htmlspecialchars((string)$request['created_at']); ?></div>
                        <div class="muted"><?php echo htmlspecialchars((string)($request['product_name'] ?? 'Entire order')); ?></div>
                    </td>
                    <td>
                        <?php echo htmlspecialchars(trim((string)$request['first_name'] . ' ' . (string)$request['last_name'])); ?><br>
                        <span class="muted"><?php echo htmlspecialchars((string)$request['email']); ?></span>
                    </td>
                    <td>
                        <?php echo nl2br(htmlspecialchars((string)$request['reason'])); ?>
                        <?php if (!empty($request['admin_notes'])): ?>
                            <div class="muted" style="margin-top:.45rem;">Note: <?php echo nl2br(htmlspecialchars((string)$request['admin_notes'])); ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge"><?php echo htmlspecialchars((string)$request['status']); ?></span></td>
                    <td>
                        <?php if (in_array($_SESSION['admin_role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true)): ?>
                            <form method="POST">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="return_id" value="<?php echo (int)$request['return_id']; ?>">
                                <select name="status" required>
                                    <?php foreach ($validStatuses as $status): ?>
                                        <option value="<?php echo $status; ?>" <?php echo $request['status'] === $status ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($status); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <textarea name="admin_notes" maxlength="1000" placeholder="Admin note"><?php echo htmlspecialchars((string)($request['admin_notes'] ?? '')); ?></textarea>
                                <button type="submit">Save</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">View only</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
