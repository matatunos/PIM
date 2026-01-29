#!/bin/bash

#############################################################################
# PIM Database Backup Script
# Realiza backup completo de BD + archivos subidos con compresión
# Uso: ./backup-db.sh [/ruta/destino] [dias_retencion]
# Ejemplo: ./backup-db.sh /backups/pim 30
#############################################################################

set -e

# Configuración
DB_HOST="localhost"
DB_USER="root"
DB_PASS="satriani"
DB_NAME="pim_db"

# Parámetros
BACKUP_DIR="${1:-/backups/pim}"
RETENTION_DAYS="${2:-30}"
PIM_HOME="/opt/PIM"
LOG_FILE="/var/log/pim-backup.log"

# Crear directorio de destino si no existe
mkdir -p "$BACKUP_DIR"

# Función de logging en BD
log_to_db() {
    local tipo_evento=$1
    local descripcion=$2
    local exitoso=$3
    
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" << EOF 2>/dev/null
INSERT INTO logs_acceso (usuario_id, tipo_evento, descripcion, exitoso, ip_address, user_agent, accion)
VALUES (NULL, '$tipo_evento', '$descripcion', $exitoso, 'SISTEMA', 'CRON/SCRIPT', 'backup');
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
    log_to_db "backup" "Error en backup automático: $1" 0
    exit 1
}

# Iniciar backup
TIMESTAMP=$(date '+%Y-%m-%d_%H%M%S')
BACKUP_NAME="pim-backup-${TIMESTAMP}"
TEMP_DIR="/tmp/${BACKUP_NAME}"
FINAL_ZIP="${BACKUP_DIR}/${BACKUP_NAME}.zip"

log "=== Iniciando backup del PIM ==="
log "Destino: $BACKUP_DIR"
log "Retención: $RETENTION_DAYS días"

# Crear directorio temporal
mkdir -p "$TEMP_DIR"

# 1. Backup de BD con mysqldump
log "Realizando dump de base de datos..."
if ! mysqldump -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$TEMP_DIR/database.sql" 2>/dev/null; then
    error "Fallo al hacer dump de base de datos"
fi
log "✓ Database dump completado"

# 2. Comprimir SQL
log "Comprimiendo SQL..."
gzip -f "$TEMP_DIR/database.sql"
log "✓ SQL comprimido: database.sql.gz"

# 3. Copiar archivos subidos
log "Copiando archivos subidos..."
if [ -d "$PIM_HOME/assets/uploads" ]; then
    cp -r "$PIM_HOME/assets/uploads" "$TEMP_DIR/" 2>/dev/null || true
    log "✓ Archivos copiados"
else
    log "⚠ Directorio de uploads no encontrado"
fi

# 4. Crear metadatos
log "Creando metadata..."
cat > "$TEMP_DIR/metadata.json" <<EOF
{
  "backup_date": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "pim_home": "$PIM_HOME",
  "database": "$DB_NAME",
  "retention_days": $RETENTION_DAYS,
  "hostname": "$(hostname)",
  "backup_script_version": "1.0"
}
EOF
log "✓ Metadata creado"

# 5. Crear ZIP final
log "Creando archivo ZIP final..."
(cd /tmp && if ! zip -r -q "$FINAL_ZIP" "$BACKUP_NAME"; then
    error "Fallo al crear archivo ZIP"
fi)
log "✓ ZIP creado: $(basename $FINAL_ZIP)"

# Obtener tamaño
BACKUP_SIZE=$(du -h "$FINAL_ZIP" | cut -f1)
log "Tamaño del backup: $BACKUP_SIZE"

# 6. Limpiar directorio temporal
rm -rf "$TEMP_DIR"
log "✓ Archivos temporales eliminados"

# 7. Limpiar backups antiguos
log "Limpiando backups más antiguos de $RETENTION_DAYS días..."
DELETED_COUNT=0
while IFS= read -r file; do
    rm -f "$file"
    log "  Eliminado: $(basename $file)"
    ((DELETED_COUNT++))
done < <(find "$BACKUP_DIR" -name "pim-backup-*.zip" -type f -mtime +$RETENTION_DAYS)

if [ $DELETED_COUNT -eq 0 ]; then
    log "  No hay backups antiguos para eliminar"
else
    log "✓ $DELETED_COUNT backups antiguos eliminados"
fi

# 8. Resumen
TOTAL_BACKUPS=$(find "$BACKUP_DIR" -name "pim-backup-*.zip" -type f | wc -l)
TOTAL_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)

log "=== Backup completado exitosamente ==="
log "Archivo: $(basename $FINAL_ZIP)"
log "Tamaño: $BACKUP_SIZE"
log "Backups totales: $TOTAL_BACKUPS"
log "Espacio usado: $TOTAL_SIZE"
log ""

# Registrar en auditoría
log_to_db "backup" "Backup automático completado: $(basename $FINAL_ZIP) - Tamaño: $BACKUP_SIZE" 1
