<?php
require_once 'functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$mode = (($_GET['mode'] ?? 'login') === 'signup') ? 'signup' : 'login';
$fallback = ($mode === 'signup') ? 'register.php' : 'login.php';

$authUrl = createGoogleOAuthUrl($mode);
if ($authUrl === null) {
    $_SESSION['error'] = 'Google sign in is not configured yet. Please contact support.';
    header('Location: ' . $fallback);
    exit();
}

header('Location: ' . $authUrl);
exit();

