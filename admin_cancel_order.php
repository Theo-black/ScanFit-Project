<?php
require_once 'functions.php';
requireAdminRole(['SUPER_ADMIN', 'ADMIN']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders_admin.php');
    exit();
}

requireCsrfPost('orders_admin.php');

$orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($orderId <= 0) {
    header('Location: orders_admin.php?error=1');
    exit();
}

global $conn;
mysqli_begin_transaction($conn);

try {
    $orderSql = "SELECT order_id, status FROM `order` WHERE order_id = ? LIMIT 1";
    $orderStmt = mysqli_prepare($conn, $orderSql);
    if (!$orderStmt) {
        throw new Exception('Failed to load order.');
    }
    mysqli_stmt_bind_param($orderStmt, 'i', $orderId);
    mysqli_stmt_execute($orderStmt);
    $orderRes = mysqli_stmt_get_result($orderStmt);
    $order = mysqli_fetch_assoc($orderRes);

    if (!$order || !in_array($order['status'], ['PENDING', 'PROCESSING'], true)) {
        throw new Exception('Order cannot be cancelled.');
    }

    $updateSql = "UPDATE `order` SET status = 'CANCELLED', updated_at = NOW() WHERE order_id = ? LIMIT 1";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        throw new Exception('Failed to update order status.');
    }
    mysqli_stmt_bind_param($updateStmt, 'i', $orderId);
    if (!mysqli_stmt_execute($updateStmt) || mysqli_stmt_affected_rows($updateStmt) !== 1) {
        throw new Exception('Failed to cancel order.');
    }

    $itemsSql = "SELECT variant_id, quantity FROM orderitem WHERE order_id = ?";
    $itemsStmt = mysqli_prepare($conn, $itemsSql);
    if (!$itemsStmt) {
        throw new Exception('Failed to load order items.');
    }
    mysqli_stmt_bind_param($itemsStmt, 'i', $orderId);
    mysqli_stmt_execute($itemsStmt);
    $itemsRes = mysqli_stmt_get_result($itemsStmt);

    while ($item = mysqli_fetch_assoc($itemsRes)) {
        $variantId = (int)$item['variant_id'];
        $qty = (int)$item['quantity'];

        $restockSql = "UPDATE productvariant SET stock_quantity = stock_quantity + ? WHERE variant_id = ?";
        $restockStmt = mysqli_prepare($conn, $restockSql);
        if (!$restockStmt) {
            throw new Exception('Failed to restock item.');
        }
        mysqli_stmt_bind_param($restockStmt, 'ii', $qty, $variantId);
        if (!mysqli_stmt_execute($restockStmt) || mysqli_stmt_affected_rows($restockStmt) !== 1) {
            throw new Exception('Failed to restock item.');
        }

        $auditSql = "
            INSERT INTO stockmovement (variant_id, movement_type, quantity, reference_id, notes)
            VALUES (?, 'IN', ?, ?, ?)
        ";
        $auditNote = 'Order #' . $orderId . ' cancelled by admin';
        $auditStmt = mysqli_prepare($conn, $auditSql);
        if (!$auditStmt) {
            throw new Exception('Failed to save stock movement.');
        }
        mysqli_stmt_bind_param($auditStmt, 'iiis', $variantId, $qty, $orderId, $auditNote);
        if (!mysqli_stmt_execute($auditStmt)) {
            throw new Exception('Failed to save stock movement.');
        }
    }

    mysqli_commit($conn);
    sendOrderCustomerEmail($orderId, 'cancelled');
    header('Location: order_view_admin.php?id=' . $orderId . '&cancelled=1');
    exit();
} catch (Exception $e) {
    mysqli_rollback($conn);
    header('Location: order_view_admin.php?id=' . $orderId . '&error=1');
    exit();
}
