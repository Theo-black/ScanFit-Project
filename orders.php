<?php
// orders.php

// Load shared functions and helpers, including authentication and order utilities
require_once 'functions.php';
// Ensure the customer is logged in; redirect to login if not authenticated
requireLogin();

// Get the currently logged-in customer's ID
$customerId = getCustomerId();
// Retrieve all orders belonging to the current customer
$orders     = getCustomerOrders($customerId);

// Prepare flash messages from query string and session (then clear session copies)
$successMsg = isset($_GET['success']) ? 'Order placed successfully!' : null;
$errorMsg   = $_SESSION['error']   ?? null;
$okMsg      = $_SESSION['success'] ?? null;
unset($_SESSION['error'], $_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Orders - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and base layout */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:#f8f9fa;color:#333;min-height:100vh
        }
        .container{max-width:1200px;margin:0 auto;padding:3rem 2rem}
        h1{font-size:2.5rem;margin-bottom:2rem;color:#2c3e50}
        /* Flash message styles */
        .success-msg{
            background:#28a745;color:#fff;padding:1rem;
            border-radius:12px;margin-bottom:1rem;text-align:center;
            font-weight:600
        }
        .error-msg{
            background:#ff4444;color:#fff;padding:1rem;
            border-radius:12px;margin-bottom:1rem;text-align:center;
            font-weight:600
        }
        /* Orders list layout and card styles */
        .orders-list{display:flex;flex-direction:column;gap:2rem}
        .order-card{
            background:#fff;border-radius:20px;padding:2rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08);
            transition:all .3s
        }
        .order-card:hover{box-shadow:0 15px 40px rgba(0,0,0,.12)}
        /* Order header: summary info grid */
        .order-header{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
            gap:1.5rem;padding-bottom:1.5rem;border-bottom:2px solid #e1e4e8;
            margin-bottom:1.5rem
        }
        .order-info-item{display:flex;flex-direction:column}
        .order-label{font-size:.9rem;color:#666;margin-bottom:.3rem}
        .order-value{font-size:1.1rem;font-weight:600;color:#2c3e50}
        /* Status badge styles with variants per status */
        .order-status{
            display:inline-block;padding:.4rem 1rem;border-radius:20px;
            font-size:.9rem;font-weight:700;text-transform:uppercase
        }
        .status-pending{background:#fff3cd;color:#856404}
        .status-processing{background:#d1ecf1;color:#0c5460}
        .status-shipped{background:#d4edda;color:#155724}
        .status-delivered{background:#d4edda;color:#155724}
        .status-cancelled{background:#f8d7da;color:#721c24}
        .order-actions{
            margin-top:.5rem;
        }
        /* Cancel order button styling */
        .cancel-btn{
            padding:.4rem .9rem;
            border-radius:999px;
            border:none;
            background:#ff4d4f;
            color:#fff;
            font-size:.8rem;
            font-weight:600;
            cursor:pointer;
        }
        .cancel-btn:hover{
            background:#e04345;
        }
        /* Order items section layout */
        .order-items{margin-top:1.5rem}
        .order-items-header{
            font-size:1.2rem;font-weight:700;margin-bottom:1rem;
            color:#2c3e50
        }
        .order-item{
            display:grid;grid-template-columns:80px 1fr auto;
            gap:1rem;padding:1rem 0;border-bottom:1px solid #e1e4e8
        }
        .order-item:last-child{border-bottom:none}
        /* Product thumbnail styles */
        .item-image{
            width:80px;height:80px;background:#f0f2f5;
            border-radius:12px;overflow:hidden
        }
        .item-image img{width:100%;height:100%;object-fit:cover}
        /* Item details: name, variant, quantity */
        .item-details h4{font-size:1.1rem;margin-bottom:.3rem;color:#2c3e50}
        .item-variant{color:#666;font-size:.9rem;margin-bottom:.3rem}
        .item-quantity{color:#666;font-size:.9rem}
        /* Line item price styling */
        .item-price{
            text-align:right;font-size:1.2rem;font-weight:700;
            color:#667eea
        }
        /* Order total section */
        .order-total{
            margin-top:1.5rem;padding-top:1.5rem;
            border-top:2px solid #e1e4e8;text-align:right
        }
        .total-label{font-size:1.1rem;color:#666;margin-right:1rem}
        .total-amount{font-size:1.6rem;font-weight:800;color:#2c3e50}
        /* Empty state styling when there are no orders */
        .no-orders{
            text-align:center;padding:4rem 2rem;background:#fff;
            border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .no-orders h2{font-size:2rem;margin-bottom:1rem;color:#2c3e50}
        .no-orders p{color:#666;margin-bottom:2rem;font-size:1.1rem}
        .shop-btn{
            display:inline-block;padding:1rem 2rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;text-decoration:none;border-radius:15px;
            font-weight:600;transition:transform .2s
        }
        .shop-btn:hover{transform:translateY(-2px)}
        /* Responsive tweaks for smaller screens */
        @media(max-width:768px){
            .order-header{grid-template-columns:1fr}
            .order-item{grid-template-columns:60px 1fr;gap:.8rem}
            .item-price{grid-column:2;text-align:left;margin-top:.5rem}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <h1>My Orders</h1>

    <!-- Flash message: order placed via GET success flag -->
    <?php if ($successMsg): ?>
        <div class="success-msg"><?php echo htmlspecialchars($successMsg); ?></div>
    <?php endif; ?>

    <!-- Flash message: generic success from session -->
    <?php if ($okMsg): ?>
        <div class="success-msg"><?php echo htmlspecialchars($okMsg); ?></div>
    <?php endif; ?>

    <!-- Flash message: error from session -->
    <?php if ($errorMsg): ?>
        <div class="error-msg"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($orders) > 0): ?>
        <!-- Orders list when customer has one or more orders -->
        <div class="orders-list">
            <?php while ($order = mysqli_fetch_assoc($orders)): ?>
                <div class="order-card">
                    <!-- Order summary header: ID, date, status, total -->
                    <div class="order-header">
                        <div class="order-info-item">
                            <div class="order-label">Order ID</div>
                            <div class="order-value">#<?php echo $order['order_id']; ?></div>
                        </div>

                        <div class="order-info-item">
                            <div class="order-label">Order Date</div>
                            <div class="order-value">
                                <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                            </div>
                        </div>

                        <div class="order-info-item">
                            <div class="order-label">Status</div>
                            <div>
                                <!-- Status badge with dynamic CSS class based on status value -->
                                <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>

                                <!-- Show cancel action only for pending/processing orders -->
                                <?php if (in_array($order['status'], ['PENDING','PROCESSING'], true)): ?>
                                    <div class="order-actions">
                                        <form action="cancel_order.php" method="POST"
                                              onsubmit="return confirm('Cancel this order?');">
                                            <?php echo csrfInput(); ?>
                                            <input type="hidden" name="order_id"
                                                   value="<?php echo (int)$order['order_id']; ?>">
                                            <button type="submit" class="cancel-btn">
                                                Cancel Order
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="order-info-item">
                            <div class="order-label">Total Amount</div>
                            <div class="order-value">
                                $<?php echo number_format($order['total_amount'], 2); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Line items for this order -->
                    <div class="order-items">
                        <div class="order-items-header">Order Items</div>

                        <?php
                        // Fetch all items belonging to the current order
                        $orderItems = getOrderItems($order['order_id']);
                        while ($item = mysqli_fetch_assoc($orderItems)):
                        ?>
                            <div class="order-item">
                                <div class="item-image">
                                    <!-- Order item product image with fallback placeholder -->
                                    <img src="images/<?php echo htmlspecialchars($item['product_sku']); ?>.jpg"
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                         onerror="this.src='https://via.placeholder.com/80/f8f9fa/999?text=👕'">
                                </div>

                                <div class="item-details">
                                    <!-- Product name for this line item -->
                                    <h4><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                    <!-- Variant details (size/color) combined into one string -->
                                    <div class="item-variant">
                                        <?php
                                        $variant = [];
                                        if (!empty($item['size_name']))   $variant[] = 'Size: ' . $item['size_name'];
                                        if (!empty($item['colour_name'])) $variant[] = 'Color: ' . $item['colour_name'];
                                        echo htmlspecialchars(implode(' | ', $variant));
                                        ?>
                                    </div>
                                    <!-- Quantity ordered for this item -->
                                    <div class="item-quantity">
                                        Quantity: <?php echo $item['quantity']; ?>
                                    </div>
                                </div>

                                <!-- Line total for this item -->
                                <div class="item-price">
                                    $<?php echo number_format($item['line_total'], 2); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Order grand total summary -->
                    <div class="order-total">
                        <span class="total-label">Total:</span>
                        <span class="total-amount">
                            $<?php echo number_format($order['total_amount'], 2); ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <!-- Empty state when customer has not placed any orders -->
        <div class="no-orders">
            <h2>No Orders Yet</h2>
            <p>You haven't placed any orders. Start shopping to see your orders here!</p>
            <a href="index.php" class="shop-btn">Start Shopping</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
