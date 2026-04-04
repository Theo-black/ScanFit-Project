<?php
// womens.php
// Include shared helper functions
require_once 'functions.php';

// Retrieve all products that are tagged for the Women gender
$products_result = getProductsByGender('Women');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Women's Collection - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and base typography */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:#f8f9fa;color:#333;line-height:1.6
        }
        /* Top hero banner for the women's collection */
        .hero-section {
            height:40vh;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            background:
                linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)),
                url('images/category_womens') center/cover no-repeat;
    color:#fff;
    text-align:center;
        }
        .hero-section h1{font-size:2.5rem;margin-bottom:.5rem}
        .hero-section p{opacity:.9}
        /* Wrapper for the products listing */
        .products-section{
            max-width:1400px;margin:0 auto;padding:3rem 2rem
        }
        /* Responsive grid for product cards */
        .products-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
            gap:2rem
        }
        /* Individual product card styling */
        .product-card{
            background:#fff;border-radius:20px;overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,.1);
            transition:all .3s ease
        }
        /* Hover elevation effect for product card */
        .product-card:hover{
            transform:translateY(-8px);
            box-shadow:0 20px 40px rgba(0,0,0,.15)
        }
        /* Product image container with fixed height */
        .product-image{
            height:260px;background:#f0f2f5;overflow:hidden
        }
        /* Product image scaling on hover */
        .product-image img{
            width:100%;height:100%;object-fit:cover;
            transition:transform .4s ease
        }
        .product-card:hover .product-image img{transform:scale(1.08)}
        /* Card body containing name, price, sizes and button */
        .product-info{padding:1.5rem}
        .product-name{font-size:1.3rem;font-weight:600;margin-bottom:.5rem}
        .product-price{font-size:1.4rem;font-weight:700;color:#e83e8c;margin-bottom:.8rem}
        .product-sizes{margin-bottom:1rem}
        /* Tag-style label for each available size */
        .size-tag{
            display:inline-block;background:#f8f9fa;color:#555;
            padding:.3rem .7rem;border-radius:999px;
            border:1px solid #e1e4e8;font-size:.85rem;margin-right:.3rem;
            margin-bottom:.3rem
        }
        /* Button linking to the detailed product page */
        .add-to-cart-btn{
            width:100%;padding:.9rem;border-radius:12px;border:none;
            background:linear-gradient(135deg,#ff9a9e 0%,#fecfef 100%);
            color:#fff;font-weight:600;cursor:pointer
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- Hero section introducing the Women's collection -->
<section class="hero-section">
    <h1>Women's Collection</h1>
    <p>Elegant and modern pieces for every occasion</p>
</section>

<!-- Main section listing all women's products -->
<section class="products-section">
    <?php if (mysqli_num_rows($products_result) > 0): ?>
        <div class="products-grid">
            <?php while ($product = mysqli_fetch_assoc($products_result)): ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="images/<?php echo htmlspecialchars($product['sku']); ?>.jpg"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             onerror="this.src='https://via.placeholder.com/300x260/f8f9fa/999?text=👗'">
                    </div>
                    <div class="product-info">
                        <!-- Product name and price -->
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <div class="product-price">
                            $<?php echo number_format($product['base_price'], 2); ?>
                        </div>

                        <?php
                        // Fetch all variants for this product to determine available sizes
                        $variants_result = getProductVariants($product['product_id']);
                        $sizes = [];
                        while ($variant = mysqli_fetch_assoc($variants_result)) {
                            if (!empty($variant['size_name'])) {
                                $sizes[] = $variant['size_name'];
                            }
                        }
                        ?>
                        <?php if (!empty($sizes)): ?>
                            <!-- Display unique size tags for the product -->
                            <div class="product-sizes">
                                <?php foreach (array_unique($sizes) as $size): ?>
                                    <span class="size-tag"><?php echo htmlspecialchars($size); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Link to detailed product page for selection and purchase -->
                        <a href="product.php?id=<?php echo (int)$product['product_id']; ?>" class="add-to-cart-btn">
                            View Details
                        </a>

                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- Fallback message when no women's products exist -->
        <p>No Women's products available yet.</p>
    <?php endif; ?>
</section>
</body>
</html>
