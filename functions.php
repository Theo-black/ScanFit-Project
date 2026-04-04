<?php
// functions.php
// Central shared functions for ScanFit


// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// Use the single, shared DB connection
require_once 'Connectdb.php';   // must define $conn (mysqli)

function ensureCustomerSecurityColumns(): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    global $conn;

    $requiredColumns = [
        'failed_login_attempts' => "ALTER TABLE customer ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0",
        'account_locked' => "ALTER TABLE customer ADD COLUMN account_locked TINYINT(1) NOT NULL DEFAULT 0",
        'account_blocked' => "ALTER TABLE customer ADD COLUMN account_blocked TINYINT(1) NOT NULL DEFAULT 0",
        'password_reset_required' => "ALTER TABLE customer ADD COLUMN password_reset_required TINYINT(1) NOT NULL DEFAULT 0",
        'theme_preference' => "ALTER TABLE customer ADD COLUMN theme_preference VARCHAR(32) NOT NULL DEFAULT 'default'",
        'gender' => "ALTER TABLE customer ADD COLUMN gender VARCHAR(16) DEFAULT NULL",
        'profile_image' => "ALTER TABLE customer ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL",
        'theme_custom_json' => "ALTER TABLE customer ADD COLUMN theme_custom_json TEXT DEFAULT NULL",
        'google_sub' => "ALTER TABLE customer ADD COLUMN google_sub VARCHAR(191) DEFAULT NULL",
        'email_verified' => "ALTER TABLE customer ADD COLUMN email_verified TINYINT(1) NOT NULL DEFAULT 0",
        'email_verification_token' => "ALTER TABLE customer ADD COLUMN email_verification_token VARCHAR(255) DEFAULT NULL",
        'email_verification_expires_at' => "ALTER TABLE customer ADD COLUMN email_verification_expires_at DATETIME DEFAULT NULL",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        $escapedColumn = mysqli_real_escape_string($conn, $columnName);
        $checkSql = "
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = 'customer'
              AND column_name = '{$escapedColumn}'
            LIMIT 1
        ";
        $res = mysqli_query($conn, $checkSql);
        if ($res && mysqli_num_rows($res) === 0) {
            @mysqli_query($conn, $alterSql);
        }
    }

    $indexCheckSql = "
        SELECT 1
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = 'customer'
          AND index_name = 'uniq_customer_google_sub'
        LIMIT 1
    ";
    $indexRes = mysqli_query($conn, $indexCheckSql);
    if ($indexRes && mysqli_num_rows($indexRes) === 0) {
        @mysqli_query($conn, "ALTER TABLE customer ADD UNIQUE KEY uniq_customer_google_sub (google_sub)");
    }

    $pendingGoogleSignupTableSql = "
        CREATE TABLE IF NOT EXISTS pending_google_signup (
            pending_google_signup_id INT NOT NULL AUTO_INCREMENT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            google_sub VARCHAR(191) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            verification_token VARCHAR(255) DEFAULT NULL,
            verification_expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (pending_google_signup_id),
            UNIQUE KEY uniq_pending_google_signup_email (email),
            UNIQUE KEY uniq_pending_google_signup_google_sub (google_sub)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ";
    @mysqli_query($conn, $pendingGoogleSignupTableSql);

    $pendingCustomerSignupTableSql = "
        CREATE TABLE IF NOT EXISTS pending_customer_signup (
            pending_customer_signup_id INT NOT NULL AUTO_INCREMENT,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            verification_token VARCHAR(255) DEFAULT NULL,
            verification_expires_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (pending_customer_signup_id),
            UNIQUE KEY uniq_pending_customer_signup_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ";
    @mysqli_query($conn, $pendingCustomerSignupTableSql);

    $contactMessageTableSql = "
        CREATE TABLE IF NOT EXISTS contact_message (
            contact_message_id INT NOT NULL AUTO_INCREMENT,
            name VARCHAR(150) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (contact_message_id),
            KEY idx_contact_message_read_created (is_read, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci
    ";
    @mysqli_query($conn, $contactMessageTableSql);
}

ensureCustomerSecurityColumns();


// ------------- BASIC AUTH HELPERS -------------


function isLoggedIn(): bool
{
    return isset($_SESSION['customer_id']) && !empty($_SESSION['customer_id']);
}


function getCustomerId(): int
{
    return isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : 0;
}

function scanfitSizeRank(string $size): int
{
    $map = ['XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5, 'XXL' => 6];
    return $map[$size] ?? 3;
}

function scanfitSizeFromRank(int $rank): string
{
    $map = [1 => 'XS', 2 => 'S', 3 => 'M', 4 => 'L', 5 => 'XL', 6 => 'XXL'];
    return $map[$rank] ?? 'M';
}

function scanfitRecommendSizeFromMeasurements(float $chestCm, float $waistCm, float $hipsCm): string
{
    $score = max($chestCm, $waistCm, $hipsCm);
    if ($score < 86) return 'XS';
    if ($score < 94) return 'S';
    if ($score < 102) return 'M';
    if ($score < 110) return 'L';
    if ($score < 118) return 'XL';
    return 'XXL';
}

function scanfitRecommendSizeFromBmiHeight(float $bmi, float $heightCm): string
{
    if ($bmi < 20) {
        $size = 'XS';
    } elseif ($bmi < 22) {
        $size = 'S';
    } elseif ($bmi < 25) {
        $size = 'M';
    } elseif ($bmi < 28) {
        $size = 'L';
    } elseif ($bmi < 30) {
        $size = 'XL';
    } else {
        $size = 'XXL';
    }

    if ($heightCm < 160 && $size !== 'XS') {
        $down = ['S' => 'XS', 'M' => 'S', 'L' => 'M', 'XL' => 'L', 'XXL' => 'XL'];
        if (isset($down[$size])) {
            $size = $down[$size];
        }
    }
    if ($heightCm > 190 && $size !== 'XXL') {
        $up = ['XS' => 'S', 'S' => 'M', 'M' => 'L', 'L' => 'XL', 'XL' => 'XXL'];
        if (isset($up[$size])) {
            $size = $up[$size];
        }
    }

    return $size;
}

function getCustomerSavedFitSize(int $customerId): ?string
{
    $measurement = getLatestBodyMeasurement($customerId);
    if (!$measurement) {
        return null;
    }

    $heightCm = !empty($measurement['height_cm']) ? (float)$measurement['height_cm'] : 0.0;
    $weightKg = !empty($measurement['weight_kg']) ? (float)$measurement['weight_kg'] : 0.0;
    $chestCm = !empty($measurement['chest_cm']) ? (float)$measurement['chest_cm'] : null;
    $waistCm = !empty($measurement['waist_cm']) ? (float)$measurement['waist_cm'] : null;
    $hipsCm = !empty($measurement['hips_cm']) ? (float)$measurement['hips_cm'] : null;

    if ($heightCm <= 0 || $weightKg <= 0) {
        return null;
    }

    $heightM = $heightCm / 100;
    if ($heightM <= 0) {
        return null;
    }

    $bmi = $weightKg / ($heightM * $heightM);
    $baseSize = scanfitRecommendSizeFromBmiHeight($bmi, $heightCm);
    $finalRank = scanfitSizeRank($baseSize);

    if ($chestCm !== null && $waistCm !== null && $hipsCm !== null) {
        $measurementSize = scanfitRecommendSizeFromMeasurements($chestCm, $waistCm, $hipsCm);
        $finalRank = (int)round(($finalRank + scanfitSizeRank($measurementSize)) / 2);
    }

    if ($waistCm !== null && $heightCm > 0) {
        $ratio = $waistCm / $heightCm;
        if ($ratio >= 0.60) {
            $finalRank += 2;
        } elseif ($ratio >= 0.53) {
            $finalRank += 1;
        } elseif ($ratio < 0.43) {
            $finalRank -= 1;
        }
    }

    return scanfitSizeFromRank(max(1, min(6, $finalRank)));
}

function getShoppingProfileContext(?int $customerId = null): array
{
    $profileFitSize = ($customerId && $customerId > 0) ? getCustomerSavedFitSize($customerId) : null;
    $sessionData = $_SESSION['shopping_profile'] ?? [];

    $mode = ($sessionData['mode'] ?? 'self') === 'other' ? 'other' : 'self';
    $recipientName = trim((string)($sessionData['recipient_name'] ?? ''));
    $recipientSize = strtoupper(trim((string)($sessionData['recipient_size'] ?? '')));
    if (!in_array($recipientSize, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true)) {
        $recipientSize = '';
    }

    if ($mode === 'self') {
        $effectiveSize = $profileFitSize;
    } else {
        $effectiveSize = $recipientSize !== '' ? $recipientSize : ($profileFitSize ?? 'M');
    }

    return [
        'mode' => $mode,
        'recipient_name' => $recipientName,
        'recipient_size' => $recipientSize,
        'profile_fit_size' => $profileFitSize,
        'effective_size' => $effectiveSize,
    ];
}

function setShoppingProfileContext(string $mode, string $recipientName = '', string $recipientSize = ''): void
{
    $mode = $mode === 'other' ? 'other' : 'self';
    $recipientName = trim($recipientName);
    $recipientName = mb_substr($recipientName, 0, 80);
    $recipientSize = strtoupper(trim($recipientSize));
    if (!in_array($recipientSize, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true)) {
        $recipientSize = '';
    }

    $_SESSION['shopping_profile'] = [
        'mode' => $mode,
        'recipient_name' => $mode === 'other' ? $recipientName : '',
        'recipient_size' => $mode === 'other' ? $recipientSize : '',
    ];
}

function saveContactMessage(string $name, string $email, string $subject, string $message): bool
{
    global $conn;

    $sql = "
        INSERT INTO contact_message (name, email, subject, message, is_read, created_at)
        VALUES (?, ?, ?, ?, 0, NOW())
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $subject, $message);
    return mysqli_stmt_execute($stmt);
}

function getUnreadContactMessageCount(): int
{
    global $conn;

    $sql = "SELECT COUNT(*) AS total FROM contact_message WHERE is_read = 0";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return 0;
    }
    $row = mysqli_fetch_assoc($res);
    return (int)($row['total'] ?? 0);
}

function getAllContactMessages(): array
{
    global $conn;

    $sql = "
        SELECT contact_message_id, name, email, subject, message, is_read, created_at
        FROM contact_message
        ORDER BY is_read ASC, created_at DESC
    ";
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return [];
    }

    $messages = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $messages[] = $row;
    }
    return $messages;
}

function markAllContactMessagesRead(): bool
{
    global $conn;
    return (bool)mysqli_query($conn, "UPDATE contact_message SET is_read = 1 WHERE is_read = 0");
}

function deleteContactMessage(int $messageId): bool
{
    global $conn;

    $sql = "DELETE FROM contact_message WHERE contact_message_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $messageId);
    return mysqli_stmt_execute($stmt);
}


function requireLogin(): void
{
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php');
        exit();
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function verifyCsrfToken(?string $token): bool
{
    if (!isset($_SESSION['csrf_token']) || !is_string($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function requireCsrfPost(string $fallback = 'index.php'): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = $_POST['csrf_token'] ?? null;
    if (!verifyCsrfToken($token)) {
        http_response_code(403);
        $_SESSION['error'] = 'Invalid security token. Please try again.';
        header('Location: ' . $fallback);
        exit();
    }
}

function getGoogleOAuthConfig(): array
{
    return [
        'client_id' => trim((string)(getenv('SCANFIT_GOOGLE_CLIENT_ID') ?: '')),
        'client_secret' => trim((string)(getenv('SCANFIT_GOOGLE_CLIENT_SECRET') ?: '')),
        'redirect_uri' => trim((string)(getenv('SCANFIT_GOOGLE_REDIRECT_URI') ?: '')),
    ];
}

function getAppBaseUrl(): string
{
    $fromEnv = trim((string)(getenv('SCANFIT_APP_URL') ?: ''));
    if ($fromEnv !== '') {
        return rtrim($fromEnv, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $https ? 'https' : 'http';
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $scriptName = trim((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = str_replace('\\', '/', dirname($scriptName));
    $dir = rtrim($dir, '/.');

    return $scheme . '://' . $host . ($dir !== '' ? $dir : '');
}

function getMailFromAddress(): string
{
    $from = trim((string)(getenv('SCANFIT_MAIL_FROM') ?: ''));
    return $from !== '' ? $from : 'no-reply@scanfit.local';
}

function getMailFromName(): string
{
    $name = trim((string)(getenv('SCANFIT_MAIL_FROM_NAME') ?: ''));
    return $name !== '' ? $name : 'ScanFit';
}

function getSmtpConfig(): array
{
    $rawPassword = trim((string)(getenv('SCANFIT_SMTP_PASSWORD') ?: ''));
    return [
        'host' => trim((string)(getenv('SCANFIT_SMTP_HOST') ?: '')),
        'port' => (int)(getenv('SCANFIT_SMTP_PORT') ?: 0),
        'encryption' => strtolower(trim((string)(getenv('SCANFIT_SMTP_ENCRYPTION') ?: ''))),
        'username' => trim((string)(getenv('SCANFIT_SMTP_USERNAME') ?: '')),
        'password' => preg_replace('/\s+/', '', $rawPassword) ?? $rawPassword,
    ];
}

function isSmtpConfigured(): bool
{
    $cfg = getSmtpConfig();
    return $cfg['host'] !== ''
        && $cfg['port'] > 0
        && in_array($cfg['encryption'], ['tls', 'ssl', ''], true)
        && $cfg['username'] !== ''
        && $cfg['password'] !== '';
}

function readSmtpResponse($socket): string
{
    $response = '';
    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;
        if (preg_match('/^\d{3}\s/', $line) === 1) {
            break;
        }
    }
    return $response;
}

function sendSmtpCommand($socket, string $command, array $expectedCodes): string
{
    fwrite($socket, $command . "\r\n");
    $response = readSmtpResponse($socket);
    $code = (int)substr($response, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP command failed: ' . trim($response));
    }
    return $response;
}

function sendEmailViaSmtp(string $to, string $subject, string $htmlBody, string $textBody): bool
{
    $cfg = getSmtpConfig();
    if (!isSmtpConfigured()) {
        return false;
    }

    $transportHost = $cfg['host'];
    if ($cfg['encryption'] === 'ssl') {
        $transportHost = 'ssl://' . $transportHost;
    }

    $socket = @stream_socket_client(
        $transportHost . ':' . $cfg['port'],
        $errno,
        $errstr,
        20,
        STREAM_CLIENT_CONNECT
    );

    if (!$socket) {
        error_log('SMTP connection failed: ' . $errstr . ' (' . $errno . ')');
        return false;
    }

    stream_set_timeout($socket, 20);

    try {
        $greeting = readSmtpResponse($socket);
        if ((int)substr($greeting, 0, 3) !== 220) {
            throw new RuntimeException('SMTP greeting failed: ' . trim($greeting));
        }

        sendSmtpCommand($socket, 'EHLO localhost', [250]);

        if ($cfg['encryption'] === 'tls') {
            sendSmtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Unable to start TLS encryption.');
            }
            sendSmtpCommand($socket, 'EHLO localhost', [250]);
        }

        sendSmtpCommand($socket, 'AUTH LOGIN', [334]);
        sendSmtpCommand($socket, base64_encode($cfg['username']), [334]);
        sendSmtpCommand($socket, base64_encode($cfg['password']), [235]);

        $fromAddress = getMailFromAddress();
        sendSmtpCommand($socket, 'MAIL FROM:<' . $fromAddress . '>', [250]);
        sendSmtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        sendSmtpCommand($socket, 'DATA', [354]);

        $boundary = 'scanfit_' . bin2hex(random_bytes(12));
        $headers = [
            'From: ' . sprintf('"%s" <%s>', str_replace('"', '', getMailFromName()), $fromAddress),
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        $message .= "--{$boundary}--\r\n.";

        sendSmtpCommand($socket, $message, [250]);
        sendSmtpCommand($socket, 'QUIT', [221]);
        fclose($socket);
        return true;
    } catch (Throwable $e) {
        error_log('SMTP send failed: ' . $e->getMessage());
        fclose($socket);
        return false;
    }
}

function sendAppEmail(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
{
    $to = trim($to);
    if ($to === '') {
        return false;
    }

    if ($textBody === null || trim($textBody) === '') {
        $textBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody)));
    }

    if (isSmtpConfigured()) {
        return sendEmailViaSmtp($to, $subject, $htmlBody, $textBody);
    }

    $fromAddress = getMailFromAddress();
    $fromName = getMailFromName();
    $boundary = 'scanfit_' . bin2hex(random_bytes(12));
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . sprintf('"%s" <%s>', addslashes($fromName), $fromAddress),
        'Reply-To: ' . $fromAddress,
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $message = "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $message .= $textBody . "\r\n\r\n";
    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $message .= $htmlBody . "\r\n\r\n";
    $message .= "--{$boundary}--\r\n";

    return @mail($to, $subject, $message, implode("\r\n", $headers));
}

function createEmailVerificationToken(): string
{
    return bin2hex(random_bytes(32));
}

function hashVerificationToken(string $token): string
{
    return hash('sha256', $token);
}

function buildEmailVerificationLink(string $token, string $type = 'customer'): string
{
    $query = ['token' => $token];
    if ($type !== 'customer') {
        $query['type'] = $type;
    }
    return getAppBaseUrl() . '/verify_email.php?' . http_build_query($query);
}

function setCustomerEmailVerificationToken(int $customerId, string $token): bool
{
    global $conn;

    $hashedToken = hashVerificationToken($token);
    $sql = "
        UPDATE customer
        SET email_verification_token = ?,
            email_verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE customer_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'si', $hashedToken, $customerId);
    return mysqli_stmt_execute($stmt);
}

function sendCustomerVerificationEmail(int $customerId, string $email, string $firstName): bool
{
    $token = createEmailVerificationToken();
    if (!setCustomerEmailVerificationToken($customerId, $token)) {
        return false;
    }

    $verificationUrl = buildEmailVerificationLink($token);
    $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $htmlBody = '
        <p>Hi ' . $safeFirstName . ',</p>
        <p>Thanks for creating your ScanFit account.</p>
        <p>Please verify your email address by clicking the link below:</p>
        <p><a href="' . $safeUrl . '">Verify your email</a></p>
        <p>This link expires in 24 hours.</p>
    ';
    $textBody = "Hi {$firstName},\n\n"
        . "Thanks for creating your ScanFit account.\n"
        . "Verify your email address here:\n{$verificationUrl}\n\n"
        . "This link expires in 24 hours.";

    return sendAppEmail($email, 'Verify your ScanFit email address', $htmlBody, $textBody);
}

function findPendingCustomerSignupByEmail(string $email): ?array
{
    global $conn;
    $sql = "SELECT * FROM pending_customer_signup WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function setPendingCustomerSignupVerificationToken(int $pendingSignupId, string $token): bool
{
    global $conn;

    $hashedToken = hashVerificationToken($token);
    $sql = "
        UPDATE pending_customer_signup
        SET verification_token = ?,
            verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE pending_customer_signup_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'si', $hashedToken, $pendingSignupId);
    return mysqli_stmt_execute($stmt);
}

function sendPendingCustomerSignupVerificationEmail(int $pendingSignupId, string $email, string $firstName): bool
{
    $token = createEmailVerificationToken();
    if (!setPendingCustomerSignupVerificationToken($pendingSignupId, $token)) {
        return false;
    }

    $verificationUrl = buildEmailVerificationLink($token, 'manual_signup');
    $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $htmlBody = '
        <p>Hi ' . $safeFirstName . ',</p>
        <p>Finish creating your ScanFit account by verifying your email address.</p>
        <p>Your account will only be created after you click the link below:</p>
        <p><a href="' . $safeUrl . '">Verify your email and create your account</a></p>
        <p>This link expires in 24 hours.</p>
    ';
    $textBody = "Hi {$firstName},\n\n"
        . "Finish creating your ScanFit account by verifying your email address.\n"
        . "Your account will only be created after you open this link:\n{$verificationUrl}\n\n"
        . "This link expires in 24 hours.";

    return sendAppEmail($email, 'Verify your email to finish creating your ScanFit account', $htmlBody, $textBody);
}

function createPendingCustomerSignup(
    string $firstName,
    string $lastName,
    string $email,
    string $phone,
    string $passwordHash,
    ?string &$error = null
): ?int {
    global $conn;

    if (findCustomerByEmail($email)) {
        $error = 'Email already registered';
        return null;
    }

    if (findPendingGoogleSignupByEmail($email)) {
        $error = 'A Google sign up is already pending for this email. Please verify that email first.';
        return null;
    }

    $existingPending = findPendingCustomerSignupByEmail($email);
    if ($existingPending) {
        $pendingId = (int)$existingPending['pending_customer_signup_id'];
        $updateSql = "
            UPDATE pending_customer_signup
            SET first_name = ?, last_name = ?, phone = ?, password_hash = ?
            WHERE pending_customer_signup_id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $updateSql);
        if (!$stmt) {
            $error = 'Unable to start registration right now.';
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'ssssi', $firstName, $lastName, $phone, $passwordHash, $pendingId);
        if (!mysqli_stmt_execute($stmt)) {
            $error = 'Unable to start registration right now.';
            return null;
        }
        return $pendingId;
    }

    $insertSql = "
        INSERT INTO pending_customer_signup (first_name, last_name, email, phone, password_hash, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";
    $stmt = mysqli_prepare($conn, $insertSql);
    if (!$stmt) {
        $error = 'Unable to start registration right now.';
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'sssss', $firstName, $lastName, $email, $phone, $passwordHash);
    if (!mysqli_stmt_execute($stmt)) {
        $error = 'Unable to start registration right now.';
        return null;
    }

    return (int)mysqli_insert_id($conn);
}

function completePendingCustomerSignupByToken(string $token, ?string &$error = null): ?int
{
    global $conn;

    $token = trim($token);
    if ($token === '') {
        $error = 'Missing verification token.';
        return null;
    }

    $hashedToken = hashVerificationToken($token);
    $sql = "
        SELECT *
        FROM pending_customer_signup
        WHERE verification_token = ?
          AND verification_expires_at IS NOT NULL
          AND verification_expires_at >= NOW()
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = 'Unable to verify email right now.';
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $hashedToken);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pending = mysqli_fetch_assoc($res);
    if (!$pending) {
        $error = 'This verification link is invalid or has expired.';
        return null;
    }

    $email = trim((string)($pending['email'] ?? ''));
    if ($email === '') {
        $error = 'This pending sign up record is incomplete.';
        return null;
    }

    if (findCustomerByEmail($email)) {
        $error = 'An account with this email already exists. Please log in instead.';
        return null;
    }

    mysqli_begin_transaction($conn);

    try {
        $insertSql = "
            INSERT INTO customer (first_name, last_name, email, phone, password_hash, email_verified, created_at)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ";
        $insertStmt = mysqli_prepare($conn, $insertSql);
        if (!$insertStmt) {
            throw new RuntimeException('Unable to prepare customer creation.');
        }

        $firstName = (string)($pending['first_name'] ?? '');
        $lastName = (string)($pending['last_name'] ?? '');
        $phone = (string)($pending['phone'] ?? '');
        $passwordHash = (string)($pending['password_hash'] ?? '');

        mysqli_stmt_bind_param($insertStmt, 'sssss', $firstName, $lastName, $email, $phone, $passwordHash);
        if (!mysqli_stmt_execute($insertStmt)) {
            throw new RuntimeException('Unable to create customer.');
        }

        $customerId = (int)mysqli_insert_id($conn);

        $deleteSql = "
            DELETE FROM pending_customer_signup
            WHERE pending_customer_signup_id = ?
            LIMIT 1
        ";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new RuntimeException('Unable to clear pending sign up.');
        }
        $pendingId = (int)$pending['pending_customer_signup_id'];
        mysqli_stmt_bind_param($deleteStmt, 'i', $pendingId);
        if (!mysqli_stmt_execute($deleteStmt)) {
            throw new RuntimeException('Unable to clear pending sign up.');
        }

        mysqli_commit($conn);
        return $customerId;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $error = 'Unable to complete email verification right now.';
        return null;
    }
}

function setPendingGoogleSignupVerificationToken(int $pendingSignupId, string $token): bool
{
    global $conn;

    $hashedToken = hashVerificationToken($token);
    $sql = "
        UPDATE pending_google_signup
        SET verification_token = ?,
            verification_expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
        WHERE pending_google_signup_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'si', $hashedToken, $pendingSignupId);
    return mysqli_stmt_execute($stmt);
}

function sendPendingGoogleSignupVerificationEmail(int $pendingSignupId, string $email, string $firstName): bool
{
    $token = createEmailVerificationToken();
    if (!setPendingGoogleSignupVerificationToken($pendingSignupId, $token)) {
        return false;
    }

    $verificationUrl = buildEmailVerificationLink($token, 'google_signup');
    $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verificationUrl, ENT_QUOTES, 'UTF-8');
    $htmlBody = '
        <p>Hi ' . $safeFirstName . ',</p>
        <p>Finish creating your ScanFit account by verifying your email address.</p>
        <p>Your account will only be created after you click the link below:</p>
        <p><a href="' . $safeUrl . '">Verify your email and create your account</a></p>
        <p>This link expires in 24 hours.</p>
    ';
    $textBody = "Hi {$firstName},\n\n"
        . "Finish creating your ScanFit account by verifying your email address.\n"
        . "Your account will only be created after you open this link:\n{$verificationUrl}\n\n"
        . "This link expires in 24 hours.";

    return sendAppEmail($email, 'Verify your email to finish creating your ScanFit account', $htmlBody, $textBody);
}

function verifyCustomerEmailByToken(string $token, ?string &$error = null): ?int
{
    global $conn;

    $token = trim($token);
    if ($token === '') {
        $error = 'Missing verification token.';
        return null;
    }

    $hashedToken = hashVerificationToken($token);
    $sql = "
        SELECT customer_id
        FROM customer
        WHERE email_verification_token = ?
          AND email_verification_expires_at IS NOT NULL
          AND email_verification_expires_at >= NOW()
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = 'Unable to verify email right now.';
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $hashedToken);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    if (!$row) {
        $error = 'This verification link is invalid or has expired.';
        return null;
    }

    $customerId = (int)$row['customer_id'];
    $updateSql = "
        UPDATE customer
        SET email_verified = 1,
            email_verification_token = NULL,
            email_verification_expires_at = NULL
        WHERE customer_id = ?
        LIMIT 1
    ";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if (!$updateStmt) {
        $error = 'Unable to complete email verification right now.';
        return null;
    }
    mysqli_stmt_bind_param($updateStmt, 'i', $customerId);
    if (!mysqli_stmt_execute($updateStmt)) {
        $error = 'Unable to complete email verification right now.';
        return null;
    }

    return $customerId;
}

function findPendingGoogleSignupByEmail(string $email): ?array
{
    global $conn;
    $sql = "SELECT * FROM pending_google_signup WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function findPendingGoogleSignupByGoogleSub(string $googleSub): ?array
{
    global $conn;
    $sql = "SELECT * FROM pending_google_signup WHERE google_sub = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $googleSub);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function createPendingGoogleSignupFromGoogle(array $profile, ?string &$error = null): ?int
{
    global $conn;

    $googleSub = trim((string)($profile['sub'] ?? ''));
    $email = trim((string)($profile['email'] ?? ''));
    $emailVerified = !empty($profile['email_verified']);

    if ($googleSub === '' || $email === '') {
        $error = 'Google sign up failed because required profile data was missing.';
        return null;
    }
    if (!$emailVerified) {
        $error = 'Google account email must be verified before using Google sign up.';
        return null;
    }

    if (findCustomerByGoogleSub($googleSub) || findCustomerByEmail($email)) {
        $error = 'An account with this email already exists. Please log in instead.';
        return null;
    }

    $firstName = trim((string)($profile['given_name'] ?? ''));
    $lastName = trim((string)($profile['family_name'] ?? ''));
    if ($firstName === '' && $lastName === '') {
        [$firstName, $lastName] = splitDisplayName((string)($profile['name'] ?? ''));
    }
    if ($firstName === '') {
        $firstName = 'Google';
    }
    if ($lastName === '') {
        $lastName = 'User';
    }

    $existingPendingByGoogle = findPendingGoogleSignupByGoogleSub($googleSub);
    $existingPendingByEmail = findPendingGoogleSignupByEmail($email);
    $existingPending = $existingPendingByGoogle ?: $existingPendingByEmail;

    if (
        $existingPendingByGoogle
        && $existingPendingByEmail
        && (int)$existingPendingByGoogle['pending_google_signup_id'] !== (int)$existingPendingByEmail['pending_google_signup_id']
    ) {
        $error = 'A pending sign up already exists for this email. Please use the latest verification email or wait for it to expire.';
        return null;
    }

    $passwordHash = randomOAuthPasswordHash();

    if ($existingPending) {
        $pendingId = (int)$existingPending['pending_google_signup_id'];
        $updateSql = "
            UPDATE pending_google_signup
            SET first_name = ?, last_name = ?, email = ?, google_sub = ?, password_hash = ?
            WHERE pending_google_signup_id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $updateSql);
        if (!$stmt) {
            $error = 'Unable to start Google sign up at this time.';
            return null;
        }
        mysqli_stmt_bind_param($stmt, 'sssssi', $firstName, $lastName, $email, $googleSub, $passwordHash, $pendingId);
        if (!mysqli_stmt_execute($stmt)) {
            $error = 'Unable to start Google sign up at this time.';
            return null;
        }
        return $pendingId;
    }

    $insertSql = "
        INSERT INTO pending_google_signup (first_name, last_name, email, google_sub, password_hash, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ";
    $stmt = mysqli_prepare($conn, $insertSql);
    if (!$stmt) {
        $error = 'Unable to start Google sign up at this time.';
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'sssss', $firstName, $lastName, $email, $googleSub, $passwordHash);
    if (!mysqli_stmt_execute($stmt)) {
        $error = 'Unable to start Google sign up at this time.';
        return null;
    }

    return (int)mysqli_insert_id($conn);
}

function completePendingGoogleSignupByToken(string $token, ?string &$error = null): ?int
{
    global $conn;

    $token = trim($token);
    if ($token === '') {
        $error = 'Missing verification token.';
        return null;
    }

    $hashedToken = hashVerificationToken($token);
    $sql = "
        SELECT *
        FROM pending_google_signup
        WHERE verification_token = ?
          AND verification_expires_at IS NOT NULL
          AND verification_expires_at >= NOW()
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        $error = 'Unable to verify email right now.';
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $hashedToken);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pending = mysqli_fetch_assoc($res);
    if (!$pending) {
        $error = 'This verification link is invalid or has expired.';
        return null;
    }

    $email = trim((string)($pending['email'] ?? ''));
    $googleSub = trim((string)($pending['google_sub'] ?? ''));
    if ($email === '' || $googleSub === '') {
        $error = 'This pending sign up record is incomplete.';
        return null;
    }

    if (findCustomerByEmail($email) || findCustomerByGoogleSub($googleSub)) {
        $error = 'An account with this email already exists. Please log in instead.';
        return null;
    }

    mysqli_begin_transaction($conn);

    try {
        $insertSql = "
            INSERT INTO customer (first_name, last_name, email, phone, password_hash, google_sub, email_verified, created_at)
            VALUES (?, ?, ?, NULL, ?, ?, 1, NOW())
        ";
        $insertStmt = mysqli_prepare($conn, $insertSql);
        if (!$insertStmt) {
            throw new RuntimeException('Unable to prepare customer creation.');
        }

        $firstName = (string)($pending['first_name'] ?? 'Google');
        $lastName = (string)($pending['last_name'] ?? 'User');
        $passwordHash = (string)($pending['password_hash'] ?? '');
        if ($passwordHash === '') {
            $passwordHash = randomOAuthPasswordHash();
        }

        mysqli_stmt_bind_param($insertStmt, 'sssss', $firstName, $lastName, $email, $passwordHash, $googleSub);
        if (!mysqli_stmt_execute($insertStmt)) {
            throw new RuntimeException('Unable to create customer.');
        }

        $customerId = (int)mysqli_insert_id($conn);

        $deleteSql = "
            DELETE FROM pending_google_signup
            WHERE pending_google_signup_id = ?
            LIMIT 1
        ";
        $deleteStmt = mysqli_prepare($conn, $deleteSql);
        if (!$deleteStmt) {
            throw new RuntimeException('Unable to clear pending sign up.');
        }
        $pendingId = (int)$pending['pending_google_signup_id'];
        mysqli_stmt_bind_param($deleteStmt, 'i', $pendingId);
        if (!mysqli_stmt_execute($deleteStmt)) {
            throw new RuntimeException('Unable to clear pending sign up.');
        }

        mysqli_commit($conn);
        return $customerId;
    } catch (Throwable $e) {
        mysqli_rollback($conn);
        $error = 'Unable to complete email verification right now.';
        return null;
    }
}

function isGoogleOAuthConfigured(): bool
{
    $cfg = getGoogleOAuthConfig();
    return $cfg['client_id'] !== '' && $cfg['client_secret'] !== '' && $cfg['redirect_uri'] !== '';
}

function createGoogleOAuthUrl(string $mode = 'login'): ?string
{
    if (!isGoogleOAuthConfigured()) {
        return null;
    }

    $mode = ($mode === 'signup') ? 'signup' : 'login';
    $state = createGoogleOAuthState($mode);
    $cfg = getGoogleOAuthConfig();

    $params = [
        'client_id' => $cfg['client_id'],
        'redirect_uri' => $cfg['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'include_granted_scopes' => 'true',
        'prompt' => 'select_account',
        'state' => $state,
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function createGoogleOAuthState(string $mode): string
{
    if (!isset($_SESSION['google_oauth_state']) || !is_array($_SESSION['google_oauth_state'])) {
        $_SESSION['google_oauth_state'] = [];
    }

    $now = time();
    foreach ($_SESSION['google_oauth_state'] as $key => $payload) {
        $ts = is_array($payload) ? (int)($payload['ts'] ?? 0) : 0;
        if ($ts <= 0 || ($now - $ts) > 900) {
            unset($_SESSION['google_oauth_state'][$key]);
        }
    }

    $payload = [
        'mode' => ($mode === 'signup') ? 'signup' : 'login',
        'nonce' => bin2hex(random_bytes(16)),
        'ts' => $now,
    ];
    $raw = json_encode($payload);
    if (!is_string($raw)) {
        $raw = '{"mode":"login","nonce":"","ts":0}';
    }

    $state = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    $_SESSION['google_oauth_state'][$state] = $payload;
    return $state;
}

function consumeGoogleOAuthState(string $state): ?array
{
    if (!isset($_SESSION['google_oauth_state']) || !is_array($_SESSION['google_oauth_state'])) {
        return null;
    }

    $payload = $_SESSION['google_oauth_state'][$state] ?? null;
    unset($_SESSION['google_oauth_state'][$state]);

    if (!is_array($payload)) {
        return null;
    }

    $ts = (int)($payload['ts'] ?? 0);
    if ($ts <= 0 || (time() - $ts) > 900) {
        return null;
    }

    $payload['mode'] = (($payload['mode'] ?? 'login') === 'signup') ? 'signup' : 'login';
    return $payload;
}

function getCABundlePath(): ?string
{
    $fromEnv = trim((string)(getenv('SCANFIT_CA_BUNDLE') ?: ''));
    if ($fromEnv !== '' && is_file($fromEnv) && is_readable($fromEnv)) {
        return $fromEnv;
    }

    $candidates = [
        'C:\\wamp64\\apps\\phpmyadmin5.2.1\\vendor\\composer\\ca-bundle\\res\\cacert.pem',
        __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem',
    ];

    foreach ($candidates as $candidate) {
        if (is_file($candidate) && is_readable($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function getLocalScannerConfig(): array
{
    return [
        'upload_url' => trim((string)(getenv('SCANFIT_LOCAL_SCANNER_URL') ?: 'http://127.0.0.1:8001/upload_images')),
    ];
}

function localScannerIsConfigured(): bool
{
    $cfg = getLocalScannerConfig();
    return $cfg['upload_url'] !== '';
}

function decodeBase64ImageToTempFile(string $imageData, string $prefix, ?string &$mimeType = null): ?string
{
    $imageData = trim($imageData);
    if ($imageData === '') {
        return null;
    }

    $mimeType = 'image/jpeg';
    $binary = null;
    if (preg_match('/^data:(image\/[a-zA-Z0-9.+-]+);base64,(.*)$/', $imageData, $matches)) {
        $mimeType = strtolower(trim((string)$matches[1]));
        $binary = base64_decode(str_replace(' ', '+', (string)$matches[2]), true);
    } else {
        $binary = base64_decode(str_replace(' ', '+', $imageData), true);
    }

    if (!is_string($binary) || $binary === '') {
        return null;
    }

    $ext = 'jpg';
    if ($mimeType === 'image/png') {
        $ext = 'png';
    } elseif ($mimeType === 'image/webp') {
        $ext = 'webp';
    }

    $tempPath = tempnam(sys_get_temp_dir(), $prefix);
    if ($tempPath === false) {
        return null;
    }

    $finalPath = $tempPath . '.' . $ext;
    if (!@rename($tempPath, $finalPath)) {
        $finalPath = $tempPath;
    }

    if (@file_put_contents($finalPath, $binary) === false) {
        @unlink($finalPath);
        return null;
    }

    return $finalPath;
}

function scanMeasurementsWithLocalService(string $frontImageData, string $sideImageData, float $heightCm, ?string &$error = null): ?array
{
    $cfg = getLocalScannerConfig();
    if ($cfg['upload_url'] === '') {
        $error = 'Local scanner URL is not configured.';
        return null;
    }
    if (!function_exists('curl_init')) {
        $error = 'PHP cURL is required for the local scanner integration.';
        return null;
    }

    $frontMime = null;
    $sideMime = null;
    $frontPath = decodeBase64ImageToTempFile($frontImageData, 'scanfit_front_', $frontMime);
    $sidePath = decodeBase64ImageToTempFile($sideImageData, 'scanfit_side_', $sideMime);
    if ($frontPath === null || $sidePath === null) {
        if ($frontPath !== null) {
            @unlink($frontPath);
        }
        if ($sidePath !== null) {
            @unlink($sidePath);
        }
        $error = 'Could not prepare uploaded images for the local scanner.';
        return null;
    }

    $frontName = 'front.' . pathinfo($frontPath, PATHINFO_EXTENSION);
    $sideName = 'left_side.' . pathinfo($sidePath, PATHINFO_EXTENSION);
    $payload = [
        'height_cm' => (string)round($heightCm, 1),
        'front' => new CURLFile($frontPath, $frontMime ?: 'image/jpeg', $frontName),
        'left_side' => new CURLFile($sidePath, $sideMime ?: 'image/jpeg', $sideName),
    ];

    $ch = curl_init($cfg['upload_url']);
    if ($ch === false) {
        @unlink($frontPath);
        @unlink($sidePath);
        $error = 'Unable to initialize cURL for the local scanner.';
        return null;
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    $responseBody = curl_exec($ch);
    $curlErrNo = curl_errno($ch);
    $curlErr = (string)curl_error($ch);
    $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    @unlink($frontPath);
    @unlink($sidePath);

    if ($responseBody === false) {
        $msg = $curlErr !== '' ? $curlErr : 'Unknown cURL error.';
        $error = 'Local scanner request failed: cURL error ' . $curlErrNo . ': ' . $msg;
        return null;
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $error = 'Local scanner returned a non-JSON response.';
        return null;
    }
    if ($statusCode >= 400) {
        $error = trim((string)($decoded['error'] ?? 'Local scanner rejected the submitted images.'));
        return null;
    }

    $measurements = $decoded['measurements'] ?? null;
    if (!is_array($measurements)) {
        $error = 'Local scanner did not return a measurements object.';
        return null;
    }

    $chest = isset($measurements['chest_circumference']) ? (float)$measurements['chest_circumference'] : 0.0;
    $waist = isset($measurements['waist']) ? (float)$measurements['waist'] : 0.0;
    $hips = isset($measurements['hip']) ? (float)$measurements['hip'] : 0.0;
    if ($chest <= 0 || $waist <= 0 || $hips <= 0) {
        $error = 'Local scanner did not return usable chest, waist, and hips values.';
        return null;
    }

    return [
        'chest' => $chest,
        'waist' => $waist,
        'hips' => $hips,
        'raw' => $decoded,
    ];
}

function getFitXpressConfig(): array
{
    $baseUrl = trim((string)(getenv('SCANFIT_FITXPRESS_BASE_URL') ?: 'https://backend.fitxpress.3dlook.me/api/1.0/'));
    if ($baseUrl !== '' && substr($baseUrl, -1) !== '/') {
        $baseUrl .= '/';
    }

    return [
        'base_url' => $baseUrl,
        'token' => trim((string)(getenv('SCANFIT_FITXPRESS_TOKEN') ?: '')),
    ];
}

function fitXpressIsConfigured(): bool
{
    $cfg = getFitXpressConfig();
    return $cfg['base_url'] !== '' && $cfg['token'] !== '';
}

function fitXpressRequestJson(string $path, string $method = 'GET', ?array $payload = null): ?array
{
    $cfg = getFitXpressConfig();
    if ($cfg['base_url'] === '' || $cfg['token'] === '') {
        return [
            'error' => 'missing_configuration',
            'error_description' => 'FitXpress API token is not configured.',
            '_http_status' => 0,
        ];
    }

    $url = $cfg['base_url'] . ltrim($path, '/');
    $headers = [
        'Authorization: Token ' . $cfg['token'],
        'Accept: application/json',
        'Content-Type: application/json',
    ];
    $body = $payload !== null ? json_encode($payload) : null;
    if ($payload !== null && !is_string($body)) {
        return [
            'error' => 'invalid_payload',
            'error_description' => 'Unable to encode FitXpress request payload.',
            '_http_status' => 0,
        ];
    }

    $method = strtoupper($method);
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['error' => 'transport_error', 'error_description' => 'Unable to initialize cURL.', '_http_status' => 0];
        }
        $caBundle = getCABundlePath();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($caBundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = (string)curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($responseBody === false) {
            $msg = $curlErr !== '' ? $curlErr : 'Unknown cURL error.';
            return [
                'error' => 'transport_error',
                'error_description' => 'cURL error ' . $curlErrNo . ': ' . $msg,
                '_http_status' => $statusCode,
            ];
        }
    } else {
        $contextHeaders = implode("\r\n", $headers);
        $caBundle = getCABundlePath();
        $opts = [
            'http' => [
                'method' => $method,
                'header' => $contextHeaders,
                'timeout' => 30,
                'ignore_errors' => true,
                'content' => $body ?? '',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
        if ($caBundle !== null) {
            $opts['ssl']['cafile'] = $caBundle;
        }
        $context = stream_context_create($opts);
        $responseBody = @file_get_contents($url, false, $context);
        $statusCode = 0;
        $respHeaders = $http_response_header ?? [];
        if (!empty($respHeaders[0]) && preg_match('/\s(\d{3})\s/', (string)$respHeaders[0], $m)) {
            $statusCode = (int)$m[1];
        }
    }

    if (!is_string($responseBody)) {
        return ['error' => 'transport_error', 'error_description' => 'Empty HTTP response body.', '_http_status' => $statusCode];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $snippet = trim(substr($responseBody, 0, 180));
        if ($snippet === '') {
            $snippet = '[empty response]';
        }
        return [
            'error' => 'invalid_response',
            'error_description' => 'Non-JSON response received from FitXpress.',
            '_http_status' => $statusCode,
            '_raw_response' => $snippet,
        ];
    }

    $decoded['_http_status'] = $statusCode;
    return $decoded;
}

function createFitXpressMeasurement(array $payload, ?string &$error = null): ?array
{
    $res = fitXpressRequestJson('measurements/', 'POST', $payload);
    if (!$res || empty($res['id'])) {
        if (is_array($res)) {
            $error = trim((string)($res['detail'] ?? $res['error_description'] ?? $res['error'] ?? ''));
            if ($error === '' && !empty($res['_raw_response'])) {
                $error = (string)$res['_raw_response'];
            }
        }
        if ($error === null || $error === '') {
            $error = 'Failed to create FitXpress measurement.';
        }
        return null;
    }
    return $res;
}

function getFitXpressMeasurement(string $measurementId, ?string &$error = null): ?array
{
    $res = fitXpressRequestJson('measurements/' . rawurlencode($measurementId) . '/', 'GET', null);
    if (!$res || empty($res['id'])) {
        if (is_array($res)) {
            $error = trim((string)($res['detail'] ?? $res['error_description'] ?? $res['error'] ?? ''));
        }
        if ($error === null || $error === '') {
            $error = 'Failed to retrieve FitXpress measurement.';
        }
        return null;
    }
    return $res;
}

function pollFitXpressMeasurement(string $measurementId, int $timeoutSeconds = 25, ?string &$error = null): ?array
{
    $startedAt = time();
    do {
        $measurement = getFitXpressMeasurement($measurementId, $error);
        if (!$measurement) {
            return null;
        }

        $status = strtolower((string)($measurement['status'] ?? ''));
        if ($status === 'successful') {
            return $measurement;
        }
        if ($status === 'failed') {
            $errors = $measurement['errors'] ?? [];
            if (is_array($errors) && !empty($errors[0]['detail'])) {
                $error = (string)$errors[0]['detail'];
            } elseif ($error === null || $error === '') {
                $error = 'FitXpress could not process the submitted images.';
            }
            return null;
        }

        usleep(1200000);
    } while ((time() - $startedAt) < $timeoutSeconds);

    $error = 'FitXpress scan is still processing. Please try again in a moment.';
    return null;
}

function extractFitXpressCircumferences(array $measurement): ?array
{
    $params = $measurement['circumference_params'] ?? null;
    if (!is_array($params)) {
        return null;
    }

    $chest = isset($params['chest']) ? (float)$params['chest'] : 0.0;
    $waist = isset($params['waist']) ? (float)$params['waist'] : 0.0;
    $hips = isset($params['hips']) ? (float)$params['hips'] : 0.0;
    if ($chest <= 0 || $waist <= 0 || $hips <= 0) {
        return null;
    }

    return [
        'chest' => $chest,
        'waist' => $waist,
        'hips' => $hips,
    ];
}

function httpRequestJson(string $url, string $method = 'GET', ?array $formData = null, array $headers = []): ?array
{
    $method = strtoupper($method);
    $body = null;
    if ($formData !== null) {
        $body = http_build_query($formData);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['error' => 'transport_error', 'error_description' => 'Unable to initialize cURL.', '_http_status' => 0];
        }
        $caBundle = getCABundlePath();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        if ($caBundle !== null) {
            curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
        }
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $responseBody = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErr = (string)curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($responseBody === false) {
            $msg = $curlErr !== '' ? $curlErr : 'Unknown cURL error.';
            return [
                'error' => 'transport_error',
                'error_description' => 'cURL error ' . $curlErrNo . ': ' . $msg,
                '_http_status' => $statusCode,
            ];
        }
    } else {
        $contextHeaders = implode("\r\n", $headers);
        $caBundle = getCABundlePath();
        $opts = [
            'http' => [
                'method' => $method,
                'header' => $contextHeaders,
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
        if ($caBundle !== null) {
            $opts['ssl']['cafile'] = $caBundle;
        }
        if ($body !== null) {
            $opts['http']['content'] = $body;
        }
        $context = stream_context_create($opts);
        $responseBody = @file_get_contents($url, false, $context);
        $statusCode = 0;
        $respHeaders = $http_response_header ?? [];
        if (!empty($respHeaders[0]) && preg_match('/\s(\d{3})\s/', (string)$respHeaders[0], $m)) {
            $statusCode = (int)$m[1];
        }
    }

    if (!is_string($responseBody)) {
        return ['error' => 'transport_error', 'error_description' => 'Empty HTTP response body.', '_http_status' => $statusCode];
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $snippet = trim(substr($responseBody, 0, 180));
        if ($snippet === '') {
            $snippet = '[empty response]';
        }
        return [
            'error' => 'invalid_response',
            'error_description' => 'Non-JSON response received from provider.',
            '_http_status' => $statusCode,
            '_raw_response' => $snippet,
        ];
    }

    $decoded['_http_status'] = $statusCode;
    return $decoded;
}

function exchangeGoogleAuthCode(string $code, ?string &$error = null): ?array
{
    $cfg = getGoogleOAuthConfig();
    if ($cfg['client_id'] === '' || $cfg['client_secret'] === '' || $cfg['redirect_uri'] === '') {
        $error = 'Missing Google OAuth configuration.';
        return null;
    }

    $payload = [
        'code' => $code,
        'client_id' => $cfg['client_id'],
        'client_secret' => $cfg['client_secret'],
        'redirect_uri' => $cfg['redirect_uri'],
        'grant_type' => 'authorization_code',
    ];

    $res = httpRequestJson('https://oauth2.googleapis.com/token', 'POST', $payload);
    if (!$res || empty($res['access_token'])) {
        if (is_array($res)) {
            $reason = trim((string)($res['error_description'] ?? $res['error'] ?? ''));
            $status = (int)($res['_http_status'] ?? 0);
            $raw = trim((string)($res['_raw_response'] ?? ''));
            if ($status > 0) {
                $reason .= ($reason !== '' ? ' ' : '') . '(HTTP ' . $status . ')';
            }
            if ($raw !== '') {
                $reason .= ($reason !== '' ? ' ' : '') . 'Response: ' . $raw;
            }
            if ($reason !== '') {
                $error = $reason;
            }
        }
        if ($error === null || $error === '') {
            $error = 'Token exchange failed.';
        }
        return null;
    }




    
    return $res;
}

function fetchGoogleUserProfile(string $accessToken, ?string &$error = null): ?array
{
    if ($accessToken === '') {
        $error = 'Missing Google access token.';
        return null;
    }

    $headers = ['Authorization: Bearer ' . $accessToken];
    $profile = httpRequestJson('https://openidconnect.googleapis.com/v1/userinfo', 'GET', null, $headers);
    if (!$profile || empty($profile['sub']) || empty($profile['email'])) {
        if (is_array($profile)) {
            $reason = trim((string)($profile['error_description'] ?? $profile['error'] ?? ''));
            $status = (int)($profile['_http_status'] ?? 0);
            $raw = trim((string)($profile['_raw_response'] ?? ''));
            if ($status > 0) {
                $reason .= ($reason !== '' ? ' ' : '') . '(HTTP ' . $status . ')';
            }
            if ($raw !== '') {
                $reason .= ($reason !== '' ? ' ' : '') . 'Response: ' . $raw;
            }
            if ($reason !== '') {
                $error = $reason;
            }
        }
        if ($error === null || $error === '') {
            $error = 'Profile fetch failed.';
        }
        return null;
    }

    return $profile;
}

function splitDisplayName(string $displayName): array
{
    $displayName = trim($displayName);
    if ($displayName === '') {
        return ['', ''];
    }

    $parts = preg_split('/\s+/', $displayName);
    if (!is_array($parts) || count($parts) === 0) {
        return [$displayName, ''];
    }
    if (count($parts) === 1) {
        return [$parts[0], ''];
    }

    $first = array_shift($parts);
    $last = implode(' ', $parts);
    return [$first ?: '', $last];
}

function randomOAuthPasswordHash(): string
{
    return password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
}

function findCustomerByGoogleSub(string $googleSub): ?array
{
    global $conn;
    $sql = "SELECT * FROM customer WHERE google_sub = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $googleSub);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function findCustomerByEmail(string $email): ?array
{
    global $conn;
    $sql = "SELECT * FROM customer WHERE email = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function findOrCreateCustomerFromGoogle(array $profile, ?string &$error = null): ?int
{
    global $conn;

    $googleSub = trim((string)($profile['sub'] ?? ''));
    $email = trim((string)($profile['email'] ?? ''));
    $emailVerified = !empty($profile['email_verified']);

    if ($googleSub === '' || $email === '') {
        $error = 'Google login failed because required profile data was missing.';
        return null;
    }
    if (!$emailVerified) {
        $error = 'Google account email must be verified before using Google sign in.';
        return null;
    }

    $firstName = trim((string)($profile['given_name'] ?? ''));
    $lastName = trim((string)($profile['family_name'] ?? ''));
    if ($firstName === '' && $lastName === '') {
        [$firstName, $lastName] = splitDisplayName((string)($profile['name'] ?? ''));
    }
    if ($firstName === '') {
        $firstName = 'Google';
    }
    if ($lastName === '') {
        $lastName = 'User';
    }

    $customer = findCustomerByGoogleSub($googleSub);
    if ($customer) {
        if ((int)($customer['account_blocked'] ?? 0) === 1) {
            $error = 'Your account has been blocked by an administrator.';
            return null;
        }

        $customerId = (int)$customer['customer_id'];
        $updateSql = "
            UPDATE customer
            SET email = ?, first_name = ?, last_name = ?, failed_login_attempts = 0, account_locked = 0, email_verified = 1
            WHERE customer_id = ?
            LIMIT 1
        ";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, 'sssi', $email, $firstName, $lastName, $customerId);
            mysqli_stmt_execute($updateStmt);
        }
        return $customerId;
    }

    $byEmail = findCustomerByEmail($email);
    if ($byEmail) {
        if ((int)($byEmail['account_blocked'] ?? 0) === 1) {
            $error = 'Your account has been blocked by an administrator.';
            return null;
        }

        $existingGoogleSub = trim((string)($byEmail['google_sub'] ?? ''));
        if ($existingGoogleSub !== '' && $existingGoogleSub !== $googleSub) {
            $error = 'This email is linked to a different Google account.';
            return null;
        }

        $customerId = (int)$byEmail['customer_id'];
        $linkSql = "
            UPDATE customer
            SET google_sub = ?, first_name = ?, last_name = ?, failed_login_attempts = 0, account_locked = 0, email_verified = 1
            WHERE customer_id = ?
            LIMIT 1
        ";
        $linkStmt = mysqli_prepare($conn, $linkSql);
        if ($linkStmt) {
            mysqli_stmt_bind_param($linkStmt, 'sssi', $googleSub, $firstName, $lastName, $customerId);
            mysqli_stmt_execute($linkStmt);
        }
        return $customerId;
    }

    $passwordHash = randomOAuthPasswordHash();
    $insertSql = "
        INSERT INTO customer (first_name, last_name, email, phone, password_hash, google_sub, email_verified, created_at)
        VALUES (?, ?, ?, NULL, ?, ?, 1, NOW())
    ";
    $insertStmt = mysqli_prepare($conn, $insertSql);
    if (!$insertStmt) {
        $error = 'Unable to create account at this time.';
        return null;
    }
    mysqli_stmt_bind_param($insertStmt, 'sssss', $firstName, $lastName, $email, $passwordHash, $googleSub);
    if (!mysqli_stmt_execute($insertStmt)) {
        $error = 'Unable to create account at this time.';
        return null;
    }

    return (int)mysqli_insert_id($conn);
}

function findCustomerForGoogleLogin(array $profile, ?string &$error = null): ?int
{
    global $conn;

    $googleSub = trim((string)($profile['sub'] ?? ''));
    $email = trim((string)($profile['email'] ?? ''));
    $emailVerified = !empty($profile['email_verified']);

    if ($googleSub === '' || $email === '') {
        $error = 'Google login failed because required profile data was missing.';
        return null;
    }
    if (!$emailVerified) {
        $error = 'Google account email must be verified before using Google sign in.';
        return null;
    }

    $customer = findCustomerByGoogleSub($googleSub);
    if (!$customer) {
        $byEmail = findCustomerByEmail($email);
        if ($byEmail) {
            if ((int)($byEmail['account_blocked'] ?? 0) === 1) {
                $error = 'Your account has been blocked by an administrator.';
                return null;
            }
            if ((int)($byEmail['account_locked'] ?? 0) === 1) {
                $error = 'Your account is locked after too many login attempts. Please contact an admin.';
                return null;
            }
            if ((int)($byEmail['email_verified'] ?? 0) !== 1) {
                $error = 'Please verify your email address before logging in.';
                return null;
            }

            $existingGoogleSub = trim((string)($byEmail['google_sub'] ?? ''));
            if ($existingGoogleSub !== '' && $existingGoogleSub !== $googleSub) {
                $error = 'This email is linked to a different Google account.';
                return null;
            }

            $customer = $byEmail;
        }
    }

    if (!$customer) {
        $pending = findPendingGoogleSignupByGoogleSub($googleSub) ?: findPendingGoogleSignupByEmail($email);
        if ($pending) {
            $error = 'Please verify the email we sent before your account can be created.';
            return null;
        }

        $error = 'No ScanFit account is linked to this Google account. Please sign up first.';
        return null;
    }

    if ((int)($customer['account_blocked'] ?? 0) === 1) {
        $error = 'Your account has been blocked by an administrator.';
        return null;
    }
    if ((int)($customer['account_locked'] ?? 0) === 1) {
        $error = 'Your account is locked after too many login attempts. Please contact an admin.';
        return null;
    }
    if ((int)($customer['email_verified'] ?? 0) !== 1) {
        $error = 'Please verify your email address before logging in.';
        return null;
    }

    $firstName = trim((string)($profile['given_name'] ?? ''));
    $lastName = trim((string)($profile['family_name'] ?? ''));
    if ($firstName === '' && $lastName === '') {
        [$firstName, $lastName] = splitDisplayName((string)($profile['name'] ?? ''));
    }
    if ($firstName === '') {
        $firstName = (string)($customer['first_name'] ?? 'Google');
    }
    if ($lastName === '') {
        $lastName = (string)($customer['last_name'] ?? 'User');
    }

    $customerId = (int)$customer['customer_id'];
    $updateSql = "
        UPDATE customer
        SET email = ?, first_name = ?, last_name = ?, google_sub = ?, failed_login_attempts = 0, account_locked = 0
        WHERE customer_id = ?
        LIMIT 1
    ";
    $updateStmt = mysqli_prepare($conn, $updateSql);
    if ($updateStmt) {
        mysqli_stmt_bind_param($updateStmt, 'ssssi', $email, $firstName, $lastName, $googleSub, $customerId);
        mysqli_stmt_execute($updateStmt);
    }

    return $customerId;
}


// ------------- ADMIN AUTH HELPERS -------------


function isAdminLoggedIn(): bool
{
    return !empty($_SESSION['admin_id']);
}


function requireAdminLogin(): void
{
    if (!isAdminLoggedIn()) {
        header('Location: admin_login.php');
        exit();
    }

    $admin = getCurrentAdmin();
    if (!$admin) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_role']);
        header('Location: admin_login.php');
        exit();
    }

    $_SESSION['admin_name'] = $admin['username'] ?? '';
    $_SESSION['admin_role'] = $admin['role'] ?? '';
}

function requireAdminRole(array $allowedRoles): void
{
    requireAdminLogin();
    $role = $_SESSION['admin_role'] ?? '';
    if (!in_array($role, $allowedRoles, true)) {
        http_response_code(403);
        exit('Forbidden');
    }
}


function getCurrentAdmin(): ?array
{
    if (!isAdminLoggedIn()) {
        return null;
    }

    global $conn;
    $adminId = (int)$_SESSION['admin_id'];

    $sql  = "SELECT * FROM admin WHERE admin_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $adminId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($res) ?: null;
}


// ------------- ADMIN MFA HELPERS -------------


function enableAdminMFA(int $adminId, string $secret): bool
{
    global $conn;
    $sql  = "UPDATE admin SET mfa_secret = ?, mfa_enabled = 0 WHERE admin_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'si', $secret, $adminId);
    return mysqli_stmt_execute($stmt);
}


function disableAdminMFA(int $adminId): bool
{
    global $conn;
    $sql  = "UPDATE admin SET mfa_secret = NULL, mfa_enabled = 0 WHERE admin_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, 'i', $adminId);
    return mysqli_stmt_execute($stmt);
}

// Get admin by ID (used by MFA verify page)
function getAdminById(int $adminId): ?array
{
    global $conn;

    $sql  = "SELECT * FROM admin WHERE admin_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $adminId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($res) ?: null;
}

// Verify a TOTP code using a given Base32 secret (±1 time step)
function verifyTOTPSecret(string $secret, string $code): bool
{
    $code = trim($code);
    if ($code === '' || !ctype_digit($code) || strlen($code) !== 6) {
        return false;
    }

    $windowSize     = 30;
    $currentCounter = floor(time() / $windowSize);

    for ($i = -1; $i <= 1; $i++) {
        $counterTime = ($currentCounter + $i) * $windowSize;
        $expected    = totp($secret, $windowSize, $counterTime);
        if (hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}

// Verify TOTP for an admin row (expects ['mfa_secret' => '...'])
function verifyAdminTOTP(array $adminRow, string $code): bool
{
    if (empty($adminRow['mfa_secret'])) {
        return false;
    }
    return verifyTOTPSecret($adminRow['mfa_secret'], $code);
}





// ------------- TOTP / MFA (SINGLE, CONSISTENT IMPLEMENTATION) -------------


// Assumes columns: mfasecret (VARCHAR) and mfaenabled (TINYINT) in `customer` table


function base32Decode(string $base32): string
{
    $base32   = strtoupper($base32);
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits     = '';

    $len = strlen($base32);
    for ($i = 0; $i < $len; $i++) {
        $val = strpos($alphabet, $base32[$i]);
        if ($val === false) {
            continue;
        }
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }

    $bytes   = '';
    $bitsLen = strlen($bits);
    for ($i = 0; $i + 8 <= $bitsLen; $i += 8) {
        $bytes .= chr(bindec(substr($bits, $i, 8)));
    }

    return $bytes;
}




// Generate TOTP code (6 digits, 30s window, optional custom time)
function totp(string $secret, int $window = 30, ?int $forTime = null): string
{
    $t           = $forTime ?? time();
    $timeCounter = floor($t / $window);

    // 8 byte big endian counter (as per RFC 6238)
    $binaryTime = pack('N*', 0) . pack('N*', $timeCounter);

    // HMAC SHA1 of counter using Base32 decoded secret
    $hmac   = hash_hmac('sha1', $binaryTime, base32Decode($secret), true);
    $offset = ord($hmac[19]) & 0x0F;

    // Dynamic truncation to 31 bit integer
    $value =
        ((ord($hmac[$offset])     & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8)  |
        ( ord($hmac[$offset + 3]) & 0xFF);

    // Modulo 1,000,000 and zero pad to 6 digits
    $code = $value % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}


// Verify a TOTP code for given user, allowing ±1 time step
function verifyTOTP(int $userId, string $code): bool
{
    global $conn;

    $sql  = "SELECT mfasecret FROM customer WHERE customer_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    if (empty($row['mfasecret'])) {
        return false;
    }

    $secret = $row['mfasecret'];
    $code   = trim($code);

    $windowSize     = 30;
    $currentCounter = floor(time() / $windowSize);

    // Check previous, current, and next window
    for ($i = -1; $i <= 1; $i++) {
        $counterTime = ($currentCounter + $i) * $windowSize;
        $expected    = totp($secret, $windowSize, $counterTime);
        if (hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}

function verifyAndConsumeMFABackupCode(int $userId, string $code): bool
{
    global $conn;

    if ($userId <= 0) {
        return false;
    }

    $normalized = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($code)));
    if ($normalized === '') {
        return false;
    }

    mysqli_begin_transaction($conn);

    try {
        $selectSql = "
            SELECT mfabackupcodes
            FROM customer
            WHERE customer_id = ?
            LIMIT 1
            FOR UPDATE
        ";
        $selectStmt = mysqli_prepare($conn, $selectSql);
        if (!$selectStmt) {
            throw new Exception('Failed to load backup codes.');
        }
        mysqli_stmt_bind_param($selectStmt, 'i', $userId);
        mysqli_stmt_execute($selectStmt);
        $selectRes = mysqli_stmt_get_result($selectStmt);
        $row = mysqli_fetch_assoc($selectRes);

        if (empty($row['mfabackupcodes'])) {
            mysqli_rollback($conn);
            return false;
        }

        $storedCodes = json_decode($row['mfabackupcodes'], true);
        if (!is_array($storedCodes) || empty($storedCodes)) {
            mysqli_rollback($conn);
            return false;
        }

        $matchedIndex = -1;
        foreach ($storedCodes as $i => $storedCode) {
            if (!is_string($storedCode) || $storedCode === '') {
                continue;
            }

            // Supports both hashed values and legacy plaintext entries.
            $isMatch = password_verify($normalized, $storedCode)
                || hash_equals(strtoupper($storedCode), $normalized);

            if ($isMatch) {
                $matchedIndex = $i;
                break;
            }
        }

        if ($matchedIndex < 0) {
            mysqli_rollback($conn);
            return false;
        }

        unset($storedCodes[$matchedIndex]);
        $storedCodes = array_values($storedCodes);

        // Re-hash any legacy plaintext entries still stored.
        foreach ($storedCodes as $i => $storedCode) {
            if (!is_string($storedCode) || $storedCode === '') {
                unset($storedCodes[$i]);
                continue;
            }
            if (!preg_match('/^\$2[aby]\$/', $storedCode)) {
                $storedCodes[$i] = password_hash(strtoupper($storedCode), PASSWORD_DEFAULT);
            }
        }
        $storedCodes = array_values($storedCodes);

        $updatedJson = json_encode($storedCodes);
        if ($updatedJson === false) {
            throw new Exception('Failed to encode backup codes.');
        }

        $updateSql = "
            UPDATE customer
            SET mfabackupcodes = ?
            WHERE customer_id = ?
            LIMIT 1
        ";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        if (!$updateStmt) {
            throw new Exception('Failed to update backup codes.');
        }
        mysqli_stmt_bind_param($updateStmt, 'si', $updatedJson, $userId);
        if (!mysqli_stmt_execute($updateStmt)) {
            throw new Exception('Failed to save backup code usage.');
        }

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}


// Generate random Base32 secret (16 chars)
function generateTOTPSecret(int $length = 16): string
{
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}


// QR Code URL for Google Authenticator (otpauth URI)
function getQRCodeUrl(string $account, string $secret, string $issuer = 'ScanFit'): string
{
    return 'otpauth://totp/' . urlencode($issuer . ':' . $account) .
           '?secret=' . $secret .
           '&issuer=' . urlencode($issuer);
}


function generateQRCodeImageDataUri(string $data): string
{
    $pythonPath = __DIR__ . DIRECTORY_SEPARATOR . '.venv311' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'generate_qr_png.py';

    if (!is_file($pythonPath) || !is_file($scriptPath)) {
        return '';
    }

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open([$pythonPath, $scriptPath], $descriptors, $pipes, __DIR__);
    if (!is_resource($process)) {
        return '';
    }

    fwrite($pipes[0], $data);
    fclose($pipes[0]);

    $pngData = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0 || $pngData === false || $pngData === '') {
        return '';
    }

    return 'data:image/png;base64,' . base64_encode($pngData);
}


function buildMFASetupData(string $account, string $secret, string $issuer = 'ScanFit'): array
{
    $qrUrl = getQRCodeUrl($account, $secret, $issuer);

    return [
        'secret' => $secret,
        'qrurl' => $qrUrl,
        'qrimage' => generateQRCodeImageDataUri($qrUrl),
    ];
}


// Generate secret + otpauth URL and store in DB (mfa disabled until verified)
function generateMFASecret(string $userEmail): array
{
    global $conn;

    $secret = generateTOTPSecret();

    $sql  = "UPDATE customer SET mfasecret = ?, mfaenabled = 0 WHERE email = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'ss', $secret, $userEmail);
        mysqli_stmt_execute($stmt);
    }

    return buildMFASetupData($userEmail, $secret);
}


// Check if MFA enabled for user
function requiresMFA(int $userId): bool
{
    global $conn;

    $sql  = "SELECT mfaenabled FROM customer WHERE customer_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);

    return !empty($row) && (int)$row['mfaenabled'] === 1;
}


// ------------- PRODUCT HELPERS -------------


/**
 * Fetch all active products for a display gender label (Men/Women/Unisex).
 */
function getProductsByGender(string $genderDisplay)
{
    global $conn;

    $genderMap = [
        'Men'    => 'Male',
        'Women'  => 'Female',
        'Unisex' => 'Unisex',
    ];

    $genderName = $genderMap[$genderDisplay] ?? $genderDisplay;

    $sql = "
        SELECT DISTINCT p.*
        FROM product p
        INNER JOIN productgender pg ON p.product_id = pg.product_id
        INNER JOIN gender g        ON pg.gender_id = g.gender_id
        WHERE g.name = ?
          AND p.status = 'ACTIVE'
        ORDER BY p.created_at DESC
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('getProductsByGender prepare error: ' . mysqli_error($conn));
        return mysqli_query($conn, "SELECT * FROM product WHERE 1=0");
    }

    mysqli_stmt_bind_param($stmt, 's', $genderName);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


/**
 * Fetch all variants for a given product.
 */
function getProductVariants(int $productId)
{
    global $conn;

    $sql = "
        SELECT
            pv.variant_id,
            pv.sku AS variant_sku,
            pv.stock_quantity,
            pv.price_adjustment,
            s.name AS size_name,
            c.name AS colour_name
        FROM productvariant pv
        LEFT JOIN size   s ON pv.size_id   = s.size_id
        LEFT JOIN colour c ON pv.colour_id = c.colour_id
        WHERE pv.product_id = ?
        ORDER BY s.sort_order, c.name
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return mysqli_query($conn, "SELECT * FROM productvariant WHERE 1=0");
    }

    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


/**
 * Get a single active product by ID.
 */
function getProductById(int $productId): ?array
{
    global $conn;

    $sql  = "SELECT * FROM product WHERE product_id = ? AND status = 'ACTIVE' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    return mysqli_fetch_assoc($res) ?: null;
}


// ------------- CUSTOMER & CART HELPERS -------------


function getCustomerInfo(int $customerId): ?array
{
    global $conn;

    $sql  = "SELECT * FROM customer WHERE customer_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}


/**
 * Get or create ACTIVE cart for customer.
 */
function getOrCreateActiveCartId(int $customerId): ?int
{
    global $conn;

    $sql  = "SELECT cart_id FROM cart WHERE customer_id = ? AND status = 'ACTIVE' LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        return (int)$row['cart_id'];
    }

    $insert = "
        INSERT INTO cart (customer_id, status, created_at, updated_at)
        VALUES (?, 'ACTIVE', NOW(), NOW())
    ";
    $stmt2 = mysqli_prepare($conn, $insert);
    if (!$stmt2) {
        return null;
    }
    mysqli_stmt_bind_param($stmt2, 'i', $customerId);
    if (!mysqli_stmt_execute($stmt2)) {
        return null;
    }

    return (int)mysqli_insert_id($conn);
}


/**
 * Add product variant to cart (or increase quantity).
 */
function addToCart(int $customerId, int $productId, int $variantId, int $quantity): bool
{
    global $conn;

    if ($customerId <= 0 || $productId <= 0 || $variantId <= 0 || $quantity <= 0) {
        return false;
    }

    $checkSql = "
        SELECT stock_quantity
        FROM productvariant
        WHERE variant_id = ? AND product_id = ?
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $checkSql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $variantId, $productId);
    mysqli_stmt_execute($stmt);
    $res     = mysqli_stmt_get_result($stmt);
    $variant = mysqli_fetch_assoc($res);
    if (!$variant) {
        return false;
    }
    $stockQty = (int)$variant['stock_quantity'];
    if ($stockQty < $quantity) {
        return false;
    }

    $cartId = getOrCreateActiveCartId($customerId);
    if (!$cartId) {
        return false;
    }

    $existingSql = "
        SELECT cart_item_id, quantity
        FROM cartitem
        WHERE cart_id = ? AND variant_id = ?
        LIMIT 1
    ";
    $stmt2 = mysqli_prepare($conn, $existingSql);
    if (!$stmt2) {
        return false;
    }
    mysqli_stmt_bind_param($stmt2, 'ii', $cartId, $variantId);
    mysqli_stmt_execute($stmt2);
    $existingRes = mysqli_stmt_get_result($stmt2);

    if ($row = mysqli_fetch_assoc($existingRes)) {
        $newQty    = (int)$row['quantity'] + $quantity;
        if ($newQty > $stockQty) {
            return false;
        }
        $updateSql = "UPDATE cartitem SET quantity = ? WHERE cart_item_id = ?";
        $stmt3     = mysqli_prepare($conn, $updateSql);
        if (!$stmt3) {
            return false;
        }
        mysqli_stmt_bind_param($stmt3, 'ii', $newQty, $row['cart_item_id']);
        return mysqli_stmt_execute($stmt3);
    }

    $insertSql = "
        INSERT INTO cartitem (cart_id, variant_id, quantity, added_at)
        VALUES (?, ?, ?, NOW())
    ";
    $stmt4 = mysqli_prepare($conn, $insertSql);
    if (!$stmt4) {
        return false;
    }
    mysqli_stmt_bind_param($stmt4, 'iii', $cartId, $variantId, $quantity);
    return mysqli_stmt_execute($stmt4);
}


/**
 * Get all items in customer's active cart.
 */
function getCartItems(int $customerId)
{
    global $conn;

    $sql = "
        SELECT
            ci.cart_item_id,
            ci.quantity,
            ci.variant_id,
            ca.cart_id,
            pv.stock_quantity,
            pv.price_adjustment,
            p.name  AS product_name,
            p.sku   AS product_sku,
            p.base_price,
            s.name  AS size_name,
            c.name  AS colour_name
        FROM cart ca
        INNER JOIN cartitem      ci ON ca.cart_id = ci.cart_id
        INNER JOIN productvariant pv ON ci.variant_id = pv.variant_id
        INNER JOIN product        p  ON pv.product_id = p.product_id
        LEFT JOIN size   s          ON pv.size_id   = s.size_id
        LEFT JOIN colour c          ON pv.colour_id = c.colour_id
        WHERE ca.customer_id = ?
          AND ca.status = 'ACTIVE'
        ORDER BY ci.added_at DESC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return mysqli_query($conn, "SELECT * FROM cartitem WHERE 1=0");
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


function getCartTotal(int $customerId): float
{
    global $conn;

    $sql = "
        SELECT
            IFNULL(SUM( (p.base_price + IFNULL(pv.price_adjustment,0)) * ci.quantity ), 0) AS total
        FROM cart ca
        INNER JOIN cartitem      ci ON ca.cart_id = ci.cart_id
        INNER JOIN productvariant pv ON ci.variant_id = pv.variant_id
        INNER JOIN product        p  ON pv.product_id = p.product_id
        WHERE ca.customer_id = ?
          AND ca.status = 'ACTIVE'
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0.0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    return isset($row['total']) ? (float)$row['total'] : 0.0;
}


function removeFromCart(int $cartItemId, int $customerId): bool
{
    global $conn;

    $sql = "
        DELETE ci FROM cartitem ci
        INNER JOIN cart ca ON ci.cart_id = ca.cart_id
        WHERE ci.cart_item_id = ? AND ca.customer_id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'ii', $cartItemId, $customerId);
    return mysqli_stmt_execute($stmt);
}


function updateCartItemQuantity(int $cartItemId, int $customerId, int $quantity): bool
{
    if ($quantity <= 0) {
        return removeFromCart($cartItemId, $customerId);
    }

    global $conn;

    $stockSql = "
        SELECT pv.stock_quantity
        FROM cartitem ci
        INNER JOIN cart ca ON ci.cart_id = ca.cart_id
        INNER JOIN productvariant pv ON ci.variant_id = pv.variant_id
        WHERE ci.cart_item_id = ? AND ca.customer_id = ?
        LIMIT 1
    ";
    $stmt0 = mysqli_prepare($conn, $stockSql);
    if (!$stmt0) {
        return false;
    }
    mysqli_stmt_bind_param($stmt0, 'ii', $cartItemId, $customerId);
    mysqli_stmt_execute($stmt0);
    $stockRes = mysqli_stmt_get_result($stmt0);
    $stockRow = mysqli_fetch_assoc($stockRes);
    if (!$stockRow || $quantity > (int)$stockRow['stock_quantity']) {
        return false;
    }

    $updateSql = "
        UPDATE cartitem ci
        INNER JOIN cart ca ON ci.cart_id = ca.cart_id
        SET ci.quantity = ?
        WHERE ci.cart_item_id = ? AND ca.customer_id = ?
    ";
    $stmt = mysqli_prepare($conn, $updateSql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'iii', $quantity, $cartItemId, $customerId);
    return mysqli_stmt_execute($stmt);
}


function getCartItemCount(int $customerId): int
{
    global $conn;

    $sql = "
        SELECT IFNULL(SUM(ci.quantity), 0) AS total_items
        FROM cart ca
        INNER JOIN cartitem ci ON ca.cart_id = ci.cart_id
        WHERE ca.customer_id = ? AND ca.status = 'ACTIVE'
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return 0;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    return isset($row['total_items']) ? (int)$row['total_items'] : 0;
}


// ------------- SEARCH & ORDERS -------------


function searchProducts(string $query)
{
    global $conn;

    $term = '%' . $query . '%';
    $sql = "
        SELECT DISTINCT p.*
        FROM product p
        WHERE p.status = 'ACTIVE'
          AND (p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)
        ORDER BY p.name
        LIMIT 50
    ";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return mysqli_query($conn, "SELECT * FROM product WHERE 1=0");
    }
    mysqli_stmt_bind_param($stmt, 'sss', $term, $term, $term);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


function getCustomerOrders(int $customerId)
{
    global $conn;

    $sql = "
        SELECT o.*, p.method_name AS payment_method
        FROM `order` o
        LEFT JOIN payment p ON o.order_id = p.order_id
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return mysqli_query($conn, "SELECT * FROM `order` WHERE 1=0");
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


function cancelOrder(int $orderId, int $customerId): bool
{
    global $conn;

    if ($orderId <= 0 || $customerId <= 0) {
        return false;
    }

    mysqli_begin_transaction($conn);
    try {
        $sql = "
            SELECT order_id, status
            FROM `order`
            WHERE order_id = ? AND customer_id = ?
            LIMIT 1
        ";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception('Failed to prepare order lookup.');
        }
        mysqli_stmt_bind_param($stmt, 'ii', $orderId, $customerId);
        mysqli_stmt_execute($stmt);
        $res   = mysqli_stmt_get_result($stmt);
        $order = mysqli_fetch_assoc($res);

        if (!$order || in_array($order['status'], ['DELIVERED', 'CANCELLED'], true)) {
            throw new Exception('Order not cancellable.');
        }

        $update = "
            UPDATE `order`
            SET status = 'CANCELLED', updated_at = NOW()
            WHERE order_id = ? AND customer_id = ?
            LIMIT 1
        ";
        $stmt2 = mysqli_prepare($conn, $update);
        if (!$stmt2) {
            throw new Exception('Failed to prepare order update.');
        }
        mysqli_stmt_bind_param($stmt2, 'ii', $orderId, $customerId);
        if (!mysqli_stmt_execute($stmt2) || mysqli_stmt_affected_rows($stmt2) !== 1) {
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
                throw new Exception('Failed to prepare restock.');
            }
            mysqli_stmt_bind_param($restockStmt, 'ii', $qty, $variantId);
            if (!mysqli_stmt_execute($restockStmt) || mysqli_stmt_affected_rows($restockStmt) !== 1) {
                throw new Exception('Failed to restock variant.');
            }

            $auditSql = "
                INSERT INTO stockmovement (variant_id, movement_type, quantity, reference_id, notes)
                VALUES (?, 'IN', ?, ?, ?)
            ";
            $auditNote = 'Order #' . $orderId . ' cancelled by customer';
            $auditStmt = mysqli_prepare($conn, $auditSql);
            if (!$auditStmt) {
                throw new Exception('Failed to prepare stock audit.');
            }
            mysqli_stmt_bind_param($auditStmt, 'iiis', $variantId, $qty, $orderId, $auditNote);
            if (!mysqli_stmt_execute($auditStmt)) {
                throw new Exception('Failed to save stock audit.');
            }
        }

        mysqli_commit($conn);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        return false;
    }
}


function getOrderItems(int $orderId)
{
    global $conn;

    $sql = "
        SELECT
            oi.*,
            p.name AS product_name,
            p.sku  AS product_sku,
            s.name AS size_name,
            c.name AS colour_name
        FROM orderitem oi
        INNER JOIN productvariant pv ON oi.variant_id = pv.variant_id
        INNER JOIN product        p  ON pv.product_id = p.product_id
        LEFT JOIN size   s           ON pv.size_id   = s.size_id
        LEFT JOIN colour c           ON pv.colour_id = c.colour_id
        WHERE oi.order_id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return mysqli_query($conn, "SELECT * FROM orderitem WHERE 1=0");
    }
    mysqli_stmt_bind_param($stmt, 'i', $orderId);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}


// ------------- SIZES, BODY TYPES & MEASUREMENTS -------------


function getAllSizes()
{
    global $conn;
    return mysqli_query($conn, "SELECT * FROM size ORDER BY sort_order");
}


function getAllBodyTypes()
{
    global $conn;
    return mysqli_query($conn, "SELECT * FROM bodytype ORDER BY bodytype_id");
}


function saveBodyMeasurement(
    int $customerId,
    float $height,
    float $weight,
    ?int $bodytypeId = null,
    ?float $chest = null,
    ?float $waist = null,
    ?float $hips = null
): bool {
    global $conn;

    $sql = "
        INSERT INTO bodymeasurement
        (customer_id, height_cm, weight_kg, bodytype_id, chest_cm, waist_cm, hips_cm, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param(
        $stmt,
        'iddiddd',
        $customerId,
        $height,
        $weight,
        $bodytypeId,
        $chest,
        $waist,
        $hips
    );
    return mysqli_stmt_execute($stmt);
}


function getLatestBodyMeasurement(int $customerId): ?array
{
    global $conn;

    $sql = "
        SELECT *
        FROM bodymeasurement
        WHERE customer_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function deleteBodyMeasurements(int $customerId): bool
{
    global $conn;

    $sql = "
        DELETE FROM bodymeasurement
        WHERE customer_id = ?
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return false;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    return mysqli_stmt_execute($stmt);
}
?>
