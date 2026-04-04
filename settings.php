<?php
require_once 'functions.php';

requireLogin();

$customerId = getCustomerId();
global $conn;

function loadCustomerSettings(mysqli $conn, int $customerId): ?array
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT
            first_name,
            last_name,
            email,
            gender,
            mfaenabled,
            mfabackupcodes,
            theme_preference,
            profile_image,
            theme_custom_json
         FROM customer
         WHERE customer_id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }
    mysqli_stmt_bind_param($stmt, 'i', $customerId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($res) ?: null;
}

function recommendSizeFromSavedMeasurement(?array $measurement): ?string
{
    if (!$measurement) {
        return null;
    }

    $heightCm = (float)($measurement['height_cm'] ?? 0);
    $weightKg = (float)($measurement['weight_kg'] ?? 0);
    $chestCm  = (float)($measurement['chest_cm'] ?? 0);
    $waistCm  = (float)($measurement['waist_cm'] ?? 0);
    $hipsCm   = (float)($measurement['hips_cm'] ?? 0);

    if ($chestCm > 0 && $waistCm > 0 && $hipsCm > 0) {
        $score = max($chestCm, $waistCm, $hipsCm);
        if ($score < 86) return 'XS';
        if ($score < 94) return 'S';
        if ($score < 102) return 'M';
        if ($score < 110) return 'L';
        if ($score < 118) return 'XL';
        return 'XXL';
    }

    if ($heightCm <= 0 || $weightKg <= 0) {
        return null;
    }

    $heightM = $heightCm / 100;
    $bmi = $weightKg / ($heightM * $heightM);

    if ($bmi < 20) return 'XS';
    if ($bmi < 22) return 'S';
    if ($bmi < 25) return 'M';
    if ($bmi < 28) return 'L';
    if ($bmi < 30) return 'XL';
    return 'XXL';
}

function normalizeMeasurementInput(string $field): ?float
{
    if (!isset($_POST[$field])) {
        return null;
    }

    $value = trim((string)$_POST[$field]);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return -1.0;
    }

    $number = (float)$value;
    return $number > 0 ? $number : -1.0;
}

$themes = [
    'default' => 'Default Indigo',
    'ocean' => 'Ocean Blue',
    'forest' => 'Forest Green',
    'sunset' => 'Sunset Orange',
    'slate' => 'Slate Dark',
    'custom' => 'Custom Theme',
];

$customer = loadCustomerSettings($conn, $customerId);
if (!$customer) {
    die('Unable to load customer profile.');
}

$mfaEnabled = (int)$customer['mfaenabled'];
$themePreference = $customer['theme_preference'] ?? 'default';
if (!isset($themes[$themePreference])) {
    $themePreference = 'default';
}
$profileImage = $customer['profile_image'] ?? null;
$gender = in_array(($customer['gender'] ?? ''), ['Male', 'Female'], true) ? $customer['gender'] : '';
$defaultCustomTheme = [
    'bg_start' => '#667eea',
    'bg_end' => '#764ba2',
    'surface' => '#ffffff',
    'surface_soft' => '#f8fafc',
    'text' => '#1f2937',
    'muted' => '#6b7280',
    'accent' => '#4f46e5',
    'accent_2' => '#7c3aed',
];
$customTheme = $defaultCustomTheme;
if (!empty($customer['theme_custom_json'])) {
    $decoded = json_decode($customer['theme_custom_json'], true);
    if (is_array($decoded)) {
        foreach ($defaultCustomTheme as $k => $v) {
            if (isset($decoded[$k]) && is_string($decoded[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded[$k])) {
                $customTheme[$k] = $decoded[$k];
            }
        }
    }
}

$backupCodesRemaining = 0;
if (!empty($customer['mfabackupcodes'])) {
    $decodedBackupCodes = json_decode($customer['mfabackupcodes'], true);
    if (is_array($decodedBackupCodes)) {
        foreach ($decodedBackupCodes as $entry) {
            if (is_string($entry) && trim($entry) !== '') {
                $backupCodesRemaining++;
            }
        }
    }
}

$latestMeasurement = getLatestBodyMeasurement($customerId);
$savedSize = recommendSizeFromSavedMeasurement($latestMeasurement);

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfPost('settings.php');

    if (isset($_POST['update_display_name'])) {
        $firstName = trim((string)($_POST['first_name'] ?? ''));
        $lastName = trim((string)($_POST['last_name'] ?? ''));
        $genderInput = trim((string)($_POST['gender'] ?? ''));
        if (!in_array($genderInput, ['Male', 'Female'], true)) {
            $genderInput = '';
        }

        $firstLen = function_exists('mb_strlen') ? mb_strlen($firstName) : strlen($firstName);
        $lastLen = function_exists('mb_strlen') ? mb_strlen($lastName) : strlen($lastName);

        if ($firstName === '' || $lastName === '') {
            $error = 'First and last name are required.';
        } elseif ($firstLen > 100 || $lastLen > 100) {
            $error = 'Display name is too long.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE customer
                 SET first_name = ?, last_name = ?, gender = ?
                 WHERE customer_id = ?
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssi', $firstName, $lastName, $genderInput, $customerId);
                if (mysqli_stmt_execute($stmt)) {
                    $_SESSION['customer_name'] = $firstName . ' ' . $lastName;
                    $success = 'Profile details updated.';
                } else {
                    $error = 'Unable to update profile details.';
                }
            } else {
                $error = 'Unable to prepare profile details update.';
            }
        }
    } elseif (isset($_POST['update_body_composition'])) {
        $heightCm = normalizeMeasurementInput('height_cm');
        $weightKg = normalizeMeasurementInput('weight_kg');
        $chestCm = normalizeMeasurementInput('chest_cm');
        $waistCm = normalizeMeasurementInput('waist_cm');
        $hipsCm = normalizeMeasurementInput('hips_cm');

        $values = [
            'Height' => $heightCm,
            'Weight' => $weightKg,
            'Chest' => $chestCm,
            'Waist' => $waistCm,
            'Hips' => $hipsCm,
        ];

        $invalidFields = [];
        foreach ($values as $label => $value) {
            if ($value !== null && $value < 0) {
                $invalidFields[] = $label;
            }
        }

        if ($heightCm === null || $weightKg === null) {
            $error = 'Height and weight are required to save body composition.';
        } elseif ($invalidFields !== []) {
            $error = implode(', ', $invalidFields) . ' must be valid positive numbers.';
        } elseif (saveBodyMeasurement($customerId, $heightCm, $weightKg, null, $chestCm, $waistCm, $hipsCm)) {
            $success = 'Body composition updated.';
        } else {
            $error = 'Unable to save body composition.';
        }
    } elseif (isset($_POST['reset_body_composition'])) {
        if (deleteBodyMeasurements($customerId)) {
            $success = 'Body composition reset.';
        } else {
            $error = 'Unable to reset body composition.';
        }
    } elseif (isset($_POST['update_profile_image'])) {
        $newProfileImage = $profileImage;
        $processedImageData = trim($_POST['profile_image_data'] ?? '');
        if ($processedImageData !== '') {
            if (!preg_match('/^data:(image\/(?:jpeg|png|webp));base64,(.+)$/', $processedImageData, $m)) {
                $error = 'Invalid processed profile image format.';
            } else {
                $mime = $m[1];
                $raw = base64_decode($m[2], true);
                if ($raw === false) {
                    $error = 'Unable to decode processed profile image.';
                } elseif (strlen($raw) > 4 * 1024 * 1024) {
                    $error = 'Processed profile image is too large.';
                } else {
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($allowed[$mime])) {
                        $error = 'Unsupported processed image type.';
                    } else {
                        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profiles';
                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            $error = 'Unable to create profile upload directory.';
                        } else {
                            $filename = 'cust_' . $customerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                            if (file_put_contents($destPath, $raw) === false) {
                                $error = 'Unable to save processed profile image.';
                            } else {
                                if (!empty($profileImage) && str_starts_with($profileImage, 'images/profiles/')) {
                                    $oldFile = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profileImage);
                                    if (is_file($oldFile)) {
                                        @unlink($oldFile);
                                    }
                                }
                                $newProfileImage = 'images/profiles/' . $filename;
                            }
                        }
                    }
                }
            }
        } elseif (isset($_FILES['profile_image']) && is_array($_FILES['profile_image']) && (int)($_FILES['profile_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $uploadError = (int)$_FILES['profile_image']['error'];
            if ($uploadError !== UPLOAD_ERR_OK) {
                $error = 'Image upload failed. Please try again.';
            } else {
                $tmpPath = $_FILES['profile_image']['tmp_name'];
                $sizeBytes = (int)$_FILES['profile_image']['size'];
                if ($sizeBytes > 4 * 1024 * 1024) {
                    $error = 'Profile image must be 4MB or smaller.';
                } else {
                    $mime = mime_content_type($tmpPath) ?: '';
                    $allowed = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                    ];
                    if (!isset($allowed[$mime])) {
                        $error = 'Only JPG, PNG, or WEBP images are allowed.';
                    } else {
                        $uploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'profiles';
                        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                            $error = 'Unable to create profile upload directory.';
                        } else {
                            $filename = 'cust_' . $customerId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
                            $destPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
                            if (!move_uploaded_file($tmpPath, $destPath)) {
                                $error = 'Unable to save profile image.';
                            } else {
                                if (!empty($profileImage) && str_starts_with($profileImage, 'images/profiles/')) {
                                    $oldFile = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $profileImage);
                                    if (is_file($oldFile)) {
                                        @unlink($oldFile);
                                    }
                                }
                                $newProfileImage = 'images/profiles/' . $filename;
                            }
                        }
                    }
                }
            }
        }

        if ($error === '') {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE customer
                 SET profile_image = ?
                 WHERE customer_id = ?
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $newProfileImage, $customerId);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Profile image updated.';
                } else {
                    $error = 'Unable to update profile image.';
                }
            } else {
                $error = 'Unable to prepare profile image update.';
            }
        }
    } elseif (isset($_POST['update_theme'])) {
        $selectedTheme = $_POST['theme_preference'] ?? 'default';
        if (!isset($themes[$selectedTheme])) {
            $selectedTheme = 'default';
        }

        $stmt = mysqli_prepare(
            $conn,
            "UPDATE customer
             SET theme_preference = ?
             WHERE customer_id = ?
             LIMIT 1"
        );
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'si', $selectedTheme, $customerId);
            if (mysqli_stmt_execute($stmt)) {
                $success = 'Theme updated across your account.';
            } else {
                $error = 'Unable to update theme.';
            }
        } else {
            $error = 'Unable to prepare theme update.';
        }
    } elseif (isset($_POST['update_custom_theme'])) {
        $selectedTheme = 'custom';
        $incoming = [
            'bg_start' => $_POST['bg_start'] ?? '',
            'bg_end' => $_POST['bg_end'] ?? '',
            'surface' => $_POST['surface'] ?? '',
            'surface_soft' => $_POST['surface_soft'] ?? '',
            'text' => $_POST['text'] ?? '',
            'muted' => $_POST['muted'] ?? '',
            'accent' => $_POST['accent'] ?? '',
            'accent_2' => $_POST['accent_2'] ?? '',
        ];

        foreach ($incoming as $k => $v) {
            if (!is_string($v) || !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
                $error = 'Invalid custom theme color provided.';
                break;
            }
        }

        if ($error === '') {
            $json = json_encode($incoming);
            if ($json === false) {
                $error = 'Unable to encode custom theme.';
            } else {
                $stmt = mysqli_prepare(
                    $conn,
                    "UPDATE customer
                     SET theme_preference = ?, theme_custom_json = ?
                     WHERE customer_id = ?
                     LIMIT 1"
                );
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, 'ssi', $selectedTheme, $json, $customerId);
                    if (mysqli_stmt_execute($stmt)) {
                        $success = 'Custom theme saved and applied.';
                    } else {
                        $error = 'Unable to update custom theme.';
                    }
                } else {
                    $error = 'Unable to prepare custom theme update.';
                }
            }
        }
    } elseif (isset($_POST['reset_theme_default'])) {
        $defaultJson = json_encode($defaultCustomTheme);
        if ($defaultJson === false) {
            $error = 'Unable to reset theme defaults.';
        } else {
            $stmt = mysqli_prepare(
                $conn,
                "UPDATE customer
                 SET theme_preference = 'default', theme_custom_json = ?
                 WHERE customer_id = ?
                 LIMIT 1"
            );
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'si', $defaultJson, $customerId);
                if (mysqli_stmt_execute($stmt)) {
                    $success = 'Theme reset to default.';
                } else {
                    $error = 'Unable to reset theme.';
                }
            } else {
                $error = 'Unable to prepare theme reset.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if ($new === '' || $confirm === '' || $current === '') {
            $error = 'All password fields are required.';
        } elseif ($new !== $confirm) {
            $error = 'New password and confirmation do not match.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } else {
            $stmt = mysqli_prepare($conn, "SELECT password_hash FROM customer WHERE customer_id = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt, 'i', $customerId);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($res);

            if (!$row || !password_verify($current, $row['password_hash'])) {
                $error = 'Current password is incorrect.';
            } else {
                $newHash = password_hash($new, PASSWORD_BCRYPT);
                $stmtUpd = mysqli_prepare($conn, "UPDATE customer SET password_hash = ? WHERE customer_id = ?");
                mysqli_stmt_bind_param($stmtUpd, 'si', $newHash, $customerId);
                if (mysqli_stmt_execute($stmtUpd)) {
                    $success = 'Password updated successfully.';
                } else {
                    $error = 'Failed to update password. Please try again.';
                }
            }
        }
    }

    $customer = loadCustomerSettings($conn, $customerId);
    if ($customer) {
        $mfaEnabled = (int)$customer['mfaenabled'];
        $themePreference = $customer['theme_preference'] ?? 'default';
        if (!isset($themes[$themePreference])) {
            $themePreference = 'default';
        }
        $profileImage = $customer['profile_image'] ?? null;
        $gender = in_array(($customer['gender'] ?? ''), ['Male', 'Female'], true) ? $customer['gender'] : '';
        $customTheme = $defaultCustomTheme;
        if (!empty($customer['theme_custom_json'])) {
            $decoded = json_decode($customer['theme_custom_json'], true);
            if (is_array($decoded)) {
                foreach ($defaultCustomTheme as $k => $v) {
                    if (isset($decoded[$k]) && is_string($decoded[$k]) && preg_match('/^#[0-9a-fA-F]{6}$/', $decoded[$k])) {
                        $customTheme[$k] = $decoded[$k];
                    }
                }
            }
        }
        $backupCodesRemaining = 0;
        if (!empty($customer['mfabackupcodes'])) {
            $decodedBackupCodes = json_decode($customer['mfabackupcodes'], true);
            if (is_array($decodedBackupCodes)) {
                foreach ($decodedBackupCodes as $entry) {
                    if (is_string($entry) && trim($entry) !== '') {
                        $backupCodesRemaining++;
                    }
                }
            }
        }
    }

    $latestMeasurement = getLatestBodyMeasurement($customerId);
    $savedSize = recommendSizeFromSavedMeasurement($latestMeasurement);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings - ScanFit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            min-height:100vh;
        }
        .wrap{max-width:1000px;margin:0 auto;padding:4rem 1rem}
        .card{
            background:#fff;border-radius:20px;box-shadow:0 25px 60px rgba(0,0,0,.18);padding:2rem
        }
        h1{font-size:2rem;margin-bottom:.4rem;color:#2c3e50}
        .subtitle{color:#6b7280;margin-bottom:1.5rem}
        .alert{padding:.75rem 1rem;border-radius:10px;margin-bottom:1rem;font-size:.9rem}
        .alert-success{background:#dcfce7;color:#166534}
        .alert-error{background:#fee2e2;color:#b91c1c}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem}
        .section{padding:1rem;border:1px solid #e5e7eb;border-radius:14px;background:#fff}
        .section h2{font-size:1.1rem;margin-bottom:.6rem;color:#374151}
        .field{margin-bottom:.8rem}
        .field label{display:block;font-size:.85rem;color:#6b7280;margin-bottom:.3rem}
        .field input,.field select{
            width:100%;padding:.65rem .75rem;border:1px solid #d1d5db;border-radius:10px;font-size:.9rem
        }
        .btn{
            display:inline-block;padding:.65rem 1.2rem;border:none;border-radius:999px;
            background:#4f46e5;color:#fff;font-size:.9rem;font-weight:600;cursor:pointer;text-decoration:none
        }
        .btn-outline{background:#fff;border:1px solid #d1d5db;color:#374151}
        .actions-row{
            display:flex;
            gap:.65rem;
            align-items:center;
            flex-wrap:wrap;
            margin-top:.35rem;
        }
        .actions-row .btn{
            margin:0;
        }
        .badge{display:inline-block;padding:.2rem .6rem;border-radius:999px;font-size:.8rem;font-weight:600}
        .badge-on{background:#dcfce7;color:#166534}
        .badge-off{background:#fee2e2;color:#b91c1c}
        .profile-head{display:flex;gap:1rem;align-items:center;margin-bottom:1rem}
        .avatar{
            width:70px;height:70px;border-radius:50%;object-fit:cover;border:2px solid #dbeafe;background:#eef2ff
        }
        .measure-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}
        .measure-box{padding:.7rem;border-radius:10px;background:#f8fafc;border:1px solid #e5e7eb}
        .measure-label{font-size:.78rem;color:#6b7280}
        .measure-value{font-size:1.05rem;font-weight:700;color:#111827}
        .crop-wrap{margin-top:.6rem}
        .crop-preview{
            width:150px;height:150px;border-radius:12px;overflow:hidden;border:1px solid #d1d5db;background:#f8fafc
        }
        #cropTarget{
            display:block;max-width:100%;max-height:280px;border-radius:10px;margin-bottom:.6rem
        }
        @media(max-width:800px){
            .grid{grid-template-columns:1fr}
            .actions-row .btn{width:100%;text-align:center}
        }
    </style>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
</head>
<body>
<?php include 'navbar.php'; ?>

<div class="wrap">
    <div class="card">
        <div class="profile-head">
            <img
                class="avatar"
                src="<?php echo htmlspecialchars(!empty($profileImage) ? $profileImage : 'https://via.placeholder.com/80x80/f1f5f9/64748b?text=U'); ?>"
                alt="Profile picture"
            >
            <div>
                <h1>Account Settings</h1>
                <p class="subtitle">
                    <?php echo htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']); ?>
                    (<?php echo htmlspecialchars($customer['email']); ?>)
                </p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid">
            <div class="section">
                <h2>Profile Update</h2>
                <p style="color:#6b7280;font-size:.9rem;margin-bottom:.8rem;">Update your display name, gender, and profile image.</p>
                <form method="POST" style="margin-bottom:1rem;">
                    <?php echo csrfInput(); ?>
                    <div class="measure-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                        <div class="field">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" maxlength="100" required value="<?php echo htmlspecialchars((string)$customer['first_name']); ?>">
                        </div>
                        <div class="field">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" maxlength="100" required value="<?php echo htmlspecialchars((string)$customer['last_name']); ?>">
                        </div>
                        <div class="field">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="" <?php echo $gender === '' ? 'selected' : ''; ?>>Select Gender</option>
                                <option value="Male" <?php echo $gender === 'Male' ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo $gender === 'Female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                    <button type="submit" name="update_display_name" class="btn">Save Profile Details</button>
                </form>

                <form method="POST" enctype="multipart/form-data">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="profile_image_data" id="profile_image_data">
                    <div class="field">
                        <label for="profile_image">Profile Image (JPG/PNG/WEBP, max 4MB)</label>
                        <input type="file" id="profile_image" name="profile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                        <div class="crop-wrap">
                            <img id="cropTarget" alt="Crop target" style="display:none;">
                            <div class="crop-preview" id="cropPreview"></div>
                        </div>
                    </div>
                    <button type="submit" name="update_profile_image" class="btn">Save Profile Image</button>
                </form>
            </div>

            <div class="section">
                <h2>Body Composition</h2>
                <p style="color:#6b7280;font-size:.9rem;margin-bottom:.8rem;">Manage the measurements used for your fit profile here instead of in the BMI calculator.</p>
                <form method="POST">
                    <?php echo csrfInput(); ?>
                    <div class="measure-grid">
                        <div class="field">
                            <label for="height_cm">Height (cm)</label>
                            <input type="number" id="height_cm" name="height_cm" step="0.1" min="0.1" required
                                   value="<?php echo htmlspecialchars((string)($_POST['height_cm'] ?? ($latestMeasurement['height_cm'] ?? ''))); ?>">
                        </div>
                        <div class="field">
                            <label for="weight_kg">Weight (kg)</label>
                            <input type="number" id="weight_kg" name="weight_kg" step="0.1" min="0.1" required
                                   value="<?php echo htmlspecialchars((string)($_POST['weight_kg'] ?? ($latestMeasurement['weight_kg'] ?? ''))); ?>">
                        </div>
                        <div class="field">
                            <label for="chest_cm">Chest (cm)</label>
                            <input type="number" id="chest_cm" name="chest_cm" step="0.1" min="0.1"
                                   value="<?php echo htmlspecialchars((string)($_POST['chest_cm'] ?? ($latestMeasurement['chest_cm'] ?? ''))); ?>">
                        </div>
                        <div class="field">
                            <label for="waist_cm">Waist (cm)</label>
                            <input type="number" id="waist_cm" name="waist_cm" step="0.1" min="0.1"
                                   value="<?php echo htmlspecialchars((string)($_POST['waist_cm'] ?? ($latestMeasurement['waist_cm'] ?? ''))); ?>">
                        </div>
                        <div class="field">
                            <label for="hips_cm">Hips (cm)</label>
                            <input type="number" id="hips_cm" name="hips_cm" step="0.1" min="0.1"
                                   value="<?php echo htmlspecialchars((string)($_POST['hips_cm'] ?? ($latestMeasurement['hips_cm'] ?? ''))); ?>">
                        </div>
                    </div>
                    <div class="actions-row">
                        <button type="submit" name="update_body_composition" class="btn">Save Body Composition</button>
                        <button type="submit" name="reset_body_composition" class="btn btn-outline" onclick="return confirm('Reset all saved body composition data?');">Reset Body Composition</button>
                    </div>
                </form>
            </div>

            <div class="section">
                <h2>Theme Customization</h2>
                <p style="color:#6b7280;font-size:.9rem;margin-bottom:.8rem;">Apply your preferred theme to the application.</p>
                <form method="POST">
                    <?php echo csrfInput(); ?>
                    <div class="field">
                        <label for="theme_preference">Theme</label>
                        <select id="theme_preference" name="theme_preference">
                            <?php foreach ($themes as $themeKey => $themeLabel): ?>
                                <option value="<?php echo htmlspecialchars($themeKey); ?>" <?php echo $themePreference === $themeKey ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($themeLabel); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="actions-row">
                        <button type="submit" name="update_theme" class="btn">Save Theme</button>
                        <button type="submit" name="reset_theme_default" class="btn btn-outline">Reset to Default</button>
                    </div>
                </form>
                <form method="POST">
                    <?php echo csrfInput(); ?>
                    <p style="margin:.8rem 0;color:#6b7280;font-size:.9rem;">Custom Colors</p>
                    <div class="measure-grid" style="grid-template-columns:repeat(2,minmax(0,1fr));">
                        <div class="field">
                            <label for="bg_start">Background Start</label>
                            <input type="color" id="bg_start" name="bg_start" value="<?php echo htmlspecialchars($customTheme['bg_start']); ?>">
                        </div>
                        <div class="field">
                            <label for="bg_end">Background End</label>
                            <input type="color" id="bg_end" name="bg_end" value="<?php echo htmlspecialchars($customTheme['bg_end']); ?>">
                        </div>
                        <div class="field">
                            <label for="surface">Surface</label>
                            <input type="color" id="surface" name="surface" value="<?php echo htmlspecialchars($customTheme['surface']); ?>">
                        </div>
                        <div class="field">
                            <label for="surface_soft">Surface Soft</label>
                            <input type="color" id="surface_soft" name="surface_soft" value="<?php echo htmlspecialchars($customTheme['surface_soft']); ?>">
                        </div>
                        <div class="field">
                            <label for="text">Text</label>
                            <input type="color" id="text" name="text" value="<?php echo htmlspecialchars($customTheme['text']); ?>">
                        </div>
                        <div class="field">
                            <label for="muted">Muted Text</label>
                            <input type="color" id="muted" name="muted" value="<?php echo htmlspecialchars($customTheme['muted']); ?>">
                        </div>
                        <div class="field">
                            <label for="accent">Accent</label>
                            <input type="color" id="accent" name="accent" value="<?php echo htmlspecialchars($customTheme['accent']); ?>">
                        </div>
                        <div class="field">
                            <label for="accent_2">Accent 2</label>
                            <input type="color" id="accent_2" name="accent_2" value="<?php echo htmlspecialchars($customTheme['accent_2']); ?>">
                        </div>
                    </div>
                    <button type="submit" name="update_custom_theme" class="btn">Save Custom Theme</button>
                </form>
            </div>

            <div class="section" id="saved-sizes">
                <h2>Saved Measurements & Size</h2>
                <?php if ($latestMeasurement): ?>
                    <div class="measure-grid">
                        <div class="measure-box">
                            <div class="measure-label">Height</div>
                            <div class="measure-value"><?php echo number_format((float)$latestMeasurement['height_cm'], 1); ?> cm</div>
                        </div>
                        <div class="measure-box">
                            <div class="measure-label">Weight</div>
                            <div class="measure-value"><?php echo number_format((float)$latestMeasurement['weight_kg'], 1); ?> kg</div>
                        </div>
                        <div class="measure-box">
                            <div class="measure-label">Chest</div>
                            <div class="measure-value"><?php echo !empty($latestMeasurement['chest_cm']) ? number_format((float)$latestMeasurement['chest_cm'], 1) . ' cm' : '-'; ?></div>
                        </div>
                        <div class="measure-box">
                            <div class="measure-label">Waist</div>
                            <div class="measure-value"><?php echo !empty($latestMeasurement['waist_cm']) ? number_format((float)$latestMeasurement['waist_cm'], 1) . ' cm' : '-'; ?></div>
                        </div>
                        <div class="measure-box">
                            <div class="measure-label">Hips</div>
                            <div class="measure-value"><?php echo !empty($latestMeasurement['hips_cm']) ? number_format((float)$latestMeasurement['hips_cm'], 1) . ' cm' : '-'; ?></div>
                        </div>
                        <div class="measure-box">
                            <div class="measure-label">Recommended Size</div>
                            <div class="measure-value"><?php echo htmlspecialchars($savedSize ?? '-'); ?></div>
                        </div>
                    </div>
                    <p style="margin-top:.8rem;color:#6b7280;font-size:.85rem;">
                        Last saved: <?php echo htmlspecialchars((string)($latestMeasurement['created_at'] ?? 'Unknown')); ?>
                    </p>
                <?php else: ?>
                    <p style="color:#6b7280;">No saved measurements yet. Add your body composition above to build your fit profile.</p>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Two-Factor Authentication (MFA)</h2>
                <p style="color:#6b7280;margin-bottom:.8rem;">Status:
                    <?php if ($mfaEnabled): ?>
                        <span class="badge badge-on">ENABLED</span>
                    <?php else: ?>
                        <span class="badge badge-off">DISABLED</span>
                    <?php endif; ?>
                </p>
                <?php if ($mfaEnabled): ?>
                    <p style="margin-bottom:.7rem;color:#6b7280;">Backup codes remaining: <strong><?php echo (int)$backupCodesRemaining; ?></strong></p>
                    <form method="POST" action="mfa_setup.php" style="display:inline;">
                        <?php echo csrfInput(); ?>
                        <input type="hidden" name="action" value="disable">
                        <button type="submit" class="btn btn-outline">Disable MFA</button>
                    </form>
                    <a href="mfa_setup.php?action=enable" class="btn" style="margin-left:.4rem;">Regenerate QR / Backup Codes</a>
                <?php else: ?>
                    <a href="mfa_setup.php?action=enable" class="btn">Enable MFA</a>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Change Password</h2>
                <form method="POST">
                    <?php echo csrfInput(); ?>
                    <div class="field">
                        <label for="current_password">Current password</label>
                        <input type="password" name="current_password" id="current_password" required>
                    </div>
                    <div class="field">
                        <label for="new_password">New password</label>
                        <input type="password" name="new_password" id="new_password" required>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm new password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required>
                    </div>
                    <button type="submit" name="change_password" class="btn">Save New Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('profile_image');
    const cropTarget = document.getElementById('cropTarget');
    const cropPreview = document.getElementById('cropPreview');
    const hiddenData = document.getElementById('profile_image_data');
    const form = document.querySelector('form[enctype="multipart/form-data"]');
    let cropper = null;

    if (!fileInput || !cropTarget || !cropPreview || !hiddenData || !form || typeof Cropper === 'undefined') {
        return;
    }

    fileInput.addEventListener('change', function () {
        const file = fileInput.files && fileInput.files[0] ? fileInput.files[0] : null;
        hiddenData.value = '';
        if (!file) {
            if (cropper) { cropper.destroy(); cropper = null; }
            cropTarget.style.display = 'none';
            cropPreview.innerHTML = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function (e) {
            cropTarget.src = e.target.result;
            cropTarget.style.display = 'block';

            if (cropper) {
                cropper.destroy();
                cropper = null;
            }

            cropper = new Cropper(cropTarget, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 0.9,
                responsive: true,
                background: false,
                preview: cropPreview,
            });
        };
        reader.readAsDataURL(file);
    });

    form.addEventListener('submit', function (e) {
        if (!cropper || !fileInput.files || !fileInput.files[0]) {
            return;
        }

        const canvas = cropper.getCroppedCanvas({
            width: 512,
            height: 512,
            imageSmoothingQuality: 'high',
        });
        if (!canvas) {
            return;
        }

        hiddenData.value = canvas.toDataURL('image/jpeg', 0.82);
    });
});
</script>
</body>
</html>
