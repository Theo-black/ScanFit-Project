<?php
$collectionTitle = $collectionTitle ?? 'Collection';
$collectionGender = $collectionGender ?? 'Unisex';
$collectionSubtitle = $collectionSubtitle ?? '';
$collectionHeroImage = $collectionHeroImage ?? '';
$collectionAccent = $collectionAccent ?? '#667eea';

$filters = [
    'size_id' => (int)($_GET['size_id'] ?? 0),
    'colour_id' => (int)($_GET['colour_id'] ?? 0),
    'min_price' => trim((string)($_GET['min_price'] ?? '')),
    'max_price' => trim((string)($_GET['max_price'] ?? '')),
    'sort' => (string)($_GET['sort'] ?? 'newest'),
];
if (!in_array($filters['sort'], ['newest', 'name_asc', 'price_asc', 'price_desc'], true)) {
    $filters['sort'] = 'newest';
}

$filterOptions = getCollectionFilterOptions($collectionGender);
$productsResult = getFilteredProductsByGender($collectionGender, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($collectionTitle); ?> - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f6f7f9;color:#1f2937;line-height:1.6}
        .hero-section{min-height:34vh;display:flex;flex-direction:column;align-items:center;justify-content:center;background:linear-gradient(rgba(0,0,0,.48),rgba(0,0,0,.48)),url('<?php echo htmlspecialchars($collectionHeroImage); ?>') center/cover no-repeat;color:#fff;text-align:center;padding:4rem 1.25rem}
        .hero-section h1{font-size:clamp(2rem,5vw,3.4rem);margin-bottom:.5rem}
        .hero-section p{max-width:620px;opacity:.92}
        .products-section{max-width:1400px;margin:0 auto;padding:2rem}
        .toolbar{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:1rem;margin-bottom:1.5rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:.8rem;align-items:end}
        label{font-size:.8rem;font-weight:700;color:#4b5563;display:grid;gap:.3rem}
        select,input{width:100%;border:1px solid #d1d5db;border-radius:8px;padding:.65rem;background:#fff;color:#111827}
        .actions{display:flex;gap:.5rem;align-items:center}
        button,.clear-link{border:none;border-radius:8px;padding:.68rem .9rem;font-weight:800;cursor:pointer;text-align:center;text-decoration:none}
        button{background:<?php echo htmlspecialchars($collectionAccent); ?>;color:#fff}
        .clear-link{background:#eef2f7;color:#374151}
        .products-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem}
        .product-card{background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e5e7eb;box-shadow:0 8px 22px rgba(15,23,42,.08);display:flex;flex-direction:column}
        .product-image{height:260px;background:#f0f2f5;overflow:hidden}
        .product-image img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}
        .product-card:hover .product-image img{transform:scale(1.04)}
        .product-info{padding:1rem;display:flex;flex-direction:column;gap:.65rem;flex:1}
        .product-name{font-size:1.1rem;font-weight:750;color:#111827}
        .product-price{font-size:1.25rem;font-weight:800;color:<?php echo htmlspecialchars($collectionAccent); ?>}
        .product-sizes{display:flex;flex-wrap:wrap;gap:.35rem;min-height:30px}
        .size-tag{display:inline-block;background:#f8fafc;color:#475569;padding:.25rem .55rem;border-radius:999px;border:1px solid #e2e8f0;font-size:.78rem}
        .details-btn{margin-top:auto;width:100%;padding:.75rem;border-radius:8px;background:<?php echo htmlspecialchars($collectionAccent); ?>;color:#fff;text-decoration:none;font-weight:800;text-align:center}
        .empty{background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:2rem;text-align:center;color:#4b5563}
        @media(max-width:640px){.products-section{padding:1rem}.actions{grid-column:1/-1}.hero-section{min-height:28vh}}
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="hero-section">
    <h1><?php echo htmlspecialchars($collectionTitle); ?></h1>
    <?php if ($collectionSubtitle !== ''): ?>
        <p><?php echo htmlspecialchars($collectionSubtitle); ?></p>
    <?php endif; ?>
</section>

<section class="products-section">
    <form method="GET" class="toolbar">
        <label>Size
            <select name="size_id">
                <option value="0">All sizes</option>
                <?php foreach ($filterOptions['sizes'] as $size): ?>
                    <option value="<?php echo (int)$size['size_id']; ?>" <?php echo $filters['size_id'] === (int)$size['size_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$size['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Color
            <select name="colour_id">
                <option value="0">All colors</option>
                <?php foreach ($filterOptions['colours'] as $colour): ?>
                    <option value="<?php echo (int)$colour['colour_id']; ?>" <?php echo $filters['colour_id'] === (int)$colour['colour_id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars((string)$colour['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Min price
            <input type="number" name="min_price" min="0" step="0.01" value="<?php echo htmlspecialchars($filters['min_price']); ?>">
        </label>
        <label>Max price
            <input type="number" name="max_price" min="0" step="0.01" value="<?php echo htmlspecialchars($filters['max_price']); ?>">
        </label>
        <label>Sort
            <select name="sort">
                <option value="newest" <?php echo $filters['sort'] === 'newest' ? 'selected' : ''; ?>>Newest</option>
                <option value="name_asc" <?php echo $filters['sort'] === 'name_asc' ? 'selected' : ''; ?>>Name</option>
                <option value="price_asc" <?php echo $filters['sort'] === 'price_asc' ? 'selected' : ''; ?>>Price low to high</option>
                <option value="price_desc" <?php echo $filters['sort'] === 'price_desc' ? 'selected' : ''; ?>>Price high to low</option>
            </select>
        </label>
        <div class="actions">
            <button type="submit">Apply</button>
            <a class="clear-link" href="<?php echo htmlspecialchars(basename($_SERVER['PHP_SELF'])); ?>">Clear</a>
        </div>
    </form>

    <?php if ($productsResult && mysqli_num_rows($productsResult) > 0): ?>
        <div class="products-grid">
            <?php while ($product = mysqli_fetch_assoc($productsResult)): ?>
                <?php
                $variantsResult = getProductVariants((int)$product['product_id']);
                $sizes = [];
                while ($variant = mysqli_fetch_assoc($variantsResult)) {
                    if (!empty($variant['size_name'])) {
                        $sizes[] = (string)$variant['size_name'];
                    }
                }
                ?>
                <div class="product-card">
                    <div class="product-image">
                        <img src="images/<?php echo htmlspecialchars((string)$product['sku']); ?>.jpg"
                             alt="<?php echo htmlspecialchars((string)$product['name']); ?>"
                             onerror="this.src='https://via.placeholder.com/400x260/f8f9fa/666?text=ScanFit'">
                    </div>
                    <div class="product-info">
                        <h3 class="product-name"><?php echo htmlspecialchars((string)$product['name']); ?></h3>
                        <div class="product-price">$<?php echo number_format((float)$product['base_price'], 2); ?></div>
                        <div class="product-sizes">
                            <?php foreach (array_slice(array_unique($sizes), 0, 5) as $size): ?>
                                <span class="size-tag"><?php echo htmlspecialchars($size); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <a href="product.php?id=<?php echo (int)$product['product_id']; ?>" class="details-btn">View Details</a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty">No products match these filters.</div>
    <?php endif; ?>
</section>
</body>
</html>
