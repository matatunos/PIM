#!/bin/bash

#############################################################################
# PIM Database Restore Script
# Restaura la base de datos desde un archivo de backup ZIP
# Uso: ./restore-db.sh /ruta/al/backup.zip
# Ejemplo: ./restore-db.sh /backups/pim/pim-backup-2026-01-29_093750.zip
#############################################################################

set -e

# Configuración
DB_HOST="localhost"
DB_USER="root"
DB_PASS="satriani"
DB_NAME="pim_db"

LOG_FILE="/var/log/pim-backup.log"

# Función de logging en BD
log_to_db() {
    local tipo_evento=$1
    local descripcion=$2
    local exitoso=$3
    
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << EOF 2>/dev/null
INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent, accion)
VALUES (NULL, '$tipo_evento', '$descripcion', $exitoso, 'SISTEMA', 'CRON/SCRIPT', 'restore');
EOF
}

# Función de logging
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Función de error
error() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" | tee -a "$LOG_FILE"
    # Registrar error en auditoría
    log_to_db "backup" "Error en restauración: $1" 0
    exit 1
}

# Validar parámetros
if [ -z "$1" ]; then
    error "Uso: $0 /ruta/al/backup.zip"
fi

BACKUP_FILE="$1"

# Validar que el archivo existe
if [ ! -f "$BACKUP_FILE" ]; then
    error "Archivo de backup no encontrado: $BACKUP_FILE"
fi

# Validar que es un ZIP
if ! file "$BACKUP_FILE" | grep -q "Zip archive"; then
    error "El archivo no es un ZIP válido"
fi

log "=== Iniciando restauración del PIM ==="
log "Archivo backup: $BACKUP_FILE"
log "Base de datos: $DB_NAME"

# 1. Crear directorio temporal para extracción
TEMP_DIR="/tmp/pim-restore-$$"
mkdir -p "$TEMP_DIR"
log "Directorio temporal: $TEMP_DIR"

# 2. Extraer ZIP
log "Extrayendo backup..."
if ! unzip -q "$BACKUP_FILE" -d "$TEMP_DIR"; then
    rm -rf "$TEMP_DIR"
    error "Fallo al extraer el archivo ZIP"
fi
log "✓ Archivo extraído"

# 3. Buscar el archivo SQL comprimido
SQLGZ_FILE=$(find "$TEMP_DIR" -name "database.sql.gz" -type f | head -1)
if [ -z "$SQLGZ_FILE" ]; then
    rm -rf "$TEMP_DIR"
    error "No se encontró database.sql.gz en el backup"
fi

# 4. Descomprimir SQL
log "Descomprimiendo base de datos..."
SQL_FILE="$TEMP_DIR/database.sql"
if ! gunzip -c "$SQLGZ_FILE" > "$SQL_FILE"; then
    rm -rf "$TEMP_DIR"
    error "Fallo al descomprimir la base de datos"
fi
log "✓ Base de datos descomprimida"

# 5. Crear backup de seguridad de la BD actual
log "Creando backup de seguridad de la BD actual..."
SAFETY_BACKUP="/tmp/pim_safety_backup_$(date '+%Y-%m-%d_%H%M%S').sql"
if ! mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$SAFETY_BACKUP" 2>/dev/null; then
    log "⚠ Advertencia: No se pudo crear backup de seguridad"
else
    log "✓ Backup de seguridad creado: $SAFETY_BACKUP"
fi

# 6. Restaurar base de datos
log "Restaurando base de datos (esto puede tomar un momento)..."
if ! mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE" 2>/dev/null; then
    rm -rf "$TEMP_DIR"
    error "Fallo al restaurar la base de datos"
fi
log "✓ Base de datos restaurada"

# 7. Restaurar archivos subidos (si existen)
if [ -d "$TEMP_DIR/uploads" ]; then
    log "Restaurando archivos subidos..."
    if cp -r "$TEMP_DIR/uploads"/* /opt/PIM/assets/uploads/ 2>/dev/null; then
        log "✓ Archivos restaurados"
    else
        log "⚠ Advertencia: No se pudieron restaurar todos los archivos"
    fi
fi

# 8. Limpiar directorio temporal
rm -rf "$TEMP_DIR"
log "✓ Archivos temporales eliminados"

log "=== Restauración completada exitosamente ==="
log "La base de datos ha sido restaurada desde: $(basename $BACKUP_FILE)"
log ""

# Registrar en auditoría
log_to_db "backup" "Restauración completada desde: $(basename $BACKUP_FILE)" 1
