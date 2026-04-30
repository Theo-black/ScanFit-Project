<?php
// cart.php

// Load shared application functions and ensure user is authenticated
require_once 'functions.php';
requireLogin();

// Get the currently logged-in customer's ID for cart operations
$customerId = getCustomerId();

// Handle cart update/remove actions when the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('cart.php');
    if (isset($_POST['action'])) {
        // Remove a specific item from the cart for this customer
        if ($_POST['action'] === 'remove') {
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
            if (!removeFromCart($cartItemId, $customerId)) {
                $_SESSION['error'] = 'Unable to remove this item.';
            }
        // Update quantity for a specific cart item
        } elseif ($_POST['action'] === 'update') {
            $cartItemId = (int)($_POST['cart_item_id'] ?? 0);
            $quantity = (int)($_POST['quantity'] ?? 1);
            if (!updateCartItemQuantity($cartItemId, $customerId, $quantity)) {
                $_SESSION['error'] = 'Requested quantity exceeds available stock.';
            }
        } elseif ($_POST['action'] === 'apply_coupon') {
            $code = normalizeCouponCode((string)($_POST['coupon_code'] ?? ''));
            if ($code === '') {
                unset($_SESSION['cart_coupon_code']);
            } elseif (getCouponByCode($code)) {
                $_SESSION['cart_coupon_code'] = $code;
            } else {
                $_SESSION['error'] = 'Coupon code is invalid or expired.';
            }
        } elseif ($_POST['action'] === 'remove_coupon') {
            unset($_SESSION['cart_coupon_code']);
        }
    }
    // After processing, reload cart page to reflect changes (PRG pattern)
    header('Location: cart.php');
    exit();
}

// Fetch all cart items and the current cart total for this customer
$cartItems = getCartItems($customerId);
$cartTotal = getCartTotal($customerId);
$pricingConfig = getStorePricingConfig();
$shippingCost = $pricingConfig['flat_shipping'];
$taxRate = $pricingConfig['tax_rate'];
$couponState = getActiveCartCoupon($cartTotal);
$couponDiscount = $couponState['discount'];
$taxableSubtotal = max(0, $cartTotal - $couponDiscount);
$errorMsg = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shopping Cart - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and base page styling */
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:#f8f9fa;color:#333;min-height:100vh
        }
        .container{max-width:1200px;margin:0 auto;padding:3rem 2rem}
        h1{font-size:2.5rem;margin-bottom:2rem;color:#2c3e50}
        .cart-grid{display:grid;grid-template-columns:1fr 400px;gap:2rem}
        .cart-items{
            /* Panel containing list of cart item rows */
            background:#fff;border-radius:20px;padding:2rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .cart-item{
            /* Layout for each individual cart item row */
            display:grid;grid-template-columns:120px 1fr auto;gap:1.5rem;
            padding:1.5rem 0;border-bottom:1px solid #e1e4e8
        }
        .cart-item:last-child{border-bottom:none}
        .item-image{
            /* Thumbnail container for product image */
            width:120px;height:120px;background:#f0f2f5;
            border-radius:15px;overflow:hidden
        }
        .item-image img{width:100%;height:100%;object-fit:cover}
        .item-details{
            /* Column with product name, variant info, and price line */
            display:flex;flex-direction:column;justify-content:space-between
        }
        .item-name{font-size:1.3rem;font-weight:600;color:#2c3e50;margin-bottom:.5rem}
        .item-variant{color:#666;font-size:.95rem;margin-bottom:.5rem}
        .item-price{font-size:1.2rem;font-weight:700;color:#667eea}
        .item-actions{
            /* Column with quantity update and remove controls */
            display:flex;flex-direction:column;justify-content:space-between;align-items:flex-end
        }
        .quantity-control{
            /* Wrapper for quantity input field */
            display:flex;align-items:center;gap:.5rem;margin-bottom:1rem
        }
        .quantity-control input{
            /* Numeric input to change item quantity */
            width:60px;padding:.5rem;text-align:center;border:2px solid #e1e4e8;
            border-radius:8px;font-size:1rem
        }
        .quantity-btn{
            /* Optional quantity button style (not used, kept for extension) */
            padding:.5rem 1rem;border:2px solid #e1e4e8;background:#fff;
            border-radius:8px;cursor:pointer;font-weight:600;
            transition:all .2s
        }
        .quantity-btn:hover{background:#f8f9fa;border-color:#667eea}
        .remove-btn{
            /* Button to remove item from cart */
            padding:.6rem 1.2rem;border:2px solid #ff4444;
            background:#fff;color:#ff4444;border-radius:10px;
            cursor:pointer;font-weight:600;transition:all .2s
        }
        .remove-btn:hover{background:#ff4444;color:#fff}
        .cart-summary{
            /* Sidebar summary panel showing totals and checkout button */
            background:#fff;border-radius:20px;padding:2rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08);height:fit-content;
            position:sticky;top:2rem
        }
        .summary-title{font-size:1.5rem;font-weight:700;margin-bottom:1.5rem;color:#2c3e50}
        .summary-row{
            /* Rows for subtotal, shipping, tax, and total breakdown */
            display:flex;justify-content:space-between;margin-bottom:1rem;
            padding-bottom:1rem;border-bottom:1px solid #e1e4e8
        }
        .summary-row:last-child{border-bottom:none;font-size:1.3rem;font-weight:700}
        .checkout-btn{
            /* Call-to-action button leading to checkout page */
            width:100%;padding:1.2rem;border:none;border-radius:15px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;font-size:1.1rem;font-weight:700;cursor:pointer;
            transition:transform .2s;margin-top:1rem
        }
        .checkout-btn:hover{transform:translateY(-2px)}
        .coupon-form{display:flex;gap:.5rem;margin:1rem 0;flex-wrap:wrap}
        .coupon-form input{flex:1;min-width:140px;padding:.65rem;border:2px solid #e1e4e8;border-radius:8px}
        .coupon-form button{padding:.65rem .8rem;border:none;border-radius:8px;background:#667eea;color:#fff;font-weight:700;cursor:pointer}
        .coupon-remove{background:#ef4444!important}
        .empty-cart{
            /* Card shown when the cart has no items */
            text-align:center;padding:4rem 2rem;background:#fff;
            border-radius:20px;box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .empty-cart h2{font-size:2rem;margin-bottom:1rem;color:#2c3e50}
        .empty-cart p{color:#666;margin-bottom:2rem;font-size:1.1rem}
        .shop-btn{
            /* Button-style link to continue shopping from empty cart */
            display:inline-block;padding:1rem 2rem;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;text-decoration:none;border-radius:15px;
            font-weight:600;transition:transform .2s
        }
        .shop-btn:hover{transform:translateY(-2px)}
        @media(max-width:968px){
            /* Responsive adjustments for tablet/mobile screens */
            .cart-grid{grid-template-columns:1fr}
            .cart-item{grid-template-columns:100px 1fr;gap:1rem}
            .item-actions{flex-direction:row;justify-content:space-between;width:100%;margin-top:1rem}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <h1>Shopping Cart</h1>
    <?php if ($errorMsg): ?>
        <div style="background:#ff4444;color:#fff;padding:1rem;border-radius:12px;margin-bottom:1rem;">
            <?php echo htmlspecialchars($errorMsg); ?>
        </div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($cartItems) > 0): ?>
        <div class="cart-grid">
            <div class="cart-items">
                <?php while ($item = mysqli_fetch_assoc($cartItems)): ?>
                    <div class="cart-item">
                        <div class="item-image">
                            <!-- Product image with SKU-based filename and placeholder fallback -->
                            <img src="images/<?php echo htmlspecialchars($item['product_sku']); ?>.jpg"
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                 onerror="this.src='https://via.placeholder.com/120/f8f9fa/999?text=👕'">
                        </div>

                        <div class="item-details">
                            <div>
                                <!-- Product name -->
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <!-- Display size/color variant information if available -->
                                <div class="item-variant">
                                    <?php
                                    $variant = [];
                                    if (!empty($item['size_name'])) $variant[] = 'Size: ' . $item['size_name'];
                                    if (!empty($item['colour_name'])) $variant[] = 'Color: ' . $item['colour_name'];
                                    echo htmlspecialchars(implode(' | ', $variant));
                                    ?>
                                </div>
                            </div>
                            <div class="item-price">
                                <!-- Line price: unit price (base + adjustment) multiplied by quantity -->
                                $<?php echo number_format($item['base_price'] + ($item['price_adjustment'] ?? 0), 2); ?>
                                × <?php echo $item['quantity']; ?>
                            </div>
                        </div>

                        <div class="item-actions">
                            <!-- Form to update quantity of this cart item -->
                            <form method="POST" style="margin:0">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                <div class="quantity-control">
                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>"
                                           min="1" max="<?php echo $item['stock_quantity']; ?>"
                                           onchange="this.form.submit()">
                                </div>
                            </form>

                            <!-- Form to remove this item from the cart -->
                            <form method="POST" style="margin:0">
                                <?php echo csrfInput(); ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                <button type="submit" class="remove-btn">Remove</button>
                            </form>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>

            <div class="cart-summary">
                <!-- Order summary: subtotal, shipping, tax, and total -->
                <div class="summary-title">Order Summary</div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>$<?php echo number_format($cartTotal, 2); ?></span>
                </div>

                <div class="summary-row">
                    <span>Shipping</span>
                    <span>$<?php echo number_format($shippingCost, 2); ?></span>
                </div>

                <form method="POST" class="coupon-form">
                    <?php echo csrfInput(); ?>
                    <?php if ($couponState['coupon']): ?>
                        <input type="hidden" name="action" value="remove_coupon">
                        <input type="text" value="<?php echo htmlspecialchars($couponState['code']); ?> applied" disabled>
                        <button type="submit" class="coupon-remove">Remove</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="apply_coupon">
                        <input type="text" name="coupon_code" placeholder="Coupon code">
                        <button type="submit">Apply</button>
                    <?php endif; ?>
                </form>

                <?php if ($couponDiscount > 0): ?>
                    <div class="summary-row">
                        <span>Discount</span>
                        <span>-$<?php echo number_format($couponDiscount, 2); ?></span>
                    </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>Tax (<?php echo number_format($taxRate * 100, 1); ?>%)</span>
                    <span>$<?php echo number_format($taxableSubtotal * $taxRate, 2); ?></span>
                </div>

                <div class="summary-row">
                    <span>Total</span>
                    <span>$<?php echo number_format($taxableSubtotal + $shippingCost + ($taxableSubtotal * $taxRate), 2); ?></span>
                </div>

                <!-- Link styled as button that leads to checkout flow -->
                <a href="checkout.php" class="checkout-btn" style="text-decoration:none;display:block;text-align:center">
                    Proceed to Checkout
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Empty cart message and link to continue shopping -->
        <div class="empty-cart">
            <h2>Your cart is empty</h2>
            <p>Add some products to get started</p>
            <a href="index.php" class="shop-btn">Continue Shopping</a>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
