@echo off
title Sistema OCR SENA - Iniciador Local
cd /d "%~dp0"

echo ========================================================
echo   Iniciando Sistema OCR SENA (Entorno Local)
echo ========================================================
echo.

echo 1. Iniciando Microservicio Python FastAPI en http://127.0.0.1:8001 ...
start "Microservicio FastAPI OCR" cmd /k "cd /d ""%~dp0ocr-service"" && python -m uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload"

echo 2. Iniciando Servidor Web Laravel en http://127.0.0.1:8000 ...
start "Servidor Laravel" cmd /k "cd /d ""%~dp0laravel-app"" && php artisan serve --port=8000"

echo.
echo ========================================================
echo   Se han abierto 2 ventanas de terminal en segundo plano.
echo   Por favor NO cierres esas ventanas mientras uses la app.
echo ========================================================
echo.
echo Accede en tu navegador a:
echo http://127.0.0.1:8000
echo.
timeout /t 5 >nul
start http://127.0.0.1:8000
pause
