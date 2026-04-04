@echo off
setlocal

cd /d "%~dp0"

echo Starting ScanFit local body scanner on http://127.0.0.1:8001
echo.
echo If Python packages are missing, install them with:
echo   pip install -r local_body_scanner_requirements.txt
echo.

set "PYTHON_EXE=%~dp0.venv311\Scripts\python.exe"
if exist "%PYTHON_EXE%" (
    "%PYTHON_EXE%" local_body_scanner_service.py
) else (
    python local_body_scanner_service.py
)

echo.
echo Local body scanner stopped.
pause
