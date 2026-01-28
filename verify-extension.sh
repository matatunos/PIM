#!/bin/bash

echo "🔍 Verificando estructura de la extensión Chrome..."
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Verificar archivos requeridos
files=(
    "chrome-extension/manifest.json"
    "chrome-extension/popup.html"
    "chrome-extension/popup.js"
    "chrome-extension/background.js"
    "chrome-extension/content-script.js"
    "chrome-extension/styles.css"
    "chrome-extension/README.md"
    "api/links.php"
    "app/links/index.php"
)

echo "📋 Archivos requeridos:"
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file (FALTA)"
    fi
done

echo ""
echo "🖼️  Archivos de iconos:"
for size in 16 48 128; do
    if [ -f "chrome-extension/images/icon-${size}.svg" ]; then
        echo -e "${GREEN}✓${NC} chrome-extension/images/icon-${size}.svg"
    else
        echo -e "${YELLOW}⚠${NC} chrome-extension/images/icon-${size}.svg (SVG - considera convertir a PNG)"
    fi
done

echo ""
echo "✅ Verificación completada!"
echo ""
echo "Próximos pasos:"
echo "1. Abre chrome://extensions/"
echo "2. Activa 'Modo de desarrollador'"
echo "3. Click en 'Cargar extensión sin empaquetar'"
echo "4. Selecciona la carpeta 'chrome-extension'"
echo "5. Configura la URL de tu PIM en el popup de la extensión"
echo ""
