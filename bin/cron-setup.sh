#!/bin/bash

#############################################################################
# PIM Cron Setup Script
# Configura backups automáticos en crontab
# Uso: sudo ./cron-setup.sh [frecuencia]
# Opciones: daily (2am), weekly (lunes 2am), monthly (1º 2am)
#############################################################################

set -e

FREQUENCY="${1:-daily}"
BACKUP_SCRIPT="/opt/PIM/bin/backup-db.sh"
BACKUP_DIR="/backups/pim"
CRON_USER="${SUDO_USER:-$(whoami)}"

# Validar que el script existe
if [ ! -f "$BACKUP_SCRIPT" ]; then
    echo "ERROR: Script de backup no encontrado en $BACKUP_SCRIPT"
    exit 1
fi

# Crear directorio de backups
mkdir -p "$BACKUP_DIR"
echo "✓ Directorio de backups creado: $BACKUP_DIR"

# Crear directorio de logs si no existe
mkdir -p /var/log
echo "✓ Directorio de logs listo"

# Determinar cron expression
case $FREQUENCY in
    daily)
        CRON_EXPR="0 2 * * *"
        DESCRIPTION="Diario a las 2:00 AM"
        ;;
    weekly)
        CRON_EXPR="0 2 * * 1"
        DESCRIPTION="Lunes a las 2:00 AM"
        ;;
    monthly)
        CRON_EXPR="0 2 1 * *"
        DESCRIPTION="1º de cada mes a las 2:00 AM"
        ;;
    *)
        echo "Opción desconocida: $FREQUENCY"
        echo "Opciones válidas: daily, weekly, monthly"
        exit 1
        ;;
esac

# Crear entrada de cron
CRON_JOB="$CRON_EXPR $BACKUP_SCRIPT $BACKUP_DIR 30 > /dev/null 2>&1"

# Verificar si la entrada ya existe
if crontab -l 2>/dev/null | grep -q "$BACKUP_SCRIPT"; then
    echo "⚠ Ya existe una entrada de cron para este script"
    echo "  Removiendo entrada anterior..."
    (crontab -l 2>/dev/null | grep -v "$BACKUP_SCRIPT" | crontab -) || true
fi

# Agregar nueva entrada
echo "Agregando entrada a crontab..."
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
echo "✓ Cron configurado: $DESCRIPTION"

echo ""
echo "=== Resumen de configuración ==="
echo "Frecuencia: $DESCRIPTION"
echo "Expresión cron: $CRON_EXPR"
echo "Script: $BACKUP_SCRIPT"
echo "Directorio: $BACKUP_DIR"
echo "Retención: 30 días"
echo "Logs: /var/log/pim-backup.log"
echo ""
echo "Para ver cron jobs: crontab -l"
echo "Para editar: crontab -e"
echo ""

# Crear archivo de permiso de escritura en log
if [ ! -f /var/log/pim-backup.log ]; then
    touch /var/log/pim-backup.log
    chmod 666 /var/log/pim-backup.log
    echo "✓ Archivo de log creado"
fi

echo "✓ Setup completado exitosamente"
