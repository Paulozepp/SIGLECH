#!/bin/bash

################################################################################
#
# SIGLECH - Script de Instalación para Linux/Mac
#
# Uso:
#   chmod +x install-siglech.sh
#   ./install-siglech.sh
#
# Este script:
# 1. Verifica que MySQL está disponible
# 2. Ejecuta el schema SQL
# 3. Crea la base de datos
# 4. Inserta datos iniciales
#
################################################################################

set -e  # Exit on error

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Obtener directorio del script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
SCHEMA_FILE="$SCRIPT_DIR/database/schema.sql"

echo ""
echo "============================================================================"
echo "                    SIGLECH - Instalador para Linux/Mac"
echo "============================================================================"
echo ""

# ============================================================================
# Paso 1: Verificar MySQL
# ============================================================================

echo -e "${BLUE}[1/4] Verificando MySQL...${NC}"

if ! command -v mysql &> /dev/null; then
    echo ""
    echo -e "${RED}ERROR: MySQL no está instalado o no está en el PATH${NC}"
    echo ""
    echo "Soluciones:"
    echo ""
    echo "En Ubuntu/Debian:"
    echo "  sudo apt-get install mysql-server mysql-client"
    echo ""
    echo "En macOS (con Homebrew):"
    echo "  brew install mysql"
    echo "  brew services start mysql"
    echo ""
    exit 1
fi

MYSQL_VERSION=$(mysql --version)
echo -e "${GREEN}    ✓ MySQL encontrado: $MYSQL_VERSION${NC}"

# ============================================================================
# Paso 2: Verificar schema.sql
# ============================================================================

echo ""
echo -e "${BLUE}[2/4] Verificando archivo schema.sql...${NC}"

if [ ! -f "$SCHEMA_FILE" ]; then
    echo ""
    echo -e "${RED}ERROR: No se encontró $SCHEMA_FILE${NC}"
    echo ""
    exit 1
fi

echo -e "${GREEN}    ✓ Schema.sql encontrado: $SCHEMA_FILE${NC}"

# ============================================================================
# Paso 3: Crear script temporal
# ============================================================================

echo ""
echo -e "${BLUE}[3/4] Preparando instalación...${NC}"

TEMP_SCRIPT="/tmp/siglech_install_$$.sql"

# Crear cabecera del script SQL
cat > "$TEMP_SCRIPT" << 'EOF'
-- Instalador automático SIGLECH
CREATE DATABASE IF NOT EXISTS sicoch_referencia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sicoch_referencia;
EOF

# Agregar el contenido de schema.sql
cat "$SCHEMA_FILE" >> "$TEMP_SCRIPT"

echo -e "${GREEN}    ✓ Script SQL preparado: $TEMP_SCRIPT${NC}"

# ============================================================================
# Paso 4: Ejecutar instalación
# ============================================================================

echo ""
echo -e "${BLUE}[4/4] Ejecutando instalación...${NC}"
echo "    (Esto puede tomar algunos segundos...)"
echo ""

# Intentar conectar como root sin contraseña
if mysql -u root < "$TEMP_SCRIPT" 2>/dev/null; then
    # Éxito
    :
else
    # Intentar con prompt de contraseña
    echo -e "${YELLOW}Se requiere contraseña de MySQL:${NC}"
    mysql -u root -p < "$TEMP_SCRIPT"
fi

echo -e "${GREEN}    ✓ Instalación completada${NC}"

# Limpiar archivo temporal
rm -f "$TEMP_SCRIPT"

# ============================================================================
# Mostrar resumen
# ============================================================================

echo ""
echo "============================================================================"
echo -e "${GREEN}                      ✅ INSTALACIÓN EXITOSA${NC}"
echo "============================================================================"
echo ""
echo "SIGLECH se ha instalado correctamente en:"
echo "  $SCRIPT_DIR"
echo ""
echo "Datos de acceso:"
echo "  URL:        http://localhost/SIGLECH"
echo "  Usuario:    admin"
echo "  Contraseña: admin"
echo ""
echo -e "${YELLOW}⚠️  IMPORTANTE: Cambia la contraseña en el primer login${NC}"
echo ""
echo "Próximos pasos:"
echo "1. Abre http://localhost/SIGLECH en tu navegador"
echo "2. Login con admin/admin"
echo "3. Cambia la contraseña"
echo ""
echo "============================================================================"
echo ""
