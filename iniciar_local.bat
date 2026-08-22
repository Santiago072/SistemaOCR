@echo off
echo ========================================================
echo   Iniciando Sistema OCR SENA (Entorno Local)
echo ========================================================
echo.

echo 1. Iniciando Microservicio Python FastAPI en http://127.0.0.1:8001 ...
start "Microservicio FastAPI OCR" cmd /k "cd ocr-service && uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload"

echo 2. Iniciando Servidor Web Laravel en http://127.0.0.1:8000 ...
start "Servidor Laravel" cmd /k "cd laravel-app && php artisan serve --port=8000"

echo.
echo Todo listo! Puedes acceder en tu navegador a:
echo http://127.0.0.1:8000
echo.
pause
