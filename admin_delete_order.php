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
    $checkSql = "SELECT status FROM `order` WHERE order_id = ? LIMIT 1";
    $checkStmt = mysqli_prepare($conn, $checkSql);
    if (!$checkStmt) {
        throw new Exception('Unable to load order.');
    }
    mysqli_stmt_bind_param($checkStmt, 'i', $orderId);
    mysqli_stmt_execute($checkStmt);
    $checkRes = mysqli_stmt_get_result($checkStmt);
    $order = mysqli_fetch_assoc($checkRes);

    if (!$order) {
        throw new Exception('Order not found.');
    }
    if ($order['status'] !== 'CANCELLED') {
        throw new Exception('Only cancelled orders can be deleted.');
    }

    $deleteSql = "DELETE FROM `order` WHERE order_id = ? AND status = 'CANCELLED' LIMIT 1";
    $deleteStmt = mysqli_prepare($conn, $deleteSql);
    if (!$deleteStmt) {
        throw new Exception('Unable to prepare delete.');
    }
    mysqli_stmt_bind_param($deleteStmt, 'i', $orderId);
    if (!mysqli_stmt_execute($deleteStmt) || mysqli_stmt_affected_rows($deleteStmt) !== 1) {
        throw new Exception('Unable to delete order.');
    }

    mysqli_commit($conn);
    $_SESSION['success'] = 'Cancelled order deleted successfully.';
    header('Location: orders_admin.php');
    exit();
} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = $e->getMessage();
    header('Location: order_view_admin.php?id=' . $orderId . '&error=1');
    exit();
}
