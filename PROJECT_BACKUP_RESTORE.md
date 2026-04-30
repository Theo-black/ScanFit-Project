# ScanFit Backup And Full Restore

This file gives you a repeatable backup and restore workflow for the full local project:

- PHP app files
- images and uploads
- `.env`
- MySQL database
- local AI scanner files
- ecommerce workflow files for Stripe checkout, coupons, wishlist, order tracking, refunds, returns, reviews, and catalog settings

This project expects:

- app root: `C:\wamp64\www\ScanFit`
- database name: `capstonestoredb`
- local scanner: `http://127.0.0.1:8001`

## 1. What gets backed up

The backup bundle contains:

- `project\` : the full app source tree
- `database\capstonestoredb.sql` : a fresh MySQL dump
- `metadata\restore-notes.txt` : restore metadata
- `ScanFit-<timestamp>.zip` : compressed archive

Excluded from the copied project tree:

- `.git`
- `.venv311`
- `__pycache__`
- `storage\sessions\sess_*`

Those are rebuilt during restore.

## 2. Backup Script

Run this in PowerShell.

Before running:

- make sure MySQL command line tools are available on PATH
- or replace `mysqldump` with the full WAMP path to `mysqldump.exe`

```powershell
$ErrorActionPreference = "Stop"

$projectRoot = "C:\wamp64\www\ScanFit"
$backupRoot = "C:\wamp64\www\ScanFit-backups"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$bundleRoot = Join-Path $backupRoot "ScanFit-$timestamp"
$projectBackup = Join-Path $bundleRoot "project"
$dbBackupDir = Join-Path $bundleRoot "database"
$metaDir = Join-Path $bundleRoot "metadata"
$zipPath = Join-Path $backupRoot "ScanFit-$timestamp.zip"

New-Item -ItemType Directory -Force -Path $projectBackup | Out-Null
New-Item -ItemType Directory -Force -Path $dbBackupDir | Out-Null
New-Item -ItemType Directory -Force -Path $metaDir | Out-Null

$envFile = Join-Path $projectRoot ".env"
if (-not (Test-Path $envFile)) {
    throw ".env not found at $envFile"
}

$envMap = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) { return }
    $parts = $line -split "=", 2
    if ($parts.Count -ne 2) { return }
    $key = $parts[0].Trim()
    $value = $parts[1].Trim().Trim('"').Trim("'")
    $envMap[$key] = $value
}

$dbHost = if ($envMap.ContainsKey("SCANFIT_DB_HOST")) { $envMap["SCANFIT_DB_HOST"] } else { "localhost" }
$dbUser = if ($envMap.ContainsKey("SCANFIT_DB_USER")) { $envMap["SCANFIT_DB_USER"] } else { "root" }
$dbPass = if ($envMap.ContainsKey("SCANFIT_DB_PASS")) { $envMap["SCANFIT_DB_PASS"] } else { "" }
$dbName = if ($envMap.ContainsKey("SCANFIT_DB_NAME")) { $envMap["SCANFIT_DB_NAME"] } else { "capstonestoredb" }

robocopy $projectRoot $projectBackup /MIR /XD ".git" ".venv311" "__pycache__" /XF "sess_*" | Out-Null

$dbDumpPath = Join-Path $dbBackupDir "$dbName.sql"
$dumpArgs = @(
    "--host=$dbHost"
    "--user=$dbUser"
    "--default-character-set=utf8mb4"
    "--routines"
    "--triggers"
    "--single-transaction"
    $dbName
)

if ($dbPass -ne "") {
    $dumpArgs = @("--password=$dbPass") + $dumpArgs
}

$dumpOutput = & mysqldump @dumpArgs
if ($LASTEXITCODE -ne 0) {
    throw "mysqldump failed with exit code $LASTEXITCODE"
}
$dumpOutput | Set-Content -Encoding UTF8 $dbDumpPath

@"
Project: ScanFit
Backup created: $timestamp
Project root: $projectRoot
Database host: $dbHost
Database name: $dbName
Contains .env: YES
Contains local scanner files: YES
"@ | Set-Content -Encoding UTF8 (Join-Path $metaDir "restore-notes.txt")

if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}
Compress-Archive -Path (Join-Path $bundleRoot "*") -DestinationPath $zipPath -Force

Write-Host "Backup complete:"
Write-Host "Folder: $bundleRoot"
Write-Host "Zip:    $zipPath"
```

## 3. Restore Script

Run this in PowerShell on the target machine.

Before running:

- install WAMP or equivalent Apache/PHP/MySQL stack
- make sure `mysql` is available on PATH
- make sure `python` is available if you want the local scanner

Update only these values first:

- `$backupZip`
- `$restoreRoot`

```powershell
$ErrorActionPreference = "Stop"

$backupZip = "C:\wamp64\www\ScanFit-backups\ScanFit-YYYYMMDD-HHMMSS.zip"
$restoreRoot = "C:\wamp64\www\ScanFit"
$workingRoot = "C:\wamp64\www\ScanFit-restore-temp"

if (Test-Path $workingRoot) {
    Remove-Item $workingRoot -Recurse -Force
}
New-Item -ItemType Directory -Force -Path $workingRoot | Out-Null

Expand-Archive -Path $backupZip -DestinationPath $workingRoot -Force

$projectBackup = Join-Path $workingRoot "project"
$dbBackupDir = Join-Path $workingRoot "database"

if (-not (Test-Path $projectBackup)) {
    throw "project folder not found inside backup"
}

if (Test-Path $restoreRoot) {
    robocopy $projectBackup $restoreRoot /MIR /XD ".venv311" "__pycache__" /XF "sess_*" | Out-Null
} else {
    New-Item -ItemType Directory -Force -Path $restoreRoot | Out-Null
    robocopy $projectBackup $restoreRoot /MIR /XD ".venv311" "__pycache__" /XF "sess_*" | Out-Null
}

$envFile = Join-Path $restoreRoot ".env"
if (-not (Test-Path $envFile)) {
    throw ".env not found after project restore"
}

$envMap = @{}
Get-Content $envFile | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq "" -or $line.StartsWith("#")) { return }
    $parts = $line -split "=", 2
    if ($parts.Count -ne 2) { return }
    $key = $parts[0].Trim()
    $value = $parts[1].Trim().Trim('"').Trim("'")
    $envMap[$key] = $value
}

$dbHost = if ($envMap.ContainsKey("SCANFIT_DB_HOST")) { $envMap["SCANFIT_DB_HOST"] } else { "localhost" }
$dbUser = if ($envMap.ContainsKey("SCANFIT_DB_USER")) { $envMap["SCANFIT_DB_USER"] } else { "root" }
$dbPass = if ($envMap.ContainsKey("SCANFIT_DB_PASS")) { $envMap["SCANFIT_DB_PASS"] } else { "" }
$dbName = if ($envMap.ContainsKey("SCANFIT_DB_NAME")) { $envMap["SCANFIT_DB_NAME"] } else { "capstonestoredb" }
$dbDumpPath = Join-Path $dbBackupDir "$dbName.sql"

if (-not (Test-Path $dbDumpPath)) {
    throw "database dump not found at $dbDumpPath"
}

$mysqlBaseArgs = @(
    "--host=$dbHost"
    "--user=$dbUser"
)
if ($dbPass -ne "") {
    $mysqlBaseArgs = @("--password=$dbPass") + $mysqlBaseArgs
}

$sqlSetup = "DROP DATABASE IF EXISTS $dbName; CREATE DATABASE $dbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
& mysql @mysqlBaseArgs -e $sqlSetup
if ($LASTEXITCODE -ne 0) {
    throw "database create failed with exit code $LASTEXITCODE"
}

cmd /c "mysql --host=$dbHost --user=$dbUser $([string]::IsNullOrWhiteSpace($dbPass) ? '' : '--password=' + $dbPass) $dbName < `"$dbDumpPath`""
if ($LASTEXITCODE -ne 0) {
    throw "database import failed with exit code $LASTEXITCODE"
}

Push-Location $restoreRoot
if (-not (Test-Path ".venv311")) {
    python -m venv .venv311
}
& ".\.venv311\Scripts\python.exe" -m pip install --upgrade pip
& ".\.venv311\Scripts\pip.exe" install -r local_body_scanner_requirements.txt
Pop-Location

Write-Host "Restore complete."
Write-Host "Project root: $restoreRoot"
Write-Host "Database: $dbName"
Write-Host "Next: restart WAMP/Apache and start the local scanner if needed."
```

## 4. Start The Restored Project

### Web app

Put the project in:

```text
C:\wamp64\www\ScanFit
```

Then restart WAMP and open:

```text
http://localhost/ScanFit
```

### Local AI scanner

From the project folder:

```powershell
cd C:\wamp64\www\ScanFit
.\start_local_body_scanner.bat
```

That starts:

```text
http://127.0.0.1:8001
```

## 5. Environment Variables

This project loads environment variables from:

```text
C:\wamp64\www\ScanFit\.env
```

At minimum, the restored `.env` should contain:

```env
SCANFIT_DB_HOST=localhost
SCANFIT_DB_USER=root
SCANFIT_DB_PASS=
SCANFIT_DB_NAME=capstonestoredb
SCANFIT_APP_URL=http://localhost/ScanFit
SCANFIT_FLAT_SHIPPING_USD=5.00
SCANFIT_TAX_RATE=0.10
SCANFIT_LOW_STOCK_THRESHOLD=5
SCANFIT_MAIL_FROM=no-reply@example.com
SCANFIT_MAIL_FROM_NAME=ScanFit
SCANFIT_ADMIN_NOTIFY_EMAIL=
SCANFIT_SMTP_HOST=smtp.gmail.com
SCANFIT_SMTP_PORT=587
SCANFIT_SMTP_ENCRYPTION=tls
SCANFIT_SMTP_USERNAME=your-gmail-address@gmail.com
SCANFIT_SMTP_PASSWORD=your-16-character-app-password
SCANFIT_GOOGLE_CLIENT_ID=
SCANFIT_GOOGLE_CLIENT_SECRET=
SCANFIT_GOOGLE_REDIRECT_URI=
SCANFIT_CA_BUNDLE=
SCANFIT_STRIPE_SECRET_KEY=
SCANFIT_STRIPE_WEBHOOK_SECRET=
SCANFIT_STRIPE_CURRENCY=usd
```

Optional scanner API values if you ever use remote FitXpress:

```env
SCANFIT_FITXPRESS_TOKEN=
SCANFIT_FITXPRESS_BASE_URL=https://backend.fitxpress.3dlook.me/api/1.0/
```

## 6. Important Notes

- The actual default database name in code is `capstonestoredb`.
- Make sure `.env` uses the database you restored. The project default is `capstonestoredb`.
- Your backup includes uploaded images because the `images\` folder is copied.
- The Python virtual environment is intentionally rebuilt during restore.
- Runtime session files under `storage\sessions\sess_*` are temporary and should not be backed up. Keep `storage\sessions\.htaccess`.
- After restore, the app bootstraps missing support tables/columns for payments, fulfillment, reviews, wishlist, coupons, returns, countries, and customer security fields. A restored database dump is still preferred because it preserves real order/customer data.
- Stripe local testing needs test keys in `.env`, a reachable `SCANFIT_APP_URL`, and webhook forwarding to `stripe_webhook.php` if you want automatic payment completion from webhooks.
- Countries are seeded at runtime, including Jamaica (`JM`) and other supported shipping countries.
- The local scanner depends on:

```text
flask
opencv-python
numpy
mediapipe==0.10.14
torch
```

## 7. Quick Disaster Recovery Checklist

1. Restore the project folder.
2. Restore the MySQL database.
3. Confirm `.env` is present.
4. Restart WAMP.
5. Rebuild `.venv311`.
6. Start `local_body_scanner_service.py`.
7. Confirm `storage\sessions\.htaccess` exists and `storage\sessions` is writable.
8. Open `http://localhost/ScanFit`.
9. Test login, cart, checkout, orders, admin fulfillment, coupons, reviews, returns, and wishlist.

## 8. Post-Restore Ecommerce Checks

After the app opens, run these checks in the browser:

1. Customer account: register, verify/login, update settings.
2. Product flow: browse Men/Women/Accessories, filter products, open product details.
3. Cart flow: add item, update quantity, apply/remove coupon.
4. Checkout flow: place COD order and, if Stripe keys are configured, test Stripe card checkout.
5. Admin flow: open dashboard, products, catalog settings, coupons, orders, returns.
6. Fulfillment flow: mark an order processing, shipped with carrier/tracking, then delivered.
7. Customer after-delivery flow: view receipt, submit review, submit return request.
8. Refund flow: use Stripe refund only on completed Stripe payments.
