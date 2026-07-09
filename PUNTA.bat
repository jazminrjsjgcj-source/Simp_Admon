@echo off
REM ============================================================
REM   PUNTA - Lanzador de un clic
REM   Doble clic en este archivo y el proyecto arranca solo.
REM   NO cierres la ventana negra mientras uses el sistema:
REM   esa ventana ES el servidor. Si la cierras, se apaga.
REM ============================================================

title PUNTA - Servidor (no cerrar esta ventana)
color 0F

REM --- Ir a la carpeta donde vive este .bat -----------------------
REM     %~dp0 = la carpeta de ESTE archivo, sin importar donde este.
REM     Asi funciona aunque muevas el proyecto a otro disco/carpeta.
cd /d "%~dp0"

REM --- Verificar que el .env existe --------------------------------
REM     Sin .env, Laravel no puede arrancar (no sabe la BD, la clave,
REM     ni nada). Si falta, lo crea desde .env.example y genera clave.
if not exist ".env" (
    echo.
    echo   [!] No se encontro el archivo .env
    echo.
    if exist ".env.example" (
        echo   Creando .env desde .env.example...
        copy .env.example .env >nul
        php artisan key:generate --force >nul 2>&1
        echo   .env creado. Edita las variables de base de datos si es necesario.
        echo   Archivo: %cd%\.env
        echo.
        echo   Presiona una tecla para continuar o cierra esta ventana para editar primero.
        pause >nul
    ) else (
        echo   [ERROR] Tampoco existe .env.example
        echo   No se puede arrancar sin configuracion.
        echo   Copia un .env valido a: %cd%\
        echo.
        pause
        exit /b 1
    )
)

REM --- Por si el servicio de Windows quedo encendido y roba el puerto ---
net stop MySQL >nul 2>&1
net stop MariaDB >nul 2>&1

REM --- Buscar y abrir Laragon (enciende la base de datos) ----------
set "LARAGON="
if exist "C:\laragon\laragon.exe" set "LARAGON=C:\laragon\laragon.exe"
if exist "D:\laragon\laragon.exe" set "LARAGON=D:\laragon\laragon.exe"

if defined LARAGON (
    echo   Abriendo Laragon...
    start "" "%LARAGON%"
    echo   Esperando a que la base de datos encienda...
    timeout /t 6 >nul
) else (
    echo.
    echo   [!] Laragon no encontrado en C:\laragon\ ni D:\laragon\
    echo       Si la base de datos ya esta encendida por otro medio, no hay problema.
    echo       Si no, PUNTA mostrara errores de conexion.
    echo.
    timeout /t 3 >nul
)

REM --- Abrir el navegador en la pagina del proyecto ----------------
start http://127.0.0.1:8000

REM --- Encender el servidor de Laravel -----------------------------
echo.
echo ============================================================
echo   PUNTA esta corriendo en: http://127.0.0.1:8000
echo   Deja esta ventana abierta mientras trabajas.
echo   Para apagar el sistema, cierra esta ventana.
echo ============================================================
echo.
php artisan serve
if errorlevel 1 (
    echo.
    echo   [ERROR] El servidor no pudo arrancar.
    echo   Revisa que PHP este en el PATH y que el .env este bien configurado.
    echo.
    pause
)
