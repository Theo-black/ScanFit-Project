<?php
// checkout.php

// Load shared functions and enforce that the user is logged in
require_once 'functions.php';
requireLogin();

// Get current customer's ID and fetch their cart items and total
$customerId = getCustomerId();
$cartItems = getCartItems($customerId);
$cartTotal = getCartTotal($customerId);

// If the cart is empty, redirect back to the cart page
if (mysqli_num_rows($cartItems) === 0) {
    header('Location: cart.php');
    exit();
}

// Initialize error and success flags for checkout processing
$error = null;

// Handle checkout form submission via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('checkout.php');

    // Collect and sanitize shipping and payment form inputs
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $countryId = (int)($_POST['country_id'] ?? 0);
    $paymentMethod = $_POST['payment_method'] ?? '';
    $allowedMethods = ['CREDIT_CARD', 'DEBIT_CARD', 'PAYPAL', 'CASH_ON_DELIVERY'];

    // Basic validation to ensure all required fields are present
    if (
        empty($address) || empty($city) || empty($postalCode) ||
        $countryId <= 0 || !in_array($paymentMethod, $allowedMethods, true)
    ) {
        $error = 'Please fill in all fields';
    } else {
        // Start a database transaction to process the order atomically
        mysqli_begin_transaction($conn);

        try {
            mysqli_data_seek($cartItems, 0);
            if (mysqli_num_rows($cartItems) === 0) {
                throw new Exception('Cart is empty.');
            }

            // Calculate shipping, tax, and final total for the order
            $shippingCost = 5.00;
            $tax = $cartTotal * 0.10;
            $total = $cartTotal + $shippingCost + $tax;

            // Persist checkout shipping address for this customer
            $insertAddressSql = "
                INSERT INTO address (customer_id, address_line1, city, postal_code, country_id, is_default)
                VALUES (?, ?, ?, ?, ?, 0)
            ";
            $stmt = mysqli_prepare($conn, $insertAddressSql);
            if (!$stmt) {
                throw new Exception('Failed to prepare address insert.');
            }
            mysqli_stmt_bind_param($stmt, 'isssi', $customerId, $address, $city, $postalCode, $countryId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to save shipping address.');
            }

            // Insert a new order row for this customer
            $insertOrderSql = "
                INSERT INTO `order` (customer_id, order_date, status, total_amount, created_at)
                VALUES (?, NOW(), 'PENDING', ?, NOW())
            ";
            $stmt = mysqli_prepare($conn, $insertOrderSql);
            if (!$stmt) {
                throw new Exception('Failed to prepare order insert.');
            }
            mysqli_stmt_bind_param($stmt, 'id', $customerId, $total);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to create order.');
            }
            // Retrieve the newly created order ID
            $orderId = mysqli_insert_id($conn);

            $paymentInsertSql = "
                INSERT INTO payment (order_id, method_name, payment_status, amount, payment_date)
                VALUES (?, ?, 'PENDING', ?, NOW())
            ";
            $paymentStmt = mysqli_prepare($conn, $paymentInsertSql);
            if (!$paymentStmt) {
                throw new Exception('Failed to prepare payment insert.');
            }
            mysqli_stmt_bind_param($paymentStmt, 'isd', $orderId, $paymentMethod, $total);
            if (!mysqli_stmt_execute($paymentStmt)) {
                throw new Exception('Failed to create payment record.');
            }

            // Loop through cart items and create corresponding order items
            mysqli_data_seek($cartItems, 0);
            while ($item = mysqli_fetch_assoc($cartItems)) {
                $lineTotal = ($item['base_price'] + ($item['price_adjustment'] ?? 0)) * $item['quantity'];
                $insertItemSql = "
                    INSERT INTO orderitem (order_id, variant_id, quantity, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?)
                ";
                $stmt = mysqli_prepare($conn, $insertItemSql);
                if (!$stmt) {
                    throw new Exception('Failed to prepare order item insert.');
                }
                $unitPrice = $item['base_price'] + ($item['price_adjustment'] ?? 0);
                mysqli_stmt_bind_param($stmt, 'iiidd', $orderId, $item['variant_id'], $item['quantity'], $unitPrice, $lineTotal);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to insert order item.');
                }

                // Decrease stock for each purchased variant
                $updateStockSql = "
                    UPDATE productvariant
                    SET stock_quantity = stock_quantity - ?
                    WHERE variant_id = ?
                      AND stock_quantity >= ?
                ";
                $stmt = mysqli_prepare($conn, $updateStockSql);
                if (!$stmt) {
                    throw new Exception('Failed to prepare stock update.');
                }
                mysqli_stmt_bind_param($stmt, 'iii', $item['quantity'], $item['variant_id'], $item['quantity']);
                if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) !== 1) {
                    throw new Exception('Stock is not available for one or more items.');
                }

                $stockAuditSql = "
                    INSERT INTO stockmovement (variant_id, movement_type, quantity, reference_id, notes)
                    VALUES (?, 'OUT', ?, ?, ?)
                ";
                $stockNotes = 'Checkout order #' . $orderId;
                $stockStmt = mysqli_prepare($conn, $stockAuditSql);
                if (!$stockStmt) {
                    throw new Exception('Failed to prepare stock audit.');
                }
                mysqli_stmt_bind_param(
                    $stockStmt,
                    'iiis',
                    $item['variant_id'],
                    $item['quantity'],
                    $orderId,
                    $stockNotes
                );
                if (!mysqli_stmt_execute($stockStmt)) {
                    throw new Exception('Failed to save stock audit.');
                }
            }

            // Mark the customer's active cart as completed
            $clearCartSql = "UPDATE cart SET status = 'COMPLETED' WHERE customer_id = ? AND status = 'ACTIVE'";
            $stmt = mysqli_prepare($conn, $clearCartSql);
            if (!$stmt) {
                throw new Exception('Failed to prepare cart clear.');
            }
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to clear cart.');
            }

            // Commit all order-related changes
            mysqli_commit($conn);

            // Redirect to orders page with a success flag
            header('Location: orders.php?success=1');
            exit();

        } catch (Exception $e) {
            // Roll back all changes if any step fails
            mysqli_rollback($conn);
            $error = 'Order processing failed. Please try again.';
        }
    }
}

// Load list of countries for the shipping address dropdown
$countries = mysqli_query($conn, "SELECT * FROM country ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout - Scanfit</title>
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
        .checkout-grid{display:grid;grid-template-columns:1fr 400px;gap:2rem}
        .checkout-form{
            /* Main form card for shipping and payment details */
            background:#fff;border-radius:20px;padding:2.5rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08)
        }
        .section-title{font-size:1.5rem;font-weight:700;margin-bottom:1.5rem;color:#2c3e50}
        .form-group{margin-bottom:1.5rem}
        .form-group label{
            /* Label styling for form fields */
            display:block;font-weight:600;margin-bottom:.5rem;color:#2c3e50
        }
        .form-group input,
        .form-group select{
            /* Inputs and select elements styling */
            width:100%;padding:1rem;border:2px solid #e1e4e8;
            border-radius:12px;font-size:1rem;outline:none;
            transition:border-color .3s
        }
        .form-group input:focus,
        .form-group select:focus{border-color:#667eea}
        .form-row{
            /* Two-column layout for city and postal code fields */
            display:grid;grid-template-columns:1fr 1fr;gap:1rem
        }
        .payment-options{
            /* Grid layout for payment method radio options */
            display:grid;gap:1rem;margin-top:.5rem
        }
        .payment-option{
            /* Styling for each payment method option */
            border:2px solid #e1e4e8;border-radius:12px;padding:1rem;
            cursor:pointer;transition:all .3s;display:flex;align-items:center;
            gap:1rem
        }
        .payment-option:hover{border-color:#667eea;background:#f8f9fa}
        .payment-option input[type="radio"]{width:auto}
        .order-summary{
            /* Sidebar card showing the cost breakdown */
            background:#fff;border-radius:20px;padding:2rem;
            box-shadow:0 10px 30px rgba(0,0,0,.08);height:fit-content;
            position:sticky;top:2rem
        }
        .summary-item{
            /* Row styling for subtotal, shipping, tax, total lines */
            display:flex;justify-content:space-between;padding:1rem 0;
            border-bottom:1px solid #e1e4e8
        }
        .summary-item:last-child{border-bottom:none}
        .summary-total{
            /* Emphasized total price row */
            font-size:1.3rem;font-weight:700;margin-top:1rem;
            padding-top:1rem;border-top:2px solid #e1e4e8
        }
        .place-order-btn{
            /* Primary action button to submit checkout form */
            width:100%;padding:1.2rem;border:none;border-radius:15px;
            background:linear-gradient(135deg,#28a745 0%,#20c997 100%);
            color:#fff;font-size:1.1rem;font-weight:700;cursor:pointer;
            transition:transform .2s;margin-top:1.5rem
        }
        .place-order-btn:hover{transform:translateY(-2px)}
        .error-msg{
            /* Error notification shown above the checkout grid */
            background:#ff4444;color:#fff;padding:1rem;
            border-radius:12px;margin-bottom:1.5rem
        }
        @media(max-width:968px){
            /* Responsive adjustments for smaller screens */
            .checkout-grid{grid-template-columns:1fr}
            .form-row{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="container">
    <h1>Checkout</h1>

    <?php if ($error): ?>
        <!-- Display validation or processing errors to the user -->
        <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="checkout-grid">
        <div class="checkout-form">
            <!-- Checkout form for entering shipping address and payment method -->
            <form method="POST">
                <?php echo csrfInput(); ?>
                <div class="section-title">Shipping Address</div>

                <div class="form-group">
                    <label>Street Address</label>
                    <input type="text" name="address" placeholder="123 Main Street" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" placeholder="New York" required>
                    </div>

                    <div class="form-group">
                        <label>Postal Code</label>
                        <input type="text" name="postal_code" placeholder="10001" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Country</label>
                    <select name="country_id" required>
                        <option value="">Select Country</option>
                        <?php while ($country = mysqli_fetch_assoc($countries)): ?>
                            <option value="<?php echo $country['country_id']; ?>">
                                <?php echo htmlspecialchars($country['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="section-title" style="margin-top:2rem">Payment Method</div>

                <div class="payment-options">
                    <!-- Payment method radio options -->
                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="CREDIT_CARD" required>
                        <span>Credit Card</span>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="DEBIT_CARD">
                        <span>Debit Card</span>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="PAYPAL">
                        <span>PayPal</span>
                    </label>

                    <label class="payment-option">
                        <input type="radio" name="payment_method" value="CASH_ON_DELIVERY">
                        <span>Cash on Delivery</span>
                    </label>
                </div>

                <!-- Submit button to place the order -->
                <button type="submit" class="place-order-btn">Place Order</button>
            </form>
        </div>

        <div class="order-summary">
            <!-- Summary of costs shown during checkout -->
            <div class="section-title">Order Summary</div>

            <div class="summary-item">
                <span>Subtotal</span>
                <span>$<?php echo number_format($cartTotal, 2); ?></span>
            </div>

            <div class="summary-item">
                <span>Shipping</span>
                <span>$5.00</span>
            </div>

            <div class="summary-item">
                <span>Tax (10%)</span>
                <span>$<?php echo number_format($cartTotal * 0.10, 2); ?></span>
            </div>

            <div class="summary-item summary-total">
                <span>Total</span>
                <span>$<?php echo number_format($cartTotal + 5 + ($cartTotal * 0.10), 2); ?></span>
            </div>
        </div>
    </div>
</div>
</body>
</html>
