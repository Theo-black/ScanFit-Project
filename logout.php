<?php
// logout.php
session_start();
unset($_SESSION['pending_mfa_user_id']);
session_destroy();
header('Location: index.php');
exit();
?>