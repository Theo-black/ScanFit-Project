<?php
//product.php
// Include database connection and shared helper functions
require_once 'functions.php';

// Get product ID from query string, cast to int, or default to 0 if missing
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If ID is invalid or not provided, return 404 and stop script
if ($id <= 0) {
    http_response_code(404);
    echo "Product not found.";
    exit();
}

// Prepare SQL query to fetch a single active product and its associated gender
$sql = "
    SELECT p.*, g.name AS gender_name
    FROM product p
    LEFT JOIN productgender pg ON p.product_id = pg.product_id
    LEFT JOIN gender g        ON pg.gender_id = g.gender_id
    WHERE p.product_id = ?
      AND p.status = 'ACTIVE'
    LIMIT 1
";
// Initialize prepared statement using existing DB connection
$stmt = mysqli_prepare($conn, $sql);
// If statement preparation fails, return 500 error and exit
if (!$stmt) {
    http_response_code(500);
    echo "Failed to load product.";
    exit();
}
// Bind product ID parameter as integer and execute the query
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
// Retrieve result set and fetch the product row as an associative array
$result  = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

// If no matching product found, return 404 and exit
if (!$product) {
    http_response_code(404);
    echo "Product not found.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shopping_profile_action'])) {
    requireLogin();
    requireCsrfPost('product.php?id=' . $id);
    setShoppingProfileContext(
        (string)($_POST['shopping_mode'] ?? 'self'),
        (string)($_POST['shopping_recipient_name'] ?? ''),
        (string)($_POST['shopping_recipient_size'] ?? '')
    );
    $_SESSION['success'] = (($_POST['shopping_mode'] ?? 'self') === 'other')
        ? 'Shopping profile updated for someone else.'
        : 'Shopping profile switched back to your saved fit.';
    header('Location: product.php?id=' . $id . '#shopping-profile-card');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    requireLogin();
    requireCsrfPost('product.php?id=' . $id);
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim((string)($_POST['comment'] ?? ''));
    if ($rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'Select a rating from 1 to 5.';
    } elseif (!saveProductReview(getCustomerId(), (int)$product['product_id'], $rating, $comment, $reviewError)) {
        $_SESSION['error'] = $reviewError ?? 'Could not save review.';
    } else {
        $_SESSION['success'] = 'Review saved.';
    }
    header('Location: product.php?id=' . $id . '#product-reviews');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wishlist_action'])) {
    requireLogin();
    requireCsrfPost('product.php?id=' . $id);
    $enabled = (string)($_POST['wishlist_action'] ?? '') === 'add';
    if (setProductWishlist(getCustomerId(), (int)$product['product_id'], $enabled)) {
        $_SESSION['success'] = $enabled ? 'Added to wishlist.' : 'Removed from wishlist.';
    } else {
        $_SESSION['error'] = 'Could not update wishlist.';
    }
    header('Location: product.php?id=' . $id);
    exit();
}

// Fetch variants into an array so we can use them in the form
$variants = [];
// Call helper function to get product variants for this product
$raw = getProductVariants($product['product_id']);
// Loop through each variant row and store in $variants array
while ($v = mysqli_fetch_assoc($raw)) {
    $variants[] = $v;
}
$hasInStockVariant = false;
foreach ($variants as $vv) {
    if ((int)($vv['stock_quantity'] ?? 0) > 0) {
        $hasInStockVariant = true;
        break;
    }
}

// Build one selectable variant per size for customer-facing size selection.
$sizeOptions = [];
foreach ($variants as $vv) {
    $sizeName = trim((string)($vv['size_name'] ?? ''));
    if ($sizeName === '') {
        $sizeName = 'Standard';
    }
    $stock = (int)($vv['stock_quantity'] ?? 0);

    if (!isset($sizeOptions[$sizeName])) {
        $sizeOptions[$sizeName] = $vv;
        continue;
    }

    $existingStock = (int)($sizeOptions[$sizeName]['stock_quantity'] ?? 0);
    if (($existingStock <= 0 && $stock > 0) || ($stock > $existingStock)) {
        $sizeOptions[$sizeName] = $vv;
    }
}

// Load the full size catalog so customers can see every available size label.
$allSizes = [];
$allSizesRes = false;
try {
    $allSizesRes = mysqli_query($conn, "SELECT size_id, name, sort_order FROM size ORDER BY sort_order, name");
} catch (Throwable $e) {
    $allSizesRes = mysqli_query($conn, "SELECT size_id, name FROM size ORDER BY name");
}
if ($allSizesRes) {
    while ($sizeRow = mysqli_fetch_assoc($allSizesRes)) {
        $sizeName = trim((string)($sizeRow['name'] ?? ''));
        if ($sizeName !== '') {
            $allSizes[] = $sizeName;
        }
    }
}

if (empty($allSizes)) {
    $allSizes = array_keys($sizeOptions);
}

$displaySizes = [];
foreach ($allSizes as $sizeName) {
    $variant = $sizeOptions[$sizeName] ?? null;
    $variantId = $variant ? (int)($variant['variant_id'] ?? 0) : 0;
    $stock = $variant ? (int)($variant['stock_quantity'] ?? 0) : 0;
    $status = 'not_available';
    if ($variantId > 0 && $stock > 0) {
        $status = 'in_stock';
    } elseif ($variantId > 0) {
        $status = 'out_of_stock';
    }

    $displaySizes[] = [
        'size_name' => $sizeName,
        'variant_id' => $variantId,
        'stock' => $stock,
        'status' => $status,
    ];
}

$defaultVariantId = 0;
$selectedSizeKey = null;
foreach ($displaySizes as $displaySize) {
    if ($displaySize['status'] === 'in_stock') {
        $defaultVariantId = (int)$displaySize['variant_id'];
        $selectedSizeKey = (string)$displaySize['size_name'];
        break;
    }
}

if ($defaultVariantId === 0) {
    foreach ($displaySizes as $displaySize) {
        if ((int)$displaySize['variant_id'] > 0) {
            $defaultVariantId = (int)$displaySize['variant_id'];
            $selectedSizeKey = (string)$displaySize['size_name'];
            break;
        }
    }
}

// flash messages from add_to_cart.php
// Read success and error flash messages from session if set
$successMsg = $_SESSION['success'] ?? null;
$errorMsg   = $_SESSION['error']   ?? null;
// Clear flash message values so they are shown only once
unset($_SESSION['success'], $_SESSION['error']);

$customerId = isLoggedIn() ? getCustomerId() : 0;
$shoppingProfile = getShoppingProfileContext($customerId);
$reviewSummary = getProductReviewSummary((int)$product['product_id']);
$reviews = getProductReviews((int)$product['product_id']);
$canReview = $customerId > 0 && customerCanReviewProduct($customerId, (int)$product['product_id']);
$isWishlisted = $customerId > 0 && isProductWishlisted($customerId, (int)$product['product_id']);
$recommendedShopSize = (string)($shoppingProfile['effective_size'] ?? '');
$shoppingContextNote = '';
if (($shoppingProfile['mode'] ?? 'self') === 'other') {
    $recipientLabel = trim((string)($shoppingProfile['recipient_name'] ?? ''));
    $shoppingContextNote = $recipientLabel !== ''
        ? 'Shopping for ' . $recipientLabel . ' using size ' . $recommendedShopSize . '.'
        : 'Shopping for someone else using size ' . $recommendedShopSize . '.';
} elseif (!empty($shoppingProfile['profile_fit_size'])) {
    $shoppingContextNote = 'Using your saved fit size ' . $shoppingProfile['profile_fit_size'] . '.';
}

$recommendedSizeAvailable = false;
if ($recommendedShopSize !== '') {
    foreach ($displaySizes as $displaySize) {
        if ((string)$displaySize['size_name'] !== $recommendedShopSize) {
            continue;
        }
        if ((string)$displaySize['status'] === 'in_stock') {
            $defaultVariantId = (int)$displaySize['variant_id'];
            $selectedSizeKey = (string)$displaySize['size_name'];
            $recommendedSizeAvailable = true;
        }
        break;
    }
}

$recommendedSizeWarning = '';
if ($recommendedShopSize !== '' && !$recommendedSizeAvailable) {
    $recommendedSizeWarning = 'Recommended size ' . $recommendedShopSize . ' is not currently available for this product.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- Dynamic page title using product name -->
    <title><?php echo htmlspecialchars($product['name']); ?> - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Reset margin, padding and set box sizing */
        *{margin:0;padding:0;box-sizing:border-box}

        /* Global body styles including background gradient and typography */
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);
            color:#333;
            min-height:100vh;
        }

        /* Hero header section with background image and overlay gradient */
        .hero-section{
            background:linear-gradient(rgba(102,126,234,.9),rgba(118,75,162,.9)),
                       url('https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=2000&q=80');
            background-size:cover;
            background-position:center;
            height:45vh;
            display:flex;
            align-items:center;
            justify-content:center;
            text-align:center;
            color:#fff;
        }

        /* Container for hero text content */
        .hero-content{
            max-width:800px;
            padding:0 1.5rem;
        }

        /* Main hero heading styling */
        .hero-content h1{
            font-size:clamp(2rem,4.5vw,3.2rem);
            font-weight:800;
            margin-bottom:.8rem;
            text-shadow:0 4px 20px rgba(0,0,0,.5);
        }

        /* Hero subtitle text styling */
        .hero-content p{
            font-size:clamp(1rem,2.3vw,1.3rem);
            opacity:.95;
        }

        /* Wrapper for main content area */
        .page-wrapper{
            max-width:1200px;
            margin:0 auto;
            padding:3rem 1.5rem 4rem;
        }

        /* Flash message base styling */
        .flash-msg{
            margin-bottom:1.5rem;
            padding:1rem 1.2rem;
            border-radius:12px;
            font-weight:600;
            text-align:center;
        }
        /* Success flash background and text color */
        .flash-success{
            background:#28a745;
            color:#fff;
        }
        /* Error flash background and text color */
        .flash-error{
            background:#ff4444;
            color:#fff;
        }

        /* Grid layout for product image and info columns */
        .product-layout{
            display:grid;
            grid-template-columns:minmax(0,1.1fr) minmax(0,1fr);
            gap:2.5rem;
            align-items:flex-start;
        }

        /* Card container for product image */
        .product-image-card{
            background:#fff;
            border-radius:24px;
            overflow:hidden;
            box-shadow:0 18px 40px rgba(0,0,0,.12);
        }

        /* Wrapper controlling image height and centering */
        .product-image-wrapper{
            background:#f0f4f8;
            height:420px;
            display:flex;
            align-items:center;
            justify-content:center;
        }

        /* Product image sizing and object-fit */
        .product-image-wrapper img{
            width:100%;
            height:100%;
            object-fit:cover;
        }

        /* Pill-style label (e.g., Product Detail) */
        .pill{
            display:inline-flex;
            align-items:center;
            padding:.3rem .9rem;
            border-radius:999px;
            font-size:.8rem;
            font-weight:600;
            letter-spacing:.08em;
            text-transform:uppercase;
            background:#e8ecff;
            color:#4b5cd7;
        }

        /* Card container for product information and form */
        .product-info-card{
            background:#fff;
            border-radius:24px;
            padding:2rem;
            box-shadow:0 18px 40px rgba(0,0,0,.08);
        }

        /* Product title typography */
        .product-title{
            font-size:1.9rem;
            font-weight:800;
            color:#2c3e50;
            margin-bottom:.4rem;
        }

        /* SKU text styling */
        .sku-text{
            font-size:.9rem;
            color:#8b95b1;
            margin-bottom:1rem;
        }

        /* Row for price and supporting text */
        .price-row{
            display:flex;
            align-items:baseline;
            gap:.8rem;
            margin-bottom:1.4rem;
        }

        /* Main price styling */
        .price-main{
            font-size:2rem;
            font-weight:800;
            color:#667eea;
        }

        /* Price note (tax/shipping) styling */
        .price-note{
            font-size:.9rem;
            color:#8b95b1;
        }

        /* Row of meta chips below price */
        .meta-row{
            display:flex;
            flex-wrap:wrap;
            gap:.6rem;
            margin-bottom:1.5rem;
        }

        /* Individual pill-like meta chip (returns, checkout, etc.) */
        .meta-chip{
            background:#f8f9fa;
            border-radius:999px;
            padding:.35rem .9rem;
            font-size:.85rem;
            color:#555;
            border:1px solid #e1e4e8;
        }

        /* Product description text styling */
        .product-description{
            font-size:.98rem;
            line-height:1.8;
            color:#555;
            margin-bottom:1.8rem;
        }

        /* Form group container spacing */
        .form-group{
            margin-bottom:1.3rem;
        }

        /* Form label styling */
        .form-group label{
            display:block;
            font-weight:600;
            margin-bottom:.5rem;
            color:#2c3e50;
            font-size:.95rem;
        }

        /* Base styles for select and numeric input fields */
        select,
        input[type="number"]{
            width:100%;
            padding:.9rem 1rem;
            border-radius:12px;
            border:2px solid #e1e4e8;
            font-size:.95rem;
            outline:none;
            transition:border-color .25s, box-shadow .25s;
            background:#fff;
        }

        /* Focus state styles for form controls */
        select:focus,
        input[type="number"]:focus{
            border-color:#667eea;
            box-shadow:0 0 0 3px rgba(102,126,234,.2);
        }

        /* Layout for quantity row: input and helper text */
        .qty-row{
            display:flex;
            gap:1rem;
            align-items:center;
        }

        /* Quantity helper text styling */
        .qty-row small{
            color:#8b95b1;
            font-size:.8rem;
        }

        .shopping-profile-card{
            margin:1.2rem 0 1.6rem;
            padding:1rem 1.1rem;
            border-radius:18px;
            border:1px solid #dbe4ff;
            background:#f8faff;
        }
        .shopping-profile-title{
            font-size:.95rem;
            font-weight:800;
            color:#2c3e50;
            margin-bottom:.35rem;
        }
        .shopping-profile-copy{
            color:#64748b;
            font-size:.92rem;
            line-height:1.5;
            margin-bottom:.85rem;
        }
        .shopping-profile-grid{
            display:grid;
            grid-template-columns:1.1fr 1fr 1fr auto;
            gap:.8rem;
            align-items:end;
        }
        .shopping-profile-grid label{
            display:block;
            margin-bottom:.35rem;
            color:#475569;
            font-size:.82rem;
            font-weight:700;
        }
        .shopping-profile-grid input[type="text"],
        .shopping-profile-grid select{
            width:100%;
            padding:.8rem .9rem;
            border-radius:12px;
            border:2px solid #e1e4e8;
            background:#fff;
            font-size:.92rem;
        }
        .shopping-profile-actions{
            display:flex;
            align-items:end;
        }
        .shopping-profile-btn{
            border:none;
            border-radius:12px;
            padding:.9rem 1rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;
            font-weight:700;
            cursor:pointer;
            white-space:nowrap;
        }
        .shopping-profile-note{
            margin-top:.75rem;
            color:#334155;
            font-size:.9rem;
            font-weight:600;
        }
        .shopping-profile-warning{
            margin-top:.55rem;
            color:#b45309;
            font-size:.88rem;
            font-weight:700;
        }

        /* Add to Cart button styling */
        .add-to-cart-btn{
            width:100%;
            padding:1rem 1.2rem;
            margin-top:1rem;
            border:none;
            border-radius:16px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;
            font-size:1rem;
            font-weight:700;
            cursor:pointer;
            box-shadow:0 14px 30px rgba(102,126,234,.35);
            transition:transform .18s ease, box-shadow .18s ease;
        }

        /* Add to Cart hover effect */
        .add-to-cart-btn:hover{
            transform:translateY(-2px);
            box-shadow:0 18px 40px rgba(102,126,234,.45);
        }
        .wishlist-btn{
            width:100%;
            padding:.85rem 1rem;
            margin-top:.75rem;
            border:2px solid #667eea;
            border-radius:16px;
            background:#fff;
            color:#4f46e5;
            font-size:.95rem;
            font-weight:800;
            cursor:pointer;
        }

        /* Badge shown when variants are limited or unavailable */
        .badge-out{
            display:inline-flex;
            align-items:center;
            gap:.4rem;
            padding:.35rem .8rem;
            border-radius:999px;
            background:#fff4f4;
            color:#e55353;
            font-size:.8rem;
            margin-top:.6rem;
        }

        /* Small helper note below variants dropdown */
        .variant-note{
            font-size:.85rem;
            color:#8b95b1;
            margin-top:.3rem;
        }

        /* Box shown when product has no variants configured */
        .no-variants-box{
            padding:1rem 1.2rem;
            border-radius:14px;
            background:#fff4e6;
            color:#b55a00;
            font-size:.9rem;
            margin:1rem 0 1.5rem;
        }
        .reviews-card{
            max-width:1200px;
            margin:2rem auto 0;
            background:#fff;
            border-radius:20px;
            padding:1.6rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
        }
        .reviews-head{
            display:flex;
            justify-content:space-between;
            gap:1rem;
            align-items:center;
            flex-wrap:wrap;
            margin-bottom:1rem;
        }
        .rating-summary{
            font-weight:800;
            color:#2c3e50;
        }
        .review-form{
            display:grid;
            gap:.75rem;
            margin:1rem 0;
            padding:1rem;
            border:1px solid #e5e7eb;
            border-radius:14px;
            background:#f8fafc;
        }
        .review-form select,
        .review-form textarea{
            width:100%;
            padding:.8rem .9rem;
            border-radius:12px;
            border:2px solid #e1e4e8;
            font:inherit;
        }
        .review-form textarea{min-height:90px;resize:vertical}
        .review-item{
            padding:1rem 0;
            border-top:1px solid #e5e7eb;
        }
        .review-meta{
            display:flex;
            justify-content:space-between;
            gap:1rem;
            flex-wrap:wrap;
            font-weight:800;
            color:#2c3e50;
            margin-bottom:.35rem;
        }
        .review-stars{color:#b45309}

        /* Section heading label above product info */
        .section-heading{
            font-size:1rem;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.12em;
            color:#9ca3af;
            margin-bottom:.6rem;
        }

        /* Highlighted span inside section heading */
        .section-heading span{
            color:#667eea;
        }

        /* Responsive layout adjustments for tablets */
        @media (max-width: 900px){
            .product-layout{
                grid-template-columns:1fr;
            }
            .product-image-wrapper{
                height:320px;
            }
        }

        /* Responsive layout adjustments for small screens */
        @media (max-width: 600px){
            .page-wrapper{
                padding:2rem 1rem 3rem;
            }
            .product-info-card{
                padding:1.6rem;
            }
            .shopping-profile-grid{
                grid-template-columns:1fr;
            }
        }
    </style>
</head>
<body>

<!-- Include main navigation bar -->
<?php include 'navbar.php'; ?>

<!-- Top hero section introducing the product detail view -->
<section class="hero-section">
    <div class="hero-content">
        <p class="pill">Product Detail</p>
        <h1><?php echo htmlspecialchars($product['name']); ?></h1>
        <p>Explore fit, style, and sizing before adding this item to your Scanfit cart.</p>
    </div>
</section>

<!-- Main page container for product content and messages -->
<div class="page-wrapper">

    <!-- Render success message if present -->
    <?php if ($successMsg): ?>
        <div class="flash-msg flash-success">
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Render error message if present -->
    <?php if ($errorMsg): ?>
        <div class="flash-msg flash-error">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <!-- Two-column layout: product image (left) and info/form (right) -->
    <div class="product-layout">

        <!-- LEFT: IMAGE -->
        <div class="product-image-card">
            <div class="product-image-wrapper">
                <img
                    src="images/<?php echo htmlspecialchars($product['sku']); ?>.jpg"
                    alt="<?php echo htmlspecialchars($product['name']); ?>"
                    onerror="this.src='https://via.placeholder.com/700x450/f8f9fa/999?text=Product+Image';"
                >
            </div>
        </div>

        <!-- RIGHT: INFO + FORM -->
        <div class="product-info-card">
            <!-- Section label showing context (Scanfit Product) -->
            <div class="section-heading"><span>Scanfit</span> Product</div>

            <!-- Product name as main heading -->
            <h2 class="product-title">
                <?php echo htmlspecialchars($product['name']); ?>
            </h2>

            <!-- SKU line, optionally including gender if available -->
            <div class="sku-text">
                SKU: <?php echo htmlspecialchars($product['sku']); ?>
                <?php if (!empty($product['gender_name'])): ?>
                    â€¢ <?php echo htmlspecialchars($product['gender_name']); ?>
                <?php endif; ?>
            </div>

            <!-- Display base product price and explanatory note -->
            <div class="price-row">
                <div class="price-main">
                    $<?php echo number_format($product['base_price'], 2); ?>
                </div>
                <div class="price-note">Tax and shipping calculated at checkout</div>
            </div>

            <!-- Meta chips for reassurance (returns, checkout, processing) -->
            <div class="meta-row">
                <span class="meta-chip">Easy returns</span>
                <span class="meta-chip">Secure checkout</span>
                <span class="meta-chip">Fast processing</span>
            </div>

            <!-- Product description block; fallback text if description is empty -->
            <?php if (!empty($product['description'])): ?>
                <p class="product-description">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </p>
            <?php else: ?>
                <p class="product-description">
                    Discover a versatile wardrobe essential designed for comfort and everyday wear.
                </p>
            <?php endif; ?>

            <?php if (isLoggedIn()): ?>
                <div class="shopping-profile-card" id="shopping-profile-card">
                    <div class="shopping-profile-title">Shopping Profile</div>
                    <div class="shopping-profile-copy">
                        Stay in your own account, but switch whether product size defaults should follow your saved fit or someone else’s size.
                    </div>
                    <form method="POST">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="shopping_profile_action" value="save">
                        <div class="shopping-profile-grid">
                            <div>
                                <label for="shopping_mode">Shopping for</label>
                                <select name="shopping_mode" id="shopping_mode">
                                    <option value="self" <?php echo (($shoppingProfile['mode'] ?? 'self') === 'self') ? 'selected' : ''; ?>>My Profile</option>
                                    <option value="other" <?php echo (($shoppingProfile['mode'] ?? '') === 'other') ? 'selected' : ''; ?>>Someone Else</option>
                                </select>
                            </div>
                            <div id="shopping_recipient_name_wrap">
                                <label for="shopping_recipient_name">Person name</label>
                                <input type="text" name="shopping_recipient_name" id="shopping_recipient_name" maxlength="80" placeholder="Optional" value="<?php echo htmlspecialchars((string)($shoppingProfile['recipient_name'] ?? '')); ?>">
                            </div>
                            <div id="shopping_recipient_size_wrap">
                                <label for="shopping_recipient_size">Preferred size</label>
                                <select name="shopping_recipient_size" id="shopping_recipient_size">
                                    <option value="">Choose size</option>
                                    <?php foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL'] as $sizeOption): ?>
                                        <option value="<?php echo $sizeOption; ?>" <?php echo (($shoppingProfile['recipient_size'] ?? '') === $sizeOption) ? 'selected' : ''; ?>><?php echo $sizeOption; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="shopping-profile-actions">
                                <button type="submit" class="shopping-profile-btn">Save Shopping Profile</button>
                            </div>
                        </div>
                    </form>
                    <?php if ($shoppingContextNote !== ''): ?>
                        <div class="shopping-profile-note"><?php echo htmlspecialchars($shoppingContextNote); ?></div>
                    <?php endif; ?>
                    <?php if ($recommendedSizeWarning !== ''): ?>
                        <div class="shopping-profile-warning"><?php echo htmlspecialchars($recommendedSizeWarning); ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Add to Cart form posting to cart handler -->
            <form method="POST" action="add_to_cart.php">
                <?php echo csrfInput(); ?>
                <!-- Hidden field with product ID used by add_to_cart.php -->
                <input type="hidden" name="product_id"
                       value="<?php echo (int)$product['product_id']; ?>">

                <!-- Size selector shown when size definitions are available -->
                <?php if (!empty($displaySizes)): ?>
                    <div class="form-group">
                        <label for="size_select">Select size</label>
                        <select name="size_select" id="size_select" required>
                            <?php foreach ($displaySizes as $displaySize): ?>
                                <?php
                                $sizeName = (string)$displaySize['size_name'];
                                $variantId = (int)$displaySize['variant_id'];
                                $stock = (int)$displaySize['stock'];
                                $status = (string)$displaySize['status'];
                                $isUnavailable = ($status !== 'in_stock');
                                ?>
                                <option value="<?php echo htmlspecialchars($sizeName); ?>" data-variant-id="<?php echo $variantId; ?>" data-stock="<?php echo $stock; ?>" <?php echo $isUnavailable ? 'disabled' : ''; ?> <?php echo ($selectedSizeKey !== null && $selectedSizeKey === $sizeName) ? 'selected' : ''; ?>>
                                    <?php
                                    // Build a human-readable size label
                                    $label = 'Size: ' . $sizeName;
                                    if ($status === 'in_stock') {
                                        $label .= ' - In stock (' . $stock . ')';
                                    } elseif ($status === 'out_of_stock') {
                                        $label .= ' - Out of stock';
                                    } else {
                                        $label .= ' - Not available';
                                    }
                                    echo htmlspecialchars($label);
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="variant-note">
                            Choose your preferred size. The matching variant is added to cart automatically.
                            <?php if ($shoppingContextNote !== ''): ?>
                                <?php echo ' ' . htmlspecialchars($shoppingContextNote); ?>
                            <?php endif; ?>
                        </p>
                        <input type="hidden" name="variant_id" id="variant_id" value="<?php echo (int)$defaultVariantId; ?>">
                    </div>
                <?php else: ?>
                    <!-- Message block when no variants are configured -->
                    <div class="no-variants-box">
                        No sizes are configured in the system yet. Please contact support.
                    </div>
                <?php endif; ?>

                <!-- Quantity selector for the product -->
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <div class="qty-row">
                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="1"
                            min="1"
                            <?php echo $hasInStockVariant ? '' : 'disabled'; ?>
                        >
                        <small>Limit applies based on available stock.</small>
                    </div>
                </div>

                <!-- Primary submit button to add item to cart -->
                <button type="submit" id="add_to_cart_btn" class="add-to-cart-btn" <?php echo $hasInStockVariant ? '' : 'disabled'; ?>>
                    <?php echo $hasInStockVariant ? 'Add to Cart' : 'Out of Stock'; ?>
                </button>

                <!-- Badge hinting that configuration options are limited -->
                <?php if (empty($variants)): ?>
                    <div class="badge-out">
                        Limited configuration available
                    </div>
                <?php elseif (!$hasInStockVariant): ?>
                    <div class="badge-out">
                        Out of stock for all sizes
                    </div>
                <?php endif; ?>
            </form>
            <?php if (isLoggedIn()): ?>
                <form method="POST">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="wishlist_action" value="<?php echo $isWishlisted ? 'remove' : 'add'; ?>">
                    <button type="submit" class="wishlist-btn">
                        <?php echo $isWishlisted ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                    </button>
                </form>
            <?php else: ?>
                <a href="login.php" class="wishlist-btn" style="display:block;text-align:center;text-decoration:none;">Log in to Save</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="reviews-card" id="product-reviews">
    <div class="reviews-head">
        <div>
            <div class="section-heading"><span>Customer</span> Reviews</div>
            <h2 style="color:#2c3e50">Reviews</h2>
        </div>
        <div class="rating-summary">
            <?php if ($reviewSummary['review_count'] > 0): ?>
                <?php echo number_format((float)$reviewSummary['average_rating'], 1); ?>/5
                from <?php echo (int)$reviewSummary['review_count']; ?> review<?php echo $reviewSummary['review_count'] === 1 ? '' : 's'; ?>
            <?php else: ?>
                No reviews yet
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canReview): ?>
        <form method="POST" class="review-form">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="review_action" value="save">
            <label>
                Rating
                <select name="rating" required>
                    <option value="">Select rating</option>
                    <?php for ($ratingOption = 5; $ratingOption >= 1; $ratingOption--): ?>
                        <option value="<?php echo $ratingOption; ?>"><?php echo $ratingOption; ?> star<?php echo $ratingOption === 1 ? '' : 's'; ?></option>
                    <?php endfor; ?>
                </select>
            </label>
            <label>
                Comment
                <textarea name="comment" maxlength="1000" placeholder="Share how it fit, felt, or looked."></textarea>
            </label>
            <button type="submit" class="shopping-profile-btn" style="width:max-content;">Save Review</button>
        </form>
    <?php elseif (isLoggedIn()): ?>
        <p class="variant-note">Reviews open after this product is delivered in one of your orders.</p>
    <?php else: ?>
        <p class="variant-note"><a href="login.php">Log in</a> to review delivered purchases.</p>
    <?php endif; ?>

    <?php if ($reviews && mysqli_num_rows($reviews) > 0): ?>
        <?php while ($review = mysqli_fetch_assoc($reviews)): ?>
            <div class="review-item">
                <div class="review-meta">
                    <span><?php echo htmlspecialchars(trim((string)$review['first_name'] . ' ' . (string)$review['last_name'])); ?></span>
                    <span class="review-stars"><?php echo str_repeat('*', (int)$review['rating']); ?> <?php echo (int)$review['rating']; ?>/5</span>
                </div>
                <?php if (trim((string)($review['comment'] ?? '')) !== ''): ?>
                    <p><?php echo nl2br(htmlspecialchars((string)$review['comment'])); ?></p>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    const sizeSelect = document.getElementById('size_select');
    const variantInput = document.getElementById('variant_id');
    const qtyInput = document.getElementById('quantity');
    const addToCartBtn = document.getElementById('add_to_cart_btn');
    const shoppingMode = document.getElementById('shopping_mode');
    const recipientNameWrap = document.getElementById('shopping_recipient_name_wrap');
    const recipientSizeWrap = document.getElementById('shopping_recipient_size_wrap');

    function syncShoppingProfileFields() {
        if (!shoppingMode || !recipientNameWrap || !recipientSizeWrap) return;
        const isOther = shoppingMode.value === 'other';
        recipientNameWrap.style.display = isOther ? 'block' : 'none';
        recipientSizeWrap.style.display = isOther ? 'block' : 'none';
    }

    syncShoppingProfileFields();
    if (shoppingMode) {
        shoppingMode.addEventListener('change', syncShoppingProfileFields);
    }

    if (!sizeSelect || !variantInput || !qtyInput) return;

    function updateSelectionState() {
        const selected = sizeSelect.options[sizeSelect.selectedIndex];
        if (!selected) return;

        const variantId = selected.getAttribute('data-variant-id');
        const stockRaw = selected.getAttribute('data-stock');
        const stock = stockRaw ? parseInt(stockRaw, 10) : 0;
        variantInput.value = variantId ? variantId : '';

        if (!Number.isFinite(stock) || stock <= 0) {
            qtyInput.value = 1;
            qtyInput.max = 1;
            qtyInput.disabled = true;
            if (addToCartBtn) addToCartBtn.disabled = true;
            return;
        }

        qtyInput.disabled = false;
        if (addToCartBtn) addToCartBtn.disabled = false;
        qtyInput.max = stock;
        if (parseInt(qtyInput.value || '1', 10) > stock) {
            qtyInput.value = stock;
        }
    }

    updateSelectionState();
    sizeSelect.addEventListener('change', updateSelectionState);
})();
</script>

</body>
</html>

