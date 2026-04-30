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

$newStatus = strtoupper(trim((string)($_POST['status'] ?? '')));
$shippingCarrier = trim((string)($_POST['shipping_carrier'] ?? ''));
$trackingNumber = trim((string)($_POST['tracking_number'] ?? ''));
$allowedStatuses = ['PENDING', 'PROCESSING', 'SHIPPED', 'DELIVERED'];
if (!in_array($newStatus, $allowedStatuses, true)) {
    $_SESSION['error'] = 'Invalid order status selected.';
    header('Location: order_view_admin.php?id=' . $orderId);
    exit();
}

global $conn;
mysqli_begin_transaction($conn);

try {
    $sql = "
        SELECT o.order_id, o.status, p.payment_id, p.method_name, p.payment_status
        FROM `order` o
        LEFT JOIN payment p ON o.order_id = p.order_id
        WHERE o.order_id = ?
        LIMIT 1
        FOR UPDATE
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception('Failed to load order.');
    }
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($res);

    if (!$order) {
        throw new Exception('Order not found.');
    }

    $currentStatus = (string)$order['status'];
    if ($currentStatus === 'CANCELLED') {
        throw new Exception('Cancelled orders cannot be moved back into fulfillment.');
    }
    if ($currentStatus === 'DELIVERED' && $newStatus !== 'DELIVERED') {
        throw new Exception('Delivered orders cannot be moved backward.');
    }

    $rank = ['PENDING' => 1, 'PROCESSING' => 2, 'SHIPPED' => 3, 'DELIVERED' => 4];
    if (($rank[$newStatus] ?? 0) < ($rank[$currentStatus] ?? 0)) {
        throw new Exception('Order status cannot be moved backward.');
    }

    $method = (string)($order['method_name'] ?? '');
    $paymentStatus = (string)($order['payment_status'] ?? '');
    if ($method === 'STRIPE_CARD' && $paymentStatus !== 'COMPLETED') {
        throw new Exception('Card orders must be paid before fulfillment.');
    }

    if (in_array($newStatus, ['SHIPPED', 'DELIVERED'], true) && $trackingNumber === '') {
        throw new Exception('Tracking number is required before marking an order shipped or delivered.');
    }

    $setShippedAt = in_array($newStatus, ['SHIPPED', 'DELIVERED'], true) && $currentStatus !== 'SHIPPED' && $currentStatus !== 'DELIVERED';
    $setDeliveredAt = $newStatus === 'DELIVERED' && $currentStatus !== 'DELIVERED';

    $updateSql = "
        UPDATE `order`
        SET status = ?,
            shipping_carrier = NULLIF(?, ''),
            tracking_number = NULLIF(?, ''),
            shipped_at = " . ($setShippedAt ? "COALESCE(shipped_at, NOW())" : "shipped_at") . ",
            delivered_at = " . ($setDeliveredAt ? "COALESCE(delivered_at, NOW())" : "delivered_at") . ",
            updated_at = NOW()
        WHERE order_id = ?
        LIMIT 1
    ";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        throw new Exception('Failed to prepare status update.');
    }
    mysqli_stmt_bind_param($updateStmt, 'sssi', $newStatus, $shippingCarrier, $trackingNumber, $orderId);
    if (!mysqli_stmt_execute($updateStmt)) {
        throw new Exception('Failed to update order status.');
    }

    if ($newStatus === 'DELIVERED' && $method === 'CASH_ON_DELIVERY' && $paymentStatus !== 'COMPLETED') {
        $paymentSql = "UPDATE payment SET payment_status = 'COMPLETED', updated_at = NOW() WHERE order_id = ? LIMIT 1";
        $paymentStmt = mysqli_prepare($conn, $paymentSql);
        if (!$paymentStmt) {
            throw new Exception('Failed to prepare COD payment update.');
        }
        mysqli_stmt_bind_param($paymentStmt, 'i', $orderId);
        if (!mysqli_stmt_execute($paymentStmt)) {
            throw new Exception('Failed to update COD payment status.');
        }
    }

    mysqli_commit($conn);
    sendOrderCustomerEmail($orderId, 'status');
    $_SESSION['success'] = 'Order status updated to ' . $newStatus . '.';
    header('Location: order_view_admin.php?id=' . $orderId);
    exit();
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header('Location: order_view_admin.php?id=' . $orderId);
    exit();
}
