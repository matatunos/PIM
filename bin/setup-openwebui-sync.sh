#!/bin/bash

##############################################################################
# Script de Instalación y Configuración Open WebUI Integration
# Ubicación: /opt/PIM/bin/setup-openwebui-sync.sh
# 
# Propósito:
# - Configura la integración de Open WebUI en PIM
# - Pregunta al usuario por los parámetros
# - Actualiza .env con claves de seguridad
# - Crea entrada en crontab para sincronización automática
# - Valida conectividad con Open WebUI
##############################################################################

set -euo pipefail

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directorios
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PIM_ROOT="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PIM_ROOT/.env"
CRON_FILE="/etc/cron.d/pim-sync-openwebui"

# Función de utilidad
print_header() {
    echo -e "\n${BLUE}=== $1 ===${NC}\n"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Validar que se ejecuta como root o con sudo
check_permissions() {
    if [ "$EUID" -ne 0 ]; then
        print_error "Este script debe ejecutarse con permisos de root (sudo)"
        exit 1
    fi
}

# Cargar valores actuales de .env
load_current_values() {
    if [ -f "$ENV_FILE" ]; then
        source <(grep -E '^(JWT_SECRET|OPENWEBUI_API_KEY)=' "$ENV_FILE")
    fi
}

# Generar JWT_SECRET si no existe
generate_jwt_secret() {
    if [ -z "${JWT_SECRET:-}" ]; then
        JWT_SECRET=$(openssl rand -base64 32)
        print_success "JWT_SECRET generado automáticamente"
    else
        print_info "JWT_SECRET ya configurado"
    fi
}

# Obtener configuración de Open WebUI
ask_openwebui_config() {
    print_header "Configuración de Open WebUI"
    
    echo "Proporciona los detalles de tu instancia de Open WebUI:"
    echo ""
    
    # Host
    read -p "Host/IP de Open WebUI [192.168.1.19]: " OPENWEBUI_HOST
    OPENWEBUI_HOST="${OPENWEBUI_HOST:-192.168.1.19}"
    
    # Puerto
    read -p "Puerto de Open WebUI [3000]: " OPENWEBUI_PORT
    OPENWEBUI_PORT="${OPENWEBUI_PORT:-3000}"
    
    # API Key
    echo ""
    print_info "Se requiere un API Key de Open WebUI para sincronización automática"
    print_info "Puedes generarlo en: http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/admin (Settings > API Keys)"
    echo ""
    read -sp "API Key de Open WebUI (dejalo vacío si no tienes): " OPENWEBUI_API_KEY
    echo ""
    
    # Intervalo de sincronización
    read -p "Intervalo de sincronización en minutos [5]: " SYNC_INTERVAL
    SYNC_INTERVAL="${SYNC_INTERVAL:-5}"
}

# Validar valores
validate_config() {
    print_header "Validación de Configuración"
    
    # Validar host
    if [[ ! $OPENWEBUI_HOST =~ ^[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$ ]] && \
       [[ ! $OPENWEBUI_HOST =~ ^[a-zA-Z0-9.-]+$ ]]; then
        print_error "Host inválido: $OPENWEBUI_HOST"
        exit 1
    fi
    
    # Validar puerto
    if ! [[ "$OPENWEBUI_PORT" =~ ^[0-9]+$ ]] || [ "$OPENWEBUI_PORT" -lt 1 ] || [ "$OPENWEBUI_PORT" -gt 65535 ]; then
        print_error "Puerto inválido: $OPENWEBUI_PORT"
        exit 1
    fi
    
    # Validar intervalo
    if ! [[ "$SYNC_INTERVAL" =~ ^[0-9]+$ ]] || [ "$SYNC_INTERVAL" -lt 1 ] || [ "$SYNC_INTERVAL" -gt 1440 ]; then
        print_error "Intervalo inválido: $SYNC_INTERVAL"
        exit 1
    fi
    
    print_success "Configuración validada"
}

# Probar conectividad con Open WebUI
test_connectivity() {
    print_header "Prueba de Conectividad"
    
    print_info "Probando conexión con Open WebUI en $OPENWEBUI_HOST:$OPENWEBUI_PORT..."
    
    local health_url="http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/api/health"
    local response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "$health_url" || echo "000")
    
    if [ "$response" = "200" ] || [ "$response" = "404" ]; then
        print_success "Open WebUI es accesible en $OPENWEBUI_HOST:$OPENWEBUI_PORT"
        return 0
    else
        print_warning "No se pudo conectar a Open WebUI (HTTP $response)"
        echo ""
        read -p "¿Deseas continuar de todas formas? (s/n): " -n 1 -r
        echo
        if [[ $REPLY =~ ^[Ss]$ ]]; then
            return 0
        else
            return 1
        fi
    fi
}

# Actualizar archivo .env
update_env_file() {
    print_header "Actualización de .env"
    
    # Crear backup
    cp "$ENV_FILE" "$ENV_FILE.backup"
    print_success "Backup de .env creado: ${ENV_FILE}.backup"
    
    # Actualizar JWT_SECRET
    if grep -q "^JWT_SECRET=" "$ENV_FILE"; then
        sed -i.bak "s/^JWT_SECRET=.*/JWT_SECRET=$JWT_SECRET/" "$ENV_FILE"
    else
        echo "JWT_SECRET=$JWT_SECRET" >> "$ENV_FILE"
    fi
    
    # Actualizar OPENWEBUI_API_KEY (si proporcionado)
    if [ -n "$OPENWEBUI_API_KEY" ]; then
        if grep -q "^OPENWEBUI_API_KEY=" "$ENV_FILE"; then
            sed -i.bak "s/^OPENWEBUI_API_KEY=.*/OPENWEBUI_API_KEY=$OPENWEBUI_API_KEY/" "$ENV_FILE"
        else
            echo "OPENWEBUI_API_KEY=$OPENWEBUI_API_KEY" >> "$ENV_FILE"
        fi
    fi
    
    print_success ".env actualizado"
}

# Crear/actualizar entrada en crontab
setup_crontab() {
    print_header "Configuración de Cron"
    
    # Convertir minutos a formato cron
    local cron_interval=$SYNC_INTERVAL
    local cron_pattern="*/$cron_interval * * * *"
    
    # Crear contenido del archivo cron
    local cron_content="# PIM Open WebUI Synchronization
# Sincroniza documentos y notas cada $SYNC_INTERVAL minutos
$cron_pattern root /opt/PIM/bin/sync-openwebui.sh >> /opt/PIM/logs/cron-sync.log 2>&1
"
    
    # Crear archivo cron
    echo "$cron_content" > "$CRON_FILE"
    chmod 644 "$CRON_FILE"
    
    print_success "Entrada de cron creada: $CRON_FILE"
    print_info "Sincronización se ejecutará cada $SYNC_INTERVAL minutos"
}

# Crear tabla de configuración en BD
setup_database() {
    print_header "Configuración de Base de Datos"
    
    # Cargar variables de .env
    source "$ENV_FILE"
    
    # Crear tabla configuracion_ia (si no existe)
    local sql="
    CREATE TABLE IF NOT EXISTS configuracion_ia (
        id INT AUTO_INCREMENT PRIMARY KEY,
        clave VARCHAR(100) NOT NULL UNIQUE,
        valor TEXT,
        tipo VARCHAR(50) DEFAULT 'string',
        descripcion TEXT,
        actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_clave (clave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

    -- Insertar configuración de Open WebUI
    INSERT INTO configuracion_ia (clave, valor, tipo, descripcion) VALUES
    ('openwebui_host', '$OPENWEBUI_HOST', 'string', 'Host de Open WebUI'),
    ('openwebui_port', '$OPENWEBUI_PORT', 'int', 'Puerto de Open WebUI'),
    ('sync_interval_minutes', '$SYNC_INTERVAL', 'int', 'Intervalo de sincronización'),
    ('sync_enabled', '1', 'bool', 'Sincronización habilitada'),
    ('sync_documents', '1', 'bool', 'Sincronizar documentos'),
    ('sync_notes', '1', 'bool', 'Sincronizar notas')
    ON DUPLICATE KEY UPDATE
        valor = VALUES(valor),
        tipo = VALUES(tipo),
        descripcion = VALUES(descripcion);
    "
    
    # Ejecutar SQL
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" <<< "$sql" 2>/dev/null || {
        print_warning "No se pudo actualizar BD automáticamente"
        print_info "Ejecuta manualmente el SQL en tu gestor de BD"
        echo "$sql"
        return 1
    }
    
    print_success "Base de datos configurada"
}

# Mostrar resumen
show_summary() {
    print_header "Resumen de Configuración"
    
    cat << EOF
Open WebUI Integration - Configuración Completada

📍 Servidor Open WebUI:
   Host:     $OPENWEBUI_HOST
   Puerto:   $OPENWEBUI_PORT
   URL:      http://$OPENWEBUI_HOST:$OPENWEBUI_PORT

🔄 Sincronización:
   Intervalo: Cada $SYNC_INTERVAL minutos
   Script:    /opt/PIM/bin/sync-openwebui.sh
   Cron:      $CRON_FILE

🔐 Seguridad:
   JWT_SECRET: Configurado ✓
   API_KEY:    ${OPENWEBUI_API_KEY:+Configurado ✓}${OPENWEBUI_API_KEY:+Configurado ✓}${OPENWEBUI_API_KEY:- NO configurado (opcional)}

📱 Acceso:
   Widget Chat IA: http://localhost/app/ai-assistant.php
   Configuración:  http://localhost/app/admin/configuracion.php

📝 Logs:
   Sync:           /opt/PIM/logs/sync-openwebui.log
   Cron:           /opt/PIM/logs/cron-sync.log

EOF

    print_info "Próximos pasos:"
    echo "1. Abre http://localhost/app/ai-assistant.php para acceder al chat"
    echo "2. Visita http://localhost/app/admin/configuracion.php para ver todas las opciones"
    echo "3. Prueba la sincronización: sudo /opt/PIM/bin/sync-openwebui.sh"
    echo ""
}

# MAIN
main() {
    print_header "Instalador - PIM + Open WebUI Integration"
    
    check_permissions
    load_current_values
    generate_jwt_secret
    ask_openwebui_config
    validate_config
    
    if test_connectivity; then
        update_env_file
        setup_crontab
        setup_database
        show_summary
        print_success "¡Instalación completada!"
    else
        print_error "Instalación cancelada por validación de conectividad"
        exit 1
    fi
}

main "$@"
