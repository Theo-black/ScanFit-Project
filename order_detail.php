<?php
require_once 'functions.php';
requireLogin();

$customerId = getCustomerId();
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$detail = getCustomerOrderDetail($orderId, $customerId);

if (!$detail) {
    $_SESSION['error'] = 'Order not found.';
    header('Location: orders.php');
    exit();
}

$order = $detail['order'];
$items = $detail['items'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_return') {
    requireCsrfPost('order_detail.php?id=' . $orderId);
    $orderItemId = (int)($_POST['order_item_id'] ?? 0);
    $reason = (string)($_POST['reason'] ?? '');
    if (createReturnRequest($orderId, $customerId, $orderItemId > 0 ? $orderItemId : null, $reason)) {
        $_SESSION['success'] = 'Return request submitted.';
    } else {
        $_SESSION['error'] = 'Unable to submit return request. Confirm the order is delivered and the reason is filled in.';
    }
    header('Location: order_detail.php?id=' . $orderId);
    exit();
}

$successMsg = $_SESSION['success'] ?? null;
$errorMsg = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
$returnRequests = getReturnRequestsForOrder($orderId, $customerId);
$cityLine = array_filter([
    (string)($order['city'] ?? ''),
    (string)($order['state_province'] ?? ''),
    (string)($order['postal_code'] ?? ''),
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Order #<?php echo (int)$order['order_id']; ?> - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:'Segoe UI',Tahoma,sans-serif;background:#f8f9fa;color:#1f2937;min-height:100vh}
        .container{max-width:1000px;margin:0 auto;padding:3rem 2rem}
        .topline{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem}
        a{color:#4f46e5;text-decoration:none;font-weight:700}
        a:hover{text-decoration:underline}
        h1{font-size:2rem;color:#172033}
        .card{background:#fff;border-radius:16px;padding:1.5rem;box-shadow:0 10px 30px rgba(0,0,0,.08);margin-bottom:1.25rem}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1rem}
        .label{font-size:.82rem;color:#6b7280;margin-bottom:.25rem}
        .value{font-weight:700;color:#172033}
        .status{display:inline-block;padding:.35rem .8rem;border-radius:999px;font-size:.82rem;font-weight:800;text-transform:uppercase}
        .status-pending{background:#fff3cd;color:#856404}
        .status-processing{background:#d1ecf1;color:#0c5460}
        .status-shipped,.status-delivered{background:#d4edda;color:#155724}
        .status-cancelled{background:#f8d7da;color:#721c24}
        table{width:100%;border-collapse:collapse}
        th,td{padding:.8rem;border-bottom:1px solid #e5e7eb;text-align:left}
        th{font-size:.82rem;color:#6b7280}
        .right{text-align:right}
        .total{font-size:1.2rem;font-weight:900}
        .actions{display:flex;gap:.75rem;flex-wrap:wrap;margin-top:1rem}
        .btn{display:inline-block;border:none;border-radius:10px;padding:.65rem 1rem;font-weight:800;cursor:pointer}
        .btn-print{background:#111827;color:#fff}
        .btn-danger{background:#ef4444;color:#fff}
        .btn-primary{background:#4f46e5;color:#fff}
        .msg{padding:.8rem 1rem;border-radius:10px;margin-bottom:1rem}
        .ok{background:#dcfce7;color:#14532d}
        .err{background:#fee2e2;color:#7f1d1d}
        .return-form{display:grid;gap:.75rem;margin-top:1rem}
        select,textarea{width:100%;padding:.75rem;border:1px solid #d1d5db;border-radius:10px;font:inherit}
        textarea{min-height:100px;resize:vertical}
        .return-row{padding:.85rem 0;border-top:1px solid #e5e7eb}
        .return-row:first-child{border-top:none}
        .return-status{display:inline-block;border-radius:999px;padding:.25rem .65rem;background:#eef2ff;color:#3730a3;font-weight:800;font-size:.78rem}
        @media print{
            .topline a,.actions,nav{display:none!important}
            body{background:#fff}
            .container{padding:0}
            .card{box-shadow:none;border:1px solid #e5e7eb}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>
<div class="container">
    <div class="topline">
        <div>
            <a href="orders.php">&larr; Back to orders</a>
            <h1>Order #<?php echo (int)$order['order_id']; ?></h1>
        </div>
        <button type="button" class="btn btn-print" onclick="window.print()">Print Receipt</button>
    </div>
    <?php if ($successMsg): ?><div class="msg ok"><?php echo htmlspecialchars($successMsg); ?></div><?php endif; ?>
    <?php if ($errorMsg): ?><div class="msg err"><?php echo htmlspecialchars($errorMsg); ?></div><?php endif; ?>

    <div class="card">
        <div class="grid">
            <div>
                <div class="label">Status</div>
                <div class="value">
                    <span class="status status-<?php echo htmlspecialchars(strtolower((string)$order['status'])); ?>">
                        <?php echo htmlspecialchars((string)$order['status']); ?>
                    </span>
                </div>
            </div>
            <div>
                <div class="label">Order Date</div>
                <div class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime((string)$order['order_date']))); ?></div>
            </div>
            <div>
                <div class="label">Payment</div>
                <div class="value">
                    <?php echo htmlspecialchars((string)($order['payment_method'] ?? 'N/A')); ?>
                    <?php if (!empty($order['payment_status'])): ?>
                        (<?php echo htmlspecialchars((string)$order['payment_status']); ?>)
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="label">Total</div>
                <div class="value">$<?php echo number_format((float)$order['total_amount'], 2); ?></div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="grid">
            <div>
                <div class="label">Ship To</div>
                <div class="value">
                    <?php if (!empty($order['address_line1'])): ?>
                        <?php echo htmlspecialchars((string)$order['address_line1']); ?><br>
                        <?php if (!empty($order['address_line2'])): ?>
                            <?php echo htmlspecialchars((string)$order['address_line2']); ?><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars(implode(', ', $cityLine)); ?><br>
                        <?php echo htmlspecialchars((string)($order['country_name'] ?? '')); ?>
                    <?php else: ?>
                        Address not available
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <div class="label">Tracking</div>
                <div class="value">
                    <?php echo htmlspecialchars((string)($order['tracking_number'] ?? 'Not shipped yet')); ?>
                    <?php if (!empty($order['shipping_carrier'])): ?>
                        <br><?php echo htmlspecialchars((string)$order['shipping_carrier']); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <table>
            <thead>
            <tr>
                <th>Item</th>
                <th>Variant</th>
                <th>Qty</th>
                <th class="right">Line Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars((string)$item['product_name']); ?></td>
                    <td>
                        <?php
                        $variant = [];
                        if (!empty($item['size_name'])) $variant[] = 'Size: ' . $item['size_name'];
                        if (!empty($item['colour_name'])) $variant[] = 'Color: ' . $item['colour_name'];
                        echo htmlspecialchars($variant ? implode(', ', $variant) : 'N/A');
                        ?>
                    </td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td class="right">$<?php echo number_format((float)$item['line_total'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="3" class="right total">Total</td>
                <td class="right total">$<?php echo number_format((float)$order['total_amount'], 2); ?></td>
            </tr>
            </tbody>
        </table>
    </div>

    <?php if (in_array($order['status'], ['PENDING', 'PROCESSING'], true)): ?>
        <div class="actions">
            <form action="cancel_order.php" method="POST" onsubmit="return confirm('Cancel this order?');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                <button type="submit" class="btn btn-danger">Cancel Order</button>
            </form>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="font-size:1.2rem;margin-bottom:.75rem;">Returns</h2>
        <?php if (!empty($returnRequests)): ?>
            <?php foreach ($returnRequests as $request): ?>
                <div class="return-row">
                    <div><span class="return-status"><?php echo htmlspecialchars((string)$request['status']); ?></span></div>
                    <div style="margin-top:.4rem;"><strong><?php echo htmlspecialchars((string)($request['product_name'] ?? 'Entire order')); ?></strong></div>
                    <div style="color:#4b5563;margin-top:.25rem;"><?php echo nl2br(htmlspecialchars((string)$request['reason'])); ?></div>
                    <?php if (!empty($request['admin_notes'])): ?>
                        <div style="color:#4b5563;margin-top:.25rem;">Admin note: <?php echo nl2br(htmlspecialchars((string)$request['admin_notes'])); ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php elseif ($order['status'] !== 'DELIVERED'): ?>
            <p>Returns can be requested after this order is delivered.</p>
        <?php endif; ?>

        <?php if ($order['status'] === 'DELIVERED'): ?>
            <form method="POST" class="return-form">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="action" value="request_return">
                <label>
                    Item
                    <select name="order_item_id">
                        <option value="0">Entire order</option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?php echo (int)$item['order_item_id']; ?>">
                                <?php echo htmlspecialchars((string)$item['product_name']); ?> - Qty <?php echo (int)$item['quantity']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    Reason
                    <textarea name="reason" maxlength="1000" required placeholder="Describe the issue with the item or order."></textarea>
                </label>
                <button type="submit" class="btn btn-primary">Request Return</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
