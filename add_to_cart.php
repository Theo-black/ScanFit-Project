<?php
//add_to_cart.php 


// Load shared application functions (authentication, cart helpers, etc.)
require_once 'functions.php';

// Ensure user is logged in before allowing cart operations
if (!isLoggedIn()) {
    // Store error message in session and redirect to login page
    $_SESSION['error'] = 'Please login to add items to cart';
    header('Location: login.php');
    exit();
}

// Only allow this script to handle POST requests (protects against direct GET access)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}
requireCsrfPost('index.php');

// Safely read product, variant, and quantity values from POST, applying integer casting and defaults
$productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$variantId = isset($_POST['variant_id']) ? (int)$_POST['variant_id'] : 0;
$quantity  = isset($_POST['quantity'])   ? (int)$_POST['quantity']   : 1;

// Basic validation to ensure required IDs and quantity are valid positive integers
if ($productId <= 0 || $variantId <= 0 || $quantity <= 0) {
    $_SESSION['error'] = 'Invalid product, variant, or quantity';
    header('Location: index.php');
    exit();
}

// Retrieve the currently logged-in customer's ID from session or helper
$customerId = getCustomerId();

// Attempt to add item to the user's cart and set feedback message accordingly
if (addToCart($customerId, $productId, $variantId, $quantity)) {
    $_SESSION['success'] = 'Item added to cart successfully';
} else {
    $_SESSION['error'] = 'Failed to add item to cart (insufficient stock or invalid variant).';
}

// Redirect back to the originating page if available, otherwise go to homepage
$referer = $_SERVER['HTTP_REFERER'] ?? 'index.php';
$parsed = parse_url($referer);
$currentHost = $_SERVER['HTTP_HOST'] ?? '';
if (
    !is_array($parsed) ||
    (isset($parsed['host']) && !hash_equals($currentHost, $parsed['host'])) ||
    (!isset($parsed['path']))
) {
    $referer = 'index.php';
}
header("Location: $referer");
exit();
