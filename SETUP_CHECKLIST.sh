#!/bin/bash

# Checklist de instalación y configuración
# Este script ayuda a verificar que todo está configurado correctamente

echo "=========================================="
echo "📋 Checklist de Configuración - PIM Links"
echo "=========================================="
echo ""

# Array de verificaciones
declare -a checks=(
    "Extensión Chrome cargada en chrome://extensions/"
    "URL de PIM configurada en el popup de la extensión"
    "Sesión activa en tu PIM (logged in)"
    "Carpeta /api/ existe y tiene links.php"
    "Carpeta chrome-extension/ en /opt/PIM/"
    "Base de datos contiene tabla 'links'"
)

echo "✅ Verificaciones necesarias:"
echo ""

for i in "${!checks[@]}"; do
    num=$((i + 1))
    echo "  [ ] $num. ${checks[$i]}"
done

echo ""
echo "=========================================="
echo "🚀 Características Disponibles:"
echo "=========================================="
echo ""
echo "1️⃣  DESDE EL NAVEGADOR"
echo "    - Click en icono de extensión"
echo "    - Se llena automáticamente título y URL"
echo "    - Personaliza si lo deseas y guarda"
echo ""

echo "2️⃣  MENÚ CONTEXTUAL"
echo "    - Click derecho en la página"
echo "    - Selecciona 'Guardar página en PIM'"
echo "    - Se guarda automáticamente"
echo ""

echo "3️⃣  ATAJO DE TECLADO"
echo "    - Presiona: Ctrl+Shift+L"
echo "    - Se abre el popup con los datos actuales"
echo ""

echo "4️⃣  DESDE LA WEB (Links)"
echo "    - Arrastra una URL a la zona de drop zone"
echo "    - Se extrae automáticamente el título"
echo "    - Se abre el formulario pre-rellenado"
echo ""

echo "=========================================="
echo "🔧 Solución de Problemas:"
echo "=========================================="
echo ""

echo "Problema: 'No autenticado'"
echo "  → Solución: Inicia sesión en tu PIM en una pestaña de Chrome"
echo ""

echo "Problema: 'Error de conexión'"
echo "  → Solución: Verifica que la URL de tu PIM es correcta"
echo "  → Comprueba que tu PIM es accesible desde internet"
echo ""

echo "Problema: 'El icono de la extensión no aparece'"
echo "  → Solución: Recarga la extensión en chrome://extensions/"
echo "  → O abre DevTools (F12) para ver los errores"
echo ""

echo "Problema: 'El título no se extrae'"
echo "  → Solución: Algunos sitios lo bloquean por CORS"
echo "  → Puedes escribir el título manualmente"
echo ""

echo "=========================================="
echo "📚 Documentación:"
echo "=========================================="
echo ""
echo "- CHROME_EXTENSION_SETUP.md → Documentación completa"
echo "- QUICK_START.md → Instalación rápida"
echo "- chrome-extension/README.md → Guía de la extensión"
echo ""

echo "=========================================="
echo "✨ ¡Todo listo para usar!"
echo "=========================================="
