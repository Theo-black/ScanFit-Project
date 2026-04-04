<?php
// product_add_admin.php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);
global $conn;

// Fetch categories and genders for dropdowns
$categories = mysqli_query($conn, "SELECT category_id, name FROM category ORDER BY name");
$genders    = mysqli_query($conn, "SELECT gender_id, name FROM gender ORDER BY name");
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
$sizes      = [];
$sizeById   = [];
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

// Handle POST: insert product + mapping rows
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('product_add_admin.php');

    $name        = trim($_POST['name'] ?? '');
    $sku         = trim($_POST['sku'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $base_price  = isset($_POST['base_price']) ? (float)$_POST['base_price'] : 0;
    $status      = $_POST['status'] ?? 'ACTIVE';
    $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $gender_id   = isset($_POST['gender_id']) ? (int)$_POST['gender_id'] : 0;
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

    if ($name === '' || $sku === '' || $base_price <= 0 || $category_id <= 0 || $gender_id <= 0) {
        $error = 'Name, SKU, price, category, and gender are required';
    } elseif (empty($selectedSizeIds)) {
        $error = 'Select at least one product size.';
    } else {
        mysqli_begin_transaction($conn);
        $created = false;
        try {
            // Insert product
            $sql = "INSERT INTO product (name, sku, description, base_price, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception('Database error: could not prepare product statement.');
            }

            mysqli_stmt_bind_param(
                $stmt,
                'sssds',
                $name,
                $sku,
                $description,
                $base_price,
                $status
            );

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to insert product (SKU might already exist).');
            }
            $product_id = (int)mysqli_insert_id($conn);

            // Map to category
            $sqlCat = "INSERT INTO productcategory (product_id, category_id) VALUES (?, ?)";
            $stmtCat = mysqli_prepare($conn, $sqlCat);
            if (!$stmtCat) {
                throw new Exception('Failed to map category.');
            }
            mysqli_stmt_bind_param($stmtCat, 'ii', $product_id, $category_id);
            if (!mysqli_stmt_execute($stmtCat)) {
                throw new Exception('Failed to map category.');
            }

            // Map to gender
            $sqlGen = "INSERT INTO productgender (product_id, gender_id) VALUES (?, ?)";
            $stmtGen = mysqli_prepare($conn, $sqlGen);
            if (!$stmtGen) {
                throw new Exception('Failed to map gender.');
            }
            mysqli_stmt_bind_param($stmtGen, 'ii', $product_id, $gender_id);
            if (!mysqli_stmt_execute($stmtGen)) {
                throw new Exception('Failed to map gender.');
            }

            // Create one variant per selected size
            $sqlVar = "INSERT INTO productvariant (product_id, size_id, colour_id, sku, stock_quantity, price_adjustment, created_at)
                       VALUES (?, ?, NULL, ?, 0, 0.00, NOW())";
            $stmtVar = mysqli_prepare($conn, $sqlVar);
            if (!$stmtVar) {
                throw new Exception('Failed to prepare product size variants.');
            }
            foreach ($selectedSizeIds as $sizeId) {
                $sizeCode = strtoupper((string)($sizeById[$sizeId]['code'] ?? ('SIZE' . $sizeId)));
                $variantSku = $sku . '-' . $sizeCode;
                mysqli_stmt_bind_param($stmtVar, 'iis', $product_id, $sizeId, $variantSku);
                if (!mysqli_stmt_execute($stmtVar)) {
                    throw new Exception('Failed to create variant for selected sizes.');
                }
            }

            mysqli_commit($conn);
            $created = true;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }

        if ($created) {
            // Redirect back to products list
            header('Location: products_admin.php');
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Product - Admin</title>
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
            max-width:650px;
            background:#020617;
            border-radius:16px;
            padding:1.8rem;
            border:1px solid #111827;
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
            padding:.6rem 1.4rem;
            border:none;
            border-radius:999px;
            font-size:.9rem;
            cursor:pointer;
            font-weight:600;
        }
        .btn-primary{background:#38bdf8;color:#0f172a}
        .btn-secondary{background:#111827;color:#e5e7eb;margin-left:.6rem}
        .msg{
            margin-bottom:1rem;
            padding:.7rem .9rem;
            border-radius:10px;
            font-size:.85rem;
        }
        .msg-error{background:#b91c1c;color:#fee2e2}
    </style>
</head>
<body>
<div class="nav">
    <a href="products_admin.php">← Back to products</a>
</div>

<h1>Add Product</h1>

<div class="card">
    <?php if ($error): ?>
        <div class="msg msg-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php echo csrfInput(); ?>
        <div class="form-group">
            <label>Name</label>
            <input type="text" name="name"
                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
        </div>

        <div class="row">
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku"
                       value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Base price (USD)</label>
                <input type="number" step="0.01" name="base_price"
                       value="<?php echo htmlspecialchars($_POST['base_price'] ?? ''); ?>" required>
            </div>
        </div>

        <div class="row">
            <div class="form-group">
                <label>Category</label>
                <select name="category_id" required>
                    <option value="">Select category</option>
                    <?php while ($c = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?php echo (int)$c['category_id']; ?>"
                            <?php echo (isset($_POST['category_id']) && (int)$_POST['category_id'] === (int)$c['category_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Gender</label>
                <select name="gender_id" required>
                    <option value="">Select gender</option>
                    <?php while ($g = mysqli_fetch_assoc($genders)): ?>
                        <option value="<?php echo (int)$g['gender_id']; ?>"
                            <?php echo (isset($_POST['gender_id']) && (int)$_POST['gender_id'] === (int)$g['gender_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($g['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status">
                <option value="ACTIVE" <?php echo (($_POST['status'] ?? 'ACTIVE')==='ACTIVE')?'selected':''; ?>>ACTIVE</option>
                <option value="INACTIVE" <?php echo (($_POST['status'] ?? '')==='INACTIVE')?'selected':''; ?>>INACTIVE</option>
                <option value="OUT_OF_STOCK" <?php echo (($_POST['status'] ?? '')==='OUT_OF_STOCK')?'selected':''; ?>>OUT OF STOCK</option>
            </select>
        </div>

        <div class="form-group">
            <label>Description</label>
            <textarea name="description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div class="form-group">
            <label>Available Sizes</label>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:.55rem;margin-top:.45rem;">
                <?php foreach ($sizes as $size): ?>
                    <?php
                    $sizeId = (int)$size['size_id'];
                    $selected = isset($_POST['size_ids']) && is_array($_POST['size_ids']) && in_array((string)$sizeId, array_map('strval', $_POST['size_ids']), true);
                    ?>
                    <label style="display:flex;align-items:center;gap:.5rem;border:1px solid #1f2937;border-radius:10px;padding:.45rem .55rem;cursor:pointer;background:#0b1220;color:#e5e7eb;">
                        <input type="checkbox" name="size_ids[]" value="<?php echo $sizeId; ?>" <?php echo $selected ? 'checked' : ''; ?>>
                        <span><?php echo htmlspecialchars($size['name']); ?> (<?php echo htmlspecialchars($size['code']); ?>)</span>
                    </label>
                <?php endforeach; ?>
            </div>
            <small style="display:block;margin-top:.35rem;color:#94a3b8;">At least one size is required. Stock can be set in Edit Product.</small>
        </div>

        <button type="submit" class="btn btn-primary">Add product</button>
        <a href="products_admin.php" class="btn btn-secondary">Cancel</a>
    </form>
</div>
</body>
</html>
