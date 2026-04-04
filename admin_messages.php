<?php
require_once 'functions.php';

requireAdminRole(['SUPER_ADMIN', 'ADMIN', 'MODERATOR']);

$admin = getCurrentAdmin();
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('admin_messages.php');

    if (isset($_POST['delete_message'])) {
        $messageId = (int)($_POST['contact_message_id'] ?? 0);
        if ($messageId <= 0) {
            $error = 'Invalid message selected.';
        } elseif (deleteContactMessage($messageId)) {
            $success = 'Message deleted.';
        } else {
            $error = 'Unable to delete message.';
        }
    } elseif (isset($_POST['mark_all_read'])) {
        if (markAllContactMessagesRead()) {
            $success = 'All contact messages marked as read.';
        } else {
            $error = 'Unable to update contact messages.';
        }
    }
}

$unreadCount = getUnreadContactMessageCount();
$messages = getAllContactMessages();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Messages - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#0f172a;color:#e5e7eb;min-height:100vh}
        .topbar{
            background:#020617;padding:1rem 2rem;display:flex;align-items:center;justify-content:space-between;
            border-bottom:1px solid #111827
        }
        .brand{font-weight:800;font-size:1.4rem;color:#38bdf8}
        .topbar-actions{display:flex;align-items:center;gap:1rem}
        .notification-link{
            position:relative;display:inline-flex;align-items:center;justify-content:center;
            width:42px;height:42px;border-radius:999px;background:#111827;color:#e5e7eb;text-decoration:none;font-size:1.1rem
        }
        .notification-badge{
            position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 .35rem;border-radius:999px;
            background:#ef4444;color:#fff;font-size:.72rem;font-weight:700;display:flex;align-items:center;justify-content:center
        }
        .admin-info{font-size:.9rem;color:#9ca3af}
        .admin-info strong{color:#e5e7eb}
        .admin-info a{color:#f97373;text-decoration:none}
        .admin-info a:hover{text-decoration:underline}
        .container{max-width:1200px;margin:0 auto;padding:2rem}
        .page-head{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1.5rem}
        .page-head h1{font-size:2rem}
        .btn{
            display:inline-block;padding:.7rem 1.2rem;border:none;border-radius:999px;background:#38bdf8;color:#082f49;
            font-size:.9rem;font-weight:700;cursor:pointer;text-decoration:none
        }
        .btn-outline{background:transparent;border:1px solid #334155;color:#e5e7eb}
        .alert{padding:.85rem 1rem;border-radius:12px;margin-bottom:1rem;font-size:.92rem}
        .alert-success{background:#14532d;color:#dcfce7}
        .alert-error{background:#7f1d1d;color:#fee2e2}
        .message-list{display:grid;gap:1rem}
        .message-card{
            background:#020617;border:1px solid #1f2937;border-radius:16px;padding:1.2rem;
            box-shadow:0 15px 40px rgba(0,0,0,.25)
        }
        .message-card.unread{border-color:#38bdf8;box-shadow:0 0 0 1px rgba(56,189,248,.35)}
        .message-meta{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.8rem}
        .message-meta h2{font-size:1.1rem;color:#f8fafc}
        .message-badges{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap}
        .badge{display:inline-block;padding:.25rem .6rem;border-radius:999px;font-size:.75rem;font-weight:700}
        .badge-unread{background:#082f49;color:#7dd3fc}
        .badge-read{background:#1f2937;color:#cbd5e1}
        .badge-date{background:#111827;color:#94a3b8}
        .message-from{font-size:.9rem;color:#94a3b8;margin-bottom:.85rem}
        .message-body{white-space:pre-wrap;line-height:1.7;color:#e5e7eb}
        .message-actions{display:flex;justify-content:flex-end;margin-top:1rem}
        .btn-danger{background:#ef4444;color:#fff}
        .empty-state{
            background:#020617;border:1px dashed #334155;border-radius:16px;padding:2rem;text-align:center;color:#94a3b8
        }
        @media(max-width:768px){
            .topbar,.page-head{flex-direction:column;align-items:flex-start}
            .topbar-actions{width:100%;justify-content:space-between}
        }
    </style>
</head>
<body>
<div class="topbar">
    <div class="brand">SCANFIT ADMIN</div>
    <div class="topbar-actions">
        <a class="notification-link" href="admin_messages.php" aria-label="Contact messages">
            &#128276;
            <?php if ($unreadCount > 0): ?>
                <span class="notification-badge"><?php echo $unreadCount > 99 ? '99+' : (int)$unreadCount; ?></span>
            <?php endif; ?>
        </a>
        <div class="admin-info">
            Logged in as <strong><?php echo htmlspecialchars($admin['username'] ?? 'Admin'); ?></strong>
            (<?php echo htmlspecialchars($admin['role'] ?? 'ADMIN'); ?>)
            | <a href="admin_dashboard.php">Dashboard</a>
            | <a href="admin_logout.php">Logout</a>
        </div>
    </div>
</div>

<div class="container">
    <div class="page-head">
        <div>
            <h1>Contact Messages</h1>
            <p style="color:#94a3b8;margin-top:.35rem;">Messages submitted from the website contact form appear here.</p>
        </div>
        <form method="POST">
            <?php echo csrfInput(); ?>
            <button type="submit" name="mark_all_read" class="btn btn-outline">Mark All Read</button>
        </form>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($messages === []): ?>
        <div class="empty-state">No contact messages yet.</div>
    <?php else: ?>
        <div class="message-list">
            <?php foreach ($messages as $message): ?>
                <article class="message-card <?php echo !empty($message['is_read']) ? '' : 'unread'; ?>">
                    <div class="message-meta">
                        <div>
                            <h2><?php echo htmlspecialchars((string)$message['subject']); ?></h2>
                            <div class="message-from">
                                From <?php echo htmlspecialchars((string)$message['name']); ?>,
                                <a href="mailto:<?php echo htmlspecialchars((string)$message['email']); ?>" style="color:#7dd3fc;text-decoration:none;">
                                    <?php echo htmlspecialchars((string)$message['email']); ?>
                                </a>
                            </div>
                        </div>
                        <div class="message-badges">
                            <span class="badge <?php echo !empty($message['is_read']) ? 'badge-read' : 'badge-unread'; ?>">
                                <?php echo !empty($message['is_read']) ? 'Read' : 'Unread'; ?>
                            </span>
                            <span class="badge badge-date"><?php echo htmlspecialchars((string)$message['created_at']); ?></span>
                        </div>
                    </div>
                    <div class="message-body"><?php echo htmlspecialchars((string)$message['message']); ?></div>
                    <div class="message-actions">
                        <form method="POST">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="contact_message_id" value="<?php echo (int)$message['contact_message_id']; ?>">
                            <button type="submit" name="delete_message" class="btn btn-danger" onclick="return confirm('Delete this message permanently?');">Delete</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
