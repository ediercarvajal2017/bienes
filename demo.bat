@echo off
REM ============================================================================
REM  MIA - Demostracion local con DATOS FICTICIOS (base sigebi_demo).
REM  No toca la base "sigebi" ni produccion. Ver docs\guion-presentacion.md
REM
REM  Uso (doble clic o desde la consola en esta carpeta):
REM    demo.bat             reinicia los datos de la demo y la abre
REM    demo.bat continuar   la abre SIN reiniciar (conserva lo hecho)
REM    demo.bat red         reinicia y la publica en la red local, para abrir los
REM                         QR con la camara del celular (misma red Wi-Fi)
REM  Requisito: MySQL encendido en el panel de XAMPP.
REM ============================================================================
setlocal
cd /d "%~dp0"
set "PHP=C:\xampp\php\php.exe"
set "DB_DATABASE=sigebi_demo"
set "STORAGE_PATH=%~dp0storage\demo"
set "APP_KEY=c2lnZWJpLWRlbW8tbGxhdmUtZGUtMzItYnl0ZXMhISE="
set "APP_DEBUG=0"
set "MAIL_HOST="
set "BACKUP_EMAIL="
set "HOST=127.0.0.1"
set "APP_URL=http://127.0.0.1:8080"

if /I "%~1"=="red" (
    for /f "usebackq delims=" %%i in (`powershell -NoProfile -Command "(Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.PrefixOrigin -in 'Dhcp','Manual' -and $_.IPAddress -notlike '169.*' -and $_.InterfaceAlias -notmatch 'vEthernet|Tailscale|Loopback' } | Select-Object -First 1).IPAddress"`) do set "IP=%%i"
)
if /I "%~1"=="red" (
    set "HOST=0.0.0.0"
    call set "APP_URL=http://%%IP%%:8080"
)

if /I not "%~1"=="continuar" (
    echo Preparando los datos de la demostracion...
    "%PHP%" database\herramientas\preparar_demo.php
    if errorlevel 1 (
        echo.
        echo No se pudo preparar la demo. Revise que MySQL este encendido en el panel de XAMPP.
        pause
        exit /b 1
    )
)

echo.
echo  MIA - DEMOSTRACION  (%APP_URL%)
echo  ---------------------------------------------------------------
echo   Rector:        rector@demo.test          Demo-Rector-2026
echo   Secretario:    secretario@demo.test      Demo-Secretario-2026
echo   Docente:       docente@demo.test         Demo-Docente-2026
echo   Superusuario:  super@demo.test           Demo-Super-2026
echo   Otra IE:       rector.sanjose@demo.test  Demo-RectorSJ-2026
echo  ---------------------------------------------------------------
echo   Cierre esta ventana para terminar la demostracion.
echo.
start "" "http://127.0.0.1:8080/login"
"%PHP%" -S %HOST%:8080 -t public tests\servidor\router.php
