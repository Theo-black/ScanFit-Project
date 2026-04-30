<?php
// bmi_calculator.php

require_once 'functions.php';

$result          = null;
$bmi             = null;
$category        = '';
$recommendedSize = '';
$cameraSize      = '';
$scanChestCm     = null;
$scanWaistCm     = null;
$scanHipsCm      = null;
$scanQuality     = '';
$scanConfidencePct = null;
$scanProtocolSteps = 0;
$scanSource      = '';
$photoSizeEstimate = '';
$photoEstimateBasis = '';
$tapeChestCm     = null;
$tapeWaistCm     = null;
$calcError       = '';
$sizingMethod    = $_POST['sizing_method'] ?? 'bmi';
$savedMeasurement = null;
$savedGender = '';
$savedHeightCm = null;
$savedWeightKg = null;
$savedChestCm = null;
$savedWaistCm = null;
$savedHipsCm = null;
$savedFitSize = null;
$activeProfileMode = 'self';
$activeGender = '';
$activeHeightCm = null;
$activeWeightKg = null;
$activeChestCm = null;
$activeWaistCm = null;
$activeHipsCm = null;
$activeFitSize = null;
$activeProfileLabel = 'your saved profile';
$activeHeightUnit = 'cm';
$activeWeightUnit = 'kg';
$uploadPhotoFlashResult = null;
$uploadPhotoFlashFront = '';
$uploadPhotoFlashSide = '';
$localScannerEnabled = localScannerIsConfigured();
$fitXpressEnabled = fitXpressIsConfigured();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SESSION['flash_upload_photo_result']) && is_array($_SESSION['flash_upload_photo_result'])) {
    $uploadPhotoFlashResult = $_SESSION['flash_upload_photo_result'];
    $uploadPhotoFlashFront = (string)($uploadPhotoFlashResult['fitxpress_front_photo'] ?? '');
    $uploadPhotoFlashSide = (string)($uploadPhotoFlashResult['fitxpress_side_photo'] ?? '');
    unset($_SESSION['flash_upload_photo_result']);
}

if (isLoggedIn()) {
    $savedMeasurement = getLatestBodyMeasurement(getCustomerId());
    global $conn;
    $genderStmt = mysqli_prepare($conn, "SELECT gender FROM customer WHERE customer_id = ? LIMIT 1");
    if ($genderStmt) {
        $cid = getCustomerId();
        mysqli_stmt_bind_param($genderStmt, 'i', $cid);
        mysqli_stmt_execute($genderStmt);
        $genderRes = mysqli_stmt_get_result($genderStmt);
        $genderRow = mysqli_fetch_assoc($genderRes);
        $savedGender = in_array(($genderRow['gender'] ?? ''), ['Male', 'Female'], true) ? $genderRow['gender'] : '';
    }
    if ($savedMeasurement) {
        $savedHeightCm = !empty($savedMeasurement['height_cm']) ? (float)$savedMeasurement['height_cm'] : null;
        $savedWeightKg = !empty($savedMeasurement['weight_kg']) ? (float)$savedMeasurement['weight_kg'] : null;
        $savedChestCm = !empty($savedMeasurement['chest_cm']) ? (float)$savedMeasurement['chest_cm'] : null;
        $savedWaistCm = !empty($savedMeasurement['waist_cm']) ? (float)$savedMeasurement['waist_cm'] : null;
        $savedHipsCm = !empty($savedMeasurement['hips_cm']) ? (float)$savedMeasurement['hips_cm'] : null;
    }
}

if ($savedHeightCm !== null && $savedWeightKg !== null) {
    $savedFitProfile = recommendFitFromProfile($savedHeightCm, $savedWeightKg, $savedChestCm, $savedWaistCm, $savedHipsCm);
    $savedFitSize = $savedFitProfile['fit_size'] ?? null;
}

$calculatorProfileSession = $_SESSION['calculator_profile_context'] ?? [];
if (($calculatorProfileSession['mode'] ?? 'self') === 'other') {
    $activeProfileMode = 'other';
    $activeHeightUnit = (($calculatorProfileSession['height_unit'] ?? 'cm') === 'ft') ? 'ft' : 'cm';
    $activeWeightUnit = (($calculatorProfileSession['weight_unit'] ?? 'kg') === 'lb') ? 'lb' : 'kg';
    $activeGender = in_array(($calculatorProfileSession['gender'] ?? ''), ['Male', 'Female'], true) ? $calculatorProfileSession['gender'] : '';
    $activeHeightCm = isset($calculatorProfileSession['height_cm']) && (float)$calculatorProfileSession['height_cm'] > 0 ? (float)$calculatorProfileSession['height_cm'] : null;
    $activeWeightKg = isset($calculatorProfileSession['weight_kg']) && (float)$calculatorProfileSession['weight_kg'] > 0 ? (float)$calculatorProfileSession['weight_kg'] : null;
    $activeChestCm = isset($calculatorProfileSession['chest_cm']) && (float)$calculatorProfileSession['chest_cm'] > 0 ? (float)$calculatorProfileSession['chest_cm'] : null;
    $activeWaistCm = isset($calculatorProfileSession['waist_cm']) && (float)$calculatorProfileSession['waist_cm'] > 0 ? (float)$calculatorProfileSession['waist_cm'] : null;
    $activeHipsCm = isset($calculatorProfileSession['hips_cm']) && (float)$calculatorProfileSession['hips_cm'] > 0 ? (float)$calculatorProfileSession['hips_cm'] : null;
    $activeProfileLabel = 'someone else';
} else {
    $activeGender = $savedGender;
    $activeHeightCm = $savedHeightCm;
    $activeWeightKg = $savedWeightKg;
    $activeChestCm = $savedChestCm;
    $activeWaistCm = $savedWaistCm;
    $activeHipsCm = $savedHipsCm;
}

if ($activeHeightCm !== null && $activeWeightKg !== null) {
    $activeFitProfile = recommendFitFromProfile($activeHeightCm, $activeWeightKg, $activeChestCm, $activeWaistCm, $activeHipsCm);
    $activeFitSize = $activeFitProfile['fit_size'] ?? null;
}

function sizeRank(string $size): int
{
    $map = ['XS' => 1, 'S' => 2, 'M' => 3, 'L' => 4, 'XL' => 5, 'XXL' => 6];
    return $map[$size] ?? 3;
}

function sizeFromRank(int $rank): string
{
    $map = [1 => 'XS', 2 => 'S', 3 => 'M', 4 => 'L', 5 => 'XL', 6 => 'XXL'];
    return $map[$rank] ?? 'M';
}

function constrainPhotoSizeEstimate(string $rawSize, ?string $baselineSize = null, int $maxShift = 1): string
{
    $rawRank = sizeRank($rawSize);
    if ($baselineSize === null || $baselineSize === '') {
        return sizeFromRank($rawRank);
    }

    $baselineRank = sizeRank($baselineSize);
    $minRank = max(1, $baselineRank - $maxShift);
    $maxRank = min(6, $baselineRank + $maxShift);

    return sizeFromRank(max($minRank, min($maxRank, $rawRank)));
}

function recommendSizeFromMeasurements(float $chestCm, float $waistCm, float $hipsCm): string
{
    $score = max($chestCm, $waistCm, $hipsCm);
    if ($score < 86) return 'XS';
    if ($score < 94) return 'S';
    if ($score < 102) return 'M';
    if ($score < 110) return 'L';
    if ($score < 118) return 'XL';
    return 'XXL';
}

function categoryFromBmi(float $bmi): string
{
    if ($bmi < 18.5) return 'Underweight';
    if ($bmi < 25) return 'Normal weight';
    if ($bmi < 30) return 'Overweight';
    return 'Obese';
}

function recommendSizeFromBmiHeight(float $bmi, float $heightCm): string
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

function fitLabelFromWaistToHeight(float $ratio): string
{
    if ($ratio < 0.43) return 'Slim';
    if ($ratio < 0.53) return 'Balanced';
    if ($ratio < 0.60) return 'Relaxed';
    return 'Fuller Fit';
}

function recommendFitFromProfile(
    float $heightCm,
    float $weightKg,
    ?float $chestCm = null,
    ?float $waistCm = null,
    ?float $hipsCm = null
): array {
    $heightM = $heightCm / 100;
    $bmi = $heightM > 0 ? ($weightKg / ($heightM * $heightM)) : 0;
    $bmiSize = recommendSizeFromBmiHeight($bmi, $heightCm);
    $finalRank = sizeRank($bmiSize);
    $measurementSize = null;
    $waistToHeightRatio = null;
    $fitLabel = null;

    if ($chestCm !== null && $waistCm !== null && $hipsCm !== null) {
        $measurementSize = recommendSizeFromMeasurements($chestCm, $waistCm, $hipsCm);
        $finalRank = (int)round(($finalRank + sizeRank($measurementSize)) / 2);
    }

    if ($waistCm !== null && $heightCm > 0) {
        $waistToHeightRatio = $waistCm / $heightCm;
        $fitLabel = fitLabelFromWaistToHeight($waistToHeightRatio);

        if ($waistToHeightRatio >= 0.60) {
            $finalRank += 2;
        } elseif ($waistToHeightRatio >= 0.53) {
            $finalRank += 1;
        } elseif ($waistToHeightRatio < 0.43) {
            $finalRank -= 1;
        }
    }

    $finalRank = max(1, min(6, $finalRank));

    return [
        'bmi_size' => $bmiSize,
        'measurement_size' => $measurementSize,
        'fit_size' => sizeFromRank($finalRank),
        'waist_to_height_ratio' => $waistToHeightRatio,
        'fit_label' => $fitLabel,
    ];
}

function renderToolResultCard(array $result, string $title, string $context): void
{
    $isSizeTool = $context === 'size';
    $isUploadTool = $context === 'upload';
    $isLiveTool = $context === 'live';
    ?>
    <div class="result-card" style="margin-top:1.5rem">
        <h2><?php echo htmlspecialchars($title); ?></h2>
        <div class="size-recommendation">
            Recommended Size: <?php echo htmlspecialchars((string)$result['size']); ?>
        </div>
        <div class="result-grid">
            <?php if ($isSizeTool && !empty($result['bmi'])): ?>
                <div class="result-item"><div class="result-item-label">BMI</div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['bmi']); ?></div></div>
            <?php endif; ?>
            <?php if ($isSizeTool && !empty($result['category'])): ?>
                <div class="result-item"><div class="result-item-label">Category</div><div class="result-item-value" style="font-size:1.3rem"><?php echo htmlspecialchars((string)$result['category']); ?></div></div>
            <?php endif; ?>
            <?php if ($isSizeTool && !empty($result['bmi_size'])): ?>
                <div class="result-item"><div class="result-item-label">BMI Size</div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['bmi_size']); ?></div></div>
            <?php endif; ?>
            <?php if ($isSizeTool && !empty($result['measurement_size'])): ?>
                <div class="result-item"><div class="result-item-label">Measurement Size</div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['measurement_size']); ?></div></div>
            <?php endif; ?>
            <?php if ($isSizeTool && !empty($result['fit_size'])): ?>
                <div class="result-item"><div class="result-item-label">Fit Size</div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['fit_size']); ?></div></div>
            <?php endif; ?>
            <?php if ($isSizeTool && !empty($result['waist_to_height_ratio'])): ?>
                <div class="result-item"><div class="result-item-label">Waist/Height</div><div class="result-item-value"><?php echo number_format((float)$result['waist_to_height_ratio'], 2); ?></div></div>
            <?php endif; ?>
            <?php if ($isSizeTool && !empty($result['fit_label'])): ?>
                <div class="result-item"><div class="result-item-label">Fit Profile</div><div class="result-item-value" style="font-size:1.2rem"><?php echo htmlspecialchars((string)$result['fit_label']); ?></div></div>
            <?php endif; ?>
            <?php if (($isUploadTool || $isLiveTool) && !empty($result['camera_size'])): ?>
                <div class="result-item"><div class="result-item-label"><?php echo $isUploadTool ? 'Photo Size' : 'Live Camera Size'; ?></div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['camera_size']); ?></div></div>
            <?php endif; ?>
            <?php if (($isUploadTool || $isLiveTool) && !empty($result['scan_quality'])): ?>
                <div class="result-item"><div class="result-item-label">Scan Quality</div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['scan_quality']); ?></div></div>
            <?php endif; ?>
            <?php if (($isUploadTool || $isLiveTool) && !empty($result['scan_provider'])): ?>
                <div class="result-item"><div class="result-item-label">Scan Provider</div><div class="result-item-value"><?php echo htmlspecialchars((string)$result['scan_provider']); ?></div></div>
            <?php endif; ?>
            <?php if (($isUploadTool || $isLiveTool) && !empty($result['scan_chest_cm'])): ?>
                <div class="result-item"><div class="result-item-label">Chest</div><div class="result-item-value"><?php echo number_format((float)$result['scan_chest_cm'], 1); ?> cm</div></div>
            <?php endif; ?>
            <?php if (($isUploadTool || $isLiveTool) && !empty($result['scan_waist_cm'])): ?>
                <div class="result-item"><div class="result-item-label">Waist</div><div class="result-item-value"><?php echo number_format((float)$result['scan_waist_cm'], 1); ?> cm</div></div>
            <?php endif; ?>
            <?php if (($isUploadTool || $isLiveTool) && !empty($result['scan_hips_cm'])): ?>
                <div class="result-item"><div class="result-item-label">Hips</div><div class="result-item-value"><?php echo number_format((float)$result['scan_hips_cm'], 1); ?> cm</div></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['calculator_profile_action'])) {
        requireCsrfPost('bmi_calculator.php');
        $profileMode = (($_POST['calculator_profile_mode'] ?? 'self') === 'other') ? 'other' : 'self';
        if ($profileMode === 'other') {
            $calculatorHeightUnit = (($_POST['calculator_height_unit'] ?? 'cm') === 'ft') ? 'ft' : 'cm';
            $calculatorWeightUnit = (($_POST['calculator_weight_unit'] ?? 'kg') === 'lb') ? 'lb' : 'kg';
            $calculatorHeightRaw = max(0, (float)($_POST['calculator_height_cm'] ?? 0));
            $calculatorWeightRaw = max(0, (float)($_POST['calculator_weight_kg'] ?? 0));
            $_SESSION['calculator_profile_context'] = [
                'mode' => 'other',
                'gender' => in_array(($_POST['calculator_gender'] ?? ''), ['Male', 'Female'], true) ? $_POST['calculator_gender'] : '',
                'height_cm' => $calculatorHeightUnit === 'ft' ? ($calculatorHeightRaw * 30.48) : $calculatorHeightRaw,
                'weight_kg' => $calculatorWeightUnit === 'lb' ? ($calculatorWeightRaw / 2.2046226218) : $calculatorWeightRaw,
                'height_unit' => $calculatorHeightUnit,
                'weight_unit' => $calculatorWeightUnit,
                'chest_cm' => max(0, (float)($_POST['calculator_chest_cm'] ?? 0)),
                'waist_cm' => max(0, (float)($_POST['calculator_waist_cm'] ?? 0)),
                'hips_cm' => max(0, (float)($_POST['calculator_hips_cm'] ?? 0)),
            ];
        } else {
            unset($_SESSION['calculator_profile_context']);
        }
        header('Location: bmi_calculator.php#size-calculator-section');
        exit;
    }

    requireCsrfPost('bmi_calculator.php');

    if (isset($_POST['calculator_profile_mode'])) {
        $postedProfileMode = (($_POST['calculator_profile_mode'] ?? 'self') === 'other') ? 'other' : 'self';
        if ($postedProfileMode === 'other') {
            $postedHeightUnit = (($_POST['calculator_height_unit'] ?? 'cm') === 'ft') ? 'ft' : 'cm';
            $postedWeightUnit = (($_POST['calculator_weight_unit'] ?? 'kg') === 'lb') ? 'lb' : 'kg';
            $postedHeightRaw = max(0, (float)($_POST['calculator_height_cm'] ?? 0));
            $postedWeightRaw = max(0, (float)($_POST['calculator_weight_kg'] ?? 0));
            $postedProfile = [
                'mode' => 'other',
                'gender' => in_array(($_POST['calculator_gender'] ?? ''), ['Male', 'Female'], true) ? $_POST['calculator_gender'] : '',
                'height_cm' => $postedHeightUnit === 'ft' ? ($postedHeightRaw * 30.48) : $postedHeightRaw,
                'weight_kg' => $postedWeightUnit === 'lb' ? ($postedWeightRaw / 2.2046226218) : $postedWeightRaw,
                'height_unit' => $postedHeightUnit,
                'weight_unit' => $postedWeightUnit,
                'chest_cm' => max(0, (float)($_POST['calculator_chest_cm'] ?? 0)),
                'waist_cm' => max(0, (float)($_POST['calculator_waist_cm'] ?? 0)),
                'hips_cm' => max(0, (float)($_POST['calculator_hips_cm'] ?? 0)),
            ];
            $_SESSION['calculator_profile_context'] = $postedProfile;
            $activeProfileMode = 'other';
            $activeGender = $postedProfile['gender'];
            $activeHeightCm = $postedProfile['height_cm'] > 0 ? (float)$postedProfile['height_cm'] : null;
            $activeWeightKg = $postedProfile['weight_kg'] > 0 ? (float)$postedProfile['weight_kg'] : null;
            $activeChestCm = $postedProfile['chest_cm'] > 0 ? (float)$postedProfile['chest_cm'] : null;
            $activeWaistCm = $postedProfile['waist_cm'] > 0 ? (float)$postedProfile['waist_cm'] : null;
            $activeHipsCm = $postedProfile['hips_cm'] > 0 ? (float)$postedProfile['hips_cm'] : null;
            $activeProfileLabel = 'someone else';
        } else {
            unset($_SESSION['calculator_profile_context']);
            $activeProfileMode = 'self';
            $activeGender = $savedGender;
            $activeHeightCm = $savedHeightCm;
            $activeWeightKg = $savedWeightKg;
            $activeChestCm = $savedChestCm;
            $activeWaistCm = $savedWaistCm;
            $activeHipsCm = $savedHipsCm;
            $activeProfileLabel = 'your saved profile';
        }
    }

    $gender = $activeGender;
    $sizingMethod = $_POST['sizing_method'] ?? 'bmi';
    if (!in_array($sizingMethod, ['bmi', 'camera'], true)) {
        $sizingMethod = 'bmi';
    }
    $heightCm = (float)($activeHeightCm ?? 0);
    $weightKg = (float)($activeWeightKg ?? 0);
    $scanChestCm = isset($_POST['scan_chest_cm']) ? (float)$_POST['scan_chest_cm'] : null;
    $scanWaistCm = isset($_POST['scan_waist_cm']) ? (float)$_POST['scan_waist_cm'] : null;
    $scanHipsCm  = isset($_POST['scan_hips_cm']) ? (float)$_POST['scan_hips_cm'] : null;
    $scanQuality = strtoupper(trim($_POST['scan_quality'] ?? ''));
    $scanConfidencePct = isset($_POST['scan_confidence_pct']) ? (float)$_POST['scan_confidence_pct'] : null;
    $scanProtocolSteps = isset($_POST['scan_protocol_steps']) ? (int)$_POST['scan_protocol_steps'] : 0;
    $scanSource = trim((string)($_POST['scan_source'] ?? ''));
    $photoSizeEstimate = strtoupper(trim((string)($_POST['photo_size_estimate'] ?? '')));
    $photoEstimateBasis = trim((string)($_POST['photo_estimate_basis'] ?? ''));
    $fitXpressFrontPhoto = trim((string)($_POST['fitxpress_front_photo'] ?? ''));
    $fitXpressSidePhoto = trim((string)($_POST['fitxpress_side_photo'] ?? ''));
    $fitXpressMeasurementId = trim((string)($_POST['fitxpress_measurement_id'] ?? ''));
    $scanProvider = trim((string)($_POST['scan_provider'] ?? ''));
    $tapeChestCm = $activeChestCm;
    $tapeWaistCm = $activeWaistCm;
    if (!in_array($scanQuality, ['LOW', 'MEDIUM', 'HIGH'], true)) {
        $scanQuality = '';
    }
    if (!in_array($scanSource, ['camera', 'photo'], true)) {
        $scanSource = '';
    }
    if (!in_array($photoSizeEstimate, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true)) {
        $photoSizeEstimate = '';
    }
    if (!in_array($photoEstimateBasis, ['saved_profile', 'photo_adjusted', 'photo_fallback', 'fitxpress_api', 'local_ai'], true)) {
        $photoEstimateBasis = '';
    }
    if (!in_array($scanProvider, ['3DLOOK FitXpress', 'Local AI Scanner'], true)) {
        $scanProvider = '';
    }

    if (($scanChestCm ?? 0) <= 0) $scanChestCm = null;
    if (($scanWaistCm ?? 0) <= 0) $scanWaistCm = null;
    if (($scanHipsCm ?? 0) <= 0) $scanHipsCm = null;
    if (($scanConfidencePct ?? 0) <= 0) $scanConfidencePct = null;
    if ($scanProtocolSteps < 0) $scanProtocolSteps = 0;
    if (($tapeChestCm ?? 0) <= 0) $tapeChestCm = null;
    if (($tapeWaistCm ?? 0) <= 0) $tapeWaistCm = null;

    if ($activeGender === '') {
        $calcError = $activeProfileMode === 'other'
            ? 'Enter gender for the person you are calculating for.'
            : 'Select your gender in Settings before running size calculations.';
    } elseif ($activeHeightCm === null || $activeWeightKg === null) {
        $calcError = $activeProfileMode === 'other'
            ? 'Enter height and weight for the person you are calculating for.'
            : 'Add your body composition in Settings before running size calculations.';
    }

    $hasBmiInputs = $heightCm > 0 && $weightKg > 0;
    $hasCameraInputs = $scanChestCm !== null && $scanWaistCm !== null && $scanHipsCm !== null;

    $bmiSize = '';
    $measurementSize = null;
    $fitSize = null;
    $waistToHeightRatio = null;
    $fitLabel = null;
    if ($hasBmiInputs) {
        $heightM = $heightCm / 100;
        if ($heightM > 0) {
            $bmi = $weightKg / ($heightM * $heightM);
            $category = categoryFromBmi($bmi);
            $bmiSize = recommendSizeFromBmiHeight($bmi, $heightCm);
            $fitProfile = recommendFitFromProfile($heightCm, $weightKg, $activeChestCm, $activeWaistCm, $activeHipsCm);
            $measurementSize = $fitProfile['measurement_size'];
            $fitSize = $fitProfile['fit_size'];
            $waistToHeightRatio = $fitProfile['waist_to_height_ratio'];
            $fitLabel = $fitProfile['fit_label'];
        }
    }
    if ($hasCameraInputs) {
        $cameraSize = recommendSizeFromMeasurements($scanChestCm, $scanWaistCm, $scanHipsCm);
    }

    $hasFitXpressImages = $fitXpressFrontPhoto !== '' && $fitXpressSidePhoto !== '';
    if ($calcError === '' && $sizingMethod === 'camera' && $hasFitXpressImages) {
        if ($localScannerEnabled) {
            $localScannerError = null;
            $localMeasures = scanMeasurementsWithLocalService($fitXpressFrontPhoto, $fitXpressSidePhoto, $heightCm, $localScannerError);
            if (!$localMeasures) {
                $calcError = $localScannerError ?: 'Unable to process images with the local AI scanner.';
            } else {
                $scanChestCm = (float)$localMeasures['chest'];
                $scanWaistCm = (float)$localMeasures['waist'];
                $scanHipsCm = (float)$localMeasures['hips'];
                $cameraSize = recommendSizeFromMeasurements($scanChestCm, $scanWaistCm, $scanHipsCm);
                $photoSizeEstimate = constrainPhotoSizeEstimate($cameraSize, $activeFitSize, 1);
                $photoEstimateBasis = 'local_ai';
                $scanQuality = 'MEDIUM';
                $scanConfidencePct = null;
                $scanProtocolSteps = 2;
                $scanProvider = 'Local AI Scanner';
                $hasCameraInputs = true;
            }
        } elseif (!$fitXpressEnabled) {
            $calcError = 'No scanner backend is available. Start the local AI scanner on localhost:8001 or configure 3DLOOK FitXpress.';
        } else {
            $fitXpressPayload = [
                'height' => (int)round($heightCm),
                'gender' => strtolower($activeGender),
                'front_photo' => $fitXpressFrontPhoto,
                'side_photo' => $fitXpressSidePhoto,
            ];
            if ($weightKg > 0) {
                $fitXpressPayload['weight'] = (int)round($weightKg);
            }

            $fitXpressError = null;
            $createdMeasurement = createFitXpressMeasurement($fitXpressPayload, $fitXpressError);
            if (!$createdMeasurement || empty($createdMeasurement['id'])) {
                $calcError = $fitXpressError ?: 'Unable to start 3DLOOK FitXpress scan.';
            } else {
                $fitXpressMeasurementId = (string)$createdMeasurement['id'];
                $completedMeasurement = pollFitXpressMeasurement($fitXpressMeasurementId, 25, $fitXpressError);
                if (!$completedMeasurement) {
                    $calcError = $fitXpressError ?: '3DLOOK FitXpress scan did not complete.';
                } else {
                    $fitXpressMeasures = extractFitXpressCircumferences($completedMeasurement);
                    if (!$fitXpressMeasures) {
                        $calcError = '3DLOOK FitXpress did not return usable chest, waist, and hips measurements.';
                    } else {
                        $scanChestCm = (float)$fitXpressMeasures['chest'];
                        $scanWaistCm = (float)$fitXpressMeasures['waist'];
                        $scanHipsCm = (float)$fitXpressMeasures['hips'];
                        $cameraSize = recommendSizeFromMeasurements($scanChestCm, $scanWaistCm, $scanHipsCm);
                        $photoSizeEstimate = constrainPhotoSizeEstimate($cameraSize, $activeFitSize, 1);
                        $photoEstimateBasis = 'fitxpress_api';
                        $scanQuality = 'HIGH';
                        $scanConfidencePct = null;
                        $scanProtocolSteps = 2;
                        $scanProvider = '3DLOOK FitXpress';
                        $hasCameraInputs = true;
                    }
                }
            }
        }
    }

    if ($calcError !== '') {
        $recommendedSize = '';
    } elseif ($sizingMethod === 'bmi') {
        if (!$hasBmiInputs || $bmi === null) {
            $calcError = 'BMI method requires valid height and weight.';
        } else {
            $recommendedSize = $fitSize ?? $bmiSize;
        }
    } elseif ($sizingMethod === 'camera') {
        if ($scanSource === 'photo' && $photoSizeEstimate === '') {
            $calcError = 'Photo scan requires a completed photo size estimate.';
        } elseif (!$hasCameraInputs && $scanSource !== 'photo') {
            $calcError = 'Camera method requires a completed body scan (chest, waist, hips).';
        } elseif ($scanSource !== 'photo' && $scanProvider === '' && !in_array($scanQuality, ['MEDIUM', 'HIGH'], true)) {
            $calcError = 'Camera method requires a MEDIUM or HIGH scan quality for accurate sizing.';
        } elseif ($scanSource === 'camera' && $scanProvider === '' && $scanProtocolSteps < 8) {
            $calcError = 'Live camera scanning requires the full 8-step capture protocol.';
        } else {
            $recommendedSize = $scanSource === 'photo' ? $photoSizeEstimate : $cameraSize;
        }
    }

    if ($calcError === '' && $recommendedSize !== '') {
        $result = [
            'method' => $sizingMethod,
            'gender' => $gender,
            'height' => $heightCm > 0 ? $heightCm : null,
            'weight' => $weightKg > 0 ? $weightKg : null,
            'bmi' => $bmi !== null ? number_format($bmi, 2) : null,
            'category' => $category !== '' ? $category : null,
            'bmi_size' => $bmiSize !== '' ? $bmiSize : null,
            'measurement_size' => $measurementSize,
            'fit_size' => $fitSize,
            'waist_to_height_ratio' => $waistToHeightRatio,
            'fit_label' => $fitLabel,
            'size' => $recommendedSize,
            'camera_size' => ($scanSource === 'photo' ? $photoSizeEstimate : ($cameraSize !== '' ? $cameraSize : null)),
            'photo_size_estimate' => $photoSizeEstimate !== '' ? $photoSizeEstimate : null,
            'scan_chest_cm' => ($scanSource === 'photo' && $scanProvider === '') ? null : $scanChestCm,
            'scan_waist_cm' => ($scanSource === 'photo' && $scanProvider === '') ? null : $scanWaistCm,
            'scan_hips_cm' => ($scanSource === 'photo' && $scanProvider === '') ? null : $scanHipsCm,
            'scan_quality' => $scanQuality !== '' ? $scanQuality : null,
            'scan_confidence_pct' => $scanConfidencePct,
            'scan_protocol_steps' => $scanProtocolSteps,
            'scan_source' => $scanSource !== '' ? $scanSource : null,
            'scan_provider' => $scanProvider !== '' ? $scanProvider : null,
            'fitxpress_measurement_id' => $fitXpressMeasurementId !== '' ? $fitXpressMeasurementId : null,
            'fitxpress_front_photo' => $fitXpressFrontPhoto !== '' ? $fitXpressFrontPhoto : null,
            'fitxpress_side_photo' => $fitXpressSidePhoto !== '' ? $fitXpressSidePhoto : null,
            'tape_chest_cm' => $tapeChestCm,
            'tape_waist_cm' => $tapeWaistCm,
            'photo_estimate_basis' => $photoEstimateBasis !== '' ? $photoEstimateBasis : null
        ];

        if ($sizingMethod === 'camera' && $scanSource === 'photo') {
            $_SESSION['flash_upload_photo_result'] = $result;
            header('Location: bmi_calculator.php#upload-photo-section');
            exit;
        }
    }
}

$scanPopupResult = null;
$scanPopupTitle = '';
$scanPopupContext = '';
if (is_array($uploadPhotoFlashResult) && ($uploadPhotoFlashResult['method'] ?? '') === 'camera' && ($uploadPhotoFlashResult['scan_source'] ?? '') === 'photo') {
    $scanPopupResult = $uploadPhotoFlashResult;
    $scanPopupTitle = 'Uploaded Photo Result';
    $scanPopupContext = 'upload';
} elseif (is_array($result) && ($result['method'] ?? '') === 'camera' && ($result['scan_source'] ?? '') === 'camera') {
    $scanPopupResult = $result;
    $scanPopupTitle = 'Live Camera Result';
    $scanPopupContext = 'live';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Size Calculator & Size Guide - Scanfit</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{
            font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
            background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);
            color:#333;min-height:100vh
        }
        .hero-section{
            background:linear-gradient(rgba(102,126,234,.9),rgba(118,75,162,.9));
            height:40vh;display:flex;align-items:center;justify-content:center;
            text-align:center;color:#fff
        }
        .hero-content h1{
            font-size:clamp(2rem,5vw,3.5rem);font-weight:800;margin-bottom:1rem
        }
        .hero-content p{
            font-size:clamp(1rem,2vw,1.3rem);opacity:.95
        }
        .container{
            max-width:900px;margin:0 auto;padding:3rem 1.5rem
        }
        .calculator-card{
            background:#fff;border-radius:25px;padding:2.5rem;
            box-shadow:0 20px 60px rgba(0,0,0,.1);margin-bottom:2rem
        }
        .section-grid{
            display:grid;
            grid-template-columns:1fr;
            gap:1.5rem;
            align-items:start;
        }
        .section-card{
            background:#fff;
            border-radius:25px;
            padding:2rem;
            box-shadow:0 20px 60px rgba(0,0,0,.08);
            margin-bottom:2rem;
        }
        .tool-panel{display:none}
        .tool-panel.is-active{display:block}
        .scanner-tool{display:none}
        .scanner-tool.is-active{display:block}
        .section-card h2{
            font-size:1.7rem;
            margin-bottom:.6rem;
            color:#2c3e50;
        }
        .section-card p{
            color:#64748b;
            line-height:1.6;
        }
        .profile-mode-card{
            margin-bottom:1rem;
            padding:1rem 1.1rem;
            border:1px solid #dbe4ff;
            border-radius:18px;
            background:#f8faff;
        }
        .profile-mode-grid{
            display:grid;
            grid-template-columns:1fr 1fr 1fr;
            gap:.85rem;
            margin-top:.85rem;
        }
        .profile-mode-grid label{
            display:block;
            font-size:.82rem;
            font-weight:700;
            color:#475569;
            margin-bottom:.3rem;
        }
        .profile-mode-grid input,
        .profile-mode-grid select{
            width:100%;
            padding:.8rem .9rem;
            border-radius:12px;
            border:2px solid #e1e4e8;
            background:#fff;
        }
        .profile-mode-actions{
            margin-top:.9rem;
            display:flex;
            gap:.75rem;
            flex-wrap:wrap;
            align-items:center;
        }
        .profile-mode-note{
            color:#334155;
            font-size:.9rem;
            font-weight:600;
        }
        .form-group{margin-bottom:1.5rem}
        .form-group label{
            display:block;font-weight:600;margin-bottom:.5rem;
            color:#2c3e50;font-size:1.05rem
        }
        select,input[type="number"]{
            font-size:1rem;
        }
        .form-group select,
        .form-group input{
            width:100%;padding:0.9rem 1rem;border:2px solid #e1e4e8;
            border-radius:12px;outline:none;transition:border-color .2s
        }
        .form-group select:focus,
        .form-group input:focus{border-color:#667eea}
        .form-row{
            display:grid;grid-template-columns:1fr 1fr;gap:1.5rem
        }
        .inline-input{
            display:flex;gap:0.75rem;align-items:center;
        }
        .inline-input .value-box{
            width:110px;
        }
        .inline-input .value-box input{
            width:100%;
        }
        .inline-input .unit-box{
            flex:1;
        }
        .inline-input .unit-box select{
            width:50%;
        }
        .hint{
            color:#6b7280;font-size:0.8rem;margin-top:0.25rem;
        }
        .action-status{
            margin-top:.85rem;
            font-size:.9rem;
            color:#1d4ed8;
            min-height:1.2rem;
            font-weight:600;
        }
        .submit-btn{
            width:100%;padding:1.1rem;border:none;border-radius:15px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:#fff;font-size:1.05rem;font-weight:700;cursor:pointer;
            transition:transform .15s,box-shadow .15s;margin-top:0.5rem
        }
        .submit-btn:disabled{
            opacity:.7;
            cursor:not-allowed;
            transform:none;
            box-shadow:none;
        }
        .submit-btn:hover{
            transform:translateY(-1px);
            box-shadow:0 12px 30px rgba(102,126,234,.4)
        }
        .secondary-btn{
            background:linear-gradient(135deg,#0f766e 0%,#0ea5a4 100%);
        }
        .secondary-btn:hover{
            box-shadow:0 12px 30px rgba(14,165,164,.35);
        }
        .result-card{
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            border-radius:25px;padding:2.5rem;color:#fff;text-align:center;
            box-shadow:0 20px 60px rgba(102,126,234,.3)
        }
        .result-card h2{font-size:2rem;margin-bottom:1.5rem}
        .result-grid{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));
            gap:1.25rem;margin-top:1.5rem
        }
        .result-item{
            background:rgba(255,255,255,.15);padding:1.2rem;border-radius:15px;backdrop-filter:blur(10px)
        }
        .result-item-label{font-size:.9rem;opacity:.9;margin-bottom:.4rem}
        .result-item-value{font-size:1.7rem;font-weight:800}
        .size-recommendation{
            font-size:2.6rem;font-weight:900;margin:1.5rem 0;
            text-shadow:0 4px 18px rgba(0,0,0,.35)
        }
        .info-section{
            background:#fff;border-radius:25px;padding:2.3rem;
            box-shadow:0 15px 40px rgba(0,0,0,.08);margin-top:2rem
        }
        .info-section h3{font-size:1.5rem;margin-bottom:0.8rem;color:#2c3e50}
        .info-section p{line-height:1.7;color:#555;margin-bottom:0.9rem}
        .size-chart{
            display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));
            gap:1rem;margin-top:1.2rem
        }
        .size-box{
            background:#f8f9fa;padding:1.2rem;border-radius:15px;
            text-align:center;border:2px solid #e1e4e8
        }
        .size-box-label{
            font-weight:700;font-size:1.2rem;color:#667eea;margin-bottom:.4rem
        }
        .size-box-range{font-size:.85rem;color:#666}
        .success-msg{
            background:#28a745;color:#fff;padding:0.9rem;
            border-radius:12px;margin-bottom:1.2rem;text-align:center;
            font-weight:600;font-size:0.95rem
        }
        .error-msg{
            background:#b91c1c;color:#fff;padding:0.9rem;
            border-radius:12px;margin-bottom:1.2rem;text-align:center;
            font-weight:600;font-size:0.95rem
        }
        .scan-card{
            margin-top:1.3rem;padding:1.2rem;border:2px dashed #c7d2fe;
            border-radius:16px;background:#f8faff
        }
        .scan-grid{
            display:grid;grid-template-columns:1fr 1fr;gap:1rem;align-items:start
        }
        .scan-video-wrap{
            position:relative;background:#111827;border-radius:12px;overflow:hidden;min-height:230px
        }
        .scan-video-wrap.photo-mode{
            background:transparent;
            min-height:0;
        }
        #scanVideo,#scanCanvas,#photoPreview{
            width:100%;height:auto;display:block
        }
        #photoPreview{
            display:none;
            background:transparent;
        }
        #scanCanvas{
            position:absolute;left:0;top:0;pointer-events:none
        }
        .scan-actions{display:flex;gap:.7rem;flex-wrap:wrap;margin-top:.8rem}
        .scan-btn{
            border:none;border-radius:10px;padding:.6rem 1rem;cursor:pointer;font-weight:700
        }
        .scan-btn.start{background:#1d4ed8;color:#fff}
        .scan-btn.stop{background:#374151;color:#fff}
        .scan-btn.measure{background:#0f766e;color:#fff}
        .scan-btn.capture{background:#7c3aed;color:#fff}
        .scan-btn.compute{background:#b45309;color:#fff}
        .scan-meta{font-size:.9rem;color:#4b5563}
        .scan-result{
            background:#eef2ff;border-radius:12px;padding:.9rem;margin-top:.7rem;font-size:.9rem
        }
        .scan-upload{
            margin:.8rem 0 1rem;
            padding:.9rem;
            border:1px solid #cbd5e1;
            border-radius:12px;
            background:#fff;
        }
        .scan-upload input[type="file"]{
            width:100%;
            margin-top:.45rem;
        }
        .photo-checklist{
            margin-top:.6rem;
            padding-left:1rem;
            color:#334155;
            font-size:.9rem;
            line-height:1.6;
        }
        .photo-result-card{
            display:none;
            margin-top:.85rem;
            padding:1rem;
            border-radius:12px;
            background:#ecfeff;
            border:1px solid #a5f3fc;
            color:#164e63;
        }
        .photo-result-card.active{
            display:block;
        }
        .photo-result-label{
            font-size:.8rem;
            font-weight:700;
            letter-spacing:.04em;
            text-transform:uppercase;
            color:#0f766e;
        }
        .photo-result-size{
            font-size:2rem;
            font-weight:900;
            margin:.25rem 0;
            color:#0f172a;
        }
        .photo-result-meta{
            font-size:.9rem;
            color:#155e75;
        }
        .result-modal{
            position:fixed;
            inset:0;
            z-index:1000;
            display:none;
            align-items:center;
            justify-content:center;
            padding:1.25rem;
            background:rgba(15,23,42,.68);
        }
        .result-modal.is-open{
            display:flex;
        }
        .result-modal-panel{
            width:min(760px,100%);
            max-height:90vh;
            overflow:auto;
            background:#fff;
            border-radius:18px;
            box-shadow:0 24px 80px rgba(15,23,42,.35);
            padding:1rem;
        }
        .result-modal-header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:1rem;
            margin-bottom:.75rem;
        }
        .result-modal-title{
            font-size:1rem;
            font-weight:800;
            color:#0f172a;
        }
        .result-modal-close{
            width:38px;
            height:38px;
            border:none;
            border-radius:8px;
            background:#e5e7eb;
            color:#111827;
            font-size:1.35rem;
            line-height:1;
            cursor:pointer;
        }
        .result-modal .result-card{
            margin-top:0 !important;
            border-radius:14px;
        }
        .scan-countdown{
            margin-top:.55rem;
            font-size:1.05rem;
            font-weight:800;
            color:#1d4ed8;
            min-height:1.4rem;
        }
        .scan-mode-row{
            display:flex;gap:.7rem;align-items:center;flex-wrap:wrap;margin:.75rem 0;
        }
        .scan-mode-row select{
            padding:.45rem .6rem;border-radius:8px;border:1px solid #cbd5e1;background:#fff
        }
        .scan-settings{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:.7rem;
            margin:.6rem 0 .9rem;
        }
        .scan-settings .field{
            margin:0;
        }
        .scan-settings label{
            display:block;
            font-size:.8rem;
            color:#4b5563;
            margin-bottom:.2rem;
            font-weight:600;
        }
        .scan-settings select,
        .scan-settings input[type="range"]{
            width:100%;
        }
        .scan-settings .range-val{
            font-size:.78rem;
            color:#6b7280;
        }
        @media(max-width:768px){
            .form-row{grid-template-columns:1fr}
            .section-grid{grid-template-columns:1fr}
            .calculator-card,.result-card,.info-section{padding:2rem 1.5rem}
            .inline-input .value-box{width:90px;}
            .scan-grid{grid-template-columns:1fr}
            .scan-settings{grid-template-columns:1fr}
            .profile-mode-grid{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<?php include 'navbar.php'; ?>

<section class="hero-section">
    <div class="hero-content">
        <h1>Size Calculator &amp; Size Guide</h1>
        <p>Find your perfect fit based on your body measurements</p>
    </div>
</section>

<div class="container">
    <div class="calculator-card">
        <h2 style="font-size:2rem;margin-bottom:1rem;color:#2c3e50">Size Tools</h2>
        <?php if ($calcError): ?>
            <div class="error-msg"><?php echo htmlspecialchars($calcError); ?></div>
        <?php endif; ?>
        <div class="profile-mode-card" id="calculator-profile-card">
            <strong style="display:block;color:#312e81;margin-bottom:.35rem;">Calculation Profile</strong>
            <p style="color:#64748b;font-size:.92rem;line-height:1.5;">
                Choose whether size calculations should use your saved profile or a separate person without changing your account settings.
            </p>
            <form method="POST" id="calculatorProfileForm" style="margin-top:.75rem;">
                <?php echo csrfInput(); ?>
                <input type="hidden" name="calculator_profile_action" value="save">
                <div class="profile-mode-grid">
                    <div>
                        <label for="calculator_profile_mode">Calculate for</label>
                        <select name="calculator_profile_mode" id="calculator_profile_mode">
                            <option value="self" <?php echo $activeProfileMode === 'self' ? 'selected' : ''; ?>>My Profile</option>
                            <option value="other" <?php echo $activeProfileMode === 'other' ? 'selected' : ''; ?>>Someone Else</option>
                        </select>
                    </div>
                    <div class="calculator-other-field">
                        <label for="calculator_gender">Gender</label>
                        <select name="calculator_gender" id="calculator_gender">
                            <option value="">Select gender</option>
                            <option value="Male" <?php echo $activeGender === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $activeGender === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    <div class="calculator-other-field">
                        <label for="calculator_height_cm">Height</label>
                        <div class="scan-mode-row">
                            <input type="number" step="0.1" min="0" name="calculator_height_cm" id="calculator_height_cm" value="<?php echo htmlspecialchars($activeProfileMode === 'other' && $activeHeightCm !== null ? ($activeHeightUnit === 'ft' ? number_format((float)($activeHeightCm / 30.48), 2, '.', '') : number_format((float)$activeHeightCm, 1, '.', '')) : ''); ?>">
                            <select name="calculator_height_unit" id="calculator_height_unit">
                                <option value="cm" <?php echo $activeHeightUnit === 'cm' ? 'selected' : ''; ?>>cm</option>
                                <option value="ft" <?php echo $activeHeightUnit === 'ft' ? 'selected' : ''; ?>>ft</option>
                            </select>
                        </div>
                    </div>
                    <div class="calculator-other-field">
                        <label for="calculator_weight_kg">Weight</label>
                        <div class="scan-mode-row">
                            <input type="number" step="0.1" min="0" name="calculator_weight_kg" id="calculator_weight_kg" value="<?php echo htmlspecialchars($activeProfileMode === 'other' && $activeWeightKg !== null ? ($activeWeightUnit === 'lb' ? number_format((float)($activeWeightKg * 2.2046226218), 1, '.', '') : number_format((float)$activeWeightKg, 1, '.', '')) : ''); ?>">
                            <select name="calculator_weight_unit" id="calculator_weight_unit">
                                <option value="kg" <?php echo $activeWeightUnit === 'kg' ? 'selected' : ''; ?>>kg</option>
                                <option value="lb" <?php echo $activeWeightUnit === 'lb' ? 'selected' : ''; ?>>lb</option>
                            </select>
                        </div>
                    </div>
                    <div class="calculator-other-field">
                        <label for="calculator_chest_cm">Chest (cm)</label>
                        <input type="number" step="0.1" min="0" name="calculator_chest_cm" id="calculator_chest_cm" value="<?php echo htmlspecialchars($activeProfileMode === 'other' && $activeChestCm !== null ? number_format((float)$activeChestCm, 1, '.', '') : ''); ?>">
                    </div>
                    <div class="calculator-other-field">
                        <label for="calculator_waist_cm">Waist (cm)</label>
                        <input type="number" step="0.1" min="0" name="calculator_waist_cm" id="calculator_waist_cm" value="<?php echo htmlspecialchars($activeProfileMode === 'other' && $activeWaistCm !== null ? number_format((float)$activeWaistCm, 1, '.', '') : ''); ?>">
                    </div>
                    <div class="calculator-other-field">
                        <label for="calculator_hips_cm">Hips (cm)</label>
                        <input type="number" step="0.1" min="0" name="calculator_hips_cm" id="calculator_hips_cm" value="<?php echo htmlspecialchars($activeProfileMode === 'other' && $activeHipsCm !== null ? number_format((float)$activeHipsCm, 1, '.', '') : ''); ?>">
                    </div>
                </div>
                <div class="profile-mode-actions">
                    <button type="submit" class="scan-btn compute">Save Calculation Profile</button>
                    <div class="profile-mode-note">
                        Active profile: <?php echo htmlspecialchars($activeProfileMode === 'other' ? 'Someone Else' : 'My Profile'); ?><?php echo $activeFitSize ? ' | Fit size ' . htmlspecialchars($activeFitSize) : ''; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php if ((!isLoggedIn() && $activeProfileMode === 'self') || $activeGender === '' || $activeHeightCm === null || $activeWeightKg === null): ?>
            <div class="hint" style="margin-bottom:1rem;padding:.9rem 1rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:14px;">
                <?php if ($activeProfileMode === 'other'): ?>
                    Save the other person's gender, height, and weight in the calculation profile above before using the calculator.
                <?php else: ?>
                    Save your gender and body composition in
                    <a href="settings.php" style="font-weight:700;color:#1d4ed8;">Settings</a>
                    before using the calculator. The calculator now pulls gender, height, weight, and saved measurements from your profile for better accuracy.
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="hint" style="margin-bottom:1rem;padding:.9rem 1rem;background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;">
                Using <?php echo htmlspecialchars($activeProfileLabel); ?>:
                Gender <?php echo htmlspecialchars($activeGender); ?>,
                Height <?php echo number_format((float)$activeHeightCm, 1); ?> cm,
                Weight <?php echo number_format((float)$activeWeightKg, 1); ?> kg
                <?php if ($activeChestCm !== null || $activeWaistCm !== null || $activeHipsCm !== null): ?>
                    <?php echo ', Chest ' . ($activeChestCm !== null ? number_format((float)$activeChestCm, 1) . ' cm' : '-'); ?>
                    <?php echo ', Waist ' . ($activeWaistCm !== null ? number_format((float)$activeWaistCm, 1) . ' cm' : '-'); ?>
                    <?php echo ', Hips ' . ($activeHipsCm !== null ? number_format((float)$activeHipsCm, 1) . ' cm' : '-'); ?>
                <?php endif; ?>.
                <?php if ($activeProfileMode === 'self'): ?>
                    <a href="settings.php" style="font-weight:700;color:#1d4ed8;">Update in Settings</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="hint" style="margin-bottom:1rem;padding:1rem;background:#eef2ff;border:1px solid #c7d2fe;border-radius:18px;">
            <strong style="display:block;color:#312e81;margin-bottom:.65rem;">Options Menu</strong>
            <div class="scan-actions" style="justify-content:flex-start">
                <button type="button" class="scan-btn compute tool-menu-btn" data-tool="size">Size Calculator</button>
                <button type="button" class="scan-btn capture tool-menu-btn" data-tool="live">Live Camera Scanner</button>
                <button type="button" class="scan-btn start tool-menu-btn" data-tool="upload">Upload Photo Scanner</button>
            </div>
        </div>
        <div class="section-grid">
            <div class="section-card tool-panel" id="size-calculator-section" data-tool-panel="size">
                <h2>Size Calculator</h2>
                <p style="margin-bottom:1rem;">Uses the active calculation profile above to calculate BMI and recommend a size.</p>
                <form method="POST" id="bmiForm">
                    <?php echo csrfInput(); ?>
                    <input type="hidden" name="sizing_method" value="bmi">
                    <button type="submit" class="submit-btn" id="bmiSubmitBtn">
                        Calculate Size
                    </button>
                    <div class="action-status" id="bmiActionStatus">
                        <?php echo ($result && ($result['method'] ?? '') === 'bmi') ? 'Size result calculated below.' : ''; ?>
                    </div>
                </form>
                <?php if ($result && ($result['method'] ?? '') === 'bmi'): ?>
                    <?php renderToolResultCard($result, 'Size Calculator Result', 'size'); ?>
                <?php endif; ?>
            </div>

            <div class="section-card tool-panel" id="scanner-section" data-tool-panel="scanner">
                <h2>Camera Body Scanner</h2>
                <p style="margin-bottom:1rem;">Choose either the live camera scanner or uploaded photos. Each tool has its own calculate button and result area.</p>
                    <?php if (!$localScannerEnabled && !$fitXpressEnabled): ?>
                        <div class="scan-meta" style="margin-bottom:.7rem;color:#b91c1c;font-weight:700;">Start the local AI scanner on `http://127.0.0.1:8001/upload_images` or configure a remote scanner backend.</div>
                    <?php endif; ?>
                    <div class="scan-card scanner-tool" id="upload-photo-section" data-scanner-tool="upload" style="margin-top:0;">
                        <form method="POST" id="uploadPhotoForm" class="scan-upload">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="sizing_method" value="camera">
                            <input type="hidden" name="scan_source" value="photo">
                            <input type="hidden" name="scan_chest_cm" value="">
                            <input type="hidden" name="scan_waist_cm" value="">
                            <input type="hidden" name="scan_hips_cm" value="">
                            <input type="hidden" name="scan_quality" value="">
                            <input type="hidden" name="scan_confidence_pct" value="">
                            <input type="hidden" name="scan_protocol_steps" value="2">
                            <input type="hidden" name="photo_size_estimate" value="">
                            <input type="hidden" name="photo_estimate_basis" value="">
                            <input type="hidden" id="upload_fitxpress_front_photo" name="fitxpress_front_photo" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'photo') ? ($_POST['fitxpress_front_photo'] ?? '') : $uploadPhotoFlashFront); ?>">
                            <input type="hidden" id="upload_fitxpress_side_photo" name="fitxpress_side_photo" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'photo') ? ($_POST['fitxpress_side_photo'] ?? '') : $uploadPhotoFlashSide); ?>">
                            <input type="hidden" name="fitxpress_measurement_id" value="">
                            <input type="hidden" name="scan_provider" value="Local AI Scanner">
                            <h3 style="margin-bottom:.5rem;color:#2c3e50">Upload Photo Scanner</h3>
                            <label for="photoScanFrontInput" style="display:block;font-weight:700;color:#2c3e50;">Upload Photos To Scan</label>
                            <p class="scan-meta" style="margin-top:.35rem;">
                                Upload one clear front photo and one clear side photo. Uploaded-photo scans depend on image quality and may not be as accurate as a clean live-camera capture.
                            </p>
                            <ul class="photo-checklist">
                                <li>Use one front-facing full-body photo and one full side-view photo with head, shoulders, hips, knees, and feet visible.</li>
                                <li>Use bright lighting, a plain background, and fitted clothing.</li>
                                <li>Avoid angled poses, shadows, mirrors, and cropped body parts.</li>
                            </ul>
                            <div class="scan-grid" style="margin-top:.8rem;">
                                <div>
                                    <label for="photoScanFrontInput" style="display:block;font-weight:700;color:#2c3e50;margin-bottom:.35rem;">Front Photo</label>
                                    <input type="file" id="photoScanFrontInput" accept="image/*">
                                </div>
                                <div>
                                    <label for="photoScanSideInput" style="display:block;font-weight:700;color:#2c3e50;margin-bottom:.35rem;">Side Photo</label>
                                    <input type="file" id="photoScanSideInput" accept="image/*">
                                </div>
                            </div>
                            <div class="scan-grid" style="margin-top:.85rem;">
                                <div style="position:relative;">
                                    <img id="photoFrontPreview" alt="Front photo preview" style="display:none;width:100%;border-radius:16px;background:#f8fafc;">
                                    <canvas id="photoFrontCanvas" style="display:none;position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"></canvas>
                                </div>
                                <div style="position:relative;">
                                    <img id="photoSidePreview" alt="Side photo preview" style="display:none;width:100%;border-radius:16px;background:#f8fafc;">
                                    <canvas id="photoSideCanvas" style="display:none;position:absolute;inset:0;width:100%;height:100%;pointer-events:none;"></canvas>
                                </div>
                            </div>
                            <div class="scan-actions" style="margin-top:.75rem;">
                                <button type="button" class="scan-btn stop" id="clearPhotoBtn">Clear Uploaded Photos</button>
                            </div>
                            <div class="photo-result-card" id="photoResultCard">
                                <div class="photo-result-label">Uploaded Photo Scan</div>
                                <div class="photo-result-size" id="photoResultSize">-</div>
                                <div class="photo-result-meta" id="photoResultMeta">Upload front and side photos, then click Calculate Uploaded Photo Size.</div>
                            </div>
                            <button type="submit" class="submit-btn secondary-btn" id="uploadPhotoSubmitBtn" style="margin-top:1rem;">
                                Calculate Uploaded Photo Size
                            </button>
                            <div id="uploadPhotoCalculatedResult">
                                <?php if ($uploadPhotoFlashResult && ($uploadPhotoFlashResult['method'] ?? '') === 'camera' && ($uploadPhotoFlashResult['scan_source'] ?? '') === 'photo'): ?>
                                    <?php renderToolResultCard($uploadPhotoFlashResult, 'Uploaded Photo Result', 'upload'); ?>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                    <div class="scan-card scanner-tool" id="live-camera-section" data-scanner-tool="live" style="margin-top:0;">
                        <form method="POST" id="liveCameraForm" class="scan-grid" style="margin-top:0;">
                            <?php echo csrfInput(); ?>
                            <input type="hidden" name="sizing_method" value="camera">
                            <input type="hidden" id="height_value" value="<?php echo htmlspecialchars($activeHeightCm !== null ? number_format((float)$activeHeightCm, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="height_unit" value="cm">
                            <input type="hidden" id="height_input" name="height" value="<?php echo htmlspecialchars($activeHeightCm !== null ? number_format((float)$activeHeightCm, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="weight_value" value="<?php echo htmlspecialchars($activeWeightKg !== null ? number_format((float)$activeWeightKg, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="weight_unit" value="kg">
                            <input type="hidden" id="weight_input" name="weight" value="<?php echo htmlspecialchars($activeWeightKg !== null ? number_format((float)$activeWeightKg, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="tape_chest_cm" name="tape_chest_cm" value="<?php echo htmlspecialchars($activeChestCm !== null ? number_format((float)$activeChestCm, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="tape_waist_cm" name="tape_waist_cm" value="<?php echo htmlspecialchars($activeWaistCm !== null ? number_format((float)$activeWaistCm, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="saved_hips_cm" value="<?php echo htmlspecialchars($activeHipsCm !== null ? number_format((float)$activeHipsCm, 1, '.', '') : ''); ?>">
                            <input type="hidden" id="saved_fit_size" value="<?php echo htmlspecialchars((string)($activeFitSize ?? '')); ?>">
                            <input type="hidden" id="scan_chest_cm" name="scan_chest_cm" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_chest_cm'] ?? '') : ''); ?>">
                            <input type="hidden" id="scan_waist_cm" name="scan_waist_cm" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_waist_cm'] ?? '') : ''); ?>">
                            <input type="hidden" id="scan_hips_cm" name="scan_hips_cm" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_hips_cm'] ?? '') : ''); ?>">
                            <input type="hidden" id="scan_quality" name="scan_quality" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_quality'] ?? '') : ''); ?>">
                            <input type="hidden" id="scan_confidence_pct" name="scan_confidence_pct" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_confidence_pct'] ?? '') : ''); ?>">
                            <input type="hidden" id="scan_protocol_steps" name="scan_protocol_steps" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_protocol_steps'] ?? '0') : '0'); ?>">
                            <input type="hidden" id="scan_source" name="scan_source" value="camera">
                            <input type="hidden" id="photo_size_estimate" name="photo_size_estimate" value="">
                            <input type="hidden" id="photo_estimate_basis" name="photo_estimate_basis" value="">
                            <input type="hidden" id="fitxpress_front_photo" name="fitxpress_front_photo" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['fitxpress_front_photo'] ?? '') : ''); ?>">
                            <input type="hidden" id="fitxpress_side_photo" name="fitxpress_side_photo" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['fitxpress_side_photo'] ?? '') : ''); ?>">
                            <input type="hidden" id="fitxpress_measurement_id" name="fitxpress_measurement_id" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['fitxpress_measurement_id'] ?? '') : ''); ?>">
                            <input type="hidden" id="scan_provider" name="scan_provider" value="<?php echo htmlspecialchars((($_POST['scan_source'] ?? '') === 'camera') ? ($_POST['scan_provider'] ?? '') : ''); ?>">
                            <div>
                                <h3 style="margin-bottom:.5rem;color:#2c3e50">Body Scanner Live Camera</h3>
                                <div class="scan-video-wrap">
                                    <video id="scanVideo" playsinline muted></video>
                                    <canvas id="scanCanvas"></canvas>
                                </div>
                                <div class="scan-actions">
                                    <button type="button" class="scan-btn start" id="toggleCameraBtn">Camera: Off</button>
                                    <button type="button" class="scan-btn capture" id="captureFrontBtn">Capture Front Snapshot</button>
                                    <button type="button" class="scan-btn capture" id="captureSideBtn">Capture Side Snapshot</button>
                                    <button type="button" class="scan-btn stop" id="resetProtocolBtn">Reset Camera Snapshots</button>
                                </div>
                                <div id="protocolStepText" class="scan-meta" style="margin-top:.45rem;">Capture one front snapshot and one side snapshot.</div>
                                <div class="scan-meta">After both views are ready, click Calculate Camera Size to send them to the scanner backend.</div>
                                <div class="scan-grid" style="margin-top:.85rem;">
                                    <img id="cameraFrontPreview" alt="Front camera snapshot" style="display:none;width:100%;border-radius:16px;background:#f8fafc;">
                                    <img id="cameraSidePreview" alt="Side camera snapshot" style="display:none;width:100%;border-radius:16px;background:#f8fafc;">
                                </div>
                            </div>
                            <div>
                                <div id="scanStatus" class="scan-meta">Camera is off.</div>
                                <div id="landmarkStatus" class="scan-meta">Front and side views are required.</div>
                                <div id="protocolProgress" class="scan-meta">Camera snapshots: 0 / 2</div>
                                <div id="scanCountdown" class="scan-countdown"></div>
                                <div class="scan-result">
                                    <div>Chest: <strong id="scanChestText">-</strong> cm</div>
                                    <div>Waist: <strong id="scanWaistText">-</strong> cm</div>
                                    <div>Hips: <strong id="scanHipsText">-</strong> cm</div>
                                    <div>Quality: <strong id="scanQualityText">-</strong></div>
                                    <div>Confidence: <strong id="scanConfidenceText">-</strong></div>
                                    <div style="margin-top:.4rem;">Scan Size: <strong id="scanSizeText">-</strong></div>
                                </div>
                            </div>
                            <button type="submit" class="submit-btn secondary-btn" id="liveCameraSubmitBtn" style="grid-column:1 / -1;">
                                Calculate Live Camera Size
                            </button>
                            <?php if ($result && ($result['method'] ?? '') === 'camera' && ($result['scan_source'] ?? '') === 'camera'): ?>
                                <?php renderToolResultCard($result, 'Live Camera Result', 'live'); ?>
                            <?php endif; ?>
                        </form>
                    </div>
            </div>
        </div>
    </div>
    <?php if (false && $result): ?>
        <div class="result-card" id="resultsCard">
            <h2>Your Results</h2>
            <?php if (isLoggedIn()): ?>
                <p style="opacity:.95;font-size:.95rem;margin-bottom:1rem;">
                    Manage saved body composition in
                    <a href="settings.php#saved-sizes" style="color:#fff;text-decoration:underline;">your profile settings</a>.
                </p>
            <?php endif; ?>
            <p style="opacity:.95;font-size:1rem;margin-bottom:.6rem;">
                Method:
                <strong>
                    <?php
                    $methodLabels = [
                        'bmi' => 'Size Calculator',
                        'camera' => 'Camera Body Scanner'
                    ];
                    echo htmlspecialchars($methodLabels[$result['method']] ?? 'Size Calculator');
                    ?>
                </strong>
            </p>

            <div class="size-recommendation">
                Recommended Size: <?php echo htmlspecialchars($result['size']); ?>
            </div>

            <div class="result-grid">
                <?php if (!empty($result['bmi'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">BMI</div>
                        <div class="result-item-value"><?php echo htmlspecialchars((string)$result['bmi']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['category'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Category</div>
                        <div class="result-item-value" style="font-size:1.3rem">
                            <?php echo htmlspecialchars((string)$result['category']); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['height'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Height</div>
                        <div class="result-item-value">
                            <?php echo number_format((float)$result['height'], 1); ?><span style="font-size:1rem"> cm</span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['weight'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Weight</div>
                        <div class="result-item-value">
                            <?php echo number_format((float)$result['weight'], 1); ?><span style="font-size:1rem"> kg</span>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['bmi_size'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">BMI Size</div>
                        <div class="result-item-value"><?php echo htmlspecialchars((string)$result['bmi_size']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['measurement_size'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Measurement Size</div>
                        <div class="result-item-value"><?php echo htmlspecialchars((string)$result['measurement_size']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['fit_size']) && $result['method'] === 'bmi'): ?>
                    <div class="result-item">
                        <div class="result-item-label">Fit Size</div>
                        <div class="result-item-value"><?php echo htmlspecialchars((string)$result['fit_size']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['waist_to_height_ratio']) && $result['method'] === 'bmi'): ?>
                    <div class="result-item">
                        <div class="result-item-label">Waist/Height</div>
                        <div class="result-item-value"><?php echo number_format((float)$result['waist_to_height_ratio'], 2); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['fit_label']) && $result['method'] === 'bmi'): ?>
                    <div class="result-item">
                        <div class="result-item-label">Fit Profile</div>
                        <div class="result-item-value" style="font-size:1.3rem"><?php echo htmlspecialchars((string)$result['fit_label']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['camera_size'])): ?>
                    <div class="result-item">
                        <div class="result-item-label"><?php echo (($result['scan_source'] ?? '') === 'photo') ? 'Photo Size Estimate' : 'Camera Size'; ?></div>
                        <div class="result-item-value"><?php echo htmlspecialchars((string)$result['camera_size']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['scan_quality'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Scan Quality</div>
                        <div class="result-item-value"><?php echo htmlspecialchars((string)$result['scan_quality']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['scan_source'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Scan Source</div>
                        <div class="result-item-value" style="font-size:1.15rem"><?php echo htmlspecialchars(ucfirst((string)$result['scan_source'])); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['scan_provider'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Scan Provider</div>
                        <div class="result-item-value" style="font-size:1.05rem"><?php echo htmlspecialchars((string)$result['scan_provider']); ?></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['scan_confidence_pct'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Scan Confidence</div>
                        <div class="result-item-value"><?php echo number_format((float)$result['scan_confidence_pct'], 1); ?>%</div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['scan_protocol_steps'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Protocol Steps</div>
                        <div class="result-item-value"><?php echo (int)$result['scan_protocol_steps']; ?>/8</div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['tape_chest_cm'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Saved Chest</div>
                        <div class="result-item-value"><?php echo number_format((float)$result['tape_chest_cm'], 1); ?><span style="font-size:1rem"> cm</span></div>
                    </div>
                <?php endif; ?>
                <?php if (!empty($result['tape_waist_cm'])): ?>
                    <div class="result-item">
                        <div class="result-item-label">Saved Waist</div>
                        <div class="result-item-value"><?php echo number_format((float)$result['tape_waist_cm'], 1); ?><span style="font-size:1rem"> cm</span></div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (($result['scan_source'] ?? '') === 'photo' && !empty($result['camera_size']) && empty($result['scan_provider'])): ?>
                <p style="margin-top:1rem;opacity:.95;">
                    Uploaded photo result:
                    <strong><?php echo htmlspecialchars((string)$result['camera_size']); ?></strong>
                    estimated size.
                    Photo scans do not expose chest, waist, or hips because a single image is not reliable enough for those measurements.
                </p>
            <?php elseif (($result['scan_source'] ?? '') === 'photo' && !empty($result['scan_provider']) && !empty($result['scan_chest_cm']) && !empty($result['scan_waist_cm']) && !empty($result['scan_hips_cm'])): ?>
                <p style="margin-top:1rem;opacity:.95;">
                    The scanner measured your uploaded front and side photos and returned:
                    Chest <?php echo number_format((float)$result['scan_chest_cm'], 1); ?> cm,
                    Waist <?php echo number_format((float)$result['scan_waist_cm'], 1); ?> cm,
                    Hips <?php echo number_format((float)$result['scan_hips_cm'], 1); ?> cm.
                </p>
            <?php elseif (!empty($result['scan_chest_cm']) && !empty($result['scan_waist_cm']) && !empty($result['scan_hips_cm'])): ?>
                <p style="margin-top:1rem;opacity:.95;">
                    Camera-estimated measurements:
                    Chest <?php echo number_format((float)$result['scan_chest_cm'], 1); ?> cm,
                    Waist <?php echo number_format((float)$result['scan_waist_cm'], 1); ?> cm,
                    Hips <?php echo number_format((float)$result['scan_hips_cm'], 1); ?> cm
                </p>
            <?php endif; ?>

            <p style="margin-top:1.8rem;opacity:.95;font-size:1.05rem">
                Based on your measurements, we recommend size
                <strong><?php echo htmlspecialchars($result['size']); ?></strong>
                for the best fit in our <?php echo htmlspecialchars($result['gender']); ?> collection.
            </p>
            <?php if (($result['method'] ?? '') === 'bmi' && !empty($result['fit_size']) && !empty($result['bmi_size']) && $result['fit_size'] !== $result['bmi_size']): ?>
                <p style="margin-top:1rem;opacity:.95;font-size:.98rem;">
                    BMI alone suggests <strong><?php echo htmlspecialchars((string)$result['bmi_size']); ?></strong>,
                    but your saved profile measurements refine that to
                    <strong><?php echo htmlspecialchars((string)$result['fit_size']); ?></strong>.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="info-section">
        <h3>Understanding BMI</h3>
        <p>
            BMI (Body Mass Index) is calculated by dividing your weight in kilograms by the square of your height in meters.
            It is a general indicator of body fat but does not account for muscle mass or body composition.
        </p>
        <p>
            ScanFit also calculates a separate fit recommendation using your saved waist and body measurements when available.
            That fit recommendation is intended for sizing accuracy, while BMI remains the standard height-and-weight metric.
        </p>

        <h3 style="margin-top:1.5rem">Size Reference</h3>
        <p>
            The Size Calculator now uses three references: a BMI base size, a measurement size from chest, waist, and hips,
            and a fit adjustment from waist-to-height ratio.
        </p>

        <h3 style="margin-top:1.2rem">BMI Base Size</h3>
        <div class="size-chart">
            <div class="size-box">
                <div class="size-box-label">XS</div>
                <div class="size-box-range">BMI &lt; 20</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">S</div>
                <div class="size-box-range">BMI 20 to &lt; 22</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">M</div>
                <div class="size-box-range">BMI 22 to &lt; 25</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">L</div>
                <div class="size-box-range">BMI 25 to &lt; 28</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">XL</div>
                <div class="size-box-range">BMI 28 to &lt; 30</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">XXL</div>
                <div class="size-box-range">BMI 30+</div>
            </div>
        </div>

        <p>
            Height adjustment in code: below 160 cm shifts the BMI size down one step when possible, and above 190 cm shifts it up one step.
        </p>

        <h3 style="margin-top:1.2rem">Measurement Size</h3>
        <div class="size-chart">
            <div class="size-box">
                <div class="size-box-label">XS</div>
                <div class="size-box-range">Largest saved measurement &lt; 86 cm</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">S</div>
                <div class="size-box-range">86 to &lt; 94 cm</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">M</div>
                <div class="size-box-range">94 to &lt; 102 cm</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">L</div>
                <div class="size-box-range">102 to &lt; 110 cm</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">XL</div>
                <div class="size-box-range">110 to &lt; 118 cm</div>
            </div>
            <div class="size-box">
                <div class="size-box-label">XXL</div>
                <div class="size-box-range">118 cm+</div>
            </div>
        </div>

        <h3 style="margin-top:1.2rem">Fit Profile Adjustment</h3>
        <p>
            Waist-to-height ratio under 0.43 is marked <strong>Slim</strong> and shifts fit down one size.
            0.43 to under 0.53 is <strong>Balanced</strong>.
            0.53 to under 0.60 is <strong>Relaxed</strong> and shifts fit up one size.
            0.60 and above is <strong>Fuller Fit</strong> and shifts fit up two sizes.
        </p>

        <p style="margin-top:1.5rem;font-style:italic;color:#666">
            These are general recommendations; personal fit preference and garment cuts may vary.
        </p>
    </div>
</div>

<?php if ($scanPopupResult !== null): ?>
    <div class="result-modal" id="scanResultModal" data-auto-open="1" role="dialog" aria-modal="true" aria-labelledby="scanResultModalTitle">
        <div class="result-modal-panel">
            <div class="result-modal-header">
                <div class="result-modal-title" id="scanResultModalTitle">Scan Complete</div>
                <button type="button" class="result-modal-close" id="scanResultModalClose" aria-label="Close results">&times;</button>
            </div>
            <?php renderToolResultCard($scanPopupResult, $scanPopupTitle, $scanPopupContext); ?>
        </div>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/drawing_utils/drawing_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/pose/pose.js"></script>
<script>
    function feetToCm(feet) { return feet * 30.48; }
    function cmToFeet(cm)  { return cm / 30.48; }
    function kgToLb(kg)    { return kg * 2.2046226218; }
    function lbToKg(lb)    { return lb / 2.2046226218; }
    function dist(a, b) {
        return Math.hypot(a.x - b.x, a.y - b.y);
    }
    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }
    function pickSizeFromMeasures(chest, waist, hips) {
        const score = Math.max(chest, waist, hips);
        if (score < 86) return 'XS';
        if (score < 94) return 'S';
        if (score < 102) return 'M';
        if (score < 110) return 'L';
        if (score < 118) return 'XL';
        return 'XXL';
    }
    function sizeRankJs(size) {
        return ({ XS: 1, S: 2, M: 3, L: 4, XL: 5, XXL: 6 })[size] || 3;
    }
    function sizeFromRankJs(rank) {
        return ({ 1: 'XS', 2: 'S', 3: 'M', 4: 'L', 5: 'XL', 6: 'XXL' })[rank] || 'M';
    }
    function ellipseCircumference(widthCm, depthCm) {
        const a = Math.max(1, widthCm / 2);
        const b = Math.max(1, depthCm / 2);
        const h = Math.pow(a - b, 2) / Math.pow(a + b, 2);
        return Math.PI * (a + b) * (1 + (3 * h) / (10 + Math.sqrt(4 - 3 * h)));
    }
    function blendMeasurement(rawValue, savedValue, rawWeight = 0.3) {
        const raw = Number(rawValue);
        const saved = Number(savedValue);
        if (!Number.isFinite(raw) || raw <= 0) return Number.isFinite(saved) && saved > 0 ? saved : null;
        if (!Number.isFinite(saved) || saved <= 0) return raw;
        return (raw * rawWeight) + (saved * (1 - rawWeight));
    }
    function qualityLabelFromScore(score) {
        if (score >= 0.8) return 'HIGH';
        if (score >= 0.55) return 'MEDIUM';
        return 'LOW';
    }

    document.addEventListener('DOMContentLoaded', function () {
        const bmiForm = document.getElementById('bmiForm');
        const uploadPhotoForm = document.getElementById('uploadPhotoForm');
        const liveCameraForm = document.getElementById('liveCameraForm');
        const scanResultModal = document.getElementById('scanResultModal');
        const scanResultModalClose = document.getElementById('scanResultModalClose');
        const bmiSubmitBtn = document.getElementById('bmiSubmitBtn');
        const bmiActionStatus = document.getElementById('bmiActionStatus');
        const liveCameraSubmitBtn = document.getElementById('liveCameraSubmitBtn');
        const uploadPhotoSubmitBtn = document.getElementById('uploadPhotoSubmitBtn');
        const calculatorProfileMode = document.getElementById('calculator_profile_mode');
        const calculatorOtherFields = document.querySelectorAll('.calculator-other-field');
        const calculatorGender = document.getElementById('calculator_gender');
        const calculatorHeightInput = document.getElementById('calculator_height_cm');
        const calculatorHeightUnit = document.getElementById('calculator_height_unit');
        const calculatorWeightInput = document.getElementById('calculator_weight_kg');
        const calculatorWeightUnit = document.getElementById('calculator_weight_unit');
        const calculatorChestInput = document.getElementById('calculator_chest_cm');
        const calculatorWaistInput = document.getElementById('calculator_waist_cm');
        const calculatorHipsInput = document.getElementById('calculator_hips_cm');
        const toolMenuButtons = document.querySelectorAll('.tool-menu-btn');
        const toolPanels = document.querySelectorAll('[data-tool-panel]');
        const scannerToolPanels = document.querySelectorAll('[data-scanner-tool]');
        const heightValue  = document.getElementById('height_value');
        const heightHidden = document.getElementById('height_input');
        const heightUnit   = document.getElementById('height_unit');

        const weightValue  = document.getElementById('weight_value');
        const weightHidden = document.getElementById('weight_input');
        const weightUnit   = document.getElementById('weight_unit');
        const scanChestInput = document.getElementById('scan_chest_cm');
        const scanWaistInput = document.getElementById('scan_waist_cm');
        const scanHipsInput = document.getElementById('scan_hips_cm');

        const scanVideo = document.getElementById('scanVideo');
        const scanCanvas = document.getElementById('scanCanvas');
        const scanVideoWrap = document.querySelector('.scan-video-wrap');
        const scanStatus = document.getElementById('scanStatus');
        const landmarkStatus = document.getElementById('landmarkStatus');
        const scanCountdown = document.getElementById('scanCountdown');
        const scanChestText = document.getElementById('scanChestText');
        const scanWaistText = document.getElementById('scanWaistText');
        const scanHipsText = document.getElementById('scanHipsText');
        const scanQualityText = document.getElementById('scanQualityText');
        const scanConfidenceText = document.getElementById('scanConfidenceText');
        const scanSizeText = document.getElementById('scanSizeText');
        const scanQualityInput = document.getElementById('scan_quality');
        const scanConfidenceInput = document.getElementById('scan_confidence_pct');
        const scanProtocolStepsInput = document.getElementById('scan_protocol_steps');
        const scanSourceInput = document.getElementById('scan_source');
        const photoSizeEstimateInput = document.getElementById('photo_size_estimate');
        const photoEstimateBasisInput = document.getElementById('photo_estimate_basis');
        const scanMode = document.getElementById('scanMode');
        const posePreset = document.getElementById('posePreset');
        const poseSmooth = document.getElementById('poseSmooth');
        const detectConfidence = document.getElementById('detectConfidence');
        const trackConfidence = document.getElementById('trackConfidence');
        const detectConfidenceVal = document.getElementById('detectConfidenceVal');
        const trackConfidenceVal = document.getElementById('trackConfidenceVal');
        const toggleCameraBtn = document.getElementById('toggleCameraBtn');
        const captureFrontBtn = document.getElementById('captureFrontBtn');
        const captureSideBtn = document.getElementById('captureSideBtn');
        const computeTwoViewBtn = document.getElementById('computeTwoViewBtn');
        const measureBtn = document.getElementById('measureBtn');
        const captureStepBtn = document.getElementById('captureStepBtn');
        const resetProtocolBtn = document.getElementById('resetProtocolBtn');
        const photoScanFrontInput = document.getElementById('photoScanFrontInput');
        const photoScanSideInput = document.getElementById('photoScanSideInput');
        const clearPhotoBtn = document.getElementById('clearPhotoBtn');
        const photoResultCard = document.getElementById('photoResultCard');
        const photoResultSize = document.getElementById('photoResultSize');
        const photoResultMeta = document.getElementById('photoResultMeta');
        const uploadPhotoCalculatedResult = document.getElementById('uploadPhotoCalculatedResult');
        const savedFitSizeInput = document.getElementById('saved_fit_size');
        const protocolStepText = document.getElementById('protocolStepText');
        const protocolProgress = document.getElementById('protocolProgress');
        const tapeChestInput = document.getElementById('tape_chest_cm');
        const tapeWaistInput = document.getElementById('tape_waist_cm');
        const savedHipsInput = document.getElementById('saved_hips_cm');
        const uploadFitXpressFrontPhotoInput = document.getElementById('upload_fitxpress_front_photo');
        const uploadFitXpressSidePhotoInput = document.getElementById('upload_fitxpress_side_photo');
        const fitXpressFrontPhotoInput = document.getElementById('fitxpress_front_photo');
        const fitXpressSidePhotoInput = document.getElementById('fitxpress_side_photo');
        const fitXpressMeasurementIdInput = document.getElementById('fitxpress_measurement_id');
        const scanProviderInput = document.getElementById('scan_provider');
        const photoFrontPreview = document.getElementById('photoFrontPreview');
        const photoSidePreview = document.getElementById('photoSidePreview');
        const photoFrontCanvas = document.getElementById('photoFrontCanvas');
        const photoSideCanvas = document.getElementById('photoSideCanvas');
        const cameraFrontPreview = document.getElementById('cameraFrontPreview');
        const cameraSidePreview = document.getElementById('cameraSidePreview');

        function openScanResultModal() {
            if (!scanResultModal) return;
            scanResultModal.classList.add('is-open');
            document.body.style.overflow = 'hidden';
            if (scanResultModalClose) {
                scanResultModalClose.focus();
            }
        }
        function closeScanResultModal() {
            if (!scanResultModal) return;
            scanResultModal.classList.remove('is-open');
            document.body.style.overflow = '';
        }
        if (scanResultModalClose) {
            scanResultModalClose.addEventListener('click', closeScanResultModal);
        }
        if (scanResultModal) {
            scanResultModal.addEventListener('click', function (e) {
                if (e.target === scanResultModal) {
                    closeScanResultModal();
                }
            });
        }
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && scanResultModal && scanResultModal.classList.contains('is-open')) {
                closeScanResultModal();
            }
        });
        if (scanResultModal && scanResultModal.dataset.autoOpen === '1') {
            openScanResultModal();
        }

        // Restore previous values (cm/kg) after POST
        if (heightHidden.value) {
            heightValue.value = heightHidden.value;
            heightUnit.value  = 'cm';
        }
        if (weightHidden.value) {
            weightValue.value = weightHidden.value;
            weightUnit.value  = 'kg';
        }
        if (scanChestInput.value) scanChestText.textContent = Number(scanChestInput.value).toFixed(1);
        if (scanWaistInput.value) scanWaistText.textContent = Number(scanWaistInput.value).toFixed(1);
        if (scanHipsInput.value) scanHipsText.textContent = Number(scanHipsInput.value).toFixed(1);
        if (scanChestInput.value && scanWaistInput.value && scanHipsInput.value) {
            scanSizeText.textContent = pickSizeFromMeasures(
                Number(scanChestInput.value),
                Number(scanWaistInput.value),
                Number(scanHipsInput.value)
            );
        }
        if (scanQualityInput.value) {
            scanQualityText.textContent = scanQualityInput.value;
        }
        if (scanConfidenceInput && scanConfidenceInput.value && scanConfidenceText) {
            scanConfidenceText.textContent = `${Number(scanConfidenceInput.value).toFixed(1)}%`;
        }
        if (photoSizeEstimateInput && scanSourceInput && scanSourceInput.value !== 'photo' && photoSizeEstimateInput.value) {
            photoSizeEstimateInput.value = '';
        }
        if (photoResultCard && photoResultSize && photoResultMeta) {
            if (photoSizeEstimateInput && photoSizeEstimateInput.value && scanSourceInput && scanSourceInput.value === 'photo') {
                photoResultCard.classList.add('active');
                photoResultSize.textContent = photoSizeEstimateInput.value;
                photoResultMeta.textContent = scanConfidenceInput && scanConfidenceInput.value
                    ? `Confidence ${Number(scanConfidenceInput.value).toFixed(1)}%. Conservative photo estimate only.`
                    : 'Conservative photo estimate only.';
            } else {
                photoResultCard.classList.remove('active');
            }
        }
        if (uploadFitXpressFrontPhotoInput && uploadFitXpressFrontPhotoInput.value && photoFrontPreview) {
            photoFrontPreview.src = uploadFitXpressFrontPhotoInput.value;
            photoFrontPreview.style.display = 'block';
        }
        if (uploadFitXpressSidePhotoInput && uploadFitXpressSidePhotoInput.value && photoSidePreview) {
            photoSidePreview.src = uploadFitXpressSidePhotoInput.value;
            photoSidePreview.style.display = 'block';
        }
        const hasUploadPhotoCalculatedResult = <?php echo json_encode(
            is_array($uploadPhotoFlashResult)
            && (($uploadPhotoFlashResult['method'] ?? '') === 'camera')
            && (($uploadPhotoFlashResult['scan_source'] ?? '') === 'photo')
        ); ?>;
        const navEntry = (performance.getEntriesByType && performance.getEntriesByType('navigation')[0]) || null;
        const isPageReload = !!(navEntry && navEntry.type === 'reload');
        if (!hasUploadPhotoCalculatedResult && uploadPhotoCalculatedResult) {
            uploadPhotoCalculatedResult.innerHTML = '';
        }

        function showSelectedTool(tool) {
            toolPanels.forEach((panel) => panel.classList.remove('is-active'));
            scannerToolPanels.forEach((panel) => panel.classList.remove('is-active'));

            if (tool === 'size') {
                const sizePanel = document.querySelector('[data-tool-panel="size"]');
                if (sizePanel) sizePanel.classList.add('is-active');
                return;
            }

            const scannerPanel = document.querySelector('[data-tool-panel="scanner"]');
            if (scannerPanel) scannerPanel.classList.add('is-active');

            if (tool === 'live' || tool === 'upload') {
                const activeScannerTool = document.querySelector(`[data-scanner-tool="${tool}"]`);
                if (activeScannerTool) activeScannerTool.classList.add('is-active');
            }
        }

        toolMenuButtons.forEach((btn) => {
            btn.addEventListener('click', function () {
                const tool = this.getAttribute('data-tool');
                if (!tool) return;
                showSelectedTool(tool);
            });
        });

        let initialTool = '';
        if (window.location.hash === '#size-calculator-section') initialTool = 'size';
        if (window.location.hash === '#live-camera-section') initialTool = 'live';
        if (window.location.hash === '#upload-photo-section') initialTool = 'upload';
        if (!initialTool && <?php echo json_encode($result ? (string)($result['method'] ?? '') : ''); ?> === 'bmi') initialTool = 'size';
        if (!initialTool && <?php echo json_encode($result ? (string)($result['scan_source'] ?? '') : ''); ?> === 'camera') initialTool = 'live';
        if (!initialTool && <?php echo json_encode($result ? (string)($result['scan_source'] ?? '') : ''); ?> === 'photo') initialTool = 'upload';
        if (initialTool) {
            showSelectedTool(initialTool);
        }

        if (window.location.hash) {
            const target = document.querySelector(window.location.hash);
            if (target && initialTool) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        function syncCalculatorProfileFields() {
            if (!calculatorProfileMode) return;
            const showOther = calculatorProfileMode.value === 'other';
            calculatorOtherFields.forEach((field) => {
                field.style.display = showOther ? 'block' : 'none';
            });
        }
        function setHiddenValue(form, name, value) {
            let input = form.querySelector(`input[type="hidden"][name="${name}"]`);
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                form.appendChild(input);
            }
            input.value = value || '';
        }
        function attachCalculatorProfile(form) {
            if (!form || !calculatorProfileMode) return;
            setHiddenValue(form, 'calculator_profile_mode', calculatorProfileMode.value);
            setHiddenValue(form, 'calculator_gender', calculatorGender ? calculatorGender.value : '');
            setHiddenValue(form, 'calculator_height_cm', calculatorHeightInput ? calculatorHeightInput.value : '');
            setHiddenValue(form, 'calculator_height_unit', calculatorHeightUnit ? calculatorHeightUnit.value : 'cm');
            setHiddenValue(form, 'calculator_weight_kg', calculatorWeightInput ? calculatorWeightInput.value : '');
            setHiddenValue(form, 'calculator_weight_unit', calculatorWeightUnit ? calculatorWeightUnit.value : 'kg');
            setHiddenValue(form, 'calculator_chest_cm', calculatorChestInput ? calculatorChestInput.value : '');
            setHiddenValue(form, 'calculator_waist_cm', calculatorWaistInput ? calculatorWaistInput.value : '');
            setHiddenValue(form, 'calculator_hips_cm', calculatorHipsInput ? calculatorHipsInput.value : '');
            if (calculatorProfileMode.value === 'other' && calculatorHeightInput && calculatorWeightInput) {
                const rawHeight = parseFloat(calculatorHeightInput.value);
                const rawWeight = parseFloat(calculatorWeightInput.value);
                if (!isNaN(rawHeight) && rawHeight > 0 && heightValue && heightHidden) {
                    const heightCm = calculatorHeightUnit && calculatorHeightUnit.value === 'ft' ? feetToCm(rawHeight) : rawHeight;
                    heightValue.value = heightCm.toFixed(1);
                    heightHidden.value = heightCm.toFixed(1);
                }
                if (!isNaN(rawWeight) && rawWeight > 0 && weightValue && weightHidden) {
                    const weightKg = calculatorWeightUnit && calculatorWeightUnit.value === 'lb' ? lbToKg(rawWeight) : rawWeight;
                    weightValue.value = weightKg.toFixed(1);
                    weightHidden.value = weightKg.toFixed(1);
                }
            }
        }
        if (calculatorHeightUnit && calculatorHeightInput) {
            calculatorHeightUnit.addEventListener('change', function () {
                const v = parseFloat(calculatorHeightInput.value);
                if (isNaN(v) || v <= 0) return;
                if (calculatorHeightUnit.value === 'ft') {
                    calculatorHeightInput.value = cmToFeet(v).toFixed(2);
                } else {
                    calculatorHeightInput.value = feetToCm(v).toFixed(1);
                }
            });
        }
        if (calculatorWeightUnit && calculatorWeightInput) {
            calculatorWeightUnit.addEventListener('change', function () {
                const v = parseFloat(calculatorWeightInput.value);
                if (isNaN(v) || v <= 0) return;
                if (calculatorWeightUnit.value === 'lb') {
                    calculatorWeightInput.value = kgToLb(v).toFixed(1);
                } else {
                    calculatorWeightInput.value = lbToKg(v).toFixed(1);
                }
            });
        }
        syncCalculatorProfileFields();
        if (calculatorProfileMode) {
            calculatorProfileMode.addEventListener('change', syncCalculatorProfileFields);
        }
        if (bmiForm) {
            bmiForm.addEventListener('submit', function (e) {
                attachCalculatorProfile(bmiForm);
                const bmiReady = !isNaN(parseFloat(heightValue.value)) && parseFloat(heightValue.value) > 0 &&
                    !isNaN(parseFloat(weightValue.value)) && parseFloat(weightValue.value) > 0;
                if (!bmiReady) {
                    if (bmiActionStatus) {
                        bmiActionStatus.textContent = 'Save gender, height, and weight in Settings first.';
                    }
                }
                if (bmiSubmitBtn) {
                    bmiSubmitBtn.textContent = 'Calculating BMI...';
                    bmiSubmitBtn.disabled = true;
                }
                if (bmiActionStatus) {
                    bmiActionStatus.textContent = 'Calculating and moving to your result...';
                }
                const currentAction = bmiForm.getAttribute('action');
                bmiForm.setAttribute('action', currentAction ? `${currentAction}#size-calculator-section` : '#size-calculator-section');
            });
        }

        if (uploadPhotoSubmitBtn) {
            uploadPhotoSubmitBtn.addEventListener('click', function () {
                const formEl = uploadPhotoForm;
                if (formEl) {
                    const currentAction = formEl.getAttribute('action');
                    formEl.setAttribute('action', currentAction ? `${currentAction}#upload-photo-section` : '#upload-photo-section');
                }
            });
        }

        if (uploadPhotoForm) {
            uploadPhotoForm.addEventListener('submit', async function (e) {
                attachCalculatorProfile(uploadPhotoForm);
                const hasFront = !!(uploadFitXpressFrontPhotoInput && uploadFitXpressFrontPhotoInput.value);
                const hasSide = !!(uploadFitXpressSidePhotoInput && uploadFitXpressSidePhotoInput.value);
                if (!hasFront || !hasSide) {
                    e.preventDefault();
                    if (photoResultCard && photoResultSize && photoResultMeta) {
                        photoResultCard.classList.add('active');
                        photoResultSize.textContent = '-';
                        photoResultMeta.textContent = 'Both front and side uploaded photos are required.';
                    }
                    alert('Please upload both a front photo and a side photo before calculating.');
                    return;
                }

                currentScanSource = 'photo';
                if (scanSourceInput) scanSourceInput.value = 'photo';
                if (scanProviderInput) scanProviderInput.value = 'Local AI Scanner';
                if (photoSizeEstimateInput) photoSizeEstimateInput.value = '';
                if (photoEstimateBasisInput) photoEstimateBasisInput.value = '';
                if (scanQualityInput) scanQualityInput.value = '';
                if (scanConfidenceInput) scanConfidenceInput.value = '';
                if (uploadPhotoSubmitBtn) {
                    uploadPhotoSubmitBtn.textContent = 'Calculating Uploaded Photo Size...';
                    uploadPhotoSubmitBtn.disabled = true;
                }
                if (photoResultCard && photoResultSize && photoResultMeta) {
                    photoResultCard.classList.add('active');
                    photoResultSize.textContent = 'Scanning';
                    photoResultMeta.textContent = 'Validating photos and drawing landmarks before calculation.';
                }
                if (photoFrontPreview && photoFrontPreview.src && photoFrontCanvas) {
                    await drawThumbnailLandmarks(photoFrontPreview, photoFrontCanvas);
                }
                if (photoSidePreview && photoSidePreview.src && photoSideCanvas) {
                    await drawThumbnailLandmarks(photoSidePreview, photoSideCanvas);
                }
                if (photoResultCard && photoResultSize && photoResultMeta) {
                    photoResultSize.textContent = 'Calculating';
                    photoResultMeta.textContent = 'Landmarks ready. Sending uploaded photos to the local AI scanner.';
                }
            });
        }

        if (liveCameraSubmitBtn) {
            liveCameraSubmitBtn.addEventListener('click', function () {
                if (scanSourceInput) scanSourceInput.value = 'camera';
                if (scanProviderInput) scanProviderInput.value = 'Local AI Scanner';
                const formEl = liveCameraForm;
                if (formEl) {
                    const currentAction = formEl.getAttribute('action');
                    formEl.setAttribute('action', currentAction ? `${currentAction}#live-camera-section` : '#live-camera-section');
                }
            });
        }

        if (scanMode) {
            scanMode.addEventListener('change', function () {
                autoHighQualityFrames = 0;
                if (autoCountdownActive) {
                    clearInterval(autoCountdownTimer);
                    autoCountdownActive = false;
                    autoCountdownTimer = null;
                    autoCountdownValue = 0;
                    if (scanCountdown) scanCountdown.textContent = '';
                }
                if (scanMode.value === 'auto') {
                    scanStatus.textContent = 'Auto mode enabled. Hold steady until quality is HIGH.';
                } else {
                    scanStatus.textContent = 'Manual mode enabled. Use capture/measure buttons.';
                }
            });
        }

        function cancelAutoCountdown(message) {
            if (!autoCountdownActive) return;
            clearInterval(autoCountdownTimer);
            autoCountdownActive = false;
            autoCountdownTimer = null;
            autoCountdownValue = 0;
            if (scanCountdown) scanCountdown.textContent = '';
            if (message) scanStatus.textContent = message;
        }

        function startAutoCountdown() {
            if (autoCountdownActive) return;
            autoCountdownActive = true;
            autoCountdownValue = 3;
            if (scanCountdown) scanCountdown.textContent = `Auto capture in ${autoCountdownValue}...`;

            autoCountdownTimer = setInterval(function () {
                autoCountdownValue -= 1;
                if (autoCountdownValue > 0) {
                    if (scanCountdown) scanCountdown.textContent = `Auto capture in ${autoCountdownValue}...`;
                    return;
                }

                clearInterval(autoCountdownTimer);
                autoCountdownTimer = null;
                autoCountdownActive = false;
                if (scanCountdown) scanCountdown.textContent = '';

                const capture = captureCurrentView('auto-final');
                if (!capture || capture.qualityScore < 0.8) {
                    scanStatus.textContent = 'Auto capture canceled due to quality drop. Hold still and try again.';
                    autoHighQualityFrames = 0;
                    return;
                }

                const chestCm = ellipseCircumference(capture.shoulderWidthCm * 1.12, capture.torsoDepthProxyCm * 1.08);
                const waistCm = ellipseCircumference(
                    ((capture.shoulderWidthCm * 0.42) + (capture.hipWidthCm * 0.58)),
                    capture.torsoDepthProxyCm * 0.92
                );
                const hipsCm = ellipseCircumference(
                    capture.hipWidthCm * 1.15,
                    capture.torsoDepthProxyCm * 1.05
                );
                applyFinalMeasurements(
                    chestCm,
                    waistCm,
                    hipsCm,
                    'HIGH',
                    'Auto scan complete after countdown. Submit form to apply recommendation.'
                );
                autoLastScanAt = Date.now();
                autoHighQualityFrames = 0;
            }, 1000);
        }

        heightUnit.addEventListener('change', function () {
            const v = parseFloat(heightValue.value);
            if (isNaN(v) || v <= 0) return;
            if (heightUnit.value === 'ft') {
                heightValue.value = cmToFeet(v).toFixed(2);
            } else {
                heightValue.value = feetToCm(v).toFixed(1);
            }
        });

        weightUnit.addEventListener('change', function () {
            const v = parseFloat(weightValue.value);
            if (isNaN(v) || v <= 0) return;
            if (weightUnit.value === 'lb') {
                weightValue.value = kgToLb(v).toFixed(1);
            } else {
                weightValue.value = lbToKg(v).toFixed(1);
            }
        });

        if (liveCameraForm) {
            liveCameraForm.addEventListener('submit', function (e) {
                attachCalculatorProfile(liveCameraForm);
                const hv = parseFloat(heightValue.value);
                const wv = parseFloat(weightValue.value);
                const hasCameraValues = Number(scanChestInput.value) > 0 && Number(scanWaistInput.value) > 0 && Number(scanHipsInput.value) > 0;
                const quality = (scanQualityInput && scanQualityInput.value) ? String(scanQualityInput.value).toUpperCase() : '';
                const protocolSteps = scanProtocolStepsInput ? Number(scanProtocolStepsInput.value || 0) : 0;

                if (!isNaN(hv) && hv > 0) {
                    heightHidden.value =
                        (heightUnit.value === 'ft' ? feetToCm(hv) : hv).toFixed(1);
                }
                if (!isNaN(wv) && wv > 0) {
                    weightHidden.value =
                        (weightUnit.value === 'lb' ? lbToKg(wv) : wv).toFixed(1);
                }

                if (isNaN(hv) || hv <= 0 || isNaN(wv) || wv <= 0) {
                    e.preventDefault();
                    alert('Save valid height and weight in Settings before using the scanner.');
                    return;
                }
                if (!hasCameraValues) {
                    e.preventDefault();
                    if (scanStatus) {
                        scanStatus.textContent = 'Capture front and side camera snapshots first, then submit.';
                    }
                    alert('Please capture front and side camera snapshots first.');
                    return;
                }
                if ((scanSourceInput ? scanSourceInput.value : '') !== 'photo' && protocolSteps < PROTOCOL_STEPS.length) {
                    e.preventDefault();
                    if (scanStatus) {
                        scanStatus.textContent = `Complete all ${PROTOCOL_STEPS.length} protocol steps before submitting.`;
                    }
                    alert(`Camera-based sizing requires full ${PROTOCOL_STEPS.length}-step protocol capture for precision.`);
                    return;
                }
                if ((scanSourceInput ? scanSourceInput.value : '') !== 'photo' && !(quality === 'MEDIUM' || quality === 'HIGH')) {
                    e.preventDefault();
                    if (scanStatus) {
                        scanStatus.textContent = 'Scan quality is too low. Re-scan until quality is MEDIUM or HIGH.';
                    }
                    alert('For accurate sizing, camera-based methods require MEDIUM or HIGH scan quality.');
                }
            });
        }

        let latestLandmarks = null;
        let cameraStarted = false;
        let currentScanSource = scanSourceInput ? String(scanSourceInput.value || '') : '';
        let mpCamera = null;
        let frontCapture = null;
        let sideCapture = null;
        let autoHighQualityFrames = 0;
        let autoLastScanAt = 0;
        let autoCountdownActive = false;
        let autoCountdownTimer = null;
        let autoCountdownValue = 0;
        let pose = null;
        let pendingPoseResultResolver = null;
        let stablePoseFrames = 0;
        let lastStablePoints = null;
        let measurementInProgress = false;
        let protocolStepIndex = 0;
        let protocolCaptures = [];
        let statusHoldUntil = 0;

        const REQUIRED_STABLE_FRAMES = 12;
        const REQUIRED_SAMPLE_COUNT = 3;
        const PROTOCOL_STEPS = [
            { id: 'front_neutral', label: 'Front neutral', view: 'front', orientation: 'front' },
            { id: 'front_arms_out', label: 'Front arms-out', view: 'front', orientation: 'front' },
            { id: 'left_45', label: 'Left 45 deg', view: 'front45', orientation: 'angled' },
            { id: 'right_45', label: 'Right 45 deg', view: 'front45', orientation: 'angled' },
            { id: 'left_side', label: 'Left side 90 deg', view: 'side', orientation: 'side' },
            { id: 'right_side', label: 'Right side 90 deg', view: 'side', orientation: 'side' },
            { id: 'back_neutral', label: 'Back neutral', view: 'back', orientation: 'front' },
            { id: 'front_confirm', label: 'Front confirm', view: 'front', orientation: 'front' }
        ];

        function waitMs(ms) {
            return new Promise((resolve) => setTimeout(resolve, ms));
        }

        function setScanStatus(message, holdMs = 0) {
            if (scanStatus) {
                scanStatus.textContent = message;
            }
            statusHoldUntil = holdMs > 0 ? (Date.now() + holdMs) : 0;
        }

        function corePosePoints(lm) {
            const idx = [0, 11, 12, 23, 24, 27, 28];
            const points = [];
            for (const i of idx) {
                const p = lm[i];
                if (!p || (p.visibility ?? 1) < 0.35) return null;
                points.push({ x: p.x, y: p.y });
            }
            return points;
        }

        function updatePoseStability(lm) {
            const points = corePosePoints(lm);
            if (!points) {
                stablePoseFrames = 0;
                lastStablePoints = null;
                return;
            }
            if (!lastStablePoints) {
                lastStablePoints = points;
                stablePoseFrames = 1;
                return;
            }

            let delta = 0;
            for (let i = 0; i < points.length; i += 1) {
                delta += Math.hypot(points[i].x - lastStablePoints[i].x, points[i].y - lastStablePoints[i].y);
            }
            delta /= points.length;

            if (delta < 0.014) {
                stablePoseFrames += 1;
            } else {
                stablePoseFrames = 0;
            }
            lastStablePoints = points;
        }

        function averageCapture(captures, viewName) {
            const fields = ['shoulderWidthCm', 'hipWidthCm', 'kneeWidthCm', 'elbowWidthCm', 'torsoDepthProxyCm', 'chestDepthCm', 'waistDepthCm', 'hipDepthCm', 'qualityScore'];
            const avg = { view: viewName };
            fields.forEach((field) => {
                avg[field] = captures.reduce((sum, c) => sum + c[field], 0) / captures.length;
            });
            return avg;
        }

        async function collectAveragedCapture(viewName, sampleCount, minQualityScore = 0.55, requireStrictQuality = true) {
            let stableWaitMs = 0;
            while (stablePoseFrames < REQUIRED_STABLE_FRAMES && stableWaitMs < 5000) {
                await waitMs(120);
                stableWaitMs += 120;
            }
            if (stablePoseFrames < REQUIRED_STABLE_FRAMES) {
                return { error: 'Could not get a stable pose. Hold still and try again.' };
            }

            const captures = [];
            let highCount = 0;
            let mediumCount = 0;
            let attempts = 0;

            while (captures.length < sampleCount && attempts < 18) {
                attempts += 1;
                const capture = captureCurrentView(viewName);
                if (capture) {
                    const q = qualityLabelFromScore(capture.qualityScore);
                    if (capture.qualityScore >= minQualityScore) {
                        captures.push(capture);
                        if (q === 'HIGH') highCount += 1;
                        if (q === 'MEDIUM') mediumCount += 1;
                    }
                }
                if (captures.length < sampleCount) {
                    await waitMs(220);
                }
            }

            if (captures.length < sampleCount) {
                return { error: 'Could not collect enough quality frames. Improve lighting and hold still.' };
            }
            if (requireStrictQuality && highCount < 1 && mediumCount < sampleCount) {
                return { error: 'Quality too low. Need HIGH quality or repeated MEDIUM captures.' };
            }

            return {
                capture: averageCapture(captures, viewName),
                qualityLabel: highCount > 0 ? 'HIGH' : 'MEDIUM'
            };
        }

        function orientationMatches(capture, expected) {
            const span = capture.shoulderSpanNorm ?? 0;
            if (expected === 'side') return span <= 0.18;
            if (expected === 'front') return span >= 0.14;
            return span >= 0.12 && span <= 0.24;
        }

        function mean(values) {
            if (!values.length) return 0;
            return values.reduce((s, v) => s + v, 0) / values.length;
        }

        function trimmedMean(values) {
            if (!values.length) return 0;
            const sorted = [...values].sort((a, b) => a - b);
            if (sorted.length <= 2) return mean(sorted);
            return mean(sorted.slice(1, sorted.length - 1));
        }

        function readFileAsDataUrl(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = (evt) => resolve(String(evt.target?.result || ''));
                reader.onerror = () => reject(new Error('Could not read image file.'));
                reader.readAsDataURL(file);
            });
        }

        function setImagePreview(target, dataUrl) {
            if (!target) return;
            if (dataUrl) {
                target.src = dataUrl;
                target.style.display = 'block';
            } else {
                target.removeAttribute('src');
                target.style.display = 'none';
            }
        }

        async function drawThumbnailLandmarks(imageEl, canvasEl) {
            if (!imageEl || !canvasEl || !window.Pose || !imageEl.src) return;

            canvasEl.style.display = 'block';
            canvasEl.width = imageEl.naturalWidth || imageEl.clientWidth || 640;
            canvasEl.height = imageEl.naturalHeight || imageEl.clientHeight || 480;
            const ctx = canvasEl.getContext('2d');
            ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);

            const localPose = new Pose({
                locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
            });
            localPose.setOptions({
                modelComplexity: 1,
                smoothLandmarks: true,
                enableSegmentation: false,
                minDetectionConfidence: 0.5,
                minTrackingConfidence: 0.5
            });

            await new Promise((resolve) => {
                localPose.onResults((results) => {
                    ctx.clearRect(0, 0, canvasEl.width, canvasEl.height);
                    if (results.poseLandmarks) {
                        drawConnectors(ctx, results.poseLandmarks, POSE_CONNECTIONS, { color: '#93c5fd', lineWidth: 2 });
                        drawLandmarks(ctx, results.poseLandmarks, { color: '#1d4ed8', lineWidth: 1, radius: 2 });
                    }
                    resolve();
                });
                localPose.send({ image: imageEl }).catch(() => resolve());
            });
        }

        function clearThumbnailLandmarks(canvasEl) {
            if (!canvasEl) return;
            const ctx = canvasEl.getContext('2d');
            ctx.clearRect(0, 0, canvasEl.width || 1, canvasEl.height || 1);
            canvasEl.style.display = 'none';
        }

        function snapshotCount() {
            let count = 0;
            if (fitXpressFrontPhotoInput && fitXpressFrontPhotoInput.value) count += 1;
            if (fitXpressSidePhotoInput && fitXpressSidePhotoInput.value) count += 1;
            return count;
        }

        function updateProtocolUi() {
            const count = snapshotCount();
            if (!captureStepBtn && !measureBtn) {
                if (scanProtocolStepsInput) {
                    scanProtocolStepsInput.value = String(count);
                }
                if (protocolProgress) {
                    protocolProgress.textContent = `Camera snapshots: ${count} / 2`;
                }
                if (protocolStepText) {
                    if (count < 2) {
                        protocolStepText.textContent = count === 0
                            ? 'Capture one front snapshot and one side snapshot.'
                            : 'Capture the remaining camera view, then calculate.';
                    } else {
                        protocolStepText.textContent = 'Front and side camera snapshots are ready to calculate.';
                    }
                }
                return;
            }

            if (scanProtocolStepsInput) {
                scanProtocolStepsInput.value = String(protocolCaptures.length);
            }
            if (protocolProgress) {
                protocolProgress.textContent = `Protocol progress: ${protocolCaptures.length} / ${PROTOCOL_STEPS.length}`;
            }
            if (protocolStepText) {
                if (protocolStepIndex < PROTOCOL_STEPS.length) {
                    const step = PROTOCOL_STEPS[protocolStepIndex];
                    protocolStepText.textContent = `Step ${protocolStepIndex + 1}/${PROTOCOL_STEPS.length}: ${step.label}.`;
                } else {
                    protocolStepText.textContent = 'All protocol steps captured. Click "Compute Precision Scan".';
                }
            }
        }

        function resetProtocolState() {
            protocolStepIndex = 0;
            protocolCaptures = [];
            frontCapture = null;
            sideCapture = null;
            if (fitXpressFrontPhotoInput) fitXpressFrontPhotoInput.value = '';
            if (fitXpressSidePhotoInput) fitXpressSidePhotoInput.value = '';
            if (fitXpressMeasurementIdInput) fitXpressMeasurementIdInput.value = '';
            if (scanProviderInput) scanProviderInput.value = '';
            setImagePreview(cameraFrontPreview, '');
            setImagePreview(cameraSidePreview, '');
            if (scanConfidenceText) scanConfidenceText.textContent = '-';
            if (scanConfidenceInput) scanConfidenceInput.value = '';
            updateProtocolUi();
        }

        function clearPhotoPreviewState(resetFileInput = false) {
            if (scanVideoWrap) {
                scanVideoWrap.classList.remove('photo-mode');
            }
            if (resetFileInput && photoScanFrontInput) photoScanFrontInput.value = '';
            if (resetFileInput && photoScanSideInput) photoScanSideInput.value = '';
            if (uploadFitXpressFrontPhotoInput) uploadFitXpressFrontPhotoInput.value = '';
            if (uploadFitXpressSidePhotoInput) uploadFitXpressSidePhotoInput.value = '';
            if (fitXpressMeasurementIdInput) fitXpressMeasurementIdInput.value = '';
            if (scanProviderInput && scanSourceInput && scanSourceInput.value === 'photo') scanProviderInput.value = '';
            setImagePreview(photoFrontPreview, '');
            setImagePreview(photoSidePreview, '');
            if (photoFrontCanvas) {
                clearThumbnailLandmarks(photoFrontCanvas);
            }
            if (photoSideCanvas) {
                clearThumbnailLandmarks(photoSideCanvas);
            }
            if (scanSourceInput && scanSourceInput.value === 'photo') {
                scanSourceInput.value = '';
            }
            if (photoSizeEstimateInput) {
                photoSizeEstimateInput.value = '';
            }
            if (photoEstimateBasisInput) {
                photoEstimateBasisInput.value = '';
            }
            if (photoResultCard && photoResultSize && photoResultMeta) {
                photoResultCard.classList.remove('active');
                photoResultSize.textContent = '-';
                photoResultMeta.textContent = 'Upload a photo and run the scan.';
            }
            if (uploadPhotoCalculatedResult) {
                uploadPhotoCalculatedResult.innerHTML = '';
            }
            if (scanSizeText) {
                scanSizeText.textContent = '-';
            }
            if (scanQualityText) {
                scanQualityText.textContent = '-';
            }
            if (scanConfidenceText) {
                scanConfidenceText.textContent = '-';
            }
            currentScanSource = cameraStarted ? 'camera' : '';
        }

        window.addEventListener('pageshow', function () {
            if (!hasUploadPhotoCalculatedResult) {
                clearPhotoPreviewState(false);
            }
        });
        if (isPageReload) {
            clearPhotoPreviewState(true);
        }

        function computeProtocolMeasurements() {
            if (protocolCaptures.length < PROTOCOL_STEPS.length) {
                return { error: 'Complete all 8 protocol steps first.' };
            }

            const frontish = protocolCaptures.filter((c) => c.step.orientation !== 'side').map((c) => c.capture);
            const sideish = protocolCaptures.filter((c) => c.step.orientation === 'side').map((c) => c.capture);
            if (!frontish.length || !sideish.length) {
                return { error: 'Protocol data incomplete. Please reset and capture again.' };
            }

            const frontShoulder = trimmedMean(frontish.map((c) => c.shoulderWidthCm));
            const frontHip = trimmedMean(frontish.map((c) => c.hipWidthCm));
            const sideChestDepth = trimmedMean(sideish.map((c) => c.chestDepthCm));
            const sideWaistDepth = trimmedMean(sideish.map((c) => c.waistDepthCm));
            const sideHipDepth = trimmedMean(sideish.map((c) => c.hipDepthCm));

            let chestCm = ellipseCircumference(frontShoulder * 1.18, sideChestDepth * 1.02);
            let waistCm = ellipseCircumference(((frontShoulder + frontHip) / 2) * 0.92, sideWaistDepth);
            let hipsCm = ellipseCircumference(frontHip * 1.20, sideHipDepth);

            const rawChest = chestCm;
            const rawWaist = waistCm;
            const tapeChest = tapeChestInput ? parseFloat(tapeChestInput.value) : NaN;
            const tapeWaist = tapeWaistInput ? parseFloat(tapeWaistInput.value) : NaN;
            const savedHips = savedHipsInput ? parseFloat(savedHipsInput.value) : NaN;

            let chestFactor = 1;
            let waistFactor = 1;
            let hipFactor = 1;
            if (!isNaN(tapeChest) && tapeChest > 40 && rawChest > 0) {
                chestFactor = clamp(tapeChest / rawChest, 0.85, 1.15);
            }
            if (!isNaN(tapeWaist) && tapeWaist > 40 && rawWaist > 0) {
                waistFactor = clamp(tapeWaist / rawWaist, 0.85, 1.15);
            }
            if (!isNaN(savedHips) && savedHips > 40 && hipsCm > 0) {
                hipFactor = clamp(savedHips / hipsCm, 0.85, 1.15);
            } else {
                hipFactor = (chestFactor + waistFactor) / 2;
            }

            chestCm *= chestFactor;
            waistCm *= waistFactor;
            hipsCm *= hipFactor;

            const avgQuality = mean(protocolCaptures.map((c) => c.capture.qualityScore));
            const qualityPenalty = protocolCaptures.filter((c) => c.capture.qualityScore < 0.55).length * 6;
            const stabilityBonus = Math.min(10, (stablePoseFrames - REQUIRED_STABLE_FRAMES) * 0.4);
            const calibrationBonus = ((!isNaN(tapeChest) && tapeChest > 40) || (!isNaN(tapeWaist) && tapeWaist > 40)) ? 8 : 0;
            const confidence = clamp((avgQuality * 100) - qualityPenalty + stabilityBonus + calibrationBonus, 5, 99);
            const qualityLabel = confidence >= 78 ? 'HIGH' : (confidence >= 58 ? 'MEDIUM' : 'LOW');

            return { chestCm, waistCm, hipsCm, confidence, qualityLabel };
        }

        function syncCameraToggleButton() {
            if (!toggleCameraBtn) return;
            if (cameraStarted) {
                toggleCameraBtn.textContent = 'Camera: On';
                toggleCameraBtn.classList.remove('start');
                toggleCameraBtn.classList.add('stop');
            } else {
                toggleCameraBtn.textContent = 'Camera: Off';
                toggleCameraBtn.classList.remove('stop');
                toggleCameraBtn.classList.add('start');
            }
        }

        function getPoseOptionsFromUi() {
            let modelComplexity = 1;
            if (posePreset && posePreset.value === 'fast') modelComplexity = 0;
            if (posePreset && posePreset.value === 'accurate') modelComplexity = 2;
            const minDetectionConfidence = detectConfidence ? parseFloat(detectConfidence.value) : 0.6;
            const minTrackingConfidence = trackConfidence ? parseFloat(trackConfidence.value) : 0.6;
            const smoothLandmarks = poseSmooth ? poseSmooth.value === '1' : true;
            return {
                modelComplexity,
                smoothLandmarks,
                minDetectionConfidence,
                minTrackingConfidence
            };
        }

        function refreshPoseOptionLabels() {
            if (detectConfidence && detectConfidenceVal) {
                detectConfidenceVal.textContent = parseFloat(detectConfidence.value).toFixed(2);
            }
            if (trackConfidence && trackConfidenceVal) {
                trackConfidenceVal.textContent = parseFloat(trackConfidence.value).toFixed(2);
            }
        }

        function applyPoseOptionsIfReady() {
            refreshPoseOptionLabels();
            if (pose) {
                pose.setOptions(getPoseOptionsFromUi());
            }
        }
        refreshPoseOptionLabels();

        function stopCameraNow() {
            cameraStarted = false;
            currentScanSource = '';
            autoHighQualityFrames = 0;
            frontCapture = null;
            sideCapture = null;
            cancelAutoCountdown('');
            const stream = scanVideo.srcObject;
            if (stream && stream.getTracks) {
                stream.getTracks().forEach((t) => t.stop());
            }
            scanVideo.srcObject = null;
            if (scanCanvas) {
                const ctx = scanCanvas.getContext('2d');
                ctx.clearRect(0, 0, scanCanvas.width || 1, scanCanvas.height || 1);
            }
            if (landmarkStatus) {
                landmarkStatus.textContent = 'Front and side views are required.';
            }
            syncCameraToggleButton();
            if (scanStatus) {
                scanStatus.textContent = 'Camera stopped.';
            }
            updateProtocolUi();
        }

        function userHeightCmFromInput() {
            const hVal = parseFloat(heightValue.value);
            if (isNaN(hVal) || hVal <= 0) return null;
            return (heightUnit.value === 'ft') ? feetToCm(hVal) : hVal;
        }

        function extractPoseMetrics(lm, userHeightCm, viewName) {
            const lShoulder = lm[11], rShoulder = lm[12];
            const lElbow = lm[13], rElbow = lm[14];
            const lHip = lm[23], rHip = lm[24];
            const lKnee = lm[25], rKnee = lm[26];
            const lAnkle = lm[27], rAnkle = lm[28];
            const nose = lm[0];
            if (!nose || (!lAnkle && !rAnkle) || (!lHip && !rHip) || (!lShoulder && !rShoulder)) {
                return null;
            }

            const leftVisible =
                (lShoulder?.visibility ?? 0) > 0.35 &&
                (lHip?.visibility ?? 0) > 0.35 &&
                (lAnkle?.visibility ?? 0) > 0.35;
            const rightVisible =
                (rShoulder?.visibility ?? 0) > 0.35 &&
                (rHip?.visibility ?? 0) > 0.35 &&
                (rAnkle?.visibility ?? 0) > 0.35;
            const useLeft = leftVisible || !rightVisible;

            const primaryShoulder = useLeft ? (lShoulder || rShoulder) : (rShoulder || lShoulder);
            const primaryHip = useLeft ? (lHip || rHip) : (rHip || lHip);
            const primaryAnkle = useLeft ? (lAnkle || rAnkle) : (rAnkle || lAnkle);
            if (!primaryShoulder || !primaryHip || !primaryAnkle) {
                return null;
            }

            const hasBothShoulders = !!lShoulder && !!rShoulder && (lShoulder.visibility ?? 0) > 0.25 && (rShoulder.visibility ?? 0) > 0.25;
            const hasBothHips = !!lHip && !!rHip && (lHip.visibility ?? 0) > 0.25 && (rHip.visibility ?? 0) > 0.25;
            const hasBothAnkles = !!lAnkle && !!rAnkle && (lAnkle.visibility ?? 0) > 0.25 && (rAnkle.visibility ?? 0) > 0.25;

            const midShoulder = hasBothShoulders
                ? { x: (lShoulder.x + rShoulder.x) / 2, y: (lShoulder.y + rShoulder.y) / 2 }
                : primaryShoulder;
            const midHip = hasBothHips
                ? { x: (lHip.x + rHip.x) / 2, y: (lHip.y + rHip.y) / 2 }
                : primaryHip;
            const midAnkle = hasBothAnkles
                ? { x: (lAnkle.x + rAnkle.x) / 2, y: (lAnkle.y + rAnkle.y) / 2 }
                : primaryAnkle;

            const torsoPx = dist(midShoulder, midHip);
            const legsPx = dist(midHip, midAnkle);
            const neckPx = dist(nose, midShoulder);
            const estimatedHeightPx = Math.max(0.0001, torsoPx + legsPx + neckPx);
            const cmPerPx = userHeightCm / estimatedHeightPx;

            const shoulderWidthCm = hasBothShoulders ? dist(lShoulder, rShoulder) * cmPerPx : userHeightCm * 0.24;
            const hipWidthCm = hasBothHips ? dist(lHip, rHip) * cmPerPx : userHeightCm * 0.20;
            const kneeWidthCm = (lKnee && rKnee && (lKnee.visibility ?? 0) > 0.25 && (rKnee.visibility ?? 0) > 0.25)
                ? dist(lKnee, rKnee) * cmPerPx
                : userHeightCm * 0.12;
            const elbowWidthCm = (lElbow && rElbow && (lElbow.visibility ?? 0) > 0.25 && (rElbow.visibility ?? 0) > 0.25)
                ? dist(lElbow, rElbow) * cmPerPx
                : userHeightCm * 0.18;

            const torsoPoints = [lShoulder, rShoulder, lHip, rHip, lKnee, rKnee]
                .filter((p) => p && (p.visibility ?? 0) > 0.25);
            let torsoSpanNorm = 0;
            if (torsoPoints.length >= 2) {
                const xs = torsoPoints.map((p) => p.x);
                torsoSpanNorm = Math.max(...xs) - Math.min(...xs);
            }
            const shoulderSpanNorm = hasBothShoulders ? Math.abs(lShoulder.x - rShoulder.x) : torsoSpanNorm;

            let torsoDepthProxyCm;
            if (viewName === 'side') {
                torsoDepthProxyCm = clamp(
                    Math.max(torsoSpanNorm * userHeightCm * 1.05, shoulderWidthCm * 0.50, hipWidthCm * 0.60),
                    12,
                    60
                );
            } else {
                torsoDepthProxyCm = clamp(
                    ((shoulderWidthCm * 0.38) + (hipWidthCm * 0.62)),
                    12,
                    58
                );
            }

            const chestDepthCm = clamp(torsoDepthProxyCm * 1.06, 12, 65);
            const waistDepthCm = clamp(torsoDepthProxyCm * 0.92, 10, 58);
            const hipDepthCm = clamp(torsoDepthProxyCm * 1.12, 12, 68);

            const avgVisibility = (
                (lShoulder?.visibility ?? 0.7) +
                (rShoulder?.visibility ?? 0.7) +
                (lHip?.visibility ?? 0.7) +
                (rHip?.visibility ?? 0.7) +
                (lKnee?.visibility ?? 0.7) +
                (rKnee?.visibility ?? 0.7) +
                (lAnkle?.visibility ?? 0.7) +
                (rAnkle?.visibility ?? 0.7)
            ) / 8;
            const shoulderYLeft = lShoulder?.y ?? primaryShoulder.y;
            const shoulderYRight = rShoulder?.y ?? primaryShoulder.y;
            const levelness = 1 - clamp(Math.abs((shoulderYLeft - shoulderYRight)) * 2.5, 0, 1);
            const frameCoverage = clamp(estimatedHeightPx / 0.85, 0, 1);
            const sideReadiness = clamp(torsoSpanNorm / 0.14, 0, 1);
            const qualityScore = clamp(
                (avgVisibility * 0.42) +
                (levelness * 0.22) +
                (frameCoverage * 0.26) +
                ((viewName === 'side' ? sideReadiness : 0.7) * 0.10),
                0,
                1
            );

            return {
                shoulderWidthCm,
                hipWidthCm,
                kneeWidthCm,
                elbowWidthCm,
                torsoDepthProxyCm,
                chestDepthCm,
                waistDepthCm,
                hipDepthCm,
                qualityScore,
                shoulderSpanNorm,
                visibleLandmarks: [
                    lShoulder, rShoulder, lElbow, rElbow, lHip, rHip, lKnee, rKnee, lAnkle, rAnkle, nose
                ].filter((p) => (p.visibility ?? 1) > 0.5).length
            };
        }

        function captureCurrentView(viewName) {
            if (!latestLandmarks) {
                setScanStatus('No pose detected. Keep full body in frame.', 1500);
                return null;
            }
            const userHeightCm = userHeightCmFromInput();
            if (!userHeightCm) {
                setScanStatus('Save your height in Settings first.', 1800);
                return null;
            }

            const metrics = extractPoseMetrics(latestLandmarks, userHeightCm, viewName);
            if (!metrics) {
                setScanStatus('Could not detect required landmarks (shoulders/hips/knees/ankles).', 1800);
                return null;
            }

            const capture = {
                view: viewName,
                shoulderWidthCm: metrics.shoulderWidthCm,
                hipWidthCm: metrics.hipWidthCm,
                kneeWidthCm: metrics.kneeWidthCm,
                elbowWidthCm: metrics.elbowWidthCm,
                torsoDepthProxyCm: metrics.torsoDepthProxyCm,
                chestDepthCm: metrics.chestDepthCm,
                waistDepthCm: metrics.waistDepthCm,
                hipDepthCm: metrics.hipDepthCm,
                qualityScore: metrics.qualityScore,
                shoulderSpanNorm: metrics.shoulderSpanNorm
            };
            return capture;
        }

        function applyFinalMeasurements(chestCm, waistCm, hipsCm, qualityLabel, statusMessage, confidencePct = null) {
            chestCm = Math.max(60, Math.min(160, chestCm));
            waistCm = Math.max(50, Math.min(150, waistCm));
            hipsCm = Math.max(70, Math.min(170, hipsCm));

            scanChestInput.value = chestCm.toFixed(1);
            scanWaistInput.value = waistCm.toFixed(1);
            scanHipsInput.value = hipsCm.toFixed(1);

            scanChestText.textContent = chestCm.toFixed(1);
            scanWaistText.textContent = waistCm.toFixed(1);
            scanHipsText.textContent = hipsCm.toFixed(1);
            scanQualityText.textContent = qualityLabel;
            scanQualityInput.value = qualityLabel;
            if (scanSourceInput) {
                scanSourceInput.value = currentScanSource || (cameraStarted ? 'camera' : 'photo');
            }
            if (scanConfidenceInput) {
                scanConfidenceInput.value = confidencePct !== null ? Number(confidencePct).toFixed(1) : '';
            }
            if (scanConfidenceText) {
                scanConfidenceText.textContent = confidencePct !== null ? `${Number(confidencePct).toFixed(1)}%` : '-';
            }
            scanSizeText.textContent = pickSizeFromMeasures(chestCm, waistCm, hipsCm);
            scanStatus.textContent = statusMessage;
        }

        syncCameraToggleButton();
        updateProtocolUi();

        if (window.Pose && scanVideo && scanCanvas) {
            pose = new Pose({
                locateFile: (file) => `https://cdn.jsdelivr.net/npm/@mediapipe/pose/${file}`
            });
            pose.setOptions(getPoseOptionsFromUi());
            applyPoseOptionsIfReady();
            pose.onResults((results) => {
                const ctx = scanCanvas.getContext('2d');
                const sourceEl = scanVideo;
                scanCanvas.width = (sourceEl && (sourceEl.videoWidth || sourceEl.naturalWidth || sourceEl.clientWidth)) || 640;
                scanCanvas.height = (sourceEl && (sourceEl.videoHeight || sourceEl.naturalHeight || sourceEl.clientHeight)) || 480;
                ctx.clearRect(0, 0, scanCanvas.width, scanCanvas.height);
                if (results.poseLandmarks) {
                    latestLandmarks = results.poseLandmarks;
                    updatePoseStability(results.poseLandmarks);
                    if (currentScanSource !== 'photo') {
                        drawConnectors(ctx, results.poseLandmarks, POSE_CONNECTIONS, { color: '#93c5fd', lineWidth: 2 });
                        drawLandmarks(ctx, results.poseLandmarks, { color: '#1d4ed8', lineWidth: 1, radius: 2 });
                    }
                    const visCount = results.poseLandmarks.filter((p) => (p.visibility ?? 1) > 0.5).length;
                    if (landmarkStatus) {
                        landmarkStatus.textContent = currentScanSource === 'photo'
                            ? `Photo landmarks: ${visCount} / 33 visible`
                            : `Landmarks: ${visCount} / 33 visible | Stable: ${Math.min(stablePoseFrames, REQUIRED_STABLE_FRAMES)} / ${REQUIRED_STABLE_FRAMES}`;
                    }
                    if (!measurementInProgress && Date.now() > statusHoldUntil) {
                        setScanStatus(currentScanSource === 'photo'
                            ? 'Uploaded photos are ready. Click Calculate Camera Size to send them to the local AI scanner.'
                            : 'Pose detected. Capture a front snapshot and a side snapshot.');
                    }

                    if (scanMode && scanMode.value === 'auto' && cameraStarted) {
                        const capture = captureCurrentView('auto');
                        if (capture && capture.qualityScore >= 0.8) {
                            autoHighQualityFrames += 1;
                        } else {
                            cancelAutoCountdown('Auto mode: waiting for stable HIGH quality pose.');
                            autoHighQualityFrames = 0;
                        }

                        const now = Date.now();
                        if (autoHighQualityFrames >= 15 && (now - autoLastScanAt) > 3000 && !autoCountdownActive) {
                            startAutoCountdown();
                        }
                    }
                } else {
                    latestLandmarks = null;
                    stablePoseFrames = 0;
                    lastStablePoints = null;
                    if (landmarkStatus) {
                        landmarkStatus.textContent = 'No full-body pose detected yet.';
                    }
                    cancelAutoCountdown('No full-body pose detected yet.');
                    autoHighQualityFrames = 0;
                    if (Date.now() > statusHoldUntil) {
                        setScanStatus(currentScanSource === 'photo'
                            ? 'No full-body pose detected in the uploaded photo yet.'
                            : 'No full-body pose detected yet.');
                    }
                }
                if (pendingPoseResultResolver) {
                    pendingPoseResultResolver(results);
                }
            });

            [posePreset, poseSmooth, detectConfidence, trackConfidence].forEach((el) => {
                if (el) {
                    el.addEventListener('input', applyPoseOptionsIfReady);
                    el.addEventListener('change', applyPoseOptionsIfReady);
                }
            });

            async function startCamera() {
                if (cameraStarted) {
                    scanStatus.textContent = 'Camera already running.';
                    return;
                }
                try {
                    if (!mpCamera) {
                        mpCamera = new Camera(scanVideo, {
                            onFrame: async () => {
                                if (!cameraStarted) return;
                                await pose.send({ image: scanVideo });
                            },
                            width: 640,
                            height: 480
                        });
                    }
                    await mpCamera.start();
                    cameraStarted = true;
                    currentScanSource = 'camera';
                    syncCameraToggleButton();
                    scanStatus.textContent = 'Camera started. Stand fully in frame.';
                } catch (e) {
                    scanStatus.textContent = 'Unable to access camera. Please allow permissions.';
                }
            }

            if (toggleCameraBtn) {
                toggleCameraBtn.addEventListener('click', async function () {
                    if (cameraStarted) {
                        stopCameraNow();
                    } else {
                        clearPhotoPreviewState(false);
                        await startCamera();
                    }
                });
            }

            if (measureBtn) {
                measureBtn.addEventListener('click', async function () {
                    if (!cameraStarted) {
                        setScanStatus('Turn on the camera first.', 1500);
                        return;
                    }
                    if (protocolCaptures.length < PROTOCOL_STEPS.length) {
                        setScanStatus('Complete all protocol capture steps first.', 1800);
                        return;
                    }
                    if (stablePoseFrames < REQUIRED_STABLE_FRAMES) {
                        setScanStatus('Hold still briefly before computing.', 1800);
                        return;
                    }
                    if (measurementInProgress) return;

                    measurementInProgress = true;
                    measureBtn.disabled = true;
                    if (captureStepBtn) captureStepBtn.disabled = true;

                    try {
                        const final = computeProtocolMeasurements();
                        if (!final || final.error) {
                            setScanStatus((final && final.error) ? final.error : 'Unable to compute precision scan.', 2200);
                            return;
                        }

                        applyFinalMeasurements(
                            final.chestCm,
                            final.waistCm,
                            final.hipsCm,
                            final.qualityLabel,
                            'Precision scan complete. Submit form to apply recommendation.',
                            final.confidence
                        );
                    } finally {
                        measurementInProgress = false;
                        measureBtn.disabled = false;
                        if (captureStepBtn) captureStepBtn.disabled = false;
                    }
                });
            }

            if (captureStepBtn) {
                captureStepBtn.addEventListener('click', async function () {
                    if (!cameraStarted) {
                        setScanStatus('Turn on the camera first.', 1500);
                        return;
                    }
                    if (measurementInProgress) return;
                    if (protocolStepIndex >= PROTOCOL_STEPS.length) {
                        setScanStatus('Protocol already complete. Click "Compute Precision Scan".', 1800);
                        return;
                    }

                    const step = PROTOCOL_STEPS[protocolStepIndex];
                    measurementInProgress = true;
                    captureStepBtn.disabled = true;
                    if (measureBtn) measureBtn.disabled = true;
                    setScanStatus(`Capturing ${step.label} (${REQUIRED_SAMPLE_COUNT} samples)...`, 1200);

                    try {
                        const result = await collectAveragedCapture(step.view, REQUIRED_SAMPLE_COUNT, 0.40, false);
                        if (!result.capture) {
                            setScanStatus(result.error || `Could not capture ${step.label}.`, 2200);
                            return;
                        }
                        if (step.orientation === 'side' && !orientationMatches(result.capture, step.orientation)) {
                            setScanStatus(`Orientation mismatch for ${step.label}. Reposition and capture again.`, 2400);
                            return;
                        }

                        protocolCaptures.push({ step, capture: result.capture });
                        protocolStepIndex += 1;
                        updateProtocolUi();

                        if (protocolStepIndex >= PROTOCOL_STEPS.length) {
                            setScanStatus('All 8 steps captured. Click "Compute Precision Scan".', 2200);
                        } else {
                            const next = PROTOCOL_STEPS[protocolStepIndex];
                            setScanStatus(`Captured ${step.label}. Next: ${next.label}.`, 2200);
                        }
                    } finally {
                        measurementInProgress = false;
                        captureStepBtn.disabled = false;
                        if (measureBtn) measureBtn.disabled = false;
                    }
                });
            }

            if (resetProtocolBtn) {
                resetProtocolBtn.addEventListener('click', function () {
                    resetProtocolState();
                    setScanStatus('Protocol reset. Start again from Step 1.', 1800);
                });
            }

            async function handlePhotoSlotChange(file, slot) {
                if (!file) {
                    if (slot === 'front') {
                        if (uploadFitXpressFrontPhotoInput) uploadFitXpressFrontPhotoInput.value = '';
                        setImagePreview(photoFrontPreview, '');
                    } else {
                        if (uploadFitXpressSidePhotoInput) uploadFitXpressSidePhotoInput.value = '';
                        setImagePreview(photoSidePreview, '');
                    }
                    updateProtocolUi();
                    return;
                }
                if (!file.type.startsWith('image/')) {
                    alert('Please choose an image file for photo scanning.');
                    return;
                }

                const dataUrl = await readFileAsDataUrl(file);
                if (slot === 'front') {
                    if (uploadFitXpressFrontPhotoInput) uploadFitXpressFrontPhotoInput.value = dataUrl;
                    setImagePreview(photoFrontPreview, dataUrl);
                    clearThumbnailLandmarks(photoFrontCanvas);
                } else {
                    if (uploadFitXpressSidePhotoInput) uploadFitXpressSidePhotoInput.value = dataUrl;
                    setImagePreview(photoSidePreview, dataUrl);
                    clearThumbnailLandmarks(photoSideCanvas);
                }
                currentScanSource = 'photo';
                if (scanSourceInput) scanSourceInput.value = 'photo';
                if (fitXpressMeasurementIdInput) fitXpressMeasurementIdInput.value = '';
                if (scanProviderInput) scanProviderInput.value = 'Local AI Scanner';
                if (cameraStarted) {
                    stopCameraNow();
                }
                updateProtocolUi();
                setScanStatus('Uploaded photos ready. Add both front and side views, then click Calculate Uploaded Photo Size.', 2200);
            }

            if (photoScanFrontInput) {
                photoScanFrontInput.addEventListener('change', async function (e) {
                    const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                    try {
                        await handlePhotoSlotChange(file, 'front');
                    } catch (error) {
                        setScanStatus('Could not load the front photo.', 1800);
                    }
                });
            }

            if (photoScanSideInput) {
                photoScanSideInput.addEventListener('change', async function (e) {
                    const file = e.target.files && e.target.files[0] ? e.target.files[0] : null;
                    try {
                        await handlePhotoSlotChange(file, 'side');
                    } catch (error) {
                        setScanStatus('Could not load the side photo.', 1800);
                    }
                });
            }

            if (clearPhotoBtn) {
                clearPhotoBtn.addEventListener('click', function () {
                    clearPhotoPreviewState(true);
                    if (!cameraStarted && scanCanvas) {
                        const ctx = scanCanvas.getContext('2d');
                        ctx.clearRect(0, 0, scanCanvas.width || 1, scanCanvas.height || 1);
                    }
                    setScanStatus(cameraStarted ? 'Live camera still available.' : 'Photo cleared.', 1200);
                });
            }

            if (captureFrontBtn) {
                captureFrontBtn.addEventListener('click', function () {
                    if (!cameraStarted || !scanVideo || !scanVideo.videoWidth || !scanVideo.videoHeight) {
                        setScanStatus('Turn on the camera before capturing the front snapshot.', 1500);
                        return;
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = scanVideo.videoWidth;
                    canvas.height = scanVideo.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(scanVideo, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                    if (fitXpressFrontPhotoInput) fitXpressFrontPhotoInput.value = dataUrl;
                    if (scanSourceInput) scanSourceInput.value = 'camera';
                    if (scanProviderInput) scanProviderInput.value = 'Local AI Scanner';
                    setImagePreview(cameraFrontPreview, dataUrl);
                    updateProtocolUi();
                    setScanStatus('Front camera snapshot captured.', 1600);
                });
            }

            if (captureSideBtn) {
                captureSideBtn.addEventListener('click', function () {
                    if (!cameraStarted || !scanVideo || !scanVideo.videoWidth || !scanVideo.videoHeight) {
                        setScanStatus('Turn on the camera before capturing the side snapshot.', 1500);
                        return;
                    }
                    const canvas = document.createElement('canvas');
                    canvas.width = scanVideo.videoWidth;
                    canvas.height = scanVideo.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(scanVideo, 0, 0, canvas.width, canvas.height);
                    const dataUrl = canvas.toDataURL('image/jpeg', 0.92);
                    if (fitXpressSidePhotoInput) fitXpressSidePhotoInput.value = dataUrl;
                    if (scanSourceInput) scanSourceInput.value = 'camera';
                    if (scanProviderInput) scanProviderInput.value = 'Local AI Scanner';
                    setImagePreview(cameraSidePreview, dataUrl);
                    updateProtocolUi();
                    setScanStatus('Side camera snapshot captured.', 1600);
                });
            }

            if (computeTwoViewBtn) {
                computeTwoViewBtn.addEventListener('click', function () {
                    if (!frontCapture || !sideCapture) {
                        scanStatus.textContent = 'Capture both front and side views first.';
                        return;
                    }
                    if (frontCapture.qualityScore < 0.45 || sideCapture.qualityScore < 0.45) {
                        scanStatus.textContent = 'Re-capture front and side views with better quality before computing.';
                        return;
                    }

                    const chestCm = ellipseCircumference(
                        frontCapture.shoulderWidthCm * 1.18,
                        sideCapture.chestDepthCm * 1.02
                    );
                    const waistCm = ellipseCircumference(
                        ((frontCapture.shoulderWidthCm + frontCapture.hipWidthCm) / 2) * 0.92,
                        sideCapture.waistDepthCm * 1.00
                    );
                    const hipsCm = ellipseCircumference(
                        frontCapture.hipWidthCm * 1.20,
                        sideCapture.hipDepthCm * 1.00
                    );

                    applyFinalMeasurements(
                        chestCm,
                        waistCm,
                        hipsCm,
                        qualityLabelFromScore((frontCapture.qualityScore + sideCapture.qualityScore) / 2),
                        '2-view scan complete. Submit form to apply blended recommendation.'
                    );
                });
            }
        } else {
            if (toggleCameraBtn) {
                toggleCameraBtn.disabled = true;
            }
            scanStatus.textContent = 'Camera scan is unavailable in this browser.';
        }
    });
</script>

</body>
</html>
