<?php
// search.php

// Include shared functions (DB connection, search helper, etc.)
require_once 'functions.php';

// Read search query from URL parameter and trim whitespace
$query = trim($_GET['q'] ?? '');
$sort = (string)($_GET['sort'] ?? 'name_asc');
if (!in_array($sort, ['name_asc', 'price_asc', 'price_desc', 'newest'], true)) {
    $sort = 'name_asc';
}
// Will hold mysqli result set when a search is performed
$results = null;

// If the user entered a non-empty search term, perform the product search
if (!empty($query)) {
    $results = searchProducts($query, $sort);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Search Results - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and base styles */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:#f8f9fa;color:#333;min-height:100vh
        }
        /* Main page container */
        .container{max-width:1400px;margin:0 auto;padding:3rem 2rem}
        /* Header area above search results */
        .search-header{margin-bottom:2rem}
        .search-header h1{
            font-size:2.5rem;color:#2c3e50;margin-bottom:.5rem
        }
        /* Text showing query and result count */
        .search-info{color:#666;font-size:1.1rem}
        .search-query{font-weight:700;color:#667eea}
        .search-controls{
            margin-top:1rem;display:flex;gap:.75rem;align-items:center;flex-wrap:wrap
        }
        .search-controls select{
            padding:.65rem .8rem;border:2px solid #e1e4e8;border-radius:10px;background:#fff;font:inherit
        }
        .search-controls button{
            border:none;border-radius:10px;padding:.7rem 1rem;background:#667eea;color:#fff;font-weight:700;cursor:pointer
        }
        /* Responsive grid of product cards */
        .products-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
            gap:2.5rem
        }
        /* Individual product card styling */
        .product-card{
            background:#fff;border-radius:20px;overflow:hidden;
            box-shadow:0 10px 30px rgba(0,0,0,.1);
            transition:all .4s cubic-bezier(.4,0,.2,1)
        }
        /* Hover effect for product cards */
        .product-card:hover{
            transform:translateY(-12px) scale(1.02);
            box-shadow:0 25px 50px rgba(0,0,0,.15)
        }
        /* Product image container with fixed height */
        .product-image{height:300px;background:#f0f2f5;overflow:hidden;position:relative}
        /* Product image styling and zoom on hover */
        .product-image img{
            width:100%;height:100%;object-fit:cover;
            transition:transform .6s ease
        }
        .product-card:hover .product-image img{transform:scale(1.1)}
        /* Availability badge in top-right of image */
        .product-badge{
            position:absolute;top:1rem;right:1rem;
            background:linear-gradient(135deg,#28a745,#20c997);
            color:#fff;padding:.5rem 1rem;border-radius:20px;
            font-size:.85rem;font-weight:700;z-index:2
        }
        /* Card body with product text/info */
        .product-info{padding:1.8rem}
        .product-name{
            font-size:1.4rem;font-weight:700;color:#2c3e50;
            margin-bottom:.8rem
        }
        /* Truncated product description preview */
        .product-description{
            color:#666;font-size:.95rem;margin-bottom:1rem;
            line-height:1.6;display:-webkit-box;
            -webkit-line-clamp:2;-webkit-box-orient:vertical;
            overflow:hidden
        }
        /* Product price styling */
        .product-price{
            font-size:1.6rem;font-weight:800;color:#667eea;
            margin-bottom:1rem
        }
        /* Button linking to product detail page */
        .view-btn{
            display:block;width:100%;padding:1rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;text-align:center;text-decoration:none;
            border-radius:12px;font-weight:600;transition:all .3s
        }
        .view-btn:hover{transform:translateY(-2px)}
        /* Container for message when no products match the search */
        .no-results{
            text-align:center;padding:4rem 2rem;background:#fff;
            border-radius:25px;box-shadow:0 15px 40px rgba(0,0,0,.1)
        }
        .no-results h2{font-size:2rem;margin-bottom:1rem;color:#2c3e50}
        .no-results p{color:#666;margin-bottom:2rem;font-size:1.1rem}
        /* Button to take user back to browse collections */
        .shop-btn{
            display:inline-block;padding:1rem 2rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;text-decoration:none;border-radius:15px;
            font-weight:600;transition:transform .2s
        }
        .shop-btn:hover{transform:translateY(-2px)}
        /* Container when no query has been entered yet */
        .empty-search{
            text-align:center;padding:4rem 2rem;background:#fff;
            border-radius:25px;box-shadow:0 15px 40px rgba(0,0,0,.1)
        }
        .empty-search h2{font-size:2rem;margin-bottom:1rem;color:#2c3e50}
        .empty-search p{color:#666;font-size:1.1rem}
        /* Responsive padding on smaller screens */
        @media(max-width:768px){
            .container{padding:2rem 1.5rem}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <?php if (!empty($query)): ?>
        <!-- Header showing current search term and result count -->
        <div class="search-header">
            <h1>Search Results</h1>
            <div class="search-info">
                Showing results for <span class="search-query">"<?php echo htmlspecialchars($query); ?>"</span>
                <?php if ($results): ?>
                    <span>(<?php echo mysqli_num_rows($results); ?> products found)</span>
                <?php endif; ?>
            </div>
            <form method="GET" class="search-controls">
                <input type="hidden" name="q" value="<?php echo htmlspecialchars($query); ?>">
                <label for="sort">Sort by</label>
                <select name="sort" id="sort">
                    <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name</option>
                    <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>Price: High to Low</option>
                    <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
                </select>
                <button type="submit">Apply</button>
            </form>
        </div>

        <?php if ($results && mysqli_num_rows($results) > 0): ?>
            <!-- Grid of matching products -->
            <div class="products-grid">
                <?php while ($product = mysqli_fetch_assoc($results)): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="product-badge">AVAILABLE</span>
                            <img src="images/<?php echo htmlspecialchars($product['sku']); ?>.jpg"
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 onerror="this.src='https://images.pexels.com/photos/996329/pexels-photo-996329.jpeg?auto=compress&cs=tinysrgb&w=400'">
                        </div>
                        <div class="product-info">
                            <!-- Product name -->
                            <h3 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h3>

                            <!-- Short product description if available -->
                            <?php if (!empty($product['description'])): ?>
                                <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                            <?php endif; ?>

                            <!-- Product base price and link to detail page -->
                            <div class="product-price">$<?php echo number_format($product['base_price'], 2); ?></div>
                            <a href="product.php?id=<?php echo $product['product_id']; ?>" class="view-btn">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <!-- Message shown when no products match the query -->
            <div class="no-results">
                <h2>No Results Found</h2>
                <p>We couldn't find any products matching "<?php echo htmlspecialchars($query); ?>"</p>
                <p style="margin-bottom:2rem">Try searching with different keywords or browse our collections</p>
                <a href="index.php" class="shop-btn">Browse Collections</a>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- Message shown when there is no search term -->
        <div class="empty-search">
            <h2>Search Products</h2>
            <p>Please enter a search term to find products</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
