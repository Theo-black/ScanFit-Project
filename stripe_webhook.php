<?php
// stripe_webhook.php

require_once 'functions.php';

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if ($payload === false || !verifyStripeWebhookSignature($payload, $signature)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit();
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit();
}

$eventType = (string)($event['type'] ?? '');
$session = $event['data']['object'] ?? [];
$sessionId = is_array($session) ? (string)($session['id'] ?? '') : '';

if (
    $sessionId !== ''
    && in_array($eventType, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)
) {
    fulfillStripeCheckoutSession($sessionId);
}

if ($sessionId !== '' && $eventType === 'checkout.session.async_payment_failed') {
    cancelPendingStripeCheckoutSession($sessionId);
}

http_response_code(200);
echo 'ok';
