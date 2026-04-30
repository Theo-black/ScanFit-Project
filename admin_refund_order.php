<?php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders_admin.php');
    exit();
}

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($orderId <= 0) {
    $_SESSION['error'] = 'Invalid order selected.';
    header('Location: orders_admin.php');
    exit();
}

requireCsrfPost('order_view_admin.php?id=' . $orderId);

global $conn;
mysqli_begin_transaction($conn);

try {
    $sql = "
        SELECT o.order_id, o.status, p.payment_id, p.method_name, p.payment_status, p.provider_payment_id, p.amount
        FROM `order` o
        INNER JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Failed to load payment.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    if (!$row) {
        throw new Exception('Order payment not found.');
    }
    if (($row['method_name'] ?? '') !== 'STRIPE_CARD') {
        throw new Exception('Only Stripe card payments can be refunded here.');
    }
    if (($row['payment_status'] ?? '') !== 'COMPLETED') {
        throw new Exception('Only completed payments can be refunded.');
    }

    $paymentIntentId = trim((string)($row['provider_payment_id'] ?? ''));
    if ($paymentIntentId === '') {
        throw new Exception('Stripe payment intent is missing.');
    }

    $refund = createStripeRefund($paymentIntentId);
    $refundId = (string)($refund['id'] ?? '');
    if ($refundId === '') {
        throw new Exception('Stripe did not return a refund id.');
    }

    $metadataJson = json_encode($refund, JSON_UNESCAPED_SLASHES);
    $paymentSql = "
        UPDATE payment
        SET payment_status = 'REFUNDED',
            metadata_json = ?,
            updated_at = NOW()
        WHERE payment_id = ?
    ";
    $paymentStmt = mysqli_prepare($conn, $paymentSql);
    if (!$paymentStmt) {
        throw new Exception('Failed to prepare payment refund update.');
    }
    mysqli_stmt_bind_param($paymentStmt, 'si', $metadataJson, $row['payment_id']);
    if (!mysqli_stmt_execute($paymentStmt)) {
        throw new Exception('Failed to update refunded payment.');
    }

    if (in_array($row['status'], ['PENDING', 'PROCESSING'], true)) {
        restockOrderItems($orderId, 'Stripe refund restock for order #' . $orderId);
        $orderSql = "UPDATE `order` SET status = 'CANCELLED', updated_at = NOW() WHERE order_id = ? LIMIT 1";
        $orderStmt = mysqli_prepare($conn, $orderSql);
        if (!$orderStmt) {
            throw new Exception('Failed to prepare order cancellation.');
        }
        mysqli_stmt_bind_param($orderStmt, 'i', $orderId);
        if (!mysqli_stmt_execute($orderStmt)) {
            throw new Exception('Failed to cancel refunded order.');
        }
    }

    mysqli_commit($conn);
    sendOrderCustomerEmail($orderId, 'cancelled');
    $_SESSION['success'] = 'Stripe payment refunded.';
    header('Location: order_view_admin.php?id=' . $orderId);
    exit();
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header('Location: order_view_admin.php?id=' . $orderId);
    exit();
}
