<?php
// cancel_order.php

// Load shared functions (authentication, order helpers, etc.)
require_once 'functions.php';

// Ensure the user is logged in before allowing order cancellation
requireLogin();
// Get the currently authenticated customer's ID
$customerId = getCustomerId();

// Only accept POST requests to perform an order cancellation
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit();
}
requireCsrfPost('orders.php');

// Read and sanitize the order ID from the POST payload
$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;

// Validate that a valid order ID was provided
if ($orderId <= 0) {
    $_SESSION['error'] = 'Invalid order.';
    header('Location: orders.php');
    exit();
}

// Attempt to cancel the order for this customer and set a flash message
if (cancelOrder($orderId, $customerId)) {
    $_SESSION['success'] = 'Order cancelled successfully.';
} else {
    $_SESSION['error'] = 'Unable to cancel this order.';
}

// Redirect back to the orders page after processing
header('Location: orders.php');
exit();
