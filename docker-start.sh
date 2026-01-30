#!/bin/bash
# Script de inicio rápido para PIM con Docker

set -e

echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║          🚀 PIM - Docker Quick Start                         ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verificar Docker
if ! command -v docker &> /dev/null; then
    echo -e "${RED}❌ Error: Docker no está instalado${NC}"
    echo "Instala Docker desde: https://docs.docker.com/get-docker/"
    exit 1
fi

# Verificar Docker Compose
if ! command -v docker compose &> /dev/null; then
    echo -e "${RED}❌ Error: Docker Compose no está instalado${NC}"
    exit 1
fi

echo -e "${GREEN}✅ Docker y Docker Compose detectados${NC}"
echo ""

# Verificar si existe .env
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚠️  No existe archivo .env${NC}"
    echo ""
    read -p "¿Quieres crear uno desde .env.docker? (s/n): " -n 1 -r
    echo ""
    if [[ $REPLY =~ ^[Ss]$ ]]; then
        cp .env.docker .env
        echo -e "${GREEN}✅ Archivo .env creado${NC}"
        echo -e "${YELLOW}⚠️  IMPORTANTE: Edita .env y cambia las contraseñas antes de continuar${NC}"
        echo ""
        read -p "Presiona ENTER cuando hayas editado .env..."
    else
        echo -e "${RED}❌ Abortado. Crea un archivo .env antes de continuar${NC}"
        exit 1
    fi
fi

echo -e "${GREEN}✅ Archivo .env encontrado${NC}"
echo ""

# Preguntar si quiere servicios de IA
echo -e "${BLUE}❓ ¿Deseas incluir servicios de IA (Ollama + Open WebUI)?${NC}"
echo "   Esto añade ~5GB de espacio en disco"
read -p "   (s/n): " -n 1 -r
echo ""
AI_PROFILE=""
if [[ $REPLY =~ ^[Ss]$ ]]; then
    AI_PROFILE="--profile ai"
    echo -e "${GREEN}✅ Servicios de IA serán incluidos${NC}"
else
    echo -e "${YELLOW}⏭️  Servicios de IA omitidos${NC}"
fi
echo ""

# Construir e iniciar contenedores
echo -e "${BLUE}🔨 Construyendo imágenes...${NC}"
docker compose build

echo ""
echo -e "${BLUE}🚀 Iniciando contenedores...${NC}"
docker compose $AI_PROFILE up -d

echo ""
echo -e "${GREEN}✅ Contenedores iniciados${NC}"
echo ""

# Esperar a que la base de datos esté lista
echo -e "${BLUE}⏳ Esperando a que la base de datos esté lista...${NC}"
sleep 10

# Mostrar estado
echo ""
echo -e "${BLUE}📊 Estado de los contenedores:${NC}"
docker compose ps

echo ""
echo "╔═══════════════════════════════════════════════════════════════╗"
echo "║          ✅ PIM está listo                                    ║"
echo "╚═══════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${GREEN}🌐 Accede a PIM en:${NC} http://localhost:8080"
echo ""
echo -e "${YELLOW}👤 Usuario por defecto:${NC}"
echo "   Usuario: admin"
echo "   Contraseña: admin123"
echo ""

if [[ $AI_PROFILE == *"ai"* ]]; then
    echo -e "${GREEN}🤖 Open WebUI:${NC} http://localhost:3000"
    echo ""
fi

echo -e "${BLUE}📝 Comandos útiles:${NC}"
echo "   Ver logs:        docker compose logs -f"
echo "   Parar:           docker compose down"
echo "   Reiniciar:       docker compose restart"
echo "   Ver estado:      docker compose ps"
echo ""
echo -e "${YELLOW}⚠️  No olvides:${NC}"
echo "   1. Cambiar la contraseña del admin"
echo "   2. Configurar 2FA"
echo "   3. Crear tu primer usuario"
echo ""
