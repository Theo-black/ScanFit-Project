<?php
// admin_logout.php

session_start();

// Only clear admin-related session keys so customer login remains intact if desired
unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_mfa_pending']);

header('Location: admin_login.php');
exit();
