<?php
require_once 'functions.php';
requireLogin();

$customerId = getCustomerId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('wishlist.php');
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        setProductWishlist($customerId, $productId, false);
        $_SESSION['success'] = 'Removed from wishlist.';
    }
    header('Location: wishlist.php');
    exit();
}

$items = getCustomerWishlist($customerId);
$successMsg = $_SESSION['success'] ?? null;
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Wishlist - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Tahoma,sans-serif;background:#f8f9fa;color:#1f2937;min-height:100vh}
        .container{max-width:1100px;margin:0 auto;padding:3rem 2rem}
        h1{font-size:2rem;margin-bottom:1rem;color:#172033}
        .msg{padding:1rem;border-radius:12px;background:#28a745;color:#fff;margin-bottom:1rem;font-weight:700}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:1rem}
        .card{background:#fff;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
        .card img{width:100%;height:180px;object-fit:cover;background:#eef2f7}
        .body{padding:1rem}
        .name{font-weight:800;color:#172033;margin-bottom:.35rem}
        .price{font-weight:900;color:#4f46e5;margin-bottom:.8rem}
        .actions{display:flex;gap:.5rem;flex-wrap:wrap}
        .btn{border:none;border-radius:999px;padding:.5rem .8rem;font-weight:800;cursor:pointer;text-decoration:none;font-size:.85rem}
        .view{background:#4f46e5;color:#fff}
        .remove{background:#fee2e2;color:#991b1b}
        .empty{background:#fff;border-radius:14px;padding:2rem;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.08)}
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <h1>Wishlist</h1>
    <?php if ($successMsg): ?><div class="msg"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>

    <?php if ($items && mysqli_num_rows($items) > 0): ?>
        <div class="grid">
            <?php while ($product = mysqli_fetch_assoc($items)): ?>
                <div class="card">
                    <img src="images/<?php echo htmlspecialchars((string)$product['sku']); ?>.jpg"
                         alt="<?php echo htmlspecialchars((string)$product['name']); ?>"
                         onerror="this.src='https://via.placeholder.com/400x260/f8f9fa/999?text=Product';">
                    <div class="body">
                        <div class="name"><?php echo htmlspecialchars((string)$product['name']); ?></div>
                        <div class="price">$<?php echo number_format((float)$product['base_price'], 2); ?></div>
                        <div class="actions">
                            <a class="btn view" href="product.php?id=<?php echo (int)$product['product_id']; ?>">View</a>
                            <form method="POST">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
                                <button type="submit" class="btn remove">Remove</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty">
            <h2>No wishlist items yet</h2>
            <p style="margin:.5rem 0 1rem;color:#6b7280;">Save products from their product pages.</p>
            <a class="btn view" href="index.php">Continue Shopping</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
