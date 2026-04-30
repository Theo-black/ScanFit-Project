<?php
// connectdb.php
// Database bootstrap only. Session handling is centralized in functions.php.

function loadLocalEnvFile(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);
        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

loadLocalEnvFile(__DIR__ . DIRECTORY_SEPARATOR . '.env');

// Database connection configuration values (from environment)
$host = getenv('SCANFIT_DB_HOST') ?: 'localhost';
$user = getenv('SCANFIT_DB_USER') ?: 'root';
$pass = getenv('SCANFIT_DB_PASS');
$db   = getenv('SCANFIT_DB_NAME') ?: 'capstonestoredb';

if ($pass === false) {
    // Do not keep real credentials in source as a fallback.
    $pass = '';
}


// Create a connection to the MySQL database using mysqli
try {
    $conn = mysqli_connect($host, $user, $pass, $db);
} catch (mysqli_sql_exception $e) {
    $passState = ($pass === '') ? 'NO' : 'YES';
    die(
        'Database Connection Error. ' .
        'Check SCANFIT_DB_HOST/SCANFIT_DB_USER/SCANFIT_DB_PASS/SCANFIT_DB_NAME in your .env file. ' .
        'Current password provided: ' . $passState
    );
}

// If the connection fails, stop execution and output the error message
if (!$conn) {
    die('Database Connection Error: ' . mysqli_connect_error());
}

// Ensure the connection uses UTF-8 (utf8mb4) character encoding
mysqli_set_charset($conn, 'utf8mb4');
?>
