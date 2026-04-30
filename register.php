<?php
// register.php

// Load shared helper functions (includes DB connection and auth helpers)
require_once 'functions.php';

// If user is already logged in, redirect them away from the signup page
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Initialize feedback message placeholders
$error = null;
$success = null;

if (!empty($_SESSION['error']) && is_string($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
if (!empty($_SESSION['success']) && is_string($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Handle form submission only when the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('register.php');

    // Sanitize and normalize incoming form values
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $acceptedTerms = isset($_POST['accept_terms']) && $_POST['accept_terms'] === '1';

    // Basic required field validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    // Ensure password and confirmation match
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
    // Enforce minimum password length
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif (!$acceptedTerms) {
        $error = 'You must agree to the ScanFit License Agreement and Terms & Conditions to create an account.';
    } else {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $signupError = null;
        $pendingSignupId = createPendingCustomerSignup($firstName, $lastName, $email, $phone, $passwordHash, getTermsAcceptanceMetadata(), $signupError);

        if (!$pendingSignupId) {
            $error = $signupError ?: 'Registration failed. Please try again.';
        } else {
            if (sendPendingCustomerSignupVerificationEmail($pendingSignupId, $email, $firstName)) {
                $success = 'Check your email and verify the link to finish creating your account.';
                $_POST = [];
            } else {
                $error = 'Registration started, but we could not send the verification email. Please contact support.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        /* Global reset and box sizing */
        *{margin:0;padding:0;box-sizing:border-box}
        /* Full-page centered gradient background */
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            min-height:100vh;display:flex;align-items:center;justify-content:center;
            padding:2rem
        }
        /* Main registration card container */
        .register-card{
            background:#fff;border-radius:25px;padding:3rem;
            box-shadow:0 30px 80px rgba(0,0,0,.25);max-width:550px;
            width:100%
        }
        /* Logo styling at top of card */
        .logo{
            text-align:center;font-size:2.5rem;font-weight:800;
            color:#667eea;margin-bottom:2rem;letter-spacing:1px
        }
        /* Main heading styles */
        h1{
            font-size:2rem;margin-bottom:.5rem;color:#2c3e50;
            text-align:center
        }
        /* Subtitle under heading */
        .subtitle{
            text-align:center;color:#666;margin-bottom:2rem
        }
        /* Two-column layout for first/last name on larger screens */
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        /* Generic form group spacing */
        .form-group{margin-bottom:1.5rem}
        /* Form label styling */
        .form-group label{
            display:block;font-weight:600;margin-bottom:.5rem;
            color:#2c3e50
        }
        /* Input field base styling */
        .form-group input{
            width:100%;padding:1rem;border:2px solid #e1e4e8;
            border-radius:12px;font-size:1rem;outline:none;
            transition:border-color .3s
        }
        /* Highlight input border when focused */
        .form-group input:focus{border-color:#667eea}
        /* Error message box styling */
        .error-msg{
            background:#ff4444;color:#fff;padding:1rem;
            border-radius:12px;margin-bottom:1.5rem;text-align:center
        }
        .success-msg{
            background:#dcfce7;color:#166534;padding:1rem;
            border-radius:12px;margin-bottom:1.5rem;text-align:center
        }
        /* Submit button styling */
        .submit-btn{
            width:100%;padding:1.2rem;border:none;border-radius:15px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;font-size:1.1rem;font-weight:700;cursor:pointer;
            transition:transform .2s
        }
        /* Slight lift on hover for button */
        .submit-btn:hover{transform:translateY(-2px)}
        .google-btn{
            display:block;width:100%;padding:1rem;margin-top:.85rem;
            border:2px solid #e1e4e8;border-radius:12px;text-align:center;
            text-decoration:none;color:#2c3e50;font-weight:700;background:#fff
        }
        .google-btn:hover{background:#f8fafc}
        .terms-box{
            display:flex;gap:.65rem;align-items:flex-start;margin:1rem 0 1.4rem;
            padding:.9rem;border:1px solid #e1e4e8;border-radius:12px;background:#f8fafc;
            color:#334155;font-size:.92rem;line-height:1.45
        }
        .terms-box input{width:auto;margin-top:.2rem;flex:0 0 auto}
        .terms-box a{color:#667eea;font-weight:700;text-decoration:none}
        .terms-box a:hover{text-decoration:underline}
        /* Existing account login text */
        .login-link{
            text-align:center;margin-top:1.5rem;color:#666
        }
        /* Login link styling */
        .login-link a{
            color:#667eea;font-weight:600;text-decoration:none
        }
        /* Underline login link when hovered */
        .login-link a:hover{text-decoration:underline}
        /* Stack form fields on small screens */
        @media(max-width:576px){
            .form-row{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
    <div class="register-card">
        <!-- Brand logo/name -->
        <div class="logo">SCANFIT</div>
        <!-- Page heading and short description -->
        <h1>Create Account</h1>
        <p class="subtitle">Join us and start shopping</p>

        <!-- Display validation or registration error if present -->
        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- User registration form -->
        <form method="POST">
            <?php echo csrfInput(); ?>
            <!-- First and last name fields -->
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" placeholder="John" required
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" placeholder="Doe" required
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                </div>
            </div>

            <!-- Email address field -->
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="your@email.com" required
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <!-- Optional phone number field -->
            <div class="form-group">
                <label>Phone (Optional)</label>
                <input type="tel" name="phone" placeholder="+18761234567"
                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>

            <!-- Password input -->
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="At least 6 characters" required>
            </div>

            <!-- Confirm password input -->
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" placeholder="Re-enter your password" required>
            </div>

            <label class="terms-box">
                <input type="checkbox" name="accept_terms" value="1" required <?php echo !empty($_POST['accept_terms']) ? 'checked' : ''; ?>>
                <span>
                    I have read and agree to the
                    <a href="terms.php" target="_blank" rel="noopener">ScanFit License Agreement and Terms &amp; Conditions</a>.
                </span>
            </label>

            <!-- Submit button to create the account -->
            <button type="submit" class="submit-btn">Create Account</button>
        </form>

        <?php if (isGoogleOAuthConfigured()): ?>
            <form method="POST" action="google_login.php?mode=signup">
                <?php echo csrfInput(); ?>
                <label class="terms-box">
                    <input type="checkbox" name="accept_terms" value="1" required>
                    <span>
                        I have read and agree to the
                        <a href="terms.php" target="_blank" rel="noopener">ScanFit License Agreement and Terms &amp; Conditions</a>.
                    </span>
                </label>
                <button type="submit" class="google-btn">Sign up with Google</button>
            </form>
        <?php endif; ?>

        <!-- Link to login page for existing users -->
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>
</body>
</html>
