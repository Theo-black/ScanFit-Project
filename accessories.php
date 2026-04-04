<?php
// accessories.php

// Load shared helper functions used across the application
require_once 'functions.php';

// Fetch all products whose gender is set as 'Unisex' (treated as accessories)
$accessories_result = getProductsByGender('Unisex');

// Build an array of products, each enriched with its available sizes and colors
$products_with_variants = [];
if ($accessories_result && mysqli_num_rows($accessories_result) > 0) {
    // Reset internal pointer to the first row of the result set
    mysqli_data_seek($accessories_result, 0);
    // Loop through each accessory product row
    while ($product = mysqli_fetch_assoc($accessories_result)) {
        // Retrieve all variant rows (sizes/colors) for the current product
        $variants_result = getProductVariants($product['product_id']);
        $sizes  = [];
        $colors = [];
        // Collect size and color values from each variant row
        while ($variant = mysqli_fetch_assoc($variants_result)) {
            if (!empty($variant['size_name'])) {
                $sizes[] = $variant['size_name'];
            }
            if (!empty($variant['colour_name'])) {
                $colors[] = $variant['colour_name'];
            }
        }
        // Attach unique size and color lists to the product entry
        $product['available_sizes']  = array_unique($sizes);
        $product['available_colors'] = array_unique($colors);
        // Store the enriched product in the final array
        $products_with_variants[]    = $product;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Accessories - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and box model setup */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            /* Base typography and gradient background for the page */
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);
            color:#333;min-height:100vh
        }
        .hero-section {
            height:40vh;
            display:flex;
            flex-direction:column;
            align-items:center;
            justify-content:center;
            background:
                linear-gradient(rgba(0,0,0,0.45), rgba(0,0,0,0.45)),
                url('images/category_accessories') center/100% no-repeat;
    color:#fff;
    text-align:center;
        }
        .hero-content{
            /* Constrain hero text width and add side padding */
            max-width:800px;padding:0 2rem
        }
        .hero-content h1{
            /* Hero title styling with responsive font-size and shadow */
            font-size:clamp(2.5rem,6vw,4.5rem);font-weight:800;
            margin-bottom:1.5rem;text-shadow:0 4px 20px rgba(0,0,0,.5)
        }
        .hero-content p{
            /* Supporting hero subtitle text */
            font-size:clamp(1.1rem,2.5vw,1.6rem);
            margin-bottom:2rem;opacity:.95
        }
        .products-section{
            /* Wrapper for the products listing area */
            max-width:1400px;margin:0 auto;padding:4rem 2rem
        }
        .products-header{
            /* Center and space the section title above the grid */
            text-align:center;margin-bottom:3rem
        }
        .section-subtitle{
            /* Small uppercase subtitle label above main heading */
            color:#667eea;font-size:1.1rem;font-weight:600;
            margin-bottom:.6rem;letter-spacing:2px;text-transform:uppercase
        }
        .products-grid{
            /* Responsive grid layout for product cards */
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
            gap:2.5rem
        }
        .product-card{
            /* Individual product card styling with rounded corners and shadow */
            background:#fff;border-radius:25px;overflow:hidden;
            box-shadow:0 15px 35px rgba(0,0,0,.08);
            transition:all .4s cubic-bezier(.4,0,.2,1);
            position:relative;height:460px
        }
        .product-card:hover{
            /* Lift and scale effect when hovering over the product card */
            transform:translateY(-12px) scale(1.01);
            box-shadow:0 30px 60px rgba(0,0,0,.15)
        }
        .product-badge{
            /* Small badge in the top-right corner (e.g., NEW) */
            position:absolute;top:1rem;right:1rem;
            background:linear-gradient(135deg,#28a745,#20c997);
            color:#fff;padding:.4rem .9rem;border-radius:18px;
            font-size:.8rem;font-weight:700;z-index:2
        }
        .product-image{
            /* Container for product image */
            height:260px;background:#f0f4f8;overflow:hidden
        }
        .product-image img{
            /* Make image fill container and animate on hover */
            width:100%;height:100%;object-fit:cover;
            transition:transform .6s ease
        }
        .product-card:hover .product-image img{
            /* Zoom image slightly on card hover */
            transform:scale(1.1)
        }
        .product-info{
            /* Content area inside the card for name, price, and actions */
            padding:1.6rem;height:200px;display:flex;
            flex-direction:column;justify-content:space-between
        }
        .product-name{
            /* Product title styling */
            font-size:1.4rem;font-weight:700;color:#2c3e50;
            margin-bottom:.5rem
        }
        .product-price{
            /* Prominent price styling */
            font-size:1.8rem;font-weight:800;color:#667eea;
            margin-bottom:.6rem
        }
        .product-variants{
            /* Container for size/color tags under each product */
            display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1rem
        }
        .variant-tag{
            /* Pill-style label for a size or other variant attribute */
            background:#f8f9fa;color:#667eea;padding:.3rem .7rem;
            border-radius:999px;font-size:.8rem;font-weight:500;
            border:1px solid #e1e4e8
        }
        .add-to-cart-btn{
            /* Full-width call-to-action button on each card */
            width:100%;padding:1rem;border:none;border-radius:15px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;font-size:1rem;font-weight:700;cursor:pointer
        }
        .no-products{
            /* Fallback block shown when no accessories are available */
            text-align:center;padding:4rem 2rem;background:#fff;
            border-radius:25px;box-shadow:0 15px 40px rgba(0,0,0,.1)
        }
        @media(max-width:768px){
            /* Mobile adjustments for padding and card height */
            .products-section{padding:2rem 1rem}
            .product-card{height:430px}
        }
    </style>
</head>
<body>
<?php 
// Insert the shared navigation bar so the page uses the global site menu
include 'navbar.php'; 
?>


<section class="hero-section">
    <!-- Hero banner introducing the accessories category -->
    <div class="hero-content">
        <h1>Premium Accessories</h1>
        <p>Elevate your style with our curated collection of watches, bags, belts, and jewelry.</p>
    </div>
</section>


<section class="products-section">
    <!-- Main section displaying the accessories product grid -->
    <div class="products-header">
        <div class="section-subtitle">ACCESSORIES COLLECTION</div>
        <h2>Find Your Perfect Accessory</h2>
    </div>

    <!-- If there are products with variants, render them as cards -->
    <?php if (!empty($products_with_variants)): ?>
        <div class="products-grid">
            <?php foreach ($products_with_variants as $product): ?>
                <div class="product-card">
                    <!-- Badge label for promotional or new products -->
                    <div class="product-badge">NEW</div>
                    <div class="product-image">
                        <!-- Product image based on SKU; fallback placeholder on error -->
                        <img src="images/<?php echo htmlspecialchars($product['sku']); ?>.jpg"
                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                             onerror="this.src='https://via.placeholder.com/400x260/f8f9fa/999?text=👜'">
                    </div>
                    <div class="product-info">
                        <!-- Product name -->
                        <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                        <!-- Base price formatted to 2 decimal places -->
                        <div class="product-price">
                            $<?php echo number_format($product['base_price'], 2); ?>
                        </div>

                        <!-- Show up to three available sizes as variant tags, if they exist -->
                        <?php if (!empty($product['available_sizes'])): ?>
                            <div class="product-variants">
                                <?php foreach (array_slice($product['available_sizes'], 0, 3) as $size): ?>
                                    <span class="variant-tag"><?php echo htmlspecialchars($size); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Link to detailed product page with product ID in query string -->
                        <a href="product.php?id=<?php echo (int)$product['product_id']; ?>" class="add-to-cart-btn">
                            View Details
                        </a>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- Message shown when there are no accessories in the database -->
        <div class="no-products">
            <h2>No Accessories Available</h2>
            <p>Our accessories collection is coming soon. Check back for watches, bags, belts, and more!</p>
        </div>
    <?php endif; ?>
</section>
</body>
</html>
