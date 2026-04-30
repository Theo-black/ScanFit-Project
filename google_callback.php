<?php
require_once 'functions.php';

$rawState = trim((string)($_GET['state'] ?? ''));
$state = consumeGoogleOAuthState($rawState);

if (!$state) {
    $fallback = 'login.php';
    $_SESSION['error'] = 'Google sign in session expired. Please try again.';
    header('Location: ' . $fallback);
    exit();
}

$mode = ($state['mode'] ?? 'login') === 'signup' ? 'signup' : 'login';
$fallback = ($mode === 'signup') ? 'register.php' : 'login.php';

if (!empty($_GET['error'])) {
    $_SESSION['error'] = 'Google sign in was cancelled or denied.';
    header('Location: ' . $fallback);
    exit();
}

$code = trim((string)($_GET['code'] ?? ''));
if ($code === '') {
    $_SESSION['error'] = 'Google sign in failed. Authorization code missing.';
    header('Location: ' . $fallback);
    exit();
}

$tokenError = null;
$tokenResponse = exchangeGoogleAuthCode($code, $tokenError);
if (!$tokenResponse || empty($tokenResponse['access_token'])) {
    $detail = $tokenError ? (' Details: ' . $tokenError) : '';
    $_SESSION['error'] = 'Google sign in failed. Could not verify your account.' . $detail;
    header('Location: ' . $fallback);
    exit();
}

$profileError = null;
$profile = fetchGoogleUserProfile((string)$tokenResponse['access_token'], $profileError);
if (!$profile) {
    $detail = $profileError ? (' Details: ' . $profileError) : '';
    $_SESSION['error'] = 'Google sign in failed. Could not read your profile.' . $detail;
    header('Location: ' . $fallback);
    exit();
}

$authError = null;
if ($mode === 'signup') {
    $termsAcceptance = $_SESSION['google_signup_terms_acceptance'] ?? null;
    unset($_SESSION['google_signup_terms_acceptance']);
    if (!is_array($termsAcceptance) || empty($termsAcceptance['version'])) {
        $_SESSION['error'] = 'Please agree to the ScanFit License Agreement and Terms & Conditions before signing up with Google.';
        header('Location: register.php');
        exit();
    }

    $pendingSignupId = createPendingGoogleSignupFromGoogle($profile, $termsAcceptance, $authError);
    if (!$pendingSignupId) {
        $_SESSION['error'] = $authError ?: 'Google sign up failed. Please try again.';
        header('Location: ' . $fallback);
        exit();
    }

    $email = trim((string)($profile['email'] ?? ''));
    $firstName = trim((string)($profile['given_name'] ?? ''));
    if ($firstName === '') {
        [$firstName] = splitDisplayName((string)($profile['name'] ?? ''));
    }
    if ($firstName === '') {
        $firstName = 'there';
    }

    if (!sendPendingGoogleSignupVerificationEmail($pendingSignupId, $email, $firstName)) {
        $_SESSION['error'] = 'Google sign up started, but we could not send the verification email. Please contact support.';
        header('Location: ' . $fallback);
        exit();
    }

    $_SESSION['success'] = 'Check your email and verify the link to finish creating your account.';
    header('Location: register.php');
    exit();
}

$customerId = findCustomerForGoogleLogin($profile, $authError);
if (!$customerId) {
    $_SESSION['error'] = $authError ?: 'Google sign in failed. Please try again.';
    header('Location: ' . $fallback);
    exit();
}

if (requiresMFA($customerId)) {
    unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['force_reset_user_id']);
    $_SESSION['pending_mfa_user_id'] = $customerId;
    $_SESSION['pending_force_password_reset'] = 0;
    header('Location: login.php');
    exit();
}

session_regenerate_id(true);
$_SESSION['customer_id'] = $customerId;

$customer = getCustomerInfo($customerId);
if ($customer) {
    $displayName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    if ($displayName !== '') {
        $_SESSION['customer_name'] = $displayName;
    }
}

unset($_SESSION['pending_mfa_user_id'], $_SESSION['pending_force_password_reset'], $_SESSION['force_reset_user_id']);

header('Location: index.php');
exit();
