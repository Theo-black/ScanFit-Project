<?php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);
global $conn;

$successMsg = $_SESSION['success'] ?? null;
$errorMsg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('catalog_settings_admin.php');
    $type = (string)($_POST['type'] ?? '');

    try {
        if ($type === 'category') {
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            if ($name === '') {
                throw new Exception('Category name is required.');
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO category (name, description, created_at) VALUES (?, ?, NOW())");
            if (!$stmt) throw new Exception('Could not prepare category.');
            mysqli_stmt_bind_param($stmt, 'ss', $name, $description);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Category added.';
        } elseif ($type === 'size') {
            $name = trim((string)($_POST['name'] ?? ''));
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $sortOrder = (int)($_POST['sort_order'] ?? 0);
            if ($name === '' || $code === '') {
                throw new Exception('Size name and code are required.');
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO size (name, code, sort_order, created_at) VALUES (?, ?, ?, NOW())");
            if (!$stmt) throw new Exception('Could not prepare size.');
            mysqli_stmt_bind_param($stmt, 'ssi', $name, $code, $sortOrder);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Size added.';
        } elseif ($type === 'colour') {
            $name = trim((string)($_POST['name'] ?? ''));
            $hex = trim((string)($_POST['hex_code'] ?? ''));
            if ($name === '' || !preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                throw new Exception('Color name and valid hex code are required.');
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO colour (name, hex_code, created_at) VALUES (?, ?, NOW())");
            if (!$stmt) throw new Exception('Could not prepare color.');
            mysqli_stmt_bind_param($stmt, 'ss', $name, $hex);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Color added.';
        } elseif ($type === 'country') {
            $name = trim((string)($_POST['name'] ?? ''));
            $isoCode = strtoupper(trim((string)($_POST['iso_code'] ?? '')));
            if ($name === '' || $isoCode === '') {
                throw new Exception('Country name and code are required.');
            }
            $stmt = mysqli_prepare($conn, "INSERT INTO country (name, iso_code, created_at) VALUES (?, ?, NOW())");
            if (!$stmt) throw new Exception('Could not prepare country.');
            mysqli_stmt_bind_param($stmt, 'ss', $name, $isoCode);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = 'Country added.';
        } else {
            throw new Exception('Invalid catalog action.');
        }
    } catch (Throwable $e) {
        $_SESSION['error'] = $e->getMessage();
    }

    header('Location: catalog_settings_admin.php');
    exit();
}

$categories = mysqli_query($conn, "SELECT * FROM category ORDER BY name");
$sizes = mysqli_query($conn, "SELECT * FROM size ORDER BY sort_order, name");
$colours = mysqli_query($conn, "SELECT * FROM colour ORDER BY name");
$countries = mysqli_query($conn, "SELECT * FROM country ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Catalog Settings - ScanFit Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',sans-serif;background:#0f172a;color:#e5e7eb;padding:2rem}
        a{color:#38bdf8;text-decoration:none}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;color:#9ca3af;font-size:.9rem}
        h1{font-size:1.8rem;margin-bottom:1rem}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1rem}
        .card{background:#020617;border:1px solid #1f2937;border-radius:14px;padding:1rem}
        h2{font-size:1rem;margin-bottom:.8rem;color:#e5e7eb}
        form{display:grid;gap:.55rem;margin-bottom:1rem}
        input,textarea{width:100%;padding:.55rem .65rem;border-radius:8px;border:1px solid #1f2937;background:#0b1220;color:#e5e7eb}
        textarea{min-height:70px;resize:vertical}
        button{border:none;border-radius:999px;padding:.55rem .85rem;background:#38bdf8;color:#0f172a;font-weight:800;cursor:pointer;width:max-content}
        table{width:100%;border-collapse:collapse}
        th,td{padding:.5rem;border-top:1px solid #111827;text-align:left;font-size:.85rem}
        th{color:#9ca3af}
        .msg{padding:.75rem 1rem;border-radius:8px;margin-bottom:1rem}
        .ok{background:#14532d;color:#bbf7d0}
        .err{background:#7f1d1d;color:#fecaca}
        .swatch{display:inline-block;width:18px;height:18px;border-radius:4px;border:1px solid #334155;vertical-align:middle;margin-right:.35rem}
    </style>
</head>
<body>
<div class="nav"><a href="admin_dashboard.php">Back to Dashboard</a> / Catalog Settings</div>
<h1>Catalog Settings</h1>
<?php if ($successMsg): ?><div class="msg ok"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
<?php if ($errorMsg): ?><div class="msg err"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

<div class="grid">
    <div class="card">
        <h2>Categories</h2>
        <form method="POST">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="type" value="category">
            <input type="text" name="name" placeholder="Category name" required>
            <textarea name="description" placeholder="Description"></textarea>
            <button type="submit">Add Category</button>
        </form>
        <table><tr><th>Name</th><th>Description</th></tr>
            <?php while ($row = mysqli_fetch_assoc($categories)): ?>
                <tr><td><?php echo htmlspecialchars((string)$row['name']); ?></td><td><?php echo htmlspecialchars((string)($row['description'] ?? '')); ?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="card">
        <h2>Sizes</h2>
        <form method="POST">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="type" value="size">
            <input type="text" name="name" placeholder="Size name" required>
            <input type="text" name="code" placeholder="Code, e.g. XL" required>
            <input type="number" name="sort_order" placeholder="Sort order" value="0">
            <button type="submit">Add Size</button>
        </form>
        <table><tr><th>Name</th><th>Code</th><th>Sort</th></tr>
            <?php while ($row = mysqli_fetch_assoc($sizes)): ?>
                <tr><td><?php echo htmlspecialchars((string)$row['name']); ?></td><td><?php echo htmlspecialchars((string)($row['code'] ?? '')); ?></td><td><?php echo (int)($row['sort_order'] ?? 0); ?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="card">
        <h2>Colors</h2>
        <form method="POST">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="type" value="colour">
            <input type="text" name="name" placeholder="Color name" required>
            <input type="text" name="hex_code" placeholder="#000000" required>
            <button type="submit">Add Color</button>
        </form>
        <table><tr><th>Name</th><th>Hex</th></tr>
            <?php while ($row = mysqli_fetch_assoc($colours)): ?>
                <tr><td><span class="swatch" style="background:<?php echo htmlspecialchars((string)$row['hex_code']); ?>"></span><?php echo htmlspecialchars((string)$row['name']); ?></td><td><?php echo htmlspecialchars((string)$row['hex_code']); ?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="card">
        <h2>Countries</h2>
        <form method="POST">
            <?php echo csrfInput(); ?>
            <input type="hidden" name="type" value="country">
            <input type="text" name="name" placeholder="Country name" required>
            <input type="text" name="iso_code" placeholder="Code, e.g. JM" required>
            <button type="submit">Add Country</button>
        </form>
        <table><tr><th>Name</th><th>Code</th></tr>
            <?php while ($row = mysqli_fetch_assoc($countries)): ?>
                <tr><td><?php echo htmlspecialchars((string)$row['name']); ?></td><td><?php echo htmlspecialchars((string)$row['iso_code']); ?></td></tr>
            <?php endwhile; ?>
        </table>
    </div>
</div>
</body>
</html>
