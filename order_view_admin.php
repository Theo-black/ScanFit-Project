<?php
// order_view_admin.php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR']);

global $conn;

// Get order id from query
$orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($orderId <= 0) {
    header('Location: orders_admin.php');
    exit();
}

// Load order with customer info and total
$sql = "
    SELECT o.*, 
           c.first_name,
           c.last_name,
           c.email,
           a.address_line1,
           a.address_line2,
           a.city,
           a.state_province,
           a.postal_code,
           co.name AS country_name,
           p.method_name,
           p.payment_status
    FROM `order` o
    JOIN customer c ON o.customer_id = c.customer_id
    LEFT JOIN address a ON o.shipping_address_id = a.address_id
    LEFT JOIN country co ON a.country_id = co.country_id
    LEFT JOIN payment p ON o.order_id = p.order_id
    WHERE o.order_id = ?
    LIMIT 1
";

$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    die('Failed to load order.');
}
mysqli_stmt_bind_param($stmt, 'i', $orderId);
mysqli_stmt_execute($stmt);
$res   = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);

if (!$order) {
    header('Location: orders_admin.php');
    exit();
}

// Get items using helper (already uses order_id internally)
$orderItems = getOrderItems($orderId);
$successMsg = $_SESSION['success'] ?? (isset($_GET['cancelled']) ? 'Order cancelled successfully.' : null);
$errorMsg = $_SESSION['error'] ?? (isset($_GET['error']) ? 'Unable to process request.' : null);
unset($_SESSION['success'], $_SESSION['error']);

// Compute total
$totalAmount = 0.0;
$items = [];
if ($orderItems && mysqli_num_rows($orderItems) > 0) {
    while ($row = mysqli_fetch_assoc($orderItems)) {
        $lineTotal    = (float)$row['line_total'];
        $totalAmount += $lineTotal;
        $items[]      = $row;
    }
}
$returnRequests = getReturnRequestsForOrder($orderId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - View Order #<?php echo (int)$order['order_id']; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{
            font-family:Segoe UI, sans-serif;
            background:#0f172a;
            color:#e5e7eb;
            padding:2rem;
        }
        a{text-decoration:none;color:#38bdf8}
        a:hover{text-decoration:underline}
        .nav{margin-bottom:1rem;font-size:.85rem;color:#9ca3af}
        h1{font-size:1.8rem;margin-bottom:1rem}
        .card{
            max-width:1000px;
            margin:0 auto;
            background:#020617;
            border-radius:16px;
            padding:1.8rem;
            border:1px solid #111827;
            box-shadow:0 15px 40px rgba(0,0,0,.4);
        }
        .section-title{
            font-size:1rem;
            color:#9ca3af;
            margin-bottom:.75rem;
        }
        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:1.2rem;
            margin-bottom:1.5rem;
        }
        .field-label{
            font-size:.8rem;
            color:#9ca3af;
            margin-bottom:.2rem;
        }
        .field-value{
            font-size:.95rem;
            color:#e5e7eb;
            font-weight:500;
        }
        .status-badge{
            display:inline-block;
            padding:.25rem .8rem;
            border-radius:999px;
            font-size:.8rem;
            font-weight:700;
            text-transform:uppercase;
        }
        .status-pending{background:#fff3cd;color:#856404}
        .status-processing{background:#d1ecf1;color:#0c5460}
        .status-shipped{background:#d4edda;color:#155724}
        .status-delivered{background:#d4edda;color:#155724}
        .status-cancelled{background:#f8d7da;color:#721c24}

        table{
            width:100%;
            border-collapse:collapse;
            margin-top:1rem;
            background:#020617;
            border-radius:12px;
            overflow:hidden;
        }
        th,td{
            padding:.75rem 1rem;
            font-size:.9rem;
            border-bottom:1px solid #111827;
            text-align:left;
        }
        th{
            background:#020617;
            font-weight:600;
            color:#9ca3af;
        }
        tr:nth-child(even){
            background:#020617;
        }
        .item-name{font-weight:600;color:#e5e7eb}
        .item-meta{font-size:.8rem;color:#9ca3af}
        .total-row td{
            border-top:2px solid #1f2937;
            font-weight:700;
            font-size:1rem;
        }
        .total-label{text-align:right}
        .total-amount{font-size:1.2rem}
        .actions{
            margin-top:1.5rem;
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:.75rem;
        }
        .btn{
            padding:.5rem 1.2rem;
            border-radius:999px;
            font-size:.85rem;
            border:none;
            cursor:pointer;
            font-weight:600;
        }
        .btn-secondary{
            background:#111827;
            color:#e5e7eb;
        }
        .btn-primary{
            background:#38bdf8;
            color:#0f172a;
        }
        .btn-danger{
            background:#ff4d4f;
            color:#fff;
        }
        .btn-danger:hover{background:#e04345}
        .status-form{
            display:flex;
            gap:.7rem;
            align-items:end;
            flex-wrap:wrap;
            margin-top:.8rem;
        }
        .status-form select,
        .status-form input{
            min-width:180px;
            padding:.55rem .7rem;
            border-radius:8px;
            border:1px solid #1f2937;
            background:#020617;
            color:#e5e7eb;
            font-size:.9rem;
        }
        .return-list{margin-top:1.5rem;border-top:1px solid #111827;padding-top:1rem}
        .return-row{padding:.85rem 0;border-top:1px solid #111827}
        .return-row:first-of-type{border-top:none}
        .return-status{display:inline-block;border-radius:999px;padding:.2rem .6rem;background:#312e81;color:#c7d2fe;font-weight:800;font-size:.75rem}
    </style>
</head>
<body>
<div class="nav">
    <a href="admin_dashboard.php">Dashboard</a> ›
    <a href="orders_admin.php">Orders</a> ›
    <span>Order #<?php echo (int)$order['order_id']; ?></span>
</div>

<div class="card">
    <h1>Order #<?php echo (int)$order['order_id']; ?></h1>
    <?php if ($successMsg): ?>
        <div style="margin-bottom:1rem;padding:.8rem 1rem;background:#14532d;border-radius:8px;color:#bbf7d0;">
            <?php echo htmlspecialchars($successMsg); ?>
        </div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
        <div style="margin-bottom:1rem;padding:.8rem 1rem;background:#7f1d1d;border-radius:8px;color:#fecaca;">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <div class="grid">
        <div>
            <div class="section-title">Order Info</div>
            <div class="field-label">Date</div>
            <div class="field-value">
                <?php echo htmlspecialchars($order['order_date']); ?>
            </div>
            <div class="field-label" style="margin-top:.4rem;">Status</div>
            <div class="field-value">
                <?php
                $status = $order['status'];
                $cls    = 'status-'.strtolower($status);
                ?>
                <span class="status-badge <?php echo $cls; ?>">
                    <?php echo htmlspecialchars($status); ?>
                </span>
            </div>
            <?php if (
                in_array($_SESSION['admin_role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true) &&
                !in_array($order['status'], ['CANCELLED', 'DELIVERED'], true)
            ): ?>
                <form action="admin_update_order_status.php" method="POST" class="status-form">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="order_id" value="<?php echo (int)$order['order_id']; ?>">
                    <div>
                        <div class="field-label">Move Order To</div>
                        <select name="status" required>
                            <?php
                            $statusOptions = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED'];
                            $statusRank = ['PENDING' => 1, 'PROCESSING' => 2, 'SHIPPED' => 3, 'DELIVERED' => 4];
                            foreach ($statusOptions as $statusOption):
                                $disabled = ($statusRank[$statusOption] ?? 0) < ($statusRank[$order['status']] ?? 0);
                            ?>
                                <option value="<?php echo $statusOption; ?>" <?php echo $order['status'] === $statusOption ? 'selected' : ''; ?> <?php echo $disabled ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($statusOption); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <div class="field-label">Carrier</div>
                        <input type="text" name="shipping_carrier" maxlength="100" value="<?php echo htmlspecialchars((string)($order['shipping_carrier'] ?? '')); ?>" placeholder="USPS, FedEx, DHL">
                    </div>
                    <div>
                        <div class="field-label">Tracking Number</div>
                        <input type="text" name="tracking_number" maxlength="191" value="<?php echo htmlspecialchars((string)($order['tracking_number'] ?? '')); ?>" placeholder="Required when shipped">
                    </div>
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </form>
            <?php endif; ?>
        </div>

        <div>
            <div class="section-title">Customer</div>
            <div class="field-label">Name</div>
            <div class="field-value">
                <?php echo htmlspecialchars($order['first_name'].' '.$order['last_name']); ?>
            </div>
            <div class="field-label" style="margin-top:.4rem;">Email</div>
            <div class="field-value">
                <?php echo htmlspecialchars($order['email']); ?>
            </div>
        </div>

        <div>
            <div class="section-title">Ship To</div>
            <?php if (!empty($order['address_line1'])): ?>
                <div class="field-label">Address</div>
                <div class="field-value">
                    <?php echo htmlspecialchars((string)$order['address_line1']); ?>
                    <?php if (!empty($order['address_line2'])): ?>
                        <br><?php echo htmlspecialchars((string)$order['address_line2']); ?>
                    <?php endif; ?>
                    <br>
                    <?php
                    $cityLine = array_filter([
                        (string)($order['city'] ?? ''),
                        (string)($order['state_province'] ?? ''),
                        (string)($order['postal_code'] ?? ''),
                    ]);
                    echo htmlspecialchars(implode(', ', $cityLine));
                    ?>
                    <?php if (!empty($order['country_name'])): ?>
                        <br><?php echo htmlspecialchars((string)$order['country_name']); ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="field-value">No shipping address linked.</div>
            <?php endif; ?>
        </div>

        <div>
            <div class="section-title">Shipping</div>
            <div class="field-label">Carrier</div>
            <div class="field-value">
                <?php echo htmlspecialchars((string)($order['shipping_carrier'] ?? 'Not set')); ?>
            </div>
            <div class="field-label" style="margin-top:.4rem;">Tracking</div>
            <div class="field-value">
                <?php echo htmlspecialchars((string)($order['tracking_number'] ?? 'Not set')); ?>
            </div>
            <?php if (!empty($order['shipped_at'])): ?>
                <div class="field-label" style="margin-top:.4rem;">Shipped At</div>
                <div class="field-value"><?php echo htmlspecialchars((string)$order['shipped_at']); ?></div>
            <?php endif; ?>
            <?php if (!empty($order['delivered_at'])): ?>
                <div class="field-label" style="margin-top:.4rem;">Delivered At</div>
                <div class="field-value"><?php echo htmlspecialchars((string)$order['delivered_at']); ?></div>
            <?php endif; ?>
        </div>

        <div>
            <div class="section-title">Amounts</div>
            <div class="field-label">Order Total (calculated)</div>
            <div class="field-value">
                $<?php echo number_format($totalAmount, 2); ?>
            </div>
            <?php if (isset($order['total_amount'])): ?>
                <div class="field-label" style="margin-top:.4rem;">Stored Total</div>
                <div class="field-value">
                    $<?php echo number_format((float)$order['total_amount'], 2); ?>
                </div>
            <?php endif; ?>
            <div class="field-label" style="margin-top:.4rem;">Payment</div>
            <div class="field-value">
                <?php echo htmlspecialchars($order['method_name'] ?? 'N/A'); ?>
                <?php if (!empty($order['payment_status'])): ?>
                    (<?php echo htmlspecialchars($order['payment_status']); ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="section-title" style="margin-top:1.5rem;">Order Items</div>
    <?php if (!empty($items)): ?>
        <table>
            <thead>
            <tr>
                <th>Product</th>
                <th>Variant</th>
                <th>Qty</th>
                <th>Price (each)</th>
                <th>Line Total</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <div class="item-name">
                            <?php echo htmlspecialchars($item['product_name']); ?>
                        </div>
                        <div class="item-meta">
                            SKU: <?php echo htmlspecialchars($item['product_sku']); ?>
                        </div>
                    </td>
                    <td>
                        <div class="item-meta">
                            <?php
                            $variant = [];
                            if (!empty($item['size_name']))   $variant[] = 'Size: '.$item['size_name'];
                            if (!empty($item['colour_name'])) $variant[] = 'Color: '.$item['colour_name'];
                            echo $variant ? htmlspecialchars(implode(', ', $variant)) : '—';
                            ?>
                        </div>
                    </td>
                    <td><?php echo (int)$item['quantity']; ?></td>
                    <td>
                        $<?php echo number_format((float)$item['unit_price'], 2); ?>
                    </td>
                    <td>
                        $<?php echo number_format((float)$item['line_total'], 2); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="total-label">Total</td>
                <td class="total-amount">
                    $<?php echo number_format($totalAmount, 2); ?>
                </td>
            </tr>
            </tbody>
        </table>
    <?php else: ?>
        <p>No items found for this order.</p>
    <?php endif; ?>

    <div class="return-list">
        <div class="section-title">Returns</div>
        <?php if (empty($returnRequests)): ?>
            <div class="field-value">No return requests for this order.</div>
        <?php else: ?>
            <?php foreach ($returnRequests as $request): ?>
                <div class="return-row">
                    <span class="return-status"><?php echo htmlspecialchars((string)$request['status']); ?></span>
                    <div class="field-value" style="margin-top:.35rem;">
                        <?php echo htmlspecialchars((string)($request['product_name'] ?? 'Entire order')); ?>
                    </div>
                    <div class="item-meta"><?php echo htmlspecialchars((string)$request['created_at']); ?></div>
                    <div style="margin-top:.35rem;"><?php echo nl2br(htmlspecialchars((string)$request['reason'])); ?></div>
                    <?php if (!empty($request['admin_notes'])): ?>
                        <div class="item-meta" style="margin-top:.35rem;">Admin note: <?php echo nl2br(htmlspecialchars((string)$request['admin_notes'])); ?></div>
                    <?php endif; ?>
                    <div style="margin-top:.45rem;"><a href="returns_admin.php">Manage returns</a></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="actions">
        <a href="orders_admin.php" class="btn btn-secondary">← Back to Orders</a>

        <?php if (
            in_array($order['status'], ['PENDING','PROCESSING'], true) &&
            in_array($_SESSION['admin_role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true)
        ): ?>
            <form action="admin_cancel_order.php" method="POST"
                  onsubmit="return confirm('Cancel this order for the customer?');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="order_id"
                       value="<?php echo (int)$order['order_id']; ?>">
                <button type="submit" class="btn btn-danger">Cancel Order</button>
            </form>
        <?php endif; ?>

        <?php if (
            $order['status'] === 'CANCELLED' &&
            in_array($_SESSION['admin_role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true)
        ): ?>
            <form action="admin_delete_order.php" method="POST"
                  onsubmit="return confirm('Delete this cancelled order permanently?');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="order_id"
                       value="<?php echo (int)$order['order_id']; ?>">
                <button type="submit" class="btn btn-danger">Delete Order</button>
            </form>
        <?php endif; ?>

        <?php if (
            ($order['method_name'] ?? '') === 'STRIPE_CARD' &&
            ($order['payment_status'] ?? '') === 'COMPLETED' &&
            in_array($_SESSION['admin_role'] ?? '', ['SUPER_ADMIN', 'ADMIN'], true)
        ): ?>
            <form action="admin_refund_order.php" method="POST"
                  onsubmit="return confirm('Refund this Stripe payment?');">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="order_id"
                       value="<?php echo (int)$order['order_id']; ?>">
                <button type="submit" class="btn btn-danger">Refund Stripe Payment</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
