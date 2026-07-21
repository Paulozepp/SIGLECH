@echo off
REM ============================================================================
REM SIGLECH - Script de Instalación para Windows
REM
REM Uso: Double-click o ejecutar en CMD
REM       install-siglech.bat
REM
REM Este script:
REM 1. Verifica que MySQL está corriendo
REM 2. Ejecuta el schema SQL
REM 3. Crea la base de datos
REM 4. Inserta datos iniciales
REM ============================================================================

setlocal enabledelayedexpansion

echo.
echo ============================================================================
echo                    SIGLECH - Instalador para Windows
echo ============================================================================
echo.

REM Verificar que MySQL está disponible
echo [1/4] Verificando MySQL...
mysql --version >nul 2>&1

if errorlevel 1 (
    echo.
    echo ERROR: MySQL no está en el PATH o no está instalado
    echo.
    echo Soluciones:
    echo 1. Asegúrate que XAMPP está corriendo (Apache + MySQL)
    echo 2. Agrega MySQL al PATH de Windows:
    echo    C:\xampp\mysql\bin
    echo 3. O ejecuta desde el directorio bin:
    echo    C:\xampp\mysql\bin\mysql.exe -u root
    echo.
    pause
    exit /b 1
)

echo     ✓ MySQL encontrado

REM Obtener la ruta del archivo schema.sql
set SCRIPT_DIR=%~dp0
set SCHEMA_FILE=%SCRIPT_DIR%database\schema.sql

echo.
echo [2/4] Verificando archivo schema.sql...

if not exist "%SCHEMA_FILE%" (
    echo.
    echo ERROR: No se encontró %SCHEMA_FILE%
    echo.
    pause
    exit /b 1
)

echo     ✓ Schema.sql encontrado: %SCHEMA_FILE%

REM Crear directorio temporal para el script SQL
set TEMP_SCRIPT=%SCRIPT_DIR%\temp_install.sql

echo.
echo [3/4] Preparando instalación...

REM Crear script SQL con contraseña vacía
(
    echo -- Installer automático SIGLECH
    echo CREATE DATABASE IF NOT EXISTS sicoch_referencia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
    echo USE sicoch_referencia;
) > "%TEMP_SCRIPT%"

REM Agregar el contenido de schema.sql
type "%SCHEMA_FILE%" >> "%TEMP_SCRIPT%"

echo     ✓ Script SQL preparado

REM Ejecutar el script
echo.
echo [4/4] Ejecutando instalación...
echo     (Esto puede tomar algunos segundos...)
echo.

mysql -u root < "%TEMP_SCRIPT%" >nul 2>&1

if errorlevel 1 (
    echo.
    echo ============================================================================
    echo ERROR: No se pudo ejecutar la instalación
    echo ============================================================================
    echo.
    echo Posibles causas:
    echo 1. MySQL no está corriendo (inicia XAMPP)
    echo 2. Credenciales incorrectas en config.php
    echo 3. Problemas de permisos de archivos
    echo.
    echo Intenta ejecutar manualmente:
    echo   cd C:\xampp\htdocs\SIGLECH
    echo   mysql -u root < database\schema.sql
    echo.
    del "%TEMP_SCRIPT%" >nul 2>&1
    pause
    exit /b 1
)

echo     ✓ Instalación completada

REM Limpiar archivo temporal
del "%TEMP_SCRIPT%" >nul 2>&1

REM Mostrar resultado
echo.
echo ============================================================================
echo                      ✅ INSTALACIÓN EXITOSA
echo ============================================================================
echo.
echo SIGLECH se ha instalado correctamente en:
echo   %SCRIPT_DIR%
echo.
echo Datos de acceso:
echo   URL:        http://localhost/SIGLECH
echo   Usuario:    admin
echo   Contraseña: admin
echo.
echo ⚠️  IMPORTANTE: Cambia la contraseña en el primer login
echo.
echo Próximos pasos:
echo 1. Abre http://localhost/SIGLECH en tu navegador
echo 2. Login con admin/admin
echo 3. Cambia la contraseña
echo.
echo ============================================================================
echo.

pause
