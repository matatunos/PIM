#!/bin/bash

##############################################################################
# Script de Verificación Post-Instalación
# Valida que toda la integración Open WebUI fue instalada correctamente
##############################################################################

echo ""
echo "╔═══════════════════════════════════════════════════════════════════════╗"
echo "║  VERIFICACIÓN POST-INSTALACIÓN PIM + Open WebUI                      ║"
echo "╚═══════════════════════════════════════════════════════════════════════╝"
echo ""

ERRORS=0
WARNINGS=0

# Color functions
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

check_pass() { echo -e "${GREEN}✓${NC} $1"; }
check_fail() { echo -e "${RED}✗${NC} $1"; ERRORS=$((ERRORS+1)); }
check_warn() { echo -e "${YELLOW}⚠${NC} $1"; WARNINGS=$((WARNINGS+1)); }

echo "1️⃣  VERIFICANDO ARCHIVOS CREADOS:"
echo ""

# Check files
[ -f /opt/PIM/api/ai-documents.php ] && check_pass "/api/ai-documents.php" || check_fail "/api/ai-documents.php"
[ -f /opt/PIM/app/ai-assistant.php ] && check_pass "/app/ai-assistant.php" || check_fail "/app/ai-assistant.php"
[ -f /opt/PIM/app/admin/test-openwebui.php ] && check_pass "/app/admin/test-openwebui.php" || check_fail "/app/admin/test-openwebui.php"
[ -f /opt/PIM/bin/sync-openwebui.sh ] && check_pass "/bin/sync-openwebui.sh" || check_fail "/bin/sync-openwebui.sh"
[ -f /opt/PIM/bin/setup-openwebui-sync.sh ] && check_pass "/bin/setup-openwebui-sync.sh" || check_fail "/bin/setup-openwebui-sync.sh"
[ -f /opt/PIM/OPEN_WEBUI_INTEGRATION.md ] && check_pass "OPEN_WEBUI_INTEGRATION.md" || check_fail "OPEN_WEBUI_INTEGRATION.md"

echo ""
echo "2️⃣  VERIFICANDO PERMISOS DE SCRIPTS:"
echo ""

[ -x /opt/PIM/bin/sync-openwebui.sh ] && check_pass "sync-openwebui.sh es ejecutable" || check_fail "sync-openwebui.sh NO es ejecutable"
[ -x /opt/PIM/bin/setup-openwebui-sync.sh ] && check_pass "setup-openwebui-sync.sh es ejecutable" || check_fail "setup-openwebui-sync.sh NO es ejecutable"

echo ""
echo "3️⃣  VERIFICANDO SINTAXIS PHP:"
echo ""

php -l /opt/PIM/api/ai-documents.php > /dev/null 2>&1 && check_pass "ai-documents.php" || check_fail "ai-documents.php (error de sintaxis)"
php -l /opt/PIM/app/ai-assistant.php > /dev/null 2>&1 && check_pass "ai-assistant.php" || check_fail "ai-assistant.php (error de sintaxis)"
php -l /opt/PIM/app/admin/test-openwebui.php > /dev/null 2>&1 && check_pass "test-openwebui.php" || check_fail "test-openwebui.php (error de sintaxis)"

echo ""
echo "4️⃣  VERIFICANDO SINTAXIS BASH:"
echo ""

bash -n /opt/PIM/bin/sync-openwebui.sh > /dev/null 2>&1 && check_pass "sync-openwebui.sh" || check_fail "sync-openwebui.sh (error de sintaxis)"
bash -n /opt/PIM/bin/setup-openwebui-sync.sh > /dev/null 2>&1 && check_pass "setup-openwebui-sync.sh" || check_fail "setup-openwebui-sync.sh (error de sintaxis)"

echo ""
echo "5️⃣  VERIFICANDO CONFIGURACIÓN:"
echo ""

if [ -f /opt/PIM/.env ]; then
    check_pass ".env existe"
    grep -q "JWT_SECRET=" /opt/PIM/.env && check_pass "JWT_SECRET configurado" || check_warn "JWT_SECRET no configurado"
    grep -q "OPENWEBUI_API_KEY=" /opt/PIM/.env && check_pass "OPENWEBUI_API_KEY existe" || check_warn "OPENWEBUI_API_KEY no configurado"
else
    check_fail ".env no existe"
fi

echo ""
echo "6️⃣  VERIFICANDO BASE DE DATOS:"
echo ""

if command -v mysql &> /dev/null; then
    # Try to connect to database
    if source /opt/PIM/.env 2>/dev/null; then
        mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SELECT 1" > /dev/null 2>&1
        if [ $? -eq 0 ]; then
            check_pass "Conectividad a BD"
            
            # Check tables
            mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SHOW TABLES LIKE 'configuracion_ia'" | grep -q configuracion_ia && \
                check_pass "Tabla configuracion_ia" || check_warn "Tabla configuracion_ia no existe (ejecuta setup)"
            
            mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SHOW TABLES LIKE 'chat_sessions'" | grep -q chat_sessions && \
                check_pass "Tabla chat_sessions" || check_warn "Tabla chat_sessions no existe"
            
            mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "SHOW TABLES LIKE 'sync_history'" | grep -q sync_history && \
                check_pass "Tabla sync_history" || check_warn "Tabla sync_history no existe"
        else
            check_warn "No se pudo conectar a BD (credenciales en .env?)"
        fi
    else
        check_warn "No se pudo cargar .env"
    fi
else
    check_warn "mysql CLI no disponible"
fi

echo ""
echo "7️⃣  VERIFICANDO DIRECTORIOS DE LOGS:"
echo ""

[ -d /opt/PIM/logs ] && check_pass "/opt/PIM/logs existe" || check_warn "/opt/PIM/logs no existe (se creará automáticamente)"

echo ""
echo "8️⃣  VERIFICANDO CRONTAB:"
echo ""

if [ -f /etc/cron.d/pim-sync-openwebui ]; then
    check_pass "Entrada de cron existe"
    grep -q "sync-openwebui.sh" /etc/cron.d/pim-sync-openwebui && \
        check_pass "Sincronización en cron" || check_fail "Script de cron mal configurado"
else
    check_warn "Entrada de cron no existe (ejecuta setup)"
fi

echo ""
echo "9️⃣  VERIFICANDO DEPENDENCIAS DEL SISTEMA:"
echo ""

command -v curl > /dev/null && check_pass "curl instalado" || check_fail "curl NO instalado (requerido)"
command -v jq > /dev/null && check_pass "jq instalado" || check_fail "jq NO instalado (requerido)"
command -v openssl > /dev/null && check_pass "openssl instalado" || check_fail "openssl NO instalado"
command -v cron > /dev/null || command -v crond > /dev/null && check_pass "cron daemon disponible" || check_warn "cron daemon NO encontrado"

echo ""
echo "🔟 VERIFICANDO ARCHIVOS MODIFICADOS:"
echo ""

if grep -q "JWT_SECRET\|OPENWEBUI_API_KEY" /opt/PIM/config/database.php; then
    check_pass "config/database.php actualizado"
else
    check_fail "config/database.php NO actualizado"
fi

if grep -q "chat_sessions\|configuracion_ia" /opt/PIM/db/schema.sql; then
    check_pass "db/schema.sql actualizado"
else
    check_fail "db/schema.sql NO actualizado"
fi

if grep -q "openwebui" /opt/PIM/app/admin/configuracion.php; then
    check_pass "app/admin/configuracion.php actualizado"
else
    check_fail "app/admin/configuracion.php NO actualizado"
fi

if grep -q "Chat IA\|ai-assistant" /opt/PIM/includes/sidebar.php; then
    check_pass "includes/sidebar.php actualizado"
else
    check_fail "includes/sidebar.php NO actualizado"
fi

echo ""
echo "════════════════════════════════════════════════════════════════════════"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ VERIFICACIÓN COMPLETADA SIN ERRORES${NC}"
    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}⚠️  Hay $WARNINGS advertencia(s) (ver arriba)${NC}"
    fi
else
    echo -e "${RED}❌ SE ENCONTRARON $ERRORS ERROR(ES)${NC}"
    echo ""
    echo "Soluciones:"
    echo "1. Ejecutar: sudo bash /opt/PIM/bin/setup-openwebui-sync.sh"
    echo "2. O revisar pasos en: /opt/PIM/OPEN_WEBUI_INTEGRATION.md"
fi

echo ""
echo "RESUMEN:"
echo "  Errores: $ERRORS"
echo "  Advertencias: $WARNINGS"
echo ""

exit $ERRORS
