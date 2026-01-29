#!/bin/bash

##############################################################################
# Script de Sincronización PIM <-> Open WebUI
# Ubicación: /opt/PIM/bin/sync-openwebui.sh
# 
# Propósito:
# - Obtiene documentos y notas desde PIM vía API
# - Los ingiera en Open WebUI automáticamente
# - Se ejecuta cada X minutos vía cron
# 
# Configuración: Lee desde base de datos PIM
# - openwebui_host, openwebui_port (table: configuracion_ia)
# - OPENWEBUI_API_KEY (desde .env)
##############################################################################

set -euo pipefail


# Directorios
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PIM_ROOT="$(dirname "$SCRIPT_DIR")"
CONFIG_FILE="$PIM_ROOT/.env"
LOG_DIR="$PIM_ROOT/logs"
LOG_FILE="$LOG_DIR/sync-openwebui.log"
LOCK_FILE="/tmp/pim-sync.lock"
COOKIE_JAR=""

# Crear directorio de logs si no existe
mkdir -p "$LOG_DIR"

# Funciones de logging
log_info() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [INFO] $1" | tee -a "$LOG_FILE"
}

log_error() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [ERROR] $1" | tee -a "$LOG_FILE" >&2
}

log_success() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [SUCCESS] $1" | tee -a "$LOG_FILE"
}

# Lock para evitar múltiples instancias
acquire_lock() {
    if [ -f "$LOCK_FILE" ]; then
        local lock_age=$(($(date +%s) - $(stat -f%m "$LOCK_FILE" 2>/dev/null || stat -c%Y "$LOCK_FILE")))
        if [ "$lock_age" -lt 300 ]; then  # 5 minutos
            log_error "Sincronización ya en progreso (lock file existe)"
            exit 1
        else
            log_info "Removiendo lock file expirado"
            rm -f "$LOCK_FILE"
        fi
    fi
    touch "$LOCK_FILE"
    trap 'rm -f "$LOCK_FILE"' EXIT
}

# Cargar variables de .env
load_config() {
    if [ ! -f "$CONFIG_FILE" ]; then
        log_error "Archivo .env no encontrado: $CONFIG_FILE"
        exit 1
    fi
    
    export $(grep -v '^#' "$CONFIG_FILE" | grep -v '^$' | xargs)
    
    # Validar variables críticas
    if [ -z "${OPENWEBUI_API_KEY:-}" ]; then
        log_error "OPENWEBUI_API_KEY no está configurada en .env"
        exit 1
    fi
}

# Obtener configuración desde BD
get_db_config() {
    local query="SELECT clave, valor FROM configuracion_ia WHERE clave IN ('openwebui_host', 'openwebui_port', 'sync_documents', 'sync_notes');"
    
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -e "$query" -N > /tmp/config.tmp 2>/dev/null || {
        log_error "No se puede conectar a la base de datos"
        return 1
    }
    
    while IFS=$'\t' read -r key value; do
        case "$key" in
            openwebui_host) OPENWEBUI_HOST="$value" ;;
            openwebui_port) OPENWEBUI_PORT="$value" ;;
            sync_documents) SYNC_DOCUMENTS="$value" ;;
            sync_notes) SYNC_NOTES="$value" ;;
        esac
    done < /tmp/config.tmp
    
    rm -f /tmp/config.tmp
    
    # Valores por defecto
    OPENWEBUI_HOST="${OPENWEBUI_HOST:-192.168.1.19}"
    OPENWEBUI_PORT="${OPENWEBUI_PORT:-3000}"
    SYNC_DOCUMENTS="${SYNC_DOCUMENTS:-1}"
    SYNC_NOTES="${SYNC_NOTES:-1}"
}

# Validar conectividad con Open WebUI
check_connectivity() {
    log_info "Verificando conectividad con Open WebUI ($OPENWEBUI_HOST:$OPENWEBUI_PORT)..."
    
    local health_url="http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/api/health"
    local response=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "$health_url")
    
    if [ "$response" = "200" ] || [ "$response" = "404" ]; then
        log_success "✓ Open WebUI es accesible"
        return 0
    else
        log_error "✗ Open WebUI no responde (HTTP $response)"
        return 1
    fi
}

# Obtener documentos desde PIM API
fetch_documents() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [INFO] Obteniendo documentos de PIM..." >> "$LOG_FILE"
    local api_url="http://localhost/api/ai-documents.php?action=get_documents&api_key=localtest"
    local response=$(curl -s "$api_url")
    echo "$response" > "$LOG_DIR/last_documents_response.json"
    # Validar respuesta JSON
    if ! echo "$response" | jq . >/dev/null 2>&1; then
        log_error "Respuesta inválida de API de documentos. Guardado en $LOG_DIR/last_documents_response.json"
        return 1
    fi
    echo "$response"
}

# Obtener notas desde PIM API
fetch_notes() {
    echo "[$(date +'%Y-%m-%d %H:%M:%S')] [INFO] Obteniendo notas de PIM..." >> "$LOG_FILE"
    local api_url="http://localhost/api/ai-documents.php?action=get_notes&api_key=localtest"
    local response=$(curl -s "$api_url")
    echo "$response" > "$LOG_DIR/last_notes_response.json"
    # Validar respuesta JSON
    if ! echo "$response" | jq . >/dev/null 2>&1; then
        log_error "Respuesta inválida de API de notas. Guardado en $LOG_DIR/last_notes_response.json"
        return 1
    fi
    echo "$response"
}

# Ingerir documentos en Open WebUI
ingest_documents() {
    local documents="$1"
    local count=$(echo "$documents" | jq '.total')
    
    if [ "$count" -eq 0 ]; then
        log_info "No hay documentos nuevos para sincronizar"
        return 0
    fi
    
    log_info "Ingiriendo $count documentos en Open WebUI..."
    
    # Iterar sobre documentos
    echo "$documents" | jq -r '.data[] | @json' | while read -r doc; do
        local nombre=$(echo "$doc" | jq -r '.nombre')
        local doc_id=$(echo "$doc" | jq -r '.id')
        local openwebui_url="http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/api/v1/files/"
        
        # Obtener ruta del archivo desde la BD
        local ruta=$(mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" -N -e "SELECT ruta FROM archivos WHERE id = $doc_id;" 2>/dev/null)
        
        if [ -z "$ruta" ] || [ ! -f "$ruta" ]; then
            log_error "Archivo no encontrado: $nombre (ruta: $ruta)"
            continue
        fi
        
        # Subir archivo real a Open WebUI
        local response=$(curl -s -X POST \
            -H "Authorization: Bearer $OPENWEBUI_API_KEY" \
            -F "file=@$ruta;filename=$nombre" \
            "$openwebui_url" || echo '{"error": "Connection failed"}')
        
        if echo "$response" | grep -q '"error"'; then
            log_error "Error ingiriendo documento: $nombre"
        elif echo "$response" | grep -q '"id"'; then
            local file_id=$(echo "$response" | jq -r '.id')
            log_info "✓ Documento subido: $nombre (ID: $file_id)"
            
            # Procesar archivo para RAG (extracción de texto)
            local process_url="http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/api/v1/files/$file_id/process"
            curl -s -X POST \
                -H "Authorization: Bearer $OPENWEBUI_API_KEY" \
                "$process_url" >/dev/null 2>&1
        else
            log_error "Respuesta inesperada para: $nombre"
        fi
    done
    
    return 0
}

# Ingerir notas en Open WebUI (como archivos .txt)
ingest_notes() {
    local notes="$1"
    local count=$(echo "$notes" | jq '.total')
    
    if [ "$count" -eq 0 ]; then
        log_info "No hay notas nuevas para sincronizar"
        return 0
    fi
    
    log_info "Ingiriendo $count notas en Open WebUI..."
    
    # Crear directorio temporal para notas
    local tmp_dir=$(mktemp -d)
    trap "rm -rf $tmp_dir" EXIT
    
    # Iterar sobre notas
    echo "$notes" | jq -r '.data[] | @json' | while read -r note; do
        local titulo=$(echo "$note" | jq -r '.titulo // "Sin título"')
        local contenido=$(echo "$note" | jq -r '.contenido')
        local nota_id=$(echo "$note" | jq -r '.id')
        local openwebui_url="http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/api/v1/files/"
        
        # Crear archivo temporal con el contenido de la nota
        local safe_name=$(echo "$titulo" | sed 's/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ _-]/_/g' | head -c 100)
        local tmp_file="$tmp_dir/${safe_name}.txt"
        
        # Escribir contenido con título como cabecera
        echo "# $titulo" > "$tmp_file"
        echo "" >> "$tmp_file"
        echo "$contenido" >> "$tmp_file"
        
        # Subir como archivo a Open WebUI
        local response=$(curl -s -X POST \
            -H "Authorization: Bearer $OPENWEBUI_API_KEY" \
            -F "file=@$tmp_file;filename=${safe_name}.txt" \
            "$openwebui_url" || echo '{"error": "Connection failed"}')
        
        if echo "$response" | grep -q '"error"'; then
            log_error "Error ingiriendo nota: $titulo"
        elif echo "$response" | grep -q '"id"'; then
            local file_id=$(echo "$response" | jq -r '.id')
            log_info "✓ Nota subida: $titulo"
            
            # Procesar archivo para RAG
            local process_url="http://$OPENWEBUI_HOST:$OPENWEBUI_PORT/api/v1/files/$file_id/process"
            curl -s -X POST \
                -H "Authorization: Bearer $OPENWEBUI_API_KEY" \
                "$process_url" >/dev/null 2>&1
        else
            log_info "✓ Nota ingirida: $titulo"
        fi
        
        rm -f "$tmp_file"
    done
    
    return 0
}

# Registrar historial de sincronización en BD
register_sync_history() {
    local status="$1"
    local mensaje="$2"
    local docs_count="${3:-0}"
    
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" <<EOF
INSERT INTO sync_history (tipo, status, mensaje, documentos_procesados, sincronizado_en)
VALUES ('documento', '$status', '$mensaje', $docs_count, NOW());
EOF
}

# MAIN
main() {
    log_info "=== Iniciando sincronización PIM <-> Open WebUI ==="
    
    acquire_lock
    load_config
    get_db_config
    
    # Validar conectividad
    if ! check_connectivity; then
        log_error "Sincronización cancelada: Open WebUI no es accesible"
        register_sync_history "failed" "Open WebUI no accesible"
        exit 1
    fi
    
    # Sincronizar documentos
    if [ "$SYNC_DOCUMENTS" = "1" ]; then
        local docs=$(fetch_documents) || {
            log_error "Error obteniendo documentos"
            register_sync_history "failed" "Error obteniendo documentos"
            exit 1
        }
        
        ingest_documents "$docs"
    fi
    
    # Sincronizar notas
    if [ "$SYNC_NOTES" = "1" ]; then
        local notes=$(fetch_notes) || {
            log_error "Error obteniendo notas"
            register_sync_history "failed" "Error obteniendo notas"
            exit 1
        }
        
        ingest_notes "$notes"
    fi
    
    log_success "=== Sincronización completada exitosamente ==="
    register_sync_history "success" "Sincronización completada"
}

main "$@"
