<?php
// stripe_checkout_success.php

require_once 'functions.php';
requireLogin();

$sessionId = trim((string)($_GET['session_id'] ?? ''));
if ($sessionId === '') {
    $_SESSION['error'] = 'Missing payment confirmation. Please check your order status.';
    header('Location: orders.php');
    exit();
}

$orderId = fulfillStripeCheckoutSession($sessionId);
if ($orderId === null) {
    $_SESSION['error'] = 'We could not confirm your card payment yet. Please check your orders shortly.';
    header('Location: orders.php');
    exit();
}

header('Location: orders.php?success=1');
exit();
