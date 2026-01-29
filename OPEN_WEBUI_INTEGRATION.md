# 🚀 Integración PIM + Open WebUI

Guía completa de instalación, configuración y uso de la integración entre PIM y Open WebUI (con Ollama).

## 📋 Tabla de Contenidos

1. [Requerimientos](#requerimientos)
2. [Instalación Rápida](#instalación-rápida)
3. [Configuración Manual](#configuración-manual)
4. [Archivos Creados](#archivos-creados)
5. [Uso](#uso)
6. [Seguridad](#seguridad)
7. [Troubleshooting](#troubleshooting)

---

## 📦 Requerimientos

- **PIM** instalado y funcionando en `localhost`
- **Open WebUI + Ollama** instalado en `192.168.1.19` (o tu servidor)
- **MySQL/MariaDB** con acceso a la BD de PIM
- **Bash 4+** para scripts
- **cURL** para llamadas HTTP
- **jq** para procesamiento JSON
- **Permisos sudo** en el servidor donde está PIM (para cron)

### Verificar dependencias

```bash
# Verificar bash
bash --version

# Verificar curl
curl --version

# Verificar jq
jq --version

# Verificar mysql
mysql --version
```

---

## 🚀 Instalación Rápida

### Opción 1: Script Automático (Recomendado)

```bash
# 1. Ir a directorio PIM
cd /opt/PIM

# 2. Ejecutar script de setup
sudo bash bin/setup-openwebui-sync.sh

# Seguir las instrucciones interactivas
```

El script automático:
- ✅ Genera `JWT_SECRET` seguro
- ✅ Pregunta por host/puerto de Open WebUI
- ✅ Valida conectividad
- ✅ Actualiza `.env`
- ✅ Crea entrada en crontab
- ✅ Configura tabla en BD

### Opción 2: Manual

Ver sección [Configuración Manual](#configuración-manual)

---

## 🔧 Configuración Manual

### Paso 1: Actualizar `.env`

```bash
cd /opt/PIM

# Editar .env
nano .env
```

Agregar/actualizar:

```env
# Seguridad para JWT
JWT_SECRET=your-super-secret-jwt-key-generate-with-openssl

# API Key de Open WebUI (generado en Settings > API Keys)
OPENWEBUI_API_KEY=sk-your-openwebui-key-here

# Base de datos (si no está configurada)
DB_HOST=localhost
DB_NAME=pim_db
DB_USER=pim_user
DB_PASS=your-password
```

**Generar JWT_SECRET seguro:**

```bash
openssl rand -base64 32
```

### Paso 2: Obtener API Key de Open WebUI

1. Accede a Open WebUI: `http://192.168.1.19:3000`
2. Ve a **Settings** → **API Keys**
3. Haz clic en **+ Create New API Key**
4. Copia la clave y pégala en `.env` como `OPENWEBUI_API_KEY`

### Paso 3: Configurar en BD (tabla `configuracion_ia`)

Ejecutar en tu gestor de BD o con mysql:

```bash
mysql -u root -p pim_db <<EOF
INSERT INTO configuracion_ia (clave, valor, tipo, descripcion) VALUES
('openwebui_host', '192.168.1.19', 'string', 'Host de Open WebUI'),
('openwebui_port', '3000', 'int', 'Puerto de Open WebUI'),
('sync_interval_minutes', '5', 'int', 'Intervalo de sincronización'),
('sync_enabled', '1', 'bool', 'Sincronización habilitada'),
('sync_documents', '1', 'bool', 'Sincronizar documentos'),
('sync_notes', '1', 'bool', 'Sincronizar notas')
ON DUPLICATE KEY UPDATE
    valor = VALUES(valor),
    tipo = VALUES(tipo),
    descripcion = VALUES(descripcion);
EOF
```

### Paso 4: Configurar Cron

```bash
# Crear archivo cron
sudo tee /etc/cron.d/pim-sync-openwebui > /dev/null <<EOF
# PIM Open WebUI Synchronization - Sincronización cada 5 minutos
*/5 * * * * root /opt/PIM/bin/sync-openwebui.sh >> /opt/PIM/logs/cron-sync.log 2>&1
EOF

# Dar permisos
sudo chmod 644 /etc/cron.d/pim-sync-openwebui
```

---

## 📁 Archivos Creados

| Archivo | Descripción |
|---------|------------|
| `/api/ai-documents.php` | API endpoint que expone documentos y notas |
| `/app/ai-assistant.php` | Widget modal de chat con Open WebUI |
| `/app/admin/test-openwebui.php` | Test de conectividad a Open WebUI |
| `/bin/sync-openwebui.sh` | Script bash de sincronización automática |
| `/bin/setup-openwebui-sync.sh` | Script interactivo de instalación |
| `db/schema.sql` | Nuevas tablas: `configuracion_ia`, `chat_sessions`, `sync_history` |
| `.env` | Variables de entorno (JWT_SECRET, OPENWEBUI_API_KEY) |

### Nuevas Tablas en BD

```sql
-- Configuración de Open WebUI
CREATE TABLE configuracion_ia (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(100) NOT NULL UNIQUE,
    valor TEXT,
    tipo VARCHAR(50) DEFAULT 'string',
    descripcion TEXT,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Historial de sesiones de chat
CREATE TABLE chat_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255),
    resumen TEXT,
    modelo VARCHAR(100),
    tokens_utilizados INT DEFAULT 0,
    activo BOOLEAN DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- Historial de sincronización
CREATE TABLE sync_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo ENUM('documento','nota') DEFAULT 'documento',
    origen_id INT,
    status ENUM('success','failed','pending') DEFAULT 'pending',
    mensaje TEXT,
    documentos_procesados INT DEFAULT 0,
    errores_count INT DEFAULT 0,
    duracion_segundos FLOAT DEFAULT 0,
    sincronizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 💬 Uso

### Acceder al Chat IA

**URL:** `http://localhost/app/ai-assistant.php`

O desde el menú sidebar: **IA & Chat** → **Chat IA**

### Características

✨ **Características disponibles:**

- 🤖 Chat con IA powered by Ollama
- 📄 Acceso automático a tus documentos
- 📝 Acceso automático a tus notas
- 🔐 Autenticación JWT firmada
- 💾 Historial de sesiones guardado
- 🔄 Sincronización automática cada X minutos

### Configuración Avanzada

**URL:** `http://localhost/app/admin/configuracion.php` (solo admin)

Opciones disponibles:
- Host y puerto de Open WebUI
- Intervalo de sincronización
- Habilitar/deshabilitar sincronización
- Probar conexión
- Ver logs

---

## 🔐 Seguridad

### Autenticación JWT

- Los tokens se firman con `JWT_SECRET` del `.env`
- Expiran después de 8 horas
- Contienen `user_id` y `username`
- Se validan en cada request

### Rate Limiting

- API `/api/ai-documents.php`: máximo 10 requests/min por usuario
- Almacenado en sesión PHP

### API Key de Open WebUI

- Se almacena en `.env` (fuera del git)
- Se utiliza solo en el script de sincronización
- Se envía en header `Authorization: Bearer`

### Logging

- Todos los eventos se registran en `security_logs`
- Historial de sincronización en `sync_history`
- Logs bash en `/opt/PIM/logs/sync-openwebui.log`
- Logs cron en `/opt/PIM/logs/cron-sync.log`

### Headers de Seguridad

```php
// Automáticamente agregados en /api/ai-documents.php
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
// CSP, CSRF, XSS prevention habilitados en config general
```

---

## 🧪 Testing

### Probar API manualmente

```bash
# Obtener documentos del usuario
curl -b "PHPSESSID=your-session-id" \
  http://localhost/api/ai-documents.php?action=get_documents

# Obtener notas
curl -b "PHPSESSID=your-session-id" \
  http://localhost/api/ai-documents.php?action=get_notes

# Buscar contenido
curl -b "PHPSESSID=your-session-id" \
  "http://localhost/api/ai-documents.php?action=search&q=termine"
```

### Ejecutar script de sincronización manualmente

```bash
# Probar script
/opt/PIM/bin/sync-openwebui.sh

# Ver logs
tail -f /opt/PIM/logs/sync-openwebui.log

# Ver logs de cron
tail -f /opt/PIM/logs/cron-sync.log
```

### Validar conectividad con Open WebUI

Desde el panel de administración: **Configuración** → **Integración IA** → **Probar Conexión**

O manualmente:

```bash
# Verificar que Open WebUI responde
curl -I http://192.168.1.19:3000/

# Verificar health endpoint
curl http://192.168.1.19:3000/api/health
```

---

## 🔍 Troubleshooting

### "Open WebUI no está configurado"

**Problema:** El widget muestra mensaje de "No configurado"

**Solución:**
1. Accede a `/app/admin/configuracion.php`
2. Completa la sección "Integración Open WebUI"
3. Haz clic en "Probar Conexión"
4. Guarda la configuración

### "No se puede conectar a Open WebUI"

**Problema:** Error `Connection refused` o timeout

**Solución:**
```bash
# 1. Verificar que Open WebUI está corriendo
curl http://192.168.1.19:3000/

# 2. Verificar firewall
ping 192.168.1.19
telnet 192.168.1.19 3000

# 3. Verificar host/puerto en configuración
mysql -e "SELECT * FROM configuracion_ia WHERE clave LIKE 'openwebui%';"

# 4. Cambiar host si es necesario
# En configuración o directamente en BD:
mysql -e "UPDATE configuracion_ia SET valor='localhost' WHERE clave='openwebui_host';"
```

### "Rate limit exceeded"

**Problema:** Error HTTP 429

**Solución:**
- Espera 1 minuto antes de hacer más requests
- Comprueba que no hay scripts que hagan requests constantemente
- Aumenta el límite en `/api/ai-documents.php` si es necesario

### "JWT inválido o expirado"

**Problema:** Error al conectar con Open WebUI

**Solución:**
1. Verifica que `JWT_SECRET` es igual en `.env` de PIM y en Open WebUI (si necesario)
2. Regenera JWT_SECRET y reinicia sesión
3. Limpia cookies del navegador

### "Script de sincronización no funciona"

**Problema:** Sincronización no se ejecuta automáticamente

**Solución:**
```bash
# 1. Verificar que cron está corriendo
sudo service cron status

# 2. Verificar entrada en crontab
sudo cat /etc/cron.d/pim-sync-openwebui

# 3. Probar script manualmente
sudo /opt/PIM/bin/sync-openwebui.sh

# 4. Ver logs
tail -50 /opt/PIM/logs/sync-openwebui.log

# 5. Verificar permisos
ls -la /opt/PIM/bin/sync-openwebui.sh
```

### "Error: configuracion_ia table doesn't exist"

**Problema:** Script ejecutado antes de crear tablas

**Solución:**
```bash
# 1. Crear tablas manualmente
mysql pim_db < /opt/PIM/db/schema.sql

# 2. O ejecutar setup nuevamente
sudo /opt/PIM/bin/setup-openwebui-sync.sh
```

---

## 📊 Logs y Monitoreo

### Ver logs de sincronización

```bash
# Logs del script
tail -f /opt/PIM/logs/sync-openwebui.log

# Logs de cron
tail -f /opt/PIM/logs/cron-sync.log

# Logs de seguridad
mysql -e "SELECT * FROM security_logs WHERE event_type LIKE 'AI%' ORDER BY created_at DESC LIMIT 20;"

# Historial de sincronización
mysql -e "SELECT * FROM sync_history ORDER BY sincronizado_en DESC LIMIT 20;"
```

### Monitorear ejecución de cron

```bash
# Ver cuándo fue la última ejecución
stat /opt/PIM/logs/cron-sync.log

# Ver si hay errores
grep "ERROR\|FAIL" /opt/PIM/logs/cron-sync.log

# Ver resumen de sincronizaciones
grep "SUCCESS\|FAIL" /opt/PIM/logs/sync-openwebui.log
```

---

## 📞 Soporte

### Verificar versión de PIM

```bash
grep "VERSION\|version" /opt/PIM/README.md
```

### Recopilar información para debug

```bash
# Script de diagnóstico
bash << 'EOF'
echo "=== PIM Open WebUI Integration Diagnostics ==="
echo ""
echo "1. Sistema:"
uname -a
echo ""
echo "2. Versiones:"
php --version
mysql --version
curl --version
jq --version
echo ""
echo "3. Directorio PIM:"
ls -la /opt/PIM/
echo ""
echo "4. Archivos necesarios:"
ls -la /opt/PIM/api/ai-documents.php /opt/PIM/app/ai-assistant.php /opt/PIM/bin/sync-openwebui.sh
echo ""
echo "5. Configuración .env:"
grep -E "JWT_SECRET|OPENWEBUI" /opt/PIM/.env | grep -v "^$"
echo ""
echo "6. Configuración en BD:"
mysql -u root -e "SELECT clave, valor FROM pim_db.configuracion_ia WHERE clave LIKE 'openwebui%';"
echo ""
echo "7. Cron job:"
sudo cat /etc/cron.d/pim-sync-openwebui
echo ""
echo "8. Últimas líneas de log:"
tail -20 /opt/PIM/logs/sync-openwebui.log
EOF
```

---

## 🎯 Casos de Uso

### Caso 1: Buscar información en documentos con IA

1. Abre **Chat IA** desde el sidebar
2. Pregunta: "¿Qué dice el documento sobre..."
3. La IA accede automáticamente a tus documentos sincronizados
4. Recibe respuesta basada en el contenido

### Caso 2: Generar resumen de tareas

1. Abre **Chat IA**
2. Pregunta: "Resúmeme mis tareas pendientes"
3. La IA lee tus notas y documentos relevantes
4. Genera un resumen automático

### Caso 3: Análisis de contactos

1. Exporta contactos desde **Contactos**
2. Sincronización automática ingiera los datos
3. Pregunta al chat: "¿Qué contactos son de..."
4. Obtén respuestas basadas en tus datos

---

## 📝 Notas

- La sincronización es **unidireccional** (PIM → Open WebUI)
- Los documentos se sincronizan como **texto** (no archivos binarios completos)
- Cada usuario solo puede acceder a sus propios documentos
- El historial de chat se guarda en tabla `chat_sessions` de PIM

---

## 📄 Licencia

Esta integración es parte del proyecto **PIM**. Ver `LICENSE` para detalles.

---

**Última actualización:** 29 de enero de 2026  
**Versión:** 1.0.0
