<?php
// orders_admin.php

require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR']);
global $conn;

$successMsg = $_SESSION['success'] ?? null;
$errorMsg   = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Fetch orders with customer name and totals
$sql = "
    SELECT o.*, c.first_name, c.last_name, c.email
    FROM `order` o
    JOIN customer c ON o.customer_id = c.customer_id
    ORDER BY o.order_date DESC
";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Orders</title>
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
        .badge{padding:.15rem .6rem;border-radius:999px;font-size:.75rem}
        .badge-pending{background:#f59e0b33;color:#fbbf24}
        .badge-processing{background:#0ea5e933;color:#22d3ee}
        .badge-shipped{background:#22c55e33;color:#4ade80}
        .badge-delivered{background:#16a34a33;color:#22c55e}
        .badge-cancelled{background:#dc262633;color:#f97373}
    </style>
</head>
<body>
<div class="nav">
    <a href="admin_dashboard.php">← Dashboard</a>
</div>

<h1>Orders</h1>

<?php if ($successMsg): ?>
    <div style="margin-bottom:1rem;padding:.8rem 1rem;background:#14532d;border-radius:8px;color:#bbf7d0;">
        <?php echo htmlspecialchars($successMsg); ?>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div style="margin-bottom:1rem;padding:.8rem 1rem;background:#7f1d1d;border-radius:8px;color:#fecaca;">
        <?php echo htmlspecialchars($errorMsg); ?>
    </div>
<?php endif; ?>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Customer</th>
        <th>Email</th>
        <th>Date</th>
        <th>Status</th>
        <th>Total (USD)</th>
        <th>Details</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td>#<?php echo (int)$row['order_id']; ?></td>
            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
            <td><?php echo htmlspecialchars($row['email']); ?></td>
            <td><?php echo htmlspecialchars($row['order_date']); ?></td>
            <td>
                <?php
                $status = $row['status'];
                $class  = 'badge-pending';
                if ($status === 'PROCESSING') $class = 'badge-processing';
                elseif ($status === 'SHIPPED') $class = 'badge-shipped';
                elseif ($status === 'DELIVERED') $class = 'badge-delivered';
                elseif ($status === 'CANCELLED') $class = 'badge-cancelled';
                ?>
                <span class="badge <?php echo $class; ?>"><?php echo htmlspecialchars($status); ?></span>
            </td>
            <td><?php echo number_format($row['total_amount'], 2); ?></td>
            <td><a href="order_view_admin.php?id=<?php echo (int)$row['order_id']; ?>">View</a></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
