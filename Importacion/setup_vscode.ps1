# 🚀 Setup automático para VSCode + Cargador CNE

Write-Host "`n╔════════════════════════════════════════╗" -ForegroundColor Magenta
Write-Host "║  🔧 SETUP - Cargador CNE en VSCode    ║" -ForegroundColor Magenta
Write-Host "╚════════════════════════════════════════╝`n" -ForegroundColor Magenta

# Paso 1: Instalar dependencias Python
Write-Host "📦 Instalando dependencias Python..." -ForegroundColor Cyan
pip install -r requirements.txt

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ Dependencias instaladas`n" -ForegroundColor Green
} else {
    Write-Host "❌ Error instalando dependencias" -ForegroundColor Red
    exit 1
}

# Paso 2: Verificar conexión MySQL
Write-Host "🔗 Verificando conexión a MySQL..." -ForegroundColor Cyan
python -c "import mysql.connector; conn = mysql.connector.connect(host='localhost', user='root', password='', database='siglech'); print('✅ Conexión OK'); conn.close()"

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ MySQL conectado`n" -ForegroundColor Green
} else {
    Write-Host "❌ No se puede conectar a MySQL" -ForegroundColor Red
    Write-Host "   Asegúrate que XAMPP está corriendo y siglech existe" -ForegroundColor Yellow
    exit 1
}

# Paso 3: Analizar CSV
Write-Host "📊 Analizando CSV..." -ForegroundColor Cyan
python analizar_csv.py

# Paso 4: Crear estructura de carpetas si no existe
$folders = @(".vscode", "logs", "backups")
foreach ($folder in $folders) {
    if (!(Test-Path $folder)) {
        New-Item -ItemType Directory -Path $folder -Force | Out-Null
        Write-Host "✅ Carpeta creada: $folder" -ForegroundColor Green
    }
}

# Paso 5: Resumen
Write-Host "`n╔════════════════════════════════════════╗" -ForegroundColor Green
Write-Host "║  ✅ SETUP COMPLETADO                   ║" -ForegroundColor Green
Write-Host "╚════════════════════════════════════════╝`n" -ForegroundColor Green

Write-Host "📖 Próximos pasos:" -ForegroundColor Yellow
Write-Host "  1. Abre VSCode en esta carpeta"
Write-Host "  2. Ctrl + Shift + D para ver Tasks"
Write-Host "  3. Selecciona: '📥 Cargar Demanda CNE (Chunking)'"
Write-Host "`n💡 Más info: Lee README_VSCODE.md`n"

# Paso 6: Preguntar si abrir VSCode
$openVSCode = Read-Host "¿Abrir VSCode en esta carpeta? (s/n)"
if ($openVSCode -eq "s" -or $openVSCode -eq "S") {
    code .
}
