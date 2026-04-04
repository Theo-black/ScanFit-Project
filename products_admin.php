<?php
//product_admin.php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);
global $conn;

// Handle activate/deactivate via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    requireCsrfPost('products_admin.php');
    $id = (int)$_POST['id'];
    if ($id > 0) {
        if ($_POST['action'] === 'deactivate') {
            $status = 'INACTIVE';
        } elseif ($_POST['action'] === 'activate') {
            $status = 'ACTIVE';
        } else {
            $status = null;
        }
        if ($status !== null) {
            $sql = "UPDATE product SET status = ? WHERE product_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $status, $id);
            mysqli_stmt_execute($stmt);
        }
    }
    header('Location: products_admin.php');
    exit();
}

// Fetch all products
$sql = "SELECT * FROM product ORDER BY created_at DESC";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Products</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e5e7eb;padding:2rem}
        a{text-decoration:none;color:#38bdf8}
        a:hover{text-decoration:underline}
        .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}
        .top h1{font-size:1.8rem}
        .btn{display:inline-block;padding:.4rem .8rem;border-radius:999px;font-size:.85rem}
        .btn-primary{background:#38bdf8;color:#0f172a}
        .btn-secondary{background:#111827;color:#e5e7eb}
        table{width:100%;border-collapse:collapse;margin-top:1rem;background:#020617;border-radius:12px;overflow:hidden}
        th,td{padding:.75rem 1rem;font-size:.9rem;border-bottom:1px solid #111827;text-align:left}
        th{background:#020617;font-weight:600;color:#9ca3af}
        tr:nth-child(even){background:#020617}
        .badge{padding:.15rem .6rem;border-radius:999px;font-size:.75rem}
        .badge-active{background:#16a34a33;color:#22c55e}
        .badge-inactive{background:#dc262633;color:#f97373}
        .status-actions a{margin-right:.5rem;font-size:.8rem}
        .nav{margin-bottom:1rem;font-size:.85rem;color:#9ca3af}
        .nav a{color:#38bdf8}
    </style>
</head>
<body>
<div class="nav">
    <a href="admin_dashboard.php">← Dashboard</a>
</div>

<div class="top">
    <h1>Products</h1>
    <div>
        <a href="product_add_admin.php" class="btn btn-primary">＋ Add Product</a>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th>ID</th>
        <th>Name</th>
        <th>SKU</th>
        <th>Base Price</th>
        <th>Status</th>
        <th>Created</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
        <tr>
            <td><?php echo (int)$row['product_id']; ?></td>
            <td><?php echo htmlspecialchars($row['name']); ?></td>
            <td><?php echo htmlspecialchars($row['sku']); ?></td>
            <td><?php echo number_format($row['base_price'], 2); ?></td>
            <td>
                <?php if ($row['status'] === 'ACTIVE'): ?>
                    <span class="badge badge-active">ACTIVE</span>
                <?php else: ?>
                    <span class="badge badge-inactive"><?php echo htmlspecialchars($row['status']); ?></span>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['created_at']); ?></td>

            <td class="status-actions">
    <a href="product_edit_admin.php?id=<?php echo (int)$row['product_id']; ?>">Edit</a>
    <a href="product_edit_admin.php?id=<?php echo (int)$row['product_id']; ?>#stock-section">Stock</a>
    <?php if ($row['status'] === 'ACTIVE'): ?>
        <form method="POST" style="display:inline;">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="deactivate">
            <input type="hidden" name="id" value="<?php echo (int)$row['product_id']; ?>">
            <button type="submit" style="background:none;border:none;color:#38bdf8;cursor:pointer;padding:0;">Deactivate</button>
        </form>
    <?php else: ?>
        <form method="POST" style="display:inline;">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="action" value="activate">
            <input type="hidden" name="id" value="<?php echo (int)$row['product_id']; ?>">
            <button type="submit" style="background:none;border:none;color:#38bdf8;cursor:pointer;padding:0;">Activate</button>
        </form>
    <?php endif; ?>
    <form action="product_delete_admin.php" method="POST" style="display:inline;" onsubmit="return confirm('Delete this product? This cannot be undone.');">
        <?php echo csrfInput(); ?>
        <input type="hidden" name="id" value="<?php echo (int)$row['product_id']; ?>">
        <button type="submit" style="background:none;border:none;color:#38bdf8;cursor:pointer;padding:0;">Delete</button>
    </form>
</td>



        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
</body>
</html>
