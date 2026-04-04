<?php
require_once 'functions.php';

requireAdminRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR']);

$admin = getCurrentAdmin();
$unreadContactMessages = getUnreadContactMessageCount();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#0f172a;color:#e5e7eb;min-height:100vh}
        .topbar{
            background:#020617;padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;
            border-bottom:1px solid #111827
        }
        .brand{font-weight:800;font-size:1.4rem;color:#38bdf8}
        .topbar-actions{display:flex;align-items:center;gap:1rem}
        .notification-link{
            position:relative;display:inline-flex;align-items:center;justify-content:center;
            width:42px;height:42px;border-radius:999px;background:#111827;color:#e5e7eb;text-decoration:none;font-size:1.1rem
        }
        .notification-badge{
            position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 .35rem;border-radius:999px;
            background:#ef4444;color:#fff;font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center
        }
        .admin-info{font-size:.9rem;color:#9ca3af}
        .admin-info strong{color:#e5e7eb}
        .admin-info a{color:#f97373;text-decoration:none}
        .admin-info a:hover{text-decoration:underline}
        .container{max-width:1200px;margin:0 auto;padding:2rem}
        h1{font-size:2rem;margin-bottom:1rem}
        .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.5rem;margin-top:2rem}
        .card{
            background:#020617;border-radius:16px;padding:1.8rem;
            border:1px solid #1f2937;box-shadow:0 15px 40px rgba(0,0,0,.4)
        }
        .card-title{font-size:1rem;margin-bottom:.4rem;color:#9ca3af}
        .card-value{font-size:1.6rem;font-weight:700;color:#e5e7eb}
        .card-link{font-size:.85rem;margin-top:1rem}
        .card-link a{color:#38bdf8;text-decoration:none}
        .card-link a:hover{text-decoration:underline}
        @media(max-width:768px){
            .topbar{flex-direction:column;align-items:flex-start;gap:.85rem}
            .topbar-actions{width:100%;justify-content:space-between}
        }
    </style>
</head>
<body>
<div class="topbar">
    <div class="brand">SCANFIT ADMIN</div>
    <div class="topbar-actions">
        <a class="notification-link" href="admin_messages.php" aria-label="Contact messages">
            &#128276;
            <?php if ($unreadContactMessages > 0): ?>
                <span class="notification-badge"><?php echo $unreadContactMessages > 99 ? '99+' : (int)$unreadContactMessages; ?></span>
            <?php endif; ?>
        </a>
        <div class="admin-info">
            Logged in as <strong><?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></strong>
            (<?php echo htmlspecialchars($admin['role'] ?? 'ADMIN'); ?>)
            | <a href="admin_logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <h1>Dashboard</h1>
    <p>Welcome to the admin dashboard.</p>

    <div class="cards">
        <div class="card">
            <div class="card-title">Products</div>
            <div class="card-value">Manage catalog</div>
            <div class="card-link"><a href="products_admin.php">Go to products -></a></div>
        </div>
        <div class="card">
            <div class="card-title">Orders</div>
            <div class="card-value">View all orders</div>
            <div class="card-link"><a href="orders_admin.php">Go to orders -></a></div>
        </div>
        <div class="card">
            <div class="card-title">Customers</div>
            <div class="card-value">Customer list</div>
            <div class="card-link"><a href="customers_admin.php">Go to customers -></a></div>
        </div>
        <div class="card">
            <div class="card-title">Messages</div>
            <div class="card-value"><?php echo (int)$unreadContactMessages; ?> unread</div>
            <div class="card-link"><a href="admin_messages.php">Open inbox -></a></div>
        </div>
        <div class="card">
            <div class="card-title">Profile</div>
            <div class="card-value">Admin settings</div>
            <div class="card-link"><a href="admin_profile.php">Edit profile -></a></div>
        </div>
    </div>
</div>
</body>
</html>
