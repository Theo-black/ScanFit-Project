<?php
//index.php

// Include shared functions and establish database connection (via $conn)
require_once 'functions.php';

// Query the database for the 8 most recently created ACTIVE products
$featuredProducts = mysqli_query($conn, "
    SELECT * FROM product
    WHERE status = 'ACTIVE'
    ORDER BY created_at DESC
    LIMIT 8
");

// If the main query fails, create an empty result set to avoid runtime errors
if (!$featuredProducts) {
    $featuredProducts = mysqli_query($conn, "SELECT * FROM product WHERE 1=0");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Scanfit - Smart Fashion Retail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and box-sizing */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            color:#333;line-height:1.6
        }
        /* Full-screen hero with gradient overlay and background image */
        .hero{
            height:90vh;
            background:linear-gradient(rgba(102,126,234,.95),rgba(118,75,162,.95)),
                       url('https://images.pexels.com/photos/996329/pexels-photo-996329.jpeg?auto=compress&cs=tinysrgb&w=1920') center/cover;
            display:flex;align-items:center;justify-content:center;
            text-align:center;color:#fff;position:relative
        }
        /* Container for hero text and buttons */
        .hero-content{max-width:900px;padding:0 2rem;z-index:2}
        .hero h1{
            font-size:clamp(3rem,8vw,5.5rem);font-weight:900;
            margin-bottom:1.5rem;text-shadow:0 4px 30px rgba(0,0,0,.4);
            letter-spacing:2px
        }
        .hero p{
            font-size:clamp(1.2rem,3vw,1.8rem);margin-bottom:3rem;
            opacity:.95;text-shadow:0 2px 10px rgba(0,0,0,.3)
        }
        /* Call-to-action buttons layout */
        .cta-buttons{display:flex;gap:1.5rem;justify-content:center;flex-wrap:wrap}
        .cta-btn{
            padding:1.2rem 2.5rem;border-radius:50px;text-decoration:none;
            font-weight:700;font-size:1.1rem;transition:all .3s;
            box-shadow:0 10px 30px rgba(0,0,0,.2)
        }
        /* Primary CTA button styling */
        .cta-primary{
            background:#fff;color:#667eea
        }
        .cta-primary:hover{transform:translateY(-3px);box-shadow:0 15px 40px rgba(0,0,0,.3)}
        /* Secondary CTA button styling with glass effect */
        .cta-secondary{
            background:rgba(255,255,255,.15);color:#fff;
            border:2px solid #fff;backdrop-filter:blur(10px)
        }
        .cta-secondary:hover{background:rgba(255,255,255,.25);transform:translateY(-3px)}
        /* Features section wrapper */
        .features{
            padding:5rem 2rem;background:#f8f9fa
        }
        .features-container{max-width:1400px;margin:0 auto}
        /* Responsive grid for feature cards */
        .features-grid{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
            gap:2.5rem;margin-top:3rem
        }
        /* Individual feature card styling */
        .feature-card{
            background:#fff;padding:2.5rem;border-radius:20px;
            text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.08);
            transition:all .3s
        }
        .feature-card:hover{transform:translateY(-10px);box-shadow:0 20px 50px rgba(0,0,0,.12)}
        /* Icon styling with gradient text */
        .feature-icon{
            font-size:3.5rem;margin-bottom:1.5rem;
            background:linear-gradient(135deg,#667eea,#764ba2);
            -webkit-background-clip:text;-webkit-text-fill-color:transparent
        }
        .feature-card h3{font-size:1.5rem;margin-bottom:1rem;color:#2c3e50}
        .feature-card p{color:#666;line-height:1.8}
        /* Shared section header styles */
        .section-header{text-align:center;margin-bottom:3rem}
        .section-header h2{
            font-size:clamp(2rem,5vw,3rem);color:#2c3e50;
            margin-bottom:1rem;font-weight:800
        }
        .section-header p{font-size:1.2rem;color:#666}
        /* Featured products section wrapper */
        .products-section{
            padding:5rem 2rem;background:#fff
        }
        .products-container{max-width:1400px;margin:0 auto}
        /* Responsive grid for product cards */
        .products-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
            gap:2.5rem
        }
        /* Product card styling */
        .product-card{
            background:#fff;border-radius:20px;overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,.1);
            transition:all .4s cubic-bezier(.4,0,.2,1)
        }
        .product-card:hover{
            transform:translateY(-12px) scale(1.02);
            box-shadow:0 25px 50px rgba(0,0,0,.15)
        }
        /* Product image container */
        .product-image{height:300px;background:#f0f2f5;overflow:hidden;position:relative}
        .product-image img{
            width:100%;height:100%;object-fit:cover;
            transition:transform .6s ease
        }
        .product-card:hover .product-image img{transform:scale(1.1)}
        /* NEW badge in top-right of product image */
        .product-badge{
            position:absolute;top:1rem;right:1rem;
            background:linear-gradient(135deg,#28a745,#20c997);
            color:#fff;padding:.5rem 1rem;border-radius:20px;
            font-size:.85rem;font-weight:700;z-index:2
        }
        /* Product text/info block */
        .product-info{padding:1.8rem}
        .product-name{
            font-size:1.4rem;font-weight:700;color:#2c3e50;
            margin-bottom:.8rem
        }
        .product-price{
            font-size:1.6rem;font-weight:800;color:#667eea;
            margin-bottom:1rem
        }
        /* View details button styling */
        .view-btn{
            display:block;width:100%;padding:1rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;text-align:center;text-decoration:none;
            border-radius:12px;font-weight:600;transition:all .3s
        }
        .view-btn:hover{transform:translateY(-2px)}
        /* Categories section with soft gradient background */
        .categories{
            padding:5rem 2rem;background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%)
        }
        .categories-container{max-width:1400px;margin:0 auto}
        /* Responsive grid for category cards */
        .categories-grid{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));
            gap:2rem;margin-top:3rem
        }
        /* Category card styling with hover lift */
        .category-card{
            height:350px;border-radius:25px;overflow:hidden;position:relative;
            box-shadow:0 15px 40px rgba(0,0,0,.15);transition:all .4s;
            cursor:pointer
        }
        .category-card:hover{transform:translateY(-10px) scale(1.02);box-shadow:0 25px 60px rgba(0,0,0,.2)}
        /* Dark overlay gradient for category text readability */
        .category-overlay{
            position:absolute;inset:0;
            background:linear-gradient(to bottom,transparent 0%,rgba(0,0,0,.7) 100%);
            display:flex;align-items:flex-end;justify-content:center;
            padding:2.5rem;transition:all .3s
        }
        .category-card:hover .category-overlay{background:linear-gradient(to bottom,rgba(102,126,234,.3) 0%,rgba(118,75,162,.9) 100%)}
        /* Category heading text styling */
        .category-title{
            color:#fff;font-size:2.2rem;font-weight:800;
            text-shadow:0 4px 15px rgba(0,0,0,.5)
        }
        /* Mobile layout adjustments */
        @media(max-width:768px){
            .hero{height:70vh}
            .cta-buttons{flex-direction:column;align-items:center}
            .products-section,.features,.categories{padding:3rem 1.5rem}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<!-- Hero section with brand name, tagline, and main calls to action -->
<section class="hero">
    <div class="hero-content">
        <h1>SCANFIT</h1>
        <p>Smart Fashion Retail - Find Your Perfect Fit with BMI-Powered Size Recommendations</p>
        <div class="cta-buttons">
            <a href="bmi_calculator.php" class="cta-btn cta-primary">Find Your Size</a>
            <a href="#products" class="cta-btn cta-secondary">Shop Now</a>
        </div>
    </div>
</section>

<!-- Feature highlights explaining core benefits of Scanfit -->
<section class="features">
    <div class="features-container">
        <div class="section-header">
            <h2>Why Choose Scanfit?</h2>
            <p>Experience the future of online fashion shopping</p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">📏</div>
                <h3>BMI Size Calculator</h3>
                <p>Get personalized size recommendations based on your body measurements and BMI calculations</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">👕</div>
                <h3>Premium Quality</h3>
                <p>Carefully curated collection of high-quality clothing and accessories for every style</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🚚</div>
                <h3>Fast Delivery</h3>
                <p>Quick and reliable shipping to your doorstep with real-time tracking</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">🔒</div>
                <h3>Secure Shopping</h3>
                <p>Safe and encrypted transactions with multiple payment options available</p>
            </div>
        </div>
    </div>
</section>

<!-- Category cards linking to key shopping sections -->
<section class="categories">
    <div class="categories-container">
        <div class="section-header">
            <h2>Shop by Category</h2>
            <p>Explore our curated collections</p>
        </div>

        <div class="categories-grid">
            <a href="men.php" class="category-card" style="text-decoration:none">
                <img src="https://images.pexels.com/photos/2379004/pexels-photo-2379004.jpeg?auto=compress&cs=tinysrgb&w=800"
                     alt="Men's Collection" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover">
                <div class="category-overlay">
                    <h3 class="category-title">Men's Collection</h3>
                </div>
            </a>

            <a href="womens.php" class="category-card" style="text-decoration:none">
                <img src="https://images.pexels.com/photos/972995/pexels-photo-972995.jpeg?auto=compress&cs=tinysrgb&w=800"
                     alt="Women's Collection" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover">
                <div class="category-overlay">
                    <h3 class="category-title">Women's Collection</h3>
                </div>
            </a>

            <a href="accessories.php" class="category-card" style="text-decoration:none">
                <img src="https://images.pexels.com/photos/190819/pexels-photo-190819.jpeg?auto=compress&cs=tinysrgb&w=800"
                     alt="Accessories" loading="lazy" decoding="async" style="width:100%;height:100%;object-fit:cover">
                <div class="category-overlay">
                    <h3 class="category-title">Accessories</h3>
                </div>
            </a>
        </div>
    </div>
</section>

<!-- Featured products section driven by database query -->
<section class="products-section" id="products">
    <div class="products-container">
        <div class="section-header">
            <h2>Featured Products</h2>
            <p>Check out our latest arrivals</p>
        </div>

        <?php if (mysqli_num_rows($featuredProducts) > 0): ?>
            <div class="products-grid">
                <?php while ($product = mysqli_fetch_assoc($featuredProducts)): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="product-badge">NEW</span>
                            <img src="images/<?php echo htmlspecialchars($product['sku']); ?>.jpg"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 loading="lazy" decoding="async"
                                 onerror="this.src='https://images.pexels.com/photos/996329/pexels-photo-996329.jpeg?auto=compress&cs=tinysrgb&w=400'">
                        </div>
                        <div class="product-info">
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="product-price">$<?php echo number_format($product['base_price'], 2); ?></div>
                            <a href="product.php?id=<?php echo $product['product_id']; ?>" class="view-btn">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Fallback message if there are no featured products -->
            <p style="text-align:center;color:#666;font-size:1.2rem">No products available yet. Check back soon!</p>
        <?php endif; ?>
    </div>
</section>

<!-- Final CTA section encouraging use of the BMI calculator -->
<section class="features" style="padding:4rem 2rem">
    <div class="features-container">
        <div class="section-header">
            <h2>Ready to Find Your Perfect Fit?</h2>
            <p>Use our BMI calculator to get personalized size recommendations</p>
        </div>
        <div style="text-align:center;margin-top:2rem">
            <a href="bmi_calculator.php" class="cta-btn cta-primary" style="display:inline-block">
                Calculate My Size
            </a>
        </div>
    </div>
</section>
</body>
</html>
