<?php
require_once 'functions.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$mode = (($_GET['mode'] ?? 'login') === 'signup') ? 'signup' : 'login';
$fallback = ($mode === 'signup') ? 'register.php' : 'login.php';

if ($mode === 'signup') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error'] = 'Please agree to the ScanFit License Agreement and Terms & Conditions before signing up with Google.';
        header('Location: register.php');
        exit();
    }
    requireCsrfPost('register.php');
    if (($_POST['accept_terms'] ?? '') !== '1') {
        $_SESSION['error'] = 'You must agree to the ScanFit License Agreement and Terms & Conditions to create an account.';
        header('Location: register.php');
        exit();
    }
    $_SESSION['google_signup_terms_acceptance'] = getTermsAcceptanceMetadata();
}

$authUrl = createGoogleOAuthUrl($mode);
if ($authUrl === null) {
    $_SESSION['error'] = 'Google sign in is not configured yet. Please contact support.';
    header('Location: ' . $fallback);
    exit();
}

header('Location: ' . $authUrl);
exit();
