<?php
// product_edit_admin.php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);
global $conn;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: products_admin.php');
    exit();
}

$sizeHasCode = false;
$sizeRows = false;
try {
    $sizeRows = mysqli_query($conn, "SELECT size_id, name, code, sort_order FROM size ORDER BY sort_order, name");
    $sizeHasCode = true;
} catch (Throwable $e) {
    try {
        $sizeRows = mysqli_query($conn, "SELECT size_id, name, sort_order FROM size ORDER BY sort_order, name");
    } catch (Throwable $e2) {
        $sizeRows = mysqli_query($conn, "SELECT size_id, name FROM size ORDER BY name");
    }
}
$sizes = [];
$sizeById = [];
if ($sizeRows) {
    while ($sizeRow = mysqli_fetch_assoc($sizeRows)) {
        $sizeRow['size_id'] = (int)$sizeRow['size_id'];
        if (!isset($sizeRow['code']) || trim((string)$sizeRow['code']) === '') {
            $sizeRow['code'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string)$sizeRow['name']));
            if ($sizeRow['code'] === '') {
                $sizeRow['code'] = 'SIZE' . $sizeRow['size_id'];
            }
        }
        $sizes[] = $sizeRow;
        $sizeById[$sizeRow['size_id']] = $sizeRow;
    }
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('product_edit_admin.php?id=' . $id);

    $formAction = $_POST['form_action'] ?? 'update_product';

    if ($formAction === 'update_variant_stock') {
        $variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
        $stockQty = isset($_POST['stock_quantity']) ? (int)$_POST['stock_quantity'] : -1;

        if ($variantId <= 0 || $stockQty < 0) {
            $error = 'Please enter a valid variant and stock quantity.';
        } else {
            $sql = "UPDATE productvariant SET stock_quantity = ? WHERE variant_id = ? AND product_id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                $error = 'Database error: could not update stock.';
            } else {
                mysqli_stmt_bind_param($stmt, 'iii', $stockQty, $variantId, $id);
                mysqli_stmt_execute($stmt);

                if (mysqli_stmt_affected_rows($stmt) > 0) {
                    header('Location: product_edit_admin.php?id=' . $id . '&stock_updated=1');
                    exit();
                }
                $error = 'No stock update applied. Confirm this variant belongs to the product.';
            }
        }
    } else {
        $name = trim($_POST['name'] ?? '');
        $sku = trim($_POST['sku'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $basePrice = isset($_POST['base_price']) ? (float)$_POST['base_price'] : 0;
        $status = $_POST['status'] ?? 'ACTIVE';
        $size_ids_raw = $_POST['size_ids'] ?? [];
        $selectedSizeIds = [];
        if (is_array($size_ids_raw)) {
            foreach ($size_ids_raw as $sizeIdRaw) {
                $sizeId = (int)$sizeIdRaw;
                if ($sizeId > 0 && isset($sizeById[$sizeId])) {
                    $selectedSizeIds[$sizeId] = $sizeId;
                }
            }
        }

        if ($name === '' || $sku === '' || $basePrice <= 0) {
            $error = 'Name, SKU and base price are required.';
        } elseif (empty($selectedSizeIds)) {
            $error = 'Select at least one product size.';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $sql = "UPDATE product
                        SET name = ?, sku = ?, description = ?, base_price = ?, status = ?
                        WHERE product_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                if (!$stmt) {
                    throw new Exception('Database error: could not prepare product update.');
                }
                mysqli_stmt_bind_param($stmt, 'sssdsi', $name, $sku, $description, $basePrice, $status, $id);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to update product details.');
                }

                // Existing size IDs from variants
                $existingSizeIds = [];
                $existingSizeSql = "SELECT DISTINCT size_id FROM productvariant WHERE product_id = ? AND size_id IS NOT NULL";
                $existingSizeStmt = mysqli_prepare($conn, $existingSizeSql);
                if (!$existingSizeStmt) {
                    throw new Exception('Failed to load existing product sizes.');
                }
                mysqli_stmt_bind_param($existingSizeStmt, 'i', $id);
                mysqli_stmt_execute($existingSizeStmt);
                $existingSizeRes = mysqli_stmt_get_result($existingSizeStmt);
                while ($existingRow = mysqli_fetch_assoc($existingSizeRes)) {
                    $existingId = (int)($existingRow['size_id'] ?? 0);
                    if ($existingId > 0) {
                        $existingSizeIds[$existingId] = $existingId;
                    }
                }

                $selectedIds = array_values($selectedSizeIds);
                $existingIds = array_values($existingSizeIds);
                $toAdd = array_diff($selectedIds, $existingIds);
                $toRemove = array_diff($existingIds, $selectedIds);

                // Add missing selected sizes
                if (!empty($toAdd)) {
                    $insertVarSql = "INSERT INTO productvariant (product_id, size_id, colour_id, sku, stock_quantity, price_adjustment, created_at)
                                     VALUES (?, ?, NULL, ?, 0, 0.00, NOW())";
                    $insertVarStmt = mysqli_prepare($conn, $insertVarSql);
                    if (!$insertVarStmt) {
                        throw new Exception('Failed to prepare size variant creation.');
                    }
                    foreach ($toAdd as $sizeId) {
                        $sizeId = (int)$sizeId;
                        $sizeCode = strtoupper((string)($sizeById[$sizeId]['code'] ?? ('SIZE' . $sizeId)));
                        $variantSku = $sku . '-' . $sizeCode;
                        mysqli_stmt_bind_param($insertVarStmt, 'iis', $id, $sizeId, $variantSku);
                        if (!mysqli_stmt_execute($insertVarStmt)) {
                            throw new Exception('Failed to create variants for selected sizes.');
                        }
                    }
                }

                // Remove unselected sizes if not used in orders; otherwise keep but force stock to zero.
                if (!empty($toRemove)) {
                    $deleteVarSql = "
                        DELETE pv
                        FROM productvariant pv
                        LEFT JOIN orderitem oi ON oi.variant_id = pv.variant_id
                        WHERE pv.product_id = ?
                          AND pv.size_id = ?
                          AND oi.order_item_id IS NULL
                    ";
                    $deleteVarStmt = mysqli_prepare($conn, $deleteVarSql);
                    $zeroStockSql = "UPDATE productvariant SET stock_quantity = 0 WHERE product_id = ? AND size_id = ?";
                    $zeroStockStmt = mysqli_prepare($conn, $zeroStockSql);
                    if (!$deleteVarStmt || !$zeroStockStmt) {
                        throw new Exception('Failed to update removed sizes.');
                    }
                    foreach ($toRemove as $sizeId) {
                        $sizeId = (int)$sizeId;
                        mysqli_stmt_bind_param($deleteVarStmt, 'ii', $id, $sizeId);
                        mysqli_stmt_execute($deleteVarStmt);

                        mysqli_stmt_bind_param($zeroStockStmt, 'ii', $id, $sizeId);
                        mysqli_stmt_execute($zeroStockStmt);
                    }
                }

                if (!saveProductImageUpload($_FILES['product_image'] ?? [], $sku, $imageError)) {
                    throw new Exception($imageError ?? 'Could not save product image.');
                }
                if (
                    (($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) &&
                    $sku !== (string)$product['sku']
                ) {
                    $oldImage = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/', '', (string)$product['sku']) . '.jpg';
                    $newImage = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . preg_replace('/[^A-Za-z0-9._-]/', '', $sku) . '.jpg';
                    if (is_file($oldImage) && !is_file($newImage)) {
                        @rename($oldImage, $newImage);
                    }
                }

                mysqli_commit($conn);
                header('Location: products_admin.php');
                exit();
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $error = $e->getMessage();
            }
        }
    }
}

if (isset($_GET['stock_updated']) && $_GET['stock_updated'] === '1') {
    $success = 'Variant stock updated.';
}

$sql = "SELECT * FROM product WHERE product_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    exit('Database error while loading product.');
}
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);

if (!$product) {
    header('Location: products_admin.php');
    exit();
}

$selectedProductSizeIds = [];
$productSizeStmt = mysqli_prepare($conn, "SELECT DISTINCT size_id FROM productvariant WHERE product_id = ? AND size_id IS NOT NULL");
if ($productSizeStmt) {
    mysqli_stmt_bind_param($productSizeStmt, 'i', $id);
    mysqli_stmt_execute($productSizeStmt);
    $productSizeRes = mysqli_stmt_get_result($productSizeStmt);
    while ($sizeRow = mysqli_fetch_assoc($productSizeRes)) {
        $sid = (int)($sizeRow['size_id'] ?? 0);
        if ($sid > 0) {
            $selectedProductSizeIds[$sid] = true;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_action'] ?? 'update_product') === 'update_product') {
    $selectedProductSizeIds = [];
    if (isset($_POST['size_ids']) && is_array($_POST['size_ids'])) {
        foreach ($_POST['size_ids'] as $postedSizeId) {
            $sid = (int)$postedSizeId;
            if ($sid > 0 && isset($sizeById[$sid])) {
                $selectedProductSizeIds[$sid] = true;
            }
        }
    }
}

$variantSql = "
    SELECT
        pv.variant_id,
        pv.sku AS variant_sku,
        pv.stock_quantity,
        pv.price_adjustment,
        s.name AS size_name,
        c.name AS colour_name
    FROM productvariant pv
    LEFT JOIN size s ON pv.size_id = s.size_id
    LEFT JOIN colour c ON pv.colour_id = c.colour_id
    WHERE pv.product_id = ?
    ORDER BY s.sort_order ASC, s.name ASC, c.name ASC, pv.variant_id ASC
";
$variantStmt = mysqli_prepare($conn, $variantSql);
$variants = [];
if ($variantStmt) {
    mysqli_stmt_bind_param($variantStmt, 'i', $id);
    mysqli_stmt_execute($variantStmt);
    $variantRes = mysqli_stmt_get_result($variantStmt);
    while ($row = mysqli_fetch_assoc($variantRes)) {
        $variants[] = $row;
    }
}

$availableSizeNames = [];
foreach ($variants as $variant) {
    $stock = (int)($variant['stock_quantity'] ?? 0);
    $sizeName = trim((string)($variant['size_name'] ?? ''));
    if ($stock > 0 && $sizeName !== '') {
        $availableSizeNames[$sizeName] = true;
    }
}
$availableSizesList = implode(', ', array_keys($availableSizeNames));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Product - Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:'Segoe UI',sans-serif;
            background:#0f172a;
            color:#e5e7eb;
            padding:2rem;
        }
        a{text-decoration:none;color:#38bdf8}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;font-size:.85rem;color:#9ca3af}
        h1{font-size:1.8rem;margin-bottom:1rem}
        .card{
            max-width:900px;
            background:#020617;
            border-radius:16px;
            padding:1.8rem;
            border:1px solid #111827;
            margin-bottom:1.25rem;
        }
        .subheading{
            font-size:1.1rem;
            margin-bottom:.9rem;
        }
        .form-group{margin-bottom:1rem}
        label{
            display:block;
            margin-bottom:.25rem;
            font-size:.9rem;
            color:#9ca3af;
        }
        input[type=text],input[type=number],textarea,select{
            width:100%;
            padding:.6rem .7rem;
            border-radius:8px;
            border:1px solid #1f2937;
            background:#020617;
            color:#e5e7eb;
            font-size:.9rem;
        }
        textarea{min-height:100px;resize:vertical}
        .row{display:flex;gap:1rem}
        .row .form-group{flex:1}
        .btn{
            padding:.55rem 1rem;
            border:none;
            border-radius:999px;
            font-size:.85rem;
            cursor:pointer;
            font-weight:600;
            display:inline-block;
        }
        .btn-primary{background:#38bdf8;color:#0f172a}
        .btn-secondary{background:#111827;color:#e5e7eb;margin-left:.6rem}
        .btn-stock{background:#22c55e;color:#052e16}
        .msg{
            margin-bottom:1rem;
            padding:.7rem .9rem;
            border-radius:10px;
            font-size:.85rem;
        }
        .msg-error{background:#b91c1c;color:#fee2e2}
        .msg-success{background:#14532d;color:#dcfce7}
        .stock-summary{
            font-size:.9rem;
            color:#cbd5e1;
            margin-bottom:1rem;
        }
        table{
            width:100%;
            border-collapse:collapse;
            margin-top:.4rem;
            border:1px solid #1f2937;
        }
        th, td{
            border-bottom:1px solid #1f2937;
            padding:.6rem;
            text-align:left;
            font-size:.85rem;
            vertical-align:middle;
        }
        th{color:#94a3b8;background:#0b1220}
        .inline-form{
            display:flex;
            gap:.5rem;
            align-items:center;
        }
        .inline-form input[type=number]{
            width:95px;
        }
        .badge{
            display:inline-block;
            padding:.15rem .5rem;
            border-radius:999px;
            font-size:.75rem;
        }
        .badge-in{background:#166534;color:#dcfce7}
        .badge-out{background:#7f1d1d;color:#fee2e2}
        .size-grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(140px,1fr));
            gap:.55rem;
            margin-top:.45rem;
        }
        .size-item{
            display:flex;
            align-items:center;
            gap:.5rem;
            border:1px solid #1f2937;
            border-radius:10px;
            padding:.45rem .55rem;
            cursor:pointer;
            background:#0b1220;
            color:#e5e7eb;
        }
        .field-note{
            display:block;
            margin-top:.35rem;
            color:#94a3b8;
            font-size:.8rem;
        }
    </style>
</head>
<body>
<div class="nav">
    <a href="products_admin.php">Back to products</a>
</div>

<h1>Edit Product #<?php echo (int)$product['product_id']; ?></h1>

<div class="card">
    <?php if ($error): ?>
        <div class="msg msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="msg msg-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <?php echo csrfInput(); ?>
        <input type="hidden" name="form_action" value="update_product">
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
        </div>

        <div class="row">
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" value="<?php echo htmlspecialchars($product['sku']); ?>" required>
            </div>
            <div class="form-group">
                <label>Base price (USD)</label>
                <input type="number" step="0.01" name="base_price" value="<?php echo htmlspecialchars((string)$product['base_price']); ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="ACTIVE" <?php echo $product['status'] === 'ACTIVE' ? 'selected' : ''; ?>>ACTIVE</option>
                <option value="INACTIVE" <?php echo $product['status'] === 'INACTIVE' ? 'selected' : ''; ?>>INACTIVE</option>
                <option value="OUT_OF_STOCK" <?php echo $product['status'] === 'OUT_OF_STOCK' ? 'selected' : ''; ?>>OUT OF STOCK</option>
            </select>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars((string)$product['description']); ?></textarea>
        </div>

        <div class="form-group">
            <label>Product Image</label>
            <div style="margin:.4rem 0;">
                <img src="images/<?php echo htmlspecialchars((string)$product['sku']); ?>.jpg"
                     alt="Current product image"
                     style="max-width:180px;border-radius:10px;background:#111827;"
                     onerror="this.style.display='none';">
            </div>
            <input type="file" name="product_image" accept="image/jpeg">
            <small class="field-note">Upload a JPEG image to replace images/<?php echo htmlspecialchars((string)$product['sku']); ?>.jpg.</small>
        </div>

        <div class="form-group">
            <label>Product Sizes</label>
            <div class="size-grid">
                <?php foreach ($sizes as $size): ?>
                    <?php
                    $sid = (int)$size['size_id'];
                    $isChecked = isset($selectedProductSizeIds[$sid]);
                    ?>
                    <label class="size-item">
                        <input type="checkbox" name="size_ids[]" value="<?php echo $sid; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                        <span><?php echo htmlspecialchars($size['name']); ?> (<?php echo htmlspecialchars($size['code']); ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small class="field-note">Selected sizes are available for this product. Unselected sizes are removed when possible.</small>
        </div>

        <button type="submit" class="btn btn-primary">Save changes</button>
        <a href="products_admin.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>

<div class="card" id="stock-section">
    <h2 class="subheading">Size Availability In Stock</h2>
    <p class="stock-summary">
        <?php if ($availableSizesList !== ''): ?>
            Available sizes in stock: <?php echo htmlspecialchars($availableSizesList); ?>
        <?php else: ?>
            No sizes currently in stock for this product.
        <?php endif; ?>
    </p>

    <?php if (empty($variants)): ?>
        <div class="msg msg-error">No variants found for this product yet.</div>
    <?php else: ?>
        <table>
            <thead>
            <tr>
                <th>Variant ID</th>
                <th>Size</th>
                <th>Color</th>
                <th>Variant SKU</th>
                <th>Price Adj.</th>
                <th>Stock</th>
                <th>Availability</th>
                <th>Update Stock</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($variants as $variant): ?>
                <?php $stock = (int)($variant['stock_quantity'] ?? 0); ?>
                <tr>
                    <td><?php echo (int)$variant['variant_id']; ?></td>
                    <td><?php echo htmlspecialchars($variant['size_name'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($variant['colour_name'] ?: 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($variant['variant_sku'] ?: 'N/A'); ?></td>
                    <td><?php echo number_format((float)($variant['price_adjustment'] ?? 0), 2); ?></td>
                    <td><?php echo $stock; ?></td>
                    <td>
                        <?php if ($stock > 0): ?>
                            <span class="badge badge-in">IN STOCK</span>
                        <?php else: ?>
                            <span class="badge badge-out">OUT</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" class="inline-form">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="form_action" value="update_variant_stock">
                            <input type="hidden" name="variant_id" value="<?php echo (int)$variant['variant_id']; ?>">
                            <input type="number" name="stock_quantity" min="0" value="<?php echo $stock; ?>" required>
                            <button type="submit" class="btn btn-stock">Save</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
