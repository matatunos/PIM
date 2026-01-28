#!/bin/bash
# Script para generar PNGs desde SVGs (opcional, si tienes ImageMagick instalado)
# Si no tienes las herramientas, puedes usar cualquier convertidor online SVG a PNG
# y guardar los archivos como:
# - images/icon-16.png
# - images/icon-48.png
# - images/icon-128.png

echo "Para usar iconos PNG, convierte los archivos SVG:"
echo "1. Usa un convertidor online SVG a PNG (ej: cloudconvert.com)"
echo "2. O instala ImageMagick y ejecuta:"
echo "   convert -background none icon-16.svg -resize 16x16 icon-16.png"
echo "   convert -background none icon-48.svg -resize 48x48 icon-48.png"
echo "   convert -background none icon-128.svg -resize 128x128 icon-128.png"
echo ""
echo "Luego guarda los PNGs en la carpeta 'images/'"
